<?php

declare(strict_types=1);

namespace SchemaLens\DTO;

use SchemaLens\Enums\DifferenceSeverity;
use SchemaLens\Enums\DifferenceType;

final readonly class SchemaDifference
{
    /**
     * @param  array<string, mixed>|null  $from
     * @param  array<string, mixed>|null  $to
     * @param  list<string>  $changedAttributes
     */
    public function __construct(
        public DifferenceType $type,
        public DifferenceSeverity $severity,
        public string $table,
        public string $object,
        public ?array $from,
        public ?array $to,
        public string $message,
        public array $changedAttributes = [],
        public bool $destructive = false,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'severity' => $this->severity->value,
            'category' => $this->type->category(),
            'table' => $this->table,
            'object' => $this->object,
            'from' => $this->from,
            'to' => $this->to,
            'message' => $this->message,
            'changed_attributes' => $this->changedAttributes,
            'destructive' => $this->destructive,
        ];
    }
}
