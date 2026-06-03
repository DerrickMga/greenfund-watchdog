@extends('layouts.app')
@section('title', 'Market Overview — GreenFund Watchdog')

@section('content')

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-lg font-bold text-white">Market Overview</h1>
        <div class="text-xs text-gray-600 mt-0.5">
            {{ $marketOpen ? '🟢 US Markets Open' : '🔴 US Markets Closed' }} ·
            {{ now()->format('l, d M Y H:i') }} UTC
        </div>
    </div>
</div>

{{-- ── Sector Heatmap ── --}}
@if($sectors->count() > 0)
<div class="card p-4 mb-5">
    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Watchlist by Sector</h2>
    <div class="flex flex-wrap gap-3">
        @foreach($sectors as $sector => $count)
        <div class="flex items-center gap-2 bg-gray-800/60 rounded-lg px-3 py-2 border border-gray-700">
            <span class="text-gray-300 text-sm">{{ $sector }}</span>
            <span class="bg-green-900/40 text-green-400 text-xs px-2 py-0.5 rounded font-mono">{{ $count }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── Watchlist Overview Grid ── --}}
<div class="card mb-5">
    <div class="px-4 py-3 border-b border-gray-800">
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">All Watchlist Counters</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="text-gray-600 uppercase tracking-widest border-b border-gray-800">
                    <th class="text-left px-4 py-2.5">Ticker</th>
                    <th class="text-left px-3 py-2.5">Company</th>
                    <th class="text-left px-3 py-2.5">Sector</th>
                    <th class="text-right px-3 py-2.5">Price</th>
                    <th class="text-right px-3 py-2.5">Change</th>
                    <th class="text-right px-3 py-2.5">RSI</th>
                    <th class="text-center px-3 py-2.5">MACD</th>
                    <th class="text-center px-3 py-2.5">Signal</th>
                    <th class="text-center px-3 py-2.5">Conf</th>
                    <th class="text-right px-3 py-2.5">Scanned</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/40">
                @foreach($watchlist as $item)
                @php $snap = $item->latestSnapshot; $sig = $item->activeSignal; @endphp
                <tr class="ticker-row" onclick="window.location='/ticker/{{ $item->ticker }}'">
                    <td class="px-4 py-2.5 font-mono font-bold text-white">
                        @if($item->is_pinned)<span class="text-green-500 mr-1">★</span>@endif
                        {{ $item->ticker }}
                    </td>
                    <td class="px-3 py-2.5 text-gray-400 max-w-[180px] truncate">{{ $item->company_name }}</td>
                    <td class="px-3 py-2.5 text-gray-600">{{ $item->sector }}</td>
                    <td class="px-3 py-2.5 text-right font-mono text-white">
                        {{ $snap?->price ? '$'.number_format($snap->price, 2) : '—' }}
                    </td>
                    <td class="px-3 py-2.5 text-right font-mono">
                        @if($snap?->change_percent !== null)
                        <span class="{{ $snap->change_percent >= 0 ? 'text-green-400' : 'text-red-400' }}">
                            {{ ($snap->change_percent >= 0 ? '+' : '') . number_format($snap->change_percent, 2) }}%
                        </span>
                        @else <span class="text-gray-700">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-right font-mono">
                        @if($snap?->rsi_14)
                        <span class="{{ $snap->rsi_14 < 30 ? 'text-green-400 font-bold' : ($snap->rsi_14 > 70 ? 'text-red-400 font-bold' : 'text-gray-300') }}">
                            {{ number_format($snap->rsi_14, 1) }}
                        </span>
                        @else <span class="text-gray-700">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        @if($snap?->macd)
                        <span class="text-xs px-1.5 py-0.5 rounded {{ $snap->macd >= 0 ? 'bg-green-900/30 text-green-500' : 'bg-red-900/30 text-red-500' }}">
                            {{ $snap->macd >= 0 ? '▲' : '▼' }}
                        </span>
                        @else <span class="text-gray-700">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        @if($sig)
                        <span class="px-2 py-0.5 rounded border text-xs font-bold
                            {{ $sig->action==='BUY'?'signal-buy':($sig->action==='SELL'?'signal-sell':($sig->action==='WATCH'?'signal-watch':'signal-hold')) }}">
                            {{ $sig->action }}
                        </span>
                        @else <span class="text-gray-700">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-center text-gray-500">{{ $sig?->confidence ? $sig->confidence.'%' : '—' }}</td>
                    <td class="px-3 py-2.5 text-right text-gray-700">{{ $snap?->captured_at?->diffForHumans() ?? 'Never' }}</td>
                    <td class="px-3 py-2.5">
                        <a href="{{ route('ticker.show', $item->ticker) }}" onclick="event.stopPropagation()"
                           class="text-green-700 hover:text-green-400 transition">→</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ── Market News ── --}}
<div class="card">
    <div class="px-4 py-3 border-b border-gray-800">
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Market News</h2>
    </div>
    <div class="divide-y divide-gray-800/40">
        @forelse($news as $n)
        <a href="{{ $n['url'] }}" target="_blank" rel="noopener"
           class="flex gap-4 px-4 py-3 hover:bg-gray-800/20 transition">
            @if($n['image'] ?? null)
            <img src="{{ $n['image'] }}" alt="" class="w-14 h-14 rounded object-cover shrink-0 bg-gray-800">
            @endif
            <div class="min-w-0 flex-1">
                <div class="text-sm text-gray-200 font-medium line-clamp-2">{{ $n['headline'] }}</div>
                <div class="text-xs text-gray-600 mt-1 flex gap-3">
                    <span>{{ $n['source'] ?? '' }}</span>
                    <span>{{ $n['datetime'] ? date('d M H:i', $n['datetime']) : '' }}</span>
                </div>
                @if($n['summary'] ?? null)
                <div class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $n['summary'] }}</div>
                @endif
            </div>
        </a>
        @empty
        <div class="px-4 py-8 text-center text-gray-700 text-xs">No market news cached</div>
        @endforelse
    </div>
</div>

@endsection
