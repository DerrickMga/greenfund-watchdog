@extends('layouts.app')
@section('title', 'Screener - GreenFund Watchdog')

@section('content')
<div x-data="screenerApp(@js($watchlist))" x-init="init()">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-lg font-bold text-white">Stock Screener</h1>
            <p class="text-xs text-gray-600 mt-0.5">Filter by signal, RSI, sector, and market cap</p>
        </div>
        <div class="text-xs text-gray-500">
            Showing <span class="font-mono text-gray-300" x-text="filtered.length"></span>
            of <span class="font-mono" x-text="rows.length"></span>
        </div>
    </div>

    <div class="card p-4 mb-5">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <div>
                <label class="text-[11px] uppercase tracking-wider text-gray-600">Signal</label>
                <select x-model="filters.signal" class="w-full mt-1 bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-xs text-gray-200">
                    <option value="">All</option>
                    <option value="BUY">BUY</option>
                    <option value="SELL">SELL</option>
                    <option value="WATCH">WATCH</option>
                    <option value="HOLD">HOLD</option>
                </select>
            </div>
            <div>
                <label class="text-[11px] uppercase tracking-wider text-gray-600">Sector</label>
                <select x-model="filters.sector" class="w-full mt-1 bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-xs text-gray-200">
                    <option value="">All</option>
                    <template x-for="s in sectors" :key="s">
                        <option :value="s" x-text="s"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="text-[11px] uppercase tracking-wider text-gray-600">RSI Min</label>
                <input x-model.number="filters.rsiMin" type="number" min="0" max="100" class="w-full mt-1 bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-xs text-gray-200" placeholder="0">
            </div>
            <div>
                <label class="text-[11px] uppercase tracking-wider text-gray-600">RSI Max</label>
                <input x-model.number="filters.rsiMax" type="number" min="0" max="100" class="w-full mt-1 bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-xs text-gray-200" placeholder="100">
            </div>
            <div>
                <label class="text-[11px] uppercase tracking-wider text-gray-600">Min MCap ($B)</label>
                <input x-model.number="filters.mcapMin" type="number" min="0" step="0.1" class="w-full mt-1 bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-xs text-gray-200" placeholder="0">
            </div>
            <div>
                <label class="text-[11px] uppercase tracking-wider text-gray-600">Max MCap ($B)</label>
                <input x-model.number="filters.mcapMax" type="number" min="0" step="0.1" class="w-full mt-1 bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-xs text-gray-200" placeholder="Any">
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button @click="resetFilters()" class="text-xs px-2.5 py-1.5 border border-gray-700 rounded text-gray-400 hover:text-gray-200 hover:border-gray-500 transition">Reset Filters</button>
            <button @click="sortBy('confidence')" class="text-xs px-2.5 py-1.5 border border-green-800 rounded text-green-400 hover:bg-green-900/20 transition">Sort by Confidence</button>
            <button @click="sortBy('change')" class="text-xs px-2.5 py-1.5 border border-blue-800 rounded text-blue-400 hover:bg-blue-900/20 transition">Sort by Change</button>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-gray-600 uppercase tracking-widest border-b border-gray-800">
                        <th class="text-left px-4 py-2.5">Ticker</th>
                        <th class="text-left px-3 py-2.5">Company</th>
                        <th class="text-left px-3 py-2.5">Sector</th>
                        <th class="text-right px-3 py-2.5">Price</th>
                        <th class="text-right px-3 py-2.5">Change%</th>
                        <th class="text-right px-3 py-2.5">RSI</th>
                        <th class="text-right px-3 py-2.5">P/E</th>
                        <th class="text-right px-3 py-2.5">MCap</th>
                        <th class="text-center px-3 py-2.5">Signal</th>
                        <th class="text-center px-3 py-2.5">Conf</th>
                        <th class="px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/40">
                    <template x-if="filtered.length === 0">
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-gray-700">No symbols match filters</td>
                        </tr>
                    </template>
                    <template x-for="row in filtered" :key="row.ticker">
                        <tr class="ticker-row" @click="window.location='/ticker/'+row.ticker">
                            <td class="px-4 py-2.5 font-bold font-mono text-white" x-text="row.ticker"></td>
                            <td class="px-3 py-2.5 text-gray-400 truncate max-w-[180px]" x-text="row.company_name || '-' "></td>
                            <td class="px-3 py-2.5 text-gray-600" x-text="row.sector || '-' "></td>
                            <td class="px-3 py-2.5 text-right font-mono text-white" x-text="row.price != null ? '$'+Number(row.price).toFixed(2) : '-' "></td>
                            <td class="px-3 py-2.5 text-right font-mono" :class="(row.change||0) >= 0 ? 'text-green-400' : 'text-red-400'" x-text="row.change != null ? fmtPct(row.change) : '-' "></td>
                            <td class="px-3 py-2.5 text-right font-mono" :style="'color:'+rsiColor(row.rsi)" x-text="row.rsi != null ? Number(row.rsi).toFixed(1) : '-' "></td>
                            <td class="px-3 py-2.5 text-right font-mono text-gray-300" x-text="row.pe != null ? Number(row.pe).toFixed(2) : '-' "></td>
                            <td class="px-3 py-2.5 text-right font-mono text-gray-400" x-text="fmtMarketCap(row.market_cap)"></td>
                            <td class="px-3 py-2.5 text-center">
                                <span x-show="row.signal" :class="signalClass(row.signal)" x-text="row.signal"></span>
                                <span x-show="!row.signal" class="text-gray-700">-</span>
                            </td>
                            <td class="px-3 py-2.5 text-center text-gray-500" x-text="row.confidence != null ? row.confidence + '%' : '-' "></td>
                            <td class="px-3 py-2.5">
                                <a :href="'/ticker/'+row.ticker" @click.stop class="text-green-700 hover:text-green-400 transition">-></a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function screenerApp(rawRows) {
    return {
        rows: [],
        filtered: [],
        sectors: [],
        sortKey: 'ticker',
        sortDir: 'asc',
        filters: {
            signal: '',
            sector: '',
            rsiMin: null,
            rsiMax: null,
            mcapMin: null,
            mcapMax: null,
        },

        init() {
            this.rows = (rawRows || []).map((w) => {
                const snap = w.latest_snapshot || w.latestSnapshot || null;
                const sig = w.active_signal || w.activeSignal || null;
                const profile = w.profile || null;

                return {
                    ticker: w.ticker,
                    company_name: w.company_name,
                    sector: (profile && profile.sector) || w.sector || '',
                    price: snap ? snap.price : null,
                    change: snap ? snap.change_percent : null,
                    rsi: snap ? snap.rsi_14 : null,
                    pe: profile ? profile.pe_ttm : null,
                    market_cap: profile ? profile.market_cap : null,
                    signal: sig ? sig.action : null,
                    confidence: sig ? sig.confidence : null,
                };
            });

            this.sectors = [...new Set(this.rows.map(r => r.sector).filter(Boolean))].sort();
            this.apply();

            this.$watch('filters', () => this.apply(), { deep: true });
        },

        resetFilters() {
            this.filters = { signal: '', sector: '', rsiMin: null, rsiMax: null, mcapMin: null, mcapMax: null };
            this.sortKey = 'ticker';
            this.sortDir = 'asc';
            this.apply();
        },

        sortBy(key) {
            if (this.sortKey === key) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDir = 'desc';
            }
            this.apply();
        },

        apply() {
            let out = [...this.rows];

            if (this.filters.signal) {
                out = out.filter(r => r.signal === this.filters.signal);
            }
            if (this.filters.sector) {
                out = out.filter(r => r.sector === this.filters.sector);
            }
            if (this.filters.rsiMin !== null && this.filters.rsiMin !== '') {
                out = out.filter(r => r.rsi !== null && Number(r.rsi) >= Number(this.filters.rsiMin));
            }
            if (this.filters.rsiMax !== null && this.filters.rsiMax !== '') {
                out = out.filter(r => r.rsi !== null && Number(r.rsi) <= Number(this.filters.rsiMax));
            }
            if (this.filters.mcapMin !== null && this.filters.mcapMin !== '') {
                out = out.filter(r => r.market_cap !== null && Number(r.market_cap) >= Number(this.filters.mcapMin) * 1000);
            }
            if (this.filters.mcapMax !== null && this.filters.mcapMax !== '') {
                out = out.filter(r => r.market_cap !== null && Number(r.market_cap) <= Number(this.filters.mcapMax) * 1000);
            }

            const key = this.sortKey;
            const dir = this.sortDir === 'asc' ? 1 : -1;
            out.sort((a, b) => {
                const av = a[key] ?? -Infinity;
                const bv = b[key] ?? -Infinity;
                if (typeof av === 'string' || typeof bv === 'string') {
                    return String(av).localeCompare(String(bv)) * dir;
                }
                return (Number(av) - Number(bv)) * dir;
            });

            this.filtered = out;
        },

        fmtMarketCap(v) {
            if (v == null || Number.isNaN(Number(v))) return '-';
            const n = Number(v);
            if (n >= 1000) return (n / 1000).toFixed(2) + 'T';
            return n.toFixed(2) + 'B';
        }
    };
}
</script>
@endpush
