<?php

namespace Tests\Unit;

use App\Adapters\Router\MockRouterAdapter;
use App\Adapters\Router\RouterOsAdapter;
use App\Exceptions\MikroTikAuthenticationException;
use App\Exceptions\MikroTikTimeoutException;
use App\Exceptions\MikroTikTrapException;
use App\Models\Router;
use App\Services\MikroTikService;
use App\Services\SensitiveDataSanitizer;
use Tests\Fakes\FakeRouterOsSession;
use Tests\Fakes\FakeRouterOsSessionFactory;
use Tests\TestCase;

class RouterOsAdapterTest extends TestCase
{
    public function test_connection_reads_only_required_properties_and_closes_session(): void
    {
        $session = new FakeRouterOsSession($this->systemResponses());
        $adapter = new RouterOsAdapter(new FakeRouterOsSessionFactory($session));
        $result = $adapter->testConnection($this->router());

        $this->assertTrue($result['success']);
        $this->assertSame('LAB-READONLY', $result['identity']);
        $this->assertSame('7.20.8', $result['routeros_version']);
        $this->assertSame(93784, $result['uptime_seconds']);
        $this->assertTrue($session->closed);
        $this->assertSame(['/system/identity/print', '/system/resource/print', '/system/routerboard/print', '/system/health/print'], array_column($session->calls, 'command'));
        $this->assertStringNotContainsString('password', json_encode($session->calls, JSON_THROW_ON_ERROR));
    }

    public function test_optional_health_trap_does_not_fail_connection_but_resource_trap_does(): void
    {
        $responses = $this->systemResponses();
        $responses['/system/health/print'] = new MikroTikTrapException('not supported');
        $adapter = new RouterOsAdapter(new FakeRouterOsSessionFactory(new FakeRouterOsSession($responses)));
        $this->assertTrue($adapter->testConnection($this->router())['success']);

        $responses['/system/resource/print'] = new MikroTikTrapException('permission denied');
        $adapter = new RouterOsAdapter(new FakeRouterOsSessionFactory(new FakeRouterOsSession($responses)));
        $this->expectException(MikroTikTrapException::class);
        $adapter->testConnection($this->router());
    }

    public function test_service_classifies_authentication_timeout_and_trap_without_sensitive_details(): void
    {
        $cases = [
            [new MikroTikAuthenticationException('password=do-not-leak'), 'authentication_failed', true, false],
            [new MikroTikTimeoutException('secret=do-not-leak'), 'connection_timeout', false, null],
            [new MikroTikTrapException('credential=do-not-leak'), 'routeros_trap', true, true],
        ];

        foreach ($cases as [$exception, $code, $tcp, $auth]) {
            $factory = new FakeRouterOsSessionFactory(new FakeRouterOsSession);
            $factory->connectException = $exception;
            $service = new MikroTikService(new MockRouterAdapter, new RouterOsAdapter($factory), new SensitiveDataSanitizer);
            config()->set('isp.integrations.mikrotik_driver', 'routeros');
            $result = $service->testConnection($this->router());

            $this->assertFalse($result['success']);
            $this->assertSame($code, $result['error_code']);
            $this->assertSame($tcp, $result['tcp_ok']);
            $this->assertSame($auth, $result['auth_ok']);
            $this->assertStringNotContainsString('do-not-leak', $result['message']);
        }
    }

    private function router(): Router
    {
        return new Router([
            'name' => 'Router Test', 'host' => '192.0.2.10', 'api_port' => 8728,
            'username' => 'readonly', 'password' => 'dummy-unit-value', 'use_ssl' => false,
        ]);
    }

    private function systemResponses(): array
    {
        return [
            '/system/identity/print' => [['name' => 'LAB-READONLY']],
            '/system/resource/print' => [[
                'uptime' => '1d2h3m4s', 'version' => '7.20.8', 'architecture-name' => 'arm64',
                'board-name' => 'RB-Test', 'cpu-count' => '4', 'cpu-load' => '12',
                'total-memory' => '1073741824', 'free-memory' => '536870912',
                'total-hdd-space' => '134217728', 'free-hdd-space' => '67108864',
            ]],
            '/system/routerboard/print' => [['model' => 'RB-Test', 'serial-number' => 'TEST-SERIAL']],
            '/system/health/print' => [['temperature' => '42.5C', 'voltage' => '24.1V']],
        ];
    }
}
