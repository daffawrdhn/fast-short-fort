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
- QR code generation (PNG/SVG) for every link
- Password-protected links
- Link expiration & click limits
- Link cloaking (iframe masking)
- UTM builder
- Deep link / mobile app scheme support

### Auth & Security
- Register/Login with Argon2id hashing
- Email verification (optional SMTP)
- Two-Factor Authentication (TOTP — Google Authenticator)
- "Remember Me" with secure token rotation
- Password reset with expiring tokens
- CSRF, XSS, SQL injection prevention
- Rate limiting (global, login, link creation, API)
- Malicious URL detection (blocklist + Google Safe Browsing)
- Security headers (HSTS, CSP, X-Frame-Options, etc.)

### Dashboard
- Quick URL shortener
- Links list: searchable, sortable, filterable
- Bulk actions: delete, enable/disable, export (CSV/JSON)
- Per-link analytics

### Analytics
- Total & unique clicks
- Geolocation (country/city)
- Device, browser, OS breakdown
- Referrer tracking
- Time-series charts (hourly/daily/monthly)
- Real-time click feed (polling)
- CSV export

### Teams
- Multi-workspace
- Role-based access (Owner, Admin, Editor, Viewer)
- Invite members via email
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

## 🗂 Project Structure

```
├── app/                # Controllers, Models, Services, Middleware, Core
├── config/             # App & database config
├── database/           # Migrations
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
- Semua password di-hash Argon2id
- Prepared statements (no SQL injection)
- CSRF tokens di semua form
- Output escaping (`htmlspecialchars`)
- Rate limiting, session security, security headers

---

## 📄 License

MIT — see [LICENSE](LICENSE).

## 🤝 Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).
