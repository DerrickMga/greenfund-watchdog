@extends('layouts.app')
@section('title', 'Dashboard — GreenFund Watchdog')

@section('content')

{{-- ── KPI Bar ── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <div class="stat-card gradient-green p-4 rounded-xl border border-green-900/40">
        <div class="text-3xl font-bold text-green-400">{{ $signalCounts['buy'] }}</div>
        <div class="text-xs text-green-700 mt-1 uppercase tracking-wider">BUY Signals</div>
    </div>
    <div class="stat-card gradient-red p-4 rounded-xl border border-red-900/40">
        <div class="text-3xl font-bold text-red-400">{{ $signalCounts['sell'] }}</div>
        <div class="text-xs text-red-700 mt-1 uppercase tracking-wider">SELL Signals</div>
    </div>
    <div class="stat-card gradient-yellow p-4 rounded-xl border border-yellow-900/40">
        <div class="text-3xl font-bold text-yellow-400">{{ $signalCounts['watch'] }}</div>
        <div class="text-xs text-yellow-700 mt-1 uppercase tracking-wider">WATCH</div>
    </div>
    <div class="stat-card gradient-blue p-4 rounded-xl border border-blue-900/40">
        <div class="text-3xl font-bold text-blue-400">{{ $unreadAlerts->count() }}</div>
        <div class="text-xs text-blue-700 mt-1 uppercase tracking-wider">Unread Alerts</div>
    </div>
</div>

{{-- ── Forecast Opportunities (7D) ── --}}
<div class="card p-4 mb-5">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Forecast Opportunities (7D)</h2>
        <span class="text-[11px] text-gray-600">Model: trend_regression_v1</span>
    </div>
    @if(($topForecasts ?? collect())->count() > 0)
    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
        @foreach($topForecasts as $f)
        <a href="{{ route('ticker.show', $f->ticker) }}"
           class="p-2 rounded-lg border transition hover:border-gray-600 {{ $f->expected_return_pct >= 0 ? 'border-green-900/40 bg-green-950/20' : 'border-red-900/40 bg-red-950/20' }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-white font-mono">{{ $f->ticker }}</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded border {{ $f->recommendation==='BUY' ? 'signal-buy' : ($f->recommendation==='RELEASE' ? 'signal-sell' : ($f->recommendation==='WATCH' ? 'signal-watch' : 'signal-hold')) }}">
                    {{ $f->recommendation }}
                </span>
            </div>
            <div class="mt-1 text-[11px] font-mono {{ $f->expected_return_pct >= 0 ? 'text-green-400' : 'text-red-400' }}">
                {{ $f->expected_return_pct >= 0 ? '+' : '' }}{{ number_format($f->expected_return_pct, 2) }}%
            </div>
            <div class="text-[11px] text-gray-500">Conf {{ $f->confidence }}%</div>
        </a>
        @endforeach
    </div>
    @else
    <div class="text-xs text-gray-600">No forecast rows yet. Run <span class="font-mono">php artisan watchdog:forecast --horizon=7</span>.</div>
    @endif
</div>

{{-- ── Top Movers ── --}}
<div class="card p-4 mb-5" x-data="movers()" x-init="load()">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Top Movers</h2>
        <button @click="load()" class="text-xs text-gray-600 hover:text-gray-300 transition">↺</button>
    </div>
    <div class="grid grid-cols-5 md:grid-cols-10 gap-2">
        <template x-for="m in rows" :key="m.ticker">
            <a :href="'/ticker/'+m.ticker"
               class="text-center p-2 rounded-lg border transition hover:border-gray-600"
               :class="(m.change_pct||0) >= 0 ? 'border-green-900/40 bg-green-950/20' : 'border-red-900/40 bg-red-950/20'">
                <div class="text-xs font-bold text-white" x-text="m.ticker"></div>
                <div class="text-xs mt-1 font-mono"
                     :class="(m.change_pct||0) >= 0 ? 'text-green-400' : 'text-red-400'"
                     x-text="fmtPct(m.change_pct)"></div>
            </a>
        </template>
        <template x-if="rows.length === 0">
            <div class="col-span-10 text-center text-gray-600 text-xs py-2">Loading movers…</div>
        </template>
    </div>
</div>

{{-- ── Main Watchlist Table + Signals column ── --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">

    {{-- Watchlist Table --}}
    <div class="xl:col-span-2 card overflow-hidden" x-data="watchlistTable()" x-init="init()">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-800">
            <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Live Watchlist</h2>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-600" x-text="lastRefresh"></span>
                <button @click="refresh()" :disabled="loading"
                        class="text-xs bg-gray-800 hover:bg-gray-700 px-2.5 py-1 rounded border border-gray-700 disabled:opacity-40 transition">
                    <span x-show="!loading">↺ Refresh</span>
                    <span x-show="loading" x-cloak>…</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-gray-600 uppercase tracking-widest border-b border-gray-800/60">
                        <th class="text-left px-4 py-2.5">Ticker</th>
                        <th class="text-right px-3 py-2.5">Price</th>
                        <th class="text-right px-3 py-2.5">Chg%</th>
                        <th class="text-right px-3 py-2.5">RSI</th>
                        <th class="text-center px-3 py-2.5">Signal</th>
                        <th class="text-center px-3 py-2.5">Conf</th>
                        <th class="text-center px-3 py-2.5">MACD</th>
                        <th class="text-center px-3 py-2.5">Fcst</th>
                        <th class="text-right px-3 py-2.5">Fcst%</th>
                        <th class="px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/40">
                    <template x-if="rows.length === 0">
                        <tr><td colspan="10" class="py-8 text-center text-gray-600">Loading…</td></tr>
                    </template>
                    <template x-for="r in rows" :key="r.ticker">
                        <tr class="ticker-row" @click="window.location='/ticker/'+r.ticker">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <span x-show="r.is_pinned" class="text-green-500 text-xs">★</span>
                                    <span class="font-bold text-white font-mono" x-text="r.ticker"></span>
                                </div>
                                <div class="text-gray-600 truncate max-w-[100px] mt-0.5" x-text="r.company_name"></div>
                            </td>
                            <td class="px-3 py-2.5 text-right font-mono">
                                <span class="text-white" x-text="r.price ? '$'+parseFloat(r.price).toFixed(2) : '—'"></span>
                            </td>
                            <td class="px-3 py-2.5 text-right font-mono">
                                <span :class="(r.change_percent||0) >= 0 ? 'text-green-400' : 'text-red-400'"
                                      x-text="r.change_percent != null ? fmtPct(r.change_percent) : '—'"></span>
                            </td>
                            <td class="px-3 py-2.5 text-right font-mono">
                                <span :style="'color:'+rsiColor(r.rsi_14)"
                                      x-text="r.rsi_14 != null ? parseFloat(r.rsi_14).toFixed(1) : '—'"></span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span x-show="r.signal" :class="signalClass(r.signal)" x-text="r.signal"></span>
                                <span x-show="!r.signal" class="text-gray-700">—</span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <div class="w-12 bg-gray-800 rounded-full h-1 mx-auto">
                                    <div class="h-1 rounded-full transition-all"
                                         :style="'width:'+(r.confidence||0)+'%'"
                                         :class="(r.confidence||0)>60?'bg-green-500':(r.confidence||0)>30?'bg-yellow-500':'bg-gray-600'">
                                    </div>
                                </div>
                                <div class="text-gray-500 mt-0.5" x-text="(r.confidence||0)+'%'"></div>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="text-xs px-1.5 py-0.5 rounded"
                                      :class="r.macd_cross==='bullish'||r.macd_cross==='bullish_cross'
                                          ? 'bg-green-900/30 text-green-500'
                                          : 'bg-red-900/30 text-red-500'"
                                      x-text="r.macd_cross ? (r.macd_cross.includes('bullish')?'▲':'▼') : '—'">
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span x-show="r.forecast && r.forecast.recommendation"
                                      :class="signalClass(r.forecast.recommendation)"
                                      x-text="r.forecast.recommendation"></span>
                                <span x-show="!r.forecast || !r.forecast.recommendation" class="text-gray-700">—</span>
                            </td>
                            <td class="px-3 py-2.5 text-right font-mono">
                                <span x-show="r.forecast && r.forecast.expected_return_pct != null"
                                      :class="(r.forecast.expected_return_pct||0) >= 0 ? 'text-green-400' : 'text-red-400'"
                                      x-text="fmtPct(r.forecast.expected_return_pct)"></span>
                                <span x-show="!r.forecast || r.forecast.expected_return_pct == null" class="text-gray-700">—</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <a :href="'/ticker/'+r.ticker" @click.stop
                                   class="text-green-700 hover:text-green-400 transition">→</a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Signals Feed --}}
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-800">
            <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Active Signals</h2>
        </div>
        <div class="overflow-y-auto" style="max-height:480px">
            @forelse($recentSignals as $signal)
            <a href="{{ route('ticker.show', $signal->ticker) }}"
               class="flex items-start gap-3 px-4 py-3 border-b border-gray-800/40 hover:bg-gray-800/20 transition">
                <span class="mt-0.5 px-2 py-0.5 rounded border text-xs font-bold shrink-0
                    {{ $signal->action==='BUY' ? 'signal-buy' : ($signal->action==='SELL' ? 'signal-sell' : ($signal->action==='WATCH' ? 'signal-watch' : 'signal-hold')) }}">
                    {{ $signal->action }}
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-bold text-white font-mono">{{ $signal->ticker }}
                        <span class="text-gray-600 font-normal ml-1">{{ $signal->strength }}</span>
                    </div>
                    <div class="text-xs text-gray-500 truncate mt-0.5">{{ Str::limit($signal->reasoning, 60) }}</div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-xs font-mono text-white">${{ number_format($signal->price_at_signal, 2) }}</div>
                    @if($signal->target_price)
                    <div class="text-xs text-green-700">→ ${{ number_format($signal->target_price, 2) }}</div>
                    @endif
                </div>
            </a>
            @empty
            <div class="px-4 py-8 text-center text-gray-700 text-xs">
                No active signals yet<br>Run a scan to populate
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ── Alerts Feed ── --}}
@if($unreadAlerts->count() > 0)
<div class="card mb-5">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">
            Watchdog Alerts
            <span class="ml-2 bg-red-900/50 text-red-400 text-xs px-1.5 py-0.5 rounded">{{ $unreadAlerts->count() }}</span>
        </h2>
        <button onclick="ackAll()" class="text-xs text-gray-600 hover:text-gray-300 transition">Dismiss all</button>
    </div>
    <div class="divide-y divide-gray-800/40 max-h-48 overflow-y-auto">
        @foreach($unreadAlerts as $alert)
        <div class="flex items-start gap-3 px-4 py-2.5">
            <span class="text-xs px-1.5 py-0.5 rounded mt-0.5 shrink-0 font-mono
                {{ $alert->severity==='CRITICAL' ? 'bg-red-900/50 text-red-400' : ($alert->severity==='WARNING' ? 'bg-yellow-900/50 text-yellow-400' : 'bg-gray-800 text-gray-400') }}">
                {{ $alert->severity }}
            </span>
            <div class="min-w-0 flex-1">
                <div class="text-xs font-semibold text-white">{{ $alert->title }}</div>
                <div class="text-xs text-gray-500 mt-0.5">{{ $alert->message }}</div>
            </div>
            <div class="text-right shrink-0">
                <a href="{{ route('ticker.show', $alert->ticker) }}"
                   class="text-xs text-green-700 font-mono hover:text-green-400">{{ $alert->ticker }}</a>
                <div class="text-xs text-gray-700 mt-0.5">{{ $alert->alerted_at->diffForHumans() }}</div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
function movers() {
    return {
        rows: [],
        async load() {
            try {
                const r = await fetch('/api/analytics/movers/top');
                this.rows = await r.json();
            } catch(e) {}
        }
    }
}

function watchlistTable() {
    return {
        rows: [], loading: false, lastRefresh: '',
        init() { this.refresh(); setInterval(() => this.refresh(), 60000); },
        async refresh() {
            this.loading = true;
            try {
                const r = await fetch('/api/watchlist');
                this.rows = await r.json();
                this.lastRefresh = new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',second:'2-digit'});
            } catch(e) {}
            finally { this.loading = false; }
        }
    }
}

async function ackAll() {
    await fetch('/api/alerts/acknowledge-all', {
        method:'POST',
        headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}
    });
    location.reload();
}
</script>
@endpush
