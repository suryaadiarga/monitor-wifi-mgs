<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['router_id', 'external_id', 'username', 'server', 'address', 'mac_address', 'login_by', 'uptime', 'uptime_seconds', 'bytes_in', 'bytes_out', 'last_seen_at', 'missing_since'])]
class RouterHotspotActiveSession extends Model
{
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'missing_since' => 'datetime'];
    }
}
