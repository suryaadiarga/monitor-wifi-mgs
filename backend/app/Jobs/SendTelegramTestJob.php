<?php

namespace App\Jobs;

use App\Models\TelegramSetting;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendTelegramTestJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 15;

    public function __construct(
        public readonly int $settingId,
        public readonly string $jobId,
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [5, 15];
    }

    public function handle(TelegramService $telegram): void
    {
        $setting = TelegramSetting::find($this->settingId);
        if (! $setting) {
            return;
        }

        $result = $telegram->testMessage($setting);
        Log::info('Telegram test mock selesai.', [
            'job_id' => $this->jobId,
            'setting_id' => $this->settingId,
            'driver' => $result['driver'],
            'dry_run' => true,
        ]);
    }
}
