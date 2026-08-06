<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\Router;
use App\Services\RouterMonitoringService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PollRouterJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 1800;

    public function __construct(public readonly int $routerId, public readonly string $scope = 'resources')
    {
        $this->onQueue('router-poll');
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(RouterMonitoringService $monitoring): void
    {
        $router = Router::find($this->routerId);
        if (! $router || ! $router->enabled || $router->maintenance_mode) {
            return;
        }

        $monitoring->poll($router, $this->scope);
    }

    public function uniqueId(): string
    {
        return $this->routerId.':'.$this->scope;
    }

    public function failed(?\Throwable $exception): void
    {
        $router = Router::find($this->routerId);
        if (! $router) {
            return;
        }

        $router->update(['status' => 'offline']);
        Alert::firstOrCreate(
            ['fingerprint' => 'router-offline-'.$router->id, 'status' => 'open'],
            ['severity' => 'critical', 'source_type' => Router::class, 'source_id' => $router->id, 'title' => 'Router offline', 'message' => "Router {$router->name} gagal dipolling setelah beberapa percobaan.", 'started_at' => now()],
        );
    }
}
