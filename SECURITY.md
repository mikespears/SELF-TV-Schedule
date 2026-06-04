# Security

This document summarizes the threat model, controls, and deployment checklist for SELF Talk Schedule Display.

## Threat model

- **Public TVs** load `room.php` and `index.php` without authentication.
- **Admin** (`admin/`) is password-protected; compromise grants config and user management.
- **Outbound HTTP** fetches pretalx schedule data based on configured host.
- **Sensitive files** (`data/admin/users.json`, `data/settings.json`, cache) must not be web-readable.

## Controls implemented

| Area | Control |
|------|---------|
| Admin auth | Per-user bcrypt hashes in `data/admin/users.json` |
| Sessions | `HttpOnly`, `SameSite=Strict`, `Secure` on HTTPS (port 443), ID regeneration on login |
| Session invalidation | `auth_version` bumped on password change; idle timeout 8 hours |
| CSRF | Token on all admin POST forms (including logout) |
| Login brute force | IP-based lockout in `data/admin/login-rates.json` (5 failures / 15 min) |
| First-time setup | Requires `data/admin/setup.token` (consumed after first account) |
| SSRF (pretalx) | HTTPS only, public DNS resolution, no redirects, path prefix tied to `api_base` |
| URLs in config | HTTPS + public host validation on save and load |
| XSS | User-facing strings escaped with `e()` in templates |
| Room slugs | Whitelist via `config['rooms']` keys only |
| File permissions | Private data written mode `0600`, dirs `0700` |
| HTTP headers | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, CSP |
| Web exposure | `data/.htaccess`, `cache/.htaccess`, `lib/.htaccess`, deny `config.php` / `bootstrap.php` (Apache) |

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
6. Delete legacy `data/admin.secrets.php` after migration to `users.json`.

### nginx snippets

```nginx
location ^~ /data/ { deny all; }
location ^~ /cache/ { deny all; }
location ^~ /lib/ { deny all; }
location ~ ^/(config|bootstrap)\.php$ { deny all; }
```

## Residual risks

- **Test clock** — When enabled, anyone can pass `?now=` on room URLs. Keep disabled on public TVs.
- **Host header / proxy** — Configure trusted proxies explicitly; do not forward client `X-Forwarded-Proto` from untrusted sources.
- **No MFA** — Stolen passwords grant full admin access; use strong passwords and network restriction.
- **Single-server sessions** — No shared session store; fine for one PHP node.

## Reporting

Report security issues to the repository maintainer privately rather than in public issues if the issue is sensitive.
