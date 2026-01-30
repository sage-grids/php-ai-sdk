# Contributing to PHP AI SDK

Thank you for considering contributing to PHP AI SDK! This guide outlines development practices and the contribution workflow.

## Requirements

- PHP 8.1 or higher
- Composer

## Development Setup

```bash
# Clone your fork
git clone https://github.com/sage-grids/php-ai-sdk.git
cd php-ai-sdk

# Install dependencies
composer install
```

## Code Standards

This project enforces strict code quality:

| Tool | Standard | Command |
|------|----------|---------|
| PHP CS Fixer | PSR-12 | `./vendor/bin/php-cs-fixer fix` |
| PHPStan | Level 8 | `./vendor/bin/phpstan analyse` |
| PHPUnit | 10.x | `./vendor/bin/phpunit` |

### Style Requirements

- Use `declare(strict_types=1)` in all PHP files
- Use short array syntax `[]`
- Keep imports alphabetically ordered
- Add type hints for all parameters and return types
- Result objects must be immutable (readonly properties)

## Running Quality Checks

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit tests/Unit
./vendor/bin/phpunit tests/Integration
./vendor/bin/phpunit tests/Feature

# Check code style (dry run)
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix code style
./vendor/bin/php-cs-fixer fix

# Static analysis
./vendor/bin/phpstan analyse
```

### Before Submitting

Run all checks:

```bash
./vendor/bin/php-cs-fixer fix && ./vendor/bin/phpstan analyse && ./vendor/bin/phpunit
```

All checks must pass with zero errors.

## Testing Guidelines

- Write tests for all new functionality
- Place unit tests in `tests/Unit/`
- Place integration tests in `tests/Integration/`
- Place end-to-end tests in `tests/Feature/`
- Use `FakeProvider` for mocking AI responses
- Test immutability of result objects
- Aim for high coverage on new code

Example test structure:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testSomething(): void
    {
        // Arrange
        // Act
        // Assert
    }
}
```

## Git Workflow

### Branch Naming

Use descriptive branch names with prefixes:

- `feature/` - New features
- `fix/` - Bug fixes
- `docs/` - Documentation changes
- `refactor/` - Code refactoring
- `test/` - Adding or updating tests

Examples:
- `feature/add-mistral-provider`
- `fix/streaming-timeout-issue`
- `docs/update-readme-examples`

### Commit Messages

Write clear, concise commit messages:

```
<type>: <short summary>

<optional body with more details>
```

Types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`

Examples:
```
feat: add support for Mistral AI provider
fix: resolve token counting in streaming responses
docs: add examples for tool calling
refactor: extract message formatting logic
test: add coverage for edge cases in schema validation
```

### Contribution Steps

1. **Fork and clone**
   ```bash
   git clone https://github.com/YOUR_USERNAME/php-ai-sdk.git
   cd php-ai-sdk
   git remote add upstream https://github.com/sage-grids/php-ai-sdk.git
   ```

2. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **Make changes**
   - Write code following the standards above
   - Add tests for new functionality
   - Keep commits atomic and focused

4. **Sync with upstream**
   ```bash
   git fetch upstream
   git rebase upstream/main
   ```

5. **Run quality checks**
   ```bash
   ./vendor/bin/php-cs-fixer fix
   ./vendor/bin/phpstan analyse
   ./vendor/bin/phpunit
   ```

6. **Push and create PR**
   ```bash
   git push origin feature/your-feature-name
   ```
   Then open a Pull Request on GitHub.

## Pull Request Guidelines

- Provide a clear title and description
- Reference any related issues
- Ensure all CI checks pass
- Keep PRs focused on a single concern
- Be responsive to review feedback

### PR Template

```markdown
## Summary
Brief description of changes.

## Changes
- Change 1
- Change 2

## Testing
Describe how you tested the changes.

## Checklist
- [ ] Tests added/updated
- [ ] Code style passes
- [ ] PHPStan passes
- [ ] Documentation updated (if applicable)
```

## Architecture Notes

When contributing, keep these patterns in mind:

- **Providers** implement `ProviderInterface` and related interfaces
- **Results** are immutable objects with readonly properties
- **Tools** use the `Tool` class and `ToolRegistry`
- **Schemas** use the builder pattern via `Schema` class
- **HTTP** goes through middleware stack for cross-cutting concerns

## Questions?

Open an issue for questions or discussions about potential changes before starting work on large features.
