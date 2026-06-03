<?php

namespace App\Http\Controllers;

use App\Models\Watchlist;
use App\Models\CompanyProfile;
use App\Services\FinnhubService;

class MarketController extends Controller
{
    public function __construct(private FinnhubService $finnhub) {}

    public function index()
    {
        $marketOpen = $this->finnhub->marketStatus('US');
        $news       = $this->finnhub->marketNews('general', 12);
        $watchlist  = Watchlist::where('active', true)
            ->with(['latestSnapshot', 'activeSignal'])
            ->orderByDesc('is_pinned')
            ->get();

        // Sector breakdown from company profiles
        $sectors = CompanyProfile::whereIn('ticker', $watchlist->pluck('ticker'))
            ->selectRaw('sector, count(*) as count')
            ->whereNotNull('sector')
            ->groupBy('sector')
            ->orderByDesc('count')
            ->pluck('count', 'sector');

        return view('market', compact('marketOpen', 'news', 'watchlist', 'sectors'));
    }

    public function screener()
    {
        $profiles = CompanyProfile::all()->keyBy('ticker');
        $watchlist = Watchlist::where('active', true)
            ->with(['latestSnapshot', 'activeSignal'])
            ->get()
            ->map(function ($item) use ($profiles) {
                $item->profile = $profiles[$item->ticker] ?? null;
                return $item;
            });

        return view('screener', compact('watchlist'));
    }
}
