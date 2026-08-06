<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nasname', 'shortname', 'type', 'ports', 'secret', 'server', 'community', 'description', 'enabled'])]
#[Hidden(['secret', 'community'])]
class RadiusNas extends Model
{
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'community' => 'encrypted',
            'enabled' => 'boolean',
        ];
    }
}
