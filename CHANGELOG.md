# Changelog

All notable changes to SchemaLens will be documented in this file.

## [1.0.0] - 2026-09-10

### Added

- Initial release of `cavid/schema-lens`
- Web dashboard at `/schema-lens` (Blade + Tailwind CDN + Alpine.js)
- Schema comparison for MySQL and MariaDB
- Table, column, index, foreign key, and table option diffs
- SQL preview with destructive operation warnings
- JSON / HTML / SQL exporters
- `php artisan schema:lens` compare command
- `php artisan schema:lens:migration` migration generator
- Configurable middleware, route prefix, ignore lists, and cache
- Credential sanitization across UI, reports, and exceptions
- Unit, feature, and optional MySQL integration tests
