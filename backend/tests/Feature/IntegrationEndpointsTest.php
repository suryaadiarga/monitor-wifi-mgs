<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramTestJob;
use App\Models\GenieAcsDevice;
use App\Models\Olt;
use App\Models\PppoeAccount;
use App\Models\TelegramSetting;
use App\Models\User;
use App\Models\VpnServer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntegrationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_integration_endpoints_are_wired_and_never_expose_saved_secrets(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);
        Queue::fake();

        $olt = Olt::create(['name' => 'OLT Endpoint Mock', 'vendor' => 'mock', 'host' => '198.51.100.40', 'protocol' => 'mock']);
        $device = GenieAcsDevice::create(['device_id' => 'endpoint-mock-1', 'serial_number' => 'MOCK-ENDPOINT-1', 'status' => 'online', 'parameters' => ['ssid' => 'Lab', 'password' => 'credential-dummy']]);
        $vpnServer = VpnServer::create(['name' => 'WG Endpoint Mock', 'type' => 'wireguard', 'endpoint' => 'vpn.example.test:51820', 'address_pool' => '10.89.0.0/29', 'enabled' => true]);
        $telegram = TelegramSetting::create(['bot_token' => 'dummy-token-never-production', 'chat_id' => 'demo-chat', 'enabled' => false]);
        PppoeAccount::create(['username' => 'endpoint@demo', 'password' => 'dummy-password-endpoint', 'profile' => 'demo']);
        DB::table('hotspot_profiles')->insert(['name' => 'endpoint-profile', 'created_at' => now(), 'updated_at' => now()]);

        $this->getJson('/api/v1/olts')->assertOk()->assertJsonMissing(['password']);
        $this->postJson("/api/v1/olts/{$olt->id}/connection-tests")->assertOk()->assertJsonPath('data.success', true);
        $this->getJson("/api/v1/olts/{$olt->id}/onts")->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/genieacs/devices')->assertOk()->assertJsonMissing(['credential-dummy']);
        $this->getJson("/api/v1/genieacs/devices/{$device->id}")->assertOk()->assertJsonPath('data.parameters.password', '[REDACTED]');
        $this->getJson('/api/v1/radius/health')->assertOk()->assertJsonPath('data.driver', 'dry-run');
        $this->postJson('/api/v1/radius/authentication-tests', ['username' => 'endpoint@demo', 'password' => 'never-return-this'])->assertOk()->assertJsonMissing(['never-return-this']);
        $this->postJson("/api/v1/vpn/servers/{$vpnServer->id}/clients/preview", ['name' => 'Teknisi Endpoint'])->assertOk()->assertJsonPath('data.dry_run', true)->assertJsonMissingPath('data.private_key');
        $this->postJson("/api/v1/vpn/servers/{$vpnServer->id}/clients", ['name' => 'Teknisi Endpoint', 'confirmed' => true])->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/vpn/servers')->assertOk()->assertJsonMissing(['private_key']);
        $this->getJson('/api/v1/vpn/clients')->assertOk()->assertJsonMissing(['preshared_key']);
        $this->getJson('/api/v1/telegram-settings')->assertOk()->assertJsonMissing(['dummy-token-never-production']);
        $this->postJson("/api/v1/telegram-settings/{$telegram->id}/tests")->assertStatus(202)->assertJsonMissing(['dummy-token-never-production']);
        Queue::assertPushed(SendTelegramTestJob::class);
        $this->getJson('/api/v1/pppoe/accounts')->assertOk()->assertJsonMissing(['dummy-password-endpoint']);
        $this->getJson('/api/v1/hotspot/users')->assertOk();
        $this->getJson('/api/v1/alerts')->assertOk();
        $this->getJson('/api/v1/audit-logs')->assertOk();
    }
}
