<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['router_id', 'kind', 'external_id', 'name', 'target', 'parent', 'queue_type', 'max_limit', 'rate', 'disabled', 'comment', 'settings', 'last_seen_at', 'missing_since'])]
class RouterQueue extends Model
{
    protected function casts(): array
    {
        return ['disabled' => 'boolean', 'settings' => 'array', 'last_seen_at' => 'datetime', 'missing_since' => 'datetime'];
    }
}
