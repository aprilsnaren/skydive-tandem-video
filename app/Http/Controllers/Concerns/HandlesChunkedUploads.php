<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait HandlesChunkedUploads
{
    /**
     * Receive a single chunk of a file and reassemble when all chunks arrived.
     *
     * POST fields:
     *   chunk    — file blob for this chunk
     *   uuid     — client-generated UUID for the whole file (used as tmp dir name)
     *   index    — 0-based chunk index
     *   total    — total number of chunks
     *   filename — original file name
     *   type     — 'video' | 'music' | 'image'
     */
    protected function handleChunkedUpload(Request $request)
    {
        $request->validate([
            'chunk'    => ['required', 'file'],
            'uuid'     => ['required', 'string', 'regex:/^[0-9a-f\-]{36}$/i'],
            'index'    => ['required', 'integer', 'min:0'],
            'total'    => ['required', 'integer', 'min:1', 'max:10000'],
            'filename' => ['required', 'string', 'max:255'],
            'type'     => ['nullable', 'in:video,music,image'],
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
        $defaultExt  = ['music' => 'mp3', 'image' => 'jpg'][$type] ?? 'mp4';
        $ext         = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: $defaultExt;
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
}
