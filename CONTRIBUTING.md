# Contributing to PHP Stream IPC

Thank you for your interest in contributing to PHP Stream IPC! This document provides guidelines and instructions to help you contribute effectively to this project.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for everyone.

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Git

### Setting Up the Development Environment

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/php-stream-ipc.git
   cd php-stream-ipc
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Set up git to track upstream:
   ```bash
   git remote add upstream https://github.com/riki137/php-stream-ipc.git
   ```

## Development Workflow

1. Create a new branch for your feature or bugfix:
   ```bash
   git checkout -b feature/your-feature-name
   ```
   or
   ```bash
   git checkout -b fix/issue-description
   ```

2. Make your changes following the coding standards

3. Write tests for your changes:
   - Unit tests go in `tests/Unit/`
   - Integration tests go in `tests/Integration/`
   - Fixtures go in `tests/Fixtures/`

4. Run tests and static analysis:
   ```bash
   vendor/bin/phpunit
   vendor/bin/phpstan analyse
   ```

5. Commit your changes with a descriptive message:
   ```bash
   git commit -m "Feature/Fix: concise description of changes"
   ```

6. Push to your fork:
   ```bash
   git push origin feature/your-feature-name
   ```

7. Open a Pull Request against the main repository

## Coding Standards

- Follow PSR-12 coding standards
- Use strict typing (`declare(strict_types=1);`) in all PHP files
- Add appropriate docblocks for classes and methods
- Keep methods focused and maintain separation of concerns
- Use meaningful variable and method names

## Testing

- All new features must include tests
- All bug fixes should include tests that demonstrate the issue is fixed
- Run the full test suite before submitting a PR:
  ```bash
  vendor/bin/phpunit
  ```

## Pull Request Process

1. Update the README.md with details of significant changes if applicable
2. Ensure your code passes all tests and static analysis
3. The PR should work for PHP 8.2 and above
4. The PR will be merged once it's reviewed and approved by maintainers

## Areas for Contribution

Current focus areas for contributions include:

- Enhancing AMPHP transport stability and tests
- Improving error handling documentation and examples
- Adding additional serialization formats
- Performance optimizations
- Expanding test coverage

## Documentation

If you're adding new features, please update the relevant documentation:
- Code-level documentation via PHPDoc comments
- User-facing documentation in README.md or related docs

## Questions?

If you have questions about the contribution process or need help, please open an issue in the repository.

Thank you for contributing to PHP Stream IPC!
