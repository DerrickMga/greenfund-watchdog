@extends('layouts.app')
@section('title', $entry->ticker . ' — GreenFund Watchdog')

@section('content')

{{-- ── Header ── --}}
<div class="flex flex-wrap items-start justify-between gap-4 mb-5">
    <div class="flex items-center gap-4">
        @if($profile?->logo)
        <img src="{{ $profile->logo }}" alt="{{ $entry->ticker }}" class="w-10 h-10 rounded-lg object-contain bg-white/5 p-1">
        @endif
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-white font-mono">{{ $entry->ticker }}</h1>
                <span class="text-gray-500 text-sm">{{ $profile?->name ?? $entry->company_name }}</span>
                @if($entry->is_pinned)
                <span class="text-green-500 text-sm">★ Pinned</span>
                @endif
            </div>
            <div class="flex items-center gap-3 text-xs text-gray-600 mt-1">
                <span>{{ $profile?->exchange ?? $entry->exchange }}</span>
                <span>·</span>
                <span>{{ $profile?->industry ?? $entry->sector }}</span>
                @if($profile?->market_cap)
                <span>·</span>
                <span>Market Cap: {{ $profile->marketCapFormatted() }}</span>
                @endif
                @if($profile?->beta)
                <span>·</span>
                <span>β {{ number_format($profile->beta, 2) }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        {{-- Active signal badge --}}
        @php $sig = $entry->activeSignal; @endphp
        @if($sig)
        <div class="text-center">
            <span class="px-3 py-1.5 rounded border text-sm font-bold
                {{ $sig->action==='BUY' ? 'signal-buy' : ($sig->action==='SELL' ? 'signal-sell' : ($sig->action==='WATCH' ? 'signal-watch' : 'signal-hold')) }}">
                {{ $sig->action }} · {{ $sig->strength }}
            </span>
            <div class="text-xs text-gray-600 mt-1">{{ $sig->confidence }}% confidence</div>
        </div>
        @endif

        <button id="scanBtn" onclick="triggerScan('{{ $entry->ticker }}')"
                class="px-4 py-2 bg-green-900/40 hover:bg-green-900/60 border border-green-700 text-green-400 rounded text-xs font-bold transition">
            ⚡ Scan Now
        </button>
    </div>
</div>

{{-- ── 7-Day Forecast Strategy ── --}}
<div class="card p-4 mb-5">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">7-Day Predictive Strategy</h3>
            <div class="text-[11px] text-gray-600 mt-1">Model output for timing buy/release decisions</div>
        </div>
        @if($forecast)
        <span class="px-2 py-1 rounded border text-xs font-bold
            {{ $forecast->recommendation==='BUY' ? 'signal-buy' : ($forecast->recommendation==='RELEASE' ? 'signal-sell' : ($forecast->recommendation==='WATCH' ? 'signal-watch' : 'signal-hold')) }}">
            {{ $forecast->recommendation }}
        </span>
        @endif
    </div>

    @if($forecast)
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mt-4 text-xs">
        <div class="bg-gray-900/40 border border-gray-800 rounded p-2">
            <div class="text-gray-600">Current</div>
            <div class="text-white font-mono text-sm">${{ number_format($forecast->current_price, 4) }}</div>
        </div>
        <div class="bg-gray-900/40 border border-gray-800 rounded p-2">
            <div class="text-gray-600">Predicted</div>
            <div class="text-blue-400 font-mono text-sm">${{ number_format($forecast->predicted_price, 4) }}</div>
        </div>
        <div class="bg-gray-900/40 border border-gray-800 rounded p-2">
            <div class="text-gray-600">Expected Return</div>
            <div class="font-mono text-sm {{ $forecast->expected_return_pct >= 0 ? 'text-green-400' : 'text-red-400' }}">
                {{ $forecast->expected_return_pct >= 0 ? '+' : '' }}{{ number_format($forecast->expected_return_pct, 2) }}%
            </div>
        </div>
        <div class="bg-gray-900/40 border border-gray-800 rounded p-2">
            <div class="text-gray-600">Confidence</div>
            <div class="text-white font-mono text-sm">{{ $forecast->confidence }}%</div>
        </div>
        <div class="bg-gray-900/40 border border-gray-800 rounded p-2">
            <div class="text-gray-600">Entry Zone</div>
            <div class="text-yellow-400 font-mono text-sm">${{ $forecast->entry_price ? number_format($forecast->entry_price, 4) : '—' }}</div>
        </div>
        <div class="bg-gray-900/40 border border-gray-800 rounded p-2">
            <div class="text-gray-600">Forecast Date</div>
            <div class="text-gray-300 font-mono text-sm">{{ $forecast->forecast_for?->format('d M') ?? '—' }}</div>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3 mt-3 text-xs">
        <div class="bg-green-950/20 border border-green-900/40 rounded p-2">
            <div class="text-green-700">Take Profit</div>
            <div class="text-green-400 font-mono text-sm">${{ $forecast->take_profit_price ? number_format($forecast->take_profit_price, 4) : '—' }}</div>
        </div>
        <div class="bg-red-950/20 border border-red-900/40 rounded p-2">
            <div class="text-red-700">Stop Loss</div>
            <div class="text-red-400 font-mono text-sm">${{ $forecast->stop_loss_price ? number_format($forecast->stop_loss_price, 4) : '—' }}</div>
        </div>
    </div>
    @else
    <div class="mt-3 text-xs text-gray-600">No forecast found for this ticker. Generate with <span class="font-mono">php artisan watchdog:forecast --ticker={{ $entry->ticker }} --horizon=7</span>.</div>
    @endif
</div>

{{-- ── Live Price Bar (loaded via JS) ── --}}
<div id="priceBar" class="card p-4 mb-5" x-data="priceBar('{{ $entry->ticker }}')" x-init="load()">
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
        <div>
            <div class="text-xs text-gray-600 mb-1">Price</div>
            <div class="text-2xl font-bold font-mono text-white" x-text="d.price ? '$'+parseFloat(d.price).toFixed(4) : '—'"></div>
            <div class="text-sm font-mono mt-0.5"
                 :class="(d.change_pct||0)>=0?'text-green-400':'text-red-400'"
                 x-text="d.change_pct != null ? fmtPct(d.change_pct) : ''"></div>
        </div>
        <div>
            <div class="text-xs text-gray-600 mb-1">Open / Close</div>
            <div class="text-sm font-mono text-gray-300" x-text="d.open ? '$'+parseFloat(d.open).toFixed(2) : '—'"></div>
            <div class="text-sm font-mono text-gray-500" x-text="d.prev_close ? '$'+parseFloat(d.prev_close).toFixed(2) : '—'"></div>
        </div>
        <div>
            <div class="text-xs text-gray-600 mb-1">Day H / L</div>
            <div class="text-sm font-mono text-green-400" x-text="d.high ? '$'+parseFloat(d.high).toFixed(2) : '—'"></div>
            <div class="text-sm font-mono text-red-400" x-text="d.low ? '$'+parseFloat(d.low).toFixed(2) : '—'"></div>
        </div>
        <div>
            <div class="text-xs text-gray-600 mb-1">52W High / Low</div>
            <div class="text-sm font-mono text-green-400">{{ $profile?->week52_high ? '$'.number_format($profile->week52_high,2) : '—' }}</div>
            <div class="text-sm font-mono text-red-400">{{ $profile?->week52_low ? '$'.number_format($profile->week52_low,2) : '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-600 mb-1">P/E · EPS</div>
            <div class="text-sm font-mono text-gray-300">{{ $profile?->pe_ttm ? number_format($profile->pe_ttm,2) : '—' }}</div>
            <div class="text-sm font-mono text-gray-500">{{ $profile?->eps_ttm ? '$'.number_format($profile->eps_ttm,4) : '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-600 mb-1">Analyst Target</div>
            @if($profile?->price_target_mean)
            <div class="text-sm font-mono text-blue-400">${{ number_format($profile->price_target_mean, 2) }}</div>
            @php $snap = $entry->latestSnapshot; @endphp
            @if($snap?->price && $profile->price_target_mean)
            @php $upside = round(($profile->price_target_mean - $snap->price) / $snap->price * 100, 1); @endphp
            <div class="text-xs font-mono {{ $upside >= 0 ? 'text-green-500' : 'text-red-500' }}">
                {{ $upside >= 0 ? '+' : '' }}{{ $upside }}% upside
            </div>
            @endif
            @else
            <div class="text-sm text-gray-600">—</div>
            @endif
        </div>
    </div>
</div>

{{-- ── Indicator Cards ── --}}
@php $snap = $entry->latestSnapshot; @endphp
<div class="grid grid-cols-3 md:grid-cols-6 gap-3 mb-5">
    @php
    $indCards = [
        ['label'=>'RSI 14', 'val'=> $snap?->rsi_14 ? number_format($snap->rsi_14,1) : '—',
         'color'=> ($snap?->rsi_14 ?? 50) < 30 ? 'text-green-400' : (($snap?->rsi_14 ?? 50) > 70 ? 'text-red-400' : 'text-gray-200')],
        ['label'=>'EMA 9',  'val'=> $snap?->ema_9   ? '$'.number_format($snap->ema_9,2) : '—', 'color'=>'text-blue-400'],
        ['label'=>'EMA 21', 'val'=> $snap?->ema_21  ? '$'.number_format($snap->ema_21,2) : '—', 'color'=>'text-purple-400'],
        ['label'=>'MACD',   'val'=> $snap?->macd     ? number_format($snap->macd,4) : '—',
         'color'=> ($snap?->macd ?? 0) >= 0 ? 'text-green-400' : 'text-red-400'],
        ['label'=>'ATR 14', 'val'=> $snap?->atr ? number_format($snap->atr,4) : '—', 'color'=>'text-yellow-400'],
        ['label'=>'Stoch K','val'=> $snap?->stoch_k ? number_format($snap->stoch_k,1) : '—',
         'color'=> ($snap?->stoch_k ?? 50) < 20 ? 'text-green-400' : (($snap?->stoch_k ?? 50) > 80 ? 'text-red-400' : 'text-gray-200')],
    ];
    @endphp
    @foreach($indCards as $card)
    <div class="card p-3">
        <div class="text-xs text-gray-600 mb-1">{{ $card['label'] }}</div>
        <div class="text-lg font-bold font-mono {{ $card['color'] }}">{{ $card['val'] }}</div>
    </div>
    @endforeach
</div>

{{-- ── Charts Area ── --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">

    {{-- Price + EMA + BB Chart (2/3 width) --}}
    <div class="xl:col-span-2 card p-4" x-data="tickerCharts('{{ $entry->ticker }}')" x-init="load()">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Price Chart</h3>
            <div class="flex gap-1">
                <button @click="setRange('intraday')" :class="range==='intraday'?'bg-green-900/50 text-green-400':'text-gray-600 hover:text-gray-300'"
                        class="text-xs px-2.5 py-1 rounded border border-gray-800 transition">1D</button>
                <button @click="setRange('daily')" :class="range==='daily'?'bg-green-900/50 text-green-400':'text-gray-600 hover:text-gray-300'"
                        class="text-xs px-2.5 py-1 rounded border border-gray-800 transition">1Y</button>
            </div>
        </div>
        <div class="relative" style="height:300px">
            <canvas id="priceChart"></canvas>
        </div>

        {{-- Volume chart --}}
        <div class="mt-3 relative" style="height:60px">
            <canvas id="volumeChart"></canvas>
        </div>
    </div>

    {{-- RSI + MACD Side Panel --}}
    <div class="space-y-4">
        {{-- RSI --}}
        <div class="card p-4">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">RSI (14)</h3>
            <div class="relative" style="height:120px">
                <canvas id="rsiChart"></canvas>
            </div>
        </div>

        {{-- MACD --}}
        <div class="card p-4">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">MACD (12,26,9)</h3>
            <div class="relative" style="height:120px">
                <canvas id="macdChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── Fundamentals + Analyst + Pivots Row ── --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">

    {{-- Fundamentals --}}
    <div class="card p-4">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Fundamentals</h3>
        <div class="space-y-2 text-xs">
            @foreach([
                ['P/E (TTM)', $profile?->pe_ttm ? number_format($profile->pe_ttm,2) : '—'],
                ['P/B', $profile?->pb_ratio ? number_format($profile->pb_ratio,2) : '—'],
                ['P/S', $profile?->ps_ratio ? number_format($profile->ps_ratio,2) : '—'],
                ['ROE', $profile?->roe ? number_format($profile->roe,2).'%' : '—'],
                ['ROI', $profile?->roi ? number_format($profile->roi,2).'%' : '—'],
                ['Debt/Equity', $profile?->debt_equity ? number_format($profile->debt_equity,2) : '—'],
                ['Current Ratio', $profile?->current_ratio ? number_format($profile->current_ratio,2) : '—'],
                ['Div Yield', $profile?->div_yield ? number_format($profile->div_yield,4).'%' : '—'],
                ['Beta', $profile?->beta ? number_format($profile->beta,2) : '—'],
            ] as [$k, $v])
            <div class="flex justify-between items-center border-b border-gray-800/40 pb-1.5">
                <span class="text-gray-600">{{ $k }}</span>
                <span class="text-gray-200 font-mono">{{ $v }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Analyst Consensus --}}
    <div class="card p-4">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Analyst Consensus</h3>
        @if($profile && ($profile->analyst_buy || $profile->analyst_hold || $profile->analyst_sell))
        @php
            $total = ($profile->analyst_buy??0) + ($profile->analyst_hold??0) + ($profile->analyst_sell??0);
            $buyPct  = $total > 0 ? round($profile->analyst_buy  / $total * 100) : 0;
            $holdPct = $total > 0 ? round($profile->analyst_hold / $total * 100) : 0;
            $sellPct = $total > 0 ? round($profile->analyst_sell / $total * 100) : 0;
        @endphp
        <div class="text-center mb-4">
            <div class="text-xl font-bold
                {{ $buyPct >= 60 ? 'text-green-400' : ($sellPct >= 50 ? 'text-red-400' : 'text-yellow-400') }}">
                {{ $profile->analystConsensus() }}
            </div>
            <div class="text-xs text-gray-600">{{ $total }} analysts</div>
        </div>
        <div class="space-y-2 text-xs">
            <div>
                <div class="flex justify-between mb-1"><span class="text-green-500">BUY</span><span class="text-gray-400">{{ $profile->analyst_buy ?? 0 }} ({{ $buyPct }}%)</span></div>
                <div class="bg-gray-800 rounded-full h-1.5"><div class="bg-green-500 h-1.5 rounded-full" style="width:{{ $buyPct }}%"></div></div>
            </div>
            <div>
                <div class="flex justify-between mb-1"><span class="text-yellow-500">HOLD</span><span class="text-gray-400">{{ $profile->analyst_hold ?? 0 }} ({{ $holdPct }}%)</span></div>
                <div class="bg-gray-800 rounded-full h-1.5"><div class="bg-yellow-500 h-1.5 rounded-full" style="width:{{ $holdPct }}%"></div></div>
            </div>
            <div>
                <div class="flex justify-between mb-1"><span class="text-red-500">SELL</span><span class="text-gray-400">{{ $profile->analyst_sell ?? 0 }} ({{ $sellPct }}%)</span></div>
                <div class="bg-gray-800 rounded-full h-1.5"><div class="bg-red-500 h-1.5 rounded-full" style="width:{{ $sellPct }}%"></div></div>
            </div>
        </div>
        @if($profile->price_target_mean)
        <div class="mt-4 pt-3 border-t border-gray-800 text-xs space-y-1">
            <div class="flex justify-between"><span class="text-gray-600">Mean target</span><span class="text-blue-400 font-mono">${{ number_format($profile->price_target_mean,2) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-600">High</span><span class="text-green-400 font-mono">${{ number_format($profile->price_target_high??0,2) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-600">Low</span><span class="text-red-400 font-mono">${{ number_format($profile->price_target_low??0,2) }}</span></div>
        </div>
        @endif
        @else
        <div class="text-xs text-gray-700 py-4 text-center">No analyst data cached<br><span class="text-gray-600">Run a full scan to populate</span></div>
        @endif
    </div>

    {{-- Pivot Points (from latest snapshot) --}}
    <div class="card p-4" x-data="pivotPanel('{{ $entry->ticker }}')" x-init="load()">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Pivot Points & Fibonacci</h3>
        <div x-show="!pivot && !fib" class="text-xs text-gray-700 py-4 text-center">Loading…</div>
        <template x-if="pivot">
            <div class="space-y-1.5 text-xs">
                <div class="flex justify-between"><span class="text-red-400">R3</span><span class="font-mono text-gray-300" x-text="'$'+parseFloat(pivot.r3).toFixed(2)"></span></div>
                <div class="flex justify-between"><span class="text-red-300">R2</span><span class="font-mono text-gray-300" x-text="'$'+parseFloat(pivot.r2).toFixed(2)"></span></div>
                <div class="flex justify-between"><span class="text-red-200">R1</span><span class="font-mono text-gray-300" x-text="'$'+parseFloat(pivot.r1).toFixed(2)"></span></div>
                <div class="flex justify-between border-y border-gray-700 py-1 my-1">
                    <span class="text-white font-bold">PP</span><span class="font-mono text-white font-bold" x-text="'$'+parseFloat(pivot.pp).toFixed(2)"></span>
                </div>
                <div class="flex justify-between"><span class="text-green-200">S1</span><span class="font-mono text-gray-300" x-text="'$'+parseFloat(pivot.s1).toFixed(2)"></span></div>
                <div class="flex justify-between"><span class="text-green-300">S2</span><span class="font-mono text-gray-300" x-text="'$'+parseFloat(pivot.s2).toFixed(2)"></span></div>
                <div class="flex justify-between"><span class="text-green-400">S3</span><span class="font-mono text-gray-300" x-text="'$'+parseFloat(pivot.s3).toFixed(2)"></span></div>
            </div>
        </template>
        <template x-if="fib">
            <div class="mt-3 pt-3 border-t border-gray-800 space-y-1 text-xs">
                <div class="text-gray-600 mb-1">Fibonacci (50-bar)</div>
                <template x-for="[level, val] in Object.entries(fib)" :key="level">
                    <div class="flex justify-between">
                        <span class="text-yellow-700" x-text="level+'%'"></span>
                        <span class="font-mono text-gray-400" x-text="'$'+parseFloat(val).toFixed(2)"></span>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>

{{-- ── Signal History + News Row ── --}}
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">

    {{-- Signal History --}}
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-800">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Signal History</h3>
        </div>
        <div class="overflow-y-auto" style="max-height:320px">
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-gray-600 uppercase tracking-widest border-b border-gray-800">
                        <th class="text-left px-4 py-2">Action</th>
                        <th class="text-right px-3 py-2">Price</th>
                        <th class="text-right px-3 py-2">Target</th>
                        <th class="text-right px-3 py-2">Stop</th>
                        <th class="text-right px-3 py-2">Conf</th>
                        <th class="text-right px-3 py-2">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/40">
                    @forelse($signals as $s)
                    <tr class="hover:bg-gray-800/20 transition">
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 rounded border text-xs font-bold
                                {{ $s->action==='BUY' ? 'signal-buy' : ($s->action==='SELL' ? 'signal-sell' : ($s->action==='WATCH' ? 'signal-watch' : 'signal-hold')) }}">
                                {{ $s->action }}
                            </span>
                            <span class="text-gray-600 ml-1">{{ $s->strength }}</span>
                        </td>
                        <td class="px-3 py-2 text-right font-mono text-gray-300">${{ number_format($s->price_at_signal,4) }}</td>
                        <td class="px-3 py-2 text-right font-mono text-green-600">${{ $s->target_price ? number_format($s->target_price,4) : '—' }}</td>
                        <td class="px-3 py-2 text-right font-mono text-red-600">${{ $s->stop_loss ? number_format($s->stop_loss,4) : '—' }}</td>
                        <td class="px-3 py-2 text-right text-gray-400">{{ $s->confidence }}%</td>
                        <td class="px-3 py-2 text-right text-gray-600">{{ $s->triggered_at->format('m/d H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="py-6 text-center text-gray-700">No signals yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- News Feed --}}
    <div class="card overflow-hidden" x-data="newsFeed('{{ $entry->ticker }}')" x-init="load()">
        <div class="px-4 py-3 border-b border-gray-800">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Latest News</h3>
        </div>
        <div class="divide-y divide-gray-800/40 overflow-y-auto" style="max-height:320px">
            <template x-for="n in news" :key="n.id">
                <a :href="n.url" target="_blank" rel="noopener"
                   class="flex gap-3 px-4 py-3 hover:bg-gray-800/20 transition">
                    <img x-show="n.image" :src="n.image" alt="" class="w-12 h-12 rounded object-cover shrink-0 bg-gray-800">
                    <div class="min-w-0">
                        <div class="text-xs text-gray-300 font-medium line-clamp-2" x-text="n.headline"></div>
                        <div class="text-xs text-gray-600 mt-1 flex gap-2">
                            <span x-text="n.source"></span>
                            <span x-text="new Date(n.datetime*1000).toLocaleDateString()"></span>
                        </div>
                    </div>
                </a>
            </template>
            <template x-if="news.length === 0 && !loading">
                <div class="px-4 py-6 text-center text-gray-700 text-xs">No recent news found</div>
            </template>
            <template x-if="loading">
                <div class="px-4 py-6 text-center text-gray-700 text-xs">Loading news…</div>
            </template>
        </div>
    </div>
</div>

{{-- ── Alerts for this ticker ── --}}
@if($alerts->count() > 0)
<div class="card mb-5">
    <div class="px-4 py-3 border-b border-gray-800">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Ticker Alerts</h3>
    </div>
    <div class="divide-y divide-gray-800/40 max-h-48 overflow-y-auto">
        @foreach($alerts as $a)
        <div class="flex items-start gap-3 px-4 py-2.5 text-xs">
            <span class="px-1.5 py-0.5 rounded mt-0.5 shrink-0
                {{ $a->severity==='CRITICAL'?'bg-red-900/50 text-red-400':($a->severity==='WARNING'?'bg-yellow-900/50 text-yellow-400':'bg-gray-800 text-gray-400') }}">
                {{ $a->severity }}
            </span>
            <div>
                <div class="font-semibold text-white">{{ $a->title }}</div>
                <div class="text-gray-500 mt-0.5">{{ $a->message }}</div>
            </div>
            <span class="ml-auto text-gray-700 shrink-0">{{ $a->alerted_at->diffForHumans() }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
const TICKER = '{{ $entry->ticker }}';
let priceChartInst, volumeChartInst, rsiChartInst, macdChartInst;

function priceBar(t) {
    return {
        d: {},
        async load() {
            try {
                const r = await fetch('/api/watchlist');
                const list = await r.json();
                this.d = list.find(x => x.ticker === t) || {};
            } catch(e) {}
            setTimeout(() => this.load(), 30000);
        }
    }
}

function pivotPanel(t) {
    return {
        pivot: null, fib: null,
        async load() {
            try {
                const r = await fetch('/api/analytics/'+t+'/snapshots');
                const d = await r.json();
                this.pivot = d.pivot || null;
                this.fib   = d.fib   || null;
            } catch(e) {}
        }
    }
}

function newsFeed(t) {
    return {
        news: [], loading: true,
        async load() {
            try {
                const r = await fetch('/api/watchlist/'+t+'/news');
                this.news = await r.json();
            } catch(e) {}
            this.loading = false;
        }
    }
}

function tickerCharts(t) {
    return {
        range: 'intraday',
        chartData: null,

        async load() {
            await this.loadRange(this.range);
        },

        async setRange(r) {
            this.range = r;
            await this.loadRange(r);
        },

        async loadRange(r) {
            try {
                const res = await fetch('/api/analytics/'+t);
                const d   = await res.json();
                this.chartData = r === 'daily' ? d.chart_daily : d.chart_intraday;
                this.renderAll(this.chartData);

                // Update pivot panel
                if (d.chart_intraday?.pivot) {
                    // Dispatch event for pivot panel
                }
            } catch(e) { console.error('Chart load failed', e); }
        },

        renderAll(d) {
            if (!d || !d.timestamps) return;
            const labels = d.timestamps.map(t => {
                const dt = new Date(t);
                return this.range === 'intraday'
                    ? dt.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})
                    : dt.toLocaleDateString([], {month:'short', day:'numeric'});
            });

            this.renderPrice(labels, d);
            this.renderVolume(labels, d);
            this.renderRsi(labels, d);
            this.renderMacd(labels, d);
        },

        renderPrice(labels, d) {
            const ctx = document.getElementById('priceChart');
            if (!ctx) return;
            if (priceChartInst) priceChartInst.destroy();
            const datasets = [
                { label:'Price', data: d.closes, borderColor:'#22c55e', borderWidth:1.5,
                  fill: false, tension:0.1, pointRadius:0 },
            ];
            if (d.ema9)  datasets.push({ label:'EMA9',  data:d.ema9,  borderColor:'#60a5fa', borderWidth:1, pointRadius:0, borderDash:[], fill:false, tension:0.1 });
            if (d.ema21) datasets.push({ label:'EMA21', data:d.ema21, borderColor:'#a78bfa', borderWidth:1, pointRadius:0, borderDash:[4,2], fill:false, tension:0.1 });
            if (d.ema50) datasets.push({ label:'EMA50', data:d.ema50, borderColor:'#fb923c', borderWidth:1, pointRadius:0, borderDash:[6,3], fill:false, tension:0.1 });
            if (d.bb_upper && d.bb_lower) {
                datasets.push({ label:'BB Upper', data:d.bb_upper, borderColor:'rgba(107,114,128,.4)', borderWidth:1, pointRadius:0, fill:false, tension:0.1 });
                datasets.push({ label:'BB Lower', data:d.bb_lower, borderColor:'rgba(107,114,128,.4)', borderWidth:1, pointRadius:0, fill:'-1', backgroundColor:'rgba(107,114,128,.05)', tension:0.1 });
            }
            if (d.vwap) datasets.push({ label:'VWAP', data:d.vwap, borderColor:'#f59e0b', borderWidth:1, borderDash:[3,3], pointRadius:0, fill:false, tension:0.1 });

            priceChartInst = new Chart(ctx, {
                type:'line', data:{ labels, datasets },
                options:{ ...CHART_DEFAULTS,
                    plugins:{ ...CHART_DEFAULTS.plugins,
                        legend:{ display:true, position:'top', labels:{ color:'#6b7280', font:{size:9}, boxWidth:12 } }
                    }
                }
            });
        },

        renderVolume(labels, d) {
            const ctx = document.getElementById('volumeChart');
            if (!ctx || !d.volumes) return;
            if (volumeChartInst) volumeChartInst.destroy();
            const colors = (d.closes||[]).map((c,i) => i>0 && c >= (d.closes[i-1]||0) ? 'rgba(34,197,94,.5)' : 'rgba(239,68,68,.5)');
            volumeChartInst = new Chart(ctx, {
                type:'bar', data:{ labels, datasets:[{ label:'Volume', data:d.volumes, backgroundColor:colors }] },
                options:{ ...CHART_DEFAULTS, scales:{
                    x:{ ...CHART_DEFAULTS.scales.x, display:false },
                    y:{ ...CHART_DEFAULTS.scales.y, ticks:{ color:'#374151', font:{size:9} } }
                }}
            });
        },

        renderRsi(labels, d) {
            const ctx = document.getElementById('rsiChart');
            if (!ctx || !d.rsi) return;
            if (rsiChartInst) rsiChartInst.destroy();
            rsiChartInst = new Chart(ctx, {
                type:'line',
                data:{ labels, datasets:[{
                    label:'RSI', data:d.rsi, borderColor:'#a78bfa', borderWidth:1.5,
                    pointRadius:0, fill:false, tension:0.1
                }]},
                options:{ ...CHART_DEFAULTS,
                    scales:{
                        x:{ ...CHART_DEFAULTS.scales.x, display:false },
                        y:{ ...CHART_DEFAULTS.scales.y, min:0, max:100,
                            ticks:{ color:'#6b7280', font:{size:9} } }
                    },
                    plugins:{ ...CHART_DEFAULTS.plugins,
                        annotation:{ annotations:{
                            ob:{ type:'line', yMin:70, yMax:70, borderColor:'rgba(239,68,68,.4)', borderWidth:1, borderDash:[4,2] },
                            os:{ type:'line', yMin:30, yMax:30, borderColor:'rgba(34,197,94,.4)', borderWidth:1, borderDash:[4,2] },
                        }}
                    }
                }
            });
        },

        renderMacd(labels, d) {
            const ctx = document.getElementById('macdChart');
            if (!ctx || !d.macd_line) return;
            if (macdChartInst) macdChartInst.destroy();
            const histColors = (d.macd_hist||[]).map(v => (v||0) >= 0 ? 'rgba(34,197,94,.6)' : 'rgba(239,68,68,.6)');
            macdChartInst = new Chart(ctx, {
                type:'bar',
                data:{ labels, datasets:[
                    { type:'bar',  label:'Histogram', data:d.macd_hist, backgroundColor:histColors, yAxisID:'y' },
                    { type:'line', label:'MACD',      data:d.macd_line, borderColor:'#60a5fa', borderWidth:1.5, pointRadius:0, fill:false, tension:0.1, yAxisID:'y' },
                    { type:'line', label:'Signal',    data:d.macd_signal, borderColor:'#f87171', borderWidth:1.5, pointRadius:0, fill:false, tension:0.1, yAxisID:'y' },
                ]},
                options:{ ...CHART_DEFAULTS,
                    scales:{
                        x:{ ...CHART_DEFAULTS.scales.x, display:false },
                        y:{ ...CHART_DEFAULTS.scales.y, ticks:{ color:'#6b7280', font:{size:9} } }
                    }
                }
            });
        }
    }
}

async function triggerScan(ticker) {
    const btn = document.getElementById('scanBtn');
    btn.textContent = '⏳ Scanning…';
    btn.disabled = true;
    try {
        await fetch('/scan/'+ticker, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
        });
        setTimeout(() => location.reload(), 1200);
    } catch(e) {
        btn.textContent = '⚡ Scan Now';
        btn.disabled = false;
    }
}
</script>
@endpush
