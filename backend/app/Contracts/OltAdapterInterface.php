<?php

namespace App\Contracts;

use App\Models\Olt;

interface OltAdapterInterface
{
    /** @return array{success: bool, message: string} */
    public function testConnection(Olt $olt): array;

    /** @return list<array<string, mixed>> */
    public function listOnts(Olt $olt): array;

    /** @return array<string, mixed> */
    public function previewCommand(Olt $olt, string $command, array $parameters = []): array;
}
