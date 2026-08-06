<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['router_id', 'correlation_id', 'scope', 'status', 'capabilities', 'counts', 'started_at', 'finished_at', 'duration_ms', 'error'])]
class RouterSyncRun extends Model
{
    protected function casts(): array
    {
        return ['capabilities' => 'array', 'counts' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
