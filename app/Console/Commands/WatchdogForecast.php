<?php

namespace App\Console\Commands;

use App\Services\PredictiveAnalyticsService;
use Illuminate\Console\Command;

class WatchdogForecast extends Command
{
    protected $signature = 'watchdog:forecast {--ticker= : Generate for one ticker} {--horizon=7 : Forecast horizon in days (1-30)}';
    protected $description = 'Generate predictive price forecasts and trading recommendations';

    public function handle(PredictiveAnalyticsService $service): int
    {
        $ticker = $this->option('ticker');
        $horizon = (int) $this->option('horizon');
        $horizon = max(1, min(30, $horizon));

        $this->info('[' . now()->toDateTimeString() . "] Forecast run starting (horizon={$horizon}d)");

        if ($ticker) {
            $result = $service->generateForTicker((string) $ticker, $horizon);
            $this->renderResult($result);
            return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $results = $service->generateForAllActive($horizon);
        $ok = 0;
        $fail = 0;

        foreach ($results as $result) {
            $this->renderResult($result);
            if ($result['ok'] ?? false) {
                $ok++;
            } else {
                $fail++;
            }
        }

        $this->info("Forecasts complete: {$ok} success, {$fail} skipped/failed.");
        return self::SUCCESS;
    }

    private function renderResult(array $result): void
    {
        $ticker = $result['ticker'] ?? 'N/A';

        if (!($result['ok'] ?? false)) {
            $status = $result['status'] ?? 'error';
            $this->warn("  {$ticker} - {$status}");
            return;
        }

        $line = sprintf(
            '  %-6s  %-8s  Pred: $%-8.4f  Exp: %6.2f%%  Conf: %3d%%',
            $ticker,
            $result['recommendation'] ?? 'HOLD',
            (float) ($result['predicted_price'] ?? 0),
            (float) ($result['expected_return_pct'] ?? 0),
            (int) ($result['confidence'] ?? 0)
        );

        match ($result['recommendation'] ?? 'HOLD') {
            'BUY' => $this->info($line),
            'RELEASE' => $this->error($line),
            'WATCH' => $this->warn($line),
            default => $this->line($line),
        };
    }
}
