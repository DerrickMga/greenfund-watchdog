<?php

namespace App\Services;

class SignalEngine
{
    /**
     * Full scoring engine — 10 rules, score range -20 to +20.
     *
     * Rules (max points each):
     *   RSI oversold/overbought          ±3
     *   MACD crossover                   ±3
     *   EMA9 vs EMA21 cross              ±2
     *   Price vs VWAP                    ±1
     *   Bollinger Band %B position       ±2
     *   Stochastic %K/%D                 ±2
     *   Momentum 5-period                ±2
     *   Williams %R                      ±2
     *   CCI                              ±2
     *   ADX trend confirmation           ±1 (confirms direction only)
     *
     * Score ≥ 4   → BUY   (STRONG ≥7, MODERATE 4-6, WEAK = exact 4 sometimes)
     * Score ≤ -4  → SELL  (mirrors above)
     * Score ±1-3  → WATCH
     * Score  0    → HOLD
     */
    public function evaluate(array $ind, float $currentPrice): array
    {
        $score    = 0;
        $reasons  = [];
        $adx      = $ind['adx']        ?? null;
        $adxVal   = $adx['adx']        ?? 0;
        $adxTrend = $adx['trend']      ?? 'weak';

        // ── Rule 1: RSI ────────────────────────────────────────────────────
        $rsi = $ind['rsi_14'] ?? null;
        if ($rsi !== null) {
            if ($rsi < 20)       { $score += 3; $reasons[] = "RSI extremely oversold ({$rsi})"; }
            elseif ($rsi < 30)   { $score += 2; $reasons[] = "RSI oversold ({$rsi})"; }
            elseif ($rsi < 40)   { $score += 1; $reasons[] = "RSI approaching oversold ({$rsi})"; }
            elseif ($rsi > 80)   { $score -= 3; $reasons[] = "RSI extremely overbought ({$rsi})"; }
            elseif ($rsi > 70)   { $score -= 2; $reasons[] = "RSI overbought ({$rsi})"; }
            elseif ($rsi > 60)   { $score -= 1; $reasons[] = "RSI approaching overbought ({$rsi})"; }
        }

        // ── Rule 2: MACD ───────────────────────────────────────────────────
        $macd = $ind['macd'] ?? null;
        if ($macd) {
            $cross = $macd['crossover'] ?? 'none';
            if ($cross === 'bullish_cross')      { $score += 3; $reasons[] = 'MACD bullish crossover'; }
            elseif ($cross === 'bearish_cross')  { $score -= 3; $reasons[] = 'MACD bearish crossover'; }
            elseif ($cross === 'bullish')        { $score += 2; $reasons[] = 'MACD above signal line'; }
            elseif ($cross === 'bearish')        { $score -= 2; $reasons[] = 'MACD below signal line'; }
        }

        // ── Rule 3: EMA9 vs EMA21 ─────────────────────────────────────────
        $ema9  = $ind['ema_9']  ?? null;
        $ema21 = $ind['ema_21'] ?? null;
        if ($ema9 && $ema21) {
            if ($ema9 > $ema21) { $score += 2; $reasons[] = 'EMA9 above EMA21 (bullish cross)'; }
            else                { $score -= 2; $reasons[] = 'EMA9 below EMA21 (bearish cross)'; }
        }

        // ── Rule 4: Price vs VWAP ─────────────────────────────────────────
        $vwap = $ind['vwap'] ?? null;
        if ($vwap && $currentPrice > 0) {
            if ($currentPrice > $vwap * 1.005) { $score += 1; $reasons[] = 'Price above VWAP'; }
            elseif ($currentPrice < $vwap * 0.995) { $score -= 1; $reasons[] = 'Price below VWAP'; }
        }

        // ── Rule 5: Bollinger Band %B ─────────────────────────────────────
        $bb = $ind['bb'] ?? null;
        if ($bb) {
            $pct = $bb['pct_b'] ?? 0.5;
            if ($pct < 0.05)       { $score += 2; $reasons[] = 'Price at lower Bollinger Band'; }
            elseif ($pct < 0.2)    { $score += 1; $reasons[] = 'Price near lower Bollinger Band'; }
            elseif ($pct > 0.95)   { $score -= 2; $reasons[] = 'Price at upper Bollinger Band'; }
            elseif ($pct > 0.8)    { $score -= 1; $reasons[] = 'Price near upper Bollinger Band'; }
        }

        // ── Rule 6: Stochastic ────────────────────────────────────────────
        $stoch = $ind['stochastic'] ?? null;
        if ($stoch) {
            $k = $stoch['k'] ?? 50;
            $d = $stoch['d'] ?? 50;
            if ($k < 20 && $d < 20)      { $score += 2; $reasons[] = "Stochastic oversold (K={$k})"; }
            elseif ($k < 20)             { $score += 1; $reasons[] = "Stochastic %K oversold ({$k})"; }
            elseif ($k > 80 && $d > 80)  { $score -= 2; $reasons[] = "Stochastic overbought (K={$k})"; }
            elseif ($k > 80)             { $score -= 1; $reasons[] = "Stochastic %K overbought ({$k})"; }
        }

        // ── Rule 7: Momentum 5-period ────────────────────────────────────
        $mom = $ind['momentum_5'] ?? null;
        if ($mom !== null) {
            if ($mom > 3)        { $score += 2; $reasons[] = "Strong positive momentum ({$mom}%)"; }
            elseif ($mom > 1)    { $score += 1; $reasons[] = "Positive momentum ({$mom}%)"; }
            elseif ($mom < -3)   { $score -= 2; $reasons[] = "Strong negative momentum ({$mom}%)"; }
            elseif ($mom < -1)   { $score -= 1; $reasons[] = "Negative momentum ({$mom}%)"; }
        }

        // ── Rule 8: Williams %R ──────────────────────────────────────────
        $willR = $ind['williams_r'] ?? null;
        if ($willR !== null) {
            if ($willR < -80)    { $score += 2; $reasons[] = "Williams %R oversold ({$willR})"; }
            elseif ($willR < -60){ $score += 1; $reasons[] = "Williams %R approaching oversold"; }
            elseif ($willR > -20){ $score -= 2; $reasons[] = "Williams %R overbought ({$willR})"; }
            elseif ($willR > -40){ $score -= 1; $reasons[] = "Williams %R approaching overbought"; }
        }

        // ── Rule 9: CCI ──────────────────────────────────────────────────
        $cci = $ind['cci'] ?? null;
        if ($cci !== null) {
            if ($cci < -150)     { $score += 2; $reasons[] = "CCI extremely oversold ({$cci})"; }
            elseif ($cci < -100) { $score += 1; $reasons[] = "CCI oversold ({$cci})"; }
            elseif ($cci > 150)  { $score -= 2; $reasons[] = "CCI extremely overbought ({$cci})"; }
            elseif ($cci > 100)  { $score -= 1; $reasons[] = "CCI overbought ({$cci})"; }
        }

        // ── Rule 10: ADX trend confirmation ──────────────────────────────
        if ($adxVal >= 25) {
            if (str_contains($adxTrend, 'up'))   { $score += 1; $reasons[] = "ADX confirms strong uptrend ({$adxVal})"; }
            elseif (str_contains($adxTrend, 'down')) { $score -= 1; $reasons[] = "ADX confirms strong downtrend ({$adxVal})"; }
        }

        // ── Classify ─────────────────────────────────────────────────────
        if ($score >= 7)       { $action = 'BUY';  $strength = 'STRONG'; }
        elseif ($score >= 4)   { $action = 'BUY';  $strength = 'MODERATE'; }
        elseif ($score >= 2)   { $action = 'BUY';  $strength = 'WEAK'; }
        elseif ($score <= -7)  { $action = 'SELL'; $strength = 'STRONG'; }
        elseif ($score <= -4)  { $action = 'SELL'; $strength = 'MODERATE'; }
        elseif ($score <= -2)  { $action = 'SELL'; $strength = 'WEAK'; }
        elseif ($score !== 0)  { $action = 'WATCH'; $strength = 'MODERATE'; }
        else                   { $action = 'HOLD';  $strength = 'WEAK'; }

        // Confidence = % of max possible score (20)
        $confidence = min(100, (int) round(abs($score) / 20 * 100));

        // Price targets using ATR
        $atr = $ind['atr'] ?? ($currentPrice * 0.01);
        $target = $action === 'SELL'
            ? round($currentPrice - 2 * $atr, 4)
            : round($currentPrice + 2 * $atr, 4);
        $stop = $action === 'SELL'
            ? round($currentPrice + 1.5 * $atr, 4)
            : round($currentPrice - 1.5 * $atr, 4);

        return [
            'action'       => $action,
            'strength'     => $strength,
            'confidence'   => $confidence,
            'score'        => $score,
            'reasoning'    => implode('; ', $reasons) ?: 'Neutral conditions',
            'target_price' => $target,
            'stop_loss'    => $stop,
        ];
    }

    /** Detect threshold-crossing alerts from two consecutive snapshots */
    public function detectAlerts(array $current, array $previous, float $price, float $prevPrice): array
    {
        $alerts = [];
        $ticker = $current['ticker'] ?? '';

        // Price spike / drop (>1% in 1 min)
        if ($prevPrice > 0) {
            $changePct = ($price - $prevPrice) / $prevPrice * 100;
            if ($changePct >= 1.5) {
                $alerts[] = ['type' => 'PRICE_SPIKE', 'severity' => 'CRITICAL',
                    'title' => "Price spike on {$ticker}", 'trigger' => $changePct,
                    'message' => sprintf('%s surged %.2f%% in one minute ($%.4f → $%.4f)', $ticker, $changePct, $prevPrice, $price)];
            } elseif ($changePct <= -1.5) {
                $alerts[] = ['type' => 'PRICE_DROP', 'severity' => 'CRITICAL',
                    'title' => "Flash drop on {$ticker}", 'trigger' => $changePct,
                    'message' => sprintf('%s dropped %.2f%% in one minute ($%.4f → $%.4f)', $ticker, $changePct, $prevPrice, $price)];
            }
        }

        // RSI crossings
        $rsiNow  = $current['rsi_14']  ?? null;
        $rsiPrev = $previous['rsi_14'] ?? null;
        if ($rsiNow !== null && $rsiPrev !== null) {
            if ($rsiPrev < 70 && $rsiNow >= 70) {
                $alerts[] = ['type' => 'RSI_OVERBOUGHT', 'severity' => 'WARNING',
                    'title' => "{$ticker} RSI entered overbought", 'trigger' => $rsiNow,
                    'message' => "RSI crossed above 70 (now {$rsiNow}). Consider taking profits."];
            }
            if ($rsiPrev > 30 && $rsiNow <= 30) {
                $alerts[] = ['type' => 'RSI_OVERSOLD', 'severity' => 'WARNING',
                    'title' => "{$ticker} RSI entered oversold", 'trigger' => $rsiNow,
                    'message' => "RSI crossed below 30 (now {$rsiNow}). Potential reversal."];
            }
        }

        // MACD crossover
        $macdNow  = $current['macd']  ?? null;
        $macdPrev = $previous['macd'] ?? null;
        if ($macdNow && $macdPrev) {
            $histNow  = $macdNow['histogram']  ?? 0;
            $histPrev = $macdPrev['histogram'] ?? 0;
            if ($histPrev <= 0 && $histNow > 0) {
                $alerts[] = ['type' => 'MACD_CROSSOVER', 'severity' => 'INFO',
                    'title' => "{$ticker} MACD bullish crossover", 'trigger' => $histNow,
                    'message' => "MACD histogram crossed above zero — bullish momentum shift."];
            } elseif ($histPrev >= 0 && $histNow < 0) {
                $alerts[] = ['type' => 'MACD_CROSSOVER', 'severity' => 'INFO',
                    'title' => "{$ticker} MACD bearish crossover", 'trigger' => $histNow,
                    'message' => "MACD histogram crossed below zero — bearish momentum shift."];
            }
        }

        // Volume surge (>3x average)
        $vol3m = $current['avg_volume_3m'] ?? null;
        $volNow = $current['volume'] ?? null;
        if ($vol3m && $volNow && $vol3m > 0 && $volNow > $vol3m * 3) {
            $alerts[] = ['type' => 'VOLUME_SURGE', 'severity' => 'WARNING',
                'title' => "{$ticker} unusual volume", 'trigger' => $volNow,
                'message' => sprintf('Volume %.0fx above 3-month average', $volNow / $vol3m)];
        }

        return $alerts;
    }
}
