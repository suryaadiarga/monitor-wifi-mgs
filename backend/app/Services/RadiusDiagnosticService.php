<?php

namespace App\Services;

use App\Models\RadiusNas;
use Illuminate\Support\Facades\DB;

class RadiusDiagnosticService
{
    /** @return array<string, mixed> */
    public function health(): array
    {
        $connection = config('database.connections.radius');
        $configured = is_array($connection)
            && filled($connection['driver'] ?? null)
            && filled($connection['database'] ?? null);

        $database = [
            'configured' => $configured,
            'connected' => false,
            'message' => $configured
                ? 'Koneksi database RADIUS belum diuji.'
                : 'Koneksi database RADIUS belum dikonfigurasi.',
        ];

        if ($configured) {
            try {
                DB::connection('radius')->selectOne('SELECT 1 AS healthy');
                $database['connected'] = true;
                $database['message'] = 'Koneksi database RADIUS berhasil.';
            } catch (\Throwable) {
                $database['message'] = 'Koneksi database RADIUS gagal. Periksa konfigurasi dan service secara lokal.';
                $database['error_code'] = 'radius_database_unavailable';
            }
        }

        return [
            'driver' => 'dry-run',
            'service' => [
                'checked' => false,
                'running' => null,
                'message' => 'Status daemon dan port tidak diperiksa oleh mock diagnostic.',
            ],
            'database' => $database,
            'nas' => [
                'configured' => RadiusNas::count(),
                'enabled' => RadiusNas::where('enabled', true)->count(),
            ],
            'safe' => true,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /** @param array{username: string, nas_id?: int|null, simulate?: string|null} $attributes
     * @return array<string, mixed>
     */
    public function dryRunAuthentication(array $attributes): array
    {
        $simulation = $attributes['simulate'] ?? 'accept';

        return [
            'driver' => 'mock',
            'dry_run' => true,
            'executed' => false,
            'username' => $attributes['username'],
            'nas_id' => $attributes['nas_id'] ?? null,
            'simulated_result' => $simulation === 'reject' ? 'Access-Reject' : 'Access-Accept',
            'message' => 'Simulasi selesai. Tidak ada paket RADIUS yang dikirim dan password tidak disimpan.',
            'tested_at' => now()->toIso8601String(),
        ];
    }
}
