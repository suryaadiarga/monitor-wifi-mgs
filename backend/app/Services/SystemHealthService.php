<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemHealthService
{
    public function check(): array
    {
        $checks = [];

        try {
            DB::select('select 1');
            $checks['database'] = ['status' => 'ok', 'driver' => DB::getDriverName()];
        } catch (\Throwable) {
            $checks['database'] = ['status' => 'error', 'message' => 'Koneksi database gagal'];
        }

        try {
            $pong = Redis::connection()->ping();
            $checks['redis'] = ['status' => in_array((string) $pong, ['1', 'PONG'], true) ? 'ok' : 'error'];
        } catch (\Throwable) {
            $checks['redis'] = ['status' => 'error', 'message' => 'Redis tidak tersedia'];
        }

        $checks['queue_worker'] = $this->heartbeatCheck('health:queue-worker:last_seen', 120);
        $checks['scheduler'] = $this->heartbeatCheck('health:scheduler:last_seen', 180);
        $checks['mikrotik'] = $this->routerCheck();
        $healthy = collect($checks)->every(fn (array $check): bool => $check['status'] === 'ok');

        return [
            'healthy' => $healthy,
            'checks' => $checks,
            'time' => now()->toIso8601String(),
        ];
    }

    private function heartbeatCheck(string $key, int $maximumAge): array
    {
        try {
            $timestamp = Cache::get($key);
            $age = is_numeric($timestamp) ? max(0, now()->timestamp - (int) $timestamp) : null;

            return [
                'status' => $age !== null && $age <= $maximumAge ? 'ok' : 'error',
                'age_seconds' => $age,
                'maximum_age_seconds' => $maximumAge,
                'message' => $age === null ? 'Heartbeat belum tersedia' : ($age > $maximumAge ? 'Heartbeat kedaluwarsa' : null),
            ];
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => 'Heartbeat tidak dapat dibaca'];
        }
    }

    private function routerCheck(): array
    {
        try {
            $routers = Router::query()->where('enabled', true)->where('maintenance_mode', false)->get();
            if ($routers->isEmpty()) {
                return ['status' => 'error', 'message' => 'Router aktif belum dikonfigurasi', 'routers' => []];
            }

            $items = $routers->map(function (Router $router): array {
                $capabilities = $router->capabilities ?? [];
                $lastSuccessAge = $router->last_successful_sync_at
                    ? (int) $router->last_successful_sync_at->diffInSeconds(now(), true)
                    : null;
                $tcpOk = $capabilities['tcp_ok'] ?? ($router->status === 'online');
                $authOk = $capabilities['auth_ok'] ?? ($router->status === 'online');
                $fresh = $lastSuccessAge !== null && $lastSuccessAge <= 600;

                return [
                    'id' => $router->id,
                    'name' => $router->name,
                    'status' => $tcpOk === true && $authOk === true && $fresh ? 'ok' : 'error',
                    'tcp_ok' => $tcpOk,
                    'auth_ok' => $authOk,
                    'last_checked_at' => $router->last_checked_at?->toIso8601String(),
                    'last_successful_sync_at' => $router->last_successful_sync_at?->toIso8601String(),
                    'last_successful_sync_age_seconds' => $lastSuccessAge,
                    'sync_fresh' => $fresh,
                    'error_code' => $capabilities['last_error_code'] ?? null,
                ];
            });

            return [
                'status' => $items->every(fn (array $item): bool => $item['status'] === 'ok') ? 'ok' : 'error',
                'routers' => $items->values()->all(),
            ];
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => 'Status router tidak dapat dibaca', 'routers' => []];
        }
    }
}
