<?php

declare(strict_types=1);

namespace SchemaLens\Comparison;

use SchemaLens\DTO\ForeignKeySchema;
use SchemaLens\DTO\SchemaDifference;
use SchemaLens\Enums\DifferenceSeverity;
use SchemaLens\Enums\DifferenceType;
use SchemaLens\Support\Normalization\SchemaNormalizer;

final class ForeignKeyComparator
{
    /**
     * @return list<SchemaDifference>
     */
    public function compare(string $table, ForeignKeySchema $from, ForeignKeySchema $to): array
    {
        $changed = [];

        if ($from->columns !== $to->columns) {
            $changed[] = 'columns';
        }

        if (strcasecmp($from->referencedTable, $to->referencedTable) !== 0) {
            $changed[] = 'referenced_table';
        }

        if ($from->referencedColumns !== $to->referencedColumns) {
            $changed[] = 'referenced_columns';
        }

        if (SchemaNormalizer::normalizeAction($from->onDelete) !== SchemaNormalizer::normalizeAction($to->onDelete)) {
            $changed[] = 'on_delete';
        }

        if (SchemaNormalizer::normalizeAction($from->onUpdate) !== SchemaNormalizer::normalizeAction($to->onUpdate)) {
            $changed[] = 'on_update';
        }

        if ($changed === []) {
            return [];
        }

        return [
            new SchemaDifference(
                type: DifferenceType::ForeignKeyModified,
                severity: DifferenceSeverity::Warning,
                table: $table,
                object: $from->name,
                from: $from->toArray(),
                to: $to->toArray(),
                message: sprintf(
                    'Foreign key `%s` on `%s` differs (%s).',
                    $from->name,
                    $table,
                    implode(', ', $changed)
                ),
                changedAttributes: $changed,
            ),
        ];
    }
}
