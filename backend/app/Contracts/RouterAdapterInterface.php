<?php

namespace App\Contracts;

use App\Models\Router;

interface RouterAdapterInterface
{
    /** @return array{success: bool, message: string, latency_ms?: int} */
    public function testConnection(Router $router): array;

    /** @return array<string, int|float|string|null> */
    public function systemResources(Router $router): array;

    /** @return list<array<string, mixed>> */
    public function activePppoeSessions(Router $router): array;

    /** @return list<array<string, mixed>> */
    public function activeHotspotSessions(Router $router): array;

    /** @return array{connection: array<string, mixed>, system: array<string, mixed>, capabilities: array<string, bool>, datasets: array<string, list<array<string, mixed>>>} */
    public function snapshot(Router $router, string $scope = 'full'): array;
}
