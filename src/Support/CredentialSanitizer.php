<?php

declare(strict_types=1);

namespace SchemaLens\Support;

/**
 * Utilities that ensure credentials never leak into output.
 */
final class CredentialSanitizer
{
    /**
     * Remove password-like values from a string.
     */
    public static function sanitize(string $text): string
    {
        $patterns = [
            '/(["\']?password["\']?\s*[:=]\s*)(["\']?)[^"\'\s,}]+(["\']?)/i' => '$1$2***$3',
            '/(["\']?pwd["\']?\s*[:=]\s*)(["\']?)[^"\'\s,}]+(["\']?)/i' => '$1$2***$3',
            '/(:\/\/[^:\/\s]+:)([^@\/\s]+)(@)/' => '$1***$3',
            '/(mysql:\/\/[^:]+:)([^@]+)(@)/i' => '$1***$3',
            '/(PDO::MYSQL[^;]*password=)([^;]+)/i' => '$1***',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }

    /**
     * Recursively sanitize an array for safe JSON/HTML export.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeArray(array $data): array
    {
        $forbidden = ['password', 'pwd', 'passwd', 'secret', 'credentials'];

        $result = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $forbidden, true)) {
                continue;
            }

            if (is_array($value)) {
                $result[$key] = self::sanitizeArray($value);
            } elseif (is_string($value)) {
                $result[$key] = self::sanitize($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
