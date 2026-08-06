<?php

namespace App\Console\Commands;

use App\Services\SystemHealthService;
use Illuminate\Console\Command;

class SystemHealthCommand extends Command
{
    protected $signature = 'isp:health';

    protected $description = 'Memeriksa database, Redis, worker, scheduler, dan freshness sinkronisasi router';

    public function handle(SystemHealthService $health): int
    {
        $result = $health->check();
        $this->table(
            ['Check', 'Status'],
            collect($result['checks'])->map(fn (array $check, string $name): array => [$name, $check['status']])->values()->all(),
        );
        $this->line('time='.$result['time']);

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
