<?php

namespace App\Providers;

use App\Contracts\RouterOsSessionFactoryInterface;
use App\Services\RouterOs\RouterOsSessionFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RouterOsSessionFactoryInterface::class, RouterOsSessionFactory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('router-connection-test', function (Request $request): Limit {
            $router = $request->route('router');
            $routerKey = is_object($router) && method_exists($router, 'getRouteKey') ? $router->getRouteKey() : $router;

            return Limit::perMinute(5)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()).':'.(string) $routerKey);
        });

        Queue::looping(function (): void {
            static $lastHeartbeat = 0;

            if (time() - $lastHeartbeat < 30) {
                return;
            }

            try {
                Cache::put('health:queue-worker:last_seen', now()->timestamp, now()->addMinutes(2));
                $lastHeartbeat = time();
            } catch (\Throwable) {
                // A health heartbeat must never terminate the queue worker.
            }
        });
    }
}
