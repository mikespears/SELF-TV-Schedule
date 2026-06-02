# SELF Talk Schedule Display

TV-friendly ballroom schedule boards for [Southeast Linux Fest 2026](https://speakers.southeastlinuxfest.org/southeast-linux-fest-2026/schedule/), powered by the [pretalx API](https://docs.pretalx.org/api/resources/#tag/schedules).

## Requirements

- PHP 8.1+ with `json` enabled and `allow_url_fopen=On` (for API fetch)
- Outbound HTTPS to `speakers.southeastlinuxfest.org`
- Writable `cache/` directory

## Quick start

```bash
cd "SELF Talk Schedule Display"
php -S localhost:8080
```

Open:

- `http://localhost:8080/` — room picker
- `http://localhost:8080/room.php?room=salon-a` — Salon A display

## Ballroom URLs

| Room | URL |
|------|-----|
| Salon A (Altispeed) | `room.php?room=salon-a` |
| Salon B (Rocky Linux) | `room.php?room=salon-b` |
| Salon C-E (VictoriaMetrics) | `room.php?room=salon-c-e` |
| Piedmont 1-3 | `room.php?room=piedmont` |
| Carolina Ballroom (Lounge) | `room.php?room=carolina` |
| AlmaLinux Classroom | `room.php?room=almalinux` |

Point each entrance TV at the matching URL in **full-screen mode** (1920×1080). The room display is laid out to fit a 40″ 1080p screen without scrolling: Now/Up Next panels show full session details; the list shows up to 8 upcoming sessions only (past sessions are hidden).

Pages auto-refresh every 60 seconds; schedule data is cached for 5 minutes to reduce API load.

## Configuration

Edit `config.php` to change `pretalx_host`, `event_slug`, timezone, cache TTL, room mappings, or `gold_sponsors` (logo URLs from [SELF sponsors](https://southeastlinuxfest.org/about/sponsors/)). The API base URL is built automatically from host + slug.

Room displays show **session title**, **speaker**, and **abstract/description** from pretalx in the Now/Up Next panels and today’s list.

Errors and API failures are written to the PHP error log (`error_log`).

## Testing a specific day or time

Schedule filtering uses **America/New_York**. To preview a conference day without changing your system clock:

1. In `config.php`, set `'allow_test_clock' => true` (leave `false` on production TVs).
2. Use either:
   - **URL override** (good for trying different times):  
     `room.php?room=salon-a&now=2026-06-12T10:30:00`
   - **Config default**:  
     `'test_now' => '2026-06-12T10:30:00'`
3. **Date only** (uses noon that day):  
   `room.php?room=salon-a&now=2026-06-12`

Pretalx slots for SELF 2026 start on **2026-06-12** (and following days). Pick a time that falls inside a real session to see **Now** / **Up Next** highlighted.

A yellow **Test clock** banner appears while overrides are active. Set `allow_test_clock` back to `false` before go-live.

## Deployment

Serve the project root with Apache or nginx + PHP-FPM. Ensure `cache/` is writable by the web server user.

**Block web access to `cache/`** (contains downloaded schedule JSON):

- Apache: `cache/.htaccess` is included (`Require all denied`).
- nginx: add `location ^~ /cache/ { deny all; }` in the server block.

The on-screen clock uses `America/New_York` from config, not the TV’s local timezone. Set `allow_test_clock` to `false` on production displays.
