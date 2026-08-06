<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['device_id', 'serial_number', 'manufacturer', 'product_class', 'software_version', 'wan_ip', 'status', 'last_inform_at', 'parameters'])]
class GenieAcsDevice extends Model
{
    protected $table = 'genieacs_devices';

    protected function casts(): array
    {
        return [
            'last_inform_at' => 'datetime',
            'parameters' => 'array',
        ];
    }
}
