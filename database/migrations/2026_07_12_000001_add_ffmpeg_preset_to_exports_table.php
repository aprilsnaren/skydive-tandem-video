<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->string('ffmpeg_preset', 20)->nullable()->after('clips_config');
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropColumn('ffmpeg_preset');
        });
    }
};
