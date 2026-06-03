@extends('layouts.app')
@section('title', 'Decision Console - GreenFund Watchdog')

@section('content')
<div x-data="decisionConsole()" x-init="init()">
    <div class="flex items-start justify-between gap-4 mb-5">
        <div>
            <h1 class="text-lg font-bold text-white">Decision Console</h1>
            <p class="text-xs text-gray-600 mt-0.5">Executive health, anomalies, and model-governed trade posture</p>
        </div>
        <button @click="refreshAll()" class="text-xs px-3 py-1.5 rounded border border-gray-700 text-gray-300 hover:border-gray-500 hover:text-white transition">Refresh</button>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-5">
        <div class="card p-3">
            <div class="text-[11px] text-gray-600 uppercase">Tickers</div>
            <div class="text-xl font-bold text-white" x-text="health.total_tickers ?? 0"></div>
        </div>
        <div class="card p-3">
            <div class="text-[11px] text-gray-600 uppercase">Healthy</div>
            <div class="text-xl font-bold text-green-400" x-text="health.healthy ?? 0"></div>
        </div>
        <div class="card p-3">
            <div class="text-[11px] text-gray-600 uppercase">Warning</div>
            <div class="text-xl font-bold text-yellow-400" x-text="health.warning ?? 0"></div>
        </div>
        <div class="card p-3">
            <div class="text-[11px] text-gray-600 uppercase">Critical</div>
            <div class="text-xl font-bold text-red-400" x-text="health.critical ?? 0"></div>
        </div>
        <div class="card p-3">
            <div class="text-[11px] text-gray-600 uppercase">Avg Conf</div>
            <div class="text-xl font-bold text-blue-400" x-text="(health.avg_forecast_confidence ?? 0).toFixed ? health.avg_forecast_confidence.toFixed(1)+'%' : '0%' "></div>
        </div>
        <div class="card p-3">
            <div class="text-[11px] text-gray-600 uppercase">Avg Quality</div>
            <div class="text-xl font-bold text-purple-400" x-text="(health.avg_data_quality_score ?? 0).toFixed ? health.avg_data_quality_score.toFixed(1) : (health.avg_data_quality_score ?? 0)"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
        <div class="xl:col-span-2 card p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Forecast Board (Champion Model)</h2>
                <span class="text-[11px] text-gray-600">7-day horizon</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-gray-600 uppercase tracking-widest border-b border-gray-800">
                            <th class="text-left px-2 py-2">Ticker</th>
                            <th class="text-center px-2 py-2">Rec</th>
                            <th class="text-right px-2 py-2">Current</th>
                            <th class="text-right px-2 py-2">Pred</th>
                            <th class="text-right px-2 py-2">Exp%</th>
                            <th class="text-right px-2 py-2">Conf</th>
                            <th class="text-right px-2 py-2">Quality</th>
                            <th class="text-right px-2 py-2">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        <template x-for="r in forecasts" :key="r.ticker">
                            <tr class="hover:bg-gray-800/20 transition">
                                <td class="px-2 py-2 font-mono text-white"><a :href="'/ticker/'+r.ticker" class="hover:text-green-400" x-text="r.ticker"></a></td>
                                <td class="px-2 py-2 text-center">
                                    <span :class="signalClass(r.recommendation)" x-text="r.recommendation"></span>
                                </td>
                                <td class="px-2 py-2 text-right font-mono text-gray-300" x-text="r.current_price != null ? '$'+Number(r.current_price).toFixed(2) : '-' "></td>
                                <td class="px-2 py-2 text-right font-mono text-blue-400" x-text="r.predicted_price != null ? '$'+Number(r.predicted_price).toFixed(2) : '-' "></td>
                                <td class="px-2 py-2 text-right font-mono" :class="(r.expected_return_pct||0) >= 0 ? 'text-green-400' : 'text-red-400'" x-text="fmtPct(r.expected_return_pct)"></td>
                                <td class="px-2 py-2 text-right text-gray-400" x-text="(r.confidence ?? 0) + '%' "></td>
                                <td class="px-2 py-2 text-right text-gray-400" x-text="r.quality_score ?? '-' "></td>
                                <td class="px-2 py-2 text-right text-gray-500" x-text="r.source ?? '-' "></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-4">
            <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Anomaly Flags</h2>
            <div class="space-y-2 max-h-[420px] overflow-y-auto">
                <template x-if="anomalies.length === 0">
                    <div class="text-xs text-gray-600">No active anomalies detected.</div>
                </template>
                <template x-for="a in anomalies" :key="a.ticker + '-' + a.type">
                    <a :href="'/ticker/'+a.ticker" class="block border rounded p-2 text-xs transition hover:border-gray-600"
                       :class="a.severity === 'critical' ? 'border-red-900/50 bg-red-950/20' : 'border-yellow-900/50 bg-yellow-950/20'">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-white" x-text="a.ticker"></span>
                            <span class="uppercase text-[10px]" :class="a.severity === 'critical' ? 'text-red-400' : 'text-yellow-400'" x-text="a.severity"></span>
                        </div>
                        <div class="text-gray-300 mt-1" x-text="a.message"></div>
                    </a>
                </template>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="card p-4">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Top Upside</h3>
            <div class="space-y-2">
                @foreach($leaders as $f)
                <a href="{{ route('ticker.show', $f->ticker) }}" class="flex items-center justify-between text-xs border-b border-gray-800/40 pb-2 hover:text-green-400 transition">
                    <span class="font-mono text-white">{{ $f->ticker }}</span>
                    <span class="text-green-400 font-mono">+{{ number_format($f->expected_return_pct, 2) }}%</span>
                </a>
                @endforeach
            </div>
        </div>
        <div class="card p-4">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Top Downside</h3>
            <div class="space-y-2">
                @foreach($risks as $f)
                <a href="{{ route('ticker.show', $f->ticker) }}" class="flex items-center justify-between text-xs border-b border-gray-800/40 pb-2 hover:text-red-400 transition">
                    <span class="font-mono text-white">{{ $f->ticker }}</span>
                    <span class="text-red-400 font-mono">{{ number_format($f->expected_return_pct, 2) }}%</span>
                </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function decisionConsole() {
    return {
        health: {},
        forecasts: [],
        anomalies: [],

        async init() {
            await this.refreshAll();
            setInterval(() => this.refreshAll(), 60000);
        },

        async refreshAll() {
            await Promise.all([this.loadHealth(), this.loadForecasts()]);
            this.computeAnomalies();
        },

        async loadHealth() {
            try {
                const r = await fetch('/api/engine/health');
                this.health = await r.json();
            } catch (e) {}
        },

        async loadForecasts() {
            try {
                const r = await fetch('/api/forecasts?horizon=7');
                this.forecasts = await r.json();
            } catch (e) {}
        },

        computeAnomalies() {
            const out = [];
            for (const r of this.forecasts) {
                const quality = Number(r.quality_score ?? 0);
                const conf = Number(r.confidence ?? 0);
                const exp = Number(r.expected_return_pct ?? 0);

                if (quality > 0 && quality < 40) {
                    out.push({ ticker: r.ticker, severity: 'warning', type: 'quality', message: 'Low data quality score (' + quality + ')' });
                }
                if (conf > 0 && conf < 35) {
                    out.push({ ticker: r.ticker, severity: 'warning', type: 'confidence', message: 'Low forecast confidence (' + conf + '%)' });
                }
                if (r.recommendation === 'BUY' && exp < 3) {
                    out.push({ ticker: r.ticker, severity: 'critical', type: 'buy_mismatch', message: 'BUY recommendation with weak expected return' });
                }
                if (r.recommendation === 'RELEASE' && exp > 0) {
                    out.push({ ticker: r.ticker, severity: 'warning', type: 'release_mismatch', message: 'RELEASE recommendation but expected return is positive' });
                }
            }
            this.anomalies = out;
        }
    }
}
</script>
@endpush
