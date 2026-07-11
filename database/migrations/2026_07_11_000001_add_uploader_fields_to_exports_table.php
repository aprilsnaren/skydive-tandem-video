<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->string('uploader_name', 100)->nullable()->after('guest_email');
            $table->text('uploader_message')->nullable()->after('uploader_name');
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropColumn(['uploader_name', 'uploader_message']);
        });
    }
};
