<?php

declare(strict_types=1);

namespace SchemaLens\Enums;

enum DifferenceSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
    case Destructive = 'destructive';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
            self::Destructive => 'Destructive',
        };
    }
}
