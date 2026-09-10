<?php

declare(strict_types=1);

namespace SchemaLens\Enums;

enum IndexType: string
{
    case Primary = 'primary';
    case Unique = 'unique';
    case Index = 'index';
    case Fulltext = 'fulltext';
    case Spatial = 'spatial';

    public static function fromMysql(string $indexType, bool $unique, bool $primary): self
    {
        if ($primary) {
            return self::Primary;
        }

        $normalized = strtoupper($indexType);

        return match (true) {
            $normalized === 'FULLTEXT' => self::Fulltext,
            $normalized === 'SPATIAL' => self::Spatial,
            $unique => self::Unique,
            default => self::Index,
        };
    }
}
