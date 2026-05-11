<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExportJob;
use App\Models\Export;
use App\Models\Upload;
use App\Services\BrevoMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EditorController extends Controller
{
    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    public function showLogin()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $configured = config('videoedit.editor_password');

        if ($configured && $request->input('password') === $configured) {
            $request->session()->regenerate();
            $request->session()->put('editor_authed', true);
            return redirect()->route('dashboard');
        }

        return back()->withErrors(['password' => 'Incorrect password.'])->withInput();
    }

    public function logout(Request $request)
    {
        $request->session()->forget('editor_authed');
        return redirect()->route('login');
    }

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    public function dashboard()
    {
        $exports = Export::latest()->get();
        return view('dashboard', compact('exports'));
    }

    // -------------------------------------------------------------------------
    // Editor
    // -------------------------------------------------------------------------

    public function newEditor()
    {
        return view('editor');
    }

    public function editExport(string $uuid)
    {
        $export = Export::where('uuid', $uuid)->firstOrFail();

        // Always build initial state — even if clips_config is missing (old exports).
        $clips = [];
        $musicUuid = null;
        $music     = null;
        $logoUuid  = null;
        $logo      = null;

        if ($export->clips_config) {
            $config    = $export->clips_config;
            $musicUuid = $config['music_uuid'] ?? null;
            $music     = isset($config['music_name']) ? ['name' => $config['music_name']] : null;
            $logoUuid  = $config['logo_uuid'] ?? null;

            if ($logoUuid) {
                $logoUpload     = Upload::where('uuid', $logoUuid)->first();
                $logoFileExists = $logoUpload && file_exists(storage_path("app/{$logoUpload->path}"));
                $logo = [
                    'name'     => $config['logo_name'] ?? 'logo',
                    'localUrl' => $logoFileExists ? route('uploads.stream', $logoUuid) : null,
                ];
            }

            foreach ($config['clips'] ?? [] as $clip) {
                $upload     = Upload::where('uuid', $clip['uuid'])->first();
                $fileExists = $upload && file_exists(storage_path("app/{$upload->path}"));

                $clips[] = [
                    'uuid'          => $clip['uuid'],
                    'original_name' => $clip['original_name'],
                    'localUrl'      => $fileExists ? route('uploads.stream', $clip['uuid']) : null,
                    'trim_start'    => (float) ($clip['trim_start'] ?? 0),
                    'trim_end'      => (float) ($clip['trim_end'] ?? 0),
                    'audio_mode'    => $clip['audio_mode']  ?? 'full',
                    'audio_start'   => isset($clip['audio_start']) ? (float) $clip['audio_start'] : 0,
                    'audio_end'     => isset($clip['audio_end'])   ? (float) $clip['audio_end']   : 0,
                    'nativeDuration'=> null,
                    'trimError'     => null,
                    'fileExpired'   => !$fileExists,
                ];
            }
        }

        $initial = [
            'isReturning' => true,
            'clips'       => $clips,
            'guestName'   => $export->guest_name ?? '',
            'guestEmail'  => $export->guest_email ?? '',
            'musicUuid'   => $musicUuid,
            'music'       => $music,
            'logoUuid'    => $logoUuid,
            'logo'        => $logo,
        ];

        if ($export->isDone() && $export->path) {
            $initial['exportUuid'] = $export->uuid;
            $initial['shareUrl']   = route('share', $export->uuid);
            $initial['videoUrl']   = route('share.video', $export->uuid);
        }

        if ($export->isFailed()) {
            $initial['exportUuid']   = $export->uuid;
            $initial['exportFailed'] = true;
            $initial['exportErrMsg'] = $export->error_message;
        }

        if (in_array($export->status, ['pending', 'processing'])) {
            $initial['exportUuid'] = $export->uuid;
        }

        return view('editor', compact('initial'));
    }

    /**
     * Stream an uploaded file — used for clip/logo preview when re-editing.
     */
    public function streamUpload(string $uuid)
    {
        $upload = Upload::where('uuid', $uuid)->firstOrFail();
        $path   = storage_path("app/{$upload->path}");

        abort_unless(file_exists($path), 404);

        return response()->file($path);
    }

    /**
     * Receive a single chunk of a video file and reassemble when complete.
     *
     * POST fields:
     *   chunk    — file blob for this chunk
     *   uuid     — client-generated UUID for the whole file (used as tmp dir name)
     *   index    — 0-based chunk index
     *   total    — total number of chunks
     *   filename — original file name
     *   type     — 'video' | 'music'
     */
    public function chunk(Request $request)
    {
        $request->validate([
            'chunk'    => ['required', 'file'],
            'uuid'     => ['required', 'string', 'regex:/^[0-9a-f\-]{36}$/i'],
            'index'    => ['required', 'integer', 'min:0'],
            'total'    => ['required', 'integer', 'min:1', 'max:10000'],
            'filename' => ['required', 'string', 'max:255'],
            'type'     => ['nullable', 'in:video,music'],
        ]);

        $uuid     = $request->input('uuid');
        $index    = (int) $request->input('index');
        $total    = (int) $request->input('total');
        $filename = $request->input('filename');
        $type     = $request->input('type', 'video');

        $tmpDir = storage_path("app/tmp/{$uuid}");
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        // Save this chunk
        $request->file('chunk')->move($tmpDir, "chunk_{$index}");

        // Not all chunks arrived yet
        $received = count(glob("{$tmpDir}/chunk_*"));
        if ($received < $total) {
            return response()->json(['status' => 'partial', 'received' => $received, 'total' => $total]);
        }

        // --- All chunks received — reassemble ---
        $safeBase    = Str::slug(pathinfo($filename, PATHINFO_FILENAME), '_') ?: 'file';
        $ext         = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: ($type === 'music' ? 'mp3' : 'mp4');
        $storagePath = "videos/{$uuid}_{$safeBase}.{$ext}";
        $finalPath   = storage_path("app/{$storagePath}");

        @mkdir(storage_path('app/videos'), 0755, true);

        $out = fopen($finalPath, 'wb');
        for ($i = 0; $i < $total; $i++) {
            $fp = fopen("{$tmpDir}/chunk_{$i}", 'rb');
            while (!feof($fp)) {
                fwrite($out, fread($fp, 65536));
            }
            fclose($fp);
        }
        fclose($out);

        // Cleanup tmp dir
        foreach (glob("{$tmpDir}/chunk_*") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($tmpDir);

        // Save to DB
        $upload = Upload::create([
            'uuid'          => $uuid,
            'original_name' => $filename,
            'path'          => $storagePath,
        ]);

        return response()->json([
            'status'        => 'done',
            'uuid'          => $upload->uuid,
            'original_name' => $upload->original_name,
        ]);
    }

    /**
     * Logo upload — small image file, no chunking needed.
     */
    public function logo(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimetypes:image/png,image/jpeg,image/webp,image/svg+xml', 'max:5120'],
        ]);

        $file = $request->file('logo');
        $ext  = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $filename    = \Illuminate\Support\Str::random(40) . '.' . $ext;
        $storagePath = 'videos/' . $filename;

        @mkdir(storage_path('app/videos'), 0755, true);
        $file->move(storage_path('app/videos'), $filename);

        $upload = Upload::create([
            'original_name' => $file->getClientOriginalName(),
            'path'          => $storagePath,
        ]);

        return response()->json(['uuid' => $upload->uuid]);
    }

    /**
     * Legacy endpoint — used by the music upload (small files, no chunking needed).
     */
    public function upload(Request $request)
    {
        $request->validate([
            'music' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp4,audio/wav,audio/ogg', 'max:51200'],
        ]);

        $music = $request->file('music');
        $ext   = strtolower($music->getClientOriginalExtension()) ?: 'mp3';
        $filename    = \Illuminate\Support\Str::random(40) . '.' . $ext;
        $storagePath = 'videos/' . $filename;

        @mkdir(storage_path('app/videos'), 0755, true);
        $music->move(storage_path('app/videos'), $filename);

        $upload = Upload::create([
            'original_name' => $music->getClientOriginalName(),
            'path'          => $storagePath,
        ]);

        return response()->json(['music' => $upload->uuid]);
    }

    /**
     * Start an export job.
     * POST body (JSON or form):
     *   clips[]          — ordered array of { uuid, trim_start, trim_end }
     *   music_uuid       — optional upload UUID of the music file
     *   logo_uuid        — optional upload UUID of the logo image
     *   guest_name       — optional guest name
     *   guest_email      — optional guest email
     */
    public function export(Request $request)
    {
        $request->validate([
            'clips'                => ['required', 'array', 'min:1'],
            'clips.*.uuid'         => ['required', 'string', 'exists:uploads,uuid'],
            'clips.*.trim_start'   => ['required', 'numeric', 'min:0'],
            'clips.*.trim_end'     => ['required', 'numeric', 'gt:clips.*.trim_start'],
            'clips.*.audio_mode'   => ['nullable', 'in:full,muted,range'],
            'clips.*.audio_start'  => ['nullable', 'numeric', 'min:0'],
            'clips.*.audio_end'    => ['nullable', 'numeric', 'min:0'],
            'music_uuid'           => ['nullable', 'string', 'exists:uploads,uuid'],
            'logo_uuid'            => ['nullable', 'string', 'exists:uploads,uuid'],
            'guest_name'           => ['nullable', 'string', 'max:100'],
            'guest_email'          => ['nullable', 'email', 'max:254'],
        ]);

        $clips     = $request->input('clips');
        $musicUuid = $request->input('music_uuid');
        $logoUuid  = $request->input('logo_uuid');

        // Build the clips_config snapshot for later re-editing.
        $clipsConfig = [
            'clips'      => array_map(function (array $c) {
                return [
                    'uuid'          => $c['uuid'],
                    'original_name' => Upload::where('uuid', $c['uuid'])->value('original_name') ?? '',
                    'trim_start'    => (float) $c['trim_start'],
                    'trim_end'      => (float) $c['trim_end'],
                    'audio_mode'    => $c['audio_mode']  ?? 'full',
                    'audio_start'   => isset($c['audio_start']) ? (float) $c['audio_start'] : null,
                    'audio_end'     => isset($c['audio_end'])   ? (float) $c['audio_end']   : null,
                ];
            }, $clips),
            'music_uuid' => $musicUuid,
            'music_name' => $musicUuid ? Upload::where('uuid', $musicUuid)->value('original_name') : null,
            'logo_uuid'  => $logoUuid,
            'logo_name'  => $logoUuid  ? Upload::where('uuid', $logoUuid)->value('original_name')  : null,
        ];

        $export = Export::create([
            'status'       => 'pending',
            'guest_name'   => $request->input('guest_name') ?: null,
            'guest_email'  => $request->input('guest_email') ?: null,
            'clips_config' => $clipsConfig,
        ]);

        ProcessExportJob::dispatch(
            $export->id,
            $clips,
            $musicUuid,
            $logoUuid,
        );

        return response()->json([
            'export_uuid' => $export->uuid,
        ]);
    }

    /**
     * Delete a project and its output video file.
     */
    public function destroy(string $uuid)
    {
        $export = Export::where('uuid', $uuid)->firstOrFail();

        if ($export->path) {
            $fullPath = storage_path('app/' . $export->path);
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $export->delete();

        return redirect()->route('dashboard');
    }

    /**
     * Poll endpoint: returns current status of an export.
     */
    public function status(string $uuid)
    {
        $export = Export::where('uuid', $uuid)->firstOrFail();

        $payload = ['status' => $export->status];

        if ($export->status_message) {
            $payload['status_message'] = $export->status_message;
        }

        if ($export->isDone()) {
            $payload['share_url'] = route('share', $export->uuid);
            $payload['video_url'] = route('share.video', $export->uuid);
        }

        if ($export->isFailed()) {
            $payload['error_message'] = $export->error_message;
        }

        return response()->json($payload);
    }

    /**
     * Send the "video ready" email on explicit user request.
     */
    public function sendEmail(Request $request, string $uuid)
    {
        $request->validate(['guest_email' => ['required', 'email', 'max:254']]);

        $export = Export::where('uuid', $uuid)->where('status', 'done')->firstOrFail();

        // Persist the (possibly updated) email address on the export record.
        $export->update(['guest_email' => $request->input('guest_email')]);

        $expiresDate = $export->expires_at
            ? $export->expires_at->isoFormat('D. MMMM YYYY')
            : now()->addDays(7)->isoFormat('D. MMMM YYYY');

        $shareUrl = route('share', $export->uuid);

        $sent = app(BrevoMailer::class)->sendReady(
            $export->guest_email,
            $export->guest_name ?? '',
            $shareUrl,
            $expiresDate,
        );

        if ($sent) {
            $export->update(['email_ready_at' => now()]);
            return response()->json(['ok' => true]);
        }

        return response()->json(['error' => 'Failed to send email. Check BREVO_API_KEY in .env.'], 500);
    }
}
