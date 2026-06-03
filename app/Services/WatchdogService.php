<?php

namespace App\Services;

use App\Models\Watchlist;
use App\Models\StockSnapshot;
use App\Models\Signal;
use App\Models\WatchdogAlert;
use App\Models\CompanyProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WatchdogService
 *
 * Orchestrates per-minute polling → snapshot storage → indicator calculation
 * → signal generation → alert detection for every active ticker.
 */
class WatchdogService
{
    private const TICKER_ALIASES = [
        'SQ' => 'XYZ',
        'TOGI' => 'TE',
    ];

    public function __construct(
        private FinnhubService   $finnhub,
        private TechnicalAnalysis $ta,
        private SignalEngine      $signalEngine,
    ) {}

    /**
     * Run one full watchdog cycle for all active tickers.
     * Called every minute by the scheduler.
     */
    public function runCycle(): array
    {
        $tickers = Watchlist::where('active', true)->get();
        $results = [];

        foreach ($tickers as $entry) {
            try {
                $result = $this->processTicker($entry->ticker);
                $results[$entry->ticker] = $result;
            } catch (\Throwable $e) {
                Log::error("Watchdog error for {$entry->ticker}: " . $e->getMessage());
                $results[$entry->ticker] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Full processing pipeline for a single ticker.
     */
    public function processTicker(string $ticker): array
    {
        $ticker = strtoupper($ticker);
        $resolvedTicker = $this->resolveTickerAlias($ticker);
        if ($resolvedTicker !== $ticker) {
            $this->remapTickerReferences($ticker, $resolvedTicker);
            $ticker = $resolvedTicker;
        }

        // 1. Fetch real-time quote
        $quote = $this->finnhub->quote($ticker);
        if (!$quote || ($quote['c'] ?? 0) <= 0) {
            return ['status' => 'no_data', 'ticker' => $ticker];
        }

        $price = (float) $quote['c'];

        // 2. Fetch candles for indicator calculation with fallbacks.
        $to      = time();
        $from    = $to - (60 * 120); // 120 candles = 2 hours
        $candles = $this->finnhub->candles($ticker, '1', $from, $to);

        if (!$candles || ($candles['s'] ?? '') !== 'ok' || empty($candles['c'])) {
            $candles = $this->finnhub->candles($ticker, '5', $to - (60 * 60 * 24 * 5), $to);
        }
        if (!$candles || ($candles['s'] ?? '') !== 'ok' || empty($candles['c'])) {
            $candles = $this->finnhub->dailyCandles($ticker, 365);
        }

        $closes  = [];
        $highs   = [];
        $lows    = [];
        $volumes = [];

        if ($candles && ($candles['s'] ?? '') === 'ok') {
            $closes  = array_map('floatval', $candles['c'] ?? []);
            $highs   = array_map('floatval', $candles['h'] ?? []);
            $lows    = array_map('floatval', $candles['l'] ?? []);
            $volumes = array_map('intval',   $candles['v'] ?? []);
        }

        // If upstream candle endpoints are restricted, reuse local snapshot history.
        if (count($closes) < 30) {
            $history = StockSnapshot::where('ticker', $ticker)
                ->orderByDesc('captured_at')
                ->limit(240)
                ->get()
                ->reverse()
                ->values();

            if ($history->isNotEmpty()) {
                $closes  = $history->pluck('price')->map(fn($v) => (float) $v)->all();
                $highs   = $history->pluck('high')->map(fn($v) => $v !== null ? (float) $v : 0.0)->all();
                $lows    = $history->pluck('low')->map(fn($v) => $v !== null ? (float) $v : 0.0)->all();
                $volumes = $history->pluck('volume')->map(fn($v) => (int) ($v ?? 0))->all();
            }
        }

        $currentVolume = !empty($volumes) ? (int) end($volumes) : 0;

        // Append current price as the latest data point
        $closes[]  = $price;
        $highs[]   = (float) ($quote['h'] ?? $price);
        $lows[]    = (float) ($quote['l'] ?? $price);
        $volumes[] = 0;

        // 3. Calculate all technical indicators
        $indicators = $this->ta->calculateAll($closes, $highs, $lows, $volumes);

        // 4. Get previous snapshot for comparison / momentum
        $prevSnapshot = StockSnapshot::where('ticker', $ticker)
            ->orderByDesc('captured_at')
            ->first();

        $mom1 = null;
        if ($prevSnapshot) {
            $prevPrice = (float) $prevSnapshot->price;
            $mom1 = $prevPrice > 0 ? round((($price - $prevPrice) / $prevPrice) * 100, 4) : null;
        }

        // 5. Build and store snapshot
        $macdData = $indicators['macd'] ?? null;
        $bbData   = $indicators['bb']   ?? null;
        $stoch    = $indicators['stochastic'] ?? null;

        $snapshot = StockSnapshot::create([
            'ticker'         => $ticker,
            'price'          => $price,
            'open'           => (float) ($quote['o'] ?? 0) ?: null,
            'high'           => (float) ($quote['h'] ?? 0) ?: null,
            'low'            => (float) ($quote['l'] ?? 0) ?: null,
            'prev_close'     => (float) ($quote['pc'] ?? 0) ?: null,
            'change'         => (float) ($quote['d'] ?? 0),
            'change_percent' => (float) ($quote['dp'] ?? 0),
            'volume'         => $currentVolume,
            'rsi_14'         => $indicators['rsi_14'],
            'macd'           => $macdData['macd']      ?? null,
            'macd_signal'    => $macdData['signal']    ?? null,
            'macd_hist'      => $macdData['histogram'] ?? null,
            'ema_9'          => $indicators['ema_9'],
            'ema_21'         => $indicators['ema_21'],
            'sma_50'         => $indicators['sma_50'],
            'bb_upper'       => $bbData['upper'] ?? null,
            'bb_lower'       => $bbData['lower'] ?? null,
            'atr'            => $indicators['atr'],
            'stoch_k'        => $stoch['k'] ?? null,
            'vwap'           => $indicators['vwap'],
            'momentum_1m'    => $mom1,
            'momentum_5m'    => $indicators['momentum_5'],
            'source'         => 'finnhub',
            'captured_at'    => now(),
        ]);

        // Keep fundamentals/profile data in sync for views and screener filters.
        $watchlistEntry = Watchlist::where('ticker', $ticker)->first();
        $rich           = $this->finnhub->richSnapshot($ticker);
        $priceTarget    = $this->finnhub->priceTarget($ticker) ?? [];
        $recs           = $this->finnhub->recommendations($ticker);
        $rec            = $recs[0] ?? [];
        $earnings       = $this->finnhub->earningsCalendar($ticker);

        $profile = CompanyProfile::updateOrCreate(
            ['ticker' => $ticker],
            [
                'name'                => $rich['name'] ?? $watchlistEntry?->company_name,
                'logo'                => $rich['logo'] ?? null,
                'exchange'            => $rich['exchange'] ?? $watchlistEntry?->exchange,
                'currency'            => $rich['currency'] ?? 'USD',
                'country'             => $rich['country'] ?? null,
                'industry'            => $rich['industry'] ?? null,
                'sector'              => $watchlistEntry?->sector ?? ($rich['industry'] ?? null),
                'market_cap'          => $rich['market_cap'] ?? null,
                'shares_outstanding'  => $rich['shares_out'] ?? null,
                'ipo_date'            => $rich['ipo_date'] ?? null,
                'weburl'              => $rich['weburl'] ?? null,
                'pe_ttm'              => $rich['pe_ttm'] ?? null,
                'eps_ttm'             => $rich['eps_ttm'] ?? null,
                'revenue_ttm'         => $rich['revenue_ttm'] ?? null,
                'beta'                => $rich['beta'] ?? null,
                'div_yield'           => $rich['div_yield'] ?? null,
                'pb_ratio'            => $rich['pb_ratio'] ?? null,
                'ps_ratio'            => $rich['ps_ratio'] ?? null,
                'roe'                 => $rich['roe'] ?? null,
                'roi'                 => $rich['roi'] ?? null,
                'current_ratio'       => $rich['current_ratio'] ?? null,
                'debt_equity'         => $rich['debt_equity'] ?? null,
                'week52_high'         => $rich['week52_high'] ?? null,
                'week52_low'          => $rich['week52_low'] ?? null,
                'avg_volume_10d'      => $rich['10d_avg_volume'] ?? null,
                'avg_volume_3m'       => $rich['3m_avg_volume'] ?? null,
                'analyst_buy'         => $rec['buy'] ?? null,
                'analyst_hold'        => $rec['hold'] ?? null,
                'analyst_sell'        => $rec['sell'] ?? null,
                'price_target_high'   => $priceTarget['targetHigh'] ?? null,
                'price_target_low'    => $priceTarget['targetLow'] ?? null,
                'price_target_mean'   => $priceTarget['targetMean'] ?? null,
                'price_target_median' => $priceTarget['targetMedian'] ?? null,
                'next_earnings_date'  => $earnings['date'] ?? null,
            ]
        );

        if ($watchlistEntry && !empty($rich['name'])) {
            $watchlistEntry->update([
                'company_name' => $rich['name'],
                'exchange'     => $rich['exchange'] ?? $watchlistEntry->exchange,
            ]);
        }

        // 6. Generate signal
        $signalData    = $this->signalEngine->evaluate($indicators, $price);

        // Only create a new signal if action changed from last active signal
        $lastSignal = Signal::where('ticker', $ticker)
            ->where('is_active', true)
            ->orderByDesc('triggered_at')
            ->first();

        $signalCreated = false;
        if (!$lastSignal || $lastSignal->action !== $signalData['action']) {
            // Deactivate old signal
            if ($lastSignal) {
                $lastSignal->update(['is_active' => false]);
            }

            Signal::create([
                'ticker'              => $ticker,
                'action'              => $signalData['action'],
                'strength'            => $signalData['strength'],
                'confidence'          => $signalData['confidence'],
                'price_at_signal'     => $price,
                'target_price'        => $signalData['target_price'],
                'stop_loss'           => $signalData['stop_loss'],
                'reasoning'           => $signalData['reasoning'],
                'indicators_snapshot' => $indicators,
                'is_active'           => true,
                'expires_at'          => now()->addMinutes(30),
                'triggered_at'        => now(),
            ]);
            $signalCreated = true;
        }

        // 7. Detect and store watchdog alerts
        $prevData = $prevSnapshot ? [
            'ticker'  => $ticker,
            'price'   => (float) $prevSnapshot->price,
            'rsi_14'  => (float) $prevSnapshot->rsi_14,
            'macd'    => $prevSnapshot->macd !== null ? [
                'crossover'  => $prevSnapshot->macd_hist >= 0 ? 'bullish' : 'bearish',
                'histogram'  => (float) $prevSnapshot->macd_hist,
            ] : null,
        ] : null;

        $currentData = array_merge($indicators, [
            'ticker'        => $ticker,
            'price'         => $price,
            'volume'        => $currentVolume,
            'avg_volume_3m' => $profile?->avg_volume_3m,
        ]);
        $alertDefs   = $this->signalEngine->detectAlerts(
            $currentData,
            $prevData ?? [],
            $price,
            $prevSnapshot ? (float) $prevSnapshot->price : 0.0
        );

        foreach ($alertDefs as $alertDef) {
            WatchdogAlert::create(array_merge($alertDef, [
                'ticker'     => $ticker,
                'alerted_at' => now(),
            ]));
        }

        return [
            'ticker'        => $ticker,
            'price'         => $price,
            'signal'        => $signalData['action'],
            'confidence'    => $signalData['confidence'],
            'signal_changed'=> $signalCreated,
            'alerts'        => count($alertDefs),
            'rsi'           => $indicators['rsi_14'],
            'macd_cross'    => $macdData['crossover'] ?? null,
        ];
    }

    /**
     * Purge snapshots older than X days to keep DB lean.
     */
    public function pruneOldSnapshots(int $days = 7): int
    {
        return StockSnapshot::where('captured_at', '<', now()->subDays($days))->delete();
    }

    private function resolveTickerAlias(string $ticker): string
    {
        return self::TICKER_ALIASES[$ticker] ?? $ticker;
    }

    private function remapTickerReferences(string $oldTicker, string $newTicker): void
    {
        $profile = $this->finnhub->companyProfile($newTicker) ?? [];

        DB::transaction(function () use ($oldTicker, $newTicker, $profile) {
            $targetExists = Watchlist::where('ticker', $newTicker)->exists();

            if ($targetExists) {
                Watchlist::where('ticker', $oldTicker)->update([
                    'active' => false,
                    'notes'  => "Remapped to {$newTicker}",
                ]);
            } else {
                Watchlist::where('ticker', $oldTicker)->update([
                    'ticker'       => $newTicker,
                    'company_name' => $profile['name'] ?? null,
                    'exchange'     => $profile['exchange'] ?? null,
                ]);
            }

            StockSnapshot::where('ticker', $oldTicker)->update(['ticker' => $newTicker]);
            Signal::where('ticker', $oldTicker)->update(['ticker' => $newTicker]);
            WatchdogAlert::where('ticker', $oldTicker)->update(['ticker' => $newTicker]);

            $profileTargetExists = CompanyProfile::where('ticker', $newTicker)->exists();
            if ($profileTargetExists) {
                CompanyProfile::where('ticker', $oldTicker)->delete();
            } else {
                CompanyProfile::where('ticker', $oldTicker)->update(['ticker' => $newTicker]);
            }
        });

        Log::info('Ticker remapped', ['from' => $oldTicker, 'to' => $newTicker]);
    }
}
