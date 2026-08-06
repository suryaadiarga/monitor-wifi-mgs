<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['router_id', 'kind', 'external_id', 'name', 'status', 'disabled', 'properties', 'last_seen_at', 'missing_since'])]
class RouterInventoryItem extends Model
{
    protected function casts(): array
    {
        return ['disabled' => 'boolean', 'properties' => 'array', 'last_seen_at' => 'datetime', 'missing_since' => 'datetime'];
    }
}
