<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'GreenFund Watchdog')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { DEFAULT: '#22c55e', dark: '#16a34a' },
                    },
                    fontFamily: { mono: ['JetBrains Mono', 'Fira Code', 'monospace'] }
                }
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'JetBrains Mono', 'Fira Code', 'Courier New', monospace; }
        .signal-buy   { background: rgba(34,197,94,.15);  border-color: #22c55e; color: #4ade80; }
        .signal-sell  { background: rgba(239,68,68,.15);  border-color: #ef4444; color: #f87171; }
        .signal-hold  { background: rgba(107,114,128,.15);border-color: #6b7280; color: #9ca3af; }
        .signal-watch { background: rgba(234,179,8,.15);  border-color: #eab308; color: #facc15; }
        .pulse-dot  { animation: pulse-ring 2s ease infinite; }
        @keyframes pulse-ring { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(1.2)} }
        .ticker-row:hover { background: rgba(34,197,94,.04); cursor: pointer; }
        .card { background: #111827; border: 1px solid #1f2937; border-radius: .75rem; }
        .stat-card { background: linear-gradient(135deg, #111827 0%, #0f172a 100%); border: 1px solid #1f2937; border-radius: .75rem; }
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: #111827; }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 2px; }
        .gradient-green { background: linear-gradient(135deg, rgba(34,197,94,.2), rgba(34,197,94,.05)); }
        .gradient-red   { background: linear-gradient(135deg, rgba(239,68,68,.2), rgba(239,68,68,.05)); }
        .gradient-yellow{ background: linear-gradient(135deg, rgba(234,179,8,.2), rgba(234,179,8,.05)); }
        .gradient-blue  { background: linear-gradient(135deg, rgba(59,130,246,.2), rgba(59,130,246,.05)); }
    </style>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen" x-data="watchdog()" x-init="init()">

<!-- ── Top Navigation ─────────────────────────────────────────────────────── -->
<nav class="bg-gray-900/95 backdrop-blur border-b border-gray-800 sticky top-0 z-50">
    <div class="max-w-screen-2xl mx-auto px-4 flex items-center justify-between h-14">

        <!-- Brand -->
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-green-400 pulse-dot"></div>
                <a href="{{ route('dashboard') }}" class="text-green-400 font-bold text-base tracking-widest">
                    GREENFUND
                </a>
                <span class="text-gray-600 text-xs">WATCHDOG</span>
            </div>

            <!-- Nav links -->
            <div class="hidden md:flex items-center gap-1 ml-4">
                <a href="{{ route('dashboard') }}"
                   class="px-3 py-1.5 rounded text-xs font-medium transition
                          {{ request()->routeIs('dashboard') ? 'bg-green-900/30 text-green-400' : 'text-gray-400 hover:text-gray-200 hover:bg-gray-800' }}">
                    Dashboard
                </a>
                <a href="{{ route('market') }}"
                   class="px-3 py-1.5 rounded text-xs font-medium transition
                          {{ request()->routeIs('market') ? 'bg-blue-900/30 text-blue-400' : 'text-gray-400 hover:text-gray-200 hover:bg-gray-800' }}">
                    Market
                </a>
                <a href="{{ route('screener') }}"
                   class="px-3 py-1.5 rounded text-xs font-medium transition
                          {{ request()->routeIs('screener') ? 'bg-purple-900/30 text-purple-400' : 'text-gray-400 hover:text-gray-200 hover:bg-gray-800' }}">
                    Screener
                </a>
                <a href="{{ route('decision.console') }}"
                   class="px-3 py-1.5 rounded text-xs font-medium transition
                          {{ request()->routeIs('decision.console') ? 'bg-amber-900/30 text-amber-400' : 'text-gray-400 hover:text-gray-200 hover:bg-gray-800' }}">
                    Console
                </a>
            </div>
        </div>

        <!-- Search -->
        <div class="hidden md:flex items-center gap-2 flex-1 max-w-xs mx-6"
             x-data="{ q:'', results:[], open:false }">
            <div class="relative w-full">
                <input x-model="q"
                       @input.debounce.400ms="q.length>1 ? (fetch('/api/search/'+encodeURIComponent(q)).then(r=>r.json()).then(d=>{results=d;open=true})) : (open=false)"
                       @click.away="open=false"
                       placeholder="Search ticker or company…"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-1.5 text-xs text-gray-200 placeholder-gray-600 focus:outline-none focus:border-green-700" />
                <div x-show="open && results.length" x-cloak
                     class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-gray-700 rounded shadow-xl z-50 max-h-64 overflow-y-auto">
                    <template x-for="r in results" :key="r.symbol">
                        <a :href="'/ticker/'+r.symbol" @click="open=false"
                           class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 text-xs">
                            <span class="text-green-400 font-bold w-16 shrink-0" x-text="r.symbol"></span>
                            <span class="text-gray-400 truncate" x-text="r.description"></span>
                        </a>
                    </template>
                </div>
            </div>
        </div>

        <!-- Status bar -->
        <div class="flex items-center gap-4 text-xs">
            <div class="flex items-center gap-1.5">
                <div class="w-1.5 h-1.5 rounded-full"
                     :class="marketOpen ? 'bg-green-400' : 'bg-red-500'"></div>
                <span :class="marketOpen ? 'text-green-400' : 'text-gray-500'"
                      x-text="marketOpen ? 'Market Open' : 'Market Closed'"></span>
            </div>
            <div class="relative" @click.away="alertDropdown=false" x-data="{alertDropdown:false}">
                <button @click="alertDropdown=!alertDropdown"
                        class="flex items-center gap-1.5 px-2 py-1 rounded hover:bg-gray-800 transition">
                    <span class="text-red-400 font-bold" x-text="alertCount || 0"></span>
                    <span class="text-gray-500">alerts</span>
                    <span x-show="alertCount > 0" class="w-1.5 h-1.5 bg-red-500 rounded-full pulse-dot"></span>
                </button>
            </div>
            <span class="text-gray-600" x-text="lastUpdate"></span>
        </div>
    </div>
</nav>

<!-- ── Critical Alert Banner ──────────────────────────────────────────────── -->
<div x-cloak x-show="criticalAlerts.length > 0"
     class="bg-red-950 border-b border-red-800/50 px-4 py-2">
    <div class="max-w-screen-2xl mx-auto flex items-center gap-3 text-sm">
        <span class="text-red-400 font-bold text-xs uppercase tracking-wider">⚠ Critical</span>
        <span class="text-red-300 text-xs" x-text="criticalAlerts[0]?.title"></span>
        <span class="text-red-500 text-xs" x-text="'— ' + (criticalAlerts[0]?.message || '')"></span>
        <button @click="acknowledgeAll()" class="ml-auto text-red-600 hover:text-red-400 text-xs transition">
            Dismiss all
        </button>
    </div>
</div>

<!-- ── Main content ───────────────────────────────────────────────────────── -->
<main class="max-w-screen-2xl mx-auto px-4 py-5">
    @yield('content')
</main>

<footer class="border-t border-gray-800/50 px-4 py-3 text-center text-xs text-gray-700 mt-8">
    GreenFund Watchdog · Real-time data via Finnhub · Personal decision-making tool · Not financial advice
</footer>

<script>
function watchdog() {
    return {
        marketOpen: false,
        alertCount: 0,
        criticalAlerts: [],
        lastUpdate: '',

        init() {
            this.checkMarket();
            this.loadAlerts();
            setInterval(() => { this.checkMarket(); this.loadAlerts(); }, 60000);
            setInterval(() => {
                const now = new Date();
                this.lastUpdate = now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
            }, 1000);
        },

        checkMarket() {
            const now  = new Date();
            const utc  = now.getUTCHours() * 60 + now.getUTCMinutes();
            const etOffset = now.getMonth() >= 2 && now.getMonth() <= 10 ? 4 : 5; // EDT/EST
            const etMin = utc - etOffset * 60;
            const day = now.getUTCDay();
            this.marketOpen = day >= 1 && day <= 5 && etMin >= 570 && etMin < 960; // 9:30-16:00 ET
        },

        async loadAlerts() {
            try {
                const res  = await fetch('/api/alerts');
                const data = await res.json();
                this.alertCount     = data.length;
                this.criticalAlerts = data.filter(a => a.severity === 'CRITICAL');
            } catch (e) {}
        },

        async acknowledgeAll() {
            await fetch('/api/alerts/acknowledge-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
            });
            this.criticalAlerts = [];
            this.alertCount = 0;
        }
    }
}

// Global chart helpers
const CHART_DEFAULTS = {
    responsive: true, maintainAspectRatio: false,
    animation: { duration: 300 },
    plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false,
        backgroundColor: '#1f2937', titleColor: '#9ca3af', bodyColor: '#e5e7eb',
        borderColor: '#374151', borderWidth: 1 } },
    scales: {
        x: { grid: { color: '#1f2937' }, ticks: { color: '#6b7280', maxTicksLimit: 8, font: { size: 10 } } },
        y: { grid: { color: '#1f2937' }, ticks: { color: '#6b7280', font: { size: 10 } }, position: 'right' }
    }
};

function fmtNum(v, dec=2) {
    if (v === null || v === undefined) return '—';
    return parseFloat(v).toFixed(dec);
}
function fmtPct(v) {
    if (v === null || v === undefined) return '—';
    const n = parseFloat(v);
    return (n >= 0 ? '+' : '') + n.toFixed(2) + '%';
}
function signalClass(s) {
    const m = { BUY:'signal-buy', SELL:'signal-sell', WATCH:'signal-watch', HOLD:'signal-hold' };
    return 'px-2 py-0.5 rounded border text-xs font-bold ' + (m[s] || m.HOLD);
}
function rsiColor(rsi) {
    if (!rsi) return '#6b7280';
    if (rsi < 30) return '#4ade80';
    if (rsi > 70) return '#f87171';
    return '#e5e7eb';
}
</script>

@stack('scripts')
</body>
</html>
