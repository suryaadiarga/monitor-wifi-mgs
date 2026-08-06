<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['vpn_server_id', 'user_id', 'name', 'assigned_ip', 'public_key', 'private_key', 'preshared_key', 'expires_at', 'last_handshake_at', 'transfer_rx', 'transfer_tx', 'enabled'])]
#[Hidden(['private_key', 'preshared_key'])]
class VpnClient extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'private_key' => 'encrypted',
            'preshared_key' => 'encrypted',
            'expires_at' => 'datetime',
            'last_handshake_at' => 'datetime',
            'enabled' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(VpnServer::class, 'vpn_server_id');
    }
}
