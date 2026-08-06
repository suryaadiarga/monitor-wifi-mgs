<?php

namespace App\Services;

use App\Exceptions\MikroTikAuthenticationException;
use App\Exceptions\MikroTikTimeoutException;
use App\Exceptions\MikroTikTrapException;
use App\Models\Router;
use App\Models\RouterInterface;
use App\Models\RouterSyncRun;
use App\Services\RouterOs\RouterOsValueParser;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RouterSyncService
{
    private const TABLE_DATASETS = [
        'pppoe_active' => 'router_pppoe_active_sessions',
        'ppp_secrets' => 'router_ppp_secrets',
        'ppp_profiles' => 'router_ppp_profiles',
        'hotspot_active' => 'router_hotspot_active_sessions',
        'hotspot_users' => 'router_hotspot_users',
        'hotspot_profiles' => 'router_hotspot_profiles',
        'dhcp_leases' => 'router_dhcp_leases',
    ];

    private const INVENTORY_DATASETS = [
        'pppoe_interfaces', 'pppoe_servers', 'hotspot_hosts', 'hotspot_bindings',
        'hotspot_servers', 'dhcp_servers', 'ip_addresses', 'routes',
    ];

    public function __construct(
        private readonly MikroTikService $mikroTik,
        private readonly SensitiveDataSanitizer $sanitizer,
        private readonly AuditLogService $audit,
    ) {}

    public function sync(Router $router, string $scope = 'full', bool $waitForLock = false): ?RouterSyncRun
    {
        $lock = Cache::lock("router:{$router->id}:sync", 50);

        try {
            $acquired = $waitForLock ? $lock->block(45) : $lock->get();
        } catch (LockTimeoutException) {
            return null;
        }

        if (! $acquired) {
            return null;
        }

        try {
            return $this->performSync($router->fresh(), $scope);
        } finally {
            $lock->release();
        }
    }

    private function performSync(Router $router, string $scope): RouterSyncRun
    {
        $correlationId = (string) Str::uuid();
        $started = now()->startOfSecond();
        $timer = hrtime(true);
        $run = RouterSyncRun::create([
            'router_id' => $router->id,
            'correlation_id' => $correlationId,
            'scope' => $scope,
            'status' => 'running',
            'started_at' => $started,
        ]);

        try {
            $snapshot = $this->mikroTik->snapshot($router, $scope);
            $healthState = ['tcp_ok' => true, 'auth_ok' => true, 'checked_at' => now()->toAtomString(), 'last_error_code' => null];
            $counts = DB::transaction(function () use ($router, $scope, $snapshot, $started) {
                $counts = [];
                $datasets = $snapshot['datasets'] ?? [];
                $capabilities = $snapshot['capabilities'] ?? [];

                if (in_array($scope, ['status', 'resources', 'full'], true)) {
                    $router->metrics()->create(($snapshot['system']['metric'] ?? []) + ['recorded_at' => $started]);
                    $counts['resources'] = 1;
                }

                if (($capabilities['interfaces'] ?? false) === true) {
                    $counts['interfaces'] = $this->syncInterfaces($router, $datasets['interfaces'] ?? [], $started);
                }

                foreach (self::TABLE_DATASETS as $dataset => $table) {
                    if (($capabilities[$dataset] ?? false) !== true) {
                        continue;
                    }
                    $rows = array_map(fn (array $row) => $this->normalizeDataset($dataset, $row), $datasets[$dataset] ?? []);
                    $counts[$dataset] = $this->upsertSnapshot($table, $router->id, $rows, ['router_id', 'external_id'], $started);
                }

                foreach (['queue_simple', 'queue_tree', 'queue_types'] as $dataset) {
                    if (($capabilities[$dataset] ?? false) !== true) {
                        continue;
                    }
                    $rows = array_map(fn (array $row) => $this->normalizeQueue($dataset, $row), $datasets[$dataset] ?? []);
                    $counts[$dataset] = $this->upsertSnapshot('router_queues', $router->id, $rows, ['router_id', 'kind', 'external_id'], $started, ['kind' => $dataset]);
                }

                foreach (self::INVENTORY_DATASETS as $dataset) {
                    if (($capabilities[$dataset] ?? false) !== true) {
                        continue;
                    }
                    $rows = array_map(fn (array $row) => $this->normalizeInventory($dataset, $row), $datasets[$dataset] ?? []);
                    $counts[$dataset] = $this->upsertSnapshot('router_inventory_items', $router->id, $rows, ['router_id', 'kind', 'external_id'], $started, ['kind' => $dataset]);
                }

                $system = $snapshot['system'] ?? [];
                $router->update([
                    'identity' => $system['identity'] ?? $router->identity,
                    'routeros_version' => $system['routeros_version'] ?? $router->routeros_version,
                    'architecture_name' => $system['architecture_name'] ?? $router->architecture_name,
                    'board_name' => $system['board_name'] ?? $router->board_name,
                    'model' => $system['model'] ?? $router->model,
                    'serial_number' => $system['serial_number'] ?? $router->serial_number,
                    'uptime_seconds' => $system['uptime_seconds'] ?? $router->uptime_seconds,
                    'connection_latency_ms' => $snapshot['connection']['latency_ms'] ?? null,
                    'capabilities' => array_replace($router->capabilities ?? [], $capabilities, ['tcp_ok' => true, 'auth_ok' => true, 'checked_at' => now()->toAtomString(), 'last_error_code' => null]),
                    'status' => 'online',
                    'last_checked_at' => $started,
                    'last_connected_at' => $started,
                    'last_seen_at' => $started,
                    'last_successful_sync_at' => $started,
                    'last_error' => null,
                ]);

                return $counts;
            });

            $duration = $this->elapsedMilliseconds($timer);
            $run->update([
                'status' => 'success',
                'capabilities' => array_replace($snapshot['capabilities'] ?? [], $healthState),
                'counts' => $counts,
                'finished_at' => now(),
                'duration_ms' => $duration,
            ]);
            $this->audit->recordSystem('routers', 'sync', $router, ['scope' => $scope, 'counts' => $counts, 'duration_ms' => $duration], correlationId: $correlationId);

            return $run->fresh();
        } catch (\Throwable $exception) {
            $error = $this->sanitizer->sanitizeText($exception->getMessage()) ?? 'Sinkronisasi RouterOS gagal.';
            $duration = $this->elapsedMilliseconds($timer);
            $healthState = $this->failureHealthState($exception);
            $run->update(['status' => 'failed', 'capabilities' => $healthState, 'finished_at' => now(), 'duration_ms' => $duration, 'error' => $error]);
            $router->update([
                'status' => 'offline',
                'last_checked_at' => now(),
                'last_error' => $error,
                'capabilities' => array_replace($router->capabilities ?? [], $healthState),
            ]);
            $this->audit->recordSystem('routers', 'sync', $router, ['scope' => $scope, 'duration_ms' => $duration], 'failed', $error, $correlationId);

            throw $exception;
        }
    }

    private function syncInterfaces(Router $router, array $sourceRows, Carbon $seenAt): int
    {
        $rows = array_map(fn (array $row) => $this->normalizeInterface($row), $sourceRows);
        $count = $this->upsertSnapshot('router_interfaces', $router->id, $rows, ['router_id', 'external_id'], $seenAt);

        if ($rows === []) {
            return $count;
        }

        $interfaces = RouterInterface::query()->where('router_id', $router->id)
            ->whereIn('external_id', array_column($rows, 'external_id'))->get()->keyBy('external_id');
        $now = now();
        $metrics = [];
        foreach ($rows as $row) {
            $interface = $interfaces->get($row['external_id']);
            if (! $interface) {
                continue;
            }
            $metrics[] = [
                'router_id' => $router->id,
                'router_interface_id' => $interface->id,
                'rx_bytes' => $row['rx_bytes'],
                'tx_bytes' => $row['tx_bytes'],
                'rx_packets' => $row['rx_packets'],
                'tx_packets' => $row['tx_packets'],
                'recorded_at' => $seenAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($metrics, 500) as $chunk) {
            DB::table('router_interface_metrics')->insert($chunk);
        }

        return $count;
    }

    private function upsertSnapshot(string $table, int $routerId, array $rows, array $uniqueBy, Carbon $seenAt, array $missingFilter = []): int
    {
        $now = now();
        $prepared = [];
        foreach ($rows as $row) {
            $prepared[] = $row + [
                'router_id' => $routerId,
                'last_seen_at' => $seenAt,
                'missing_since' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($prepared, 500) as $chunk) {
            $updates = array_values(array_diff(array_keys($chunk[0]), array_merge($uniqueBy, ['created_at'])));
            DB::table($table)->upsert($chunk, $uniqueBy, $updates);
        }

        $missing = DB::table($table)->where('router_id', $routerId);
        foreach ($missingFilter as $column => $value) {
            $missing->where($column, $value);
        }
        $missing->whereNull('missing_since')->where(function ($query) use ($seenAt) {
            $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $seenAt);
        })->update(['missing_since' => $seenAt, 'updated_at' => $now]);

        return count($prepared);
    }

    private function normalizeInterface(array $row): array
    {
        return [
            'external_id' => $this->externalId($row, 'name'),
            'name' => (string) ($row['name'] ?? 'unknown'),
            'type' => $row['type'] ?? null,
            'running' => RouterOsValueParser::boolean($row['running'] ?? false),
            'disabled' => RouterOsValueParser::boolean($row['disabled'] ?? false),
            'dynamic' => RouterOsValueParser::boolean($row['dynamic'] ?? false),
            'mtu' => RouterOsValueParser::integer($row['mtu'] ?? 0) ?: null,
            'actual_mtu' => RouterOsValueParser::integer($row['actual-mtu'] ?? 0) ?: null,
            'mac_address' => $row['mac-address'] ?? null,
            'comment' => $this->sanitizer->sanitizeText($row['comment'] ?? null),
            'rx_bytes' => RouterOsValueParser::integer($row['rx-byte'] ?? 0),
            'tx_bytes' => RouterOsValueParser::integer($row['tx-byte'] ?? 0),
            'rx_packets' => RouterOsValueParser::integer($row['rx-packet'] ?? 0),
            'tx_packets' => RouterOsValueParser::integer($row['tx-packet'] ?? 0),
            'link_downs' => RouterOsValueParser::integer($row['link-downs'] ?? 0),
            'last_link_up_at' => RouterOsValueParser::dateTime($row['last-link-up-time'] ?? null),
            'last_link_down_at' => RouterOsValueParser::dateTime($row['last-link-down-time'] ?? null),
            'last_polled_at' => now(),
        ];
    }

    private function normalizeDataset(string $dataset, array $row): array
    {
        return match ($dataset) {
            'pppoe_active' => [
                'external_id' => $this->externalId($row, 'name'), 'username' => (string) ($row['name'] ?? 'unknown'),
                'service' => $row['service'] ?? null, 'caller_id' => $row['caller-id'] ?? null,
                'address' => $row['address'] ?? null, 'uptime' => $row['uptime'] ?? null,
                'uptime_seconds' => RouterOsValueParser::durationSeconds($row['uptime'] ?? null),
                'encoding' => $row['encoding'] ?? null, 'session_id' => $row['session-id'] ?? null,
                'interface' => $row['interface'] ?? null, 'profile' => $row['profile'] ?? null,
            ],
            'ppp_secrets' => [
                'external_id' => $this->externalId($row, 'name'), 'username' => (string) ($row['name'] ?? 'unknown'),
                'service' => $row['service'] ?? null, 'profile' => $row['profile'] ?? null,
                'local_address' => $row['local-address'] ?? null, 'remote_address' => $row['remote-address'] ?? null,
                'disabled' => RouterOsValueParser::boolean($row['disabled'] ?? false), 'comment' => $this->sanitizer->sanitizeText($row['comment'] ?? null),
            ],
            'ppp_profiles' => [
                'external_id' => $this->externalId($row, 'name'), 'name' => (string) ($row['name'] ?? 'unknown'),
                'local_address' => $row['local-address'] ?? null, 'remote_address' => $row['remote-address'] ?? null,
                'rate_limit' => $row['rate-limit'] ?? null, 'only_one' => $row['only-one'] ?? null,
                'settings' => json_encode($this->sanitizer->sanitizeArray($row), JSON_THROW_ON_ERROR),
            ],
            'hotspot_active' => [
                'external_id' => $this->externalId($row, 'user'), 'username' => (string) ($row['user'] ?? 'unknown'),
                'server' => $row['server'] ?? null, 'address' => $row['address'] ?? null,
                'mac_address' => $row['mac-address'] ?? null, 'login_by' => $row['login-by'] ?? null,
                'uptime' => $row['uptime'] ?? null, 'uptime_seconds' => RouterOsValueParser::durationSeconds($row['uptime'] ?? null),
                'bytes_in' => RouterOsValueParser::integer($row['bytes-in'] ?? 0), 'bytes_out' => RouterOsValueParser::integer($row['bytes-out'] ?? 0),
            ],
            'hotspot_users' => [
                'external_id' => $this->externalId($row, 'name'), 'username' => (string) ($row['name'] ?? 'unknown'),
                'server' => $row['server'] ?? null, 'profile' => $row['profile'] ?? null,
                'mac_address' => $row['mac-address'] ?? null, 'disabled' => RouterOsValueParser::boolean($row['disabled'] ?? false),
                'limit_uptime' => $row['limit-uptime'] ?? null, 'limit_bytes_total' => $row['limit-bytes-total'] ?? null,
                'comment' => $this->sanitizer->sanitizeText($row['comment'] ?? null),
            ],
            'hotspot_profiles' => [
                'external_id' => $this->externalId($row, 'name'), 'name' => (string) ($row['name'] ?? 'unknown'),
                'rate_limit' => $row['rate-limit'] ?? null, 'shared_users' => $row['shared-users'] ?? null,
                'settings' => json_encode($this->sanitizer->sanitizeArray($row), JSON_THROW_ON_ERROR),
            ],
            'dhcp_leases' => [
                'external_id' => $this->externalId($row, 'address'), 'address' => $row['address'] ?? null,
                'mac_address' => $row['mac-address'] ?? null, 'host_name' => $row['host-name'] ?? null,
                'server' => $row['server'] ?? null, 'status' => $row['status'] ?? null,
                'dynamic' => RouterOsValueParser::boolean($row['dynamic'] ?? false), 'disabled' => RouterOsValueParser::boolean($row['disabled'] ?? false),
                'expires_after' => $row['expires-after'] ?? null, 'comment' => $this->sanitizer->sanitizeText($row['comment'] ?? null),
            ],
            default => throw new \LogicException('Dataset RouterOS tidak dikenali.'),
        };
    }

    private function normalizeQueue(string $dataset, array $row): array
    {
        return [
            'kind' => $dataset,
            'external_id' => $this->externalId($row, 'name'),
            'name' => (string) ($row['name'] ?? 'unknown'),
            'target' => $row['target'] ?? null,
            'parent' => $row['parent'] ?? null,
            'queue_type' => $row['queue'] ?? ($row['kind'] ?? null),
            'max_limit' => $row['max-limit'] ?? null,
            'rate' => $row['rate'] ?? null,
            'disabled' => RouterOsValueParser::boolean($row['disabled'] ?? false),
            'comment' => $this->sanitizer->sanitizeText($row['comment'] ?? null),
            'settings' => json_encode($this->sanitizer->sanitizeArray($row), JSON_THROW_ON_ERROR),
        ];
    }

    private function normalizeInventory(string $dataset, array $row): array
    {
        return [
            'kind' => $dataset,
            'external_id' => $this->externalId($row, 'name'),
            'name' => $row['name'] ?? $row['service-name'] ?? $row['address'] ?? $row['dst-address'] ?? null,
            'status' => $row['status'] ?? (RouterOsValueParser::boolean($row['running'] ?? $row['active'] ?? false) ? 'running' : null),
            'disabled' => RouterOsValueParser::boolean($row['disabled'] ?? false),
            'properties' => json_encode($this->sanitizer->sanitizeArray($row), JSON_THROW_ON_ERROR),
        ];
    }

    private function externalId(array $row, string $fallbackKey): string
    {
        return (string) ($row['.id'] ?? $row[$fallbackKey] ?? hash('sha256', json_encode($this->sanitizer->sanitizeArray($row), JSON_THROW_ON_ERROR)));
    }

    private function elapsedMilliseconds(int $started): int
    {
        return max(1, (int) round((hrtime(true) - $started) / 1_000_000));
    }

    private function failureHealthState(\Throwable $exception): array
    {
        $errorCode = match (true) {
            $exception instanceof MikroTikAuthenticationException => 'authentication_failed',
            $exception instanceof MikroTikTimeoutException => 'connection_timeout',
            $exception instanceof MikroTikTrapException => 'routeros_trap',
            default => 'connection_failed',
        };

        return [
            'tcp_ok' => in_array($errorCode, ['authentication_failed', 'routeros_trap'], true),
            'auth_ok' => $errorCode === 'routeros_trap' ? true : ($errorCode === 'authentication_failed' ? false : null),
            'checked_at' => now()->toAtomString(),
            'last_error_code' => $errorCode,
        ];
    }
}
