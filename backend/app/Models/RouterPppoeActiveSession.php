<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['router_id', 'external_id', 'username', 'service', 'caller_id', 'address', 'uptime', 'uptime_seconds', 'encoding', 'session_id', 'interface', 'profile', 'last_seen_at', 'missing_since'])]
class RouterPppoeActiveSession extends Model
{
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'missing_since' => 'datetime'];
    }
}
