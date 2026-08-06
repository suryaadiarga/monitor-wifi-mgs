<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['router_id', 'cpu_percent', 'cpu_count', 'memory_percent', 'total_memory_bytes', 'free_memory_bytes', 'storage_percent', 'total_storage_bytes', 'free_storage_bytes', 'temperature_celsius', 'voltage_volts', 'uptime_seconds', 'download_bps', 'upload_bps', 'recorded_at'])]
class RouterMetric extends Model
{
    protected function casts(): array
    {
        return [
            'cpu_percent' => 'float',
            'cpu_count' => 'integer',
            'memory_percent' => 'float',
            'total_memory_bytes' => 'integer',
            'free_memory_bytes' => 'integer',
            'storage_percent' => 'float',
            'total_storage_bytes' => 'integer',
            'free_storage_bytes' => 'integer',
            'temperature_celsius' => 'float',
            'voltage_volts' => 'float',
            'uptime_seconds' => 'integer',
            'download_bps' => 'integer',
            'upload_bps' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }
}
