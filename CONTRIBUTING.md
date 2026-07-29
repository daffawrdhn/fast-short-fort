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

## Reporting Issues

- Use the [GitHub issue tracker](https://github.com/your-org/fort/issues) to report bugs.
- Include a clear description of the issue, steps to reproduce, and expected behavior.
- Add environment details (PHP version, database, OS) when relevant.

## Feature Requests

- Open an issue with the **feature request** label.
- Describe the feature and the problem it solves.
- Be open to discussion and feedback from maintainers.

## Development Setup

1. Clone the repository:
   ```
   git clone https://github.com/your-org/fort.git
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

Thank you for helping improve FORT!
