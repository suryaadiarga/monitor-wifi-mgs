<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'interface_name', 'endpoint', 'address_pool', 'private_key', 'enabled'])]
#[Hidden(['private_key'])]
class VpnServer extends Model
{
    protected function casts(): array
    {
        return ['private_key' => 'encrypted', 'enabled' => 'boolean'];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(VpnClient::class);
    }
}
