<?php

namespace App\Http\Controllers;

use App\Models\Watchlist;
use App\Models\Signal;
use App\Models\WatchdogAlert;
use App\Models\CompanyProfile;
use App\Models\PriceForecast;
use App\Services\WatchdogService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private WatchdogService $watchdog) {}

    public function index()
    {
        $watchlist = Watchlist::where('active', true)
            ->with(['latestSnapshot', 'activeSignal'])
            ->orderByDesc('is_pinned')
            ->orderBy('ticker')
            ->get();

        $unreadAlerts  = WatchdogAlert::unacknowledged()->latest('alerted_at')->take(20)->get();
        $criticalAlerts= WatchdogAlert::critical()->unacknowledged()->latest('alerted_at')->take(5)->get();
        $recentSignals = Signal::where('is_active', true)->latest('triggered_at')->take(15)->get();
        $topForecasts  = PriceForecast::where('is_active', true)
            ->where('horizon_days', 7)
            ->orderByDesc('expected_return_pct')
            ->take(10)
            ->get();

        $signalCounts  = [
            'buy'   => $recentSignals->where('action', 'BUY')->count(),
            'sell'  => $recentSignals->where('action', 'SELL')->count(),
            'watch' => $recentSignals->where('action', 'WATCH')->count(),
            'hold'  => $recentSignals->where('action', 'HOLD')->count(),
        ];

        return view('dashboard', compact(
            'watchlist',
            'unreadAlerts',
            'criticalAlerts',
            'recentSignals',
            'signalCounts',
            'topForecasts'
        ));
    }

    public function ticker(string $ticker)
    {
        $ticker  = strtoupper($ticker);
        $entry   = Watchlist::where('ticker', $ticker)->firstOrFail();
        $profile = CompanyProfile::where('ticker', $ticker)->first();
        $forecast = PriceForecast::where('ticker', $ticker)
            ->where('is_active', true)
            ->where('horizon_days', 7)
            ->latest('generated_at')
            ->first();
        $snapshots = \App\Models\StockSnapshot::recentFor($ticker, 200);
        $signals   = Signal::where('ticker', $ticker)->latest('triggered_at')->take(30)->get();
        $alerts    = WatchdogAlert::where('ticker', $ticker)->latest('alerted_at')->take(30)->get();

        return view('ticker', compact('entry', 'profile', 'forecast', 'snapshots', 'signals', 'alerts'));
    }

    public function scanNow(string $ticker)
    {
        $ticker = strtoupper($ticker);
        try {
            $result = $this->watchdog->processTicker($ticker);
            return response()->json(['ok' => true, 'ticker' => $ticker]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
