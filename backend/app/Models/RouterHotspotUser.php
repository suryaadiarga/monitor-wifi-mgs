<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['router_id', 'external_id', 'username', 'server', 'profile', 'mac_address', 'disabled', 'limit_uptime', 'limit_bytes_total', 'comment', 'last_seen_at', 'missing_since'])]
class RouterHotspotUser extends Model
{
    protected function casts(): array
    {
        return ['disabled' => 'boolean', 'last_seen_at' => 'datetime', 'missing_since' => 'datetime'];
    }
}
