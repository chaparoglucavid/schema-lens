<?php

declare(strict_types=1);

namespace SchemaLens\DTO;

final readonly class TableSchema
{
    /**
     * @param  array<string, ColumnSchema>  $columns
     * @param  array<string, IndexSchema>  $indexes
     * @param  array<string, ForeignKeySchema>  $foreignKeys
     */
    public function __construct(
        public string $name,
        public array $columns = [],
        public array $indexes = [],
        public array $foreignKeys = [],
        public ?string $engine = null,
        public ?string $charset = null,
        public ?string $collation = null,
        public ?string $comment = null,
    ) {}

    public function getColumn(string $name): ?ColumnSchema
    {
        return $this->columns[$name] ?? null;
    }

    public function getIndex(string $name): ?IndexSchema
    {
        return $this->indexes[$name] ?? null;
    }

    public function getForeignKey(string $name): ?ForeignKeySchema
    {
        return $this->foreignKeys[$name] ?? null;
    }

    public function columnNames(): array
    {
        return array_keys($this->columns);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'engine' => $this->engine,
            'charset' => $this->charset,
            'collation' => $this->collation,
            'comment' => $this->comment,
            'columns' => array_map(fn (ColumnSchema $c) => $c->toArray(), $this->columns),
            'indexes' => array_map(fn (IndexSchema $i) => $i->toArray(), $this->indexes),
            'foreign_keys' => array_map(fn (ForeignKeySchema $f) => $f->toArray(), $this->foreignKeys),
        ];
    }
}
