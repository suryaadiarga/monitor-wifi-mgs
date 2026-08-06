<?php

namespace Tests\Unit;

use App\Services\AuditLogService;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    public function test_sensitive_values_are_recursively_redacted(): void
    {
        $sanitized = app(AuditLogService::class)->sanitize([
            'username' => 'operator',
            'password' => 'rahasia',
            'nested' => ['bot_token' => 'token-rahasia', 'snmp_community' => 'public'],
        ]);

        $this->assertSame('operator', $sanitized['username']);
        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['bot_token']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['snmp_community']);
    }
}
