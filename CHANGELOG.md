# Changelog

All notable changes to **FORT (Fast Short)** will be documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), versioning follows [Semantic Versioning](https://semver.org/).

---

## [4.2.0] — 2026-07-29

### Security

- **CRITICAL**: Separate email verification token into dedicated `email_verification_token` column — previously shared `remember_token` with remember-me, enabling token confusion authentication bypass
- **CRITICAL**: Fix URL blocklist — SQL query was non-functional (PDO parameter cannot appear on left side of LIKE); replaced with PHP-level `str_contains()` iteration portable across SQLite & PostgreSQL
- **MEDIUM**: Fix `User::create()` password hashing — was using `PASSWORD_BCRYPT` directly, now uses `Hash::make()` (Argon2id) consistent with the rest of the codebase
- **MEDIUM**: Fix rate limit race condition — SELECT+then-UPDATE pattern replaced with atomic `INSERT ... ON CONFLICT DO UPDATE` UPSERT for both SQLite and PostgreSQL

### Fixed

- **CRITICAL**: 2FA login redirect was pointing to `/twofa` (404 route) — corrected to `/twofa/challenge` (4 occurrences in `AuthController`)
- **CRITICAL**: Resend email verification redirect was pointing to `/email/verify` (404 route) — corrected to `/verify-email`
- **CRITICAL**: Password-protected link redirect was pointing to `/links/password/{slug}` (404 route) — corrected to `/p/{slug}`
- **CRITICAL**: Admin panel user data — `$_SESSION['user']` was never populated by `AuthService::createSession()`; admin layout now reads `$_SESSION['user_id']`, `user_name`, `user_email`, `user_is_admin` directly
- **MAJOR**: Session expiry — was throwing uncaught `RuntimeException('Session expired')` causing ugly 500 error page; now gracefully redirects to `/login?reason=session_expired`
- **MAJOR**: `View::render()` double output — method was calling `echo $content` directly AND returning a `Response` object; output is now fully buffered via `Response::body()` and sent once via `Response::send()`

### Removed

- **MAJOR**: Dead code `Link::incrementClicks()` — method only updated `updated_at` (did not actually increment a click counter) and was never called anywhere; click tracking is handled correctly by `AnalyticsService::recordClick()` → `link_clicks` table

### Improved

- `visitor_uuid` cookie `secure` flag now respects `SESSION_HTTPS_ONLY` environment variable instead of being hardcoded to `true` (was breaking local HTTP development)
- Updated E2E test suite: removed call to deleted `incrementClicks()`, added click count verification via `link_clicks` table
- **Database**: Added migration `019_add_email_verification_token_to_users.sql` — adds `email_verification_token VARCHAR(255) NULL` to `users` table (SQLite & PostgreSQL)

### Testing

- All 108 E2E tests pass (0 failures) after fixes

---

## [4.1.0] — 2025-03-01

### Security (15 critical fixes applied post-audit)

- AdminMiddleware split — user vs admin routes properly separated
- Password reset tokens hashed in DB; removed from error URLs
- All admin POST routes registered
- CSRF protection on all forms including logout
- Rate limiting on password reset endpoints
- Password complexity enforced at all entry points
- `password_hash` removed from API responses
- Remember-me tokens invalidated on password change
- Installer defaults secured (HTTPS_ONLY, SECURE_COOKIE, CORS)
- `Response::back()` validates referer against `APP_URL`
- Global exception handler does not leak stack traces in production

---

## [4.0.0] — 2024-09-01

### Added

- Teams/workspace with RBAC (Owner, Admin, Editor, Viewer)
- REST API with JWT + API key auth
- Custom domains with DNS CNAME verification
- Webhooks with delivery log and retry
- Audit log per workspace
- Real-time analytics click feed

---

## [3.0.0] — 2024-04-01

### Added

- Multi-driver database support: PostgreSQL 15+ and SQLite 3.x
- Docker & docker-compose support
- Web installer wizard
- Two-factor authentication (TOTP)
- Analytics: geolocation, device/browser/OS, referrer, time-series charts
