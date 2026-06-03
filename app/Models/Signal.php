<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Signal extends Model
{
    protected $fillable = [
        'ticker', 'action', 'strength', 'confidence',
        'price_at_signal', 'target_price', 'stop_loss',
        'reasoning', 'indicators_snapshot', 'is_active',
        'expires_at', 'triggered_at',
    ];

    protected $casts = [
        'indicators_snapshot' => 'array',
        'is_active'           => 'boolean',
        'expires_at'          => 'datetime',
        'triggered_at'        => 'datetime',
        'confidence'          => 'integer',
        'price_at_signal'     => 'float',
        'target_price'        => 'float',
        'stop_loss'           => 'float',
    ];

    /** Badge colour for the action */
    public function actionColour(): string
    {
        return match ($this->action) {
            'BUY'   => 'green',
            'SELL'  => 'red',
            'WATCH' => 'yellow',
            default => 'gray',
        };
    }
}
