<?php

declare(strict_types=1);

namespace SchemaLens\Database\Inspectors;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use SchemaLens\Contracts\SchemaInspectorInterface;
use SchemaLens\DTO\ColumnSchema;
use SchemaLens\DTO\DatabaseSchema;
use SchemaLens\DTO\ForeignKeySchema;
use SchemaLens\DTO\IndexSchema;
use SchemaLens\DTO\TableSchema;
use SchemaLens\Enums\IndexType;
use SchemaLens\Exceptions\ConnectionInspectionException;
use SchemaLens\Support\Normalization\SchemaNormalizer;
use Throwable;

/**
 * Efficient MySQL schema inspector using bulk INFORMATION_SCHEMA queries.
 * Avoids N+1 by loading all tables, columns, indexes and FKs in few queries.
 */
class MySqlSchemaInspector implements SchemaInspectorInterface
{
    public function supports(string $driver): bool
    {
        return in_array(strtolower($driver), ['mysql', 'mariadb'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function inspect(string $connection, array $ignoreTables = []): DatabaseSchema
    {
        try {
            $db = DB::connection($connection);
        } catch (Throwable $e) {
            throw ConnectionInspectionException::unableToInspect(
                $connection,
                $this->humanReason($e)
            );
        }

        try {
            $database = $db->getDatabaseName();
            $config = $db->getConfig();
            $driver = (string) ($config['driver'] ?? 'mysql');
            $host = isset($config['host']) ? (string) $config['host'] : null;
            $version = $this->safeVersion($db);

            $ignoreLookup = array_fill_keys(
                array_map('strtolower', $ignoreTables),
                true
            );

            $tableRows = $this->fetchTables($db, $database);
            $tableNames = [];

            foreach ($tableRows as $row) {
                $name = (string) $row->TABLE_NAME;
                if (isset($ignoreLookup[strtolower($name)])) {
                    continue;
                }
                $tableNames[] = $name;
            }

            if ($tableNames === []) {
                return new DatabaseSchema(
                    connection: $connection,
                    driver: $driver,
                    database: (string) $database,
                    tables: [],
                    host: $host,
                    version: $version,
                );
            }

            $columnsByTable = $this->fetchColumns($db, $database, $tableNames);
            $indexesByTable = $this->fetchIndexes($db, $database, $tableNames);
            $foreignKeysByTable = $this->fetchForeignKeys($db, $database, $tableNames);

            $tables = [];

            foreach ($tableRows as $row) {
                $name = (string) $row->TABLE_NAME;

                if (isset($ignoreLookup[strtolower($name)])) {
                    continue;
                }

                $tables[$name] = new TableSchema(
                    name: $name,
                    columns: $columnsByTable[$name] ?? [],
                    indexes: $indexesByTable[$name] ?? [],
                    foreignKeys: $foreignKeysByTable[$name] ?? [],
                    engine: $row->ENGINE !== null ? (string) $row->ENGINE : null,
                    charset: SchemaNormalizer::normalizeCharset(
                        $row->TABLE_COLLATION !== null
                            ? $this->charsetFromCollation((string) $row->TABLE_COLLATION)
                            : null
                    ),
                    collation: SchemaNormalizer::normalizeCharset(
                        $row->TABLE_COLLATION !== null ? (string) $row->TABLE_COLLATION : null
                    ),
                    comment: $row->TABLE_COMMENT !== null && $row->TABLE_COMMENT !== ''
                        ? (string) $row->TABLE_COMMENT
                        : null,
                );
            }

            ksort($tables);

            return new DatabaseSchema(
                connection: $connection,
                driver: $driver,
                database: (string) $database,
                tables: $tables,
                host: $host,
                version: $version,
            );
        } catch (ConnectionInspectionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ConnectionInspectionException::unableToInspect(
                $connection,
                $this->humanReason($e)
            );
        }
    }

    /**
     * @return list<object>
     */
    protected function fetchTables(Connection $db, string $database): array
    {
        return $db->select(
            'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_COMMENT
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ?
               AND TABLE_TYPE = \'BASE TABLE\'
             ORDER BY TABLE_NAME',
            [$database]
        );
    }

    /**
     * @param  list<string>  $tableNames
     * @return array<string, array<string, ColumnSchema>>
     */
    protected function fetchColumns(Connection $db, string $database, array $tableNames): array
    {
        $placeholders = implode(',', array_fill(0, count($tableNames), '?'));

        $rows = $db->select(
            "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH,
                    NUMERIC_PRECISION, NUMERIC_SCALE, IS_NULLABLE, COLUMN_DEFAULT,
                    EXTRA, COLUMN_COMMENT, ORDINAL_POSITION, CHARACTER_SET_NAME, COLLATION_NAME,
                    GENERATION_EXPRESSION
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME IN ({$placeholders})
             ORDER BY TABLE_NAME, ORDINAL_POSITION",
            array_merge([$database], $tableNames)
        );

        $result = [];

        foreach ($rows as $row) {
            $table = (string) $row->TABLE_NAME;
            $name = (string) $row->COLUMN_NAME;
            $nativeType = SchemaNormalizer::normalizeType((string) $row->DATA_TYPE);
            $columnType = strtolower((string) $row->COLUMN_TYPE);
            $extra = strtolower((string) ($row->EXTRA ?? ''));

            $length = $row->CHARACTER_MAXIMUM_LENGTH !== null
                ? (int) $row->CHARACTER_MAXIMUM_LENGTH
                : $this->extractDisplayWidth($columnType);

            $generated = str_contains($extra, 'generated')
                || (isset($row->GENERATION_EXPRESSION) && $row->GENERATION_EXPRESSION !== null && $row->GENERATION_EXPRESSION !== '');

            $result[$table][$name] = new ColumnSchema(
                name: $name,
                type: $nativeType,
                nativeType: $nativeType,
                length: $length,
                precision: $row->NUMERIC_PRECISION !== null ? (int) $row->NUMERIC_PRECISION : null,
                scale: $row->NUMERIC_SCALE !== null ? (int) $row->NUMERIC_SCALE : null,
                nullable: strtoupper((string) $row->IS_NULLABLE) === 'YES',
                default: SchemaNormalizer::normalizeDefault($row->COLUMN_DEFAULT, $nativeType),
                autoIncrement: str_contains($extra, 'auto_increment'),
                unsigned: str_contains($columnType, 'unsigned'),
                comment: $row->COLUMN_COMMENT !== null && $row->COLUMN_COMMENT !== ''
                    ? (string) $row->COLUMN_COMMENT
                    : null,
                ordinalPosition: (int) $row->ORDINAL_POSITION,
                charset: SchemaNormalizer::normalizeCharset(
                    $row->CHARACTER_SET_NAME !== null ? (string) $row->CHARACTER_SET_NAME : null
                ),
                collation: SchemaNormalizer::normalizeCharset(
                    $row->COLLATION_NAME !== null ? (string) $row->COLLATION_NAME : null
                ),
                generated: $generated,
                generationExpression: $generated && isset($row->GENERATION_EXPRESSION)
                    ? (string) $row->GENERATION_EXPRESSION
                    : null,
            );
        }

        return $result;
    }

    /**
     * @param  list<string>  $tableNames
     * @return array<string, array<string, IndexSchema>>
     */
    protected function fetchIndexes(Connection $db, string $database, array $tableNames): array
    {
        $placeholders = implode(',', array_fill(0, count($tableNames), '?'));

        $rows = $db->select(
            "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, INDEX_TYPE
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME IN ({$placeholders})
             ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
            array_merge([$database], $tableNames)
        );

        /** @var array<string, array<string, array{unique: bool, type: string, primary: bool, columns: array<int, string>}>> $raw */
        $raw = [];

        foreach ($rows as $row) {
            $table = (string) $row->TABLE_NAME;
            $indexName = (string) $row->INDEX_NAME;
            $seq = (int) $row->SEQ_IN_INDEX;

            if (! isset($raw[$table][$indexName])) {
                $isPrimary = strtoupper($indexName) === 'PRIMARY';
                $unique = ((int) $row->NON_UNIQUE) === 0;

                $raw[$table][$indexName] = [
                    'unique' => $unique,
                    'type' => (string) $row->INDEX_TYPE,
                    'primary' => $isPrimary,
                    'columns' => [],
                ];
            }

            $raw[$table][$indexName]['columns'][$seq] = (string) $row->COLUMN_NAME;
        }

        $result = [];

        foreach ($raw as $table => $indexes) {
            foreach ($indexes as $indexName => $meta) {
                ksort($meta['columns']);
                $columns = array_values($meta['columns']);

                $result[$table][$indexName] = new IndexSchema(
                    name: $indexName,
                    type: IndexType::fromMysql($meta['type'], $meta['unique'], $meta['primary']),
                    unique: $meta['unique'],
                    columns: $columns,
                );
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $tableNames
     * @return array<string, array<string, ForeignKeySchema>>
     */
    protected function fetchForeignKeys(Connection $db, string $database, array $tableNames): array
    {
        $placeholders = implode(',', array_fill(0, count($tableNames), '?'));

        $rows = $db->select(
            "SELECT
                kcu.TABLE_NAME,
                kcu.CONSTRAINT_NAME,
                kcu.COLUMN_NAME,
                kcu.ORDINAL_POSITION,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME,
                rc.DELETE_RULE,
                rc.UPDATE_RULE
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
             INNER JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
               AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
               AND kcu.TABLE_NAME = rc.TABLE_NAME
             WHERE kcu.TABLE_SCHEMA = ?
               AND kcu.TABLE_NAME IN ({$placeholders})
               AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION",
            array_merge([$database], $tableNames)
        );

        /** @var array<string, array<string, array{columns: array<int, string>, ref_table: string, ref_columns: array<int, string>, on_delete: string, on_update: string}>> $raw */
        $raw = [];

        foreach ($rows as $row) {
            $table = (string) $row->TABLE_NAME;
            $name = (string) $row->CONSTRAINT_NAME;
            $pos = (int) $row->ORDINAL_POSITION;

            if (! isset($raw[$table][$name])) {
                $raw[$table][$name] = [
                    'columns' => [],
                    'ref_table' => (string) $row->REFERENCED_TABLE_NAME,
                    'ref_columns' => [],
                    'on_delete' => SchemaNormalizer::normalizeAction((string) $row->DELETE_RULE),
                    'on_update' => SchemaNormalizer::normalizeAction((string) $row->UPDATE_RULE),
                ];
            }

            $raw[$table][$name]['columns'][$pos] = (string) $row->COLUMN_NAME;
            $raw[$table][$name]['ref_columns'][$pos] = (string) $row->REFERENCED_COLUMN_NAME;
        }

        $result = [];

        foreach ($raw as $table => $fks) {
            foreach ($fks as $name => $meta) {
                ksort($meta['columns']);
                ksort($meta['ref_columns']);

                $result[$table][$name] = new ForeignKeySchema(
                    name: $name,
                    columns: array_values($meta['columns']),
                    referencedTable: $meta['ref_table'],
                    referencedColumns: array_values($meta['ref_columns']),
                    onDelete: $meta['on_delete'],
                    onUpdate: $meta['on_update'],
                );
            }
        }

        return $result;
    }

    protected function charsetFromCollation(string $collation): ?string
    {
        $parts = explode('_', $collation, 2);

        return $parts[0] !== '' ? $parts[0] : null;
    }

    protected function extractDisplayWidth(string $columnType): ?int
    {
        if (preg_match('/^(?:tiny|small|medium|big)?int(?:eger)?\((\d+)\)/i', $columnType, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^(?:var)?(?:char|binary)\((\d+)\)/i', $columnType, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^bit\((\d+)\)/i', $columnType, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    protected function safeVersion(Connection $db): ?string
    {
        try {
            $row = $db->selectOne('SELECT VERSION() AS version');

            return $row->version ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function humanReason(Throwable $e): string
    {
        $message = $e->getMessage();

        // Never leak connection strings with passwords
        $message = preg_replace('/:\/\/[^:]+:[^@]+@/', '://***:***@', $message) ?? $message;
        $message = preg_replace('/password[=:]\s*\S+/i', 'password=***', $message) ?? $message;

        if (stripos($message, 'Access denied') !== false) {
            return 'Access denied.';
        }

        if (stripos($message, 'Connection refused') !== false
            || stripos($message, 'actively refused') !== false
        ) {
            return 'Connection refused.';
        }

        if (stripos($message, 'Unknown database') !== false) {
            return 'Unknown database.';
        }

        if (stripos($message, 'could not find driver') !== false) {
            return 'PDO driver not available.';
        }

        if (stripos($message, 'timed out') !== false || stripos($message, 'timeout') !== false) {
            return 'Connection timed out.';
        }

        // Truncate long SQLSTATE dumps
        if (strlen($message) > 300) {
            $message = substr($message, 0, 300).'…';
        }

        return $message;
    }
}
