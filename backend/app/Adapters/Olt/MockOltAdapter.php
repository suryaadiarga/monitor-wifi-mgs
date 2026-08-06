<?php

namespace App\Adapters\Olt;

use App\Contracts\OltAdapterInterface;
use App\Models\Olt;

class MockOltAdapter implements OltAdapterInterface
{
    public function testConnection(Olt $olt): array
    {
        return ['success' => true, 'message' => 'Koneksi OLT mock berhasil'];
    }

    public function listOnts(Olt $olt): array
    {
        return [
            ['serial_number' => 'MOCK00000001', 'onu_id' => '0/1/1:1', 'status' => 'online', 'optical_rx' => -19.4],
            ['serial_number' => 'MOCK00000002', 'onu_id' => '0/1/1:2', 'status' => 'los', 'optical_rx' => -31.8],
        ];
    }

    public function previewCommand(Olt $olt, string $command, array $parameters = []): array
    {
        return ['dry_run' => true, 'adapter' => 'mock', 'command' => $command, 'parameters' => $parameters];
    }
}
