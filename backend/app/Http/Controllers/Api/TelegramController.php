<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\SendTelegramTestJob;
use App\Models\TelegramSetting;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuditLogService $audit) {}

    public function index()
    {
        $settings = TelegramSetting::latest()->get()->map(fn (TelegramSetting $setting) => [
            'id' => $setting->id,
            'chat_id' => $setting->chat_id,
            'alert_types' => $setting->alert_types,
            'quiet_hours_start' => $setting->quiet_hours_start,
            'quiet_hours_end' => $setting->quiet_hours_end,
            'enabled' => $setting->enabled,
            'bot_token_configured' => filled($setting->getRawOriginal('bot_token')),
            'updated_at' => $setting->updated_at,
        ]);

        return $this->success($settings, 'Konfigurasi Telegram berhasil dimuat');
    }

    public function test(Request $request, TelegramSetting $telegramSetting)
    {
        $jobId = (string) Str::uuid();
        SendTelegramTestJob::dispatch($telegramSetting->id, $jobId);

        $this->audit->record($request, 'telegram', 'test_message_queued_mock', $telegramSetting, after: [
            'job_id' => $jobId,
            'driver' => 'mock',
            'dry_run' => true,
        ]);

        return $this->success([
            'job_id' => $jobId,
            'status' => 'queued',
            'driver' => 'mock',
            'dry_run' => true,
        ], 'Pesan uji Telegram mock telah dimasukkan ke antrean', status: 202);
    }
}
