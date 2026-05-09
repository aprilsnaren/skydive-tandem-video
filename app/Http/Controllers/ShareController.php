<?php

namespace App\Http\Controllers;

use App\Models\Export;

class ShareController extends Controller
{
    public function show(string $uuid)
    {
        $export = Export::where('uuid', $uuid)->firstOrFail();

        $videoUrl = null;

        if ($export->isDone() && $export->path) {
            // Serve via a dedicated route rather than exposing storage path directly
            $videoUrl = route('share.video', $export->uuid);
        }

        return view('share', [
            'export'   => $export,
            'videoUrl' => $videoUrl,
        ]);
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

        if (! $export->downloaded_at) {
            $export->update(['downloaded_at' => now()]);
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
