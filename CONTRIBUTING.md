# Contributing to FORT (Fast Short)

Thank you for considering contributing to FORT! We welcome contributions from everyone.

## How to Contribute

1. **Fork** the repository on GitHub.
2. **Create a branch** for your feature or bug fix:
   ```
   git checkout -b feature/your-feature-name
   ```
3. **Commit your changes** with clear, descriptive commit messages.
4. **Push** to your fork and submit a **Pull Request** to the `main` branch.

## Code Style

This project follows **PSR-12** coding standards. Please ensure your code adheres to these standards:

- Run `vendor/bin/phpcs --standard=PSR12 app/` to check your code.
- Use meaningful variable and function names.
- Keep methods focused and concise.

## Testing

All contributions must include tests where applicable. We use **PHPUnit** for testing.

- Run tests with: `vendor/bin/phpunit`
- Place unit tests in `tests/Unit/`
- Ensure all existing tests pass before submitting a PR.

## Security Testing

When implementing new features or fixing vulnerabilities:

1. **Review security requirements** at least once per change: Check for:
   - SQL injection (use prepared statements)
   - XSS (escape all user output with `htmlspecialchars()`)
   - CSRF (add `validateCsrf()` to all POST/PUT/DELETE endpoints)
   - Session security (use HttpOnly, Secure, SameSite cookie flags)
   - Authentication flow (verify all protected routes enforce auth)
   - Input validation (validate all user input against strict rules)
   - Rate limiting (apply middleware where needed)
   - Password policy (min length, complexity, and hash policy consistent across all endpoints)
   - Feature toggle for third-party services (never default to enabled)

2. **Security-specific tests** for changes:
   - Validate CSRF protection on new forms or API endpoints
   - Test input validation and sanitization
   - Ensure password reset uses hashed tokens and secure handling
   - Verify all environment variables (API keys, secrets) loaded via `Env::get()`
   - Confirm that all exceptions throw a generic error message in production

3. **Automated security checks**:
   ```bash
   # Basic lint (code style)
   vendor/bin/phpcs --standard=PSR12 app/
   
   # Unit tests
   vendor/bin/phpunit --coverage-text
   
   # Manual security review checklist (see SECURITY.md for checklist)
   ```

4. **Development Security Checklist** (manual verification during development):
   
   [x] Apakah menemukan kerentanan terlarang dalam audit (misalnya token plaintext, password complexity tidak konsisten, route tak terlindungi)?
   [x] Apakah mengubah jalur autentikasi (misalnya admin routes, reset password)?
   [x] Apakah mengubah validasi input (misalnya feature toggle, validation rules)?
   [x] Apakah menambahkan fitur baru yang berhubungan dengan token, cookie, or session?
   [x] Apakah mengubah skema database (misalnya menambah kolom, new tables)?
   [x] Apakah mengubah middleware handler (misalnya AuthMiddleware, InstallerMiddleware)?
   [x] Apakah menerapkan API baru yang memerlukan autentikasi?
   [x] Apakah mengubah penanganan error atau framework exception?
   [x] Apakah mengedit file .env secara manual atau membuat .env baru?
   [x] Apakah menghubungkan ke third-party services (Google Safe Browsing, EmailService, Webhook)

## Reporting Issues

- Use the [GitHub issue tracker](https://github.com/daffawrdhn/fast-short-fort/issues) to report bugs.
- Include a clear description of the issue, steps to reproduce, and expected behavior.
- Add environment details (PHP version, database, OS) when relevant.

## Feature Requests

- Open an issue with the **feature request** label.
- Describe the feature and the problem it solves.
- Be open to discussion and feedback from maintainers.

## Development Setup

1. Clone the repository:
   ```
   git clone https://github.com/daffawrdhn/fast-short-fort.git
   cd fort
   ```
2. Install dependencies:
   ```
   composer install
   ```
3. Copy the environment file:
   ```
   cp .env.example .env
   ```
4. Run database migrations:
   ```
   php database/migrate.php
   ```
5. Start the development server:
   ```
   php -S localhost:8000 -t public
   ```

## Pull Request Checklist

- [ ] Code follows PSR-12 standards.
- [ ] Tests are added/updated and pass.
- [ ] Documentation is updated if needed.
- [ ] PR targets the `main` branch.
- [ ] Commits are squashed and logically grouped.
- [ ] Security review checklist completed (feature toggles, validation, authentication, session/cookie security, third-party integration, CSRF, rate-limiting, password policy, and debug info).

Thank you for helping improve FORT!
