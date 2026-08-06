<?php

namespace App\Services;

use App\Adapters\Olt\MockOltAdapter;
use App\Contracts\OltAdapterInterface;
use App\Models\Olt;
use RuntimeException;

class OltService
{
    public function __construct(private readonly MockOltAdapter $mockAdapter) {}

    /** @return array{success: bool, message: string} */
    public function testConnection(Olt $olt): array
    {
        return $this->adapter($olt)->testConnection($olt);
    }

    /** @return list<array<string, mixed>> */
    public function listOnts(Olt $olt): array
    {
        return $this->adapter($olt)->listOnts($olt);
    }

    private function adapter(Olt $olt): OltAdapterInterface
    {
        $driver = strtolower((string) config('isp.integrations.olt_driver', 'mock'));
        $protocol = strtolower((string) $olt->protocol);

        if ($driver !== 'mock' || $protocol !== 'mock') {
            throw new RuntimeException('Driver OLT nyata belum terverifikasi dan tidak diaktifkan. Gunakan driver serta protokol mock untuk pengujian lokal.');
        }

        return $this->mockAdapter;
    }
}
