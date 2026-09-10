<?php

declare(strict_types=1);

namespace SchemaLens\DTO;

/**
 * Safe connection display info — never includes passwords or DSNs with credentials.
 */
final readonly class ConnectionInfo
{
    public function __construct(
        public string $name,
        public string $driver,
        public string $database,
        public ?string $host = null,
        public ?int $port = null,
    ) {}

    public function toArray(bool $showHost = true): array
    {
        $data = [
            'name' => $this->name,
            'driver' => $this->driver,
            'database' => $this->database,
        ];

        if ($showHost) {
            $data['host'] = $this->host;
            $data['port'] = $this->port;
        }

        return $data;
    }
}
