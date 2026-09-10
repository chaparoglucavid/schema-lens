<?php

declare(strict_types=1);

namespace SchemaLens\DTO;

final readonly class ForeignKeySchema
{
    /**
     * @param  list<string>  $columns
     * @param  list<string>  $referencedColumns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public string $referencedTable,
        public array $referencedColumns,
        public string $onDelete = 'RESTRICT',
        public string $onUpdate = 'RESTRICT',
    ) {}

    public function definition(): string
    {
        $local = implode(', ', $this->columns);
        $ref = implode(', ', $this->referencedColumns);

        return sprintf(
            'FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE %s',
            $local,
            $this->referencedTable,
            $ref,
            strtoupper($this->onDelete),
            strtoupper($this->onUpdate)
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'columns' => $this->columns,
            'referenced_table' => $this->referencedTable,
            'referenced_columns' => $this->referencedColumns,
            'on_delete' => strtoupper($this->onDelete),
            'on_update' => strtoupper($this->onUpdate),
            'definition' => $this->definition(),
        ];
    }
}
