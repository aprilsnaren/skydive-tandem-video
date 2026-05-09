<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->timestamp('expires_at')->nullable()->after('guest_email');

            // Track which notification emails have been sent
            $table->timestamp('email_ready_at')->nullable()->after('expires_at');
            $table->timestamp('email_reminder_at')->nullable()->after('email_ready_at');
            $table->timestamp('email_tomorrow_at')->nullable()->after('email_reminder_at');
            $table->timestamp('email_today_at')->nullable()->after('email_tomorrow_at');
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropColumn([
                'guest_email',
                'expires_at',
                'email_ready_at',
                'email_reminder_at',
                'email_tomorrow_at',
                'email_today_at',
            ]);
        });
    }
};
