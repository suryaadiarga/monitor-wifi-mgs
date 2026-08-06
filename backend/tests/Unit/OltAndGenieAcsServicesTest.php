<?php

namespace Tests\Unit;

use App\Models\GenieAcsDevice;
use App\Models\Olt;
use App\Services\GenieAcsService;
use App\Services\OltService;
use RuntimeException;
use Tests\TestCase;

class OltAndGenieAcsServicesTest extends TestCase
{
    public function test_olt_service_uses_mock_adapter_only_for_mock_driver_and_protocol(): void
    {
        config()->set('isp.integrations.olt_driver', 'mock');

        $olt = new Olt(['name' => 'OLT Mock', 'protocol' => 'mock']);
        $result = app(OltService::class)->testConnection($olt);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('mock', strtolower($result['message']));
    }

    public function test_olt_service_fails_closed_for_a_real_protocol(): void
    {
        config()->set('isp.integrations.olt_driver', 'mock');

        $this->expectException(RuntimeException::class);

        app(OltService::class)->testConnection(new Olt([
            'name' => 'OLT Nyata',
            'protocol' => 'ssh',
        ]));
    }

    public function test_olt_service_fails_closed_for_a_real_global_driver(): void
    {
        config()->set('isp.integrations.olt_driver', 'huawei');

        $this->expectException(RuntimeException::class);

        app(OltService::class)->testConnection(new Olt([
            'name' => 'OLT Mock',
            'protocol' => 'mock',
        ]));
    }

    public function test_genieacs_device_parameters_are_redacted_recursively(): void
    {
        $device = new GenieAcsDevice([
            'device_id' => 'mock-device-1',
            'parameters' => [
                'username' => 'subscriber',
                'password' => 'credential-dummy',
                'api_key' => 'api-key-dummy',
                'wifi' => [
                    'preSharedKey' => 'wifi-dummy',
                    'snmp_community' => 'public',
                ],
            ],
        ]);

        $parameters = app(GenieAcsService::class)->device($device)['parameters'];

        $this->assertSame('subscriber', $parameters['username']);
        $this->assertSame('[REDACTED]', $parameters['password']);
        $this->assertSame('[REDACTED]', $parameters['api_key']);
        $this->assertSame('[REDACTED]', $parameters['wifi']['preSharedKey']);
        $this->assertSame('[REDACTED]', $parameters['wifi']['snmp_community']);
    }
}
