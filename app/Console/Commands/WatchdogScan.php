<?php

namespace App\Console\Commands;

use App\Services\WatchdogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WatchdogScan extends Command
{
    protected $signature   = 'watchdog:scan {--ticker= : Scan a single ticker only}';
    protected $description = 'Run one watchdog cycle – fetch quotes, calculate indicators, generate signals & alerts';

    public function handle(WatchdogService $watchdog): int
    {
        $singleTicker = $this->option('ticker');
        $start        = microtime(true);

        $this->info('[' . now()->toDateTimeString() . '] Watchdog scan starting…');

        if ($singleTicker) {
            try {
                $result = $watchdog->processTicker(strtoupper($singleTicker));
                $this->renderResult($result);
            } catch (\Throwable $e) {
                $this->error("Error scanning {$singleTicker}: " . $e->getMessage());
                return self::FAILURE;
            }
        } else {
            $results = $watchdog->runCycle();
            foreach ($results as $result) {
                $this->renderResult($result);
            }

            if (now()->dayOfWeek === 0) {
                $pruned = $watchdog->pruneOldSnapshots(7);
                $this->line("  Pruned {$pruned} old snapshots.");
            }
        }

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("Watchdog cycle complete in {$elapsed}s");
        Log::info("Watchdog scan complete", ['elapsed_seconds' => $elapsed]);

        return self::SUCCESS;
    }

    private function renderResult(array $result): void
    {
        if (isset($result['error'])) {
            $this->warn("  {$result['ticker']} – ERROR: {$result['error']}");
            return;
        }
        if (($result['status'] ?? '') === 'no_data') {
            $this->warn("  {$result['ticker']} – no data returned from API");
            return;
        }

        $signal  = $result['signal']         ?? '?';
        $price   = $result['price']          ?? 0;
        $conf    = $result['confidence']     ?? 0;
        $rsi     = $result['rsi']            ?? 'N/A';
        $alerts  = $result['alerts']         ?? 0;
        $changed = ($result['signal_changed'] ?? false) ? ' ← NEW' : '';

        $line = sprintf(
            "  %-6s  $%-8.4f  Signal: %-5s (%3d%%)  RSI: %-5s  Alerts: %d%s",
            $result['ticker'], $price, $signal, $conf, $rsi, $alerts, $changed
        );

        match ($signal) {
            'BUY'   => $this->info($line),
            'SELL'  => $this->error($line),
            'WATCH' => $this->warn($line),
            default => $this->line($line),
        };
    }
}

