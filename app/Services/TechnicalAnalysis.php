<?php

namespace App\Services;

/**
 * TechnicalAnalysis — Pure PHP, zero dependencies.
 * Arrays are always ordered oldest → newest.
 */
class TechnicalAnalysis
{
    // ── Moving Averages ──────────────────────────────────────────────────────

    public function sma(array $c, int $period): ?float
    {
        if (count($c) < $period) return null;
        return round(array_sum(array_slice($c, -$period)) / $period, 4);
    }

    /** Returns full EMA series aligned to input length (NaN-filled at start) */
    public function emaAll(array $c, int $period): array
    {
        $n = count($c);
        if ($n < $period) return array_fill(0, $n, null);
        $k      = 2 / ($period + 1);
        $result = array_fill(0, $period - 1, null);
        $ema    = array_sum(array_slice($c, 0, $period)) / $period;
        $result[] = $ema;
        for ($i = $period; $i < $n; $i++) {
            $ema      = $c[$i] * $k + $ema * (1 - $k);
            $result[] = round($ema, 4);
        }
        return $result;
    }

    public function ema(array $c, int $period): ?float
    {
        $all = $this->emaAll($c, $period);
        $v   = end($all);
        return $v !== null ? round($v, 4) : null;
    }

    /** Full SMA series */
    public function smaAll(array $c, int $period): array
    {
        $n      = count($c);
        $result = array_fill(0, $period - 1, null);
        for ($i = $period - 1; $i < $n; $i++) {
            $result[] = round(array_sum(array_slice($c, $i - $period + 1, $period)) / $period, 4);
        }
        return $result;
    }

    // ── RSI ──────────────────────────────────────────────────────────────────

    /** Full RSI series (Wilder smoothing) */
    public function rsiAll(array $c, int $period = 14): array
    {
        $n = count($c);
        if ($n < $period + 1) return array_fill(0, $n, null);

        $gains = $losses = [];
        for ($i = 1; $i < $n; $i++) {
            $d = $c[$i] - $c[$i - 1];
            $gains[]  = max($d, 0);
            $losses[] = max(-$d, 0);
        }

        $result   = array_fill(0, $period, null);
        $avgGain  = array_sum(array_slice($gains,  0, $period)) / $period;
        $avgLoss  = array_sum(array_slice($losses, 0, $period)) / $period;
        $result[] = $avgLoss == 0 ? 100.0 : round(100 - (100 / (1 + $avgGain / $avgLoss)), 2);

        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = ($avgGain * ($period - 1) + $gains[$i])  / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$i]) / $period;
            $result[] = $avgLoss == 0 ? 100.0 : round(100 - (100 / (1 + $avgGain / $avgLoss)), 2);
        }
        return $result;
    }

    public function rsi(array $c, int $period = 14): ?float
    {
        $all = $this->rsiAll($c, $period);
        $v   = end($all);
        return $v !== null ? $v : null;
    }

    // ── MACD ─────────────────────────────────────────────────────────────────

    /** Returns full series arrays: macd[], signal[], histogram[] aligned to input */
    public function macdSeries(array $c, int $fast = 12, int $slow = 26, int $sig = 9): array
    {
        $n        = count($c);
        $emaFast  = $this->emaAll($c, $fast);
        $emaSlow  = $this->emaAll($c, $slow);

        $macdLine = array_fill(0, $n, null);
        for ($i = $slow - 1; $i < $n; $i++) {
            if ($emaFast[$i] !== null && $emaSlow[$i] !== null) {
                $macdLine[$i] = round($emaFast[$i] - $emaSlow[$i], 6);
            }
        }

        // Signal = EMA9 of macdLine (only over non-null values)
        $macdValid = array_values(array_filter($macdLine, fn($v) => $v !== null));
        $sigSeries = $this->emaAll($macdValid, $sig);

        $sigAligned = array_fill(0, $n, null);
        $validIdx   = array_keys(array_filter($macdLine, fn($v) => $v !== null));
        foreach ($sigSeries as $j => $val) {
            if (isset($validIdx[$j])) $sigAligned[$validIdx[$j]] = $val;
        }

        $histLine = array_fill(0, $n, null);
        for ($i = 0; $i < $n; $i++) {
            if ($macdLine[$i] !== null && $sigAligned[$i] !== null) {
                $histLine[$i] = round($macdLine[$i] - $sigAligned[$i], 6);
            }
        }

        return ['macd' => $macdLine, 'signal' => $sigAligned, 'histogram' => $histLine];
    }

    public function macd(array $c, int $fast = 12, int $slow = 26, int $sig = 9): ?array
    {
        $series = $this->macdSeries($c, $fast, $slow, $sig);
        $macd   = end($series['macd']);
        $signal = end($series['signal']);
        $hist   = end($series['histogram']);
        if ($macd === null) return null;

        // Detect crossover: previous histogram vs current
        $prevHist = null;
        $hArr = array_values(array_filter($series['histogram'], fn($v) => $v !== null));
        if (count($hArr) >= 2) $prevHist = $hArr[count($hArr) - 2];

        $crossover = 'none';
        if ($prevHist !== null && $hist !== null) {
            if ($prevHist <= 0 && $hist > 0) $crossover = 'bullish_cross';
            elseif ($prevHist >= 0 && $hist < 0) $crossover = 'bearish_cross';
            elseif ($hist > 0) $crossover = 'bullish';
            else $crossover = 'bearish';
        }

        return [
            'macd'      => round($macd, 6),
            'signal'    => round($signal ?? 0, 6),
            'histogram' => round($hist ?? 0, 6),
            'crossover' => $crossover,
        ];
    }

    // ── Bollinger Bands ──────────────────────────────────────────────────────

    /** Full BB series: upper[], middle[], lower[] */
    public function bbSeries(array $c, int $period = 20, float $mult = 2.0): array
    {
        $n      = count($c);
        $upper  = array_fill(0, $n, null);
        $middle = array_fill(0, $n, null);
        $lower  = array_fill(0, $n, null);

        for ($i = $period - 1; $i < $n; $i++) {
            $slice    = array_slice($c, $i - $period + 1, $period);
            $mean     = array_sum($slice) / $period;
            $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $slice)) / $period;
            $std      = sqrt($variance);
            $upper[$i]  = round($mean + $mult * $std, 4);
            $middle[$i] = round($mean, 4);
            $lower[$i]  = round($mean - $mult * $std, 4);
        }
        return ['upper' => $upper, 'middle' => $middle, 'lower' => $lower];
    }

    public function bollingerBands(array $c, int $period = 20, float $mult = 2.0): ?array
    {
        if (count($c) < $period) return null;
        $slice    = array_slice($c, -$period);
        $mean     = array_sum($slice) / $period;
        $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $slice)) / $period;
        $std      = sqrt($variance);
        $last     = end($c);
        $upper    = $mean + $mult * $std;
        $lower    = $mean - $mult * $std;
        $pct      = $std > 0 ? ($last - $lower) / ($upper - $lower) : 0.5;
        return [
            'upper'  => round($upper, 4), 'middle' => round($mean, 4),
            'lower'  => round($lower, 4), 'width'  => round(($upper - $lower) / $mean * 100, 2),
            'pct_b'  => round($pct, 4),
        ];
    }

    // ── Stochastic %K/%D ─────────────────────────────────────────────────────

    public function stochastic(array $h, array $l, array $c, int $kP = 14, int $dP = 3): ?array
    {
        $n = count($c);
        if ($n < $kP) return null;
        $kValues = [];
        for ($i = $kP - 1; $i < $n; $i++) {
            $hs    = array_slice($h, $i - $kP + 1, $kP);
            $ls    = array_slice($l, $i - $kP + 1, $kP);
            $range = max($hs) - min($ls);
            $kValues[] = $range > 0 ? (($c[$i] - min($ls)) / $range) * 100 : 50;
        }
        $k = end($kValues);
        $d = count($kValues) >= $dP ? array_sum(array_slice($kValues, -$dP)) / $dP : $k;
        return ['k' => round($k, 2), 'd' => round($d, 2)];
    }

    // ── ATR ──────────────────────────────────────────────────────────────────

    public function atr(array $h, array $l, array $c, int $period = 14): ?float
    {
        $n = count($c);
        if ($n < $period + 1) return null;
        $tr = [];
        for ($i = 1; $i < $n; $i++) {
            $tr[] = max($h[$i] - $l[$i], abs($h[$i] - $c[$i - 1]), abs($l[$i] - $c[$i - 1]));
        }
        $atr = array_sum(array_slice($tr, 0, $period)) / $period;
        for ($i = $period; $i < count($tr); $i++) {
            $atr = ($atr * ($period - 1) + $tr[$i]) / $period;
        }
        return round($atr, 4);
    }

    // ── VWAP ─────────────────────────────────────────────────────────────────

    public function vwap(array $h, array $l, array $c, array $v): ?float
    {
        $tpv = $tv = 0;
        $n   = count($c);
        for ($i = 0; $i < $n; $i++) {
            $tp  = ($h[$i] + $l[$i] + $c[$i]) / 3;
            $tpv += $tp * $v[$i];
            $tv  += $v[$i];
        }
        return $tv > 0 ? round($tpv / $tv, 4) : null;
    }

    /** Full VWAP series (cumulative from first candle) */
    public function vwapSeries(array $h, array $l, array $c, array $v): array
    {
        $result = [];
        $tpv = $tv = 0;
        $n   = count($c);
        for ($i = 0; $i < $n; $i++) {
            $tp   = ($h[$i] + $l[$i] + $c[$i]) / 3;
            $tpv += $tp * $v[$i];
            $tv  += $v[$i];
            $result[] = $tv > 0 ? round($tpv / $tv, 4) : null;
        }
        return $result;
    }

    // ── Williams %R ──────────────────────────────────────────────────────────

    public function williamsR(array $h, array $l, array $c, int $period = 14): ?float
    {
        $n = count($c);
        if ($n < $period) return null;
        $hs    = array_slice($h, -$period);
        $ls    = array_slice($l, -$period);
        $range = max($hs) - min($ls);
        return $range > 0 ? round(((max($hs) - end($c)) / $range) * -100, 2) : null;
    }

    // ── CCI ──────────────────────────────────────────────────────────────────

    public function cci(array $h, array $l, array $c, int $period = 20): ?float
    {
        $n = count($c);
        if ($n < $period) return null;
        $tp    = array_map(fn($i) => ($h[$i] + $l[$i] + $c[$i]) / 3, range(0, $n - 1));
        $slice = array_slice($tp, -$period);
        $mean  = array_sum($slice) / $period;
        $md    = array_sum(array_map(fn($v) => abs($v - $mean), $slice)) / $period;
        return $md > 0 ? round((end($tp) - $mean) / (0.015 * $md), 2) : null;
    }

    // ── OBV (On Balance Volume) ──────────────────────────────────────────────

    /** Full OBV series */
    public function obvSeries(array $c, array $v): array
    {
        $result = [0];
        $obv    = 0;
        for ($i = 1; $i < count($c); $i++) {
            if ($c[$i] > $c[$i - 1])      $obv += $v[$i];
            elseif ($c[$i] < $c[$i - 1])  $obv -= $v[$i];
            $result[] = $obv;
        }
        return $result;
    }

    public function obv(array $c, array $v): float
    {
        $s = $this->obvSeries($c, $v);
        return end($s);
    }

    // ── Pivot Points (Classic) ───────────────────────────────────────────────

    public function pivotPoints(float $high, float $low, float $close): array
    {
        $p  = ($high + $low + $close) / 3;
        return [
            'pp' => round($p, 4),
            'r1' => round(2 * $p - $low, 4),
            'r2' => round($p + ($high - $low), 4),
            'r3' => round($high + 2 * ($p - $low), 4),
            's1' => round(2 * $p - $high, 4),
            's2' => round($p - ($high - $low), 4),
            's3' => round($low - 2 * ($high - $p), 4),
        ];
    }

    // ── Fibonacci Retracement ────────────────────────────────────────────────

    public function fibonacci(float $high, float $low): array
    {
        $diff = $high - $low;
        return [
            '0'    => round($high, 4),
            '23.6' => round($high - 0.236 * $diff, 4),
            '38.2' => round($high - 0.382 * $diff, 4),
            '50.0' => round($high - 0.500 * $diff, 4),
            '61.8' => round($high - 0.618 * $diff, 4),
            '78.6' => round($high - 0.786 * $diff, 4),
            '100'  => round($low, 4),
        ];
    }

    // ── Momentum ─────────────────────────────────────────────────────────────

    public function momentum(array $c, int $period = 10): ?float
    {
        $n = count($c);
        if ($n <= $period) return null;
        $base = $c[$n - 1 - $period];
        return $base != 0 ? round((end($c) / $base - 1) * 100, 4) : null;
    }

    // ── ADX (Average Directional Index) ─────────────────────────────────────

    public function adx(array $h, array $l, array $c, int $period = 14): ?array
    {
        $n = count($c);
        if ($n < $period * 2) return null;

        $trArr = $dmPlus = $dmMinus = [];
        for ($i = 1; $i < $n; $i++) {
            $trArr[]  = max($h[$i] - $l[$i], abs($h[$i] - $c[$i-1]), abs($l[$i] - $c[$i-1]));
            $up       = $h[$i] - $h[$i-1];
            $down     = $l[$i-1] - $l[$i];
            $dmPlus[]  = ($up > $down && $up > 0) ? $up : 0;
            $dmMinus[] = ($down > $up && $down > 0) ? $down : 0;
        }

        $atr14  = array_sum(array_slice($trArr,   0, $period)) / $period;
        $dmp14  = array_sum(array_slice($dmPlus,  0, $period)) / $period;
        $dmm14  = array_sum(array_slice($dmMinus, 0, $period)) / $period;

        for ($i = $period; $i < count($trArr); $i++) {
            $atr14 = ($atr14 * ($period - 1) + $trArr[$i])   / $period;
            $dmp14 = ($dmp14 * ($period - 1) + $dmPlus[$i])  / $period;
            $dmm14 = ($dmm14 * ($period - 1) + $dmMinus[$i]) / $period;
        }

        $pdi   = $atr14 > 0 ? 100 * $dmp14 / $atr14 : 0;
        $mdi   = $atr14 > 0 ? 100 * $dmm14 / $atr14 : 0;
        $dx    = ($pdi + $mdi) > 0 ? 100 * abs($pdi - $mdi) / ($pdi + $mdi) : 0;

        return [
            'adx'  => round($dx, 2),
            '+di'  => round($pdi, 2),
            '-di'  => round($mdi, 2),
            'trend'=> $dx > 25 ? ($pdi > $mdi ? 'strong_up' : 'strong_down') : 'weak',
        ];
    }

    // ── Master calculateAll ───────────────────────────────────────────────────

    /**
     * Run all indicators. Returns scalars for the latest bar + named series for charting.
     * Input arrays must be ordered oldest→newest, same length.
     */
    public function calculateAll(array $closes, array $highs, array $lows, array $volumes): array
    {
        $macd    = $this->macd($closes);
        $bb      = $this->bollingerBands($closes);
        $stoch   = $this->stochastic($highs, $lows, $closes);
        $adxData = $this->adx($highs, $lows, $closes);
        $prev_h  = count($highs) > 2 ? $highs[count($highs) - 2] : end($highs);
        $prev_l  = count($lows)  > 2 ? $lows[count($lows)  - 2] : end($lows);

        return [
            // Scalars
            'rsi_14'      => $this->rsi($closes),
            'ema_9'       => $this->ema($closes, 9),
            'ema_21'      => $this->ema($closes, 21),
            'ema_50'      => $this->ema($closes, 50),
            'sma_50'      => $this->sma($closes, 50),
            'sma_200'     => $this->sma($closes, 200),
            'macd'        => $macd,
            'bb'          => $bb,
            'stochastic'  => $stoch,
            'atr'         => $this->atr($highs, $lows, $closes),
            'vwap'        => $this->vwap($highs, $lows, $closes, $volumes),
            'momentum_5'  => $this->momentum($closes, 5),
            'momentum_10' => $this->momentum($closes, 10),
            'momentum_1'  => count($closes) >= 2
                               ? round((end($closes) / $closes[count($closes) - 2] - 1) * 100, 4)
                               : null,
            'williams_r'  => $this->williamsR($highs, $lows, $closes),
            'cci'         => $this->cci($highs, $lows, $closes),
            'obv'         => $this->obv($closes, $volumes),
            'adx'         => $adxData,
            'pivot'       => $this->pivotPoints($prev_h, $prev_l, $closes[count($closes) - 2] ?? end($closes)),
            'fib'         => count($closes) >= 50
                               ? $this->fibonacci(max(array_slice($closes, -50)), min(array_slice($closes, -50)))
                               : null,
            // Series for charts
            '_series' => [
                'closes'  => $closes,
                'highs'   => $highs,
                'lows'    => $lows,
                'volumes' => $volumes,
                'ema9'    => $this->emaAll($closes, 9),
                'ema21'   => $this->emaAll($closes, 21),
                'ema50'   => $this->emaAll($closes, 50),
                'sma200'  => $this->smaAll($closes, 200),
                'bb'      => $this->bbSeries($closes),
                'rsi'     => $this->rsiAll($closes),
                'macd'    => $this->macdSeries($closes),
                'vwap'    => $this->vwapSeries($highs, $lows, $closes, $volumes),
                'obv'     => $this->obvSeries($closes, $volumes),
            ],
        ];
    }
}
