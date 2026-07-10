<?php

namespace App\Http\Controllers;

use App\Models\Export;
use App\Services\BrevoMailer;

class ShareController extends Controller
{
    public function show(string $uuid)
    {
        $export = Export::where('uuid', $uuid)->firstOrFail();

        $videoUrl = null;
        $images   = [];

        if ($export->isDone() && $export->path) {
            // Serve via a dedicated route rather than exposing storage path directly
            $videoUrl = route('share.video', $export->uuid);
            $images   = $export->downloadableImages();
        }

        return view('share', [
            'export'   => $export,
            'videoUrl' => $videoUrl,
            'images'   => $images,
        ]);
    }

    /**
     * Display an end photo inline (used by <img> tags on the share page).
     */
    public function image(string $uuid, int $index)
    {
        [, $image] = $this->findImage($uuid, $index);

        return response()->file(storage_path('app/' . $image['path']));
    }

    /**
     * Force-download an end photo with a human-readable filename.
     */
    public function imageDownload(string $uuid, int $index)
    {
        [$export, $image] = $this->findImage($uuid, $index);

        $base = $export->guest_name
            ? \Illuminate\Support\Str::slug($export->guest_name)
            : 'tandem';
        $ext = strtolower(pathinfo($image['path'], PATHINFO_EXTENSION)) ?: 'jpg';

        return response()->download(
            storage_path('app/' . $image['path']),
            $base . '-photo-' . ($index + 1) . '.' . $ext,
        );
    }

    /**
     * Download all end photos as a single zip file.
     */
    public function imagesDownloadAll(string $uuid)
    {
        $export = Export::where('uuid', $uuid)
            ->where('status', 'done')
            ->firstOrFail();

        $images = $export->downloadableImages();

        abort_if(empty($images), 404);

        $base = $export->guest_name
            ? \Illuminate\Support\Str::slug($export->guest_name)
            : 'tandem';

        @mkdir(storage_path('app/tmp'), 0755, true);
        $zipPath = storage_path('app/tmp/' . $export->uuid . '_photos.zip');

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create zip file.');
        }

        foreach ($images as $i => $image) {
            $ext = strtolower(pathinfo($image['path'], PATHINFO_EXTENSION)) ?: 'jpg';
            $zip->addFile(storage_path('app/' . $image['path']), 'photo-' . ($i + 1) . '.' . $ext);
        }

        $zip->close();

        return response()->download($zipPath, $base . '-photos.zip')->deleteFileAfterSend(true);
    }

    /**
     * Look up a downloadable end photo by export UUID and image index.
     * 404s unless the export is done, downloads are enabled, and the file exists.
     */
    private function findImage(string $uuid, int $index): array
    {
        $export = Export::where('uuid', $uuid)
            ->where('status', 'done')
            ->firstOrFail();

        $image = collect($export->downloadableImages())->firstWhere('index', $index);

        abort_unless($image, 404);

        return [$export, $image];
    }

    /**
     * Force-download the exported video with a human-readable filename.
     */
    public function download(string $uuid)
    {
        $export = Export::where('uuid', $uuid)
            ->where('status', 'done')
            ->firstOrFail();

        $absolutePath = storage_path('app/' . $export->path);

        if (!file_exists($absolutePath)) {
            abort(404);
        }

        $filename = $export->guest_name
            ? \Illuminate\Support\Str::slug($export->guest_name) . '-tandem.mp4'
            : 'tandem-video.mp4';

        $firstDownload = ! $export->downloaded_at;

        if ($firstDownload) {
            $export->update(['downloaded_at' => now()]);

            $notifyEmail = config('videoedit.notify_email');
            if ($notifyEmail) {
                app(BrevoMailer::class)->sendDownloadNotification(
                    $notifyEmail,
                    $export->guest_name ?? 'Ukendt',
                    route('share', $export->uuid),
                );
            }
        }

        return response()->download($absolutePath, $filename, [
            'Content-Type' => 'video/mp4',
        ]);
    }

    /**
     * Stream the exported video file.
     * Only videos marked as "done" with an existing file are served.
     */
    public function video(string $uuid)
    {
        $export = Export::where('uuid', $uuid)
            ->where('status', 'done')
            ->firstOrFail();

        $absolutePath = storage_path('app/' . $export->path);

        abort_unless(file_exists($absolutePath), 404);

        return response()->file($absolutePath, [
            'Content-Type' => 'video/mp4',
        ]);
    }
}
