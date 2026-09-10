<?php

declare(strict_types=1);

namespace SchemaLens\Exceptions;

class ConnectionInspectionException extends SchemaLensException
{
    public static function unableToInspect(string $connection, string $reason): self
    {
        return new self(
            sprintf(
                'Unable to inspect database "%s".%sReason: %s',
                $connection,
                PHP_EOL,
                $reason
            )
        );
    }

    public static function invalidConnection(string $connection): self
    {
        return new self(
            sprintf('Database connection "%s" is not configured.', $connection)
        );
    }
}
