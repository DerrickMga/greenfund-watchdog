<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\SignalController;
use App\Http\Controllers\Api\WatchlistController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ForecastController;
use App\Http\Controllers\Api\EngineController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/mobile/health', function () {
    DB::connection()->getPdo();

    return response()->json([
        'status' => 'ok',
        'service' => 'greenfund-vpn-account',
        'time' => now()->toIso8601String(),
    ]);
});

Route::post('/mobile/register', [MobileAuthController::class, 'register']);
Route::post('/mobile/login', [MobileAuthController::class, 'login']);
Route::get('/mobile/subscription-plans', [SubscriptionController::class, 'plans']);

Route::middleware('mobile.auth')->group(function () {
    Route::get('/mobile/me', [MobileAuthController::class, 'me']);
    Route::post('/mobile/logout', [MobileAuthController::class, 'logout']);
    Route::get('/mobile/subscription', [SubscriptionController::class, 'show']);
    Route::post('/mobile/subscription/select-plan', [SubscriptionController::class, 'selectPlan']);
});

// Watchlist CRUD
Route::get('/watchlist',                   [WatchlistController::class, 'index']);
Route::post('/watchlist',                  [WatchlistController::class, 'store']);
Route::delete('/watchlist/{ticker}',       [WatchlistController::class, 'destroy']);
Route::get('/watchlist/{ticker}/news',     [WatchlistController::class, 'news']);

// Analytics (full chart + indicators payloads)
Route::get('/analytics/{ticker}',          [AnalyticsController::class, 'ticker']);
Route::get('/analytics/{ticker}/snapshots',[AnalyticsController::class, 'snapshots']);
Route::get('/analytics/movers/top',        [AnalyticsController::class, 'movers']);
Route::get('/analytics/news/market',       [AnalyticsController::class, 'marketNews']);
Route::get('/search/{query}',              [AnalyticsController::class, 'search']);

// Predictive forecasts
Route::get('/forecasts',                   [ForecastController::class, 'index']);
Route::get('/forecasts/{ticker}',          [ForecastController::class, 'show']);
Route::post('/forecasts/generate',         [ForecastController::class, 'generate']);

// Engine observability / quality
Route::get('/engine/health',               [EngineController::class, 'health']);
Route::get('/engine/quality/{ticker}',     [EngineController::class, 'ticker']);
Route::get('/engine/metrics',              [EngineController::class, 'metrics']);

// Signals
Route::get('/signals',          [SignalController::class, 'index']);
Route::get('/signals/{ticker}', [SignalController::class, 'show']);

// Alerts
Route::get('/alerts',                        [AlertController::class, 'index']);
Route::patch('/alerts/{id}/acknowledge',     [AlertController::class, 'acknowledge']);
Route::post('/alerts/acknowledge-all',       [AlertController::class, 'acknowledgeAll']);
