<?php

declare(strict_types=1);

namespace SchemaLens\Support\Normalization;

/**
 * Normalizes schema metadata so semantically equivalent definitions
 * are not reported as differences due to formatting quirks.
 */
final class SchemaNormalizer
{
    /**
     * Normalize a default value for semantic comparison.
     */
    public static function normalizeDefault(mixed $default, string $nativeType): mixed
    {
        if ($default === null) {
            return null;
        }

        if (is_string($default)) {
            $trimmed = trim($default);

            // Strip surrounding quotes often present in INFORMATION_SCHEMA
            if (
                (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))
                || (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'))
            ) {
                $trimmed = substr($trimmed, 1, -1);
            }

            $upper = strtoupper($trimmed);

            if (in_array($upper, ['NULL', 'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()', 'NOW()'], true)) {
                return $upper === 'NULL' ? null : 'CURRENT_TIMESTAMP';
            }

            // Numeric defaults stored as strings
            $type = strtolower($nativeType);
            if (in_array($type, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint'], true)
                && is_numeric($trimmed)
            ) {
                return (int) $trimmed;
            }

            if (in_array($type, ['decimal', 'numeric', 'float', 'double', 'real'], true)
                && is_numeric($trimmed)
            ) {
                return (string) (0 + $trimmed);
            }

            if (in_array($type, ['bit', 'boolean', 'bool'], true)) {
                if (in_array(strtolower($trimmed), ['1', 'b\'1\'', "b'1'", 'true'], true)) {
                    return 1;
                }
                if (in_array(strtolower($trimmed), ['0', 'b\'0\'', "b'0'", 'false'], true)) {
                    return 0;
                }
            }

            return $trimmed;
        }

        return $default;
    }

    /**
     * Normalize native type name for comparison.
     */
    public static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return match ($type) {
            'integer' => 'int',
            'bool' => 'tinyint',
            'boolean' => 'tinyint',
            'real' => 'double',
            'character' => 'char',
            'character varying' => 'varchar',
            default => $type,
        };
    }

    /**
     * Normalize referential action (ON DELETE / ON UPDATE).
     */
    public static function normalizeAction(string $action): string
    {
        $action = strtoupper(trim($action));

        return match ($action) {
            'NO ACTION', '' => 'RESTRICT',
            default => $action,
        };
    }

    /**
     * Normalize charset/collation casing.
     */
    public static function normalizeCharset(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return strtolower($value);
    }

    /**
     * Compare two defaults semantically.
     */
    public static function defaultsEqual(mixed $a, mixed $b, string $nativeType): bool
    {
        $na = self::normalizeDefault($a, $nativeType);
        $nb = self::normalizeDefault($b, $nativeType);

        if ($na === null && $nb === null) {
            return true;
        }

        return $na == $nb; // loose equality for numeric string equivalence
    }
}
