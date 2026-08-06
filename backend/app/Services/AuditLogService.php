<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    private const SENSITIVE_KEYS = ['password', 'token', 'secret', 'private_key', 'preshared_key', 'psk', 'community', 'credential', 'snmp_credential'];

    public function __construct(private readonly SensitiveDataSanitizer $sanitizer) {}

    public function record(Request $request, string $module, string $action, ?object $target = null, ?array $before = null, ?array $after = null, string $status = 'success', ?string $error = null): AuditLog
    {
        return AuditLog::create([
            'correlation_id' => $request->attributes->get('correlation_id', (string) Str::uuid()),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000),
            'module' => $module,
            'action' => $action,
            'target_type' => $target ? $target::class : null,
            'target_id' => method_exists($target ?? new \stdClass, 'getKey') ? (string) $target->getKey() : null,
            'before' => $this->sanitize($before),
            'after' => $this->sanitize($after),
            'status' => $status,
            'error' => $error ? $this->sanitizer->sanitizeText($error) : null,
        ]);
    }

    public function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach ($data as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }

    public function recordSystem(string $module, string $action, ?object $target = null, ?array $after = null, string $status = 'success', ?string $error = null, ?string $correlationId = null): AuditLog
    {
        return AuditLog::create([
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'user_id' => null,
            'ip_address' => null,
            'user_agent' => 'system',
            'module' => $module,
            'action' => $action,
            'target_type' => $target ? $target::class : null,
            'target_id' => method_exists($target ?? new \stdClass, 'getKey') ? (string) $target->getKey() : null,
            'before' => null,
            'after' => $this->sanitize($after),
            'status' => $status,
            'error' => $error ? $this->sanitizer->sanitizeText($error) : null,
        ]);
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        return collect(self::SENSITIVE_KEYS)->contains(fn (string $sensitive) => str_contains($key, $sensitive));
    }
}
