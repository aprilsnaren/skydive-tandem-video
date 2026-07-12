<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending', 'processing', 'done', 'failed'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])
                ->default('pending')
                ->change();
        });
    }
};
