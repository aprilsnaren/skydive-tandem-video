<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesChunkedUploads;
use App\Models\Export;
use App\Models\Upload;
use App\Services\BrevoMailer;
use Illuminate\Http\Request;

/**
 * Uploader portal — a simple page where external uploaders (camera flyers,
 * instructors, …) submit raw footage for a guest. Each submission creates a
 * draft project on the editor dashboard, ready to be opened in the editor.
 */
class PortalController extends Controller
{
    use HandlesChunkedUploads;

    // -------------------------------------------------------------------------
    // Auth (shared uploader password, separate from the editor password)
    // -------------------------------------------------------------------------

    public function showLogin()
    {
        return view('portal-login');
    }

    public function login(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $configured = config('videoedit.uploader_password');

        if ($configured && $request->input('password') === $configured) {
            $request->session()->regenerate();
            $request->session()->put('uploader_authed', true);
            return redirect()->route('portal');
        }

        return back()->withErrors(['password' => 'Incorrect password.'])->withInput();
    }

    // -------------------------------------------------------------------------
    // Portal
    // -------------------------------------------------------------------------

    public function show()
    {
        return view('portal');
    }

    /**
     * Chunked file upload (videos and photos) — same contract as the editor.
     */
    public function chunk(Request $request)
    {
        return $this->handleChunkedUpload($request);
    }

    /**
     * Create a draft project from the uploaded files.
     *
     * POST body (JSON):
     *   uploader_name   — who is submitting
     *   receiver_name   — the guest the video is for
     *   receiver_email  — the guest's email
     *   message         — optional note to the editor
     *   videos[]        — ordered upload UUIDs of video clips
     *   images[]        — ordered upload UUIDs of photos
     */
    public function submit(Request $request)
    {
        $request->validate([
            'uploader_name'  => ['required', 'string', 'max:100'],
            'receiver_name'  => ['required', 'string', 'max:100'],
            'receiver_email' => ['required', 'email', 'max:254'],
            'message'        => ['nullable', 'string', 'max:2000'],
            'videos'         => ['nullable', 'array', 'max:50'],
            'videos.*'       => ['required', 'string', 'exists:uploads,uuid'],
            'images'         => ['nullable', 'array', 'max:' . config('videoedit.max_images_upload', 200)],
            'images.*'       => ['required', 'string', 'exists:uploads,uuid'],
        ]);

        $videos = $request->input('videos') ?: [];
        $images = $request->input('images') ?: [];

        if (empty($videos) && empty($images)) {
            return response()->json(['message' => 'Upload at least one video or photo before submitting.'], 422);
        }

        $name = fn (string $uuid) => Upload::where('uuid', $uuid)->value('original_name') ?? '';

        // Draft clips carry no trim_end — the editor fills it from the video's
        // duration, exactly like a fresh upload.
        $clipsConfig = [
            'clips' => array_map(fn (string $uuid) => [
                'uuid'          => $uuid,
                'original_name' => $name($uuid),
                'trim_start'    => 0,
            ], $videos),
            'images' => array_map(fn (string $uuid) => [
                'uuid'          => $uuid,
                'original_name' => $name($uuid),
            ], $images),
        ];

        $export = Export::create([
            'status'           => 'draft',
            'guest_name'       => $request->input('receiver_name'),
            'guest_email'      => $request->input('receiver_email'),
            'uploader_name'    => $request->input('uploader_name'),
            'uploader_message' => $request->input('message') ?: null,
            'clips_config'     => $clipsConfig,
        ]);

        $notifyEmail = config('videoedit.notify_email');
        if ($notifyEmail) {
            app(BrevoMailer::class)->sendIntakeNotification(
                $notifyEmail,
                $export->uploader_name,
                $export->guest_name,
                count($videos),
                count($images),
                route('editor.edit', $export->uuid),
                $export->uploader_message,
            );
        }

        return response()->json(['ok' => true]);
    }
}
