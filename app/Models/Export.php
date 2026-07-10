<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Export extends Model
{
    protected $fillable = [
        'uuid', 'guest_name', 'guest_email', 'path', 'status',
        'status_message', 'error_message', 'clips_config',
        'expires_at',
        'email_ready_at', 'email_reminder_at', 'email_tomorrow_at', 'email_today_at',
        'downloaded_at',
    ];

    protected $casts = [
        'clips_config'      => 'array',
        'expires_at'        => 'datetime',
        'email_ready_at'    => 'datetime',
        'email_reminder_at' => 'datetime',
        'email_tomorrow_at' => 'datetime',
        'email_today_at'    => 'datetime',
        'downloaded_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Export $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * End photos available for separate download on the share page.
     * Returns [['index' => int, 'path' => string, 'name' => string], ...]
     * — only photos whose copied file still exists, and only when the
     * editor enabled separate downloads for this export.
     */
    public function downloadableImages(): array
    {
        $config = $this->clips_config ?? [];

        if (empty($config['images_downloadable'])) {
            return [];
        }

        $images = [];
        foreach ($config['images'] ?? [] as $i => $image) {
            $path = $image['download_path'] ?? null;
            if ($path && file_exists(storage_path("app/{$path}"))) {
                $images[] = [
                    'index' => $i,
                    'path'  => $path,
                    'name'  => $image['original_name'] ?? "photo-{$i}",
                ];
            }
        }

        return $images;
    }

    /**
     * Delete the copied end photos (exports/{uuid}_images/) for this export.
     */
    public function deleteImageCopies(): void
    {
        $dir = storage_path("app/exports/{$this->uuid}_images");

        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }
}
