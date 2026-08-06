<?php

namespace App\Console\Commands;

use App\Jobs\PollRouterJob;
use App\Models\Router;
use App\Models\RouterSyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchRouterPolls extends Command
{
    protected $signature = 'isp:dispatch-router-polls';

    protected $description = 'Mengirim job polling untuk setiap router aktif';

    public function handle(): int
    {
        $intervals = [
            'resources' => (int) config('isp.polling.router_resources_seconds', 300),
            'active' => (int) config('isp.polling.router_active_seconds', 180),
            'network' => (int) config('isp.polling.router_network_seconds', 600),
            'config' => (int) config('isp.polling.router_config_seconds', 1800),
        ];

        Router::query()->where('enabled', true)->where('maintenance_mode', false)->pluck('id')->each(function (int $id) use ($intervals) {
            foreach ($intervals as $scope => $seconds) {
                $alreadyRunning = RouterSyncRun::query()->where('router_id', $id)->where('scope', $scope)
                    ->where('status', 'running')->where('started_at', '>=', now()->subMinute())->exists();
                $lastSuccess = RouterSyncRun::query()->where('router_id', $id)->where('scope', $scope)
                    ->where('status', 'success')->max('finished_at');

                if (! $alreadyRunning && (! $lastSuccess || Carbon::parse($lastSuccess)->lte(now()->subSeconds($seconds)))) {
                    PollRouterJob::dispatch($id, $scope);
                }
            }
        });
        $this->info('Job polling router berhasil dikirim.');

        return self::SUCCESS;
    }
}
