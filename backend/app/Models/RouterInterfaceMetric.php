<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['router_id', 'router_interface_id', 'rx_bytes', 'tx_bytes', 'rx_packets', 'tx_packets', 'recorded_at'])]
class RouterInterfaceMetric extends Model
{
    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function routerInterface(): BelongsTo
    {
        return $this->belongsTo(RouterInterface::class);
    }
}
