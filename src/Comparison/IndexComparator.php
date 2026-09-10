<?php

declare(strict_types=1);

namespace SchemaLens\Comparison;

use SchemaLens\DTO\IndexSchema;
use SchemaLens\DTO\SchemaDifference;
use SchemaLens\Enums\DifferenceSeverity;
use SchemaLens\Enums\DifferenceType;

final class IndexComparator
{
    /**
     * @return list<SchemaDifference>
     */
    public function compare(string $table, IndexSchema $from, IndexSchema $to): array
    {
        $changed = [];

        if ($from->type !== $to->type) {
            $changed[] = 'type';
        }

        if ($from->unique !== $to->unique) {
            $changed[] = 'unique';
        }

        if ($from->columns !== $to->columns) {
            $changed[] = 'columns';
        }

        if ($changed === []) {
            return [];
        }

        return [
            new SchemaDifference(
                type: DifferenceType::IndexModified,
                severity: DifferenceSeverity::Warning,
                table: $table,
                object: $from->name,
                from: $from->toArray(),
                to: $to->toArray(),
                message: sprintf(
                    'Index `%s` on `%s` differs (%s).',
                    $from->name,
                    $table,
                    implode(', ', $changed)
                ),
                changedAttributes: $changed,
            ),
        ];
    }
}
