<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('isp:dispatch-router-polls')->everyMinute()->withoutOverlapping(5);
Schedule::call(fn () => Cache::put('health:scheduler:last_seen', now()->timestamp, now()->addMinutes(3)))
    ->name('health:scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping(5);
Schedule::command('isp:router:prune --chunk=500')->dailyAt('04:10')->onOneServer()->withoutOverlapping(120);
Schedule::command('queue:prune-failed --hours=720')->dailyAt('04:20')->onOneServer()->withoutOverlapping(120);
