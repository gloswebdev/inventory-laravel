@extends('layouts.app')

@section('header', 'Branch-Wise Sales Report')

@section('content')
<div class="space-y-6" id="salesReportApp">

    {{-- TOP BANNER / HEADER --}}
    <div class="rounded-3xl p-6 sm:p-8 text-white relative overflow-hidden shadow-xl"
         style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);">
        {{-- Background glow --}}
        <div class="absolute -right-10 -bottom-10 w-72 h-72 rounded-full bg-indigo-500/10 blur-3xl pointer-events-none"></div>
        <div class="absolute right-1/3 -top-10 w-48 h-48 rounded-full bg-emerald-500/10 blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-emerald-300 text-xl shadow-inner">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-2xl font-black tracking-tight text-white">Branch-Wise Consolidated Sales</h1>
                        @if($totalSyncedRecords > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            {{ number_format($totalSyncedRecords) }} Records Synced
                        </span>
                        @endif
                    </div>
                    <p class="text-xs text-indigo-200/80 font-medium mt-0.5">
                        Consolidated multi-branch performance overview & revenue distribution
                    </p>
                </div>
            </div>

            {{-- ACTION BUTTONS --}}
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('reports.query-executor.index') }}"
                    class="px-4 py-2.5 rounded-2xl bg-amber-500/15 hover:bg-amber-500/25 border border-amber-400/30 text-amber-300 text-xs font-bold transition flex items-center gap-2">
                    <i class="fas fa-bolt text-amber-400"></i>
                    <span>Sync From MSSQL</span>
                </a>

                <button onclick="window.print()"
                    class="px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/10 text-white text-xs font-bold transition flex items-center gap-2">
                    <i class="fas fa-print text-indigo-300"></i>
                    <span>Print Report</span>
                </button>
            </div>
        </div>
    </div>

    {{-- FILTER BAR --}}
    <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm">
        <form method="GET" action="{{ route('reports.sales-report') }}" id="filterForm" class="space-y-4">
            
            {{-- Quick Date Range Pills --}}
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-black text-slate-500 uppercase tracking-wider mr-1">Period:</span>

                @php
                    $presets = [
                        'all_time'   => '⚡ All Time',
                        'this_fy'    => '🟢 FY 26-27 (Current)',
                        'prev_fy'    => '📅 FY 25-26',
                        'fy_24_25'   => '📜 FY 24-25',
                        'this_month' => '📆 This Month',
                        'last_month' => '⏮️ Last Month',
                        'today'      => '⭐ Today',
                        'custom'     => '🛠️ Custom',
                    ];
                @endphp

                @foreach($presets as $pKey => $pLabel)
                <button type="button" onclick="selectDatePreset('{{ $pKey }}')"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all {{ $datePreset === $pKey ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200/70' }}">
                    {{ $pLabel }}
                </button>
                @endforeach
                <input type="hidden" name="date_range" id="dateRangeInput" value="{{ $datePreset }}">
            </div>

            {{-- Dropdown Filters & Search --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 pt-2 border-t border-slate-50">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">From Date:</label>
                    <input type="date" name="from_date" value="{{ $fromDate }}"
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500 font-bold text-slate-700">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">To Date:</label>
                    <input type="date" name="to_date" value="{{ $toDate }}"
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500 font-bold text-slate-700">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Filter Branch:</label>
                    <select name="branch" class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500 font-bold text-slate-700">
                        <option value="">🏢 All Branches</option>
                        @foreach($allBranchNames as $bName)
                        <option value="{{ $bName }}" {{ $selectedBranch === $bName ? 'selected' : '' }}>{{ $bName }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Search Keyword:</label>
                    <div class="flex items-center gap-2">
                        <input type="text" name="search" value="{{ $searchQuery }}" placeholder="Item / Party / Branch..."
                            class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500 text-slate-700">
                        <button type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black rounded-xl transition flex-shrink-0">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>

    {{-- 4 TOP KPI SUMMARY CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- TOTAL CONSOLIDATED SALES --}}
        <div class="p-5 rounded-3xl bg-white border border-slate-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Total Consolidated Sales</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                    <i class="fas fa-indian-rupee-sign"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight text-emerald-600">
                {{ $formattedGrandSales }}
            </div>
            <div class="text-[11px] text-slate-400 mt-1 font-mono font-bold">
                Exact: ₹ {{ number_format($grandTotalSales, 2) }}
            </div>
        </div>

        {{-- TOP PERFORMING BRANCH --}}
        <div class="p-5 rounded-3xl bg-white border border-slate-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Top Branch</span>
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">
                    <i class="fas fa-trophy text-amber-500"></i>
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-black text-indigo-900 truncate" title="{{ $topBranch['branch_name'] ?? 'N/A' }}">
                {{ $topBranch['branch_name'] ?? 'N/A' }}
            </div>
            <div class="text-[11px] text-slate-500 mt-1 font-bold">
                @if($topBranch)
                <span class="text-emerald-600 font-bold">{{ $topBranch['formatted_sales'] }}</span> ({{ $topBranch['share_percent'] }}% share)
                @else
                No sales records
                @endif
            </div>
        </div>

        {{-- TOTAL INVOICES / BILLS --}}
        <div class="p-5 rounded-3xl bg-white border border-slate-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Total Invoices</span>
                <div class="w-8 h-8 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center text-sm">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight">
                {{ number_format($grandTotalInvoices) }}
            </div>
            <div class="text-[11px] text-slate-400 mt-1 font-bold">
                Across {{ count($branchSummary) }} active branches
            </div>
        </div>

        {{-- TOTAL QUANTITY SOLD --}}
        <div class="p-5 rounded-3xl bg-white border border-slate-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Total Quantity Sold</span>
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                    <i class="fas fa-cubes"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight text-amber-600">
                {{ number_format($grandTotalQty, 0) }}
            </div>
            <div class="text-[11px] text-slate-400 mt-1 font-bold">
                Total item lines: {{ number_format(collect($branchSummary)->sum('total_lines')) }}
            </div>
        </div>

    </div>

    {{-- BRANCH-WISE CARDS GRID (Visual Breakdown like Akola 20 Cr, Pune 6.8 Cr) --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-building text-indigo-600"></i>
                <span>Branch-Wise Revenue Breakdown</span>
            </h2>
            <span class="text-xs text-slate-400 font-bold">{{ count($branchSummary) }} Branches Ranked</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse($branchSummary as $b)
            <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm hover:shadow-md transition-all relative overflow-hidden group">
                
                {{-- Top Badge / Rank --}}
                <div class="flex items-center justify-between mb-3">
                    <span class="px-3 py-1 rounded-xl text-xs font-black {{ $b['rank'] == 1 ? 'bg-amber-100 text-amber-800 border border-amber-200' : ($b['rank'] == 2 ? 'bg-slate-100 text-slate-700' : 'bg-slate-50 text-slate-500') }}">
                        #{{ $b['rank'] }} Ranked
                    </span>
                    <span class="text-xs font-black text-indigo-600 bg-indigo-50 px-2.5 py-0.5 rounded-lg">
                        {{ $b['share_percent'] }}% Share
                    </span>
                </div>

                {{-- Branch Title --}}
                <div class="text-lg font-black text-slate-800 truncate mb-1" title="{{ $b['branch_name'] }}">
                    {{ $b['branch_name'] }}
                </div>

                {{-- Big Bold Revenue --}}
                <div class="text-3xl font-black text-emerald-600 tracking-tight my-2">
                    {{ $b['formatted_sales'] }}
                </div>

                {{-- Progress Share Bar --}}
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden my-3">
                    <div class="bg-gradient-to-r from-emerald-500 to-indigo-600 h-full rounded-full transition-all duration-500"
                         style="width: {{ min(100, max(5, $b['share_percent'])) }}%"></div>
                </div>

                {{-- Sub Metrics Grid --}}
                <div class="grid grid-cols-3 gap-2 pt-3 border-t border-slate-50 text-center">
                    <div>
                        <div class="text-[10px] font-black text-slate-400 uppercase">Invoices</div>
                        <div class="text-xs font-black text-slate-700">{{ number_format($b['total_invoices']) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-black text-slate-400 uppercase">Total Qty</div>
                        <div class="text-xs font-black text-slate-700">{{ number_format($b['total_qty'], 0) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-black text-slate-400 uppercase">Avg Order</div>
                        <div class="text-xs font-black text-indigo-600">{{ $b['formatted_aov'] }}</div>
                    </div>
                </div>

                {{-- Exact Value on Hover --}}
                <div class="text-[10px] text-slate-400 text-center mt-3 font-mono">
                    ₹ {{ number_format($b['total_sales'], 2) }}
                </div>

            </div>
            @empty
            <div class="col-span-full bg-white rounded-3xl p-12 text-center border border-slate-100">
                <i class="fas fa-database text-4xl text-slate-300 mb-3 block"></i>
                <h3 class="text-base font-black text-slate-700">Koi Sales Data Available Nahi Hai</h3>
                <p class="text-xs text-slate-400 mt-1 mb-4">Query Executor se MSSQL sales data sync/import karein.</p>
                <a href="{{ route('reports.query-executor.index') }}"
                    class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black rounded-xl transition inline-flex items-center gap-2">
                    <i class="fas fa-bolt text-amber-300"></i> Open Query Executor
                </a>
            </div>
            @endforelse
        </div>
    </div>

    {{-- DETAILED CONSOLIDATED TABLE --}}
    @if(count($branchSummary) > 0)
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2">
                <i class="fas fa-table text-indigo-600 text-sm"></i>
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Branch Performance Data Table</h3>
            </div>
            <span class="text-xs text-slate-400 font-bold">{{ count($branchSummary) }} Rows</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs divide-y divide-slate-100">
                <thead class="bg-slate-50 text-[11px] font-black text-slate-600 uppercase tracking-wider">
                    <tr>
                        <th class="p-3.5 text-center w-12">#</th>
                        <th class="p-3.5">Branch Name</th>
                        <th class="p-3.5 text-right">Invoices (Bills)</th>
                        <th class="p-3.5 text-right">Total Qty Sold</th>
                        <th class="p-3.5 text-right">Total Sales Revenue (₹)</th>
                        <th class="p-3.5 text-right">% Business Share</th>
                        <th class="p-3.5 text-right">Avg Bill Value</th>
                        <th class="p-3.5 text-center">Date Range Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700 bg-white">
                    @foreach($branchSummary as $b)
                    <tr class="hover:bg-indigo-50/40 transition-colors">
                        <td class="p-3.5 text-center font-black text-slate-400">
                            {{ $b['rank'] }}
                        </td>
                        <td class="p-3.5 font-black text-slate-900 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $b['rank'] == 1 ? 'bg-amber-400' : 'bg-indigo-500' }}"></span>
                            {{ $b['branch_name'] }}
                        </td>
                        <td class="p-3.5 text-right font-bold text-slate-700">
                            {{ number_format($b['total_invoices']) }}
                        </td>
                        <td class="p-3.5 text-right font-bold text-slate-700">
                            {{ number_format($b['total_qty'], 0) }}
                        </td>
                        <td class="p-3.5 text-right">
                            <div class="font-black text-emerald-600 text-sm">{{ $b['formatted_sales'] }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">₹ {{ number_format($b['total_sales'], 2) }}</div>
                        </td>
                        <td class="p-3.5 text-right">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-black bg-indigo-50 text-indigo-700">
                                {{ $b['share_percent'] }}%
                            </span>
                        </td>
                        <td class="p-3.5 text-right font-bold text-slate-700">
                            {{ $b['formatted_aov'] }}
                        </td>
                        <td class="p-3.5 text-center text-[10px] text-slate-400 font-mono">
                            {{ $b['min_date'] ?? '-' }} to {{ $b['max_date'] ?? '-' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                {{-- TOTAL FOOTER --}}
                <tfoot class="bg-slate-900 text-white font-black text-xs">
                    <tr>
                        <td class="p-4 text-center">TOTAL</td>
                        <td class="p-4 uppercase font-bold">{{ count($branchSummary) }} BRANCHES</td>
                        <td class="p-4 text-right">{{ number_format($grandTotalInvoices) }}</td>
                        <td class="p-4 text-right">{{ number_format($grandTotalQty, 0) }}</td>
                        <td class="p-4 text-right text-emerald-400 text-sm">
                            {{ $formattedGrandSales }}
                            <div class="text-[10px] font-mono text-slate-400 font-normal">₹ {{ number_format($grandTotalSales, 2) }}</div>
                        </td>
                        <td class="p-4 text-right text-indigo-300">100.0%</td>
                        <td class="p-4 text-right text-slate-300">
                            {{ $grandTotalInvoices > 0 ? \App\Http\Controllers\ReportController::formatIndianCurrency($grandTotalSales / $grandTotalInvoices) : '₹ 0' }}
                        </td>
                        <td class="p-4 text-center text-slate-400 text-[10px]">&bull; CONSOLIDATED &bull;</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- SIDE-BY-SIDE: TOP PRODUCTS & TOP PARTIES (2 COLS) --}}
    @if(count($topProducts) > 0 || count($topParties) > 0)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- TOP SELLING PRODUCTS --}}
        <div class="bg-white rounded-3xl border border-slate-100 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                        <i class="fas fa-box"></i>
                    </div>
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Top Selling Items (Overall)</h3>
                </div>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($topProducts as $idx => $prod)
                <div class="py-3 flex items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-500 font-black text-[10px] flex items-center justify-center flex-shrink-0">
                            {{ $idx + 1 }}
                        </span>
                        <div class="truncate">
                            <div class="font-bold text-slate-800 truncate" title="{{ $prod['item_name'] }}">{{ $prod['item_name'] }}</div>
                            <div class="text-[10px] text-slate-400">Qty: {{ number_format($prod['total_qty'], 0) }} units</div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="font-black text-emerald-600">{{ $prod['formatted_sales'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- TOP CUSTOMERS / PARTIES --}}
        <div class="bg-white rounded-3xl border border-slate-100 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Top Customers / Parties</h3>
                </div>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($topParties as $idx => $party)
                <div class="py-3 flex items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-500 font-black text-[10px] flex items-center justify-center flex-shrink-0">
                            {{ $idx + 1 }}
                        </span>
                        <div class="truncate">
                            <div class="font-bold text-slate-800 truncate" title="{{ $party['party_name'] }}">{{ $party['party_name'] }}</div>
                            <div class="text-[10px] text-slate-400">Branch: <span class="font-bold text-indigo-600">{{ $party['branch_name'] }}</span> &middot; {{ $party['invoice_count'] }} bills</div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="font-black text-emerald-600">{{ $party['formatted_sales'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>
    @endif

</div>

<script>
function selectDatePreset(preset) {
    document.getElementById('dateRangeInput').value = preset;
    if (preset === 'all_time') {
        const fromInput = document.querySelector('input[name="from_date"]');
        const toInput = document.querySelector('input[name="to_date"]');
        if (fromInput) fromInput.value = '';
        if (toInput) toInput.value = '';
    }
    document.getElementById('filterForm').submit();
}

document.querySelectorAll('input[name="from_date"], input[name="to_date"]').forEach(el => {
    el.addEventListener('change', () => {
        const dri = document.getElementById('dateRangeInput');
        if (dri) dri.value = 'custom';
    });
});
</script>
@endsection
