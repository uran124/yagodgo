<?php

declare(strict_types=1);

namespace App\Helpers;

final class SensitiveData
{
    /**
     * @param array<string, mixed> $config
     */
    public static function maskConfig(array $config): array
    {
        $masked = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = self::maskConfig($value);
                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                $masked[$key] = $value;
                continue;
            }

            $valueString = (string)$value;
            if (self::isSensitiveKey((string)$key)) {
                $masked[$key] = self::maskValue($valueString);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * @param array<int, string> $knownSecrets
     */
    public static function sanitizeText(string $text, array $knownSecrets = []): string
    {
        $result = $text;
        foreach ($knownSecrets as $secret) {
            if ($secret === '') {
                continue;
            }
            $result = str_replace($secret, self::maskValue($secret), $result);
        }

        $patterns = [
            '/(bot\d+:)[A-Za-z0-9_-]+/',
            '/(api[_-]?id[=:\s]+)([^\s,&]+)/i',
            '/(token[=:\s]+)([^\s,&]+)/i',
            '/(password[=:\s]+)([^\s,&]+)/i',
        ];

        foreach ($patterns as $pattern) {
            $result = (string)preg_replace_callback(
                $pattern,
                static fn(array $m): string => ($m[1] ?? '') . '***',
                $result
            );
        }

        return $result;
    }

    private static function isSensitiveKey(string $key): bool
    {
        return (bool)preg_match('/token|secret|password|api[_-]?id|key/i', $key);
    }

    private static function maskValue(string $value): string
    {
        $length = strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length > 0 ? $length : 3);
        }

        return substr($value, 0, 2) . str_repeat('*', max(4, $length - 4)) . substr($value, -2);
    }
}
