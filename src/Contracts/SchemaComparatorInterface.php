<?php

declare(strict_types=1);

namespace SchemaLens\Contracts;

use SchemaLens\DTO\ComparisonResult;
use SchemaLens\DTO\DatabaseSchema;

interface SchemaComparatorInterface
{
    /**
     * Compare two database schemas and return structured differences.
     *
     * @param  list<string>  $ignoreColumns
     */
    public function compare(
        DatabaseSchema $from,
        DatabaseSchema $to,
        array $ignoreColumns = []
    ): ComparisonResult;
}
