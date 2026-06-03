<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceForecast;
use App\Models\Watchlist;
use App\Services\PredictiveAnalyticsService;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function __construct(private PredictiveAnalyticsService $service) {}

    public function index(Request $request)
    {
        $horizon = max(1, min(30, (int) $request->query('horizon', 7)));

        $championIds = PriceForecast::where('horizon_days', $horizon)
            ->where('is_active', true)
            ->where('model_role', 'champion')
            ->selectRaw('MAX(id) as id')
            ->groupBy('ticker')
            ->pluck('id');

        $challengerIds = PriceForecast::where('horizon_days', $horizon)
            ->where('is_active', true)
            ->where('model_role', 'challenger')
            ->selectRaw('MAX(id) as id')
            ->groupBy('ticker')
            ->pluck('id');

        $champions = PriceForecast::whereIn('id', $championIds)->get()->keyBy('ticker');
        $challengers = PriceForecast::whereIn('id', $challengerIds)->get()->keyBy('ticker');

        $rows = Watchlist::where('active', true)
            ->with('latestSnapshot')
            ->orderByDesc('is_pinned')
            ->orderBy('ticker')
            ->get()
            ->map(function ($w) use ($champions, $challengers) {
                $f = $champions->get($w->ticker);
                $c = $challengers->get($w->ticker);
                return [
                    'ticker' => $w->ticker,
                    'company_name' => $w->company_name,
                    'current_price' => $w->latestSnapshot?->price ?? $f?->current_price,
                    'predicted_price' => $f?->predicted_price,
                    'expected_return_pct' => $f?->expected_return_pct,
                    'recommendation' => $f?->recommendation,
                    'confidence' => $f?->confidence,
                    'quality_score' => $f?->quality_score,
                    'source' => $f?->features['source'] ?? null,
                    'model_version' => $f?->model_version,
                    'entry_price' => $f?->entry_price,
                    'take_profit_price' => $f?->take_profit_price,
                    'stop_loss_price' => $f?->stop_loss_price,
                    'forecast_for' => $f?->forecast_for,
                    'generated_at' => $f?->generated_at,
                    'challenger' => $c ? [
                        'model_version' => $c->model_version,
                        'predicted_price' => $c->predicted_price,
                        'expected_return_pct' => $c->expected_return_pct,
                        'recommendation' => $c->recommendation,
                        'confidence' => $c->confidence,
                        'quality_score' => $c->quality_score,
                    ] : null,
                ];
            });

        return response()->json($rows);
    }

    public function show(string $ticker, Request $request)
    {
        $horizon = max(1, min(30, (int) $request->query('horizon', 7)));
        $ticker = strtoupper($ticker);

        $latest = PriceForecast::where('ticker', $ticker)
            ->where('horizon_days', $horizon)
            ->where('is_active', true)
            ->where('model_role', 'champion')
            ->latest('generated_at')
            ->first();

        $challenger = PriceForecast::where('ticker', $ticker)
            ->where('horizon_days', $horizon)
            ->where('is_active', true)
            ->where('model_role', 'challenger')
            ->latest('generated_at')
            ->first();

        $history = PriceForecast::where('ticker', $ticker)
            ->where('horizon_days', $horizon)
            ->latest('generated_at')
            ->limit(30)
            ->get();

        return response()->json([
            'ticker' => $ticker,
            'latest' => $latest,
            'challenger' => $challenger,
            'history' => $history,
        ]);
    }

    public function generate(Request $request)
    {
        $horizon = max(1, min(30, (int) $request->input('horizon', 7)));
        $ticker = $request->input('ticker');

        if ($ticker) {
            return response()->json($this->service->generateForTicker((string) $ticker, $horizon));
        }

        return response()->json($this->service->generateForAllActive($horizon));
    }
}
