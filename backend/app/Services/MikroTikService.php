<?php

namespace App\Services;

use App\Adapters\Router\MockRouterAdapter;
use App\Adapters\Router\RouterOsAdapter;
use App\Contracts\RouterAdapterInterface;
use App\Exceptions\MikroTikAuthenticationException;
use App\Exceptions\MikroTikTimeoutException;
use App\Exceptions\MikroTikTrapException;
use App\Models\Router;

class MikroTikService
{
    public function __construct(
        private readonly MockRouterAdapter $mockAdapter,
        private readonly RouterOsAdapter $routerOsAdapter,
        private readonly SensitiveDataSanitizer $sanitizer,
    ) {}

    private function adapter(Router $router): RouterAdapterInterface
    {
        return match (config('isp.integrations.mikrotik_driver')) {
            'mock' => $this->mockAdapter,
            'routeros' => $this->routerOsAdapter,
            default => throw new \RuntimeException('Driver MikroTik tidak didukung.'),
        };
    }

    public function testConnection(Router $router): array
    {
        try {
            return $this->adapter($router)->testConnection($router) + [
                'tcp_ok' => true,
                'auth_ok' => true,
                'checked_at' => now()->toAtomString(),
                'error_code' => null,
            ];
        } catch (\Throwable $exception) {
            $errorCode = match (true) {
                $exception instanceof MikroTikAuthenticationException => 'authentication_failed',
                $exception instanceof MikroTikTimeoutException => 'connection_timeout',
                $exception instanceof MikroTikTrapException => 'routeros_trap',
                default => 'connection_failed',
            };

            return [
                'success' => false,
                'message' => $this->safeFailureMessage($exception),
                'error_code' => $errorCode,
                'tcp_ok' => in_array($errorCode, ['authentication_failed', 'routeros_trap'], true),
                'auth_ok' => $errorCode === 'routeros_trap' ? true : ($errorCode === 'authentication_failed' ? false : null),
                'checked_at' => now()->toAtomString(),
            ];
        }
    }

    public function resources(Router $router): array
    {
        return $this->adapter($router)->systemResources($router);
    }

    public function activePppoeSessions(Router $router): array
    {
        return $this->adapter($router)->activePppoeSessions($router);
    }

    public function activeHotspotSessions(Router $router): array
    {
        return $this->adapter($router)->activeHotspotSessions($router);
    }

    public function snapshot(Router $router, string $scope = 'full'): array
    {
        return $this->adapter($router)->snapshot($router, $scope);
    }

    private function safeFailureMessage(\Throwable $exception): string
    {
        $message = $this->sanitizer->sanitizeText($exception->getMessage());

        return blank($message) ? 'Koneksi RouterOS gagal.' : $message;
    }
}
