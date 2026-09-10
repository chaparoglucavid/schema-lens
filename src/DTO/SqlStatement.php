<?php

declare(strict_types=1);

namespace SchemaLens\DTO;

final readonly class SqlStatement
{
    public function __construct(
        public string $sql,
        public bool $destructive = false,
        public string $description = '',
        public ?string $table = null,
    ) {}

    public function toArray(): array
    {
        return [
            'sql' => $this->sql,
            'destructive' => $this->destructive,
            'description' => $this->description,
            'table' => $this->table,
        ];
    }
}
