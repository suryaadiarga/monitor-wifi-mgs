<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['router_id', 'external_id', 'name', 'type', 'running', 'disabled', 'mtu', 'actual_mtu', 'mac_address', 'comment', 'dynamic', 'rx_bytes', 'tx_bytes', 'rx_packets', 'tx_packets', 'link_downs', 'last_link_up_at', 'last_link_down_at', 'rx_bps', 'tx_bps', 'last_polled_at', 'last_seen_at', 'missing_since'])]
class RouterInterface extends Model
{
    protected function casts(): array
    {
        return [
            'running' => 'boolean', 'disabled' => 'boolean', 'dynamic' => 'boolean',
            'last_link_up_at' => 'datetime', 'last_link_down_at' => 'datetime',
            'last_polled_at' => 'datetime', 'last_seen_at' => 'datetime', 'missing_since' => 'datetime',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(RouterInterfaceMetric::class);
    }
}
