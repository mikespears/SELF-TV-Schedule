# Security

This document summarizes the threat model, controls, and deployment checklist for SELF Talk Schedule Display.

## Threat model

- **Public TVs** load `room.php` and `index.php` without authentication.
- **Admin** (`admin/`) is password-protected; compromise grants config and user management.
- **Outbound HTTP** fetches pretalx schedule data based on configured host.
- **Sensitive files** under `data/` and schedule cache under `cache/` must not be web-readable.

## Controls implemented

| Area | Control |
|------|---------|
| Admin auth | Per-user password hashes (`password_hash` / `password_verify`) in MySQL `admin_users` or `data/admin/users.json` when no database is configured |
| Settings storage | Dashboard overrides in MySQL `app_settings` or `data/settings.json` when no database is configured |
| Sessions | `HttpOnly`, `SameSite=Strict`, `Secure` on HTTPS (port 443), ID regeneration on login |
| Session invalidation | `auth_version` incremented on password change; idle timeout 8 hours |
| CSRF | Token on all admin POST forms (including logout) |
| Login brute force | IP-based lockout in `data/admin/login-rates.json` (5 failures / 15 min) |
| Login rate-limit IP | Uses `REMOTE_ADDR` only (not client-supplied `X-Forwarded-For`) |
| First-time setup | Requires one-time `data/admin/setup.token` (consumed after first account) |
| SSRF (pretalx) | HTTPS only, public DNS resolution on admin save, no redirects, pagination URLs restricted to configured host/path |
| URLs in config | HTTPS + public host validation on save; relaxed DNS check at runtime load |
| XSS | User-facing strings escaped with `e()` in templates |
| Room slugs | Whitelist via `config['rooms']` keys only |
| File permissions | Private data written mode `0600`, dirs `0700` |
| HTTP headers | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, CSP on public and admin pages |
| Web exposure | `data/.htaccess`, `cache/.htaccess`, `lib/.htaccess`, deny `config.php` / `bootstrap.php` (Apache) |
| Speaker avatars | HTTPS image URLs only; optional admin toggle |

## Deployment checklist

1. Serve over **HTTPS** in production.
2. Confirm **nginx/Apache** blocks:
   - `/data/`
   - `/cache/`
   - `/lib/`
   - Direct access to `config.php` and `bootstrap.php`
3. Before exposing `/admin/`:
   - Create `data/admin/setup.token` with a long random secret (see `data/admin/setup.token.example`).
   - Complete first-account setup, then verify `setup.token` was removed.
4. Keep `allow_test_clock` **false** on production TVs.
5. Run PHP-FPM as a dedicated user; avoid world-readable files under `data/`.
6. Behind a reverse proxy, configure the proxy to pass the real client IP to PHP (`REMOTE_ADDR`) or accept that login rate limits apply per proxy connection.
7. Delete legacy `data/admin.secrets.php` after migration to `users.json` or MySQL.
8. For MySQL deployments: keep `data/database.php` out of version control; use a dedicated DB user with least privilege; prefer TLS to the database when the server is remote.

### nginx snippets

```nginx
location ^~ /data/ { deny all; }
location ^~ /cache/ { deny all; }
location ^~ /lib/ { deny all; }
location ~ ^/(config|bootstrap)\.php$ { deny all; }
```

## Residual risks

- **Test clock** — When enabled, anyone can pass a time override on room URLs. Keep disabled on public TVs.
- **No MFA** — Stolen passwords grant full admin access; use strong passwords and restrict `/admin/` by network where possible.
- **Admin password reset** — A signed-in admin can reset another user’s password without re-entering their own password (session is the control).
- **Single-server sessions** — No shared session store; fine for one PHP node.

## Audit notes (2026-06)

Review covered admin auth, pretalx fetch SSRF, config validation, CSP, and template escaping.

**Fixed in this pass**

- Password changes now increment `auth_version` so existing sessions end after a reset.
- Login rate limiting no longer trusts `X-Forwarded-For` from clients.
- First-time setup form includes the setup token field (was missing).
- Admin UI copy no longer exposes filesystem paths or hash implementation details.

**Verified OK**

- pretalx HTTP: no redirects, host/path allowlist on pagination URLs.
- Cannot disable or delete the last active admin.
- Own-password change requires current password; other users require only an admin session.
- CSRF on admin POST including logout.

## Reporting

Report security issues to the repository maintainer privately rather than in public issues if the issue is sensitive.
