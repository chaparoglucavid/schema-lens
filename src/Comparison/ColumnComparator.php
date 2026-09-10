<?php

declare(strict_types=1);

namespace SchemaLens\Comparison;

use SchemaLens\DTO\ColumnSchema;
use SchemaLens\DTO\SchemaDifference;
use SchemaLens\Enums\DifferenceSeverity;
use SchemaLens\Enums\DifferenceType;
use SchemaLens\Support\Normalization\SchemaNormalizer;

final class ColumnComparator
{
    /**
     * @return list<SchemaDifference>
     */
    public function compare(string $table, ColumnSchema $from, ColumnSchema $to): array
    {
        $changed = [];

        if (SchemaNormalizer::normalizeType($from->nativeType) !== SchemaNormalizer::normalizeType($to->nativeType)) {
            $changed[] = 'type';
        }

        if ($from->length !== $to->length) {
            // Ignore length diffs for types that don't meaningfully use length the same way
            if ($this->lengthMatters($from->nativeType) || $this->lengthMatters($to->nativeType)) {
                $changed[] = 'length';
            }
        }

        if ($from->precision !== $to->precision) {
            $changed[] = 'precision';
        }

        if ($from->scale !== $to->scale) {
            $changed[] = 'scale';
        }

        if ($from->nullable !== $to->nullable) {
            $changed[] = 'nullable';
        }

        if (! SchemaNormalizer::defaultsEqual($from->default, $to->default, $from->nativeType)) {
            $changed[] = 'default';
        }

        if ($from->autoIncrement !== $to->autoIncrement) {
            $changed[] = 'auto_increment';
        }

        if ($from->unsigned !== $to->unsigned) {
            $changed[] = 'unsigned';
        }

        if (($from->comment ?? '') !== ($to->comment ?? '')) {
            $changed[] = 'comment';
        }

        if ($from->generated !== $to->generated) {
            $changed[] = 'generated';
        }

        if ($changed === []) {
            return [];
        }

        return [
            new SchemaDifference(
                type: DifferenceType::ColumnModified,
                severity: DifferenceSeverity::Warning,
                table: $table,
                object: $from->name,
                from: $this->display($from),
                to: $this->display($to),
                message: sprintf(
                    'Column `%s`.`%s` differs (%s).',
                    $table,
                    $from->name,
                    implode(', ', $changed)
                ),
                changedAttributes: $changed,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function display(ColumnSchema $column): array
    {
        return [
            'name' => $column->name,
            'type_definition' => $column->typeDefinition(),
            'nullable' => $column->nullabilityLabel(),
            'default' => $column->defaultLabel(),
            'auto_increment' => $column->autoIncrement,
            'unsigned' => $column->unsigned,
            'comment' => $column->comment,
            'ordinal_position' => $column->ordinalPosition,
            'native_type' => $column->nativeType,
            'length' => $column->length,
            'precision' => $column->precision,
            'scale' => $column->scale,
        ];
    }

    private function lengthMatters(string $type): bool
    {
        $type = strtolower($type);

        return in_array($type, [
            'varchar', 'char', 'varbinary', 'binary', 'bit', 'year',
        ], true);
    }
}
