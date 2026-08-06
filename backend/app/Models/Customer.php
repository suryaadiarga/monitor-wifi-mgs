<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'customer_number', 'name', 'phone', 'email', 'address', 'installation_address', 'latitude', 'longitude', 'area_id', 'pop_id', 'odp_id', 'olt_id', 'router_id', 'package_id', 'reseller_id', 'pon', 'ont_identifier', 'pppoe_username', 'pppoe_password', 'hotspot_username', 'static_ip', 'status', 'installed_at', 'due_day', 'notes'])]
#[Hidden(['pppoe_password'])]
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['pppoe_password' => 'encrypted', 'installed_at' => 'date'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(InternetPackage::class, 'package_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
