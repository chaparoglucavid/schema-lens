# Contributing to SchemaLens

Thanks for helping improve SchemaLens.

## Development setup

```bash
git clone https://github.com/cavid/schema-lens.git
cd schema-lens
composer install
vendor/bin/phpunit
```

## Guidelines

- Keep the package **read-only** by default — never auto-execute SQL against compared databases
- Prefer typed DTOs over raw arrays
- Add inspectors behind `SchemaInspectorInterface`
- Normalize metadata before comparing (see `SchemaNormalizer`)
- Never log or render credentials
- Follow PSR-12 and strict types
- Cover new comparison rules with unit tests

## Pull requests

1. Fork and create a feature branch
2. Add tests for behavioral changes
3. Update README / CHANGELOG when relevant
4. Open a PR with a clear description of *why*

## Code of conduct

Be respectful. This is a developer tooling project — clarity and safety matter more than cleverness.
