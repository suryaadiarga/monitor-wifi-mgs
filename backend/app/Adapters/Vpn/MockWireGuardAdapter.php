<?php

namespace App\Adapters\Vpn;

use App\Contracts\VpnAdapterInterface;

class MockWireGuardAdapter implements VpnAdapterInterface
{
    public function previewClient(array $attributes): array
    {
        return [
            'adapter' => 'mock',
            'dry_run' => true,
            'server_id' => $attributes['server_id'],
            'server_name' => $attributes['server_name'],
            'client_name' => $attributes['name'],
            'assigned_ip' => $attributes['assigned_ip'],
            'endpoint' => $attributes['endpoint'],
            'expires_at' => $attributes['expires_at'] ?? null,
            'changes' => ['Membuat peer WireGuard mock dan metadata client.'],
            'warnings' => ['Tidak ada konfigurasi sistem operasi atau firewall yang diubah.'],
            'secret_delivery' => 'Konfigurasi dan secret hanya dikembalikan satu kali saat provision.',
        ];
    }

    public function provisionClient(array $attributes): array
    {
        $privateKey = base64_encode(random_bytes(32));
        $presharedKey = base64_encode(random_bytes(32));
        $publicKey = base64_encode(hash('sha256', $privateKey, true));
        $configuration = implode("\n", [
            '# MOCK ONLY - bukan konfigurasi production',
            '[Interface]',
            'PrivateKey = '.$privateKey,
            'Address = '.$attributes['assigned_ip'].'/32',
            '',
            '[Peer]',
            'PublicKey = MOCK_SERVER_PUBLIC_KEY',
            'PresharedKey = '.$presharedKey,
            'Endpoint = '.($attributes['endpoint'] ?: 'vpn.example.invalid:51820'),
            'AllowedIPs = 10.0.0.0/8',
        ]);

        return [
            'adapter' => 'mock',
            'dry_run' => true,
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'preshared_key' => $presharedKey,
            'configuration' => $configuration,
            'shown_once' => true,
        ];
    }
}
