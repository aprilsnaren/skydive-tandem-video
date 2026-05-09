<?php

namespace App\Console\Commands;

use App\Models\Export;
use App\Services\BrevoMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendVideoNotifications extends Command
{
    protected $signature   = 'videoedit:notify';
    protected $description = 'Send email reminders for videos that are expiring soon';

    public function handle(BrevoMailer $mailer): int
    {
        $today    = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        // Only exports that have an email address, are done, and not yet downloaded
        $exports = Export::query()
            ->where('status', 'done')
            ->whereNotNull('guest_email')
            ->whereNotNull('expires_at')
            ->whereNotNull('path')
            ->whereNull('downloaded_at')  // stop reminders once the video has been downloaded
            ->get();

        $sent = 0;

        foreach ($exports as $export) {
            $expiresAt   = Carbon::parse($export->expires_at);
            $shareUrl    = route('share', $export->uuid);
            $expiresDate = $expiresAt->isoFormat('D. MMMM YYYY'); // e.g. "9. maj 2026"
            $name        = $export->guest_name ?? '';

            // "Din tandem video udløber i dag" — send on expiry day
            if ($expiresAt->isSameDay($today) && ! $export->email_today_at) {
                $mailer->sendExpiringToday($export->guest_email, $name, $shareUrl, $expiresDate);
                $export->update(['email_today_at' => now()]);
                $sent++;
                continue;
            }

            // "Din tandem video udløber i morgen" — send 1 day before expiry
            if ($expiresAt->isSameDay($tomorrow) && ! $export->email_tomorrow_at) {
                $mailer->sendExpiringTomorrow($export->guest_email, $name, $shareUrl, $expiresDate);
                $export->update(['email_tomorrow_at' => now()]);
                $sent++;
                continue;
            }

            // "Husk at downloade din video" — send 4 days before expiry (≈ day 3 after ready)
            $reminderDay = $expiresAt->copy()->subDays(4);
            if ($today->greaterThanOrEqualTo($reminderDay) && ! $export->email_reminder_at
                && ! $export->email_tomorrow_at && ! $export->email_today_at) {
                $mailer->sendReminder($export->guest_email, $name, $shareUrl, $expiresDate);
                $export->update(['email_reminder_at' => now()]);
                $sent++;
            }
        }

        $this->info("Sent {$sent} notification email(s).");

        return self::SUCCESS;
    }
}
