@extends('layouts.app')

@section('header', 'Dashboard')

@section('content')
<div class="space-y-10">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <!-- Card: Total Products -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow flex items-center group">
            <div class="bg-blue-500 bg-gradient-to-br from-blue-400 to-blue-600 text-white rounded-2xl h-16 w-16 flex items-center justify-center shadow-lg shadow-blue-200 group-hover:scale-110 transition-transform">
                <i class="fas fa-box fa-2xl"></i>
            </div>
            <div class="ml-5">
                <p class="text-slate-500 font-medium text-sm uppercase tracking-wider">Total Products</p>
                <p class="text-3xl font-extrabold text-slate-800">{{ number_format($totalProducts ?? 0) }}</p>
            </div>
        </div>

        <!-- Card: Low Stock Alerts -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 {{ ($lowStockCount ?? 0) > 0 ? 'border-red-500' : 'border-slate-100' }} hover:shadow-md transition-shadow flex items-center group relative overflow-hidden">
            @if(($lowStockCount ?? 0) > 0)
            <div class="absolute -right-2 -top-2 w-12 h-12 bg-red-50 rounded-full animate-ping opacity-20"></div>
            @endif
            <div class="bg-red-500 bg-gradient-to-br from-red-400 to-red-600 text-white rounded-2xl h-16 w-16 flex items-center justify-center shadow-lg shadow-red-200 group-hover:scale-110 transition-transform">
                <i class="fas fa-exclamation-triangle fa-2xl"></i>
            </div>
            <div class="ml-5">
                <p class="text-slate-500 font-medium text-sm uppercase tracking-wider">Low Stock</p>
                <p class="text-3xl font-extrabold text-slate-800">{{ number_format($lowStockCount ?? 0) }}</p>
            </div>
        </div>

        <!-- Card: Items Produced -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow flex items-center group">
            <div class="bg-emerald-500 bg-gradient-to-br from-emerald-400 to-emerald-600 text-white rounded-2xl h-16 w-16 flex items-center justify-center shadow-lg shadow-emerald-200 group-hover:scale-110 transition-transform">
                <i class="fas fa-industry fa-2xl"></i>
            </div>
            <div class="ml-5">
                <p class="text-slate-500 font-medium text-sm uppercase tracking-wider">Produced Today</p>
                <p class="text-3xl font-extrabold text-slate-800">{{ number_format($productionCount ?? 0) }}</p>
            </div>
        </div>

        <!-- Card: Total Recipes -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow flex items-center group">
            <div class="bg-violet-500 bg-gradient-to-br from-violet-400 to-violet-600 text-white rounded-2xl h-16 w-16 flex items-center justify-center shadow-lg shadow-violet-200 group-hover:scale-110 transition-transform">
                <i class="fas fa-receipt fa-2xl"></i>
            </div>
            <div class="ml-5">
                <p class="text-slate-500 font-medium text-sm uppercase tracking-wider">Total Recipes</p>
                <p class="text-3xl font-extrabold text-slate-800">{{ number_format($recipeCount ?? 0) }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Chart Section -->
        <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100">
            <div class="flex items-center justify-between mb-6">
                <h3 class="font-bold text-xl text-slate-800 flex items-center">
                    <span class="w-2 h-8 bg-blue-500 rounded-full mr-3"></span>
                    Stock Overview
                </h3>
                <span class="text-xs font-semibold px-3 py-1 bg-blue-50 text-blue-600 rounded-full uppercase">Visual Trends</span>
            </div>
            <div class="h-[300px]">
                <canvas id="stockChart"></canvas>
            </div>
        </div>

        <!-- Low Stock List -->
        <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100">
            <div class="flex items-center justify-between mb-6">
                <h3 class="font-bold text-xl text-slate-800 flex items-center">
                    <span class="w-2 h-8 bg-red-500 rounded-full mr-3"></span>
                    Low Stock Details
                </h3>
                <span class="text-xs font-semibold px-3 py-1 bg-red-50 text-red-600 rounded-full uppercase">Critical Items</span>
            </div>
            <div class="overflow-y-auto h-[300px] scrollbar-hide">
                <table class="min-w-full">
                    <thead class="bg-slate-50 sticky top-0 z-10">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider rounded-l-xl">Product</th>
                            <th class="py-3 px-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Current</th>
                            <th class="py-3 px-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider rounded-r-xl">Alert</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($lowStockItems as $item)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-4 px-4 text-sm font-semibold text-slate-900 border-none">{{ $item->name }}</td>
                            <td class="py-4 px-4 text-sm text-right border-none">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 bg-red-50 text-red-700 font-bold rounded-lg">{{ $item->current_stock }}</span>
                            </td>
                            <td class="py-4 px-4 text-sm text-right text-slate-400 border-none">{{ $item->low_alert_quantity }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="py-20 text-center text-slate-400 font-medium">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-check-circle text-4xl text-emerald-100 mb-3"></i>
                                    All items are adequately stocked.
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Basic Chart.js implementation with custom styling
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#64748b';
    Chart.defaults.plugins.tooltip.backgroundColor = '#1e293b';
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;

    const ctx = document.getElementById('stockChart').getContext('2d');
    const chartGradient = ctx.createLinearGradient(0, 0, 0, 300);
    chartGradient.addColorStop(0, 'rgba(59, 130, 246, 0.8)');
    chartGradient.addColorStop(1, 'rgba(59, 130, 246, 0.2)');

    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($stockChartLabels ?? []) !!},
            datasets: [{
                label: 'Current Stock',
                data: {!! json_encode($stockChartData ?? []) !!},
                backgroundColor: chartGradient,
                borderRadius: 12,
                barThickness: 32,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: { display: true, color: '#f1f5f9' },
                    border: { display: false }
                },
                x: {
                    grid: { display: false },
                    border: { display: false }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
</script>
@endsection
