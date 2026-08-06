<?php

namespace Tests\Feature;

use App\Jobs\PollRouterJob;
use App\Models\Router;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAndPollingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_mock_polling_persists_metric_and_dashboard_uses_it(): void
    {
        $router = Router::create(['name' => 'Mock Router', 'host' => '192.0.2.99', 'username' => 'mock', 'password' => 'dummy-secret-for-test', 'status' => 'unknown']);
        PollRouterJob::dispatchSync($router->id);

        $this->assertDatabaseHas('router_metrics', ['router_id' => $router->id]);
        $this->assertDatabaseHas('routers', ['id' => $router->id, 'status' => 'online']);

        $user = User::factory()->create();
        $user->assignRole('NOC');
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.counts.routers_total', 1)->assertJsonPath('data.counts.routers_online', 1)->assertJsonPath('data.traffic.download_bps', fn ($value) => $value > 0);
    }
}
