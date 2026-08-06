<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'download_kbps', 'upload_kbps', 'burst_limit', 'burst_threshold', 'burst_time', 'priority', 'price', 'mikrotik_profile', 'radius_group', 'validity_days', 'enabled'])]
class InternetPackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'packages';

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'price' => 'decimal:2'];
    }
}
