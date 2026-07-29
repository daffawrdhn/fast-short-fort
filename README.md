# FORT (Fast Short)

**Enterprise-Grade Open-Source URL Shortener**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net)
![CI](https://github.com/daffawrdhn/fast-short-fort/actions/workflows/ci.yml/badge.svg)

Built with **Native PHP 8.2+** — no heavy frameworks. Self-host on shared hosting, VPS, or Docker. Dual database support (PostgreSQL & SQLite).

---

## ✨ Features

### Core
- Shorten URLs with custom aliases or random slugs
- Auto-prepend `https://` prefix for user convenience if scheme is missing
- QR code generation (PNG/SVG) for every link
- Password-protected links
- Link expiration & click limits
- Link cloaking (iframe masking)
- UTM builder
- Deep link / mobile app scheme support

### Auth & Security
- Register/Login with **Argon2id** hashing (consistent across all registration paths)
- Email verification (optional SMTP) using **dedicated, single-use token** — separate from remember-me tokens
- Two-Factor Authentication (TOTP — Google Authenticator compatible)
- "Remember Me" with **secure token rotation** and browser-secure cookie flag (`SESSION_HTTPS_ONLY=true`)
- Password reset with expiring **hashed** tokens (sha256), single-use, no token exposure in URLs
- CSRF protection on **all** forms (including logout)
- XSS, CSRF, SQL injection prevention
- **Atomic rate limiting** (no race condition — uses database UPSERT) for global, login, link creation, API, password reset
- Malicious URL detection (blocklist — PHP `str_contains` match, portable across SQLite & PostgreSQL)
- Security headers (HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy)
- Separate admin routes with `AdminMiddleware` protection
- Feature toggles for all third-party services (default to `false`)
- **Graceful session expiry** — redirect to `/login` instead of unhandled 500 error
- Soft delete for links with restore/forceDelete

### Dashboard
- Quick URL shortener
- Links list: searchable, sortable, filterable
- Bulk actions: delete, enable/disable, export (CSV/JSON)
- Per-link analytics

### Analytics
- Total & unique clicks
- Geolocation (country/city) with automatic fallback IP lookup
- Device, browser, OS breakdown
- User preferred language tracking (parsed from `Accept-Language` headers) with pie charts
- Raw IP Address logging
- Referrer tracking
- Time-series charts (hourly/daily/monthly)
- Real-time click feed (polling)
- CSV/JSON exports (per-link and workspace-wide)

### Teams
- Multi-workspace switcher dashboard
- Role-based access (Owner, Admin, Editor, Viewer)
- Invite members via email with role configuration
- Revoke member access instantly
- Audit log per workspace

### REST API
- JWT + API Key authentication
- Full CRUD for links, workspaces, domains, webhooks
- Rate limiting per key
- OpenAPI 3.0 spec included

### Custom Domains
- Bring your own domain
- DNS verification (CNAME)

### Admin Panel
- User & workspace management
- System health dashboard
- URL blocklist
- Global settings
- Audit logs

---

## 🚀 Quick Start (Docker)

```bash
git clone https://github.com/daffawrdhn/fast-short-fort.git
cd fast-short-fort
docker-compose up -d
```

Visit `http://localhost` and follow the installer wizard.

---

## 📦 Manual Installation

### Requirements
- PHP 8.2+
- Composer
- PostgreSQL 15+ **or** SQLite 3.x
- Extensions: `pdo`, `pdo_pgsql`/`pdo_sqlite`, `json`, `mbstring`, `openssl`, `gd`, `curl`, `xml`, `bcmath`

### Steps

```bash
git clone https://github.com/daffawrdhn/fast-short-fort.git
cd fast-short-fort
composer install
```

```bash
cp .env.example .env
# Edit .env — DB_DRIVER defaults to sqlite
```

```bash
chmod -R 775 storage/
```

Visit `http://your-domain.com/install` and follow the wizard.

### Or run migrations manually

```bash
php database/migrate.php
```

### Cronjob (cleanup expired links, etc.)

```
* * * * * php /path/to/fort/cron/cleanup.php
```

---

## 🪟 Instalasi di XAMPP

1. **Letakkan project** di `C:\xampp\htdocs\fort`
2. **Install dependencies**:
   ```bash
   cd C:\xampp\htdocs\fort
   composer install
   ```
3. **Konfigurasi `.env`**:
   ```bash
   copy .env.example .env
   ```
   Edit `.env` — `DB_DRIVER=sqlite` sudah default, tidak perlu setup database.
4. **Set permission**:
   Pastikan folder `storage/` writable (Properties → Security → beri write untuk User).
5. **Aktifkan `mod_rewrite`**:
   - Buka `C:\xampp\apache\conf\httpd.conf`
   - Hapus `#` dari `#LoadModule rewrite_module modules/mod_rewrite.so`
   - Cari `<Directory "C:/xampp/htdocs">` dan ubah `AllowOverride None` menjadi `AllowOverride All`
   - Restart Apache via XAMPP Control Panel
6. **Jalankan installer**:
   Buka `http://localhost/fort` di browser dan ikuti wizard.

### Virtual Host (opsional)
```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/fort/public"
    ServerName fort.local
</VirtualHost>
```
Tambahkan `127.0.0.1 fort.local` di `C:\Windows\System32\drivers\etc\hosts`.

---

## 🪟 Instalasi di Laragon

1. **Letakkan project** di `C:\laragon\www\fort`
2. **Install dependencies**:
   ```bash
   cd C:\laragon\www\fort
   composer install
   ```
3. **Konfigurasi `.env`**:
   ```bash
   copy .env.example .env
   ```
   `DB_DRIVER=sqlite` sudah default.
4. **Set permission**: Laragon otomatis memberikan permission yang tepat.
5. **Aktifkan `mod_rewrite`**:
   - Laragon sudah mengaktifkan `mod_rewrite` secara default.
   - Pastikan `AllowOverride All` di `C:\laragon\etc\apache2\sites-enabled\*.conf`
6. **Jalankan installer**:
   - Klik **"WWW"** pada Laragon → pilih `fort`
   - Atau buka `http://fort.test` (Laragon auto virtual host)

### Menggunakan PostgreSQL di Laragon
- Laragon sudah include PostgreSQL. Klik kanan Laragon → Tools → Quick Add → PostgreSQL
- Di `.env` ubah `DB_DRIVER=pgsql` dan isi koneksi database

### Pretty URL
Laragon sudah auto-configure rewrite. Pastikan file `public/.htaccess` ada (sudah disediakan).

---

## 🗂 Project Structure

```
├── app/                # Controllers, Models, Services, Middleware, Core
├── config/             # App & database config
├── database/           # Migrations (001–019)
├── public/             # Web root (only folder exposed)
│   ├── index.php       # Front controller
│   ├── api.php         # API entry point
│   └── assets/         # CSS, JS, OpenAPI spec
├── resources/views/    # PHP templates
├── storage/            # Logs, cache, SQLite DB
├── cron/               # Scheduled tasks
├── docker/             # Docker config
├── docker-compose.yml
├── Dockerfile
└── nginx.conf.example
```

---

## 🔌 API Examples

```bash
# Login
curl -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"secret"}'

# Create short link
curl -X POST https://your-domain.com/api/v1/links \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com/long-url","slug":"my-link"}'

# Get analytics
curl -X GET https://your-domain.com/api/v1/links/{id}/analytics \
  -H "Authorization: Bearer <token>"
```

Full API docs at [`public/assets/openapi.yaml`](public/assets/openapi.yaml).

---

## 🛡 Security

- Webroot terpisah (`public/`), direktori lain terproteksi `.htaccess`/nginx
- Semua password di-hash dengan **Argon2id** (konsisten di seluruh codebase)
- Email verification menggunakan **dedicated single-use token** (kolom `email_verification_token`) — terpisah dari remember-me token
- Prepared statements (no SQL injection)
- CSRF tokens di semua form
- Output escaping (`htmlspecialchars`)
- **Atomic rate limiting** via database UPSERT (no race condition)
- Session security, security headers

See [SECURITY.md](SECURITY.md) for full security policy.

---

## 📋 Changelog

### v4.2.0 — 2026-07-29 (Post Deep-Audit Hardening)

**Security Fixes:**
- `[CRITICAL]` Fix email verification token using dedicated `email_verification_token` column — prevents token confusion with `remember_me` cookie (CVE-class: authentication bypass)
- `[CRITICAL]` Fix URL blocklist SQL query — was non-functional due to invalid PDO parameter position in LIKE clause
- `[MEDIUM]` Fix `User::create()` hashing — was using `PASSWORD_BCRYPT`, now consistently uses `PASSWORD_ARGON2ID` via `Hash::make()`
- `[MEDIUM]` Fix rate limit race condition — replaced SELECT+UPDATE with atomic `INSERT ... ON CONFLICT DO UPDATE` UPSERT

**Bug Fixes:**
- `[CRITICAL]` Fix 2FA login redirect — was redirecting to `/twofa` (404), corrected to `/twofa/challenge`
- `[CRITICAL]` Fix resend email verification redirect — was redirecting to `/email/verify` (404), corrected to `/verify-email`
- `[CRITICAL]` Fix password-protected link redirect — was redirecting to `/links/password/{slug}` (404), corrected to `/p/{slug}`
- `[CRITICAL]` Fix admin panel user data — `$_SESSION['user']` was never set by `AuthService`, now reads from correct session keys
- `[MAJOR]` Fix session expiry — was throwing uncaught `RuntimeException` causing error 500, now gracefully redirects to `/login?reason=session_expired`
- `[MAJOR]` Fix `View::render()` double output — removed direct `echo`, output now fully buffered via `Response::body()`
- Remove dead code `Link::incrementClicks()` — method was incorrect (only updated `updated_at`) and never called

**Improvements:**
- `visitor_uuid` cookie `secure` flag now follows `SESSION_HTTPS_ONLY` env var instead of hardcoded `true`

**Database:**
- Migration `019`: Add `email_verification_token` column to `users` table

---

## 📄 License

MIT — see [LICENSE](LICENSE).

## 🤝 Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).
