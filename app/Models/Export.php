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
}
