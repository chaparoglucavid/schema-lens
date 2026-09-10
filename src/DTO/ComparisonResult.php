<?php

declare(strict_types=1);

namespace SchemaLens\DTO;

use SchemaLens\Enums\DifferenceType;
use SchemaLens\Enums\TableStatus;

final class ComparisonResult
{
    /**
     * @param  list<SchemaDifference>  $differences
     * @param  array<string, TableStatus>  $tableStatuses
     */
    public function __construct(
        public readonly DatabaseSchema $from,
        public readonly DatabaseSchema $to,
        public readonly array $differences = [],
        public readonly array $tableStatuses = [],
        public readonly float $durationMs = 0.0,
    ) {}

    public function hasDifferences(): bool
    {
        return $this->differences !== [];
    }

    /**
     * @return list<SchemaDifference>
     */
    public function differencesOfType(DifferenceType ...$types): array
    {
        $values = array_map(fn (DifferenceType $t) => $t->value, $types);

        return array_values(array_filter(
            $this->differences,
            fn (SchemaDifference $d) => in_array($d->type->value, $values, true)
        ));
    }

    /**
     * @return list<SchemaDifference>
     */
    public function differencesForCategory(string $category): array
    {
        return array_values(array_filter(
            $this->differences,
            fn (SchemaDifference $d) => $d->type->category() === $category
        ));
    }

    /**
     * @return list<SchemaDifference>
     */
    public function differencesForTable(string $table): array
    {
        return array_values(array_filter(
            $this->differences,
            fn (SchemaDifference $d) => $d->table === $table
        ));
    }

    public function summary(): ComparisonSummary
    {
        $missingTables = count($this->differencesOfType(DifferenceType::TableRemoved));
        $extraTables = count($this->differencesOfType(DifferenceType::TableAdded));
        $modifiedTables = count(array_filter(
            $this->tableStatuses,
            fn (TableStatus $s) => $s === TableStatus::Modified
        ));

        return new ComparisonSummary(
            fromTableCount: $this->from->tableCount(),
            toTableCount: $this->to->tableCount(),
            missingTables: $missingTables,
            extraTables: $extraTables,
            modifiedTables: $modifiedTables,
            columnChanges: count($this->differencesForCategory('columns')),
            indexChanges: count($this->differencesForCategory('indexes')),
            foreignKeyChanges: count($this->differencesForCategory('foreign_keys')),
            totalDifferences: count($this->differences),
            durationMs: $this->durationMs,
        );
    }

    /**
     * @return array<string, list<SchemaDifference>>
     */
    public function groupedByTable(): array
    {
        $grouped = [];

        foreach ($this->differences as $difference) {
            $grouped[$difference->table][] = $difference;
        }

        ksort($grouped);

        return $grouped;
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->safeConnectionInfo(),
            'to' => $this->to->safeConnectionInfo(),
            'summary' => $this->summary()->toArray(),
            'table_statuses' => array_map(
                fn (TableStatus $s) => $s->value,
                $this->tableStatuses
            ),
            'differences' => array_map(
                fn (SchemaDifference $d) => $d->toArray(),
                $this->differences
            ),
            'duration_ms' => $this->durationMs,
        ];
    }
}
