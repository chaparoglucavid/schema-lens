<?php

declare(strict_types=1);

namespace SchemaLens\DTO;

final readonly class ColumnSchema
{
    public function __construct(
        public string $name,
        public string $type,
        public string $nativeType,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public bool $nullable = true,
        public mixed $default = null,
        public bool $autoIncrement = false,
        public bool $unsigned = false,
        public ?string $comment = null,
        public int $ordinalPosition = 0,
        public ?string $charset = null,
        public ?string $collation = null,
        public bool $generated = false,
        public ?string $generationExpression = null,
    ) {}

    /**
     * Human-readable type definition for display/diff.
     */
    public function typeDefinition(): string
    {
        $type = strtoupper($this->nativeType);

        if ($this->length !== null && $this->shouldShowLength()) {
            $type .= '('.$this->length.')';
        } elseif ($this->precision !== null && $this->shouldShowPrecision()) {
            $type .= $this->scale !== null
                ? '('.$this->precision.','.$this->scale.')'
                : '('.$this->precision.')';
        }

        if ($this->unsigned) {
            $type .= ' UNSIGNED';
        }

        return $type;
    }

    public function nullabilityLabel(): string
    {
        return $this->nullable ? 'NULL' : 'NOT NULL';
    }

    public function defaultLabel(): string
    {
        if ($this->default === null) {
            return $this->nullable ? 'DEFAULT NULL' : 'NO DEFAULT';
        }

        if (is_string($this->default) && strtoupper($this->default) === 'CURRENT_TIMESTAMP') {
            return 'DEFAULT CURRENT_TIMESTAMP';
        }

        if (is_numeric($this->default)) {
            return 'DEFAULT '.$this->default;
        }

        return "DEFAULT '".$this->default."'";
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'native_type' => $this->nativeType,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'auto_increment' => $this->autoIncrement,
            'unsigned' => $this->unsigned,
            'comment' => $this->comment,
            'ordinal_position' => $this->ordinalPosition,
            'charset' => $this->charset,
            'collation' => $this->collation,
            'generated' => $this->generated,
            'generation_expression' => $this->generationExpression,
            'type_definition' => $this->typeDefinition(),
        ];
    }

    private function shouldShowLength(): bool
    {
        $type = strtolower($this->nativeType);

        return in_array($type, [
            'varchar', 'char', 'varbinary', 'binary',
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint',
            'bit', 'year',
        ], true);
    }

    private function shouldShowPrecision(): bool
    {
        $type = strtolower($this->nativeType);

        return in_array($type, ['decimal', 'numeric', 'float', 'double', 'real'], true);
    }
}
