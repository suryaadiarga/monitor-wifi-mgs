<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Router;
use App\Models\RouterInterface;
use App\Models\RouterMetric;
use App\Models\RouterPppSecret;
use App\Models\RouterQueue;
use App\Models\User;
use App\Services\MikroTikService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouterMonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_detail_and_nested_snapshots_are_paginated_and_never_expose_credentials(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        $router = Router::create([
            'name' => 'Read Only Router',
            'host' => '192.0.2.80',
            'api_port' => 8728,
            'username' => 'readonly-api',
            'password' => 'dummy-router-credential',
            'identity' => 'READONLY-IDENTITY',
            'routeros_version' => '7.20.8',
            'status' => 'online',
            'enabled' => true,
        ]);
        RouterMetric::create([
            'router_id' => $router->id,
            'cpu_percent' => 12,
            'memory_percent' => 40,
            'storage_percent' => 30,
            'total_memory_bytes' => 1024,
            'free_memory_bytes' => 512,
            'recorded_at' => now(),
        ]);
        RouterInterface::create([
            'router_id' => $router->id,
            'external_id' => '*1',
            'name' => 'ether1',
            'running' => true,
            'disabled' => false,
            'last_seen_at' => now(),
        ]);
        RouterPppSecret::create([
            'router_id' => $router->id,
            'external_id' => '*2',
            'username' => 'subscriber-one',
            'profile' => 'profile-one',
            'disabled' => false,
            'last_seen_at' => now(),
        ]);
        RouterQueue::create([
            'router_id' => $router->id,
            'kind' => 'queue_simple',
            'external_id' => '*3',
            'name' => 'subscriber-one',
            'disabled' => false,
            'last_seen_at' => now(),
        ]);
        AuditLog::create([
            'correlation_id' => fake()->uuid(),
            'user_id' => $user->id,
            'module' => 'routers',
            'action' => 'test_connection',
            'target_type' => Router::class,
            'target_id' => (string) $router->id,
            'after' => ['password' => 'must-never-leak'],
            'status' => 'success',
        ]);

        $detail = $this->getJson("/api/v1/routers/{$router->id}")
            ->assertOk()
            ->assertJsonPath('data.identity', 'READONLY-IDENTITY')
            ->assertJsonPath('data.credential_configured', true)
            ->assertJsonPath('data.counts.interfaces_active', 1)
            ->assertJsonMissingPath('data.password');

        $this->assertStringNotContainsString('dummy-router-credential', $detail->getContent());

        $secrets = $this->getJson("/api/v1/routers/{$router->id}/ppp-secrets?search=subscriber&sort=name&direction=asc")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'subscriber-one')
            ->assertJsonPath('meta.total', 1);
        $this->assertStringNotContainsString('password', strtolower($secrets->getContent()));

        $this->getJson("/api/v1/routers/{$router->id}/queues?type=simple&sort=type")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'simple');

        $audit = $this->getJson("/api/v1/routers/{$router->id}/audit-logs")
            ->assertOk()
            ->assertJsonPath('data.0.actor.name', $user->name)
            ->assertJsonPath('data.0.after.password', '[REDACTED]');
        $this->assertStringNotContainsString('must-never-leak', $audit->getContent());
    }

    public function test_protected_health_uses_real_heartbeats_and_router_sync_freshness(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);
        Cache::put('health:queue-worker:last_seen', now()->timestamp, 120);
        Cache::put('health:scheduler:last_seen', now()->timestamp, 180);
        Redis::shouldReceive('connection->ping')->once()->andReturn('PONG');

        Router::create([
            'name' => 'Healthy Router',
            'host' => '192.0.2.81',
            'username' => 'readonly-api',
            'password' => 'dummy-router-credential',
            'status' => 'online',
            'enabled' => true,
            'last_checked_at' => now(),
            'last_successful_sync_at' => now()->subMinute(),
            'capabilities' => ['tcp_ok' => true, 'auth_ok' => true, 'last_error_code' => null],
        ]);

        $this->getJson('/api/v1/system/health')
            ->assertOk()
            ->assertJsonPath('data.healthy', true)
            ->assertJsonPath('data.checks.queue_worker.status', 'ok')
            ->assertJsonPath('data.checks.scheduler.status', 'ok')
            ->assertJsonPath('data.checks.mikrotik.routers.0.sync_fresh', true);
    }

    public function test_connection_failure_and_recovery_replace_compact_health_state(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);
        $router = Router::create([
            'name' => 'Connection State Router',
            'host' => '192.0.2.82',
            'username' => 'readonly-api',
            'password' => 'dummy-router-credential',
            'status' => 'online',
            'enabled' => true,
            'connection_latency_ms' => 10,
            'capabilities' => ['tcp_ok' => true, 'auth_ok' => true, 'last_error_code' => null],
        ]);
        $service = $this->mock(MikroTikService::class);
        $service->shouldReceive('testConnection')->twice()->andReturn(
            [
                'success' => false,
                'message' => 'Autentikasi RouterOS ditolak.',
                'error_code' => 'authentication_failed',
                'tcp_ok' => true,
                'auth_ok' => false,
                'checked_at' => now()->toAtomString(),
            ],
            [
                'success' => true,
                'message' => 'Koneksi RouterOS read-only berhasil.',
                'identity' => 'RECOVERED',
                'routeros_version' => '7.20.8',
                'uptime_seconds' => 120,
                'latency_ms' => 15,
                'tcp_ok' => true,
                'auth_ok' => true,
                'checked_at' => now()->toAtomString(),
                'error_code' => null,
            ],
        );

        $this->postJson("/api/v1/routers/{$router->id}/test-connection")
            ->assertUnprocessable()
            ->assertJsonPath('errors.error_code.0', 'authentication_failed');
        $router->refresh();
        $this->assertSame('offline', $router->status);
        $this->assertFalse($router->capabilities['auth_ok']);
        $this->assertSame('authentication_failed', $router->capabilities['last_error_code']);
        $this->assertNull($router->connection_latency_ms);

        $this->postJson("/api/v1/routers/{$router->id}/test-connection")
            ->assertOk()
            ->assertJsonPath('data.identity', 'RECOVERED');
        $router->refresh();
        $this->assertSame('online', $router->status);
        $this->assertTrue($router->capabilities['auth_ok']);
        $this->assertNull($router->capabilities['last_error_code']);
        $this->assertSame(15, $router->connection_latency_ms);
    }

    public function test_protected_health_rejects_stale_router_sync(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);
        Cache::put('health:queue-worker:last_seen', now()->timestamp, 120);
        Cache::put('health:scheduler:last_seen', now()->timestamp, 180);
        Redis::shouldReceive('connection->ping')->once()->andReturn('PONG');
        Router::create([
            'name' => 'Stale Router',
            'host' => '192.0.2.83',
            'username' => 'readonly-api',
            'password' => 'dummy-router-credential',
            'status' => 'online',
            'enabled' => true,
            'last_checked_at' => now(),
            'last_successful_sync_at' => now()->subMinutes(11),
            'capabilities' => ['tcp_ok' => true, 'auth_ok' => true, 'last_error_code' => null],
        ]);

        $this->getJson('/api/v1/system/health')
            ->assertServiceUnavailable()
            ->assertJsonPath('data.healthy', false)
            ->assertJsonPath('data.checks.mikrotik.routers.0.sync_fresh', false);
    }
}
