<?php

namespace App\Services;

use App\Adapters\Vpn\MockWireGuardAdapter;
use App\Models\VpnClient;
use App\Models\VpnServer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WireGuardService
{
    public function __construct(private readonly MockWireGuardAdapter $adapter) {}

    /** @return array<string, mixed> */
    public function previewClient(VpnServer $server, array $attributes): array
    {
        $normalized = $this->normalizeAttributes($server, $attributes);

        return $this->adapter->previewClient($normalized);
    }

    /** @return array<string, mixed> */
    public function provisionClient(VpnServer $server, array $attributes): array
    {
        return DB::transaction(function () use ($server, $attributes): array {
            $lockedServer = VpnServer::query()->lockForUpdate()->findOrFail($server->getKey());
            $normalized = $this->normalizeAttributes($lockedServer, $attributes);

            if (VpnClient::withTrashed()->where('vpn_server_id', $lockedServer->id)->where('name', $normalized['name'])->exists()) {
                throw ValidationException::withMessages(['name' => 'Nama client sudah digunakan pada server ini.']);
            }

            if (VpnClient::withTrashed()->where('vpn_server_id', $lockedServer->id)->where('assigned_ip', $normalized['assigned_ip'])->exists()) {
                throw ValidationException::withMessages(['assigned_ip' => 'Alamat IP sudah dialokasikan pada server ini.']);
            }

            $provisioned = $this->adapter->provisionClient($normalized);
            $client = VpnClient::create([
                'vpn_server_id' => $lockedServer->id,
                'user_id' => $normalized['user_id'] ?? null,
                'name' => $normalized['name'],
                'assigned_ip' => $normalized['assigned_ip'],
                'public_key' => $provisioned['public_key'],
                'private_key' => $provisioned['private_key'],
                'preshared_key' => $provisioned['preshared_key'],
                'expires_at' => $normalized['expires_at'] ?? null,
                'enabled' => true,
            ]);

            return [
                'client' => [
                    'id' => $client->id,
                    'vpn_server_id' => $client->vpn_server_id,
                    'name' => $client->name,
                    'assigned_ip' => $client->assigned_ip,
                    'public_key' => $client->public_key,
                    'expires_at' => $client->expires_at?->toIso8601String(),
                    'enabled' => $client->enabled,
                ],
                'provisioning' => [
                    'private_key' => $provisioned['private_key'],
                    'preshared_key' => $provisioned['preshared_key'],
                    'configuration' => $provisioned['configuration'],
                    'shown_once' => true,
                ],
                'adapter' => 'mock',
                'dry_run' => true,
                'message' => 'Client mock dibuat. Tidak ada perubahan WireGuard pada sistem operasi.',
            ];
        });
    }

    /** @return array<string, mixed> */
    private function normalizeAttributes(VpnServer $server, array $attributes): array
    {
        if (strtolower($server->type) !== 'wireguard') {
            throw ValidationException::withMessages(['vpn_server_id' => 'Server yang dipilih bukan WireGuard.']);
        }

        if (! $server->enabled) {
            throw ValidationException::withMessages(['vpn_server_id' => 'Server WireGuard sedang nonaktif.']);
        }

        $assignedIp = $attributes['assigned_ip'] ?? $this->nextAvailableIp($server);
        if (! $this->ipBelongsToPool($assignedIp, $server->address_pool ?: '10.88.0.0/24')) {
            throw ValidationException::withMessages(['assigned_ip' => 'Alamat IP berada di luar address pool server.']);
        }

        return [
            ...$attributes,
            'server_id' => $server->id,
            'server_name' => $server->name,
            'endpoint' => $server->endpoint,
            'assigned_ip' => $assignedIp,
        ];
    }

    private function nextAvailableIp(VpnServer $server): string
    {
        [$network, $prefix] = $this->parseIpv4Cidr($server->address_pool ?: '10.88.0.0/24');
        $mask = (-1 << (32 - $prefix)) & 0xFFFFFFFF;
        $networkLong = ip2long($network) & $mask;
        $broadcastLong = $networkLong | (~$mask & 0xFFFFFFFF);
        $used = VpnClient::withTrashed()
            ->where('vpn_server_id', $server->id)
            ->pluck('assigned_ip')
            ->filter()
            ->flip();

        for ($candidate = $networkLong + 2; $candidate < $broadcastLong; $candidate++) {
            $ip = long2ip($candidate);
            if ($ip !== false && ! $used->has($ip)) {
                return $ip;
            }
        }

        throw ValidationException::withMessages(['assigned_ip' => 'Address pool WireGuard sudah penuh.']);
    }

    private function ipBelongsToPool(string $ip, string $cidr): bool
    {
        [$network, $prefix] = $this->parseIpv4Cidr($cidr);
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        if ($ipLong === false || $networkLong === false) {
            return false;
        }

        $mask = (-1 << (32 - $prefix)) & 0xFFFFFFFF;
        $base = $networkLong & $mask;
        $broadcast = $base | (~$mask & 0xFFFFFFFF);

        return $ipLong > $base && $ipLong < $broadcast;
    }

    /** @return array{0: string, 1: int} */
    private function parseIpv4Cidr(string $cidr): array
    {
        $parts = explode('/', $cidr, 2);
        $network = $parts[0] ?? '';
        $prefix = isset($parts[1]) ? (int) $parts[1] : -1;

        if (filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || $prefix < 16 || $prefix > 30) {
            throw ValidationException::withMessages(['vpn_server_id' => 'Address pool mock harus berupa CIDR IPv4 dengan prefix /16 sampai /30.']);
        }

        return [$network, $prefix];
    }
}
