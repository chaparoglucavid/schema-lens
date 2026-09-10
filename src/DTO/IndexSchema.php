<?php

declare(strict_types=1);

namespace SchemaLens\DTO;

use SchemaLens\Enums\IndexType;

final readonly class IndexSchema
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public string $name,
        public IndexType $type,
        public bool $unique,
        public array $columns,
    ) {}

    public function definition(): string
    {
        $cols = implode(', ', $this->columns);

        return match ($this->type) {
            IndexType::Primary => "PRIMARY KEY ({$cols})",
            IndexType::Unique => "UNIQUE ({$cols})",
            IndexType::Fulltext => "FULLTEXT ({$cols})",
            IndexType::Spatial => "SPATIAL ({$cols})",
            IndexType::Index => "INDEX ({$cols})",
        };
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'unique' => $this->unique,
            'columns' => $this->columns,
            'definition' => $this->definition(),
        ];
    }
}
