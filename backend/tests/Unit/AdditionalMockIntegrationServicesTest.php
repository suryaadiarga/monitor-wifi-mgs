<?php

namespace Tests\Unit;

use App\Jobs\SendTelegramTestJob;
use App\Models\TelegramSetting;
use App\Services\RadiusDiagnosticService;
use App\Services\SensitiveDataSanitizer;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdditionalMockIntegrationServicesTest extends TestCase
{
    public function test_sensitive_parameters_are_redacted_including_generic_keys(): void
    {
        $sanitized = app(SensitiveDataSanitizer::class)->sanitizeArray([
            'serial_number' => 'ONT-001',
            'wifi_key' => 'rahasia-wifi',
            'nested' => ['api_token' => 'rahasia-token', 'community' => 'public'],
        ]);

        $this->assertSame('ONT-001', $sanitized['serial_number']);
        $this->assertSame('[REDACTED]', $sanitized['wifi_key']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['api_token']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['community']);
    }

    public function test_radius_authentication_is_dry_run_and_does_not_return_password(): void
    {
        $result = app(RadiusDiagnosticService::class)->dryRunAuthentication([
            'username' => 'pelanggan-001',
            'password' => 'jangan-dikembalikan',
            'simulate' => 'accept',
        ]);

        $this->assertTrue($result['dry_run']);
        $this->assertFalse($result['executed']);
        $this->assertSame('Access-Accept', $result['simulated_result']);
        $this->assertArrayNotHasKey('password', $result);
        $this->assertStringNotContainsString('jangan-dikembalikan', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_telegram_test_uses_mock_service_and_queue_payload_contains_no_token(): void
    {
        $setting = new TelegramSetting;
        $setting->id = 7;
        $setting->bot_token = 'dummy-token-for-test';
        $setting->chat_id = '123456';
        $setting->enabled = true;

        $result = app(TelegramService::class)->testMessage($setting);

        $this->assertSame('mock', $result['driver']);
        $this->assertTrue($result['dry_run']);
        $this->assertArrayNotHasKey('bot_token', $result);

        Queue::fake();
        SendTelegramTestJob::dispatch($setting->id, 'test-job-id');
        Queue::assertPushed(SendTelegramTestJob::class, function (SendTelegramTestJob $job) use ($setting): bool {
            return $job->settingId === $setting->id
                && $job->jobId === 'test-job-id'
                && ! property_exists($job, 'botToken');
        });
    }
}
