<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['area_id', 'pop_id', 'name', 'vendor', 'model', 'host', 'port', 'protocol', 'username', 'password', 'snmp_version', 'snmp_credential', 'location', 'status', 'last_seen_at', 'enabled'])]
#[Hidden(['password', 'snmp_credential'])]
class Olt extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['password' => 'encrypted', 'snmp_credential' => 'encrypted', 'enabled' => 'boolean', 'last_seen_at' => 'datetime'];
    }
}
