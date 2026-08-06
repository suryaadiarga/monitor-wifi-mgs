<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['router_id', 'external_id', 'name', 'local_address', 'remote_address', 'rate_limit', 'only_one', 'settings', 'last_seen_at', 'missing_since'])]
class RouterPppProfile extends Model
{
    protected function casts(): array
    {
        return ['settings' => 'array', 'last_seen_at' => 'datetime', 'missing_since' => 'datetime'];
    }
}
