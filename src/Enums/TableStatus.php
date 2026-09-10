<?php

declare(strict_types=1);

namespace SchemaLens\Enums;

enum TableStatus: string
{
    case Added = 'added';
    case Removed = 'removed';
    case Modified = 'modified';
    case Unchanged = 'unchanged';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Added => 'green',
            self::Removed => 'red',
            self::Modified => 'amber',
            self::Unchanged => 'gray',
        };
    }
}
