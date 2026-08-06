<?php

namespace App\Services;

use Illuminate\Support\Str;

class SensitiveDataSanitizer
{
    private const SENSITIVE_KEYS = [
        'password',
        'passwd',
        'token',
        'secret',
        'private_key',
        'privatekey',
        'preshared_key',
        'presharedkey',
        'psk',
        'community',
        'authorization',
        'credential',
        'key',
    ];

    public function sanitizeArray(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $data[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->sanitizeText($value);
            }
        }

        return $data;
    }

    public function sanitizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = preg_replace(
            '/\b(password|passwd|token|secret|(?:[a-z0-9]+[_-]?)?key|psk|community|authorization|credential)\b(\s*[:=]\s*)([^\s,;]+)/i',
            '$1$2[REDACTED]',
            $value,
        );

        return Str::limit($sanitized ?? '[REDACTED]', 2000);
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '.'], '_', $key));

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
