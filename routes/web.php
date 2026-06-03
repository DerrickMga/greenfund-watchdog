<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\DecisionConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/',              [DashboardController::class, 'index'])->name('dashboard');
Route::get('/market',        [MarketController::class,   'index'])->name('market');
Route::get('/screener',      [MarketController::class,   'screener'])->name('screener');
Route::get('/decision-console', [DecisionConsoleController::class, 'index'])->name('decision.console');
Route::get('/ticker/{ticker}', [DashboardController::class, 'ticker'])->name('ticker.show');
Route::post('/scan/{ticker}',  [DashboardController::class, 'scanNow'])->name('ticker.scan');
