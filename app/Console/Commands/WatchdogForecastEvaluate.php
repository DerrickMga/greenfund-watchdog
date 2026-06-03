<?php

namespace App\Console\Commands;

use App\Models\PriceForecast;
use App\Models\StockSnapshot;
use App\Services\FinnhubService;
use Illuminate\Console\Command;

class WatchdogForecastEvaluate extends Command
{
    protected $signature = 'watchdog:forecast:evaluate {--horizon=7 : Horizon in days to evaluate}';
    protected $description = 'Evaluate matured forecasts and compute error metrics (MAPE, hit-rate)';

    public function handle(FinnhubService $finnhub): int
    {
        $horizon = max(1, min(30, (int) $this->option('horizon')));

        $rows = PriceForecast::where('horizon_days', $horizon)
            ->whereNull('evaluated_at')
            ->whereDate('forecast_for', '<=', now()->toDateString())
            ->orderBy('forecast_for')
            ->get();

        if ($rows->isEmpty()) {
            $this->line('No matured forecasts to evaluate.');
            return self::SUCCESS;
        }

        $this->info('Evaluating ' . $rows->count() . ' matured forecasts...');

        foreach ($rows as $f) {
            $realized = $this->realizedPrice($f->ticker, $finnhub);
            if ($realized === null || $f->current_price <= 0) {
                continue;
            }

            $predMove = $f->predicted_price - $f->current_price;
            $realMove = $realized - $f->current_price;

            $realizedReturn = (($realized - $f->current_price) / $f->current_price) * 100;
            $absErrorPct = abs(($f->predicted_price - $realized) / $f->current_price) * 100;
            $directionHit = ($predMove === 0.0 && $realMove === 0.0)
                ? true
                : (($predMove > 0 && $realMove > 0) || ($predMove < 0 && $realMove < 0));

            $f->update([
                'realized_price' => round($realized, 4),
                'realized_return_pct' => round($realizedReturn, 4),
                'abs_error_pct' => round($absErrorPct, 4),
                'direction_hit' => $directionHit,
                'evaluated_at' => now(),
            ]);
        }

        $stats = PriceForecast::where('horizon_days', $horizon)
            ->whereNotNull('evaluated_at')
            ->whereNotNull('abs_error_pct')
            ->selectRaw('model_version, COUNT(*) as n, AVG(abs_error_pct) as mape, AVG(CASE WHEN direction_hit = 1 THEN 1.0 ELSE 0.0 END) as hit_rate')
            ->groupBy('model_version')
            ->get();

        foreach ($stats as $s) {
            $this->line(sprintf(
                '  %s  n=%d  MAPE=%.2f%%  HitRate=%.1f%%',
                strtoupper((string) $s->model_version),
                (int) $s->n,
                (float) $s->mape,
                ((float) $s->hit_rate) * 100
            ));
        }

        return self::SUCCESS;
    }

    private function realizedPrice(string $ticker, FinnhubService $finnhub): ?float
    {
        $latest = StockSnapshot::where('ticker', $ticker)->orderByDesc('captured_at')->first();
        if ($latest?->price) {
            return (float) $latest->price;
        }

        $quote = $finnhub->quote($ticker);
        if (!$quote || ($quote['c'] ?? 0) <= 0) {
            return null;
        }

        return (float) $quote['c'];
    }
}
