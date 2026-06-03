<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FinnhubService
{
    private string $apiKey;
    private string $baseUrl = 'https://finnhub.io/api/v1';

    public function __construct()
    {
        $this->apiKey = config('services.finnhub.key');
    }

    /** Unified GET helper with caching */
    private function get(string $endpoint, array $params = [], int $ttl = 60): ?array
    {
        $cacheKey = 'fh_' . md5($endpoint . serialize($params));
        return Cache::remember($cacheKey, $ttl, function () use ($endpoint, $params) {
            try {
                $response = Http::timeout(12)->get(
                    "{$this->baseUrl}{$endpoint}",
                    array_merge($params, ['token' => $this->apiKey])
                );
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable $e) {
                Log::warning("Finnhub {$endpoint} failed: " . $e->getMessage());
            }
            return null;
        });
    }

    // ── Real-time Quote ──────────────────────────────────────────────────────
    /** c=current, o=open, h=high, l=low, pc=prev_close, d=change, dp=change% */
    public function quote(string $ticker): ?array
    {
        $data = $this->get('/quote', ['symbol' => $ticker], 30);
        return ($data && ($data['c'] ?? 0) > 0) ? $data : null;
    }

    // ── Candles / OHLCV ─────────────────────────────────────────────────────
    /** resolution: 1,5,15,30,60,D,W,M */
    public function candles(string $ticker, string $resolution = '1', int $from = 0, int $to = 0): ?array
    {
        if ($to === 0)   $to   = time();
        if ($from === 0) $from = $to - (60 * 120);
        $ttl = $resolution === '1' ? 58 : ($resolution === 'D' ? 3600 : 300);
        $data = $this->get('/stock/candle', [
            'symbol' => $ticker, 'resolution' => $resolution,
            'from' => $from, 'to' => $to,
        ], $ttl);
        return ($data && ($data['s'] ?? '') === 'ok') ? $data : null;
    }

    /** Daily OHLCV for past N days — good for 50/200MA and longer charts */
    public function dailyCandles(string $ticker, int $days = 365): ?array
    {
        $to   = time();
        $from = $to - (86400 * $days);
        return $this->candles($ticker, 'D', $from, $to);
    }

    // ── Company Profile ──────────────────────────────────────────────────────
    public function companyProfile(string $ticker): ?array
    {
        return $this->get('/stock/profile2', ['symbol' => $ticker], 3600 * 6);
    }

    // ── Basic Financials / Metrics ───────────────────────────────────────────
    public function basicFinancials(string $ticker): ?array
    {
        $data = $this->get('/stock/metric', ['symbol' => $ticker, 'metric' => 'all'], 3600);
        return $data['metric'] ?? null;
    }

    // ── Earnings / EPS ───────────────────────────────────────────────────────
    public function earningsSurprises(string $ticker, int $limit = 8): array
    {
        $data = $this->get('/stock/earnings', ['symbol' => $ticker, 'limit' => $limit], 3600 * 12);
        return $data ?? [];
    }

    public function earningsCalendar(string $ticker): ?array
    {
        $from = date('Y-m-d');
        $to   = date('Y-m-d', strtotime('+90 days'));
        $data = $this->get('/calendar/earnings', ['symbol' => $ticker, 'from' => $from, 'to' => $to], 3600 * 4);
        return $data['earningsCalendar'][0] ?? null;
    }

    // ── Analyst Recommendations ──────────────────────────────────────────────
    public function recommendations(string $ticker): array
    {
        $data = $this->get('/stock/recommendation', ['symbol' => $ticker], 3600 * 4);
        return is_array($data) ? array_slice($data, 0, 3) : [];
    }

    public function priceTarget(string $ticker): ?array
    {
        return $this->get('/stock/price-target', ['symbol' => $ticker], 3600 * 4);
    }

    // ── Peer Group ───────────────────────────────────────────────────────────
    public function peers(string $ticker): array
    {
        $data = $this->get('/stock/peers', ['symbol' => $ticker], 3600 * 6);
        return is_array($data) ? array_slice($data, 0, 8) : [];
    }

    // ── Insider Transactions ─────────────────────────────────────────────────
    public function insiderTransactions(string $ticker): array
    {
        $data = $this->get('/stock/insider-transactions', ['symbol' => $ticker], 3600 * 6);
        $txns = $data['data'] ?? [];
        return array_slice($txns, 0, 10);
    }

    // ── Institutional Ownership ──────────────────────────────────────────────
    public function institutionalOwnership(string $ticker): array
    {
        $data = $this->get('/institutional/ownership', ['symbol' => $ticker], 3600 * 12);
        $holders = $data['ownership'] ?? [];
        return array_slice($holders, 0, 5);
    }

    // ── Company News ─────────────────────────────────────────────────────────
    public function companyNews(string $ticker, int $limit = 8): array
    {
        $to   = date('Y-m-d');
        $from = date('Y-m-d', strtotime('-7 days'));
        $data = $this->get('/company-news', ['symbol' => $ticker, 'from' => $from, 'to' => $to], 600);
        return array_slice($data ?? [], 0, $limit);
    }

    // ── General Market News ──────────────────────────────────────────────────
    public function marketNews(string $category = 'general', int $limit = 10): array
    {
        $data = $this->get('/news', ['category' => $category], 300);
        return array_slice($data ?? [], 0, $limit);
    }

    // ── Sentiment ────────────────────────────────────────────────────────────
    public function newsSentiment(string $ticker): ?array
    {
        return $this->get('/news-sentiment', ['symbol' => $ticker], 3600);
    }

    // ── Support & Resistance ─────────────────────────────────────────────────
    public function supportResistance(string $ticker, string $resolution = 'D'): ?array
    {
        return $this->get('/scan/support-resistance', ['symbol' => $ticker, 'resolution' => $resolution], 3600);
    }

    // ── Pattern Recognition ──────────────────────────────────────────────────
    public function patternRecognition(string $ticker, string $resolution = 'D'): ?array
    {
        return $this->get('/scan/pattern', ['symbol' => $ticker, 'resolution' => $resolution], 3600);
    }

    // ── Market Status ────────────────────────────────────────────────────────
    public function marketStatus(string $exchange = 'US'): bool
    {
        $data = $this->get('/stock/market-status', ['exchange' => $exchange], 60);
        return (bool) ($data['isOpen'] ?? false);
    }

    // ── Market Holiday ───────────────────────────────────────────────────────
    public function marketHoliday(string $exchange = 'US'): array
    {
        $data = $this->get('/stock/market-holiday', ['exchange' => $exchange], 3600 * 24);
        return $data['data'] ?? [];
    }

    // ── Symbol Lookup ────────────────────────────────────────────────────────
    public function symbolSearch(string $query): array
    {
        $data = $this->get('/search', ['q' => $query], 300);
        return array_slice($data['result'] ?? [], 0, 10);
    }

    // ── 52-week high/low + volume ratio from stored metrics ──────────────────
    /** Returns a rich snapshot combining quote + metrics + profile */
    public function richSnapshot(string $ticker): array
    {
        $quote   = $this->quote($ticker) ?? [];
        $metrics = $this->basicFinancials($ticker) ?? [];
        $profile = $this->companyProfile($ticker) ?? [];

        return [
            'ticker'         => $ticker,
            'name'           => $profile['name'] ?? null,
            'logo'           => $profile['logo'] ?? null,
            'industry'       => $profile['finnhubIndustry'] ?? null,
            'country'        => $profile['country'] ?? null,
            'market_cap'     => $profile['marketCapitalization'] ?? null,
            'shares_out'     => $profile['shareOutstanding'] ?? null,
            'ipo_date'       => $profile['ipo'] ?? null,
            'currency'       => $profile['currency'] ?? 'USD',
            'exchange'       => $profile['exchange'] ?? null,
            'weburl'         => $profile['weburl'] ?? null,
            // Quote
            'price'          => $quote['c']  ?? null,
            'open'           => $quote['o']  ?? null,
            'high'           => $quote['h']  ?? null,
            'low'            => $quote['l']  ?? null,
            'prev_close'     => $quote['pc'] ?? null,
            'change'         => $quote['d']  ?? null,
            'change_pct'     => $quote['dp'] ?? null,
            // Metrics
            'week52_high'    => $metrics['52WeekHigh'] ?? null,
            'week52_low'     => $metrics['52WeekLow']  ?? null,
            'pe_ttm'         => $metrics['peBasicExclExtraTTM'] ?? null,
            'eps_ttm'        => $metrics['epsBasicExclExtraItemsTTM'] ?? null,
            'revenue_ttm'    => $metrics['revenuePerShareTTM'] ?? null,
            'beta'           => $metrics['beta'] ?? null,
            'div_yield'      => $metrics['currentDividendYieldTTM'] ?? null,
            'pb_ratio'       => $metrics['pbAnnual'] ?? null,
            'ps_ratio'       => $metrics['psAnnual'] ?? null,
            'roe'            => $metrics['roeRfy'] ?? null,
            'roi'            => $metrics['roiRfy'] ?? null,
            'current_ratio'  => $metrics['currentRatioAnnual'] ?? null,
            'debt_equity'    => $metrics['totalDebt/totalEquityAnnual'] ?? null,
            '10d_avg_volume' => $metrics['10DayAverageTradingVolume'] ?? null,
            '3m_avg_volume'  => $metrics['3MonthAverageTradingVolume'] ?? null,
        ];
    }
}
