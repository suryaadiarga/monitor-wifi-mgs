<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bot_token', 'chat_id', 'alert_types', 'quiet_hours_start', 'quiet_hours_end', 'enabled'])]
#[Hidden(['bot_token'])]
class TelegramSetting extends Model
{
    protected function casts(): array
    {
        return [
            'bot_token' => 'encrypted',
            'alert_types' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
