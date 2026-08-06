<?php

namespace App\Services;

use App\Models\Router;
use App\Models\RouterMetric;
use Illuminate\Support\Facades\Cache;

class RouterMonitoringService
{
    public function __construct(private readonly RouterSyncService $sync) {}

    public function poll(Router $router, string $scope = 'resources'): ?RouterMetric
    {
        $run = $this->sync->sync($router, $scope);
        if (! $run) {
            return null;
        }

        $metric = $router->metrics()->latest('recorded_at')->first();
        if ($metric) {
            Cache::put("router:{$router->id}:latest", $metric->toArray(), now()->addMinutes(5));
        }

        return $metric;
    }
}
