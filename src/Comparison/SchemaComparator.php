<?php

declare(strict_types=1);

namespace SchemaLens\Comparison;

use SchemaLens\Contracts\SchemaComparatorInterface;
use SchemaLens\DTO\ComparisonResult;
use SchemaLens\DTO\DatabaseSchema;
use SchemaLens\DTO\SchemaDifference;
use SchemaLens\Enums\DifferenceSeverity;
use SchemaLens\Enums\DifferenceType;
use SchemaLens\Enums\TableStatus;

final class SchemaComparator implements SchemaComparatorInterface
{
    public function __construct(
        private readonly TableComparator $tableComparator = new TableComparator,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function compare(
        DatabaseSchema $from,
        DatabaseSchema $to,
        array $ignoreColumns = []
    ): ComparisonResult {
        $start = hrtime(true);
        $differences = [];
        $tableStatuses = [];

        $fromTables = $from->tableNames();
        $toTables = $to->tableNames();

        $onlyFrom = array_diff($fromTables, $toTables);
        $onlyTo = array_diff($toTables, $fromTables);
        $common = array_intersect($fromTables, $toTables);

        // Tables missing on target (present in FROM / source of truth)
        foreach ($onlyFrom as $tableName) {
            $table = $from->getTable($tableName);
            $differences[] = new SchemaDifference(
                type: DifferenceType::TableRemoved,
                severity: DifferenceSeverity::Critical,
                table: $tableName,
                object: $tableName,
                from: $table?->toArray(),
                to: null,
                message: sprintf('Table `%s` is missing on %s.', $tableName, $to->connection),
            );
            $tableStatuses[$tableName] = TableStatus::Removed;
        }

        // Tables only on target (extra relative to source)
        foreach ($onlyTo as $tableName) {
            $table = $to->getTable($tableName);
            $differences[] = new SchemaDifference(
                type: DifferenceType::TableAdded,
                severity: DifferenceSeverity::Warning,
                table: $tableName,
                object: $tableName,
                from: null,
                to: $table?->toArray(),
                message: sprintf('Table `%s` exists only on %s.', $tableName, $to->connection),
                destructive: true,
            );
            $tableStatuses[$tableName] = TableStatus::Added;
        }

        foreach ($common as $tableName) {
            $fromTable = $from->getTable($tableName);
            $toTable = $to->getTable($tableName);

            if ($fromTable === null || $toTable === null) {
                continue;
            }

            $tableDiffs = $this->tableComparator->compare($fromTable, $toTable, $ignoreColumns);

            if ($tableDiffs === []) {
                $tableStatuses[$tableName] = TableStatus::Unchanged;
            } else {
                $tableStatuses[$tableName] = TableStatus::Modified;
                $differences = array_merge($differences, $tableDiffs);
            }
        }

        ksort($tableStatuses);

        $durationMs = (hrtime(true) - $start) / 1_000_000;

        return new ComparisonResult(
            from: $from,
            to: $to,
            differences: $differences,
            tableStatuses: $tableStatuses,
            durationMs: $durationMs,
        );
    }
}
