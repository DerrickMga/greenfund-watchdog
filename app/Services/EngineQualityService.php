<?php

namespace App\Services;

use App\Models\PriceForecast;
use App\Models\Watchlist;

class EngineQualityService
{
    public function summary(int $horizonDays = 7): array
    {
        $rows = $this->perTicker($horizonDays);
        $total = count($rows);

        $healthy = collect($rows)->where('status', 'healthy')->count();
        $warning = collect($rows)->where('status', 'warning')->count();
        $critical = collect($rows)->where('status', 'critical')->count();

        $avgForecastConfidence = $total > 0
            ? round(collect($rows)->avg('forecast_confidence') ?? 0, 2)
            : 0.0;

        $avgDataQuality = $total > 0
            ? round(collect($rows)->avg('data_quality_score') ?? 0, 2)
            : 0.0;

        return [
            'total_tickers' => $total,
            'healthy' => $healthy,
            'warning' => $warning,
            'critical' => $critical,
            'avg_forecast_confidence' => $avgForecastConfidence,
            'avg_data_quality_score' => $avgDataQuality,
            'anomalies' => collect($rows)->whereNotNull('anomaly_flag')->values()->all(),
            'tickers' => $rows,
        ];
    }

    public function perTicker(int $horizonDays = 7): array
    {
        $horizonDays = max(1, min(30, $horizonDays));

        $championIds = PriceForecast::where('horizon_days', $horizonDays)
            ->where('is_active', true)
            ->where('model_role', 'champion')
            ->selectRaw('MAX(id) as id')
            ->groupBy('ticker')
            ->pluck('id');

        $challengerIds = PriceForecast::where('horizon_days', $horizonDays)
            ->where('is_active', true)
            ->where('model_role', 'challenger')
            ->selectRaw('MAX(id) as id')
            ->groupBy('ticker')
            ->pluck('id');

        $champions = PriceForecast::whereIn('id', $championIds)->get()->keyBy('ticker');
        $challengers = PriceForecast::whereIn('id', $challengerIds)->get()->keyBy('ticker');

        return Watchlist::where('active', true)
            ->with('latestSnapshot')
            ->orderByDesc('is_pinned')
            ->orderBy('ticker')
            ->get()
            ->map(function ($w) use ($champions, $challengers) {
                $snapshot = $w->latestSnapshot;
                $forecast = $champions->get($w->ticker);
                $challenger = $challengers->get($w->ticker);

                $snapshotAgeMin = $snapshot?->captured_at
                    ? now()->diffInMinutes($snapshot->captured_at)
                    : null;
                $forecastAgeMin = $forecast?->generated_at
                    ? now()->diffInMinutes($forecast->generated_at)
                    : null;

                $dataQuality = (int) ($forecast?->quality_score ?? ($forecast->features['data_quality_score'] ?? 0));
                $confidence = (int) ($forecast?->confidence ?? 0);

                $status = 'healthy';
                if ($snapshotAgeMin === null || $snapshotAgeMin > 90 || $forecast === null || $forecastAgeMin > 180) {
                    $status = 'critical';
                } elseif ($snapshotAgeMin > 30 || $confidence < 35 || $dataQuality < 40) {
                    $status = 'warning';
                }

                $anomaly = null;
                if ($forecast && $challenger && $forecast->expected_return_pct !== null && $challenger->expected_return_pct !== null) {
                    $dispersion = abs((float) $forecast->expected_return_pct - (float) $challenger->expected_return_pct);
                    if ($dispersion >= 12) {
                        $anomaly = 'model_divergence';
                    }
                }
                if (!$anomaly && $forecast && $forecast->recommendation === 'BUY' && (($forecast->expected_return_pct ?? 0) < 3)) {
                    $anomaly = 'weak_buy_signal';
                }
                if (!$anomaly && $dataQuality > 0 && $dataQuality < 35) {
                    $anomaly = 'low_data_quality';
                }

                return [
                    'ticker' => $w->ticker,
                    'company_name' => $w->company_name,
                    'snapshot_age_min' => $snapshotAgeMin,
                    'forecast_age_min' => $forecastAgeMin,
                    'forecast_confidence' => $confidence,
                    'data_quality_score' => $dataQuality,
                    'recommendation' => $forecast?->recommendation,
                    'expected_return_pct' => $forecast?->expected_return_pct,
                    'source' => $forecast->features['source'] ?? null,
                    'challenger_expected_return_pct' => $challenger?->expected_return_pct,
                    'anomaly_flag' => $anomaly,
                    'status' => $status,
                ];
            })
            ->values()
            ->all();
    }

    public function tickerDetail(string $ticker, int $horizonDays = 7): array
    {
        $ticker = strtoupper($ticker);
        $summary = $this->perTicker($horizonDays);
        $row = collect($summary)->firstWhere('ticker', $ticker);

        $forecast = PriceForecast::where('ticker', $ticker)
            ->where('horizon_days', max(1, min(30, $horizonDays)))
            ->latest('generated_at')
            ->limit(30)
            ->get();

        return [
            'ticker' => $ticker,
            'current' => $row,
            'forecast_history' => $forecast,
        ];
    }
}
