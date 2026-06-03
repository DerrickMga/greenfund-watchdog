<?php

namespace App\Http\Controllers;

use App\Models\PriceForecast;

class DecisionConsoleController extends Controller
{
    public function index()
    {
        $leaders = PriceForecast::where('is_active', true)
            ->where('horizon_days', 7)
            ->where('model_role', 'champion')
            ->orderByDesc('expected_return_pct')
            ->take(8)
            ->get();

        $risks = PriceForecast::where('is_active', true)
            ->where('horizon_days', 7)
            ->where('model_role', 'champion')
            ->orderBy('expected_return_pct')
            ->take(8)
            ->get();

        return view('decision_console', compact('leaders', 'risks'));
    }
}
