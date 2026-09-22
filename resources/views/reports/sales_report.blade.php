@extends('layouts.app')

@section('header', 'Branch-Wise Sales Report')

@section('content')
<div class="space-y-6" id="salesReportApp" x-data="desktopSalesReport()">

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
                {{-- Targets live on one screen for both sales and collection, rather than a
                     second editor here that could drift out of step with it. --}}
                <a href="{{ route('reports.agent-targets.index') }}"
                    class="px-4 py-2.5 rounded-2xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-400/40 text-emerald-200 hover:text-white text-xs font-bold transition flex items-center gap-2 shadow-sm">
                    <i class="fas fa-bullseye text-emerald-400"></i>
                    <span>Set Agent Targets</span>
                </a>

                <button type="button" @click="openCleanModal()"
                    class="px-4 py-2.5 rounded-2xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-400/40 text-rose-200 hover:text-white text-xs font-bold transition flex items-center gap-2 shadow-sm">
                    <i class="fas fa-trash-can text-rose-400"></i>
                    <span>Clean / Reset Data</span>
                </button>

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

            {{-- Transaction Type toggles --}}
            <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-50">
                <span class="text-xs font-black text-slate-500 uppercase tracking-wider mr-1">Type:</span>

                @foreach($txnTypeOptions as $tKey => $tMeta)
                @php $tOn = in_array($tKey, $selectedTypes, true); @endphp
                <label title="{{ $tMeta['hint'] }}"
                    class="cursor-pointer select-none px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all border {{ $tOn ? 'bg-emerald-600 text-white border-emerald-600 shadow-md shadow-emerald-600/20' : 'bg-slate-50 hover:bg-slate-100 text-slate-500 border-slate-200/70' }}">
                    <input type="checkbox" name="txn_types[]" value="{{ $tKey }}" class="hidden filter-auto-submit"
                        {{ $tOn ? 'checked' : '' }}>
                    {{ $tMeta['icon'] }} {{ $tMeta['label'] }}
                </label>
                @endforeach

                <span class="text-[11px] text-slate-400 ml-1">
                    @if(in_array('stock_transfer', $selectedTypes, true))
                        Stock transfers included — ye internal movement hai, sale nahi.
                    @else
                        Stock transfers excluded.
                    @endif
                </span>
            </div>

            {{-- Product Category Multi-Select Pills --}}
            @if(!empty($allCategories))
            <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-50">
                <span class="text-xs font-black text-slate-500 uppercase tracking-wider mr-1">Category:</span>

                @php $isAllCat = empty($selectedCategories); @endphp
                <button type="button" onclick="clearDesktopCategories()"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 {{ $isAllCat ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200/70' }}">
                    <span>✨</span>
                    <span>All Categories</span>
                </button>

                @foreach($allCategories as $cat)
                @php
                    $isCatActive = in_array($cat, $selectedCategories, true);
                    $meta = \App\Http\Controllers\ReportController::categoryMeta($cat);
                @endphp
                <label class="cursor-pointer select-none px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 border {{ $isCatActive ? ($meta['active_class'] . ' shadow-md') : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200/70' }}">
                    <input type="checkbox" name="categories[]" value="{{ $cat }}" class="hidden filter-auto-submit desktop-cat-toggle"
                        {{ $isCatActive ? 'checked' : '' }}>
                    <span>{{ $meta['icon'] }}</span>
                    <span>{{ $meta['label'] }}</span>
                    @if($isCatActive)
                        <i class="fas fa-check text-[10px] ml-0.5 opacity-90"></i>
                    @endif
                </label>
                @endforeach

                @if(!empty($selectedCategories))
                <button type="button" onclick="clearDesktopCategories()"
                   class="text-xs font-bold text-rose-500 hover:text-rose-600 ml-2 flex items-center gap-1">
                    <i class="fas fa-times-circle"></i> Clear ({{ count($selectedCategories) }})
                </button>
                @endif
            </div>
            @endif

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

                <div class="relative" id="branchFilterWrap">
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Filter Branch:</label>

                    <button type="button" onclick="toggleBranchPanel()"
                        class="w-full text-left text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500 font-bold text-slate-700 flex items-center justify-between gap-2">
                        <span id="branchSummaryLabel" class="truncate">
                            @if(empty($selectedBranches))
                                🏢 All Branches
                            @elseif(count($selectedBranches) === 1)
                                🏢 {{ $selectedBranches[0] }}
                            @else
                                🏢 {{ count($selectedBranches) }} branches
                            @endif
                        </span>
                        <i class="fas fa-chevron-down text-[10px] text-slate-400"></i>
                    </button>

                    <div id="branchPanel"
                        class="hidden absolute z-30 mt-1 w-full max-h-64 overflow-y-auto bg-white border border-slate-200 rounded-xl shadow-lg p-2 space-y-0.5">

                        <div class="flex items-center justify-between px-2 py-1">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Select branches</span>
                            <button type="button" onclick="clearBranches()"
                                class="text-[10px] font-bold text-indigo-600 hover:underline">Clear</button>
                        </div>

                        @foreach($allBranchNames as $bName)
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="branches[]" value="{{ $bName }}"
                                class="branch-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                {{ in_array($bName, $selectedBranches, true) ? 'checked' : '' }}>
                            <span class="text-xs font-bold text-slate-700">{{ $bName }}</span>
                        </label>
                        @endforeach

                        <p class="px-2 pt-1 text-[10px] text-slate-400">
                            Kuch bhi select na karo = saari branches.
                        </p>
                    </div>
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

    {{-- MULTI-YEAR YOY SALES COMPARISON CARD (Same Period / Till Date) --}}
    @if(!empty($yoyComparison))
    <div id="desktopYoyComparisonCard" class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 text-white shadow-xl border border-indigo-500/20 relative overflow-hidden space-y-4">
        {{-- Background ambient glow --}}
        <div class="absolute -right-10 -top-10 w-64 h-64 rounded-full bg-indigo-500/20 blur-3xl pointer-events-none"></div>
        <div class="absolute -left-10 -bottom-10 w-64 h-64 rounded-full bg-emerald-500/15 blur-3xl pointer-events-none"></div>

        {{-- Header --}}
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/10 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-emerald-300 text-lg shadow-inner">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-900 uppercase tracking-wider text-white">YoY Multi-Year Period Comparison</h3>
                        <span class="text-[10px] px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-black">
                            Same Dates / Till Date
                        </span>
                    </div>
                    <p class="text-xs text-indigo-200/70 font-medium mt-0.5">
                        Current Date tak pichle saalo ki bikri aur vikas dar (Growth Rate) ki tulna
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                @if(!empty($selectedCategories))
                <span class="text-xs font-black uppercase px-3 py-1 rounded-xl bg-white/10 text-indigo-200 border border-white/10 flex items-center gap-1.5">
                    <i class="fas fa-tags text-indigo-400"></i> {{ count($selectedCategories) }} Categories Filtered
                </span>
                @endif

                {{-- WhatsApp Share Button --}}
                <button type="button" onclick="shareDesktopYoYCardToWhatsApp()" id="desktopYoyShareBtn"
                    class="px-3.5 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 active:scale-95 text-white text-xs font-900 transition-all flex items-center gap-2 shadow-lg shadow-emerald-500/25 cursor-pointer no-export">
                    <i class="fab fa-whatsapp text-sm text-white"></i>
                    <span id="desktopYoyShareBtnText">Share on WhatsApp</span>
                </button>
            </div>
        </div>

        {{-- 3-Period Cards Grid with Right-Side Branch Breakdown --}}
        <div class="relative z-10 space-y-3">

            @php
                $pConfigs = [
                    'current' => [
                        'border' => 'border-emerald-400/40 bg-emerald-500/10',
                        'badge'  => 'bg-emerald-500/25 text-emerald-300 border border-emerald-400/30',
                        'sales'  => 'text-emerald-400',
                    ],
                    'last_year' => [
                        'border' => 'border-indigo-400/25 bg-white/5',
                        'badge'  => 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30',
                        'sales'  => 'text-white',
                    ],
                    'two_years_ago' => [
                        'border' => 'border-amber-400/25 bg-white/5',
                        'badge'  => 'bg-amber-500/20 text-amber-300 border border-amber-500/30',
                        'sales'  => 'text-white',
                    ],
                ];
            @endphp

            @foreach(['current', 'last_year', 'two_years_ago'] as $pKey)
            @php
                $p = $yoyComparison[$pKey];
                $style = $pConfigs[$pKey];
            @endphp
            <div class="backdrop-blur-md rounded-2xl p-4 border {{ $style['border'] }} relative overflow-hidden space-y-3">
                
                {{-- Top: Badge & Invoices --}}
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-lg {{ $style['badge'] }}">
                        {{ $p['badge'] }}
                    </span>
                    <span class="text-xs text-slate-300 font-mono font-bold">
                        {{ number_format($p['total_invoices']) }} bills
                    </span>
                </div>

                {{-- Grid: Left = Main Total, Right = Branch Breakdown --}}
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                    
                    {{-- Left Column: Total Amount & Growth --}}
                    <div class="md:col-span-4 space-y-1">
                        <div class="text-xs text-slate-300 font-bold truncate">
                            {{ $p['display_period'] }}
                        </div>
                        <div class="text-2xl sm:text-3xl font-black {{ $style['sales'] }} tracking-tight">
                            {{ $p['formatted_sales'] }}
                        </div>
                        <div class="text-[11px] text-slate-400 font-mono">
                            {{ $p['exact_sales'] }}
                        </div>
                        @if(isset($p['formatted_diff']))
                        <div class="pt-1">
                            <span class="text-xs font-black px-2 py-0.5 rounded-lg inline-flex items-center gap-1.5 {{ $p['is_growth'] ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' }}">
                                <i class="fas {{ $p['is_growth'] ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[9px]"></i>
                                {{ $p['formatted_diff'] }}
                            </span>
                        </div>
                        @endif
                    </div>

                    {{-- Right Column: Branch-Wise Breakdown --}}
                    <div class="md:col-span-8 bg-black/30 rounded-2xl p-3 border border-white/5 space-y-2">
                        <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-indigo-200/90 px-1">
                            <span class="flex items-center gap-1.5">
                                <i class="fas fa-building text-indigo-400"></i>
                                <span>Branch-Wise Sales Breakdown</span>
                            </span>
                            <span class="text-xs text-slate-400 font-mono">{{ count($p['branches']) }} Branches</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            @forelse($p['branches'] as $br)
                            <div class="bg-white/5 hover:bg-white/10 rounded-xl p-2 flex flex-col justify-between gap-0.5 border border-white/5 transition-colors">
                                <span class="text-xs font-bold text-slate-200 truncate" title="{{ $br['name'] }}">
                                    {{ $br['name'] }}
                                </span>
                                <span class="text-xs font-mono font-black text-emerald-300">
                                    {{ $br['formatted_sales'] }}
                                </span>
                            </div>
                            @empty
                            <div class="col-span-3 text-center text-xs text-slate-400 py-2">No branch records found</div>
                            @endforelse
                        </div>
                    </div>

                </div>

            </div>
            @endforeach

        </div>

        {{-- Comparative Proportion Visual Bar --}}
        <div class="relative z-10 bg-black/20 rounded-2xl p-3 border border-white/5 space-y-2">
            <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-indigo-200/80">
                <span>Revenue Scale Comparison (Same Date Span)</span>
                <span class="text-emerald-400">Current FY vs Last FY vs 2 Years Ago</span>
            </div>
            <div class="space-y-1.5">
                <div class="flex items-center gap-3">
                    <span class="w-24 text-xs font-bold text-slate-300">FY 26-27</span>
                    <div class="flex-1 bg-white/10 rounded-full h-2 overflow-hidden">
                        <div class="bg-emerald-400 h-2 rounded-full transition-all duration-500" style="width: {{ $yoyComparison['current']['bar_percent'] }}%"></div>
                    </div>
                    <span class="w-24 text-right text-xs font-mono font-bold text-emerald-300">{{ $yoyComparison['current']['formatted_sales'] }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="w-24 text-xs font-bold text-slate-300">FY 25-26</span>
                    <div class="flex-1 bg-white/10 rounded-full h-2 overflow-hidden">
                        <div class="bg-indigo-400 h-2 rounded-full transition-all duration-500" style="width: {{ $yoyComparison['last_year']['bar_percent'] }}%"></div>
                    </div>
                    <span class="w-24 text-right text-xs font-mono font-bold text-slate-300">{{ $yoyComparison['last_year']['formatted_sales'] }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="w-24 text-xs font-bold text-slate-300">FY 24-25</span>
                    <div class="flex-1 bg-white/10 rounded-full h-2 overflow-hidden">
                        <div class="bg-amber-400 h-2 rounded-full transition-all duration-500" style="width: {{ $yoyComparison['two_years_ago']['bar_percent'] }}%"></div>
                    </div>
                    <span class="w-24 text-right text-xs font-mono font-bold text-slate-300">{{ $yoyComparison['two_years_ago']['formatted_sales'] }}</span>
                </div>
            </div>
        </div>

        {{-- Branch-Wise Multi-Year Direct Comparison Table --}}
        @if(!empty($yoyComparison['branch_comparison']))
        <div class="relative z-10 bg-black/25 rounded-2xl p-4 border border-white/5 space-y-3">
            <div class="flex items-center justify-between text-xs font-black uppercase tracking-wider text-indigo-200/90">
                <span class="flex items-center gap-2">
                    <i class="fas fa-table-columns text-emerald-400"></i>
                    <span>Branch Multi-Year Summary (Till Date)</span>
                </span>
                <span class="text-slate-400 font-mono">{{ count($yoyComparison['branch_comparison']) }} Branches Total</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-white/10 text-[11px] font-black uppercase tracking-wider text-slate-400">
                            <th class="py-2 pr-4">Branch Name</th>
                            <th class="py-2 px-3 text-right text-emerald-300">FY 26-27 (Current)</th>
                            <th class="py-2 px-3 text-right text-indigo-300">FY 25-26 (Last Year)</th>
                            <th class="py-2 px-3 text-right text-amber-300">FY 24-25 (2 Years Ago)</th>
                            <th class="py-2 pl-3 text-right">YoY Growth</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 font-mono">
                        @foreach($yoyComparison['branch_comparison'] as $bc)
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="py-2.5 pr-4 font-sans font-bold text-white">
                                {{ $bc['name'] }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-black text-emerald-300">
                                {{ $bc['formatted_cur'] }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-slate-300">
                                {{ $bc['formatted_ly'] }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-slate-400">
                                {{ $bc['formatted_lly'] }}
                            </td>
                            <td class="py-2.5 pl-3 text-right font-bold {{ $bc['is_growth'] ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $bc['is_growth'] ? '+' : '' }}{{ $bc['growth_percent'] }}%
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    @endif

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
            <div @click="openDrilldown('{{ $b['branch_name'] }}')"
                 class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm hover:shadow-xl hover:border-indigo-300 transition-all relative overflow-hidden group cursor-pointer active:scale-98">
                
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
                <div class="text-lg font-black text-slate-800 truncate mb-1 flex items-center justify-between" title="{{ $b['branch_name'] }}">
                    <span>{{ $b['branch_name'] }}</span>
                    <span class="text-xs font-bold text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1">
                        <span>Series Breakdown</span>
                        <i class="fas fa-arrow-right text-[10px]"></i>
                    </span>
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

                {{-- Reporting Period Span --}}
                @php
                    $displayStart = $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d M Y') : ($b['min_date'] ? \Carbon\Carbon::parse($b['min_date'])->format('d M Y') : '01 Apr 2026');
                    $displayEnd = $toDate ? \Carbon\Carbon::parse($toDate)->format('d M Y') : ($b['max_date'] ? \Carbon\Carbon::parse($b['max_date'])->format('d M Y') : now()->format('d M Y'));
                    if ($b['max_date'] && \Carbon\Carbon::parse($displayEnd)->isFuture()) {
                        $displayEnd = \Carbon\Carbon::parse($b['max_date'])->format('d M Y');
                    }
                @endphp
                <div class="text-[9px] text-slate-400 font-bold flex items-center justify-between pt-2 mt-2 border-t border-slate-100">
                    <span>Period: {{ $displayStart }} &rarr; {{ $displayEnd }}</span>
                    <span class="text-indigo-600 font-black flex items-center gap-1">
                        <span>Click for Series</span>
                        <i class="fas fa-chevron-right text-[7px]"></i>
                    </span>
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

    {{-- DRILLDOWN MODAL (SERIES & PRODUCT CATEGORIES) --}}
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="closeDrilldown()"></div>

        {{-- Modal Dialog --}}
        <div class="relative w-full max-w-2xl bg-white rounded-3xl p-6 sm:p-8 shadow-2xl flex flex-col max-h-[85vh] z-10 border border-slate-100 overflow-hidden"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">

            {{-- Header --}}
            <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-100 flex-shrink-0">
                <div class="min-w-0 flex-1">
                    {{-- Breadcrumb: branch first, then one crumb per level drilled into --}}
                    <div class="flex items-center gap-1 flex-wrap text-xs font-bold mb-2">
                        <button @click="goToDepth(0)"
                                class="px-2 py-0.5 rounded-lg transition"
                                :class="path.length ? 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100' : 'text-slate-800'">
                            🏢 <span x-text="activeBranch"></span>
                        </button>

                        <template x-for="(crumb, cIdx) in path" :key="crumb.key">
                            <span class="flex items-center gap-1">
                                <i class="fas fa-chevron-right text-[8px] text-slate-300"></i>
                                <button @click="goToDepth(cIdx + 1)"
                                        class="px-2 py-0.5 rounded-lg transition max-w-[16rem] truncate"
                                        :class="cIdx + 1 < path.length ? 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100' : 'text-slate-800'"
                                        :title="crumb.display">
                                    <span x-text="crumb.icon"></span> <span x-text="crumb.display"></span>
                                </button>
                            </span>
                        </template>
                    </div>

                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3 class="text-xl font-black text-slate-800 tracking-tight truncate"
                            x-text="path.length ? path[path.length - 1].display : activeBranch"></h3>
                        <template x-if="levelMeta">
                            <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100"
                                  x-text="levelMeta.label + ' breakdown'"></span>
                        </template>
                    </div>

                    <p class="text-xs text-slate-400 font-medium mt-0.5"
                       x-text="isLeaf
                            ? 'Yahi last level hai — iske andar aur kuch nahi.'
                            : ('Click on any ' + (levelMeta ? levelMeta.label.toLowerCase() : 'row') + ' to drill into ' + (nextLevel || 'the next level').toLowerCase())"></p>
                </div>

                <button @click="closeDrilldown()" class="w-9 h-9 rounded-2xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition active:scale-95 flex-shrink-0">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            {{-- Summary KPI Ribbon --}}
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-2xl p-4 text-white my-4 flex items-center justify-between flex-shrink-0 shadow-lg gap-4">
                <div class="min-w-0">
                    <div class="text-[9px] font-black text-indigo-300 uppercase tracking-widest truncate"
                         x-text="(path.length ? path[path.length - 1].display : activeBranch) + ' — Total Revenue'"></div>
                    <div class="text-2xl font-black text-emerald-400 tracking-tight" x-text="drillTotalSales"></div>
                </div>
                <div class="text-right text-xs font-bold text-indigo-200/80 space-y-1 flex-shrink-0">
                    <div>Total Bills: <span class="text-white font-black" x-text="drillInvoices"></span></div>
                    <div>Total Qty: <span class="text-white font-black" x-text="drillQty"></span></div>
                </div>
            </div>

            {{-- Back button when we are below the top level --}}
            <template x-if="path.length > 0">
                <button @click="goToDepth(path.length - 1)"
                        class="self-start mb-3 flex items-center gap-1.5 text-xs font-black text-indigo-600 uppercase tracking-wider hover:underline flex-shrink-0">
                    <i class="fas fa-arrow-left text-[10px]"></i>
                    <span x-text="'Back to ' + (path.length > 1 ? path[path.length - 2].display : activeBranch)"></span>
                </button>
            </template>

            {{-- Scrollable List Area --}}
            <div class="flex-1 overflow-y-auto space-y-3 pr-2 -mr-2">

                {{-- Loading Spinner --}}
                <template x-if="loading">
                    <div class="py-12 text-center space-y-2">
                        <div class="inline-flex items-center gap-2.5 px-5 py-2.5 bg-indigo-50 text-indigo-600 rounded-2xl text-xs font-bold animate-pulse">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <span>Fetching live breakdown from database...</span>
                        </div>
                    </div>
                </template>

                {{-- One generic list, whatever level we are on --}}
                <template x-if="!loading">
                    <div class="space-y-3">
                        <template x-if="items.length === 0">
                            <div class="text-center py-8 text-slate-400 text-sm font-bold"
                                 x-text="isLeaf ? 'Ye sabse andar ka level hai.' : 'Is filter pe koi record nahi mila.'"></div>
                        </template>

                        {{-- Says out loud when a level has more rows than were sent --}}
                        <template x-if="truncated">
                            <div class="text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                                Showing top <span x-text="shown"></span> of <span x-text="available"></span>
                                by value — baaki chhote entries list me nahi hain.
                            </div>
                        </template>

                        <template x-for="item in items" :key="item.key">
                            <div @click="drillInto(item)"
                                 class="bg-slate-50 p-4 rounded-2xl border border-slate-100 transition-all space-y-2.5"
                                 :class="isLeaf ? '' : 'hover:bg-white hover:border-indigo-300 hover:shadow-md cursor-pointer group'">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="space-y-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <template x-if="item.code">
                                                <span class="px-2.5 py-0.5 rounded-lg text-xs font-black uppercase font-mono"
                                                      :class="item.is_return ? 'bg-rose-100 text-rose-700 border border-rose-200' : 'bg-indigo-100 text-indigo-700 border border-indigo-200'"
                                                      x-text="item.code"></span>
                                            </template>
                                            <span class="text-sm font-black text-slate-800" x-text="item.label"></span>
                                            <template x-if="item.bill_date">
                                                <span class="text-[10px] font-bold text-slate-400 font-mono" x-text="item.bill_date"></span>
                                            </template>
                                        </div>

                                        <div class="text-xs text-slate-400">
                                            <span class="font-bold text-slate-600" x-text="Number(item.total_invoices).toLocaleString()"></span> bills &middot;
                                            <span class="font-bold text-slate-600" x-text="Number(item.total_qty).toLocaleString()"></span> units
                                            <template x-if="item.bill_party">
                                                <span> &middot; <span class="font-bold text-slate-600" x-text="item.bill_party"></span></span>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="text-right flex items-center gap-3 flex-shrink-0">
                                        <div>
                                            <div class="text-base font-black"
                                                 :class="item.is_return ? 'text-rose-600' : 'text-emerald-600'"
                                                 x-text="item.formatted_sales"></div>
                                            <div class="text-[10px] font-bold text-slate-400" x-text="item.share_percent + '%'"></div>
                                        </div>
                                        <template x-if="!isLeaf">
                                            <div class="w-8 h-8 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 group-hover:text-indigo-600 group-hover:border-indigo-300 transition-all group-hover:translate-x-1">
                                                <i class="fas fa-chevron-right text-xs"></i>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                {{-- Progress bar --}}
                                <div class="w-full bg-slate-200/70 rounded-full h-2 overflow-hidden">
                                    <div class="h-2 rounded-full transition-all duration-300"
                                         :class="item.is_return ? 'bg-rose-500' : 'bg-indigo-600'"
                                         :style="'width: ' + Math.min(100, Math.max(3, Math.abs(item.share_percent))) + '%'"></div>
                                </div>

                                {{-- Target vs achievement, agent level only --}}
                                <template x-if="levelMeta && levelMeta.key === 'agent'">
                                    <div class="pt-2 border-t border-slate-200/70">
                                        <template x-if="item.target">
                                            <div class="space-y-1.5">
                                                <div class="flex items-center justify-between text-[11px] font-bold">
                                                    <span class="text-slate-500">
                                                        Target <span class="text-slate-800 font-black" x-text="item.formatted_target"></span>
                                                        <span class="text-slate-400" x-text="'· ' + item.target_months + ' of ' + periodMonths + ' months set'"></span>
                                                    </span>
                                                    <span class="font-black"
                                                          :class="item.achievement_percent >= 100 ? 'text-emerald-600' : (item.achievement_percent >= 75 ? 'text-amber-600' : 'text-rose-600')"
                                                          x-text="item.achievement_percent + '%'"></span>
                                                </div>

                                                <div class="w-full bg-slate-200/70 rounded-full h-1.5 overflow-hidden">
                                                    <div class="h-1.5 rounded-full transition-all duration-300"
                                                         :class="item.achievement_percent >= 100 ? 'bg-emerald-500' : (item.achievement_percent >= 75 ? 'bg-amber-500' : 'bg-rose-500')"
                                                         :style="'width: ' + Math.min(100, Math.max(2, item.achievement_percent)) + '%'"></div>
                                                </div>

                                                <div class="flex items-center justify-between text-[10px] font-bold">
                                                    <span :class="item.shortfall > 0 ? 'text-rose-500' : 'text-emerald-600'"
                                                          x-text="item.shortfall > 0
                                                                ? ('Shortfall ₹ ' + Number(item.shortfall).toLocaleString('en-IN'))
                                                                : ('Surplus ₹ ' + Number(Math.abs(item.shortfall)).toLocaleString('en-IN'))"></span>

                                                    {{-- A year of sales against one month of target is not 1400% achieved. Say so. --}}
                                                    <template x-if="item.target_months < periodMonths">
                                                        <span class="text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-1.5 py-0.5">
                                                            Target sirf <span x-text="item.target_months"></span> mahine ka set hai
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="!item.target">
                                            <div class="text-[11px] font-bold text-slate-400">
                                                Koi target set nahi
                                                <span x-show="periodMonths === 0">(All Time pe target lagu nahi hota)</span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

            </div>
        </div>
    </div>

    {{-- CLEAN / RESET DATA MODAL --}}
    <div x-show="cleanModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="cleanModalOpen = false"
             class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-lg w-full p-6 space-y-5">
            
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg shadow-sm">
                        <i class="fas fa-trash-can"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-800">Clean Imported Sales Data</h3>
                        <p class="text-xs text-slate-400 font-medium">Select which Financial Year data you want to delete</p>
                    </div>
                </div>
                <button @click="cleanModalOpen = false" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center text-xs transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- Options List --}}
            <div class="space-y-3">
                <label class="flex items-center gap-3.5 p-3.5 rounded-2xl border cursor-pointer transition"
                       :class="selectedCleanYear === '20262027' ? 'border-indigo-600 bg-indigo-50/40 ring-2 ring-indigo-600/20' : 'border-slate-200 hover:border-indigo-300 bg-slate-50/50'">
                    <input type="radio" name="clean_year" value="20262027" x-model="selectedCleanYear" class="text-indigo-600 focus:ring-indigo-500">
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-black text-slate-800 flex items-center gap-2">
                            <span>🟢 FY 2026-2027 (Current Year)</span>
                            <span class="text-[10px] font-bold bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">Active FY</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-0.5">Cleans only records from 01/04/2026 to 31/03/2027</p>
                    </div>
                </label>

                <label class="flex items-center gap-3.5 p-3.5 rounded-2xl border cursor-pointer transition"
                       :class="selectedCleanYear === '20252026' ? 'border-indigo-600 bg-indigo-50/40 ring-2 ring-indigo-600/20' : 'border-slate-200 hover:border-indigo-300 bg-slate-50/50'">
                    <input type="radio" name="clean_year" value="20252026" x-model="selectedCleanYear" class="text-indigo-600 focus:ring-indigo-500">
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-black text-slate-800">📅 FY 2025-2026 (Previous Year)</div>
                        <p class="text-[11px] text-slate-400 mt-0.5">Cleans only records from 01/04/2025 to 31/03/2026</p>
                    </div>
                </label>

                <label class="flex items-center gap-3.5 p-3.5 rounded-2xl border cursor-pointer transition"
                       :class="selectedCleanYear === '20242025' ? 'border-indigo-600 bg-indigo-50/40 ring-2 ring-indigo-600/20' : 'border-slate-200 hover:border-indigo-300 bg-slate-50/50'">
                    <input type="radio" name="clean_year" value="20242025" x-model="selectedCleanYear" class="text-indigo-600 focus:ring-indigo-500">
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-black text-slate-800">📜 FY 2024-2025 (Historical)</div>
                        <p class="text-[11px] text-slate-400 mt-0.5">Cleans only records from 01/04/2024 to 31/03/2025</p>
                    </div>
                </label>

                <label class="flex items-center gap-3.5 p-3.5 rounded-2xl border cursor-pointer transition"
                       :class="selectedCleanYear === 'all' ? 'border-rose-600 bg-rose-50/60 ring-2 ring-rose-600/20' : 'border-rose-200 hover:border-rose-300 bg-rose-50/30'">
                    <input type="radio" name="clean_year" value="all" x-model="selectedCleanYear" class="text-rose-600 focus:ring-rose-500">
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-black text-rose-700 flex items-center gap-2">
                            <span>⚠️ All Financial Years (Complete Reset)</span>
                        </div>
                        <p class="text-[11px] text-rose-500/80 mt-0.5">Wipes entire table completely so you can fresh sync from scratch</p>
                    </div>
                </label>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" @click="cleanModalOpen = false"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                    Cancel
                </button>
                <button type="button" @click="executeCleanData()" :disabled="cleaning"
                    class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-50 transition flex items-center gap-2 shadow-lg shadow-rose-600/20">
                    <i class="fas fa-trash-can" :class="cleaning ? 'fa-spin' : ''"></i>
                    <span x-text="cleaning ? 'Cleaning Data...' : 'Confirm & Delete'"></span>
                </button>
            </div>
        </div>
    </div>

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

/* ---- Transaction type chips: tick = reload straight away ---- */
document.querySelectorAll('.filter-auto-submit').forEach(el => {
    el.addEventListener('change', () => document.getElementById('filterForm').submit());
});

/* Clear all selected categories */
function clearDesktopCategories() {
    document.querySelectorAll('.desktop-cat-toggle').forEach(cb => { cb.checked = false; });
    document.getElementById('filterForm').submit();
}

/* ---- Branch multi-select panel ---- */
function toggleBranchPanel() {
    document.getElementById('branchPanel').classList.toggle('hidden');
}

function clearBranches() {
    document.querySelectorAll('.branch-checkbox').forEach(cb => { cb.checked = false; });
    document.getElementById('filterForm').submit();
}

// Ticking branches one by one should not reload on every click, so the form is only
// submitted once the panel is closed and something actually changed.
(function () {
    const wrap = document.getElementById('branchFilterWrap');
    if (!wrap) return;

    const snapshot = () => Array.from(document.querySelectorAll('.branch-checkbox'))
        .filter(cb => cb.checked).map(cb => cb.value).join('|');

    let openedWith = snapshot();

    document.addEventListener('click', (e) => {
        const panel = document.getElementById('branchPanel');
        if (!panel) return;

        if (wrap.contains(e.target)) {
            if (panel.classList.contains('hidden')) openedWith = snapshot();
            return;
        }

        if (!panel.classList.contains('hidden')) {
            panel.classList.add('hidden');
            if (snapshot() !== openedWith) document.getElementById('filterForm').submit();
        }
    });
})();

function desktopSalesReport() {
    return {
        modalOpen: false,
        cleanModalOpen: false,
        cleaning: false,
        selectedCleanYear: '20262027',
        loading: false,
        activeBranch: '',
        drillTotalSales: '₹ 0.00',
        drillInvoices: '0',
        drillQty: '0',

        /* Drill-down state. `levels` is whatever chain the server reports, so the view
           never hardcodes branch > category > series > agent > party > bill > product. */
        levels: [],
        knownLevelKeys: ['category', 'series', 'agent', 'party', 'bill', 'item'],
        path: [],
        items: [],
        levelMeta: null,
        nextLevel: null,
        isLeaf: false,
        shown: 0,
        available: 0,
        truncated: false,
        periodMonths: 0,
        requestToken: 0,

        openCleanModal() {
            this.cleanModalOpen = true;
        },

        async executeCleanData() {
            const labels = {
                '20262027': 'FY 2026-2027 (Current Year)',
                '20252026': 'FY 2025-2026 (Previous Year)',
                '20242025': 'FY 2024-2025 (Historical)',
                'all': 'ALL Financial Years (Complete Reset)'
            };

            const selectedLabel = labels[this.selectedCleanYear] || this.selectedCleanYear;
            if (!confirm(`Are you sure you want to clean/delete ${selectedLabel}?`)) {
                return;
            }

            this.cleaning = true;
            try {
                const res = await fetch("{{ route('reports.sales-report.clean-data') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        year_choice: this.selectedCleanYear
                    })
                });

                const data = await res.json();
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Error cleaning data: ' + (data.message || 'Unknown error'));
                }
            } catch (err) {
                console.error('Clean error:', err);
                alert('Network error while cleaning data: ' + err.message);
            } finally {
                this.cleaning = false;
                this.cleanModalOpen = false;
            }
        },

        openDrilldown(branchName) {
            this.activeBranch = branchName;
            this.path = [];
            this.modalOpen = true;
            this.fetchLevel();
        },

        closeDrilldown() {
            this.modalOpen = false;
        },

        /* Drop back to a given depth: 0 = the branch itself, n = after the nth crumb. */
        goToDepth(depth) {
            if (depth >= this.path.length) return;
            this.path = this.path.slice(0, depth);
            this.fetchLevel();
        },

        /* Step one level deeper using whatever level the server just told us we were on. */
        drillInto(item) {
            if (this.isLeaf || !this.levelMeta) return;
            this.path = this.path.concat([{
                key: this.levelMeta.key,
                icon: this.levelMeta.icon,
                label: this.levelMeta.label,
                value: item.key,
                display: item.label,
            }]);
            this.fetchLevel();
        },

        /*
         * One request serves every level. The page's own query string rides along so the
         * date range and transaction-type filters stay applied all the way down.
         */
        async fetchLevel() {
            this.loading = true;
            const token = ++this.requestToken;

            try {
                const params = new URLSearchParams(window.location.search);
                params.set('branch', this.activeBranch);

                // Clear any level keys inherited from the URL, then set our own path.
                (this.levels.length ? this.levels : this.knownLevelKeys)
                    .forEach(l => params.delete(typeof l === 'string' ? l : l.key));
                this.path.forEach(c => params.set(c.key, c.value));

                const res = await fetch("{{ route('reports.sales-report.drilldown') }}?" + params.toString());
                const data = await res.json();

                // A slower earlier request must not overwrite a newer one's results.
                if (token !== this.requestToken) return;

                if (data.status === 'success') {
                    this.levels = data.levels || [];
                    this.items = data.items || [];
                    this.levelMeta = data.level || null;
                    this.nextLevel = data.next_level || null;
                    this.isLeaf = !!data.is_leaf;
                    this.shown = data.shown || 0;
                    this.available = data.available || 0;
                    this.truncated = !!data.truncated;
                    this.periodMonths = data.period_months || 0;
                    this.drillTotalSales = data.formatted_total_sales;
                    this.drillInvoices = Number(data.total_invoices || 0).toLocaleString();
                    this.drillQty = Number(data.total_qty || 0).toLocaleString();

                    // Trust the server's view of the path -- it drops levels it could not
                    // apply (e.g. agent_name missing on a not-yet-synced database).
                    this.path = data.path || [];
                } else {
                    this.items = [];
                    console.error('Drilldown error:', data.message);
                }
            } catch (err) {
                if (token === this.requestToken) {
                    this.items = [];
                    console.error('Desktop drilldown error:', err);
                }
            } finally {
                if (token === this.requestToken) this.loading = false;
            }
        }
    };
}

/**
 * Capture Desktop YoY Comparison Card as PNG image and share/redirect to WhatsApp
 */
async function shareDesktopYoYCardToWhatsApp() {
    const card = document.getElementById('desktopYoyComparisonCard');
    const shareBtn = document.getElementById('desktopYoyShareBtn');
    const shareBtnText = document.getElementById('desktopYoyShareBtnText');
    if (!card) return;

    if (shareBtn) shareBtn.disabled = true;
    if (shareBtnText) shareBtnText.innerHTML = '<i class="fas fa-circle-notch fa-spin text-xs"></i> Sharing...';

    // Summary Text for WhatsApp
    let text = "📊 *MULTI-YEAR SALES COMPARISON (TILL DATE)*\n";
    @if(!empty($yoyComparison))
    text += "📅 *Period:* {{ $yoyComparison['current']['display_period'] }}\n";
    @if(!empty($selectedCategories))
    text += "🏷️ *Filter:* {{ implode(', ', $selectedCategories) }}\n";
    @endif
    @if(!empty($selectedBranches))
    text += "🏢 *Branches:* {{ implode(', ', $selectedBranches) }}\n";
    @endif
    text += "\n🟢 *{{ $yoyComparison['current']['fy_label'] }}:* {{ $yoyComparison['current']['formatted_sales'] }} ({{ number_format($yoyComparison['current']['total_invoices']) }} bills)\n";
    text += "🏢 *Branch Sales:*\n";
    @foreach($yoyComparison['current']['branches'] as $b)
    text += "  • {{ $b['name'] }}: {{ $b['formatted_sales'] }}\n";
    @endforeach

    text += "\n📅 *{{ $yoyComparison['last_year']['fy_label'] }}:* {{ $yoyComparison['last_year']['formatted_sales'] }} ({{ number_format($yoyComparison['last_year']['total_invoices']) }} bills)\n";
    @if(isset($yoyComparison['last_year']['formatted_diff']))
    text += "  📈 *Diff:* {{ $yoyComparison['last_year']['formatted_diff'] }}\n";
    @endif

    text += "\n📜 *{{ $yoyComparison['two_years_ago']['fy_label'] }}:* {{ $yoyComparison['two_years_ago']['formatted_sales'] }} ({{ number_format($yoyComparison['two_years_ago']['total_invoices']) }} bills)\n";
    @if(isset($yoyComparison['two_years_ago']['formatted_diff']))
    text += "  📈 *Diff:* {{ $yoyComparison['two_years_ago']['formatted_diff'] }}\n";
    @endif
    @endif

    text += "\n🚀 _InvoFlow Sales Insights_";

    try {
        if (typeof html2canvas === 'undefined') {
            await new Promise((res, rej) => {
                const s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                s.onload = res;
                s.onerror = rej;
                document.head.appendChild(s);
            });
        }

        const canvas = await html2canvas(card, {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#0f172a',
            logging: false,
            ignoreElements: (el) => el.classList && el.classList.contains('no-export')
        });

        canvas.toBlob(async (blob) => {
            const fileName = 'Sales-YoY-Comparison-' + new Date().toISOString().slice(0, 10) + '.png';
            const file = new File([blob], fileName, { type: 'image/png' });

            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({
                        title: 'YoY Sales Comparison',
                        text: text,
                        files: [file]
                    });
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        fallbackDownloadAndWhatsAppDesktop(blob, fileName, text);
                    }
                }
            } else {
                fallbackDownloadAndWhatsAppDesktop(blob, fileName, text);
            }

            if (shareBtn) shareBtn.disabled = false;
            if (shareBtnText) shareBtnText.innerText = 'Share on WhatsApp';
        }, 'image/png');
    } catch (err) {
        console.error('Share generation error:', err);
        window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(text), '_blank');
        if (shareBtn) shareBtn.disabled = false;
        if (shareBtnText) shareBtnText.innerText = 'Share on WhatsApp';
    }
}

function fallbackDownloadAndWhatsAppDesktop(blob, fileName, text) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 4000);

    const waUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text + "\n\n*(Card image has also been saved to your device)*");
    window.open(waUrl, '_blank');
}
</script>
@endsection
