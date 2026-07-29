# Security Policy

## Reporting a Vulnerability

We take the security of FORT (Fast Short) seriously. If you discover a security vulnerability, please report it to us privately.

**Do not** disclose the issue publicly until it has been addressed by the maintainers.

### How to Report

- **Email**: security@example.com
- **GitHub**: Use [GitHub Private Security Advisories](https://github.com/daffawrdhn/fast-short-fort/security/advisories/new)

### What to Include

- A description of the vulnerability
- Steps to reproduce the issue
- Affected versions
- Any potential impact or exploit scenario

### Response Commitment

- Acknowledgement within **48 hours**
- Initial assessment within **5 business days**
- Critical vulnerabilities prioritized and addressed ASAP
- Progress updates throughout remediation

---

## Security Measures Implemented

### v4.2.0 — 2026-07-29 (Deep Audit Hardening)

**Critical Fixes (7 applied):**
- [x] **Email verification token isolation** — dedicated `email_verification_token` column; no longer shares `remember_token` with remember-me sessions. Prevents token confusion authentication bypass.
- [x] **2FA redirect fixed** — `/twofa` was a dead route (404); corrected to `/twofa/challenge`
- [x] **Email resend redirect fixed** — `/email/verify` was a dead route (404); corrected to `/verify-email`
- [x] **Password-protected link redirect fixed** — `/links/password/{slug}` was a dead route; corrected to `/p/{slug}`
- [x] **Admin session data fixed** — `$_SESSION['user']` was never populated; admin panel now reads the correct session keys set by `AuthService`
- [x] **URL blocklist SQL fixed** — PDO parameter in LIKE left-side is unsupported; replaced with PHP-level `str_contains()` iteration (portable across SQLite & PostgreSQL)
- [x] **Session expiry graceful handling** — replaced uncaught `RuntimeException` with graceful redirect to `/login?reason=session_expired`

**Security Fixes:**
- [x] **Argon2id hashing consistency** — `User::create()` was using `PASSWORD_BCRYPT` directly, now correctly uses `Hash::make()` (Argon2id) consistent with `AuthService`
- [x] **Rate limit race condition fixed** — SELECT+UPDATE race replaced with atomic `INSERT ... ON CONFLICT DO UPDATE` UPSERT for both SQLite and PostgreSQL
- [x] **View double-output fixed** — `View::render()` was calling `echo` before `Response::send()`, risking headers-already-sent errors; now fully buffered
- [x] **Dead code removed** — `Link::incrementClicks()` was incorrect (only updated `updated_at`, not click counter) and never called; removed

### v4.1.0 — Post-Audit Security Hardening (Jan–Mar 2025)

**Critical Fixes (15 applied):**
- [x] AdminMiddleware split — user vs admin routes properly separated
- [x] Password reset tokens hashed in database; never exposed in error URLs
- [x] All admin POST routes registered (create/edit/delete user, workspace, blocklist)
- [x] CSRF protection on **all** forms including logout
- [x] Rate limiting on password reset endpoints (web + API)
- [x] Password complexity enforced at all entry points (Profile, Admin, API)
- [x] `password_hash` excluded from API responses (`Link::toArray()`)
- [x] Remember-me tokens invalidated on password change
- [x] Installer defaults secured: `SESSION_HTTPS_ONLY=true`, `SESSION_SECURE_COOKIE=true`, `CORS_ALLOWED_ORIGINS=APP_URL`
- [x] Consistent `Env::get()` usage throughout codebase
- [x] Token comparison hardened for password reset
- [x] `Response::back()` validates referer against `APP_URL` (open redirect prevention)
- [x] Global exception handler does not leak stack traces to users in production
- [x] Content-Type validation on API endpoints
- [x] Low-risk mitigations and ongoing improvements

---

## Security Architecture

### Multi-Layer Defense

| Layer | Mechanism |
|-------|-----------|
| **Transport** | HSTS (`max-age=31536000; includeSubDomains` in production) |
| **Authentication** | Argon2id password hashing, TOTP 2FA, rotating remember-me tokens |
| **Email Verification** | Dedicated single-use `email_verification_token` — cleared after use |
| **Session** | `session_regenerate_id()` on login, idle timeout with graceful redirect |
| **CSRF** | Per-session token validated on all state-changing requests |
| **Rate Limiting** | Atomic database UPSERT per IP/API key per endpoint |
| **SQL Injection** | PDO prepared statements throughout |
| **XSS** | `htmlspecialchars()` on all user-controlled output |
| **Headers** | X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy |
| **URL Blocklist** | PHP-level `str_contains()` pattern matching (no SQL injection surface) |
| **API Auth** | JWT (HS256, expiring) + API Key (SHA-256 hashed in DB) |
| **Admin Protection** | `AdminMiddleware` — dedicated check on `$_SESSION['user_is_admin']` |

### Known Limitations / Tech Debt

- No `Content-Security-Policy` header — recommended for XSS mitigation in depth
- Webhook delivery is synchronous with blocking `sleep()` retries — consider async queue for production
- Rate limits use database (adequate for moderate traffic); consider Redis for high-scale
- Blocklist is substring-match only — does not support wildcard or regex patterns

---

## Supported Versions

| Version | Supported |
|---------|-----------|
| 4.2.x   | ✅ Yes (current) |
| 4.1.x   | ⚠️ Security fixes only |
| < 4.1   | ❌ No |

---

**Last Updated**: 2026-07-29 (v4.2.0 — Deep Audit Hardening)
