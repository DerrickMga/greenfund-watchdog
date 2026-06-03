<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WatchdogAlert;

class AlertController extends Controller
{
    public function index()
    {
        return response()->json(
            WatchdogAlert::unacknowledged()->orderByDesc('alerted_at')->limit(50)->get()
        );
    }

    public function acknowledge(int $id)
    {
        WatchdogAlert::findOrFail($id)->update(['acknowledged' => true]);
        return response()->json(['ok' => true]);
    }

    public function acknowledgeAll()
    {
        WatchdogAlert::unacknowledged()->update(['acknowledged' => true]);
        return response()->json(['ok' => true]);
    }
}
