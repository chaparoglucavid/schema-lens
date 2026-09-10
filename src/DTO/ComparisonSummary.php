<?php

declare(strict_types=1);

namespace SchemaLens\DTO;

final readonly class ComparisonSummary
{
    public function __construct(
        public int $fromTableCount,
        public int $toTableCount,
        public int $missingTables,
        public int $extraTables,
        public int $modifiedTables,
        public int $columnChanges,
        public int $indexChanges,
        public int $foreignKeyChanges,
        public int $totalDifferences,
        public float $durationMs = 0.0,
    ) {}

    public function hasDifferences(): bool
    {
        return $this->totalDifferences > 0;
    }

    public function toArray(): array
    {
        return [
            'from_table_count' => $this->fromTableCount,
            'to_table_count' => $this->toTableCount,
            'missing_tables' => $this->missingTables,
            'extra_tables' => $this->extraTables,
            'modified_tables' => $this->modifiedTables,
            'column_changes' => $this->columnChanges,
            'index_changes' => $this->indexChanges,
            'foreign_key_changes' => $this->foreignKeyChanges,
            'total_differences' => $this->totalDifferences,
            'duration_ms' => $this->durationMs,
        ];
    }
}
