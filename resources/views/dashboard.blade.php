@extends('layouts.app')

@section('header', 'Dashboard')

@section('content')

{{-- Ambient background blobs --}}
<div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl"></div>
    <div class="absolute top-1/2 -right-24 w-80 h-80 bg-violet-500/10 rounded-full blur-3xl"></div>
    <div class="absolute bottom-0 left-1/3 w-64 h-64 bg-emerald-500/8 rounded-full blur-3xl"></div>
</div>

<div class="space-y-8 pt-2">

    {{-- ══════════════════════════════════════
         ROW 1 — KPI STAT CARDS
    ══════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">

        {{-- Total Products --}}
        <div class="relative group bg-white rounded-2xl p-6 shadow-sm border border-slate-100/80 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-2xl"></div>
            <div class="flex items-start justify-between relative">
                <div>
                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-[0.15em] mb-3">Total Products</p>
                    <p class="text-4xl font-black text-slate-800 leading-none tabular-nums">{{ number_format($totalProducts ?? 0) }}</p>
                    <div class="mt-3 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                        <span class="text-[11px] font-semibold text-slate-400">Active in inventory</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center shadow-lg shadow-blue-200/60 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0">
                    <i class="fas fa-box text-white text-xl"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-blue-400 to-blue-600 scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left rounded-b-2xl"></div>
        </div>

        {{-- Low Stock Alert --}}
        <div class="relative group bg-white rounded-2xl p-6 shadow-sm border {{ ($lowStockCount ?? 0) > 0 ? 'border-red-200' : 'border-slate-100/80' }} hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden">
            @if(($lowStockCount ?? 0) > 0)
            <div class="absolute top-3 right-3 flex items-center gap-1.5">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                </span>
                <span class="text-[9px] font-black text-red-400 uppercase tracking-widest">Alert</span>
            </div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-br from-red-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-2xl"></div>
            <div class="flex items-start justify-between relative">
                <div>
                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-[0.15em] mb-3">Low Stock</p>
                    <p class="text-4xl font-black {{ ($lowStockCount ?? 0) > 0 ? 'text-red-600' : 'text-slate-800' }} leading-none tabular-nums">{{ number_format($lowStockCount ?? 0) }}</p>
                    <div class="mt-3 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full {{ ($lowStockCount ?? 0) > 0 ? 'bg-red-400' : 'bg-emerald-400' }}"></span>
                        <span class="text-[11px] font-semibold text-slate-400">{{ ($lowStockCount ?? 0) > 0 ? 'Needs attention' : 'All stocked' }}</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-400 to-rose-600 flex items-center justify-center shadow-lg shadow-red-200/60 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0">
                    <i class="fas fa-triangle-exclamation text-white text-xl"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-red-400 to-rose-600 scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left rounded-b-2xl"></div>
        </div>

        {{-- Produced Today --}}
        <div class="relative group bg-white rounded-2xl p-6 shadow-sm border border-slate-100/80 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-2xl"></div>
            <div class="flex items-start justify-between relative">
                <div>
                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-[0.15em] mb-3">Produced Today</p>
                    <p class="text-4xl font-black text-slate-800 leading-none tabular-nums">{{ number_format($productionCount ?? 0) }}</p>
                    <div class="mt-3 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span class="text-[11px] font-semibold text-slate-400">Production entries</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-200/60 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0">
                    <i class="fas fa-industry text-white text-xl"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-emerald-400 to-teal-600 scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left rounded-b-2xl"></div>
        </div>

        {{-- Total Recipes --}}
        <div class="relative group bg-white rounded-2xl p-6 shadow-sm border border-slate-100/80 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-violet-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-2xl"></div>
            <div class="flex items-start justify-between relative">
                <div>
                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-[0.15em] mb-3">Total Recipes</p>
                    <p class="text-4xl font-black text-slate-800 leading-none tabular-nums">{{ number_format($recipeCount ?? 0) }}</p>
                    <div class="mt-3 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-violet-400"></span>
                        <span class="text-[11px] font-semibold text-slate-400">Formulations defined</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-400 to-purple-600 flex items-center justify-center shadow-lg shadow-violet-200/60 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0">
                    <i class="fas fa-flask text-white text-xl"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-violet-400 to-purple-600 scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left rounded-b-2xl"></div>
        </div>

    </div>

    {{-- ══════════════════════════════════════
         ROW 2 — CHART + LOW STOCK TABLE
    ══════════════════════════════════════ --}}
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">

        {{-- Stock Chart (3/5 width) --}}
        <div class="xl:col-span-3 bg-white rounded-2xl shadow-sm border border-slate-100/80 overflow-hidden">
            {{-- Card Header --}}
            <div class="flex items-center justify-between px-7 py-5 border-b border-slate-50">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-md shadow-blue-200/50">
                        <i class="fas fa-chart-bar text-white text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-800 text-[15px] leading-tight">Stock Overview</h3>
                        <p class="text-[11px] text-slate-400 font-medium">Top 10 products by current stock</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-600 rounded-xl text-[10px] font-black uppercase tracking-wider border border-blue-100/80">
                        <i class="fas fa-signal text-[8px]"></i> Live Data
                    </span>
                </div>
            </div>
            {{-- Chart Body --}}
            <div class="p-6">
                <div class="h-72 relative">
                    <canvas id="stockChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Low Stock Table (2/5 width) --}}
        <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100/80 overflow-hidden flex flex-col">
            {{-- Card Header --}}
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-50 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center shadow-md shadow-red-200/50">
                        <i class="fas fa-triangle-exclamation text-white text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-800 text-[15px] leading-tight">Low Stock Alerts</h3>
                        <p class="text-[11px] text-slate-400 font-medium">Items below threshold</p>
                    </div>
                </div>
                @if(($lowStockCount ?? 0) > 0)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-50 text-red-600 rounded-lg text-[10px] font-black uppercase tracking-wider border border-red-100">
                    {{ $lowStockCount }} Items
                </span>
                @endif
            </div>

            {{-- Scrollable Table --}}
            <div class="flex-1 overflow-y-auto" style="max-height:290px; scrollbar-width:thin; scrollbar-color:#e2e8f0 transparent;">
                @forelse($lowStockItems as $item)
                <div class="flex items-center justify-between px-6 py-3.5 border-b border-slate-50/80 hover:bg-red-50/30 transition-colors group last:border-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-2 h-2 rounded-full bg-red-400 flex-shrink-0 group-hover:scale-125 transition-transform"></div>
                        <span class="text-[13px] font-semibold text-slate-700 truncate">{{ $item->name }}</span>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0 ml-3">
                        <div class="text-right">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Current</div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-black {{ $item->current_stock <= 0 ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700' }}">
                                {{ number_format($item->current_stock, 3) }}
                            </span>
                        </div>
                        <div class="w-px h-8 bg-slate-100"></div>
                        <div class="text-right">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Alert</div>
                            <span class="text-xs font-bold text-slate-400">{{ number_format($item->low_alert_quantity, 3) }}</span>
                        </div>
                    </div>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center h-56 gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center">
                        <i class="fas fa-check-circle text-emerald-400 text-2xl"></i>
                    </div>
                    <div class="text-center">
                        <p class="font-bold text-slate-600 text-sm">All Clear!</p>
                        <p class="text-xs text-slate-400 mt-1">All items are adequately stocked</p>
                    </div>
                </div>
                @endforelse
            </div>

            {{-- Footer link --}}
            <div class="px-6 py-3.5 border-t border-slate-50 flex-shrink-0">
                <a href="{{ route('products.index') }}" class="flex items-center justify-center gap-2 text-[11px] font-black text-blue-500 hover:text-blue-700 uppercase tracking-wider transition-colors">
                    View All Products <i class="fas fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════
         ROW 3 — QUICK ACTION LINKS
    ══════════════════════════════════════ --}}
    <div>
        <p class="text-[11px] font-black text-slate-400 uppercase tracking-[0.15em] mb-4 flex items-center gap-2">
            <i class="fas fa-bolt text-amber-400"></i> Quick Actions
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @php
            $quickLinks = [
                ['label'=>'Products',    'icon'=>'fa-box',          'href'=>route('products.index'),    'color'=>'from-blue-500 to-indigo-600',   'shadow'=>'shadow-blue-200/50'],
                ['label'=>'Recipes',     'icon'=>'fa-flask',         'href'=>route('recipes.index'),     'color'=>'from-violet-500 to-purple-600', 'shadow'=>'shadow-violet-200/50'],
                ['label'=>'Production',  'icon'=>'fa-industry',      'href'=>route('production.index'),  'color'=>'from-emerald-500 to-teal-600',  'shadow'=>'shadow-emerald-200/50'],
                ['label'=>'Planning',    'icon'=>'fa-tasks',         'href'=>route('planning.index'),    'color'=>'from-orange-500 to-amber-500',  'shadow'=>'shadow-orange-200/50'],
                ['label'=>'Live Stock',  'icon'=>'fa-chart-line',    'href'=>route('reports.live-stock'),'color'=>'from-cyan-500 to-sky-600',     'shadow'=>'shadow-cyan-200/50'],
                ['label'=>'Settings',   'icon'=>'fa-cog',           'href'=>route('settings.branches.index'),'color'=>'from-slate-500 to-slate-700','shadow'=>'shadow-slate-200/50'],
            ];
            @endphp
            @foreach($quickLinks as $link)
            <a href="{{ $link['href'] }}"
               class="group relative bg-white rounded-2xl p-4 border border-slate-100/80 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-200 flex flex-col items-center gap-3 text-center overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br {{ $link['color'] }} opacity-0 group-hover:opacity-5 transition-opacity rounded-2xl"></div>
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br {{ $link['color'] }} flex items-center justify-center shadow-md {{ $link['shadow'] }} group-hover:scale-110 group-hover:rotate-3 transition-transform duration-200">
                    <i class="fas {{ $link['icon'] }} text-white text-sm"></i>
                </div>
                <span class="text-[11px] font-black text-slate-600 group-hover:text-slate-900 uppercase tracking-wide transition-colors">{{ $link['label'] }}</span>
            </a>
            @endforeach
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════
     CHART SCRIPT
══════════════════════════════════════ --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#94a3b8';

    const ctx = document.getElementById('stockChart').getContext('2d');

    const gradientBar = ctx.createLinearGradient(0, 0, 0, 280);
    gradientBar.addColorStop(0,   'rgba(59, 130, 246, 0.90)');
    gradientBar.addColorStop(0.5, 'rgba(99, 102, 241, 0.70)');
    gradientBar.addColorStop(1,   'rgba(139, 92, 246, 0.40)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($stockChartLabels ?? []) !!},
            datasets: [{
                label: 'Current Stock',
                data: {!! json_encode($stockChartData ?? []) !!},
                backgroundColor: gradientBar,
                borderRadius: 10,
                borderRadiusTopLeft: 10,
                borderRadiusTopRight: 10,
                barThickness: 28,
                hoverBackgroundColor: 'rgba(59, 130, 246, 0.95)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 800, easing: 'easeOutQuart' },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9', lineWidth: 1 },
                    border: { display: false, dash: [4, 4] },
                    ticks: { font: { size: 11, weight: '600' }, padding: 8 }
                },
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        font: { size: 10, weight: '700' },
                        maxRotation: 35,
                        minRotation: 20,
                        color: '#94a3b8'
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { size: 12, weight: '700' },
                    bodyFont: { size: 13, weight: '600' },
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: false,
                    callbacks: {
                        label: ctx => `  Stock: ${Number(ctx.parsed.y).toLocaleString()}`
                    }
                }
            }
        }
    });
});
</script>

@endsection
