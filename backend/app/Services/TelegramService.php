<?php

namespace App\Services;

use App\Models\TelegramSetting;

class TelegramService
{
    /** @return array<string, mixed> */
    public function testMessage(TelegramSetting $setting): array
    {
        return [
            'success' => true,
            'driver' => 'mock',
            'dry_run' => true,
            'setting_id' => $setting->id,
            'enabled' => $setting->enabled,
            'chat_id_configured' => filled($setting->chat_id),
            'bot_token_configured' => filled($setting->getRawOriginal('bot_token')),
            'message' => 'Pesan uji disimulasikan; Telegram Bot API tidak dipanggil.',
            'processed_at' => now()->toIso8601String(),
        ];
    }
}
