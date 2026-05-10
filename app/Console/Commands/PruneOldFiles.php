<?php

namespace App\Console\Commands;

use App\Models\Export;
use App\Models\Upload;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneOldFiles extends Command
{
    protected $signature   = 'videoedit:prune';
    protected $description = 'Delete uploads, exports, and orphaned tmp files';

    public function handle(): int
    {
        // ------------------------------------------------------------------
        // 1. Prune raw uploads older than 12 hours
        //    Uploads are only needed while the FFmpeg job runs (minutes).
        //    12 hours is generous enough to cover retries and slow queues.
        // ------------------------------------------------------------------
        $uploadThreshold = Carbon::now()->subHours(12);
        $uploads = Upload::where('created_at', '<', $uploadThreshold)->get();

        foreach ($uploads as $upload) {
            $this->deleteFile($upload->path);
            $upload->delete();
        }

        $this->info("Pruned {$uploads->count()} upload(s).");

        // ------------------------------------------------------------------
        // 2. Prune exports whose expires_at has passed
        // ------------------------------------------------------------------
        $expired = Export::whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::today())
            ->get();

        foreach ($expired as $export) {
            if ($export->path) {
                $this->deleteFile($export->path);
            }
            $export->delete();
        }

        // 2b. Legacy exports with no expires_at older than VE_DELETE_AFTER days
        $days    = (int) config('videoedit.delete_after_days', 7);
        $legacy  = Export::whereNull('expires_at')
            ->where('created_at', '<', Carbon::now()->subDays($days))
            ->get();

        foreach ($legacy as $export) {
            if ($export->path) {
                $this->deleteFile($export->path);
            }
            $export->delete();
        }

        $pruned = $expired->count() + $legacy->count();
        $this->info("Pruned {$pruned} export(s).");

        // ------------------------------------------------------------------
        // 3. Prune failed exports — delete any partial output files
        // ------------------------------------------------------------------
        $failed = Export::where('status', 'failed')
            ->where('created_at', '<', Carbon::now()->subHours(1))
            ->get();

        foreach ($failed as $export) {
            if ($export->path) {
                $this->deleteFile($export->path);
                $export->update(['path' => null]);
            }
        }

        $this->info("Cleaned up {$failed->count()} failed export file(s).");

        // ------------------------------------------------------------------
        // 4. Prune orphaned tmp dirs older than 6 hours
        //    (incomplete chunk uploads or crashed export jobs)
        // ------------------------------------------------------------------
        $tmpRoot = storage_path('app/tmp');
        $cutoff  = Carbon::now()->subHours(6)->timestamp;
        $pruned  = 0;

        if (is_dir($tmpRoot)) {
            foreach (glob($tmpRoot . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                if (filemtime($dir) < $cutoff) {
                    foreach (glob($dir . '/*') ?: [] as $file) {
                        @unlink($file);
                    }
                    @rmdir($dir);
                    $pruned++;
                }
            }
        }

        $this->info("Pruned {$pruned} orphaned tmp dir(s).");

        // ------------------------------------------------------------------
        // 5. Remove leftover files in storage/app/private/videos/
        //    (uploaded via old Storage::disk('local') before the path fix)
        // ------------------------------------------------------------------
        $privateVideos = storage_path('app/private/videos');
        $pruned = 0;

        if (is_dir($privateVideos)) {
            $knownPaths = Upload::pluck('path')->map(fn ($p) => basename($p))->flip();

            foreach (glob($privateVideos . '/*') ?: [] as $file) {
                if (! isset($knownPaths[basename($file)])) {
                    @unlink($file);
                    $pruned++;
                }
            }
        }

        $this->info("Pruned {$pruned} orphaned private/videos file(s).");

        return self::SUCCESS;
    }

    /**
     * Delete a file stored at storage/app/{path}, with fallback to
     * storage/app/private/{path} for old records created before the path fix.
     */
    private function deleteFile(string $relativePath): void
    {
        $primary = storage_path('app/' . $relativePath);
        if (file_exists($primary)) {
            @unlink($primary);
            return;
        }

        $fallback = storage_path('app/private/' . $relativePath);
        if (file_exists($fallback)) {
            @unlink($fallback);
        }
    }
}
