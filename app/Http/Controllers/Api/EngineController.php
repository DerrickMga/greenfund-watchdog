<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceForecast;
use App\Services\EngineQualityService;
use Illuminate\Http\Request;

class EngineController extends Controller
{
    public function __construct(private EngineQualityService $quality) {}

    public function health(Request $request)
    {
        $horizon = max(1, min(30, (int) $request->query('horizon', 7)));
        return response()->json($this->quality->summary($horizon));
    }

    public function ticker(string $ticker, Request $request)
    {
        $horizon = max(1, min(30, (int) $request->query('horizon', 7)));
        return response()->json($this->quality->tickerDetail($ticker, $horizon));
    }

    public function metrics(Request $request)
    {
        $horizon = max(1, min(30, (int) $request->query('horizon', 7)));

        $rows = PriceForecast::where('horizon_days', $horizon)
            ->whereNotNull('evaluated_at')
            ->whereNotNull('abs_error_pct')
            ->selectRaw('model_version, COUNT(*) as n, AVG(abs_error_pct) as mape, AVG(CASE WHEN direction_hit = 1 THEN 1.0 ELSE 0.0 END) as hit_rate, AVG(realized_return_pct) as avg_realized_return')
            ->groupBy('model_version')
            ->get();

        return response()->json([
            'horizon_days' => $horizon,
            'models' => $rows,
        ]);
    }
}
