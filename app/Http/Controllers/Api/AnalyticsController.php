<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Watchlist;
use App\Models\CompanyProfile;
use App\Models\StockSnapshot;
use App\Services\FinnhubService;
use App\Services\TechnicalAnalysis;
use App\Services\SignalEngine;

class AnalyticsController extends Controller
{
    public function __construct(
        private FinnhubService  $finnhub,
        private TechnicalAnalysis $ta,
        private SignalEngine     $engine,
    ) {}

    /** Full analytics payload for a single ticker (used by ticker detail page) */
    public function ticker(string $ticker): \Illuminate\Http\JsonResponse
    {
        $ticker  = strtoupper($ticker);
        $profile = CompanyProfile::where('ticker', $ticker)->first();
        $snap    = Watchlist::where('ticker', $ticker)->with('latestSnapshot')->first()?->latestSnapshot;

        // Fetch daily candles for 1-year chart
        $daily = $this->finnhub->dailyCandles($ticker, 365);
        $intraday = $this->finnhub->candles($ticker, '1', time() - 7200, time());

        $dailySeries    = $this->buildChartSeries($daily) ?? $this->buildSeriesFromSnapshots($ticker, 365);
        $intradaySeries = $this->buildChartSeries($intraday) ?? $this->buildSeriesFromSnapshots($ticker, 240);

        // TA on daily data
        $indDaily = null;
        if ($dailySeries) {
            $indDaily = $this->ta->calculateAll(
                $dailySeries['closes'], $dailySeries['highs'],
                $dailySeries['lows'],  $dailySeries['volumes']
            );
        }

        // TA on intraday data
        $indIntraday = null;
        if ($intradaySeries) {
            $indIntraday = $this->ta->calculateAll(
                $intradaySeries['closes'], $intradaySeries['highs'],
                $intradaySeries['lows'],  $intradaySeries['volumes']
            );
        }

        // Analyst data
        $priceTarget  = $this->finnhub->priceTarget($ticker);
        $recommendations = $this->finnhub->recommendations($ticker);
        $earnings     = $this->finnhub->earningsSurprises($ticker, 8);
        $insiders     = $this->finnhub->insiderTransactions($ticker);
        $sentiment    = $this->finnhub->newsSentiment($ticker);
        $sr           = $this->finnhub->supportResistance($ticker);
        $patterns     = $this->finnhub->patternRecognition($ticker);

        return response()->json([
            'ticker'          => $ticker,
            'profile'         => $profile,
            'snapshot'        => $snap,
            'price_target'    => $priceTarget,
            'recommendations' => $recommendations,
            'earnings'        => $earnings,
            'insiders'        => $insiders,
            'sentiment'       => $sentiment,
            'support_resistance' => $sr,
            'patterns'        => $patterns,
            'indicators_daily'    => $indDaily ? $this->scalarIndicators($indDaily) : null,
            'indicators_intraday' => $indIntraday ? $this->scalarIndicators($indIntraday) : null,
            'chart_daily'     => $dailySeries ? $this->buildChartPayloadFromSeries($dailySeries, $indDaily) : null,
            'chart_intraday'  => $intradaySeries ? $this->buildChartPayloadFromSeries($intradaySeries, $indIntraday) : null,
        ]);
    }

    /** Snapshot data for the sparklines on the watchlist */
    public function snapshots(string $ticker): \Illuminate\Http\JsonResponse
    {
        $intraday = $this->finnhub->candles(strtoupper($ticker), '5', time() - 86400, time());
        $series   = $this->buildChartSeries($intraday) ?? $this->buildSeriesFromSnapshots(strtoupper($ticker), 288);
        if (!$series) return response()->json([]);

        $ind = $this->ta->calculateAll($series['closes'], $series['highs'], $series['lows'], $series['volumes']);
        return response()->json($this->buildChartPayloadFromSeries($series, $ind));
    }

    /** Top movers from watchlist (by % change) */
    public function movers(): \Illuminate\Http\JsonResponse
    {
        $tickers = Watchlist::where('active', true)->pluck('ticker');
        $quotes  = [];
        foreach ($tickers as $t) {
            $q = $this->finnhub->quote($t);
            if ($q) {
                $quotes[] = [
                    'ticker'     => $t,
                    'price'      => $q['c'],
                    'change_pct' => $q['dp'],
                    'change'     => $q['d'],
                ];
            }
        }
        usort($quotes, fn($a, $b) => abs($b['change_pct']) <=> abs($a['change_pct']));
        return response()->json(array_slice($quotes, 0, 10));
    }

    /** Market-wide news */
    public function marketNews(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->finnhub->marketNews('general', 20));
    }

    /** Symbol search */
    public function search(string $query): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->finnhub->symbolSearch($query));
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function buildChartSeries(?array $candles): ?array
    {
        if (!$candles || ($candles['s'] ?? '') !== 'ok') return null;
        return [
            'timestamps' => $candles['t'] ?? [],
            'closes'     => $candles['c'] ?? [],
            'opens'      => $candles['o'] ?? [],
            'highs'      => $candles['h'] ?? [],
            'lows'       => $candles['l'] ?? [],
            'volumes'    => $candles['v'] ?? [],
        ];
    }

    private function buildSeriesFromSnapshots(string $ticker, int $limit): ?array
    {
        $rows = StockSnapshot::where('ticker', strtoupper($ticker))
            ->orderByDesc('captured_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'timestamps' => $rows->map(fn($r) => (int) $r->captured_at->timestamp)->all(),
            'closes'     => $rows->pluck('price')->map(fn($v) => (float) $v)->all(),
            'opens'      => $rows->map(fn($r) => $r->open !== null ? (float) $r->open : (float) $r->price)->all(),
            'highs'      => $rows->map(fn($r) => $r->high !== null ? (float) $r->high : (float) $r->price)->all(),
            'lows'       => $rows->map(fn($r) => $r->low !== null ? (float) $r->low : (float) $r->price)->all(),
            'volumes'    => $rows->pluck('volume')->map(fn($v) => (int) ($v ?? 0))->all(),
        ];
    }

    private function buildChartPayloadFromSeries(?array $series, ?array $ind): ?array
    {
        if (!$series) return null;
        $ts = array_map(fn($t) => $t * 1000, $series['timestamps'] ?? []);

        $payload = [
            'timestamps' => $ts,
            'opens'      => $series['opens'] ?? [],
            'highs'      => $series['highs'] ?? [],
            'lows'       => $series['lows'] ?? [],
            'closes'     => $series['closes'] ?? [],
            'volumes'    => $series['volumes'] ?? [],
        ];

        if ($ind && isset($ind['_series'])) {
            $s = $ind['_series'];
            $payload['ema9']      = $s['ema9']   ?? [];
            $payload['ema21']     = $s['ema21']  ?? [];
            $payload['ema50']     = $s['ema50']  ?? [];
            $payload['sma200']    = $s['sma200'] ?? [];
            $payload['bb_upper']  = $s['bb']['upper']  ?? [];
            $payload['bb_middle'] = $s['bb']['middle'] ?? [];
            $payload['bb_lower']  = $s['bb']['lower']  ?? [];
            $payload['rsi']       = $s['rsi']    ?? [];
            $payload['macd_line'] = $s['macd']['macd']      ?? [];
            $payload['macd_signal']= $s['macd']['signal']   ?? [];
            $payload['macd_hist'] = $s['macd']['histogram'] ?? [];
            $payload['vwap']      = $s['vwap']   ?? [];
            $payload['obv']       = $s['obv']    ?? [];
            // Pivot & Fibonacci as flat keys
            $payload['pivot']     = $ind['pivot'] ?? null;
            $payload['fib']       = $ind['fib']   ?? null;
        }

        return $payload;
    }

    private function scalarIndicators(array $ind): array
    {
        $keys = ['rsi_14','ema_9','ema_21','ema_50','sma_50','sma_200','macd','bb',
                 'stochastic','atr','vwap','momentum_5','momentum_10','momentum_1',
                 'williams_r','cci','obv','adx','pivot','fib'];
        return array_intersect_key($ind, array_flip($keys));
    }
}
