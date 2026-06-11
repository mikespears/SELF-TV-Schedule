# SELF Talk Schedule Display

TV-friendly schedule boards for ballroom entrances at [SouthEast LinuxFest](https://southeastlinuxfest.org). Schedule data is loaded from the public [pretalx API](https://docs.pretalx.org/api/resources/#tag/schedules) and cached locally to limit API traffic.

## Features

- **Per-room displays** — One URL per ballroom, optimized for a 40″ TV at 1920×1080 (no page scroll)
- **Now / Up Next** — Current and following session with title, speaker, and description
- **Upcoming list** — Up to eight remaining sessions for that room today (past sessions hidden)
- **Sponsor bar** — Sponsor logos along the bottom of each room display (all rooms or per room)
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
- Keep **test clock disabled** in the admin panel (or `allow_test_clock` false in `config.php` with no override)
- Confirm the on-screen clock matches the conference timezone (`America/New_York` by default), not the TV’s local timezone
- Protect `/admin/` with HTTPS and a strong password; block direct web access to `data/` (see Admin panel)

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

**Defaults** live in `config.php` (committed). **Runtime overrides** from the admin panel are stored in **MySQL** (when `data/database.php` is configured) or in `data/settings.json` (gitignored) and merged on each request.

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

## Admin panel

Multi-user, password-protected settings UI at `admin/` (e.g. `https://your-host.example/admin/`).

### First-time setup

1. Ensure the web server cannot serve files under `data/` directly. Apache: `data/.htaccess` denies all requests. For nginx, see [SECURITY.md](SECURITY.md).
2. Create a setup token on the server (before exposing `/admin/` publicly):

   ```bash
   # Example: 32-byte random token
   openssl rand -hex 32 > data/admin/setup.token
   chmod 600 data/admin/setup.token
   ```

3. Open `admin/login.php`, enter the setup token, and **create the first account** (username + password, minimum 10 characters). The token file is deleted after successful setup.
4. Sign in and adjust settings. Changes are saved to MySQL or `data/settings.json`, depending on configuration.

See [SECURITY.md](SECURITY.md) for the full deployment checklist.

### MySQL storage (recommended for production)

1. Create a MySQL database and user with `CREATE`, `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on that database.
2. Copy `data/database.example.php` to `data/database.php` and set host, database name, username, and password.
3. Run `php scripts/setup-database.php` once to create tables (also runs automatically on first connection).
4. If you already have file-based data, run `php scripts/migrate-to-mysql.php` to import `data/admin/users.json` and `data/settings.json`.

When `data/database.php` exists, admin users and dashboard settings use the database exclusively. File-based stores are not read on each request (existing JSON files are imported automatically only when the matching database tables are empty).

**Local Docker dev:** `.\scripts\bootstrap-local.ps1 -UseDocker` creates `data/database.php`, starts MySQL, runs setup/migration, and creates the default `admin` account. Then `docker compose up` serves the app on port 8080.

**Migrating from the old single-password file:** If you already have `data/admin.secrets.php` with a `password_hash`, it is imported automatically on first load as user `admin` with the same password. You can then add more users under **Users**.

### Admin authentication

| Piece | Location |
|-------|----------|
| User accounts | MySQL `admin_users` table, or `data/admin/users.json` (gitignored) when no database is configured |
| Database config | `data/database.php` (gitignored); example in `data/database.example.php` |
| Example structure | `data/admin/users.example.json` |
| Login | `admin/login.php` (username + password) |
| User management | `admin/users.php` (add, disable, delete, change passwords) |

Features:

- Per-user bcrypt password hashes (`password_hash` / `password_verify`)
- Session cookie: HttpOnly, SameSite=Strict, secure flag on HTTPS
- CSRF tokens on all admin POST forms
- Lockout after 5 failed sign-in attempts per IP (15 minutes)
- First-account setup requires `data/admin/setup.token`
- Sessions expire after 8 hours idle; password changes invalidate existing sessions
- POST logout with CSRF (no GET logout)
- Cannot delete or disable the last active admin user

### What you can manage

- Test clock and default preview time
- Event title, timezone, refresh and cache intervals, masthead logo
- Sponsors (name, logo URL, website URL, all rooms or selected rooms)
- Room slugs, pretalx room picker, auto-filled names from pretalx, and optional subtitles
- pretalx host and event slug
- Clear schedule cache (forces refetch on next display load)
- Admin user accounts (`admin/users.php`)

### Security

- Use **HTTPS** and **strong passwords** if `/admin/` is reachable on your network.
- Keep `allow_test_clock` **off** on production TVs; the dashboard warns when it is enabled.
- Do not commit `data/database.php`, `data/admin/users.json`, `data/admin.secrets.php`, or `data/settings.json` (they are gitignored).
- Restrict admin access by firewall or VPN when possible.

## Previewing a specific day or time

Schedule times use **America/New_York**. To test without changing the system clock:

1. Enable **Allow test clock** in the admin panel (or set `'allow_test_clock' => true` in `config.php`)
2. Open a room with a time override, for example:  
   `room.php?room=salon-a&now=2026-06-12T10:30:00`
3. Or set a default test time in the admin panel (or `'test_now'` in config)
4. For **date only**, use `now=2026-06-12` (defaults to noon that day)

Pick a time inside a real session to see **Now** and **Up Next** populated. A yellow test banner appears while overrides are active.

For SouthEast LinuxFest 2026, pretalx slots begin on **2026-06-12**.

## Project layout

```
config.php          Default settings (committed)
data/               Database config, admin users, settings (gitignored)
database/schema.sql Reference schema for app_settings and admin_users
admin/              Login, dashboard, user management
index.php           Room picker (setup)
room.php            Per-ballroom TV display
lib/                Config store, pretalx client, schedule logic
templates/partials/ Hero, logo, and sponsor partials
templates/admin/    Admin layout and dashboard sections
assets/tv.css       Signage styles
assets/admin.css    Admin UI styles
cache/              Cached schedule JSON (not committed)
```
