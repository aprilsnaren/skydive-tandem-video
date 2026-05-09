<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output resolution — all clips are scaled and padded to this before concat
    |--------------------------------------------------------------------------
    */
    'output_width'  => (int) env('VE_OUTPUT_WIDTH', 1920),
    'output_height' => (int) env('VE_OUTPUT_HEIGHT', 1080),

    /*
    |--------------------------------------------------------------------------
    | FFmpeg binary path
    |--------------------------------------------------------------------------
    */
    'ffmpeg' => env('VE_FFMPEG', 'ffmpeg'),

    /*
    |--------------------------------------------------------------------------
    | Logo end-card duration in seconds (appended when a logo is uploaded)
    |--------------------------------------------------------------------------
    */
    'logo_duration' => (int) env('VE_LOGO_DURATION', 10),

    /*
    |--------------------------------------------------------------------------
    | Branding — shown on the share page
    |--------------------------------------------------------------------------
    */
    'brand_name'  => env('VE_BRAND_NAME', 'Tandem Video Maker'),
    'brand_color' => env('VE_BRAND_COLOR', '#ff3c6e'),
    'brand_logo'  => env('VE_BRAND_LOGO', 'storage/logo.png'),

    /*
    |--------------------------------------------------------------------------
    | Days before exports are automatically deleted (if no expires_at set)
    |--------------------------------------------------------------------------
    */
    'delete_after_days' => (int) env('VE_DELETE_AFTER', 7),

    /*
    |--------------------------------------------------------------------------
    | Operator notification e-mail — receives a message when a guest downloads
    | Override with VE_NOTIFY_EMAIL env variable, or set to null to disable.
    |--------------------------------------------------------------------------
    */
    'notify_email' => env('VE_NOTIFY_EMAIL', 'emil@anle.dk'),

    /*
    |--------------------------------------------------------------------------
    | Brevo transactional email API
    |--------------------------------------------------------------------------
    */
    'brevo_api_key'    => env('BREVO_API_KEY', ''),
    'brevo_from_email' => env('BREVO_FROM_EMAIL', ''),
    'brevo_from_name'  => env('BREVO_FROM_NAME', 'Tandem Video Maker'),
];
