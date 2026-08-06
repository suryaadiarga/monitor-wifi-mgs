<?php

namespace Tests\Feature;

use App\Jobs\PollRouterJob;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RouterCommandsAndJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_interactive_add_command_encrypts_hidden_password_and_updates_same_host(): void
    {
        config()->set('isp.integrations.mikrotik_driver', 'mock');

        $this->artisan('isp:router:add', [
            '--name' => 'Command Router', '--host' => '192.0.2.30', '--port' => 8728,
            '--username' => 'readonly-command',
        ])->expectsQuestion('Password API', 'dummy-hidden-value')->assertSuccessful();

        $stored = (string) DB::table('routers')->value('password');
        $this->assertNotSame('dummy-hidden-value', $stored);
        $this->assertStringNotContainsString('dummy-hidden-value', (string) DB::table('audit_logs')->latest('id')->value('after'));

        $this->artisan('isp:router:add', [
            '--name' => 'Command Router Updated', '--host' => '192.0.2.30', '--port' => 8728,
            '--username' => 'readonly-command',
        ])->expectsQuestion('Password API', 'dummy-hidden-new-value')->assertSuccessful();

        $this->assertSame(1, Router::count());
        $this->assertDatabaseHas('routers', ['host' => '192.0.2.30', 'name' => 'Command Router Updated']);
    }

    public function test_poll_job_uses_single_worker_queue_retry_policy_and_unique_scope_key(): void
    {
        Queue::fake();
        PollRouterJob::dispatch(42, 'active');

        Queue::assertPushedOn('router-poll', PollRouterJob::class);
        Queue::assertPushed(PollRouterJob::class, function (PollRouterJob $job) {
            return $job->tries === 3
                && $job->timeout === 45
                && $job->failOnTimeout === true
                && $job->backoff() === [30, 120]
                && $job->uniqueId() === '42:active';
        });
    }
}
