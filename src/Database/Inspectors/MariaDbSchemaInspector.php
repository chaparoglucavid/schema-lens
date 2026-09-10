<?php

declare(strict_types=1);

namespace SchemaLens\Database\Inspectors;

/**
 * MariaDB uses the same INFORMATION_SCHEMA layout as MySQL for SchemaLens v1.
 * Kept as a distinct class so future MariaDB-specific quirks can be handled cleanly.
 */
class MariaDbSchemaInspector extends MySqlSchemaInspector
{
    public function supports(string $driver): bool
    {
        return strtolower($driver) === 'mariadb';
    }
}
