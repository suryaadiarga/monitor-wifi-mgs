<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['router_id', 'external_id', 'name', 'rate_limit', 'shared_users', 'settings', 'last_seen_at', 'missing_since'])]
class RouterHotspotProfile extends Model
{
    protected function casts(): array
    {
        return ['settings' => 'array', 'last_seen_at' => 'datetime', 'missing_since' => 'datetime'];
    }
}
