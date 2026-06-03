<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = [
        'ticker','name','logo','exchange','currency','country','industry','sector',
        'market_cap','shares_outstanding','ipo_date','weburl','phone',
        'pe_ttm','eps_ttm','revenue_ttm','beta','div_yield','pb_ratio','ps_ratio',
        'roe','roi','current_ratio','debt_equity','week52_high','week52_low',
        'avg_volume_10d','avg_volume_3m',
        'analyst_buy','analyst_hold','analyst_sell',
        'price_target_high','price_target_low','price_target_mean','price_target_median',
        'next_earnings_date',
    ];

    protected $casts = [
        'ipo_date'           => 'date',
        'next_earnings_date' => 'date',
        'market_cap'         => 'float',
        'beta'               => 'float',
        'pe_ttm'             => 'float',
        'eps_ttm'            => 'float',
        'div_yield'          => 'float',
        'week52_high'        => 'float',
        'week52_low'         => 'float',
    ];

    public function watchlistEntry()
    {
        return $this->belongsTo(Watchlist::class, 'ticker', 'ticker');
    }

    public function analystConsensus(): string
    {
        $buy  = $this->analyst_buy  ?? 0;
        $hold = $this->analyst_hold ?? 0;
        $sell = $this->analyst_sell ?? 0;
        $total = $buy + $hold + $sell;
        if ($total === 0) return 'N/A';
        if ($buy / $total >= 0.6) return 'Strong Buy';
        if ($buy / $total >= 0.4) return 'Buy';
        if ($sell / $total >= 0.5) return 'Sell';
        return 'Hold';
    }

    public function marketCapFormatted(): string
    {
        $cap = $this->market_cap;
        if (!$cap) return 'N/A';
        if ($cap >= 1000) return '$' . round($cap / 1000, 2) . 'B';
        return '$' . round($cap, 1) . 'M';
    }

    public function upside(float $currentPrice): ?float
    {
        if (!$this->price_target_mean || $currentPrice <= 0) return null;
        return round(($this->price_target_mean - $currentPrice) / $currentPrice * 100, 1);
    }
}
