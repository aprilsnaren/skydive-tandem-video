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
    | FFmpeg encode settings — the biggest lever on encode speed is threads.
    | It's capped at 2 by default to avoid one export starving the rest of a
    | small VPS, but if the server has more cores to spare (check `nproc`),
    | raising VE_FFMPEG_THREADS (or setting it to 0 for "use all cores") can
    | cut encode time dramatically — especially for high-resolution source
    | footage (4K/60fps action-cam or drone clips are the common cause of
    | slow encodes even when the final video is short).
    |--------------------------------------------------------------------------
    */
    'ffmpeg_threads' => (int) env('VE_FFMPEG_THREADS', 2),
    'ffmpeg_preset'  => env('VE_FFMPEG_PRESET', 'fast'), // ultrafast|superfast|veryfast|faster|fast|medium…
    'ffmpeg_crf'     => (int) env('VE_FFMPEG_CRF', 23),

    /*
    |--------------------------------------------------------------------------
    | Export job timeout in seconds. If FFmpeg is still running when this
    | elapses, the queue worker kills the job (TimeoutExceededException) even
    | mid-encode. Raise this if exports are timing out on slow encodes rather
    | than failing outright — tune ffmpeg_threads/ffmpeg_preset first though,
    | since a faster encode helps every export, not just the timeout case.
    |--------------------------------------------------------------------------
    */
    'export_timeout' => (int) env('VE_EXPORT_TIMEOUT', 3600),

    /*
    |--------------------------------------------------------------------------
    | Logo end-card duration in seconds (appended when a logo is uploaded)
    |--------------------------------------------------------------------------
    */
    'logo_duration' => (int) env('VE_LOGO_DURATION', 10),

    /*
    |--------------------------------------------------------------------------
    | Max end photos that can be attached to a single export. Photos are
    | never embedded in the video — they're only offered as a separate
    | download on the share page.
    |--------------------------------------------------------------------------
    */
    'max_images_upload' => (int) env('VE_MAX_IMAGES_UPLOAD', 200),

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
    | Editor password — required to access the /editor dashboard
    |--------------------------------------------------------------------------
    */
    'editor_password' => env('EDITOR_PASSWORD', null),

    /*
    |--------------------------------------------------------------------------
    | Uploader password — required to access the /portal upload page where
    | external uploaders submit raw footage. Separate from the editor password
    | so uploaders can't reach the dashboard. Null disables the portal.
    |--------------------------------------------------------------------------
    */
    'uploader_password' => env('UPLOADER_PASSWORD', null),

    /*
    |--------------------------------------------------------------------------
    | Days that raw files submitted via the uploader portal are kept before
    | pruning (regular uploads are pruned after 12 hours). Drafts themselves
    | are removed by the existing delete_after_days rule.
    |--------------------------------------------------------------------------
    */
    'intake_keep_days' => (int) env('VE_INTAKE_KEEP_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Brevo transactional email API
    |--------------------------------------------------------------------------
    */
    'brevo_api_key'    => env('BREVO_API_KEY', ''),
    'brevo_from_email' => env('BREVO_FROM_EMAIL', ''),
    'brevo_from_name'  => env('BREVO_FROM_NAME', 'Tandem Video Maker'),
];
