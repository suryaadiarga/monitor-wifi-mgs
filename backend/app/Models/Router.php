<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['area_id', 'pop_id', 'name', 'host', 'api_port', 'api_ssl_port', 'use_ssl', 'verify_tls', 'ca_certificate_path', 'certificate_fingerprint', 'username', 'password', 'location', 'identity', 'model', 'serial_number', 'routeros_version', 'architecture_name', 'board_name', 'uptime_seconds', 'status', 'connection_latency_ms', 'last_seen_at', 'last_checked_at', 'last_connected_at', 'last_successful_sync_at', 'last_error', 'capabilities', 'maintenance_mode', 'enabled', 'notes'])]
#[Hidden(['password'])]
class Router extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'use_ssl' => 'boolean',
            'verify_tls' => 'boolean',
            'maintenance_mode' => 'boolean',
            'enabled' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_connected_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
            'capabilities' => 'array',
        ];
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(RouterMetric::class);
    }

    public function interfaces(): HasMany
    {
        return $this->hasMany(RouterInterface::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(RouterSyncRun::class);
    }

    public function pppoeActiveSessions(): HasMany
    {
        return $this->hasMany(RouterPppoeActiveSession::class);
    }

    public function pppSecrets(): HasMany
    {
        return $this->hasMany(RouterPppSecret::class);
    }

    public function pppProfiles(): HasMany
    {
        return $this->hasMany(RouterPppProfile::class);
    }

    public function hotspotActiveSessions(): HasMany
    {
        return $this->hasMany(RouterHotspotActiveSession::class);
    }

    public function hotspotUsers(): HasMany
    {
        return $this->hasMany(RouterHotspotUser::class);
    }

    public function hotspotProfiles(): HasMany
    {
        return $this->hasMany(RouterHotspotProfile::class);
    }

    public function dhcpLeases(): HasMany
    {
        return $this->hasMany(RouterDhcpLease::class);
    }

    public function queues(): HasMany
    {
        return $this->hasMany(RouterQueue::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(RouterInventoryItem::class);
    }
}
