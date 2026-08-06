<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_login_read_profile_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'Password-Aman-123!']);
        $user->assignRole('Super Admin');

        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password-Aman-123!', 'device_name' => 'phpunit']);
        $login->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', 'Login berhasil')->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['roles', 'permissions']]]);

        $token = $login->json('data.token');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', $user->email);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('audit_logs', ['module' => 'authentication', 'action' => 'logout', 'status' => 'success']);
    }

    public function test_failed_login_is_audited_without_password(): void
    {
        User::factory()->create(['email' => 'user@example.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'user@example.test', 'password' => 'password-salah'])->assertUnprocessable();

        $this->assertDatabaseHas('audit_logs', ['module' => 'authentication', 'action' => 'login', 'status' => 'failed']);
        $this->assertStringNotContainsString('password-salah', (string) AuditLog::first()?->toJson());
    }
}
