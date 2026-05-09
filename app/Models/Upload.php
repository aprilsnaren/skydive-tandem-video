<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Upload extends Model
{
    protected $fillable = ['uuid', 'original_name', 'path', 'duration'];

    protected static function booted(): void
    {
        static::creating(function (Upload $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
