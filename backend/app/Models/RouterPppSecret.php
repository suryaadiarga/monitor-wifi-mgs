<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['router_id', 'external_id', 'username', 'service', 'profile', 'local_address', 'remote_address', 'disabled', 'comment', 'last_seen_at', 'missing_since'])]
class RouterPppSecret extends Model
{
    protected function casts(): array
    {
        return ['disabled' => 'boolean', 'last_seen_at' => 'datetime', 'missing_since' => 'datetime'];
    }
}
