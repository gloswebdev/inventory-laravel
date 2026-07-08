@extends('layouts.app')

@section('header', 'Dashboard')

@section('content')

@php
    $lowStockPercent = $totalProducts > 0 ? round(($lowStockCount / $totalProducts) * 100, 1) : 0;
    $adequateCount = max(0, $totalProducts - $lowStockCount);
    $adequatePercent = $totalProducts > 0 ? round(($adequateCount / $totalProducts) * 100, 1) : 0;
@endphp

{{-- Ambient background blobs --}}
<div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl"></div>
    <div class="absolute top-1/2 -right-24 w-80 h-80 bg-violet-500/10 rounded-full blur-3xl"></div>
    <div class="absolute bottom-0 left-1/3 w-64 h-64 bg-emerald-500/8 rounded-full blur-3xl"></div>
</div>

<div class="space-y-8 pt-2">

    {{-- ══════════════════════════════════════
         WELCOME GREETING BANNER
    ══════════════════════════════════════ --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 sm:p-8 shadow-lg border border-slate-800">
        {{-- Decorative glowing shapes --}}
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-2xl"></div>
        <div class="absolute left-1/3 -bottom-10 w-32 h-32 bg-purple-500/10 rounded-full blur-2xl"></div>
        
        <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-500/15 border border-indigo-400/20 rounded-full text-[10px] font-black uppercase tracking-wider text-indigo-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse"></span>
                    Live Inventory Status
                </span>
                <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight mt-3">
                    Welcome Back, <span class="bg-gradient-to-r from-blue-400 via-indigo-200 to-purple-400 bg-clip-text text-transparent">{{ Auth::user()->name ?? Auth::user()->username }}</span>!
                </h1>
                <p class="text-xs text-slate-400 font-medium mt-1">
                    Keep track of your formulations, production outputs, and critical stock alerts here.
                </p>
            </div>
            
            <div class="flex items-center gap-4 bg-white/5 border border-white/10 rounded-2xl p-4 backdrop-blur-sm self-start md:self-auto">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-300 flex-shrink-0">
                    <i class="fas fa-calendar-alt text-lg"></i>
                </div>
                <div>
                    <div class="text-[10px] font-black text-indigo-300 uppercase tracking-widest leading-none mb-1">Current Date</div>
                    <div class="text-sm font-bold text-white leading-none">{{ now()->format('l, M d, Y') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════
         ROW 1 — KPI STAT CARDS
    ══════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">

        {{-- Total Products --}}
        <div class="relative group bg-white rounded-3xl p-6 shadow-sm border border-slate-100/80 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-3xl"></div>
            <div class="flex items-start justify-between relative">
                <div class="space-y-4 flex-1">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] mb-1.5">Total Products</p>
                        <p class="text-4xl font-black text-slate-800 leading-none tabular-nums">{{ number_format($totalProducts ?? 0) }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-50 text-[10px] font-bold text-emerald-600 border border-emerald-100">
                            {{ $adequatePercent }}%
                        </span>
                        <span class="text-[11px] font-semibold text-slate-400">Adequately stocked</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-400 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-200/50 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0">
                    <i class="fas fa-box text-white text-xl"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-400 to-indigo-600 scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left rounded-b-3xl"></div>
        </div>

        {{-- Low Stock Alert --}}
        <div class="relative group bg-white rounded-3xl p-6 shadow-sm border {{ ($lowStockCount ?? 0) > 0 ? 'border-red-100' : 'border-slate-100/80' }} hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden">
            @if(($lowStockCount ?? 0) > 0)
            <div class="absolute top-4 right-4 flex items-center gap-1 px-2 py-0.5 bg-red-50 border border-red-100 rounded-lg animate-pulse">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                <span class="text-[8px] font-black text-red-500 uppercase tracking-widest">Alert</span>
            </div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-br from-red-500/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-3xl"></div>
            <div class="flex items-start justify-between relative">
                <div class="space-y-4 flex-1">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] mb-1.5">Low Stock Items</p>
                        <p class="text-4xl font-black {{ ($lowStockCount ?? 0) > 0 ? 'text-red-600' : 'text-slate-800' }} leading-none tabular-nums">{{ number_format($lowStockCount ?? 0) }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg {{ ($lowStockCount ?? 0) > 0 ? 'bg-red-50 text-red-600 border-red-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100' }}">
                            {{ $lowStockPercent }}%
                        </span>
                        <span class="text-[11px] font-semibold text-slate-400">{{ ($lowStockCount ?? 0) > 0 ? 'Requires attention' : 'All stocked fine' }}</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-400 to-rose-600 flex items-center justify-center shadow-lg shadow-red-200/50 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0">
                    <i class="fas fa-triangle-exclamation text-white text-xl"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-red-400 to-rose-600 scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left rounded-b-3xl"></div>
        </div>

        {{-- Produced Today --}}
        <div class="relative group bg-white rounded-3xl p-6 shadow-sm border border-slate-100/80 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-3xl"></div>
            <div class="flex items-start justify-between relative">
                <div class="space-y-4 flex-1">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] mb-1.5">Produced Today</p>
                        <p class="text-4xl font-black text-slate-800 leading-none tabular-nums">{{ number_format($productionCount ?? 0) }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-[11px] font-semibold text-slate-400">Production logs entered</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-200/50 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0">
                    <i class="fas fa-industry text-white text-xl"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-400 to-teal-600 scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left rounded-b-3xl"></div>
        </div>

        {{-- Total Recipes --}}
        <div class="relative group bg-white rounded-3xl p-6 shadow-sm border border-slate-100/80 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-violet-500/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-3xl"></div>
            <div class="flex items-start justify-between relative">
                <div class="space-y-4 flex-1">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] mb-1.5">Recipe Formulations</p>
                        <p class="text-4xl font-black text-slate-800 leading-none tabular-nums">{{ number_format($recipeCount ?? 0) }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-violet-400"></span>
                        <span class="text-[11px] font-semibold text-slate-400">Formulations defined</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-400 to-purple-600 flex items-center justify-center shadow-lg shadow-violet-200/50 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0">
                    <i class="fas fa-flask text-white text-xl"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-violet-400 to-purple-600 scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left rounded-b-3xl"></div>
        </div>

    </div>

    {{-- ══════════════════════════════════════
         ROW 2 — CHART + LOW STOCK SUMMARY
    ══════════════════════════════════════ --}}
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">

        {{-- Stock Chart (3/5 width) --}}
        <div class="xl:col-span-3 bg-white rounded-3xl shadow-sm border border-slate-100/80 overflow-hidden flex flex-col">
            {{-- Card Header --}}
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-50 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-md shadow-blue-200/40">
                        <i class="fas fa-chart-bar text-white text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-800 text-[15px] leading-tight">Stock Overview</h3>
                        <p class="text-[11px] text-slate-400 font-semibold mt-0.5">Top 10 products by current stock</p>
                    </div>
                </div>
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-600 rounded-xl text-[10px] font-black uppercase tracking-wider border border-blue-100">
                        <i class="fas fa-chart-line text-[9px]"></i> Live Status
                    </span>
                </div>
            </div>
            {{-- Chart Body --}}
            <div class="p-6 flex-1 flex items-center justify-center">
                <div class="w-full h-80 relative">
                    <canvas id="stockChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Stock Health & Low Stock Alerts (2/5 width) --}}
        <div class="xl:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100/80 overflow-hidden flex flex-col">
            {{-- Card Header --}}
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-50 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center shadow-md shadow-red-200/40">
                        <i class="fas fa-heart-pulse text-white text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-800 text-[15px] leading-tight">Stock Health & Alerts</h3>
                        <p class="text-[11px] text-slate-400 font-semibold mt-0.5">Real-time status assessment</p>
                    </div>
                </div>
                @if(($lowStockCount ?? 0) > 0)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-50 text-red-600 rounded-lg text-[10px] font-black uppercase tracking-wider border border-red-100">
                    {{ $lowStockCount }} Alerts
                </span>
                @endif
            </div>

            {{-- Doughnut Summary Section --}}
            <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-100 flex items-center gap-6 flex-shrink-0">
                <div class="relative w-24 h-24 flex-shrink-0 flex items-center justify-center">
                    <canvas id="healthDoughnut"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none mt-1">
                        <span class="text-base font-black text-slate-800 leading-none">{{ $adequatePercent }}%</span>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-0.5">Stocked</span>
                    </div>
                </div>
                <div class="flex-1 space-y-1.5 text-[11px]">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 font-semibold text-slate-500">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Adequate Stock
                        </div>
                        <span class="font-bold text-slate-700">{{ number_format($adequateCount) }} ({{ $adequatePercent }}%)</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 font-semibold text-slate-500">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            Low Stock
                        </div>
                        <span class="font-bold text-red-600">{{ number_format($lowStockCount) }} ({{ $lowStockPercent }}%)</span>
                    </div>
                    <div class="w-full h-px bg-slate-200/80 my-1"></div>
                    <div class="flex items-center justify-between font-bold text-slate-600">
                        <span>Total Items</span>
                        <span>{{ number_format($totalProducts) }}</span>
                    </div>
                </div>
            </div>

            {{-- Scrollable List --}}
            <div class="flex-1 overflow-y-auto divide-y divide-slate-100/50 content-scroll" style="max-height: 240px; scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;">
                @forelse($lowStockItems as $item)
                @php
                    $alertQty = floatval($item->low_alert_quantity);
                    $currentStock = floatval($item->current_stock);
                    $percent = 0;
                    if ($alertQty > 0) {
                        $percent = min(100, max(0, ($currentStock / $alertQty) * 100));
                    }
                    
                    // Severity settings
                    if ($currentStock <= 0) {
                        $severityLabel = 'Critical';
                        $severityClass = 'bg-red-50 text-red-700 border-red-100';
                        $barClass = 'bg-rose-500';
                    } elseif ($currentStock <= ($alertQty * 0.4)) {
                        $severityLabel = 'Very Low';
                        $severityClass = 'bg-amber-50 text-amber-700 border-amber-100';
                        $barClass = 'bg-amber-500';
                    } else {
                        $severityLabel = 'Low';
                        $severityClass = 'bg-yellow-50 text-yellow-800 border-yellow-100';
                        $barClass = 'bg-yellow-500';
                    }
                @endphp
                <div class="p-4 hover:bg-slate-50/50 transition-colors group">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="min-w-0">
                            <span class="text-xs font-bold text-slate-800 block truncate group-hover:text-indigo-600 transition-colors">{{ $item->name }}</span>
                            <span class="text-[10px] text-slate-400 font-semibold mt-0.5 block">Threshold: {{ number_format($item->low_alert_quantity, 3) }}</span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-wider border {{ $severityClass }}">
                            {{ $severityLabel }}
                        </span>
                    </div>
                    
                    {{-- Progress Bar and Stock counts --}}
                    <div class="flex items-center gap-3">
                        <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 {{ $barClass }}" style="width: {{ $percent }}%"></div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="text-[11px] font-black text-slate-800">{{ number_format($item->current_stock, 3) }}</span>
                        </div>
                    </div>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center py-12 px-6 gap-3">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-50 flex items-center justify-center border border-emerald-100 shadow-sm">
                        <i class="fas fa-circle-check text-emerald-500 text-xl"></i>
                    </div>
                    <div class="text-center">
                        <p class="font-black text-slate-800 text-xs uppercase tracking-wide">All Stocked Up!</p>
                        <p class="text-[11px] text-slate-400 font-medium mt-0.5">No products are currently running low.</p>
                    </div>
                </div>
                @endforelse
            </div>

            {{-- Footer link --}}
            <div class="px-6 py-4 bg-slate-50/30 border-t border-slate-100 flex-shrink-0 text-center">
                <a href="{{ route('products.index') }}" class="inline-flex items-center justify-center gap-2 text-[10px] font-black text-indigo-600 hover:text-indigo-800 uppercase tracking-widest transition-colors">
                    View Master Inventory <i class="fas fa-arrow-right text-[9px]"></i>
                </a>
            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════
         ROW 3 — QUICK ACTION LINKS
    ══════════════════════════════════════ --}}
    <div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4 flex items-center gap-2 pl-1">
            <i class="fas fa-bolt text-amber-500"></i> Operational Quick Actions
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            @php
            $quickLinks = [
                ['label'=>'Products',    'icon'=>'fa-box',          'href'=>route('products.index'),    'color'=>'from-blue-500 to-indigo-600',   'hover_border'=>'hover:border-blue-300',   'bg_glow'=>'group-hover:bg-blue-500/10', 'text_color'=>'text-blue-600'],
                ['label'=>'Recipes',     'icon'=>'fa-flask',         'href'=>route('recipes.index'),     'color'=>'from-violet-500 to-purple-600', 'hover_border'=>'hover:border-purple-300', 'bg_glow'=>'group-hover:bg-purple-500/10', 'text_color'=>'text-purple-600'],
                ['label'=>'Production',  'icon'=>'fa-industry',      'href'=>route('production.index'),  'color'=>'from-emerald-500 to-teal-600',  'hover_border'=>'hover:border-emerald-300','bg_glow'=>'group-hover:bg-emerald-500/10','text_color'=>'text-emerald-600'],
                ['label'=>'Planning',    'icon'=>'fa-tasks',         'href'=>route('planning.index'),    'color'=>'from-orange-500 to-amber-500',  'hover_border'=>'hover:border-orange-300', 'bg_glow'=>'group-hover:bg-orange-500/10', 'text_color'=>'text-orange-600'],
                ['label'=>'Live Stock',  'icon'=>'fa-chart-line',    'href'=>route('reports.live-stock'),'color'=>'from-cyan-500 to-sky-600',     'hover_border'=>'hover:border-cyan-300',   'bg_glow'=>'group-hover:bg-cyan-500/10',   'text_color'=>'text-cyan-600'],
                ['label'=>'Settings',   'icon'=>'fa-cog',           'href'=>route('settings.branches.index'),'color'=>'from-slate-500 to-slate-700','hover_border'=>'hover:border-slate-300',  'bg_glow'=>'group-hover:bg-slate-500/10',  'text_color'=>'text-slate-600'],
            ];
            @endphp
            @foreach($quickLinks as $link)
            <a href="{{ $link['href'] }}"
               class="group relative bg-white rounded-3xl p-5 border border-slate-100/80 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col items-center gap-4 text-center overflow-hidden {{ $link['hover_border'] }}">
                {{-- Inner soft background hover --}}
                <div class="absolute inset-0 bg-gradient-to-br {{ $link['color'] }} opacity-0 group-hover:opacity-5 transition-opacity duration-300"></div>
                
                {{-- Icon container --}}
                <div class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center border border-slate-100 transition-all duration-300 group-hover:scale-110 group-hover:rotate-3 shadow-sm {{ $link['bg_glow'] }}">
                    <i class="fas {{ $link['icon'] }} {{ $link['text_color'] }} text-base group-hover:scale-110 transition-transform"></i>
                </div>
                
                <span class="text-[11px] font-black text-slate-600 group-hover:text-slate-900 uppercase tracking-widest transition-colors">{{ $link['label'] }}</span>
            </a>
            @endforeach
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════
     CHARTS INJECTED SCRIPT
══════════════════════════════════════ --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Shared Font Family
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#94a3b8';

    // ----------------------------------------------------
    // 1. Stock Overview Bar Chart
    // ----------------------------------------------------
    const ctxBar = document.getElementById('stockChart').getContext('2d');

    const gradientBar = ctxBar.createLinearGradient(0, 0, 0, 320);
    gradientBar.addColorStop(0,   'rgba(59, 130, 246, 0.90)'); // Blue
    gradientBar.addColorStop(0.5, 'rgba(99, 102, 241, 0.70)'); // Indigo
    gradientBar.addColorStop(1,   'rgba(139, 92, 246, 0.35)'); // Purple

    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: {!! json_encode($stockChartLabels ?? []) !!},
            datasets: [{
                label: 'Current Stock',
                data: {!! json_encode($stockChartData ?? []) !!},
                backgroundColor: gradientBar,
                borderRadius: 8,
                borderSkipped: false,
                barThickness: 24,
                hoverBackgroundColor: 'rgba(59, 130, 246, 0.95)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 1000, easing: 'easeOutQuart' },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9', drawTicks: false },
                    border: { display: false, dash: [5, 5] },
                    ticks: { 
                        font: { size: 10, weight: '600' },
                        padding: 10,
                        color: '#64748b'
                    }
                },
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        font: { size: 9, weight: '700' },
                        maxRotation: 25,
                        minRotation: 15,
                        color: '#64748b',
                        padding: 6
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 11, weight: '700' },
                    bodyFont: { size: 12, weight: '600' },
                    padding: 12,
                    cornerRadius: 12,
                    displayColors: false,
                    callbacks: {
                        label: ctx => ` Stock: ${Number(ctx.parsed.y).toLocaleString()}`
                    }
                }
            }
        }
    });

    // ----------------------------------------------------
    // 2. Stock Health Doughnut Chart
    // ----------------------------------------------------
    const ctxDoughnut = document.getElementById('healthDoughnut').getContext('2d');
    
    new Chart(ctxDoughnut, {
        type: 'doughnut',
        data: {
            labels: ['Adequate Stock', 'Low Stock'],
            datasets: [{
                data: [{{ $adequateCount }}, {{ $lowStockCount }}],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.90)', // Green
                    'rgba(239, 68, 68, 0.90)'   // Red
                ],
                hoverBackgroundColor: [
                    'rgba(16, 185, 129, 1)',
                    'rgba(239, 68, 68, 1)'
                ],
                borderWidth: 0,
                cutout: '78%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 1000, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 10, weight: '700' },
                    bodyFont: { size: 11, weight: '600' },
                    padding: 10,
                    cornerRadius: 10,
                    displayColors: true,
                    boxWidth: 8,
                    boxHeight: 8,
                    boxPadding: 4,
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${Number(ctx.parsed).toLocaleString()}`
                    }
                }
            }
        }
    });
});
</script>

@endsection
