<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['router_id', 'external_id', 'address', 'mac_address', 'host_name', 'server', 'status', 'dynamic', 'disabled', 'expires_after', 'comment', 'last_seen_at', 'missing_since'])]
class RouterDhcpLease extends Model
{
    protected function casts(): array
    {
        return ['dynamic' => 'boolean', 'disabled' => 'boolean', 'last_seen_at' => 'datetime', 'missing_since' => 'datetime'];
    }
}
