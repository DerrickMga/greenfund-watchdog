<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Signal;

class SignalController extends Controller
{
    public function index()
    {
        $signals = Signal::where('is_active', true)
            ->orderByDesc('triggered_at')
            ->get()
            ->map(fn($s) => [
                'ticker'          => $s->ticker,
                'action'          => $s->action,
                'strength'        => $s->strength,
                'confidence'      => $s->confidence,
                'price_at_signal' => $s->price_at_signal,
                'target_price'    => $s->target_price,
                'stop_loss'       => $s->stop_loss,
                'reasoning'       => $s->reasoning,
                'triggered_at'    => $s->triggered_at,
                'colour'          => $s->actionColour(),
            ]);

        return response()->json($signals);
    }

    public function show(string $ticker)
    {
        $signals = Signal::where('ticker', strtoupper($ticker))
            ->orderByDesc('triggered_at')
            ->limit(50)
            ->get();

        return response()->json($signals);
    }
}
