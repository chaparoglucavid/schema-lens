<?php

declare(strict_types=1);

namespace SchemaLens\Contracts;

use SchemaLens\DTO\DatabaseSchema;

interface SchemaInspectorInterface
{
    /**
     * Inspect the full schema for the given Laravel connection name.
     *
     * @param  list<string>  $ignoreTables
     */
    public function inspect(string $connection, array $ignoreTables = []): DatabaseSchema;

    /**
     * Whether this inspector supports the given driver name.
     */
    public function supports(string $driver): bool;
}
