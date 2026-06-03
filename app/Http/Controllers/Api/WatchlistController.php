<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Watchlist;
use App\Models\StockSnapshot;
use App\Services\FinnhubService;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function __construct(private FinnhubService $finnhub) {}

    public function index()
    {
        $data = Watchlist::where('active', true)
            ->orderByDesc('is_pinned')
            ->orderBy('ticker')
            ->with(['latestSnapshot', 'activeSignal', 'latestForecast'])
            ->get()
            ->map(function ($entry) {
                $snap   = $entry->latestSnapshot;
                $signal = $entry->activeSignal;
                $forecast = $entry->latestForecast;
                return [
                    'ticker'          => $entry->ticker,
                    'company_name'    => $entry->company_name,
                    'exchange'        => $entry->exchange,
                    'sector'          => $entry->sector,
                    'is_pinned'       => $entry->is_pinned,
                    'price'           => $snap?->price,
                    'change'          => $snap?->change,
                    'change_percent'  => $snap?->change_percent,
                    'rsi_14'          => $snap?->rsi_14,
                    'macd_cross'      => ($snap?->macd_hist ?? 0) >= 0 ? 'bullish' : 'bearish',
                    'volume'          => $snap?->volume,
                    'captured_at'     => $snap?->captured_at,
                    'signal'          => $signal?->action,
                    'signal_strength' => $signal?->strength,
                    'confidence'      => $signal?->confidence,
                    'forecast'        => $forecast ? [
                        'recommendation'     => $forecast->recommendation,
                        'predicted_price'    => $forecast->predicted_price,
                        'expected_return_pct'=> $forecast->expected_return_pct,
                        'confidence'         => $forecast->confidence,
                        'entry_price'        => $forecast->entry_price,
                        'take_profit_price'  => $forecast->take_profit_price,
                        'stop_loss_price'    => $forecast->stop_loss_price,
                        'forecast_for'       => $forecast->forecast_for,
                        'generated_at'       => $forecast->generated_at,
                    ] : null,
                ];
            });

        return response()->json($data);
    }

    public function snapshots(string $ticker)
    {
        $ticker    = strtoupper($ticker);
        $snapshots = StockSnapshot::recentFor($ticker, 120)
            ->map(fn($s) => [
                'ts'          => $s->captured_at->timestamp * 1000,
                'price'       => $s->price,
                'rsi_14'      => $s->rsi_14,
                'macd_hist'   => $s->macd_hist,
                'ema_9'       => $s->ema_9,
                'ema_21'      => $s->ema_21,
                'bb_upper'    => $s->bb_upper,
                'bb_lower'    => $s->bb_lower,
                'volume'      => $s->volume,
                'momentum_1m' => $s->momentum_1m,
            ]);

        return response()->json(['ticker' => $ticker, 'candles' => $snapshots]);
    }

    public function news(string $ticker)
    {
        $news = $this->finnhub->companyNews(strtoupper($ticker));
        return response()->json($news);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ticker'       => 'required|string|max:20|regex:/^[A-Z0-9\.\-]+$/',
            'company_name' => 'required|string|max:255',
            'exchange'     => 'nullable|string|max:20',
            'sector'       => 'nullable|string|max:100',
            'is_pinned'    => 'nullable|boolean',
        ]);

        $entry = Watchlist::updateOrCreate(
            ['ticker' => strtoupper($validated['ticker'])],
            array_merge($validated, ['active' => true])
        );

        return response()->json($entry, 201);
    }

    public function destroy(string $ticker)
    {
        Watchlist::where('ticker', strtoupper($ticker))->update(['active' => false]);
        return response()->json(['ok' => true]);
    }

    public function show(string $id) { return response()->json([]); }
    public function update(Request $request, string $id) { return response()->json([]); }
}
