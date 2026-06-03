<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Watchlist extends Model
{
    protected $table = 'watchlist';

    protected $fillable = [
        'ticker', 'company_name', 'exchange', 'sector',
        'is_pinned', 'active', 'alert_price_above', 'alert_price_below', 'notes',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'active'    => 'boolean',
        'alert_price_above' => 'decimal:4',
        'alert_price_below' => 'decimal:4',
    ];

    public function snapshots()
    {
        return $this->hasMany(StockSnapshot::class, 'ticker', 'ticker');
    }

    public function latestSnapshot()
    {
        return $this->hasOne(StockSnapshot::class, 'ticker', 'ticker')
                    ->latestOfMany('captured_at');
    }

    public function signals()
    {
        return $this->hasMany(Signal::class, 'ticker', 'ticker');
    }

    public function activeSignal()
    {
        return $this->hasOne(Signal::class, 'ticker', 'ticker')
                    ->where('is_active', true)
                    ->latestOfMany('triggered_at');
    }

    public function forecasts()
    {
        return $this->hasMany(PriceForecast::class, 'ticker', 'ticker');
    }

    public function latestForecast()
    {
        return $this->hasOne(PriceForecast::class, 'ticker', 'ticker')
            ->where('model_role', 'champion')
            ->where('is_active', true)
            ->latestOfMany('generated_at');
    }

    public function latestChallengerForecast()
    {
        return $this->hasOne(PriceForecast::class, 'ticker', 'ticker')
            ->where('model_role', 'challenger')
            ->where('is_active', true)
            ->latestOfMany('generated_at');
    }
}
