# SchemaLens

> See exactly what changed in your database.

**Git diff for Laravel database schemas.**

SchemaLens compares any two Laravel database connections and shows exactly what differs — tables, columns, indexes, foreign keys, and table options — then generates reviewable SQL or a Laravel migration. It is **read-only by default** and never modifies your databases automatically.

---

## Features

- Modern web dashboard at `/schema-lens`
- Compare any two configured Laravel connections
- Table / column / index / foreign key diffs
- Git-style before/after UI
- SQL preview with destructive operation warnings
- Laravel migration generation (file only — not executed)
- Artisan CLI with JSON / HTML / SQL export
- MySQL & MariaDB support (PostgreSQL/SQLite-ready architecture)
- Configurable ignore lists, caching, middleware, and route prefix
- Credentials never exposed in UI, JSON, HTML, SQL, or logs

---

## Installation

```bash
composer require cavid/schema-lens
```

Laravel package auto-discovery registers the service provider. **No** `config/app.php` or `routes/web.php` edits required.

Open:

```
http://localhost/schema-lens
```

Optional config publish:

```bash
php artisan vendor:publish --tag=schemalens-config
```

---

## Configuration

`config/schemalens.php`:

```php
return [
    'enabled' => env('SCHEMA_LENS_ENABLED', true),
    'route_prefix' => env('SCHEMA_LENS_ROUTE_PREFIX', 'schema-lens'),
    'middleware' => ['web', 'auth'],
    'default_from' => env('SCHEMA_LENS_DEFAULT_FROM', 'mysql'),
    'default_to' => env('SCHEMA_LENS_DEFAULT_TO', null),
    'ignore_tables' => [
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'failed_jobs',
    ],
    'ignore_columns' => [],
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
    ],
    'show_host' => env('SCHEMA_LENS_SHOW_HOST', true),
];
```

By default the dashboard requires authentication (`web` + `auth`). Customize middleware for your app (e.g. add `can:view-schema-lens`).

---

## Dashboard

1. Open `/schema-lens`
2. Select **FROM** (desired schema) and **TO** (target)
3. Click **Compare Databases**
4. Review summary cards and per-table diffs
5. Open **SQL Preview** to copy or download SQL
6. Optionally generate a Laravel migration

SchemaLens never runs a “Sync Database” action.

---

## Artisan commands

### Compare

```bash
php artisan schema:lens
php artisan schema:lens --from=mysql --to=production
php artisan schema:lens --from=mysql --to=production --ignore=logs,telemetry
php artisan schema:lens --from=mysql --to=production --json
php artisan schema:lens --from=mysql --to=production --html
php artisan schema:lens --from=mysql --to=production --sql
php artisan schema:lens --from=mysql --to=production --no-cache
```

Exit code `0` = in sync, `1` = differences found (useful for future CI).

### Generate migration

```bash
php artisan schema:lens:migration --from=mysql --to=production
```

Creates a file under `database/migrations/` with a review warning. **Does not execute** the migration.

---

## Examples

**Local has tables production is missing:**

```
FROM: mysql (152 tables)
TO:   production (149 tables)

Missing on production:
  special_permission_items
```

**Column drift:**

```
special_permissions.status

LOCAL                    PRODUCTION
VARCHAR(255)             VARCHAR(100)
NULL                     NOT NULL
```

**Generated SQL (never auto-executed):**

```sql
ALTER TABLE `special_permissions`
MODIFY COLUMN `status` VARCHAR(255) NULL;
```

---

## SQL preview & safety

- SQL is deterministic and intended to make **TO** match **FROM**
- `DROP TABLE` / `DROP COLUMN` / `DROP INDEX` / `DROP FOREIGN KEY` are marked **DESTRUCTIVE**
- The UI and CLI warn before you copy or download such SQL
- SchemaLens never executes generated SQL

---

## Supported databases

| Database   | Status                          |
|------------|---------------------------------|
| MySQL      | Supported (v1)                  |
| MariaDB    | Supported (v1)                  |
| PostgreSQL | Architecture ready (future)     |
| SQLite     | Architecture ready (future)     |

Inspectors implement `SchemaInspectorInterface` so new drivers can be added cleanly.

---

## Security

- Passwords, DSNs with credentials, and secrets are never rendered
- Connection display is limited to name, driver, database, and optional host
- Exception messages are sanitized
- Dashboard middleware defaults to authenticated access only
- Exports are credential-scrubbed

---

## Performance

- Bulk `INFORMATION_SCHEMA` queries (no N+1 table loops)
- In-memory schema DTOs for the comparison pass
- Optional Laravel cache for inspected schemas (`schemalens.cache`)
- Use `--no-cache` to force a fresh inspect
- Large schemas: group by table, search, and filter in the UI

---

## Project impact

After install, the host app typically only gains:

- `composer.json` / `composer.lock` changes
- optional published `config/schemalens.php`

All package code, views, and assets live under `vendor/cavid/schema-lens/`. SchemaLens does **not** create its own database tables.

---

## Troubleshooting

| Problem | Likely cause |
|---------|----------------|
| 404 on `/schema-lens` | `SCHEMA_LENS_ENABLED=false` or route cache; run `php artisan route:clear` |
| Redirect to login | Default `auth` middleware — log in or adjust `schemalens.middleware` |
| Unsupported driver | v1 supports MySQL/MariaDB only |
| Access denied | DB user needs `INFORMATION_SCHEMA` read access |
| Empty comparison | Check `ignore_tables` — Laravel system tables are ignored by default |

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

MySQL integration tests (optional):

```bash
SCHEMA_LENS_TEST_MYSQL=true \
SCHEMA_LENS_MYSQL_DB_A=schemalens_a \
SCHEMA_LENS_MYSQL_DB_B=schemalens_b \
vendor/bin/phpunit
```

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

---

## License

MIT — see [LICENSE](LICENSE).

---

**SchemaLens** — *See exactly what changed in your database.*
