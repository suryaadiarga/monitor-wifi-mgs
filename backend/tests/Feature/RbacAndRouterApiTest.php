<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RbacAndRouterApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_technician_can_view_but_cannot_delete_customer(): void
    {
        $technician = User::factory()->create();
        $technician->assignRole('Teknisi');
        Sanctum::actingAs($technician);
        $customer = Customer::create(['uuid' => (string) Str::uuid(), 'customer_number' => 'CUST-TEST-1', 'name' => 'Pelanggan Test', 'status' => 'aktif']);

        $this->getJson('/api/v1/customers')->assertOk();
        $this->deleteJson('/api/v1/customers/'.$customer->id)->assertForbidden();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'deleted_at' => null]);
    }

    public function test_admin_can_create_router_and_secret_is_encrypted_and_hidden(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/routers', [
            'name' => 'Router Uji', 'host' => '192.0.2.55', 'api_port' => 8728,
            'api_ssl_port' => 8729, 'use_ssl' => false, 'username' => 'api-user',
            'password' => 'credential-dummy-test', 'enabled' => true,
        ]);

        $response->assertCreated()->assertJsonMissingPath('data.password');
        $stored = DB::table('routers')->value('password');
        $this->assertNotSame('credential-dummy-test', $stored);
        $this->assertStringNotContainsString('credential-dummy-test', (string) DB::table('audit_logs')->latest('id')->value('after'));

        $routerId = $response->json('data.id');
        $this->postJson("/api/v1/routers/{$routerId}/test-connection")->assertOk()->assertJsonPath('data.success', true);
    }
}
