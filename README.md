# Skydive Tandem Video Editor

A self-hostable web app built for skydiving centers (or similar). Staff upload video clips, trim and reorder them, optionally add background music, and export a single finished video. The result is shared with the tandem guest via a branded public link — no login required for the guest.

**Self-hostable** — bring your own watermark, splash screen, logo, and brand colours. No tiers or accounts.

---

## How it works

1. Staff opens `/` and uploads any number of video clips + optional music
2. Staff sets trim points per clip and drags to reorder
3. Staff clicks **Export**
4. Laravel queues an FFmpeg job that:
   - Trims and concatenates all clips in order
   - Appends your splash screen video
   - Mixes in background music (capped to video length)
   - Burns in your watermark (bottom-right)
5. A share link is displayed: `/share/{uuid}`
6. Staff sends the link to the guest
7. Guest opens the link → branded page with a video player

---

## Requirements

| Dependency | Notes |
|---|---|
| PHP 8.2+ | With `fileinfo`, `pdo_mysql`, `gd` extensions |
| Composer | Dependency manager |
| MySQL 8+ | Database |
| FFmpeg | Must be on `$PATH` — `ffmpeg -version` should work |
| Queue worker | `php artisan queue:work` — required for exports |

---

## PHP configuration

Video uploads are large files. The default PHP limits will cause 500 errors. Before running, update your `php.ini` (find it with `php --ini`):

```ini
memory_limit       = 512M
upload_max_filesize = 512M
post_max_size      = 512M
max_execution_time = 0
```

A `public/.user.ini` with these values is included and is picked up automatically by PHP-FPM and the built-in dev server (after a ~5-minute cache window). For the built-in dev server you can also pass the flags directly:

```bash
php -d memory_limit=512M \
    -d upload_max_filesize=512M \
    -d post_max_size=512M \
    artisan serve
```

For **nginx + PHP-FPM** in production, set the values in your FPM pool config (`/etc/php/8.x/fpm/pool.d/www.conf`) or a site-specific `php.ini` override, and also add to your nginx server block:

```nginx
client_max_body_size 512M;
```

---

## Installation

```bash
# 1. Clone and install dependencies
git clone <repo> skydive-tandem-video
cd skydive-tandem-video
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Set database credentials in .env
#    DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 4. Run migrations
php artisan migrate

# 5. Add your branding assets (all optional — see Branding section below)
cp /path/to/watermark.png storage/watermark.png
cp /path/to/splash.mp4    storage/splash.mp4
cp /path/to/logo.png      storage/logo.png
```

---

## Branding

Place the following files at the paths configured in `.env` (defaults shown):

| File | Purpose |
|---|---|
| `storage/watermark.png` | PNG burned onto every export (bottom-right, 20px margin) |
| `storage/splash.mp4` | Video clip appended at the end of every export |
| `storage/logo.png` | Logo shown in the header and share page |

All three are **optional** — any missing asset is skipped gracefully.

---

## Environment variables

All `VE_*` variables are read from `.env` via `config/videoedit.php`.

| Variable | Default | Description |
|---|---|---|
| `VE_BRAND_NAME` | `Skydive Tandem Video Editor` | Name shown in the header and share page |
| `VE_BRAND_COLOR` | `#ff3c6e` | Primary brand colour (any CSS hex value) |
| `VE_BRAND_LOGO` | `storage/logo.png` | Path to logo image |
| `VE_WATERMARK` | `storage/watermark.png` | Path to watermark PNG |
| `VE_SPLASH_VIDEO` | `storage/splash.mp4` | Path to splash screen video |
| `VE_DELETE_AFTER` | `3` | Days before uploads and exports are auto-deleted |
| `VE_FFMPEG_PRESET` | `fast` | Default FFmpeg `-preset` used when an export doesn't pick its own — editors can override this per export from the "Encode preset" dropdown in the editor UI |
| `VE_FFMPEG_THREADS` | `2` | FFmpeg encode threads |
| `VE_FFMPEG_CRF` | `23` | FFmpeg constant rate factor (quality — lower is higher quality/larger file, unaffected by preset) |
| `VE_EXPORT_TIMEOUT` | `3600` | Job timeout in seconds — see note below on keeping the queue worker's own timeout above this |

### FFmpeg encode presets

All exports use libx264. The preset trades encode **speed** for output **file
size** at the same visual quality (`VE_FFMPEG_CRF` controls quality, not the
preset) — it does not affect how long the final video plays, only how long
the export job takes and how big the resulting MP4 is. `VE_FFMPEG_PRESET`
sets the site-wide default; editors can override it per export from the
"Encode preset" dropdown on the export step.

| Preset | Speed vs. file size | Notes |
|---|---|---|
| `placebo` | Slowest, marginally smaller file than `veryslow` | Enormous encode time for a tiny size gain over `veryslow`. Not recommended in practice — included so every libx264 option is available. |
| `veryslow` | Very slow | Smallest file for a given quality. Use when storage/bandwidth matters more than turnaround time. |
| `slower` | Slow | Noticeably smaller file than `slow`, noticeably slower. |
| `slow` | Moderately slow | Smaller file than `medium` for a moderate time cost. |
| `medium` | Balanced | FFmpeg's own built-in default. |
| `fast` | Balanced-fast | **App default.** Good turnaround for short tandem videos with a reasonable file size. |
| `faster` | Fast | Quicker than `fast`, somewhat larger file. |
| `veryfast` | Very fast | Large file, useful when the export queue is backed up and turnaround matters most. |
| `superfast` | Very fast | Noticeably larger file; quality-per-bit drops off more here. |
| `ultrafast` | Fastest | Largest file, lowest quality-per-bit. Good for quick previews or an overloaded server that can't otherwise keep up. |

Paths can be absolute or relative to the project root.

---

## Running

Run all three processes — typically in separate terminal tabs:

```bash
# 1. Web server (dev)
php -d memory_limit=512M -d upload_max_filesize=512M -d post_max_size=512M artisan serve

# 2. Queue worker — processes FFmpeg export jobs
php artisan queue:work --timeout=1800

# 3. Scheduler — runs the daily file prune (dev only)
php artisan schedule:work
```

In **production**, manage the queue worker with Supervisor and add the scheduler to cron:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

### Avoiding `ProcessTimedOutException`

Whatever manages the queue worker (Supervisor, or a hosting panel's queue-worker
UI like Ploi's) has its **own** process timeout, separate from the job's own
`$timeout` (set from `VE_EXPORT_TIMEOUT`, default 3600s/1h). If the worker's
timeout is lower — Laravel's `queue:work` defaults to just **60 seconds** when
no `--timeout` is passed — it will kill the PHP worker process mid-encode and
throw `Symfony\Component\Process\Exception\ProcessTimedOutException`, even
though the job itself would have kept running for much longer.

Make sure the worker is started with a timeout at or above `VE_EXPORT_TIMEOUT`:

```bash
php artisan queue:work --timeout=3600
```

If you're using a hosting panel to manage the worker, set its "Timeout" (and
ideally "Max Tries") fields to match — don't rely only on the `.env` value,
since the CLI flag the panel passes takes precedence. When a job does
ultimately time out or exhaust its retries, the export is now marked
`failed` (with a retry button in the dashboard/editor) instead of being
stuck at "processing" forever.

---

## Routes

| Method | Route | Description |
|---|---|---|
| `GET` | `/` | Editor page |
| `POST` | `/upload` | Upload video clips and/or music file |
| `POST` | `/export` | Start an export job |
| `GET` | `/export/{uuid}/status` | Poll export status (JSON) |
| `GET` | `/share/{uuid}` | Public share page for the guest |
| `GET` | `/share/{uuid}/video` | Streams the exported MP4 |

---

## Tech stack

- **Laravel** — routing, queued jobs, daily scheduler
- **Alpine.js** — reactive editor UI (CDN, no build step)
- **Tailwind CSS** — styling (CDN, no build step)
- **FFmpeg** — all video processing (trim, concat, watermark, music mix)
- **MySQL** — `uploads` and `exports` tables
