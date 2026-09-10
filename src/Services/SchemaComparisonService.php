<?php

declare(strict_types=1);

namespace SchemaLens\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use SchemaLens\Comparison\SchemaComparator;
use SchemaLens\Contracts\SchemaComparatorInterface;
use SchemaLens\Database\SchemaInspectorFactory;
use SchemaLens\DTO\ComparisonResult;
use SchemaLens\DTO\ConnectionInfo;
use SchemaLens\DTO\DatabaseSchema;
use SchemaLens\Exceptions\ConnectionInspectionException;

/**
 * High-level orchestration for inspecting and comparing two connections.
 * Read-only: never modifies either database.
 */
final class SchemaComparisonService
{
    public function __construct(
        private readonly SchemaInspectorFactory $inspectorFactory = new SchemaInspectorFactory,
        private readonly SchemaComparatorInterface $comparator = new SchemaComparator,
    ) {}

    /**
     * @param  list<string>|null  $ignoreTables
     * @param  list<string>|null  $ignoreColumns
     */
    public function compare(
        string $fromConnection,
        string $toConnection,
        ?array $ignoreTables = null,
        ?array $ignoreColumns = null,
        bool $useCache = true,
    ): ComparisonResult {
        $ignoreTables ??= Config::get('schemalens.ignore_tables', []);
        $ignoreColumns ??= Config::get('schemalens.ignore_columns', []);

        $fromSchema = $this->inspect($fromConnection, $ignoreTables, $useCache);
        $toSchema = $this->inspect($toConnection, $ignoreTables, $useCache);

        return $this->comparator->compare($fromSchema, $toSchema, $ignoreColumns);
    }

    /**
     * @param  list<string>  $ignoreTables
     */
    public function inspect(string $connection, array $ignoreTables = [], bool $useCache = true): DatabaseSchema
    {
        $cacheEnabled = $useCache && (bool) Config::get('schemalens.cache.enabled', true);

        if (! $cacheEnabled) {
            return $this->doInspect($connection, $ignoreTables);
        }

        $ttl = (int) Config::get('schemalens.cache.ttl', 300);
        $store = Config::get('schemalens.cache.store');
        $key = $this->cacheKey($connection, $ignoreTables);

        /** @var CacheRepository $cache */
        $cache = $store ? Cache::store($store) : Cache::store();

        /** @var DatabaseSchema $schema */
        $schema = $cache->remember($key, $ttl, function () use ($connection, $ignoreTables) {
            return $this->doInspect($connection, $ignoreTables);
        });

        return $schema;
    }

    /**
     * @return list<ConnectionInfo>
     */
    public function connections(): array
    {
        $showHost = (bool) Config::get('schemalens.show_host', true);

        return $this->inspectorFactory->listConnections($showHost);
    }

    /**
     * @param  list<string>  $ignoreTables
     */
    private function doInspect(string $connection, array $ignoreTables): DatabaseSchema
    {
        if (! array_key_exists($connection, Config::get('database.connections', []))) {
            throw ConnectionInspectionException::invalidConnection($connection);
        }

        $inspector = $this->inspectorFactory->make($connection);

        return $inspector->inspect($connection, $ignoreTables);
    }

    /**
     * @param  list<string>  $ignoreTables
     */
    private function cacheKey(string $connection, array $ignoreTables): string
    {
        sort($ignoreTables);

        return 'schemalens:schema:'.sha1($connection.'|'.implode(',', $ignoreTables));
    }
}
