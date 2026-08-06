<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['alert_rule_id', 'severity', 'status', 'source_type', 'source_id', 'title', 'message', 'fingerprint', 'started_at', 'resolved_at'])]
class Alert extends Model
{
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
