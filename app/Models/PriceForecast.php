<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceForecast extends Model
{
    protected $fillable = [
        'ticker',
        'model_name',
        'model_version',
        'model_role',
        'horizon_days',
        'current_price',
        'predicted_price',
        'expected_return_pct',
        'confidence',
        'quality_score',
        'volatility_regime',
        'recommendation',
        'entry_price',
        'take_profit_price',
        'stop_loss_price',
        'realized_price',
        'realized_return_pct',
        'abs_error_pct',
        'direction_hit',
        'forecast_for',
        'generated_at',
        'evaluated_at',
        'features',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'horizon_days' => 'integer',
        'current_price' => 'float',
        'predicted_price' => 'float',
        'expected_return_pct' => 'float',
        'confidence' => 'integer',
        'quality_score' => 'integer',
        'entry_price' => 'float',
        'take_profit_price' => 'float',
        'stop_loss_price' => 'float',
        'realized_price' => 'float',
        'realized_return_pct' => 'float',
        'abs_error_pct' => 'float',
        'direction_hit' => 'boolean',
        'forecast_for' => 'date',
        'generated_at' => 'datetime',
        'evaluated_at' => 'datetime',
        'features' => 'array',
        'is_active' => 'boolean',
    ];
}
