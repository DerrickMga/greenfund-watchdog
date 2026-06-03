<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchdogAlert extends Model
{
    protected $fillable = [
        'ticker', 'type', 'severity', 'title', 'message',
        'trigger_value', 'threshold_value', 'acknowledged', 'alerted_at',
    ];

    protected $casts = [
        'acknowledged'    => 'boolean',
        'alerted_at'      => 'datetime',
        'trigger_value'   => 'float',
        'threshold_value' => 'float',
    ];

    public function scopeUnacknowledged($query)
    {
        return $query->where('acknowledged', false);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'CRITICAL');
    }
}
