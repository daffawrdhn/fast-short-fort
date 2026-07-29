# Security Policy

## Reporting a Vulnerability

We take the security of FORT (Fast Short) seriously. If you discover a security vulnerability, please report it to us privately.

**Do not** disclose the issue publicly until it has been addressed by the maintainers.

### How to Report

- **Email**: security@example.com
- **PGP Key**: If available, please encrypt your report using the maintainer's PGP key (key ID and fingerprint to be added here).

### What to Include

- A description of the vulnerability.
- Steps to reproduce the issue.
- Affected versions.
- Any potential impact or exploit scenario.

### Response Commitment

- We will acknowledge receipt of your report within **48 hours**.
- We aim to provide an initial assessment within **5 business days**.
- Critical vulnerabilities will be prioritized and addressed as quickly as possible.
- We will keep you informed of progress toward a fix and release timeline.

### Security Measures Implemented

This version includes the **complete security audit** from January-March 2025:

**Critical Fixes (15 applied):**
- [x] **AdminMiddleware was blocking all routes** — split admin and user routes
- [x] Password reset tokens **hashed** di database; dihapus dari URL pada error
- [x] All admin POST routes terdaftar (create/edit/delete user, workspace, blocklist)
- [x] **CSRF protection di semua forms** (termasuk logout)
- [x] **Rate limiting di password reset endpoints** (browser, API)
- [x] Password complexity enforced di **semua** backend (Profil, Admin, API)
- [x] `password_hash` dihapus dari API responses (`Link::toArray()`)
- [x] **Token remember di-invalidate** saat password berubah
- [x] Installer defaults secures: `SESSION_HTTPS_ONLY=true`, `SESSION_SECURE_COOKIE=true`, `CORS_ALLOWED_ORIGINS=APP_URL`
- [x] `Env::get()` consistency — semua perubahan ke lingkungan konsisten
- [x] Token comparison untuk save token reset
- [x] **Response::back() validations** di `Response.php`
- [x] Global exception handler pembocorkan debugging riat
- [x] Content-Type validation endpoint API (mengurangi serangan `PUT`/`PATCH`)
- [x] Low-risk mitigations and ongoing improvements based on audit findings

**Additional Protections:**
- **Browser-side**: CSP, HSTS, X-Frame-Options, XSS protection, Referrer-Policy
- **Session security**: Browser-keep cookie flag, Secure cookie allow, browser safe
- **Auth security**: Argon2id hashing, 2FA (TOTP), email verification, token remember rotasi, CORS restricted
- **Transport security**: https-only mode default di produksi, CSP enforcement, pencegahan file upload terlarang
- **Injection prevention**: Prepared statements, escaping (`htmlspecialchars`), validasi input

### Known Limitations

- Ini adalah impor `run-tests` akhir yang diterapkan si ISSN/**mengatasi kerentanan kritis dan biasa** yang teridentifikasi di 2025 Namun implementasi ini **terbuka untuk eksperimen akar baru** dan kemungkinan perbaikan arsitektur tambahan:
  - CSRF pada token API (kalau menggunakan header Authorization Bearer)
  - Kelemahan di spesifik endpoint yg fokus mendapat serangan high-volume (seperti `/install/`).
  - Tiruan keamanan token awasi Di pencurian session cookie.

### Contact Us

For security issues, contact:
- **Email**: security@example.com
- **PGP Key**: [Akan disediakan]

Be sure to use strong encryption if available and include your bug report and reproduction steps.

---

*Laporan perbaikan keamanan akan diprioritaskan berdasarkan tingkat keparahan. Tim keamanan bekerja hari kerja standard kecuali untuk eskalasi darurat.*

---

Keamanan FORT:
- [x] Token reset hash
- [x] Response back validation
- [x] Auth: CSRF + escaped output
- [x] DB: Prepared statements
- [x] Session: browser-safe cookie
- [x] Cookie secure, remember me IP bounds
- [x] CSP: full restrictions
- [x] Global exception handler

Multi-layer defense yang komprehensif diterapkan. Tetap waspada!

---

**Last Updated**: July 29, 2026 (v4.1.0 — Post-Audit Security Hardening)

### Previous Revisions

- v3.2.0 (Aug 2024)
- v3.1.0 (Apr 2024)
- ...
