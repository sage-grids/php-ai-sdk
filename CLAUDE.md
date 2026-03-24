# Claude Code Instructions

## Task Master AI Instructions
**Import Task Master's development workflow commands and guidelines, treat as if import is in the main CLAUDE.md file.**
@./.taskmaster/CLAUDE.md

## Project Overview

Unified PHP SDK for multiple AI providers (OpenAI, Google, OpenRouter), inspired by the Vercel AI SDK.
**Namespace:** `SageGrids\PhpAiSdk` | **PHP 8.1+** | PSR-4 autoloaded from `src/`

## Repo Structure

- `src/AI.php` — main facade; `src/Core/` — functions, messages, schema, tools
- `src/Provider/` — provider implementations (OpenAI, Google, OpenRouter) behind `ProviderInterface`
- `src/Result/` — response value objects; `src/Http/` — HTTP/SSE layer; `src/Exception/` — typed errors
- `src/Testing/` — `FakeProvider`, `FakeResponse`, `AITestCase` for unit tests without API calls
- `tests/` — `Unit/`, `Integration/`, `Feature/` suites mirroring `src/`

## Dev Commands

- `composer test` — PHPUnit | `composer cs-fix` — PHP-CS-Fixer | `composer phpstan` — static analysis

## Conventions

- All new code must pass `phpstan` and `cs-fix` before committing.
- Tests go in the matching `tests/` subdirectory mirroring `src/`.
- Use `FakeProvider` in tests — never make real API calls in unit tests.
