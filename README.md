# SELF Talk Schedule Display

TV-friendly schedule boards for ballroom entrances at [SouthEast LinuxFest](https://southeastlinuxfest.org). Schedule data is loaded from the public [pretalx API](https://docs.pretalx.org/api/resources/#tag/schedules) and cached locally to limit API traffic.

## Features

- **Per-room displays** — One URL per ballroom, optimized for a 40″ TV at 1920×1080 (no page scroll)
- **Now / Up Next** — Current and following session with title, speaker, and description
- **Upcoming list** — Up to eight remaining sessions for that room today (past sessions hidden)
- **Gold sponsor bar** — Sponsor logos along the bottom of each room display
- **Auto-refresh** — Page reloads every 60 seconds; schedule data is refreshed at most every 5 minutes

## Requirements

- PHP 8.1+ with the `json` extension
- `allow_url_fopen=On` (used to fetch pretalx)
- Outbound HTTPS to `speakers.southeastlinuxfest.org`
- A writable `cache/` directory

## Deployment

Serve the project root with Apache or nginx and PHP-FPM (or PHP’s built-in server for local testing). The web server user must be able to write to `cache/`.

**Protect the cache directory** (it contains downloaded schedule JSON):

| Server | Action |
|--------|--------|
| Apache | `cache/.htaccess` is included (`Require all denied`) |
| nginx | Add `location ^~ /cache/ { deny all; }` |

**Production checklist**

- Point each TV at the correct ballroom URL (below) in full-screen mode
- Set `allow_test_clock` to `false` in `config.php`
- Confirm the on-screen clock matches the conference timezone (`America/New_York` by default), not the TV’s local timezone

API and cache errors are logged via PHP `error_log`.

## Ballroom URLs

Use `index.php` to pick a room during setup. For entrance TVs, open the matching URL directly:

| Ballroom | URL |
|----------|-----|
| Salon A (Altispeed) | `room.php?room=salon-a` |
| Salon B (Rocky Linux) | `room.php?room=salon-b` |
| Salon C-E (VictoriaMetrics) | `room.php?room=salon-c-e` |
| Piedmont 1-3 | `room.php?room=piedmont` |
| Carolina Ballroom (Lounge) | `room.php?room=carolina` |
| AlmaLinux Classroom | `room.php?room=almalinux` |

Example: `https://your-host.example/room.php?room=salon-a`

## Configuration

All settings live in `config.php`.

| Setting | Purpose |
|---------|---------|
| `event_title` | Browser tab title suffix (e.g. `SELF 2026`) |
| `pretalx_host` | pretalx instance base URL |
| `event_slug` | Event slug on pretalx (API path is built from host + slug) |
| `timezone` | Schedule and clock timezone (`America/New_York`) |
| `refresh_seconds` | How often each page auto-reloads |
| `cache_ttl_seconds` | How long to reuse cached schedule data |
| `event_logo` | Masthead image in the page header |
| `gold_sponsors` | Sponsor name, logo URL, and link for the footer bar |
| `rooms` | Maps URL slug → pretalx room ID, label, and subtitle |
| `allow_test_clock` | Enable fake date/time for previews (`false` on TVs) |
| `test_now` | Default fake time when test clock is enabled |

Sponsor logos for gold tier are listed on the [SELF sponsors page](https://southeastlinuxfest.org/about/sponsors/).

## Previewing a specific day or time

Schedule times use **America/New_York**. To test without changing the system clock:

1. Set `'allow_test_clock' => true` in `config.php`
2. Open a room with a time override, for example:  
   `room.php?room=salon-a&now=2026-06-12T10:30:00`
3. Or set a default in config: `'test_now' => '2026-06-12T10:30:00'`
4. For **date only**, use `now=2026-06-12` (defaults to noon that day)

Pick a time inside a real session to see **Now** and **Up Next** populated. A yellow test banner appears while overrides are active.

For SouthEast LinuxFest 2026, pretalx slots begin on **2026-06-12**.

## Project layout

```
config.php          Settings and room mapping
index.php           Room picker (setup)
room.php            Per-ballroom TV display
lib/                pretalx client and schedule logic
templates/partials/ Hero, logo, and sponsor partials
assets/tv.css       Signage styles
cache/              Cached schedule JSON (not committed)
```
