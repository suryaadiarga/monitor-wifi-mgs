<?php

namespace App\Adapters\Router;

use App\Contracts\RouterAdapterInterface;
use App\Models\Router;

class MockRouterAdapter implements RouterAdapterInterface
{
    public function testConnection(Router $router): array
    {
        return ['success' => true, 'message' => 'Koneksi mock berhasil', 'latency_ms' => 12];
    }

    public function systemResources(Router $router): array
    {
        $seed = crc32($router->name.$router->host);

        return [
            'cpu_percent' => 15 + ($seed % 35),
            'memory_percent' => 32 + ($seed % 26),
            'storage_percent' => 28 + ($seed % 18),
            'temperature_celsius' => 39 + ($seed % 12),
            'uptime_seconds' => 86400 + ($seed % 1200000),
            'download_bps' => 15000000 + ($seed % 60000000),
            'upload_bps' => 3000000 + ($seed % 15000000),
        ];
    }

    public function activePppoeSessions(Router $router): array
    {
        return [
            ['username' => 'demo-aktif-01', 'address' => '10.10.0.21', 'uptime' => '4h12m', 'caller_id' => 'AA:BB:CC:00:00:01'],
            ['username' => 'demo-aktif-02', 'address' => '10.10.0.22', 'uptime' => '1h44m', 'caller_id' => 'AA:BB:CC:00:00:02'],
        ];
    }

    public function activeHotspotSessions(Router $router): array
    {
        return [['username' => 'voucher-demo', 'address' => '10.20.0.15', 'uptime' => '35m']];
    }

    public function snapshot(Router $router, string $scope = 'full'): array
    {
        $resource = $this->systemResources($router);

        return [
            'connection' => ['latency_ms' => 12],
            'system' => [
                'identity' => $router->name,
                'routeros_version' => '7.20.8-mock',
                'architecture_name' => 'arm64',
                'board_name' => 'mock-board',
                'model' => 'Mock Router',
                'serial_number' => 'MOCK-'.str_pad((string) $router->id, 6, '0', STR_PAD_LEFT),
                'uptime_seconds' => $resource['uptime_seconds'],
                'metric' => $resource,
            ],
            'capabilities' => ['system' => true, 'interfaces' => true, 'pppoe_active' => true, 'hotspot_active' => true],
            'datasets' => [
                'interfaces' => [['.id' => '*1', 'name' => 'ether1', 'type' => 'ether', 'running' => 'true', 'disabled' => 'false', 'dynamic' => 'false', 'rx-byte' => '1000', 'tx-byte' => '2000']],
                'pppoe_active' => array_map(fn (array $row, int $index) => ['.id' => '*'.($index + 1), 'name' => $row['username']] + $row, $this->activePppoeSessions($router), array_keys($this->activePppoeSessions($router))),
                'hotspot_active' => [['.id' => '*1', 'user' => 'voucher-demo', 'address' => '10.20.0.15', 'uptime' => '35m']],
            ],
        ];
    }
}
