<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['olt_id', 'olt_port_id', 'customer_id', 'serial_number', 'onu_id', 'status', 'optical_rx', 'optical_tx', 'distance_meters', 'description', 'vlan', 'last_seen_at'])]
class OntDevice extends Model
{
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
