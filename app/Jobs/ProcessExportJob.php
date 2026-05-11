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
            $slug       = $export->guest_name ? \Illuminate\Support\Str::slug($export->guest_name) . '_' : '';
            $outputFile = 'exports/' . $slug . $export->uuid . '.mp4';
            $outputPath = storage_path('app/' . $outputFile);

            @mkdir(storage_path('app/exports'), 0755, true);

            $clipCount = count($this->clips);
            $musicPath = null;
            if ($this->musicUuid) {
                $musicUpload = Upload::where('uuid', $this->musicUuid)->firstOrFail();
                $musicPath   = $this->storagePath($musicUpload->path);
            }

            if ($this->logoUuid) {
                // ----------------------------------------------------------------
                // Single FFmpeg pass: clips + logo end-card + optional music → output
                // This avoids ever writing concat.mp4 to disk, eliminating the peak
                // disk usage of 2× the output size that caused ENOSPC in production.
                // ----------------------------------------------------------------
                $logoUpload   = Upload::where('uuid', $this->logoUuid)->firstOrFail();
                $logoPath     = $this->storagePath($logoUpload->path);
                $logoDuration = (int) config('videoedit.logo_duration', 10);

                $statusMsg = "Trimming and joining {$clipCount} clip" . ($clipCount === 1 ? '' : 's')
                           . ' and appending logo end card';
                if ($musicPath) $statusMsg .= ' and mixing music';
                $export->update(['status_message' => $statusMsg . '…']);

                $this->buildVideo($this->clips, $outputPath, $musicPath, $logoPath, $logoDuration);
            } else {
                // ----------------------------------------------------------------
                // Two-step: concat clips (+ optional music) → rename to output.
                // No logo means peak disk usage stays at 1× the output size.
                // ----------------------------------------------------------------
                $statusMsg = "Trimming and joining {$clipCount} clip" . ($clipCount === 1 ? '' : 's');
                if ($musicPath) $statusMsg .= ' and mixing music';
                $export->update(['status_message' => $statusMsg . '…']);

                $this->concatClips($this->clips, $concatFile, $musicPath);

                $export->update(['status_message' => 'Finalising video…']);
                $this->finalEncode($concatFile, $outputPath);
            }

            $export->update([
                'status'         => 'done',
                'status_message' => null,
                'path'           => $outputFile,
                'expires_at'     => now()->addDays(8),
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

    private function concatClips(array $clips, string $outputPath, ?string $musicPath = null): void
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
            $audioF   = $this->audioFilter($clip);

            if ($musicPath) {
                // Build audio chain: optionally apply volume filter first, then amix
                $aIn = $audioF ? "[0:a]{$audioF}[clipA];[clipA]" : '[0:a]';
                // Duck music to 50% when the clip has its own audio (full or range)
                $hasVideoAudio = ($clip['audio_mode'] ?? 'full') !== 'muted';
                if ($hasVideoAudio) {
                    $musicFilter = '[1:a]volume=0.5[musicA]';
                    $musicIn     = '[musicA]';
                } else {
                    $musicFilter = '';
                    $musicIn     = '[1:a]';
                }
                $filter = "[0:v]{$normalize}[v];" . ($hasVideoAudio ? "{$musicFilter};" : '') . "{$aIn}{$musicIn}amix=inputs=2:duration=first:dropout_transition=2[a]";
                $this->ffmpeg(sprintf(
                    '-y -ss %s -t %s -i %s -stream_loop -1 -i %s -filter_complex %s -map [v] -map [a] -c:v libx264 -preset fast -crf 23 -threads 2 -c:a aac %s',
                    escapeshellarg((string) $clip['trim_start']),
                    escapeshellarg((string) $duration),
                    escapeshellarg($input),
                    escapeshellarg($musicPath),
                    escapeshellarg($filter),
                    escapeshellarg($outputPath),
                ));
            } elseif ($audioF !== null) {
                $filter = "[0:v]{$normalize}[v];[0:a]{$audioF}[a]";
                $this->ffmpeg(sprintf(
                    '-y -ss %s -t %s -i %s -filter_complex %s -map [v] -map [a] -c:v libx264 -preset fast -crf 23 -threads 2 -c:a aac %s',
                    escapeshellarg((string) $clip['trim_start']),
                    escapeshellarg((string) $duration),
                    escapeshellarg($input),
                    escapeshellarg($filter),
                    escapeshellarg($outputPath),
                ));
            } else {
                $this->ffmpeg(sprintf(
                    '-y -ss %s -t %s -i %s -vf %s -c:v libx264 -preset fast -crf 23 -threads 2 -c:a aac %s',
                    escapeshellarg((string) $clip['trim_start']),
                    escapeshellarg((string) $duration),
                    escapeshellarg($input),
                    escapeshellarg($normalize),
                    escapeshellarg($outputPath),
                ));
            }

            return;
        }

        // Multiple clips — normalize each, concat, and optionally mix music — all in one pass
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

            $audioF = $this->audioFilter($clip);
            $scaleFilters .= "[{$i}:v]{$normalize}[v{$i}];";
            if ($audioF !== null) {
                $scaleFilters .= "[{$i}:a]{$audioF}[a{$i}];";
                $concatStreams .= "[v{$i}][a{$i}]";
            } else {
                $concatStreams .= "[v{$i}][{$i}:a]";
            }
        }

        if ($musicPath) {
            // Music is appended as input index $n; concat audio is piped into amix
            // Duck music to 50% when any clip has its own audio (full or range)
            $hasVideoAudio = collect($clips)->contains(fn($c) => ($c['audio_mode'] ?? 'full') !== 'muted');
            if ($hasVideoAudio) {
                $musicFilter = "[{$n}:a]volume=0.5[musicA];";
                $musicIn     = '[musicA]';
            } else {
                $musicFilter = '';
                $musicIn     = "[{$n}:a]";
            }
            $filter = $scaleFilters
                    . $concatStreams
                    . "concat=n={$n}:v=1:a=1[v][concata];{$musicFilter}[concata]{$musicIn}amix=inputs=2:duration=first:dropout_transition=2[a]";

            $this->ffmpeg(sprintf(
                '-y %s -stream_loop -1 -i %s -filter_complex %s -map [v] -map [a] -c:v libx264 -preset fast -crf 23 -threads 2 -c:a aac %s',
                $inputs,
                escapeshellarg($musicPath),
                escapeshellarg($filter),
                escapeshellarg($outputPath),
            ));
        } else {
            $filter = $scaleFilters . $concatStreams . "concat=n={$n}:v=1:a=1[v][a]";

            $this->ffmpeg(sprintf(
                '-y %s -filter_complex %s -map [v] -map [a] -c:v libx264 -preset fast -crf 23 -threads 2 -c:a aac %s',
                $inputs,
                escapeshellarg($filter),
                escapeshellarg($outputPath),
            ));
        }
    }

    /**
     * Build the complete output video in a single FFmpeg pass.
     * Handles N-clip concatenation, optional logo end-card, and optional music mix.
     * Writes directly to $outputPath with no intermediate files on disk.
     */
    private function buildVideo(
        array $clips,
        string $outputPath,
        ?string $musicPath,
        ?string $logoPath,
        int $logoDuration,
    ): void {
        $w = (int) config('videoedit.output_width',  1920);
        $h = (int) config('videoedit.output_height', 1080);
        $normalize = "scale={$w}:{$h}:force_original_aspect_ratio=decrease,"
                   . "pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2,setsar=1";

        $n           = count($clips);
        $inputArgs   = '';
        $filterParts = [];
        $idx         = 0;

        // ── Clip inputs ──────────────────────────────────────────────────────
        $concatInputs = ''; // interleaved [cvN][caN] pairs for the concat filter
        foreach ($clips as $clip) {
            $upload = Upload::where('uuid', $clip['uuid'])->firstOrFail();
            $path   = $this->storagePath($upload->path);
            $start  = (float) $clip['trim_start'];
            $dur    = (float) $clip['trim_end'] - $start;

            $inputArgs .= sprintf(
                ' -ss %s -t %s -i %s',
                escapeshellarg((string) $start),
                escapeshellarg((string) $dur),
                escapeshellarg($path),
            );
            $filterParts[] = "[{$idx}:v]{$normalize}[cv{$idx}]";
            $audioF = $this->audioFilter($clip);
            if ($audioF !== null) {
                $filterParts[] = "[{$idx}:a]{$audioF}[ca{$idx}]";
                $concatInputs .= "[cv{$idx}][ca{$idx}]";
            } else {
                $concatInputs .= "[cv{$idx}][{$idx}:a]";
            }
            $idx++;
        }

        // Concatenate clips → [joinv][joina]
        $filterParts[] = "{$concatInputs}concat=n={$n}:v=1:a=1[joinv][joina]";
        $outV = 'joinv';
        $outA = 'joina';

        // ── Logo end-card (optional) ─────────────────────────────────────────
        if ($logoPath !== null) {
            // Logo image input: loop for $logoDuration seconds at 25 fps
            $logoIdx = $idx++;
            $inputArgs .= sprintf(
                ' -loop 1 -framerate 25 -t %d -i %s',
                $logoDuration,
                escapeshellarg($logoPath),
            );
            // Silent stereo audio to pair with the silent logo image
            $nullIdx = $idx++;
            $inputArgs .= sprintf(
                ' -f lavfi -t %d -i anullsrc=channel_layout=stereo:sample_rate=48000',
                $logoDuration,
            );

            $filterParts[] = "[{$logoIdx}:v]{$normalize}[vlogo]";
            $filterParts[] = "[{$outV}][{$outA}][vlogo][{$nullIdx}:a]concat=n=2:v=1:a=1[withlogov][withlogoa]";
            $outV = 'withlogov';
            $outA = 'withlogoa';
        }

        // ── Music mix (optional) ─────────────────────────────────────────────
        if ($musicPath !== null) {
            $musicIdx = $idx++;
            $inputArgs .= ' -stream_loop -1 -i ' . escapeshellarg($musicPath);
            // Duck music to 50% when any clip has its own audio (full or range)
            $hasVideoAudio = collect($clips)->contains(fn($c) => ($c['audio_mode'] ?? 'full') !== 'muted');
            if ($hasVideoAudio) {
                $filterParts[] = "[{$musicIdx}:a]volume=0.5[musicA]";
                $filterParts[] = "[{$outA}][musicA]amix=inputs=2:duration=first:dropout_transition=2[mixeda]";
            } else {
                $filterParts[] = "[{$outA}][{$musicIdx}:a]amix=inputs=2:duration=first:dropout_transition=2[mixeda]";
            }
            $outA = 'mixeda';
        }

        $filterComplex = implode(';', $filterParts);

        $this->ffmpeg(sprintf(
            '-y %s -filter_complex %s -map [%s] -map [%s] -c:v libx264 -preset fast -crf 23 -threads 2 -c:a aac %s',
            $inputArgs,
            escapeshellarg($filterComplex),
            $outV,
            $outA,
            escapeshellarg($outputPath),
        ));
    }

    /**
     * Build a volume filter string for a clip's audio track, or null if no filter needed.
     *
     * audio_mode:
     *   'full'  — pass through unchanged (no filter)
     *   'muted' — silence the entire track
     *   'range' — keep audio only between audio_start and audio_end (seconds within
     *             the trimmed clip), silence everything outside that window
     *
     * FFmpeg volume expression uses gte/lte to avoid commas inside between().
     * The backslash before each comma escapes it for filter_complex parsing.
     */
    private function audioFilter(array $clip): ?string
    {
        $mode = $clip['audio_mode'] ?? 'full';

        if ($mode === 'muted') {
            return 'volume=0';
        }

        if ($mode === 'range') {
            $s = round((float) ($clip['audio_start'] ?? 0), 3);
            $e = round((float) ($clip['audio_end']   ?? PHP_INT_MAX), 3);
            // gte(t\,START)*lte(t\,END) evaluates to 1 inside the window, 0 outside
            return "volume='gte(t\\,{$s})*lte(t\\,{$e})':eval=frame";
        }

        return null; // 'full' — no filter
    }

    private function finalEncode(string $inputPath, string $outputPath): void
    {
        // Move the last intermediate file directly into the output path.
        // rename() is atomic and zero-copy when src and dst are on the same
        // filesystem (both under storage/), so peak disk usage drops to 1×
        // the output file size instead of 2×. FFmpeg is not invoked at all.
        if (!rename($inputPath, $outputPath)) {
            throw new \RuntimeException("Failed to move {$inputPath} → {$outputPath}");
        }
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
            // Log the full output so we can see the actual error line,
            // not just the libx264 stats that appear at the end.
            Log::error('FFmpeg command failed', [
                'exit_code' => $code,
                'command'   => $bin . ' ' . $args,
                'output'    => implode("\n", $output),
            ]);

            throw new RuntimeException(
                'FFmpeg failed (exit ' . $code . '): ' . implode("\n", array_slice($output, -40))
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
