<?php

namespace Tests\Feature;

use App\Models\VpnClient;
use App\Models\VpnServer;
use App\Services\WireGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdditionalMockIntegrationServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_wireguard_mock_preview_and_one_time_provision_encrypt_secrets_at_rest(): void
    {
        $server = VpnServer::create([
            'name' => 'WireGuard Mock',
            'type' => 'wireguard',
            'interface_name' => 'wg-mock',
            'endpoint' => 'vpn.example.invalid:51820',
            'address_pool' => '10.88.0.0/29',
            'enabled' => true,
        ]);

        $service = app(WireGuardService::class);
        $preview = $service->previewClient($server, ['name' => 'teknisi-001']);
        $result = $service->provisionClient($server, ['name' => 'teknisi-001']);

        $this->assertTrue($preview['dry_run']);
        $this->assertArrayNotHasKey('private_key', $preview);
        $this->assertTrue($result['provisioning']['shown_once']);
        $this->assertNotEmpty($result['provisioning']['private_key']);
        $this->assertStringContainsString('MOCK ONLY', $result['provisioning']['configuration']);

        $client = VpnClient::findOrFail($result['client']['id']);
        $this->assertArrayNotHasKey('private_key', $client->toArray());
        $this->assertArrayNotHasKey('preshared_key', $client->toArray());
        $this->assertNotSame(
            $result['provisioning']['private_key'],
            DB::table('vpn_clients')->where('id', $client->id)->value('private_key'),
        );
    }
}
