<?php

namespace Tests\Feature;

use App\Contracts\RouterOsSessionFactoryInterface;
use App\Models\Router;
use App\Services\RouterSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeRouterOsSession;
use Tests\Fakes\FakeRouterOsSessionFactory;
use Tests\TestCase;

class RouterReadOnlySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_read_only_sync_upserts_snapshots_marks_missing_and_never_stores_device_passwords(): void
    {
        config()->set('isp.integrations.mikrotik_driver', 'routeros');
        $session = new FakeRouterOsSession($this->responses());
        $factory = new FakeRouterOsSessionFactory($session);
        $this->app->instance(RouterOsSessionFactoryInterface::class, $factory);

        $router = Router::create([
            'name' => 'Router Integration Test', 'host' => '192.0.2.20', 'api_port' => 8728,
            'username' => 'readonly-test', 'password' => 'dummy-encrypted-value', 'enabled' => true,
        ]);
        $sync = $this->app->make(RouterSyncService::class);

        $first = $sync->sync($router, 'full');
        $this->assertSame('success', $first?->status);
        $this->assertDatabaseCount('router_interfaces', 2);
        $this->assertDatabaseCount('router_pppoe_active_sessions', 1);
        $this->assertDatabaseCount('router_ppp_secrets', 1);
        $this->assertDatabaseCount('router_hotspot_active_sessions', 1);
        $this->assertDatabaseCount('router_hotspot_users', 1);
        $this->assertDatabaseCount('router_dhcp_leases', 1);
        $this->assertDatabaseCount('router_queues', 1);
        $this->assertDatabaseCount('router_interface_metrics', 2);
        $this->assertDatabaseHas('routers', ['id' => $router->id, 'status' => 'online', 'identity' => 'LAB-READONLY']);

        $storedCredential = (string) DB::table('routers')->where('id', $router->id)->value('password');
        $this->assertNotSame('dummy-encrypted-value', $storedCredential);
        $this->assertStringNotContainsString('never-store-this', json_encode(DB::table('router_ppp_secrets')->get(), JSON_THROW_ON_ERROR));

        $this->travel(2)->seconds();
        $session->responses['/interface/print'] = [
            ['.id' => '*1', 'name' => 'ether1', 'type' => 'ether', 'running' => 'true', 'disabled' => 'false', 'dynamic' => 'false', 'rx-byte' => '1500', 'tx-byte' => '3000'],
        ];
        $second = $sync->sync($router->fresh(), 'full');

        $this->assertSame('success', $second?->status);
        $this->assertDatabaseCount('router_interfaces', 2);
        $this->assertDatabaseHas('router_interfaces', ['router_id' => $router->id, 'external_id' => '*1', 'rx_bytes' => 1500, 'missing_since' => null]);
        $this->assertNotNull(DB::table('router_interfaces')->where('external_id', '*2')->value('missing_since'));
        $this->assertDatabaseCount('router_interface_metrics', 3);
        $this->assertDatabaseCount('router_sync_runs', 2);
    }

    public function test_router_lock_skips_overlapping_sync(): void
    {
        $router = Router::create([
            'name' => 'Locked Router', 'host' => '192.0.2.21', 'username' => 'readonly-test',
            'password' => 'dummy-encrypted-value', 'enabled' => true,
        ]);
        $lock = Cache::lock("router:{$router->id}:sync", 50);
        $this->assertTrue($lock->get());

        try {
            $this->assertNull($this->app->make(RouterSyncService::class)->sync($router));
        } finally {
            $lock->release();
        }
    }

    private function responses(): array
    {
        return [
            '/system/identity/print' => [['name' => 'LAB-READONLY']],
            '/system/resource/print' => [['uptime' => '2d', 'version' => '7.20.8', 'architecture-name' => 'arm64', 'board-name' => 'RB-Test', 'cpu-count' => '2', 'cpu-load' => '10', 'total-memory' => '1000', 'free-memory' => '500', 'total-hdd-space' => '2000', 'free-hdd-space' => '1000']],
            '/system/routerboard/print' => [['model' => 'RB-Test', 'serial-number' => 'TEST-SERIAL']],
            '/system/health/print' => [['temperature' => '40', 'voltage' => '24']],
            '/interface/print' => [
                ['.id' => '*1', 'name' => 'ether1', 'type' => 'ether', 'running' => 'true', 'disabled' => 'false', 'dynamic' => 'false', 'rx-byte' => '1000', 'tx-byte' => '2000'],
                ['.id' => '*2', 'name' => 'pppoe-user-test', 'type' => 'pppoe-in', 'running' => 'false', 'disabled' => 'false', 'dynamic' => 'false', 'rx-byte' => '0', 'tx-byte' => '0'],
            ],
            '/ppp/active/print' => [['.id' => '*A', 'name' => 'subscriber-test', 'service' => 'pppoe', 'address' => '198.51.100.10', 'uptime' => '5m']],
            '/ppp/secret/print' => [['.id' => '*S', 'name' => 'subscriber-test', 'profile' => 'test-profile', 'disabled' => 'false', 'comment' => 'password=never-store-this']],
            '/ppp/profile/print' => [['.id' => '*P', 'name' => 'test-profile', 'rate-limit' => '10M/10M']],
            '/ip/hotspot/active/print' => [['.id' => '*H', 'user' => 'voucher-test', 'address' => '198.51.100.20', 'uptime' => '2m']],
            '/ip/hotspot/user/print' => [['.id' => '*U', 'name' => 'voucher-test', 'profile' => 'default', 'disabled' => 'false']],
            '/ip/hotspot/user/profile/print' => [['.id' => '*HP', 'name' => 'default']],
            '/ip/dhcp-server/lease/print' => [['.id' => '*D', 'address' => '198.51.100.30', 'status' => 'bound', 'dynamic' => 'true']],
            '/queue/simple/print' => [['.id' => '*Q', 'name' => 'queue-test', 'target' => '198.51.100.10/32', 'max-limit' => '10M/10M', 'disabled' => 'false']],
        ];
    }
}
