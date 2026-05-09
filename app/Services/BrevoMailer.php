<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoMailer
{
    private const API_URL = 'https://api.brevo.com/v3/smtp/email';

    /**
     * Send "Din tandem video er klar".
     */
    public function sendReady(string $email, string $name, string $shareUrl, string $expiresDate): bool
    {
        $subject = 'Din tandem video er klar 🎬';

        $html = $this->wrap($name, "
            <p>Din tandem video er nu klar til at se og downloade!</p>
            <p style='text-align:center;margin:32px 0'>
                <a href='{$shareUrl}' style='{$this->btnStyle()}'>Se video</a>
            </p>
            <p style='color:#888;font-size:14px'>
                Videoen er tilgængelig til og med <strong>{$expiresDate}</strong> og slettes automatisk derefter.
            </p>
        ");

        return $this->send($email, $name, $subject, $html);
    }

    /**
     * Send "Husk at downloade din video".
     */
    public function sendReminder(string $email, string $name, string $shareUrl, string $expiresDate): bool
    {
        $subject = 'Husk at downloade din tandem video';

        $html = $this->wrap($name, "
            <p>Har du set din tandem video endnu? Husk at downloade den, inden den udløber.</p>
            <p style='text-align:center;margin:32px 0'>
                <a href='{$shareUrl}' style='{$this->btnStyle()}'>Se og download video</a>
            </p>
            <p style='color:#888;font-size:14px'>
                Videoen udløber den <strong>{$expiresDate}</strong>.
            </p>
        ");

        return $this->send($email, $name, $subject, $html);
    }

    /**
     * Send "Din tandem video udløber i morgen".
     */
    public function sendExpiringTomorrow(string $email, string $name, string $shareUrl, string $expiresDate): bool
    {
        $subject = 'Din tandem video udløber i morgen';

        $html = $this->wrap($name, "
            <p>Din tandem video udløber <strong>i morgen</strong> den {$expiresDate}.</p>
            <p>Download den nu, inden den slettes automatisk.</p>
            <p style='text-align:center;margin:32px 0'>
                <a href='{$shareUrl}' style='{$this->btnStyle()}'>Download video</a>
            </p>
        ");

        return $this->send($email, $name, $subject, $html);
    }

    /**
     * Send "Din tandem video udløber i dag".
     */
    public function sendExpiringToday(string $email, string $name, string $shareUrl, string $expiresDate): bool
    {
        $subject = 'Din tandem video udløber i dag';

        $html = $this->wrap($name, "
            <p>Det er din <strong>sidste chance</strong>! Din tandem video udløber i dag og slettes i morgen.</p>
            <p style='text-align:center;margin:32px 0'>
                <a href='{$shareUrl}' style='{$this->btnStyle()}'>Download video nu</a>
            </p>
        ");

        return $this->send($email, $name, $subject, $html);
    }

    // -------------------------------------------------------------------------

    protected function send(string $toEmail, string $toName, string $subject, string $htmlContent): bool
    {
        $apiKey = config('videoedit.brevo_api_key');

        if (empty($apiKey)) {
            Log::info('BrevoMailer: no API key configured, skipping email.', [
                'to'      => $toEmail,
                'subject' => $subject,
            ]);
            return false;
        }

        try {
            $response = Http::withHeaders([
                'api-key'      => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post(self::API_URL, [
                'sender' => [
                    'name'  => config('videoedit.brevo_from_name', 'Tandem Video Maker'),
                    'email' => config('videoedit.brevo_from_email'),
                ],
                'to' => [
                    ['email' => $toEmail, 'name' => $toName],
                ],
                'subject'     => $subject,
                'htmlContent' => $htmlContent,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('BrevoMailer: API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('BrevoMailer: exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function wrap(string $name, string $body): string
    {
        $greeting = $name ? "Hej {$name}," : 'Hej,';
        $color    = config('videoedit.brand_color', '#ff3c6e');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="da">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 0">
                <tr><td align="center">
                    <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;max-width:560px;width:100%">
                        <tr>
                            <td style="background:{$color};padding:24px 32px">
                                <span style="color:#fff;font-size:18px;font-weight:bold">Tandem Video Maker</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:32px;color:#1a1a1a;font-size:16px;line-height:1.6">
                                <p style="margin-top:0">{$greeting}</p>
                                {$body}
                            </td>
                        </tr>
                        <tr>
                            <td style="background:#f9f9f9;padding:16px 32px;color:#999;font-size:12px;border-top:1px solid #eee">
                                Tandem Video Maker &mdash; denne e-mail er sendt automatisk.
                            </td>
                        </tr>
                    </table>
                </td></tr>
            </table>
        </body>
        </html>
        HTML;
    }

    private function btnStyle(): string
    {
        $color = config('videoedit.brand_color', '#ff3c6e');
        return "display:inline-block;background:{$color};color:#fff;font-weight:bold;font-size:16px;"
             . "padding:14px 32px;border-radius:8px;text-decoration:none";
    }
}
