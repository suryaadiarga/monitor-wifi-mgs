<?php

namespace App\Adapters\Router;

use App\Contracts\RouterAdapterInterface;
use App\Contracts\RouterOsSessionFactoryInterface;
use App\Contracts\RouterOsSessionInterface;
use App\Exceptions\MikroTikTrapException;
use App\Models\Router;
use App\Services\RouterOs\RouterOsValueParser;

class RouterOsAdapter implements RouterAdapterInterface
{
    private const DATASET_COMMANDS = [
        'interfaces' => ['/interface/print', ['.id', 'name', 'type', 'running', 'disabled', 'dynamic', 'mtu', 'actual-mtu', 'mac-address', 'comment', 'rx-byte', 'tx-byte', 'rx-packet', 'tx-packet', 'link-downs', 'last-link-up-time', 'last-link-down-time']],
        'pppoe_active' => ['/ppp/active/print', ['.id', 'name', 'service', 'caller-id', 'address', 'uptime', 'encoding', 'session-id', 'interface', 'profile']],
        'ppp_secrets' => ['/ppp/secret/print', ['.id', 'name', 'service', 'profile', 'local-address', 'remote-address', 'disabled', 'comment']],
        'ppp_profiles' => ['/ppp/profile/print', ['.id', 'name', 'local-address', 'remote-address', 'rate-limit', 'only-one', 'change-tcp-mss', 'use-encryption', 'use-compression']],
        'pppoe_interfaces' => ['/interface/pppoe-server/print', ['.id', 'name', 'user', 'service-name', 'running', 'disabled', 'dynamic', 'comment']],
        'pppoe_servers' => ['/interface/pppoe-server/server/print', ['.id', 'service-name', 'interface', 'default-profile', 'authentication', 'disabled', 'one-session-per-host', 'max-mtu', 'max-mru']],
        'hotspot_active' => ['/ip/hotspot/active/print', ['.id', 'user', 'server', 'address', 'mac-address', 'login-by', 'uptime', 'bytes-in', 'bytes-out']],
        'hotspot_users' => ['/ip/hotspot/user/print', ['.id', 'name', 'server', 'profile', 'mac-address', 'disabled', 'limit-uptime', 'limit-bytes-total', 'comment']],
        'hotspot_profiles' => ['/ip/hotspot/user/profile/print', ['.id', 'name', 'rate-limit', 'shared-users', 'session-timeout', 'idle-timeout', 'keepalive-timeout', 'address-pool']],
        'hotspot_hosts' => ['/ip/hotspot/host/print', ['.id', 'mac-address', 'address', 'to-address', 'server', 'authorized', 'bypassed', 'uptime']],
        'hotspot_bindings' => ['/ip/hotspot/ip-binding/print', ['.id', 'mac-address', 'address', 'to-address', 'server', 'type', 'disabled', 'comment']],
        'hotspot_servers' => ['/ip/hotspot/print', ['.id', 'name', 'interface', 'profile', 'address-pool', 'disabled', 'invalid']],
        'dhcp_leases' => ['/ip/dhcp-server/lease/print', ['.id', 'address', 'mac-address', 'host-name', 'server', 'status', 'dynamic', 'disabled', 'expires-after', 'comment']],
        'dhcp_servers' => ['/ip/dhcp-server/print', ['.id', 'name', 'interface', 'address-pool', 'lease-time', 'disabled', 'invalid']],
        'queue_simple' => ['/queue/simple/print', ['.id', 'name', 'target', 'parent', 'queue', 'max-limit', 'rate', 'disabled', 'comment']],
        'queue_tree' => ['/queue/tree/print', ['.id', 'name', 'parent', 'packet-mark', 'queue', 'max-limit', 'rate', 'disabled', 'comment']],
        'queue_types' => ['/queue/type/print', ['.id', 'name', 'kind', 'pfifo-limit', 'bfifo-limit', 'pcq-rate', 'pcq-classifier']],
        'ip_addresses' => ['/ip/address/print', ['.id', 'address', 'network', 'interface', 'actual-interface', 'dynamic', 'disabled', 'invalid', 'comment']],
        'routes' => ['/ip/route/print', ['.id', 'dst-address', 'gateway', 'distance', 'active', 'dynamic', 'disabled', 'routing-table', 'comment']],
    ];

    private const SCOPES = [
        'status' => [],
        'resources' => ['interfaces'],
        'active' => ['pppoe_active', 'hotspot_active'],
        'network' => ['dhcp_leases', 'dhcp_servers', 'queue_simple', 'queue_tree', 'queue_types', 'ip_addresses', 'routes'],
        'config' => ['ppp_secrets', 'ppp_profiles', 'pppoe_interfaces', 'pppoe_servers', 'hotspot_users', 'hotspot_profiles', 'hotspot_hosts', 'hotspot_bindings', 'hotspot_servers'],
        'full' => ['interfaces', 'pppoe_active', 'ppp_secrets', 'ppp_profiles', 'pppoe_interfaces', 'pppoe_servers', 'hotspot_active', 'hotspot_users', 'hotspot_profiles', 'hotspot_hosts', 'hotspot_bindings', 'hotspot_servers', 'dhcp_leases', 'dhcp_servers', 'queue_simple', 'queue_tree', 'queue_types', 'ip_addresses', 'routes'],
    ];

    public function __construct(private readonly RouterOsSessionFactoryInterface $sessions) {}

    public function testConnection(Router $router): array
    {
        $started = hrtime(true);
        $session = $this->sessions->connect($router);

        try {
            $system = $this->readSystem($session);
            $this->validateSystem($system);

            return [
                'success' => true,
                'message' => 'Koneksi RouterOS read-only berhasil.',
                'latency_ms' => $this->elapsedMilliseconds($started),
                'identity' => $system['identity'],
                'routeros_version' => $system['routeros_version'],
                'uptime_seconds' => $system['uptime_seconds'],
                'model' => $system['model'],
                'serial_number' => $system['serial_number'],
            ];
        } finally {
            $session->close();
        }
    }

    public function systemResources(Router $router): array
    {
        $snapshot = $this->snapshot($router, 'status');

        return $snapshot['system']['metric'];
    }

    public function activePppoeSessions(Router $router): array
    {
        return $this->snapshot($router, 'active')['datasets']['pppoe_active'] ?? [];
    }

    public function activeHotspotSessions(Router $router): array
    {
        return $this->snapshot($router, 'active')['datasets']['hotspot_active'] ?? [];
    }

    public function snapshot(Router $router, string $scope = 'full'): array
    {
        if (! array_key_exists($scope, self::SCOPES)) {
            throw new \InvalidArgumentException('Scope sinkronisasi RouterOS tidak valid.');
        }

        $started = hrtime(true);
        $session = $this->sessions->connect($router);

        try {
            $system = $this->readSystem($session);
            $this->validateSystem($system);
            $connectionLatency = $this->elapsedMilliseconds($started);
            $datasets = [];
            $capabilities = ['system' => true];

            foreach (self::SCOPES[$scope] as $dataset) {
                [$command, $properties] = self::DATASET_COMMANDS[$dataset];
                try {
                    $datasets[$dataset] = $session->query($command, $properties);
                    $capabilities[$dataset] = true;
                } catch (MikroTikTrapException) {
                    $datasets[$dataset] = [];
                    $capabilities[$dataset] = false;
                }
            }

            return [
                'connection' => ['latency_ms' => $connectionLatency],
                'system' => $system,
                'capabilities' => $capabilities,
                'datasets' => $datasets,
            ];
        } finally {
            $session->close();
        }
    }

    private function readSystem(RouterOsSessionInterface $session): array
    {
        $identity = $session->query('/system/identity/print', ['name'])[0] ?? [];
        $resource = $session->query('/system/resource/print', ['uptime', 'version', 'architecture-name', 'board-name', 'cpu-count', 'cpu-load', 'total-memory', 'free-memory', 'total-hdd-space', 'free-hdd-space'])[0] ?? [];
        $routerboard = $session->query('/system/routerboard/print', ['model', 'serial-number', 'current-firmware'])[0] ?? [];
        try {
            $health = $session->query('/system/health/print', ['temperature', 'voltage'])[0] ?? [];
        } catch (MikroTikTrapException) {
            $health = [];
        }

        $totalMemory = RouterOsValueParser::integer($resource['total-memory'] ?? 0);
        $freeMemory = RouterOsValueParser::integer($resource['free-memory'] ?? 0);
        $totalStorage = RouterOsValueParser::integer($resource['total-hdd-space'] ?? 0);
        $freeStorage = RouterOsValueParser::integer($resource['free-hdd-space'] ?? 0);
        $uptimeSeconds = RouterOsValueParser::durationSeconds($resource['uptime'] ?? null);

        return [
            'identity' => $identity['name'] ?? null,
            'routeros_version' => $resource['version'] ?? null,
            'architecture_name' => $resource['architecture-name'] ?? null,
            'board_name' => $resource['board-name'] ?? null,
            'model' => $routerboard['model'] ?? ($resource['board-name'] ?? null),
            'serial_number' => $routerboard['serial-number'] ?? null,
            'uptime_seconds' => $uptimeSeconds,
            'metric' => [
                'cpu_percent' => RouterOsValueParser::decimal($resource['cpu-load'] ?? null),
                'cpu_count' => RouterOsValueParser::integer($resource['cpu-count'] ?? 0) ?: null,
                'memory_percent' => $this->usedPercent($totalMemory, $freeMemory),
                'total_memory_bytes' => $totalMemory ?: null,
                'free_memory_bytes' => $freeMemory ?: null,
                'storage_percent' => $this->usedPercent($totalStorage, $freeStorage),
                'total_storage_bytes' => $totalStorage ?: null,
                'free_storage_bytes' => $freeStorage ?: null,
                'temperature_celsius' => RouterOsValueParser::decimal($health['temperature'] ?? null),
                'voltage_volts' => RouterOsValueParser::decimal($health['voltage'] ?? null),
                'uptime_seconds' => $uptimeSeconds,
                'download_bps' => 0,
                'upload_bps' => 0,
            ],
        ];
    }

    private function validateSystem(array $system): void
    {
        if (blank($system['identity'] ?? null) || blank($system['routeros_version'] ?? null) || ($system['uptime_seconds'] ?? null) === null) {
            throw new MikroTikTrapException('Respons identitas, versi, atau uptime RouterOS tidak lengkap.');
        }
    }

    private function usedPercent(int $total, int $free): ?float
    {
        return $total > 0 ? round((($total - min($free, $total)) / $total) * 100, 2) : null;
    }

    private function elapsedMilliseconds(int $started): int
    {
        return max(1, (int) round((hrtime(true) - $started) / 1_000_000));
    }
}
