<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockSnapshot extends Model
{
    protected $fillable = [
        'ticker', 'price', 'open', 'high', 'low', 'prev_close',
        'change', 'change_percent', 'volume',
        'rsi_14', 'macd', 'macd_signal', 'macd_hist',
        'ema_9', 'ema_21', 'sma_50', 'bb_upper', 'bb_lower',
        'atr', 'stoch_k', 'vwap', 'momentum_1m', 'momentum_5m',
        'source', 'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'price'        => 'float',
        'change'       => 'float',
        'change_percent' => 'float',
        'rsi_14'       => 'float',
        'macd'         => 'float',
        'macd_signal'  => 'float',
        'macd_hist'    => 'float',
    ];

    public function watchlistEntry()
    {
        return $this->belongsTo(Watchlist::class, 'ticker', 'ticker');
    }

    /** Return the last N snapshots for a ticker (most recent first) */
    public static function recentFor(string $ticker, int $limit = 60)
    {
        return static::where('ticker', $ticker)
            ->orderByDesc('captured_at')
            ->limit($limit)
            ->get();
    }
}
