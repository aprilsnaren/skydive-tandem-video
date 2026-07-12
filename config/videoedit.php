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
    'ffmpeg_preset'  => env('VE_FFMPEG_PRESET', 'fast'), // default used when an export doesn't pick its own preset
    'ffmpeg_crf'     => (int) env('VE_FFMPEG_CRF', 23),

    /*
    |--------------------------------------------------------------------------
    | Presets selectable per export from the editor UI — every preset libx264
    | supports, slowest/smallest first to fastest/largest last. VE_FFMPEG_PRESET
    | above only sets the default selection — editors can override it per
    | export to trade encode speed for file size while experimenting.
    |
    | Each step roughly halves (or doubles) encode time relative to its
    | neighbour, in exchange for output file size at the same visual quality
    | (quality itself is held constant by ffmpeg_crf, not the preset).
    |--------------------------------------------------------------------------
    */
    'ffmpeg_presets' => [
        'placebo', 'veryslow', 'slower', 'slow', 'medium',
        'fast', 'faster', 'veryfast', 'superfast', 'ultrafast',
    ],

    /*
    |--------------------------------------------------------------------------
    | Human-readable explanation of each preset, shown in the editor's preset
    | dropdown. Ordered slowest → fastest, matching ffmpeg_presets above.
    |--------------------------------------------------------------------------
    */
    'ffmpeg_preset_descriptions' => [
        'placebo'   => 'Marginally smaller file than veryslow for a huge amount of extra encode time. Diminishing returns — not recommended, included for completeness.',
        'veryslow'  => 'Smallest file size for the given quality. Much slower to encode — use only when file size matters more than turnaround time.',
        'slower'    => 'Noticeably smaller file than "slow", noticeably slower to encode.',
        'slow'      => 'Smaller file than "medium" with a moderate increase in encode time. Good choice when you can afford to wait a bit for a leaner file.',
        'medium'    => 'FFmpeg\'s own default balance of speed and file size.',
        'fast'      => 'App default. Good balance for short tandem videos — quick turnaround with a reasonable file size.',
        'faster'    => 'Quicker than "fast" at the cost of a somewhat larger file.',
        'veryfast'  => 'Fast encodes, larger files. Useful when the queue is backed up and turnaround matters most.',
        'superfast' => 'Very fast, noticeably larger file. Quality per bit drops more sharply here.',
        'ultrafast' => 'Fastest possible encode, largest file and lowest quality-per-bit. Use for quick previews or when a slow/overloaded server can\'t keep up otherwise.',
    ],

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
