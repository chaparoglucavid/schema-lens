<?php

declare(strict_types=1);

namespace SchemaLens\Exceptions;

class UnsupportedDriverException extends SchemaLensException
{
    public static function forDriver(string $driver): self
    {
        return new self(
            sprintf(
                'Database driver "%s" is not supported by SchemaLens v1. Supported drivers: mysql, mariadb.',
                $driver
            )
        );
    }
}
