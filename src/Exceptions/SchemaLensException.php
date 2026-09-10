<?php

declare(strict_types=1);

namespace SchemaLens\Exceptions;

use Exception;
use Throwable;

class SchemaLensException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($this->sanitize($message), $code, $previous);
    }

    /**
     * Strip credential-like substrings from exception messages.
     */
    protected function sanitize(string $message): string
    {
        $patterns = [
            '/password[=:]\s*[^\s;\'"]+/i',
            '/pwd[=:]\s*[^\s;\'"]+/i',
            '/passwd[=:]\s*[^\s;\'"]+/i',
            '/:\/\/[^:]+:([^@]+)@/',
        ];

        $sanitized = preg_replace($patterns[0], 'password=***', $message) ?? $message;
        $sanitized = preg_replace($patterns[1], 'pwd=***', $sanitized) ?? $sanitized;
        $sanitized = preg_replace($patterns[2], 'passwd=***', $sanitized) ?? $sanitized;
        $sanitized = preg_replace($patterns[3], '://***:***@', $sanitized) ?? $sanitized;

        return $sanitized;
    }
}
