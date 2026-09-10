<?php

declare(strict_types=1);

namespace SchemaLens\DTO;

final readonly class DatabaseSchema
{
    /**
     * @param  array<string, TableSchema>  $tables
     */
    public function __construct(
        public string $connection,
        public string $driver,
        public string $database,
        public array $tables = [],
        public ?string $host = null,
        public ?string $version = null,
    ) {}

    public function tableCount(): int
    {
        return count($this->tables);
    }

    public function getTable(string $name): ?TableSchema
    {
        return $this->tables[$name] ?? null;
    }

    public function tableNames(): array
    {
        return array_keys($this->tables);
    }

    /**
     * Safe connection metadata for display — never includes credentials.
     */
    public function safeConnectionInfo(bool $showHost = true): array
    {
        $info = [
            'connection' => $this->connection,
            'driver' => $this->driver,
            'database' => $this->database,
            'table_count' => $this->tableCount(),
            'version' => $this->version,
        ];

        if ($showHost && $this->host !== null) {
            $info['host'] = $this->host;
        }

        return $info;
    }

    public function toArray(bool $includeTables = true): array
    {
        $data = $this->safeConnectionInfo();

        if ($includeTables) {
            $data['tables'] = array_map(
                fn (TableSchema $t) => $t->toArray(),
                $this->tables
            );
        }

        return $data;
    }
}
