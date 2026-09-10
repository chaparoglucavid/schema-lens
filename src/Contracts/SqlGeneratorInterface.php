<?php

declare(strict_types=1);

namespace SchemaLens\Contracts;

use SchemaLens\DTO\ComparisonResult;
use SchemaLens\DTO\SqlStatement;

interface SqlGeneratorInterface
{
    /**
     * Generate SQL statements that would make the "to" database match the "from" database.
     *
     * @return list<SqlStatement>
     */
    public function generate(ComparisonResult $result): array;
}
