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
