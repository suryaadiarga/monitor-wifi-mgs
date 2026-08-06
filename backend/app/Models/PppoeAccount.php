<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['customer_id', 'router_id', 'username', 'password', 'profile', 'local_address', 'remote_address', 'auth_source', 'disabled', 'last_synced_at'])]
#[Hidden(['password'])]
class PppoeAccount extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['password' => 'encrypted', 'disabled' => 'boolean', 'last_synced_at' => 'datetime'];
    }
}
