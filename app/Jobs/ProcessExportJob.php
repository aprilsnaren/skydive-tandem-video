<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\Upload;
use App\Services\BrevoMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes

    public int $tries = 3; // allow up to 3 attempts so an orphaned job (killed worker) can recover

    public function __construct(
        private readonly int $exportId,
        private readonly array $clips,
        private readonly ?string $musicUuid,
        private readonly ?string $logoUuid = null,
    ) {}

    public function handle(): void
    {
        $export = Export::findOrFail($this->exportId);
        $export->update(['status' => 'processing', 'status_message' => 'Starting…']);

        $tmpDir = storage_path('app/tmp/' . $export->uuid);
        @mkdir($tmpDir, 0755, true);

        try {
            $concatFile = $tmpDir . '/concat.mp4';
            $musicFile  = $tmpDir . '/with_music.mp4';
            $logoFile   = $tmpDir . '/with_logo.mp4';
            $slug       = $export->guest_name ? \Illuminate\Support\Str::slug($export->guest_name) . '_' : '';
            $outputFile = 'exports/' . $slug . $export->uuid . '.mp4';
            $outputPath = storage_path('app/' . $outputFile);

            @mkdir(storage_path('app/exports'), 0755, true);

            // ----------------------------------------------------------------
            // Step 1 — Trim every clip and concatenate them
            // ----------------------------------------------------------------
            $clipCount = count($this->clips);
            $export->update(['status_message' => "Trimming and joining {$clipCount} clip" . ($clipCount === 1 ? '' : 's') . '…']);
            $this->concatClips($this->clips, $concatFile);

            $workingFile = $concatFile;

            // ----------------------------------------------------------------
            // Step 2 — Mix in background music (optional)
            // ----------------------------------------------------------------
            if ($this->musicUuid) {
                $export->update(['status_message' => 'Mixing in background music…']);
                $musicUpload = Upload::where('uuid', $this->musicUuid)->firstOrFail();
                $musicPath   = $this->storagePath($musicUpload->path);
                $this->mixMusic($workingFile, $musicPath, $musicFile);
                $workingFile = $musicFile;
            }

            // ----------------------------------------------------------------
            // Step 3 — Append logo end-card (optional)
            // ----------------------------------------------------------------
            if ($this->logoUuid) {
                $export->update(['status_message' => 'Appending logo end card…']);
                $logoPath = $this->storagePath(Upload::where('uuid', $this->logoUuid)->value('path'));
                $duration = (int) config('videoedit.logo_duration', 10);
                $this->appendLogoSplash($workingFile, $logoPath, $duration, $logoFile);
                $workingFile = $logoFile;
            }

            // ----------------------------------------------------------------
            // Step 4 — Final encode with faststart
            // ----------------------------------------------------------------
            $export->update(['status_message' => 'Encoding final video…']);
            $this->finalEncode($workingFile, $outputPath);

            $export->update([
                'status'         => 'done',
                'status_message' => null,
                'path'           => $outputFile,
                'expires_at'     => now()->addDays(7),
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessExportJob failed', [
                'export_id' => $this->exportId,
                'error'     => $e->getMessage(),
            ]);

            $export->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Clean up any partial output file immediately
            if (isset($outputPath) && file_exists($outputPath)) {
                @unlink($outputPath);
            }
        } finally {
            $this->cleanupTmp($tmpDir);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function concatClips(array $clips, string $outputPath): void
    {
        $w = config('videoedit.output_width',  1920);
        $h = config('videoedit.output_height', 1080);

        // Scale+pad filter: fit clip inside target box, pad remainder with black
        $normalize = "scale={$w}:{$h}:force_original_aspect_ratio=decrease,"
                   . "pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2,setsar=1";

        if (count($clips) === 1) {
            $clip     = $clips[0];
            $upload   = Upload::where('uuid', $clip['uuid'])->firstOrFail();
            $input    = $this->storagePath($upload->path);
            $duration = (float) $clip['trim_end'] - (float) $clip['trim_start'];

            $this->ffmpeg(sprintf(
                '-y -ss %s -t %s -i %s -vf %s -c:v libx264 -preset fast -crf 23 -c:a aac %s',
                escapeshellarg((string) $clip['trim_start']),
                escapeshellarg((string) $duration),
                escapeshellarg($input),
                escapeshellarg($normalize),
                escapeshellarg($outputPath),
            ));

            return;
        }

        // Multiple clips — normalize each, then concat
        $inputs        = '';
        $scaleFilters  = '';
        $concatStreams  = '';
        $n             = count($clips);

        foreach ($clips as $i => $clip) {
            $upload   = Upload::where('uuid', $clip['uuid'])->firstOrFail();
            $input    = $this->storagePath($upload->path);
            $start    = (float) $clip['trim_start'];
            $duration = (float) $clip['trim_end'] - $start;

            $inputs .= sprintf(
                ' -ss %s -t %s -i %s',
                escapeshellarg((string) $start),
                escapeshellarg((string) $duration),
                escapeshellarg($input),
            );

            $scaleFilters .= "[{$i}:v]{$normalize}[v{$i}];";
            $concatStreams .= "[v{$i}][{$i}:a]";
        }

        $filter = $scaleFilters . $concatStreams . "concat=n={$n}:v=1:a=1[v][a]";

        $this->ffmpeg(sprintf(
            '-y %s -filter_complex %s -map [v] -map [a] -c:v libx264 -preset fast -crf 23 -c:a aac %s',
            $inputs,
            escapeshellarg($filter),
            escapeshellarg($outputPath),
        ));
    }

    private function appendLogoSplash(string $inputPath, string $logoPath, int $duration, string $outputPath): void
    {
        $w = config('videoedit.output_width',  1920);
        $h = config('videoedit.output_height', 1080);

        // Build a $duration-second video from the image, scaled+padded to match output resolution
        $logoVideoFile = dirname($outputPath) . '/logo_card.mp4';

        $this->ffmpeg(sprintf(
            '-y -loop 1 -i %s -vf %s -t %d -c:v libx264 -preset fast -crf 23 -pix_fmt yuv420p -an %s',
            escapeshellarg($logoPath),
            escapeshellarg("scale={$w}:{$h}:force_original_aspect_ratio=decrease,pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1"),
            $duration,
            escapeshellarg($logoVideoFile),
        ));

        // Concat main video + logo card; logo card has no audio so we pad silence
        // Input order: 0=mainVideo, 1=anullsrc (audio only), 2=logoVideo
        $filter = '[0:v][0:a][2:v][1:a]concat=n=2:v=1:a=1[v][a]';

        // Add a silent audio stream to the logo card so concat works
        $this->ffmpeg(sprintf(
            '-y -i %s -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=44100 -i %s '
            . '-filter_complex %s -map [v] -map [a] -c:v libx264 -preset fast -crf 23 -c:a aac -shortest %s',
            escapeshellarg($inputPath),
            escapeshellarg($logoVideoFile),
            escapeshellarg($filter),
            escapeshellarg($outputPath),
        ));

        @unlink($logoVideoFile);
    }

    private function finalEncode(string $inputPath, string $outputPath): void
    {
        $this->ffmpeg(sprintf(
            '-y -i %s -c:v libx264 -preset fast -crf 23 -movflags +faststart -c:a aac %s',
            escapeshellarg($inputPath),
            escapeshellarg($outputPath),
        ));
    }

    private function mixMusic(string $inputPath, string $musicPath, string $outputPath): void
    {
        // amix: blend existing audio with music; duration=first caps music to video length
        $filter = '[0:a][1:a]amix=inputs=2:duration=first:dropout_transition=2[outa]';

        $this->ffmpeg(sprintf(
            '-y -i %s -i %s -filter_complex %s -map 0:v -map [outa] -c:v copy -c:a aac %s',
            escapeshellarg($inputPath),
            escapeshellarg($musicPath),
            escapeshellarg($filter),
            escapeshellarg($outputPath),
        ));
    }

    private function burnWatermark(string $inputPath, string $watermarkPath, string $outputPath): void
    {
        if (!file_exists($watermarkPath)) {
            // No watermark — encode without overlay
            $this->ffmpeg(sprintf(
                '-y -i %s -c:v libx264 -preset fast -crf 23 -movflags +faststart -c:a aac %s',
                escapeshellarg($inputPath),
                escapeshellarg($outputPath),
            ));
            return;
        }

        $duration = (int) config('videoedit.watermark_duration', 10);

        // Show watermark at bottom-right for $duration seconds (0 = whole video)
        $enableExpr = $duration > 0 ? "between(t,0,{$duration})" : '1';
        $filter = "[0:v][1:v]overlay=W-w-20:H-h-20:enable='{$enableExpr}'[outv]";

        $this->ffmpeg(sprintf(
            '-y -i %s -i %s -filter_complex %s -map [outv] -map 0:a -c:v libx264 -preset fast -crf 23 -movflags +faststart -c:a aac %s',
            escapeshellarg($inputPath),
            escapeshellarg($watermarkPath),
            escapeshellarg($filter),
            escapeshellarg($outputPath),
        ));
    }

    private function ffmpeg(string $args): void
    {
        $bin    = config('videoedit.ffmpeg', 'ffmpeg');
        $cmd    = $bin . ' ' . $args . ' 2>&1';
        $output = [];
        $code   = 0;

        exec($cmd, $output, $code);

        if ($code !== 0) {
            throw new RuntimeException(
                'FFmpeg failed (exit ' . $code . '): ' . implode("\n", array_slice($output, -20))
            );
        }
    }

    /**
     * Resolve a stored upload path to an absolute filesystem path.
     * Tries storage/app/{path} first (new convention), then falls back to
     * storage/app/private/{path} (Laravel 11 Storage::disk('local') default)
     * for records created before the path fix.
     */
    private function storagePath(string $relativePath): string
    {
        $primary = storage_path('app/' . $relativePath);
        if (file_exists($primary)) {
            return $primary;
        }

        $fallback = storage_path('app/private/' . $relativePath);
        if (file_exists($fallback)) {
            return $fallback;
        }

        // Return primary so the error message shows the expected path
        return $primary;
    }

    /**
     * Resolve a config path that may be relative to the Laravel base path
     * or an absolute path.
     */
    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    private function cleanupTmp(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($dir);
    }
}
