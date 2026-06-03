<?php

namespace App\Services;

use App\Models\PriceForecast;
use App\Models\StockSnapshot;
use App\Models\Watchlist;
use Illuminate\Support\Facades\Http;

class PredictiveAnalyticsService
{
    public function __construct(
        private FinnhubService $finnhub,
        private TechnicalAnalysis $ta,
    ) {}

    public function generateForAllActive(int $horizonDays = 7): array
    {
        $results = [];
        $tickers = Watchlist::where('active', true)->pluck('ticker');

        foreach ($tickers as $ticker) {
            $results[] = $this->generateForTicker($ticker, $horizonDays);
        }

        return $results;
    }

    public function generateForTicker(string $ticker, int $horizonDays = 7): array
    {
        $ticker = strtoupper($ticker);
        $horizonDays = max(1, min(30, $horizonDays));

        [$source, $series, $meta] = $this->historicalSeries($ticker);
        if (!$series || count($series['closes']) < 8) {
            return [
                'ticker' => $ticker,
                'ok' => false,
                'status' => 'insufficient_data',
            ];
        }

        $closes = array_map('floatval', $series['closes']);
        $highs = array_map('floatval', $series['highs']);
        $lows = array_map('floatval', $series['lows']);
        $volumes = array_map('intval', $series['volumes']);

        $n = count($closes);
        $currentPrice = (float) end($closes);

        [$slope, $intercept, $r2] = $this->linearRegression($closes);
        $regressionForecast = max(0.0001, $intercept + $slope * (($n - 1) + $horizonDays));

        $returns = $this->returns($closes);
        $avgReturn = $this->mean($returns);
        $volatility = $this->stddev($returns);
        $momentumForecast = $currentPrice * (1 + $avgReturn * $horizonDays);

        $ema20 = $this->ta->ema($closes, 20) ?? $currentPrice;
        $ema50 = $this->ta->ema($closes, 50) ?? $currentPrice;
        $rsi14 = $this->ta->rsi($closes, 14);
        $meanRevertForecast = $currentPrice + (($ema20 - $currentPrice) * 0.35);

        $predictedPrice = max(0.0001,
            ($regressionForecast * 0.45) +
            ($momentumForecast * 0.35) +
            ($meanRevertForecast * 0.20)
        );

        $expectedReturnPct = $currentPrice > 0
            ? (($predictedPrice - $currentPrice) / $currentPrice) * 100
            : 0.0;

        $baseConfidence = $this->confidenceScore($r2, $volatility, $n);
        $dataQuality = $this->dataQualityScore($source, $n, $volatility, (float) ($meta['staleness_hours'] ?? 0.0));
        $volatilityRegime = $this->volatilityRegime($volatility);

        $models = $this->modelOutputs(
            currentPrice: $currentPrice,
            regressionForecast: $regressionForecast,
            momentumForecast: $momentumForecast,
            meanRevertForecast: $meanRevertForecast,
            expectedReturnPctBase: $expectedReturnPct,
            baseConfidence: $baseConfidence,
            dataQuality: $dataQuality,
            rsi14: $rsi14,
            ema20: $ema20,
            ema50: $ema50,
            volatility: $volatility,
            volatilityRegime: $volatilityRegime,
            horizonDays: $horizonDays
        );

        $champion = $models['champion'];
        $challenger = $models['challenger'];

        PriceForecast::where('ticker', $ticker)
            ->where('horizon_days', $horizonDays)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $shared = [
            'ticker' => $ticker,
            'horizon_days' => $horizonDays,
            'current_price' => round($currentPrice, 4),
            'quality_score' => $dataQuality,
            'volatility_regime' => $volatilityRegime,
            'forecast_for' => now()->addDays($horizonDays)->toDateString(),
            'generated_at' => now(),
            'features' => [
                'source' => $source,
                'data_quality_score' => $dataQuality,
                'staleness_hours' => round((float) ($meta['staleness_hours'] ?? 0.0), 2),
                'sample_size' => $n,
                'r2' => round($r2, 6),
                'volatility' => round($volatility, 6),
                'avg_return' => round($avgReturn, 6),
                'ema20' => round($ema20, 4),
                'ema50' => round($ema50, 4),
                'rsi14' => $rsi14 !== null ? round($rsi14, 2) : null,
                'regression_forecast' => round($regressionForecast, 4),
                'momentum_forecast' => round($momentumForecast, 4),
                'mean_reversion_forecast' => round($meanRevertForecast, 4),
                'champion_score' => round($champion['score'], 4),
                'challenger_score' => round($challenger['score'], 4),
            ],
            'is_active' => true,
        ];

        $championRow = PriceForecast::create(array_merge($shared, [
            'model_name' => $champion['model_name'],
            'model_version' => $champion['model_version'],
            'model_role' => 'champion',
            'predicted_price' => round($champion['predicted_price'], 4),
            'expected_return_pct' => round($champion['expected_return_pct'], 4),
            'confidence' => $champion['confidence'],
            'recommendation' => $champion['recommendation'],
            'entry_price' => round($champion['entry_price'], 4),
            'take_profit_price' => round($champion['take_profit_price'], 4),
            'stop_loss_price' => round($champion['stop_loss_price'], 4),
            'notes' => $champion['notes'],
        ]));

        PriceForecast::create(array_merge($shared, [
            'model_name' => $challenger['model_name'],
            'model_version' => $challenger['model_version'],
            'model_role' => 'challenger',
            'predicted_price' => round($challenger['predicted_price'], 4),
            'expected_return_pct' => round($challenger['expected_return_pct'], 4),
            'confidence' => $challenger['confidence'],
            'recommendation' => $challenger['recommendation'],
            'entry_price' => round($challenger['entry_price'], 4),
            'take_profit_price' => round($challenger['take_profit_price'], 4),
            'stop_loss_price' => round($challenger['stop_loss_price'], 4),
            'notes' => $challenger['notes'],
        ]));

        return [
            'ticker' => $ticker,
            'ok' => true,
            'source' => $source,
            'data_quality_score' => $dataQuality,
            'recommendation' => $champion['recommendation'],
            'expected_return_pct' => round($champion['expected_return_pct'], 2),
            'confidence' => $champion['confidence'],
            'predicted_price' => round($champion['predicted_price'], 4),
            'forecast_id' => $championRow->id,
            'champion' => [
                'model_version' => $champion['model_version'],
                'recommendation' => $champion['recommendation'],
                'expected_return_pct' => round($champion['expected_return_pct'], 2),
                'confidence' => $champion['confidence'],
            ],
            'challenger' => [
                'model_version' => $challenger['model_version'],
                'recommendation' => $challenger['recommendation'],
                'expected_return_pct' => round($challenger['expected_return_pct'], 2),
                'confidence' => $challenger['confidence'],
            ],
        ];
    }

    private function modelOutputs(
        float $currentPrice,
        float $regressionForecast,
        float $momentumForecast,
        float $meanRevertForecast,
        float $expectedReturnPctBase,
        int $baseConfidence,
        int $dataQuality,
        ?float $rsi14,
        float $ema20,
        float $ema50,
        float $volatility,
        string $volatilityRegime,
        int $horizonDays
    ): array {
        $v1Pred = max(0.0001,
            ($regressionForecast * 0.45) +
            ($momentumForecast * 0.35) +
            ($meanRevertForecast * 0.20)
        );
        $v2Pred = max(0.0001,
            ($regressionForecast * 0.30) +
            ($momentumForecast * 0.50) +
            ($meanRevertForecast * 0.20)
        );

        $v1Exp = $currentPrice > 0 ? (($v1Pred - $currentPrice) / $currentPrice) * 100 : $expectedReturnPctBase;
        $v2Exp = $currentPrice > 0 ? (($v2Pred - $currentPrice) / $currentPrice) * 100 : $expectedReturnPctBase;

        $v1Conf = (int) round(max(5, min(95, ($baseConfidence * 0.62) + ($dataQuality * 0.38))));
        $v2Conf = (int) round(max(5, min(95, ($baseConfidence * 0.55) + ($dataQuality * 0.45))));

        $v1Rec = $this->recommendation($v1Exp, $v1Conf, $rsi14, $ema20, $ema50, $dataQuality, $volatilityRegime);
        $v2Rec = $this->recommendation($v2Exp, $v2Conf, $rsi14, $ema20, $ema50, $dataQuality, $volatilityRegime);

        $v1Risk = $this->riskPlan($currentPrice, $ema20, $v1Pred, $volatility, $horizonDays, $volatilityRegime);
        $v2Risk = $this->riskPlan($currentPrice, $ema20, $v2Pred, $volatility, $horizonDays, $volatilityRegime);

        $v1Score = $this->modelScore($v1Exp, $v1Conf, $dataQuality);
        $v2Score = $this->modelScore($v2Exp, $v2Conf, $dataQuality + 2);

        $v1 = [
            'model_name' => 'trend_regression_v1',
            'model_version' => 'v1',
            'predicted_price' => $v1Pred,
            'expected_return_pct' => $v1Exp,
            'confidence' => $v1Conf,
            'recommendation' => $v1Rec,
            'entry_price' => $v1Risk['entry_price'],
            'take_profit_price' => $v1Risk['take_profit_price'],
            'stop_loss_price' => $v1Risk['stop_loss_price'],
            'score' => $v1Score,
            'notes' => 'Champion/challenger blend model v1 (trend balanced).',
        ];
        $v2 = [
            'model_name' => 'trend_regression_v2',
            'model_version' => 'v2',
            'predicted_price' => $v2Pred,
            'expected_return_pct' => $v2Exp,
            'confidence' => $v2Conf,
            'recommendation' => $v2Rec,
            'entry_price' => $v2Risk['entry_price'],
            'take_profit_price' => $v2Risk['take_profit_price'],
            'stop_loss_price' => $v2Risk['stop_loss_price'],
            'score' => $v2Score,
            'notes' => 'Champion/challenger blend model v2 (momentum-forward).',
        ];

        if ($v1Score >= $v2Score) {
            return ['champion' => $v1, 'challenger' => $v2];
        }

        return ['champion' => $v2, 'challenger' => $v1];
    }

    private function historicalSeries(string $ticker): array
    {
        $daily = $this->finnhub->dailyCandles($ticker, 420);
        if ($daily && ($daily['s'] ?? '') === 'ok' && !empty($daily['c'])) {
            return ['finnhub_daily', [
                'closes' => $daily['c'] ?? [],
                'highs' => $daily['h'] ?? [],
                'lows' => $daily['l'] ?? [],
                'volumes' => $daily['v'] ?? [],
            ], ['staleness_hours' => 24.0]];
        }

        $yahoo = $this->yahooDailySeries($ticker);
        if ($yahoo && !empty($yahoo['closes'])) {
            return ['yahoo_daily', $yahoo, ['staleness_hours' => 24.0]];
        }

        $rows = StockSnapshot::where('ticker', $ticker)
            ->orderByDesc('captured_at')
            ->limit(500)
            ->get()
            ->reverse()
            ->values();

        if ($rows->isEmpty()) {
            return ['none', null, ['staleness_hours' => 9999.0]];
        }

        $latest = $rows->last();
        $staleHrs = $latest?->captured_at ? now()->diffInHours($latest->captured_at) : 9999.0;

        return ['local_snapshots', [
            'closes' => $rows->pluck('price')->map(fn($v) => (float) $v)->all(),
            'highs' => $rows->map(fn($r) => $r->high !== null ? (float) $r->high : (float) $r->price)->all(),
            'lows' => $rows->map(fn($r) => $r->low !== null ? (float) $r->low : (float) $r->price)->all(),
            'volumes' => $rows->pluck('volume')->map(fn($v) => (int) ($v ?? 0))->all(),
        ], ['staleness_hours' => (float) $staleHrs]];
    }

    private function yahooDailySeries(string $ticker): ?array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get("https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}", [
                    'range' => '2y',
                    'interval' => '1d',
                    'includePrePost' => 'false',
                    'events' => 'div,splits',
                ]);

            if (!$response->successful()) {
                return null;
            }

            $json = $response->json();
            $result = $json['chart']['result'][0] ?? null;
            $quote = $result['indicators']['quote'][0] ?? null;
            if (!$result || !$quote) {
                return null;
            }

            $closes = [];
            $highs = [];
            $lows = [];
            $volumes = [];

            $rawClose = $quote['close'] ?? [];
            $rawHigh = $quote['high'] ?? [];
            $rawLow = $quote['low'] ?? [];
            $rawVolume = $quote['volume'] ?? [];
            $count = count($rawClose);

            for ($i = 0; $i < $count; $i++) {
                $close = $rawClose[$i] ?? null;
                if ($close === null) {
                    continue;
                }

                $closes[] = (float) $close;
                $highs[] = (float) ($rawHigh[$i] ?? $close);
                $lows[] = (float) ($rawLow[$i] ?? $close);
                $volumes[] = (int) ($rawVolume[$i] ?? 0);
            }

            if (count($closes) < 8) {
                return null;
            }

            return [
                'closes' => $closes,
                'highs' => $highs,
                'lows' => $lows,
                'volumes' => $volumes,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function linearRegression(array $y): array
    {
        $n = count($y);
        if ($n < 2) {
            return [0.0, (float) ($y[0] ?? 0), 0.0];
        }

        $sumX = $sumY = $sumXX = $sumXY = 0.0;
        foreach ($y as $i => $value) {
            $x = (float) $i;
            $v = (float) $value;
            $sumX += $x;
            $sumY += $v;
            $sumXX += $x * $x;
            $sumXY += $x * $v;
        }

        $den = ($n * $sumXX) - ($sumX * $sumX);
        if (abs($den) < 1e-9) {
            return [0.0, $sumY / $n, 0.0];
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $den;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        $meanY = $sumY / $n;
        $ssTot = 0.0;
        $ssRes = 0.0;
        foreach ($y as $i => $value) {
            $pred = $intercept + ($slope * $i);
            $ssTot += (($value - $meanY) ** 2);
            $ssRes += (($value - $pred) ** 2);
        }
        $r2 = $ssTot > 0 ? max(0.0, min(1.0, 1 - ($ssRes / $ssTot))) : 0.0;

        return [$slope, $intercept, $r2];
    }

    private function returns(array $closes): array
    {
        $ret = [];
        for ($i = 1, $n = count($closes); $i < $n; $i++) {
            $prev = (float) $closes[$i - 1];
            $curr = (float) $closes[$i];
            if ($prev <= 0) {
                continue;
            }
            $ret[] = ($curr - $prev) / $prev;
        }
        return $ret;
    }

    private function mean(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }
        return array_sum($values) / count($values);
    }

    private function stddev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $mean = $this->mean($values);
        $sum = 0.0;
        foreach ($values as $v) {
            $sum += (($v - $mean) ** 2);
        }
        return sqrt($sum / ($n - 1));
    }

    private function confidenceScore(float $r2, float $volatility, int $sampleSize): int
    {
        $dataScore = min(40.0, ($sampleSize / 365) * 40.0);
        $fitScore = max(0.0, min(40.0, $r2 * 40.0));
        $stabilityScore = max(0.0, 20.0 - min(20.0, $volatility * 100.0 * 2.2));

        return (int) round(max(5.0, min(95.0, $dataScore + $fitScore + $stabilityScore)));
    }

    private function recommendation(
        float $expectedReturnPct,
        int $confidence,
        ?float $rsi14,
        float $ema20,
        float $ema50,
        int $dataQuality,
        string $volatilityRegime
    ): string {
        $bullTrend = $ema20 >= $ema50;

        if ($dataQuality < 35 || $confidence < 25) {
            return 'HOLD';
        }

        if (($expectedReturnPct >= 8.0 && $confidence >= 45 && $bullTrend) || ($expectedReturnPct >= 5.0 && $confidence >= 55)) {
            if ($volatilityRegime === 'high' && $confidence < 75) {
                return 'WATCH';
            }
            return 'BUY';
        }

        if ($expectedReturnPct <= -4.0 || ($rsi14 !== null && $rsi14 >= 72.0)) {
            return 'RELEASE';
        }

        if ($expectedReturnPct >= 2.0 && $confidence >= 30) {
            return 'WATCH';
        }

        return 'HOLD';
    }

    private function riskPlan(
        float $currentPrice,
        float $ema20,
        float $predictedPrice,
        float $volatility,
        int $horizonDays,
        string $volatilityRegime
    ): array {
        $multiplier = match ($volatilityRegime) {
            'high' => 3.5,
            'medium' => 2.8,
            default => 2.2,
        };

        $riskBand = max(0.02, min(0.22, $volatility * sqrt($horizonDays) * $multiplier));
        $entry = min($currentPrice, $ema20);
        $takeProfit = max($predictedPrice, $currentPrice * (1 + max(0.0125, $riskBand * 0.9)));
        $stopLoss = $currentPrice * (1 - $riskBand);

        return [
            'entry_price' => $entry,
            'take_profit_price' => $takeProfit,
            'stop_loss_price' => $stopLoss,
        ];
    }

    private function modelScore(float $expectedReturnPct, int $confidence, int $quality): float
    {
        $downsidePenalty = $expectedReturnPct < 0 ? abs($expectedReturnPct) * 1.15 : 0.0;
        return ($expectedReturnPct * 0.5) + ($confidence * 0.32) + ($quality * 0.18) - $downsidePenalty;
    }

    private function dataQualityScore(string $source, int $sampleSize, float $volatility, float $stalenessHours): int
    {
        $sourceScore = match ($source) {
            'finnhub_daily' => 35,
            'yahoo_daily' => 32,
            'local_snapshots' => 24,
            default => 5,
        };

        $depthScore = min(35.0, ($sampleSize / 260.0) * 35.0);
        $volPenalty = min(20.0, max(0.0, ($volatility * 100.0) * 1.5));
        $freshPenalty = min(20.0, max(0.0, $stalenessHours / 6.0));

        $score = $sourceScore + $depthScore - $volPenalty - $freshPenalty;
        return (int) round(max(5.0, min(95.0, $score)));
    }

    private function volatilityRegime(float $volatility): string
    {
        if ($volatility >= 0.045) {
            return 'high';
        }
        if ($volatility >= 0.02) {
            return 'medium';
        }
        return 'low';
    }
}
