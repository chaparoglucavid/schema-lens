<?php

declare(strict_types=1);

namespace SchemaLens\Database;

use Illuminate\Support\Facades\Config;
use SchemaLens\Contracts\SchemaInspectorInterface;
use SchemaLens\Database\Inspectors\MariaDbSchemaInspector;
use SchemaLens\Database\Inspectors\MySqlSchemaInspector;
use SchemaLens\DTO\ConnectionInfo;
use SchemaLens\Exceptions\ConnectionInspectionException;
use SchemaLens\Exceptions\UnsupportedDriverException;

final class SchemaInspectorFactory
{
    /** @var list<SchemaInspectorInterface> */
    private array $inspectors;

    /**
     * @param  list<SchemaInspectorInterface>|null  $inspectors
     */
    public function __construct(?array $inspectors = null)
    {
        $this->inspectors = $inspectors ?? [
            new MariaDbSchemaInspector,
            new MySqlSchemaInspector,
        ];
    }

    public function make(string $connection): SchemaInspectorInterface
    {
        if (! array_key_exists($connection, Config::get('database.connections', []))) {
            throw ConnectionInspectionException::invalidConnection($connection);
        }

        $driver = (string) Config::get("database.connections.{$connection}.driver", '');

        foreach ($this->inspectors as $inspector) {
            if ($inspector->supports($driver)) {
                return $inspector;
            }
        }

        // MariaDB often reports as mysql driver in Laravel config
        if ($driver === 'mysql') {
            return new MySqlSchemaInspector;
        }

        throw UnsupportedDriverException::forDriver($driver !== '' ? $driver : 'unknown');
    }

    /**
     * Safe connection list for UI/CLI — never includes passwords.
     *
     * @return list<ConnectionInfo>
     */
    public function listConnections(bool $showHost = true): array
    {
        $connections = Config::get('database.connections', []);
        $result = [];

        foreach ($connections as $name => $config) {
            if (! is_array($config)) {
                continue;
            }

            $result[] = new ConnectionInfo(
                name: (string) $name,
                driver: (string) ($config['driver'] ?? 'unknown'),
                database: (string) ($config['database'] ?? ''),
                host: $showHost && isset($config['host']) ? (string) $config['host'] : null,
                port: isset($config['port']) ? (int) $config['port'] : null,
            );
        }

        return $result;
    }

    /**
     * Resolve driver for a connection without opening it.
     */
    public function driverFor(string $connection): string
    {
        return (string) Config::get("database.connections.{$connection}.driver", '');
    }
}
