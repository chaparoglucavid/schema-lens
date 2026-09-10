<?php

declare(strict_types=1);

namespace SchemaLens\Comparison;

use SchemaLens\DTO\SchemaDifference;
use SchemaLens\DTO\TableSchema;
use SchemaLens\Enums\DifferenceSeverity;
use SchemaLens\Enums\DifferenceType;
use SchemaLens\Support\Normalization\SchemaNormalizer;

final class TableComparator
{
    public function __construct(
        private readonly ColumnComparator $columnComparator = new ColumnComparator,
        private readonly IndexComparator $indexComparator = new IndexComparator,
        private readonly ForeignKeyComparator $foreignKeyComparator = new ForeignKeyComparator,
    ) {}

    /**
     * Compare two tables that exist in both schemas.
     *
     * @param  list<string>  $ignoreColumns
     * @return list<SchemaDifference>
     */
    public function compare(TableSchema $from, TableSchema $to, array $ignoreColumns = []): array
    {
        $differences = [];
        $ignoreLookup = array_fill_keys(array_map('strtolower', $ignoreColumns), true);

        $differences = array_merge(
            $differences,
            $this->compareColumns($from, $to, $ignoreLookup),
            $this->compareIndexes($from, $to),
            $this->compareForeignKeys($from, $to),
            $this->compareOptions($from, $to),
        );

        return $differences;
    }

    /**
     * @param  array<string, true>  $ignoreLookup
     * @return list<SchemaDifference>
     */
    private function compareColumns(TableSchema $from, TableSchema $to, array $ignoreLookup): array
    {
        $differences = [];
        $fromCols = array_keys($from->columns);
        $toCols = array_keys($to->columns);

        $onlyFrom = array_diff($fromCols, $toCols);
        $onlyTo = array_diff($toCols, $fromCols);
        $common = array_intersect($fromCols, $toCols);

        foreach ($onlyFrom as $name) {
            if (isset($ignoreLookup[strtolower($name)])) {
                continue;
            }

            $col = $from->columns[$name];
            $differences[] = new SchemaDifference(
                type: DifferenceType::ColumnRemoved,
                severity: DifferenceSeverity::Critical,
                table: $from->name,
                object: $name,
                from: $this->columnComparator->display($col),
                to: null,
                message: sprintf('Column `%s`.`%s` is missing on target.', $from->name, $name),
                destructive: false,
            );
        }

        foreach ($onlyTo as $name) {
            if (isset($ignoreLookup[strtolower($name)])) {
                continue;
            }

            $col = $to->columns[$name];
            $differences[] = new SchemaDifference(
                type: DifferenceType::ColumnAdded,
                severity: DifferenceSeverity::Warning,
                table: $from->name,
                object: $name,
                from: null,
                to: $this->columnComparator->display($col),
                message: sprintf('Column `%s`.`%s` exists only on target.', $from->name, $name),
                destructive: true,
            );
        }

        foreach ($common as $name) {
            if (isset($ignoreLookup[strtolower($name)])) {
                continue;
            }

            $differences = array_merge(
                $differences,
                $this->columnComparator->compare($from->name, $from->columns[$name], $to->columns[$name])
            );
        }

        return $differences;
    }

    /**
     * @return list<SchemaDifference>
     */
    private function compareIndexes(TableSchema $from, TableSchema $to): array
    {
        $differences = [];
        $fromKeys = array_keys($from->indexes);
        $toKeys = array_keys($to->indexes);

        // Match indexes by name first; also try matching PRIMARY specially
        $onlyFrom = array_diff($fromKeys, $toKeys);
        $onlyTo = array_diff($toKeys, $fromKeys);
        $common = array_intersect($fromKeys, $toKeys);

        foreach ($onlyFrom as $name) {
            $idx = $from->indexes[$name];
            $differences[] = new SchemaDifference(
                type: DifferenceType::IndexRemoved,
                severity: DifferenceSeverity::Critical,
                table: $from->name,
                object: $name,
                from: $idx->toArray(),
                to: null,
                message: sprintf('Index `%s` is missing on target table `%s`.', $name, $from->name),
            );
        }

        foreach ($onlyTo as $name) {
            $idx = $to->indexes[$name];
            $differences[] = new SchemaDifference(
                type: DifferenceType::IndexAdded,
                severity: DifferenceSeverity::Warning,
                table: $from->name,
                object: $name,
                from: null,
                to: $idx->toArray(),
                message: sprintf('Index `%s` exists only on target table `%s`.', $name, $from->name),
                destructive: true,
            );
        }

        foreach ($common as $name) {
            $differences = array_merge(
                $differences,
                $this->indexComparator->compare($from->name, $from->indexes[$name], $to->indexes[$name])
            );
        }

        return $differences;
    }

    /**
     * @return list<SchemaDifference>
     */
    private function compareForeignKeys(TableSchema $from, TableSchema $to): array
    {
        $differences = [];
        $fromKeys = array_keys($from->foreignKeys);
        $toKeys = array_keys($to->foreignKeys);

        $onlyFrom = array_diff($fromKeys, $toKeys);
        $onlyTo = array_diff($toKeys, $fromKeys);
        $common = array_intersect($fromKeys, $toKeys);

        foreach ($onlyFrom as $name) {
            $fk = $from->foreignKeys[$name];
            $differences[] = new SchemaDifference(
                type: DifferenceType::ForeignKeyRemoved,
                severity: DifferenceSeverity::Critical,
                table: $from->name,
                object: $name,
                from: $fk->toArray(),
                to: null,
                message: sprintf('Foreign key `%s` is missing on target table `%s`.', $name, $from->name),
            );
        }

        foreach ($onlyTo as $name) {
            $fk = $to->foreignKeys[$name];
            $differences[] = new SchemaDifference(
                type: DifferenceType::ForeignKeyAdded,
                severity: DifferenceSeverity::Warning,
                table: $from->name,
                object: $name,
                from: null,
                to: $fk->toArray(),
                message: sprintf('Foreign key `%s` exists only on target table `%s`.', $name, $from->name),
                destructive: true,
            );
        }

        foreach ($common as $name) {
            $differences = array_merge(
                $differences,
                $this->foreignKeyComparator->compare(
                    $from->name,
                    $from->foreignKeys[$name],
                    $to->foreignKeys[$name]
                )
            );
        }

        return $differences;
    }

    /**
     * @return list<SchemaDifference>
     */
    private function compareOptions(TableSchema $from, TableSchema $to): array
    {
        $changed = [];

        if ($from->engine !== null && $to->engine !== null
            && strcasecmp($from->engine, $to->engine) !== 0
        ) {
            $changed['engine'] = ['from' => $from->engine, 'to' => $to->engine];
        }

        $fromCharset = SchemaNormalizer::normalizeCharset($from->charset);
        $toCharset = SchemaNormalizer::normalizeCharset($to->charset);
        if ($fromCharset !== null && $toCharset !== null && $fromCharset !== $toCharset) {
            $changed['charset'] = ['from' => $fromCharset, 'to' => $toCharset];
        }

        $fromCollation = SchemaNormalizer::normalizeCharset($from->collation);
        $toCollation = SchemaNormalizer::normalizeCharset($to->collation);
        if ($fromCollation !== null && $toCollation !== null && $fromCollation !== $toCollation) {
            $changed['collation'] = ['from' => $fromCollation, 'to' => $toCollation];
        }

        if (($from->comment ?? '') !== ($to->comment ?? '')) {
            $changed['comment'] = ['from' => $from->comment, 'to' => $to->comment];
        }

        if ($changed === []) {
            return [];
        }

        return [
            new SchemaDifference(
                type: DifferenceType::TableModified,
                severity: DifferenceSeverity::Info,
                table: $from->name,
                object: $from->name,
                from: [
                    'engine' => $from->engine,
                    'charset' => $from->charset,
                    'collation' => $from->collation,
                    'comment' => $from->comment,
                ],
                to: [
                    'engine' => $to->engine,
                    'charset' => $to->charset,
                    'collation' => $to->collation,
                    'comment' => $to->comment,
                ],
                message: sprintf(
                    'Table options for `%s` differ (%s).',
                    $from->name,
                    implode(', ', array_keys($changed))
                ),
                changedAttributes: array_keys($changed),
            ),
        ];
    }
}
