@extends('layouts.mobile')

@section('content')
<div class="space-y-4 pb-8" x-data="mobileSalesReport()">

    {{-- COMPACT SLIM HEADER --}}
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-2xl p-3 px-4 text-white shadow-md border border-indigo-500/20">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-300 flex-shrink-0">
                    <i class="fas fa-chart-line text-sm"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-sm font-900 tracking-tight leading-none text-white truncate">Sales Report</h1>
                    <span class="text-[8px] text-indigo-300/70 font-black uppercase tracking-wider block mt-0.5 truncate">
                        {{ count($branchSummary) }} Branches · {{ $lastSyncTime }}
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-1.5 flex-shrink-0">
                @if($totalSyncedRecords > 0)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[8px] font-black bg-emerald-500/20 border border-emerald-400/30 text-emerald-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    {{ number_format($totalSyncedRecords) }}
                </span>
                @endif
                @if(auth()->user()->role === 'admin' || auth()->user()->hasPermission('mobile_sales_360', 'view'))
                <a href="{{ route('mobile.sales-360') }}"
                   class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[8px] font-black bg-white/15 border border-white/25 text-white hover:bg-white/25 transition-all">
                    <i class="fas fa-circle-nodes"></i> 360°
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- FILTER CONTROLS --}}
    <div class="bg-white/80 backdrop-blur-xl rounded-[1.75rem] border border-white shadow-sm p-4 space-y-3.5">
        
        {{-- Quick Period Filter Horizontal Scroll --}}
        <div>
            <div class="flex items-center justify-between mb-1.5 px-1">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                    <i class="fas fa-calendar-alt text-indigo-500"></i> Period Preset
                </label>
                @if($datePreset)
                <span class="text-[8px] font-black text-indigo-600 uppercase bg-indigo-50 px-2 py-0.5 rounded-md">
                    {{ strtoupper(str_replace('_', ' ', $datePreset)) }}
                </span>
                @endif
            </div>

            @php
                $presets = [
                    'this_fy'    => '🟢 FY 26-27 (Current)',
                    'prev_fy'    => '📅 FY 25-26',
                    'fy_24_25'   => '📜 FY 24-25',
                    'this_month' => '📆 This Month',
                    'last_month' => '⏮️ Last Month',
                    'today'      => '⭐ Today',
                    'all_time'   => '⚡ All Time',
                ];
            @endphp

            <div class="flex items-center gap-2 overflow-x-auto pb-1.5 no-scrollbar -mx-1 px-1">
                @foreach($presets as $pKey => $pLabel)
                @php
                    $isActive = ($datePreset === $pKey);
                    $presetUrl = route('mobile.sales-report', array_merge(request()->except(['date_range', 'from_date', 'to_date', 'page']), ['date_range' => $pKey]));
                @endphp
                <a href="{{ $presetUrl }}"
                    class="px-3 py-1.5 rounded-xl text-[11px] font-bold whitespace-nowrap transition-all flex-shrink-0 {{ $isActive ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 ring-2 ring-indigo-400/40' : 'bg-slate-100/80 hover:bg-slate-200 text-slate-700 border border-slate-200/60' }}">
                    {{ $pLabel }}
                </a>
                @endforeach
            </div>
        </div>

        {{-- Advanced Filter Form --}}
        <form method="GET" action="{{ route('mobile.sales-report') }}" id="mobileFilterForm" class="space-y-3">
            <input type="hidden" name="date_range" id="mobileDateRangeInput" value="{{ $datePreset }}">

            {{-- Transaction Type chips --}}
            <div class="border-t border-slate-100 pt-2.5">
                <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Transaction Type</label>
                <div class="flex items-center gap-2 overflow-x-auto pb-1.5 no-scrollbar -mx-1 px-1">
                    @foreach($txnTypeOptions as $tKey => $tMeta)
                    @php $tOn = in_array($tKey, $selectedTypes, true); @endphp
                    <label class="cursor-pointer select-none px-3 py-1.5 rounded-xl text-[11px] font-bold whitespace-nowrap flex-shrink-0 transition-all border {{ $tOn ? 'bg-emerald-600 text-white border-emerald-600 shadow-md shadow-emerald-600/30' : 'bg-slate-100/80 text-slate-600 border-slate-200/60' }}">
                        <input type="checkbox" name="txn_types[]" value="{{ $tKey }}" class="hidden mobile-type-toggle"
                            {{ $tOn ? 'checked' : '' }}>
                        {{ $tMeta['icon'] }} {{ $tMeta['label'] }}
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Product Category Multi-Select Horizontal Scroll Pills (One-Tap Instant Filter) --}}
            @if(!empty($allCategories))
            <div class="border-t border-slate-100 pt-2.5">
                <div class="flex items-center justify-between mb-1.5 px-1">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                        <i class="fas fa-tags text-indigo-500"></i> Product Category
                        @if(!empty($selectedCategories))
                        <span class="text-indigo-600 font-bold">({{ count($selectedCategories) }} selected)</span>
                        @endif
                    </label>
                    @if(!empty($selectedCategories))
                    <button type="button" onclick="clearAllCategories()"
                       class="text-[8px] font-black text-rose-500 hover:text-rose-600 uppercase bg-rose-50 px-2 py-0.5 rounded-md flex items-center gap-1">
                        <i class="fas fa-times"></i> Clear All
                    </button>
                    @endif
                </div>

                <div class="flex items-center gap-2 overflow-x-auto pb-1.5 no-scrollbar -mx-1 px-1">
                    @php $isAllCatActive = empty($selectedCategories); @endphp
                    {{-- ALL CATEGORIES PILL BUTTON --}}
                    <button type="button" onclick="clearAllCategories()"
                        class="px-3 py-1.5 rounded-xl text-[11px] font-bold whitespace-nowrap transition-all flex-shrink-0 flex items-center gap-1.5 {{ $isAllCatActive ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 ring-2 ring-indigo-400/40' : 'bg-slate-100/80 hover:bg-slate-200 text-slate-700 border border-slate-200/60' }}">
                        <span>✨</span>
                        <span>All Categories</span>
                    </button>

                    @foreach($allCategories as $cat)
                    @php
                        $isCatActive = in_array($cat, $selectedCategories, true);
                        $meta = \App\Http\Controllers\ReportController::categoryMeta($cat);
                    @endphp
                    <label class="cursor-pointer select-none px-3 py-1.5 rounded-xl text-[11px] font-bold whitespace-nowrap flex-shrink-0 transition-all flex items-center gap-1.5 border {{ $isCatActive ? ($meta['active_class'] . ' shadow-md ring-2 ring-indigo-400/40') : 'bg-slate-100/80 hover:bg-slate-200 text-slate-700 border-slate-200/60' }}">
                        <input type="checkbox" name="categories[]" value="{{ $cat }}" class="hidden mobile-cat-toggle"
                            {{ $isCatActive ? 'checked' : '' }}>
                        <span>{{ $meta['icon'] }}</span>
                        <span>{{ $meta['label'] }}</span>
                        @if($isCatActive)
                            <i class="fas fa-check text-[9px] ml-0.5 opacity-90"></i>
                        @endif
                    </label>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Collapsible Advanced Filters --}}
            @php
                $activeAdvanced = (int)!empty($selectedBranches)
                    + (int)($datePreset === 'custom')
                    + (int)!empty($searchQuery);
            @endphp
            <div x-data="{ expanded: false }" class="space-y-3 border-t border-slate-100 pt-2.5">
                <button type="button" @click="expanded = !expanded" class="w-full flex items-center justify-between text-[10px] font-black text-indigo-600 uppercase tracking-wider py-1 px-1">
                    <span class="flex items-center gap-1.5">
                        <i class="fas fa-sliders-h"></i>
                        <span>Custom Date Range & Branch Filter</span>
                        @if($activeAdvanced > 0)
                        <span class="px-1.5 py-0.5 rounded-md bg-indigo-600 text-white text-[8px] leading-none">{{ $activeAdvanced }}</span>
                        @endif
                    </span>
                    <i class="fas fa-chevron-down transition-transform duration-200 text-[9px]" :class="expanded ? 'rotate-180' : ''"></i>
                </button>

                <div x-show="expanded" x-cloak x-collapse class="space-y-3 pt-1">
                    {{-- Date Pickers --}}
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 ml-1">From Date</label>
                            <input type="date" name="from_date" id="mobileFromDate" value="{{ $fromDate }}"
                                onchange="document.getElementById('mobileDateRangeInput').value='custom'"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-2.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 ml-1">To Date</label>
                            <input type="date" name="to_date" id="mobileToDate" value="{{ $toDate }}"
                                onchange="document.getElementById('mobileDateRangeInput').value='custom'"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-2.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none">
                        </div>
                    </div>

                    {{-- Branch multi-select --}}
                    <div>
                        <div class="flex items-center justify-between mb-1 ml-1">
                            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest">
                                Branch
                                @if(count($selectedBranches) > 1)
                                    <span class="text-emerald-600">({{ count($selectedBranches) }} selected)</span>
                                @endif
                            </label>
                            @if(!empty($selectedBranches))
                            <button type="button" onclick="clearMobileBranches()"
                                class="text-[9px] font-black text-indigo-600 uppercase tracking-wider">Clear</button>
                            @endif
                        </div>

                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-1.5 max-h-44 overflow-y-auto space-y-0.5">
                            @foreach($allBranchNames as $bName)
                            <label class="flex items-center gap-2.5 px-2 py-2 rounded-lg active:bg-slate-100 cursor-pointer">
                                <input type="checkbox" name="branches[]" value="{{ $bName }}"
                                    class="mobile-branch-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-400"
                                    {{ in_array($bName, $selectedBranches, true) ? 'checked' : '' }}>
                                <span class="text-xs font-bold text-slate-700">{{ $bName }}</span>
                            </label>
                            @endforeach
                        </div>
                        <p class="text-[9px] text-slate-400 mt-1 ml-1">Kuch bhi tick na karo = saari branches</p>
                    </div>

                    {{-- Keyword Search --}}
                    <div>
                        <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 ml-1">Search Keyword</label>
                        <input type="text" name="search" value="{{ $searchQuery }}" placeholder="Item / Party / Branch..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-xs font-medium text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none">
                    </div>
                </div>
            </div>

            {{-- Submit & Reset Buttons --}}
            <div class="flex items-center gap-2 pt-1">
                <button type="submit"
                    class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black rounded-xl shadow-md shadow-indigo-600/20 active:scale-95 transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-filter text-[10px]"></i>
                    <span>Apply Filter</span>
                </button>

                @if($fromDate || $toDate || !empty($selectedBranches) || !empty($selectedCategories) || $searchQuery || ($datePreset && $datePreset !== 'this_fy') || $selectedTypes !== \App\Http\Controllers\ReportController::DEFAULT_TXN_TYPES)
                <a href="{{ route('mobile.sales-report') }}"
                    class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl active:scale-95 transition-all flex items-center justify-center" title="Reset Filters">
                    <i class="fas fa-undo text-[11px]"></i>
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- ACTIVE CATEGORY FILTER BADGE --}}
    @if(!empty($selectedCategories))
    <div class="bg-gradient-to-r from-indigo-50 via-purple-50 to-indigo-50 border border-indigo-200/80 rounded-2xl p-3 px-4 shadow-sm flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-7 h-7 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs flex-shrink-0 shadow-sm">
                <i class="fas fa-tags"></i>
            </div>
            <div class="min-w-0">
                <div class="text-[9px] font-black uppercase tracking-wider text-indigo-500">Active Category Filter</div>
                <div class="text-xs font-black text-indigo-950 truncate flex items-center gap-1.5 mt-0.5 flex-wrap">
                    @foreach($selectedCategories as $sc)
                        @php $scMeta = \App\Http\Controllers\ReportController::categoryMeta($sc); @endphp
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] {{ $scMeta['badge_class'] }}">
                            <span>{{ $scMeta['icon'] }}</span>
                            <span>{{ $scMeta['label'] }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
        <a href="{{ route('mobile.sales-report', request()->except(['category', 'categories', 'page'])) }}"
           class="text-[10px] font-black text-rose-600 bg-white hover:bg-rose-50 border border-rose-200 px-2.5 py-1.5 rounded-xl shadow-xs flex-shrink-0 flex items-center gap-1">
            <i class="fas fa-times text-[9px]"></i> Reset
        </a>
    </div>
    @endif

    {{-- MULTI-YEAR YOY SALES COMPARISON CARD (Same Period / Till Date) --}}
    @if(!empty($yoyComparison))
    <div id="yoyComparisonCard" class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 rounded-[1.75rem] p-4 text-white shadow-xl border border-indigo-500/20 relative overflow-hidden space-y-3.5">
        {{-- Background ambient glow --}}
        <div class="absolute -right-8 -top-8 w-40 h-40 rounded-full bg-indigo-500/20 blur-2xl pointer-events-none"></div>
        <div class="absolute -left-8 -bottom-8 w-40 h-40 rounded-full bg-emerald-500/15 blur-2xl pointer-events-none"></div>

        {{-- Header --}}
        <div class="relative z-10 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-white/10 backdrop-blur-md flex items-center justify-center text-emerald-300 text-sm shadow-inner">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h3 class="text-xs font-900 uppercase tracking-wider text-white flex items-center gap-1.5">
                        <span>YoY Period Comparison</span>
                        <span class="text-[9px] px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-black">
                            Same Dates
                        </span>
                    </h3>
                    <p class="text-[9px] text-indigo-200/70 font-medium">
                        Current Date tak pichle saalo ki bikri tulna
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1.5 flex-shrink-0">
                @if(!empty($selectedCategories))
                <span class="text-[8px] font-black uppercase px-2 py-0.5 rounded-md bg-white/10 text-indigo-200 border border-white/10 flex items-center gap-1 hidden sm:flex">
                    <i class="fas fa-tags text-[7px] text-indigo-400"></i> {{ count($selectedCategories) }}
                </span>
                @endif

                {{-- WhatsApp Share Button --}}
                <button type="button" onclick="shareYoYCardToWhatsApp()" id="yoyShareBtn"
                    class="px-2.5 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 active:scale-95 text-white text-[10px] font-900 transition-all flex items-center gap-1.5 shadow-md shadow-emerald-500/30 cursor-pointer no-export">
                    <i class="fab fa-whatsapp text-xs text-white"></i>
                    <span id="yoyShareBtnText">Share</span>
                </button>
            </div>
        </div>

        {{-- 3-Period Cards with Right-Side Branch Breakdown --}}
        <div class="relative z-10 space-y-3">

            @php
                $pCards = [
                    'current' => [
                        'border' => 'border-emerald-400/35 bg-emerald-500/10',
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
                $style = $pCards[$pKey];
            @endphp
            <div class="backdrop-blur-md rounded-2xl p-3 border {{ $style['border'] }} relative overflow-hidden space-y-2">
                
                {{-- Top: Badge & Invoices --}}
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md {{ $style['badge'] }}">
                        {{ $p['badge'] }}
                    </span>
                    <span class="text-[8px] text-slate-300 font-mono font-bold">
                        {{ number_format($p['total_invoices']) }} bills
                    </span>
                </div>

                {{-- Grid: Left = Main Total, Right = Branch Breakdown --}}
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-start">
                    
                    {{-- Left Column: Total Amount & Growth --}}
                    <div class="sm:col-span-5 space-y-0.5">
                        <div class="text-[9px] text-slate-300 font-bold truncate">
                            {{ $p['display_period'] }}
                        </div>
                        <div class="text-xl sm:text-2xl font-900 {{ $style['sales'] }} tracking-tight">
                            {{ $p['formatted_sales'] }}
                        </div>
                        <div class="text-[8px] text-slate-400 font-mono">
                            {{ $p['exact_sales'] }}
                        </div>
                        @if(isset($p['formatted_diff']))
                        <div class="pt-0.5">
                            <span class="text-[8px] font-black px-1.5 py-0.5 rounded-md inline-flex items-center gap-1 {{ $p['is_growth'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                <i class="fas {{ $p['is_growth'] ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[7px]"></i>
                                {{ $p['formatted_diff'] }}
                            </span>
                        </div>
                        @endif
                    </div>

                    {{-- Right Column: Branch-Wise Breakdown --}}
                    <div class="sm:col-span-7 bg-black/30 rounded-xl p-2 border border-white/5 space-y-1">
                        <div class="flex items-center justify-between text-[8px] font-black uppercase tracking-wider text-indigo-200/90 px-0.5">
                            <span class="flex items-center gap-1">
                                <i class="fas fa-building text-[7px] text-indigo-400"></i>
                                <span>Branch Breakdown</span>
                            </span>
                            <span class="text-[7px] text-slate-400 font-mono">{{ count($p['branches']) }} Branches</span>
                        </div>

                        <div class="grid grid-cols-2 gap-1">
                            @forelse($p['branches'] as $br)
                            <div class="bg-white/5 hover:bg-white/10 rounded-lg px-2 py-1 flex items-center justify-between gap-1 border border-white/5 transition-colors">
                                <span class="text-[9px] font-bold text-slate-300 truncate" title="{{ $br['name'] }}">
                                    {{ $br['name'] }}
                                </span>
                                <span class="text-[9px] font-mono font-black text-emerald-300 flex-shrink-0">
                                    {{ $br['formatted_sales'] }}
                                </span>
                            </div>
                            @empty
                            <div class="col-span-2 text-center text-[8px] text-slate-400 py-1">No branch records</div>
                            @endforelse
                        </div>
                    </div>

                </div>

            </div>
            @endforeach

        </div>

        {{-- Comparative Proportion Visual Bar --}}
        <div class="relative z-10 bg-black/20 rounded-xl p-2.5 border border-white/5 space-y-1.5">
            <div class="flex items-center justify-between text-[8px] font-black uppercase tracking-wider text-indigo-200/80">
                <span>Revenue Scale Comparison (Same Dates)</span>
                <span class="text-emerald-400">CY vs LY vs 2Y Ago</span>
            </div>
            <div class="space-y-1">
                {{-- Current FY Bar --}}
                <div class="flex items-center gap-2">
                    <span class="w-16 text-[8px] font-bold text-slate-300 truncate">FY 26-27</span>
                    <div class="flex-1 bg-white/10 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-emerald-400 h-1.5 rounded-full" style="width: {{ $yoyComparison['current']['bar_percent'] }}%"></div>
                    </div>
                    <span class="w-14 text-right text-[8px] font-mono font-bold text-emerald-300">{{ $yoyComparison['current']['formatted_sales'] }}</span>
                </div>
                {{-- Last FY Bar --}}
                <div class="flex items-center gap-2">
                    <span class="w-16 text-[8px] font-bold text-slate-300 truncate">FY 25-26</span>
                    <div class="flex-1 bg-white/10 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-indigo-400 h-1.5 rounded-full" style="width: {{ $yoyComparison['last_year']['bar_percent'] }}%"></div>
                    </div>
                    <span class="w-14 text-right text-[8px] font-mono font-bold text-slate-300">{{ $yoyComparison['last_year']['formatted_sales'] }}</span>
                </div>
                {{-- 2 Years Ago Bar --}}
                <div class="flex items-center gap-2">
                    <span class="w-16 text-[8px] font-bold text-slate-300 truncate">FY 24-25</span>
                    <div class="flex-1 bg-white/10 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-amber-400 h-1.5 rounded-full" style="width: {{ $yoyComparison['two_years_ago']['bar_percent'] }}%"></div>
                    </div>
                    <span class="w-14 text-right text-[8px] font-mono font-bold text-slate-300">{{ $yoyComparison['two_years_ago']['formatted_sales'] }}</span>
                </div>
            </div>
        </div>

        {{-- Branch-Wise Multi-Year Direct Comparison Table --}}
        @if(!empty($yoyComparison['branch_comparison']))
        <div class="relative z-10 bg-black/25 rounded-2xl p-2.5 border border-white/5 space-y-2">
            <div class="flex items-center justify-between text-[8px] font-black uppercase tracking-wider text-indigo-200/90 px-0.5">
                <span class="flex items-center gap-1.5">
                    <i class="fas fa-table-columns text-emerald-400"></i>
                    <span>Branch Multi-Year Summary (Till Date)</span>
                </span>
                <span class="text-slate-400 font-mono">{{ count($yoyComparison['branch_comparison']) }} Branches</span>
            </div>

            <div class="overflow-x-auto no-scrollbar -mx-1 px-1">
                <table class="w-full text-left border-collapse text-[9px]">
                    <thead>
                        <tr class="border-b border-white/10 text-[8px] font-black uppercase tracking-wider text-slate-400">
                            <th class="py-1 pr-2">Branch</th>
                            <th class="py-1 px-1 text-right text-emerald-300">FY 26-27</th>
                            <th class="py-1 px-1 text-right text-indigo-300">FY 25-26</th>
                            <th class="py-1 px-1 text-right text-amber-300">FY 24-25</th>
                            <th class="py-1 pl-1 text-right">Growth</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 font-mono">
                        @foreach($yoyComparison['branch_comparison'] as $bc)
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="py-1 pr-2 font-sans font-bold text-slate-200 truncate max-w-[90px]">
                                {{ $bc['name'] }}
                            </td>
                            <td class="py-1 px-1 text-right font-black text-emerald-300 whitespace-nowrap">
                                {{ $bc['formatted_cur'] }}
                            </td>
                            <td class="py-1 px-1 text-right text-slate-300 whitespace-nowrap">
                                {{ $bc['formatted_ly'] }}
                            </td>
                            <td class="py-1 px-1 text-right text-slate-400 whitespace-nowrap">
                                {{ $bc['formatted_lly'] }}
                            </td>
                            <td class="py-1 pl-1 text-right font-bold whitespace-nowrap {{ $bc['is_growth'] ? 'text-emerald-400' : 'text-rose-400' }}">
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
    <div class="grid grid-cols-2 gap-3">
        
        {{-- Total Sales --}}
        <div class="bg-white/90 backdrop-blur-md rounded-[1.5rem] p-4 border border-emerald-100 shadow-sm relative overflow-hidden col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Total Sales Revenue</span>
                <div class="w-7 h-7 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fas fa-indian-rupee-sign"></i>
                </div>
            </div>
            <div class="text-2xl font-900 text-emerald-600 tracking-tight">
                {{ $formattedGrandSales }}
            </div>
            <div class="text-[10px] text-slate-400 font-mono font-bold mt-0.5">
                ₹ {{ number_format($grandTotalSales, 2) }}
            </div>
        </div>

        {{-- Top Branch --}}
        <div class="bg-white/90 backdrop-blur-md rounded-[1.5rem] p-4 border border-indigo-100 shadow-sm relative overflow-hidden col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Top Performing Branch</span>
                <div class="w-7 h-7 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center text-xs">
                    <i class="fas fa-trophy"></i>
                </div>
            </div>
            <div class="text-lg font-900 text-indigo-950 truncate">
                {{ $topBranch['branch_name'] ?? 'N/A' }}
            </div>
            <div class="text-[10px] text-slate-500 font-bold mt-0.5">
                @if($topBranch)
                <span class="text-emerald-600 font-bold">{{ $topBranch['formatted_sales'] }}</span> ({{ $topBranch['share_percent'] }}% share)
                @else
                No records
                @endif
            </div>
        </div>

        {{-- Invoices --}}
        <div class="bg-white/90 backdrop-blur-md rounded-[1.5rem] p-4 border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Total Bills</span>
                <div class="w-7 h-7 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center text-xs">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
            <div class="text-xl font-900 text-slate-800 tracking-tight">
                {{ number_format($grandTotalInvoices) }}
            </div>
            <div class="text-[9px] text-slate-400 font-bold mt-0.5">
                Across active branches
            </div>
        </div>

        {{-- Quantity --}}
        <div class="bg-white/90 backdrop-blur-md rounded-[1.5rem] p-4 border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Total Quantity</span>
                <div class="w-7 h-7 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                    <i class="fas fa-boxes-stacked"></i>
                </div>
            </div>
            <div class="text-xl font-900 text-slate-800 tracking-tight">
                {{ number_format($grandTotalQty) }}
            </div>
            <div class="text-[9px] text-slate-400 font-bold mt-0.5">
                Units & Packets Sold
            </div>
        </div>
    </div>

    {{-- BRANCH-WISE PERFORMANCE RANKING --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between px-1">
            <h2 class="text-[11px] font-900 text-slate-800 uppercase tracking-[0.15em] flex items-center gap-2">
                <i class="fas fa-building text-indigo-600"></i>
                <span>Branch Revenue Breakdown</span>
            </h2>
            <span class="text-[9px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                {{ count($branchSummary) }} Ranked
            </span>
        </div>

        <div class="space-y-2.5">
            @forelse($branchSummary as $b)
            @php
                $rankBadges = [
                    1 => ['bg' => 'bg-amber-500 text-white shadow-amber-500/20', 'label' => '#1 Ranked'],
                    2 => ['bg' => 'bg-slate-400 text-white shadow-slate-400/20', 'label' => '#2 Ranked'],
                    3 => ['bg' => 'bg-amber-700 text-white shadow-amber-700/20', 'label' => '#3 Ranked'],
                ];
                $badge = $rankBadges[$b['rank']] ?? ['bg' => 'bg-slate-100 text-slate-600 border border-slate-200', 'label' => '#' . $b['rank'] . ' Ranked'];
            @endphp
            <div @click="openDrilldown('{{ $b['branch_name'] }}')"
                 class="bg-white/90 backdrop-blur-xl rounded-[1.5rem] p-4 border border-white shadow-sm hover:shadow-md hover:border-indigo-200 transition-all space-y-3 cursor-pointer active:scale-98 group">
                
                {{-- Top row: Rank & Branch Name & Sales Amount --}}
                <div class="flex items-start justify-between gap-2">
                    <div class="space-y-1">
                        <span class="inline-block px-2 py-0.5 rounded-lg text-[8px] font-black uppercase tracking-wider {{ $badge['bg'] }} shadow-sm">
                            {{ $badge['label'] }}
                        </span>
                        <h3 class="text-base font-900 text-slate-800 tracking-tight leading-tight flex items-center gap-1.5">
                            <span>{{ $b['branch_name'] }}</span>
                            <i class="fas fa-arrow-up-right-from-square text-[9px] text-indigo-400 group-hover:text-indigo-600 transition-colors"></i>
                        </h3>
                    </div>

                    <div class="text-right">
                        <div class="text-lg font-900 text-emerald-600 tracking-tight">
                            {{ $b['formatted_sales'] }}
                        </div>
                        <div class="text-[9px] font-bold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-md inline-block mt-0.5">
                            {{ $b['share_percent'] }}% Share
                        </div>
                    </div>
                </div>

                {{-- Visual Revenue Share Progress Bar --}}
                <div class="space-y-1">
                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full bg-gradient-to-r from-indigo-500 to-emerald-500 transition-all duration-500"
                            style="width: {{ min(100, max(2, $b['share_percent'])) }}%"></div>
                    </div>
                </div>

                {{-- Bottom 3 Metrics Grid --}}
                <div class="grid grid-cols-3 gap-2 bg-slate-50/80 rounded-2xl p-2.5 text-center border border-slate-100">
                    <div>
                        <div class="text-[8px] font-black text-slate-400 uppercase">Invoices</div>
                        <div class="text-xs font-900 text-slate-700 mt-0.5 font-mono">{{ number_format($b['total_invoices']) }}</div>
                    </div>
                    <div class="border-x border-slate-200/60">
                        <div class="text-[8px] font-black text-slate-400 uppercase">Total Qty</div>
                        <div class="text-xs font-900 text-slate-700 mt-0.5 font-mono">{{ number_format($b['total_qty']) }}</div>
                    </div>
                    <div>
                        <div class="text-[8px] font-black text-slate-400 uppercase">Avg Order</div>
                        <div class="text-xs font-900 text-indigo-600 mt-0.5 font-mono">{{ $b['formatted_aov'] }}</div>
                    </div>
                </div>

                {{-- Reporting Period Span & Tap Hint --}}
                @php
                    $displayStart = $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d M Y') : ($b['min_date'] ? \Carbon\Carbon::parse($b['min_date'])->format('d M Y') : '01 Apr 2026');
                    $displayEnd = $toDate ? \Carbon\Carbon::parse($toDate)->format('d M Y') : ($b['max_date'] ? \Carbon\Carbon::parse($b['max_date'])->format('d M Y') : now()->format('d M Y'));
                    if ($b['max_date'] && \Carbon\Carbon::parse($displayEnd)->isFuture()) {
                        $displayEnd = \Carbon\Carbon::parse($b['max_date'])->format('d M Y');
                    }
                @endphp
                <div class="text-[8px] text-slate-400 font-bold flex items-center justify-between pt-1 px-1 border-t border-slate-100">
                    <span>Period: {{ $displayStart }} → {{ $displayEnd }}</span>
                    <span class="text-indigo-600 font-black flex items-center gap-0.5">
                        <span>Series Details</span>
                        <i class="fas fa-chevron-right text-[7px]"></i>
                    </span>
                </div>
            </div>
            @empty
            <div class="bg-white rounded-3xl p-8 text-center border border-slate-100 shadow-sm space-y-2">
                <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto text-xl">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="text-sm font-bold text-slate-700">No Sales Records Found</div>
                <p class="text-xs text-slate-400">Try changing the date range or branch filter.</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- TOP PRODUCTS & PARTIES ACCORDION --}}
    @if(count($topProducts) > 0 || count($topParties) > 0)
    <div class="space-y-3 pt-2" x-data="{ tab: 'products' }">
        <div class="flex items-center justify-between px-1">
            <h2 class="text-[11px] font-900 text-slate-800 uppercase tracking-[0.15em]">
                Business Highlights
            </h2>
            <div class="flex items-center bg-slate-100 p-0.5 rounded-xl text-[10px] font-bold">
                <button type="button" @click="tab = 'products'"
                    :class="tab === 'products' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500'"
                    class="px-2.5 py-1 rounded-lg transition-all">
                    Top Items
                </button>
                <button type="button" @click="tab = 'parties'"
                    :class="tab === 'parties' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500'"
                    class="px-2.5 py-1 rounded-lg transition-all">
                    Top Customers
                </button>
            </div>
        </div>

        {{-- Top Products Tab --}}
        <div x-show="tab === 'products'" class="space-y-2">
            @foreach($topProducts as $idx => $prod)
            <div class="bg-white/80 backdrop-blur-md rounded-2xl p-3 border border-slate-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-6 h-6 rounded-xl bg-indigo-50 text-indigo-600 text-[10px] font-black flex items-center justify-center flex-shrink-0">
                        {{ $idx + 1 }}
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-slate-800 truncate" title="{{ $prod['item_name'] }}">
                            {{ $prod['item_name'] }}
                        </div>
                        <div class="text-[9px] text-slate-400 font-mono">
                            Qty: {{ number_format($prod['total_qty']) }}
                        </div>
                    </div>
                </div>
                <div class="text-xs font-900 text-emerald-600 font-mono flex-shrink-0">
                    {{ $prod['formatted_sales'] }}
                </div>
            </div>
            @endforeach
        </div>

        {{-- Top Parties Tab --}}
        <div x-show="tab === 'parties'" class="space-y-2" x-cloak>
            @foreach($topParties as $idx => $pty)
            <div class="bg-white/80 backdrop-blur-md rounded-2xl p-3 border border-slate-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-6 h-6 rounded-xl bg-violet-50 text-violet-600 text-[10px] font-black flex items-center justify-center flex-shrink-0">
                        {{ $idx + 1 }}
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-slate-800 truncate" title="{{ $pty['party_name'] }}">
                            {{ $pty['party_name'] }}
                        </div>
                        <div class="text-[9px] text-slate-400">
                            {{ $pty['branch_name'] }} · {{ $pty['invoice_count'] }} Bills
                        </div>
                    </div>
                </div>
                <div class="text-xs font-900 text-emerald-600 font-mono flex-shrink-0">
                    {{ $pty['formatted_sales'] }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- iOS STYLE BOTTOM SHEET DRAWER FOR SERIES & CATEGORIES DRILLDOWN --}}
    <div x-show="sheetOpen" x-cloak class="fixed inset-0 z-[100] flex items-end justify-center"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="closeDrilldown()"></div>

        {{-- Drawer Panel --}}
        <div class="relative w-full max-w-lg bg-white rounded-t-[2.5rem] p-5 pb-8 shadow-2xl flex flex-col max-h-[88vh] z-10 border-t border-slate-100 overflow-hidden"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full">

            {{-- iOS Grab Handle --}}
            <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-3 flex-shrink-0 cursor-pointer" @click="closeDrilldown()"></div>

            {{-- Drawer Header --}}
            <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100 flex-shrink-0">
                <div class="min-w-0 flex-1">
                    {{-- Breadcrumb — scrolls sideways so a deep path never wraps the drawer --}}
                    <div class="flex items-center gap-1 overflow-x-auto no-scrollbar text-[10px] font-black mb-1.5 -mx-0.5 px-0.5">
                        <button @click="goToDepth(0)"
                                class="px-1.5 py-0.5 rounded-lg whitespace-nowrap flex-shrink-0"
                                :class="path.length ? 'bg-indigo-50 text-indigo-600' : 'text-slate-700'">
                            🏢 <span x-text="activeBranch"></span>
                        </button>

                        <template x-for="(crumb, cIdx) in path" :key="crumb.key">
                            <span class="flex items-center gap-1 flex-shrink-0">
                                <i class="fas fa-chevron-right text-[7px] text-slate-300"></i>
                                <button @click="goToDepth(cIdx + 1)"
                                        class="px-1.5 py-0.5 rounded-lg whitespace-nowrap max-w-[9rem] truncate"
                                        :class="cIdx + 1 < path.length ? 'bg-indigo-50 text-indigo-600' : 'text-slate-700'">
                                    <span x-text="crumb.icon"></span> <span x-text="crumb.display"></span>
                                </button>
                            </span>
                        </template>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-lg font-900 text-slate-800 tracking-tight leading-tight truncate"
                            x-text="path.length ? path[path.length - 1].display : activeBranch"></h3>
                        <template x-if="levelMeta">
                            <span class="text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600"
                                  x-text="levelMeta.label"></span>
                        </template>
                    </div>

                    <p class="text-[9px] text-slate-400 font-bold mt-0.5"
                       x-text="isLeaf
                            ? 'Yahi sabse andar ka level hai.'
                            : ('Tap karo ' + (nextLevel || 'next level').toLowerCase() + ' dekhne ke liye')"></p>
                </div>

                <button @click="closeDrilldown()" class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-600 active:scale-90 transition flex-shrink-0">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            {{-- Total Summary Banner in Sheet --}}
            <div class="bg-gradient-to-r from-slate-900 to-indigo-950 rounded-2xl p-3.5 text-white my-3 flex items-center justify-between flex-shrink-0 shadow-md gap-3">
                <div class="min-w-0">
                    <div class="text-[8px] font-black text-indigo-300/80 uppercase tracking-widest truncate"
                         x-text="(path.length ? path[path.length - 1].display : activeBranch) + ' Total'"></div>
                    <div class="text-xl font-900 text-emerald-400 tracking-tight" x-text="drillTotalSales"></div>
                </div>
                <div class="text-right text-[9px] font-bold text-indigo-200/80 space-y-0.5 flex-shrink-0">
                    <div>Bills: <span class="text-white font-black" x-text="drillInvoices"></span></div>
                    <div>Quantity: <span class="text-white font-black" x-text="drillQty"></span></div>
                </div>
            </div>

            {{-- Back one level --}}
            <template x-if="path.length > 0">
                <button @click="goToDepth(path.length - 1)"
                        class="self-start mb-2 flex items-center gap-1.5 text-[10px] font-black text-indigo-600 uppercase tracking-wider flex-shrink-0">
                    <i class="fas fa-arrow-left text-[9px]"></i>
                    <span x-text="'Back to ' + (path.length > 1 ? path[path.length - 2].display : activeBranch)"></span>
                </button>
            </template>

            {{-- Content Area (Scrollable) --}}
            <div class="flex-1 overflow-y-auto space-y-2.5 pr-1 -mr-1">

                {{-- Loading Skeleton --}}
                <template x-if="loading">
                    <div class="space-y-2.5 py-8 text-center">
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-600 rounded-2xl text-xs font-bold animate-pulse">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <span>Fetching live breakdown...</span>
                        </div>
                    </div>
                </template>

                {{-- One generic list for every level of the chain --}}
                <template x-if="!loading">
                    <div class="space-y-2.5">
                        <template x-if="items.length === 0">
                            <div class="text-center py-6 text-slate-400 text-xs font-bold"
                                 x-text="isLeaf ? 'Ye sabse andar ka level hai.' : 'Is filter pe koi record nahi mila.'"></div>
                        </template>

                        <template x-if="truncated">
                            <div class="text-[9px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-2.5 py-1.5">
                                Top <span x-text="shown"></span> of <span x-text="available"></span> dikha rahe hain (value ke hisaab se)
                            </div>
                        </template>

                        <template x-for="item in items" :key="item.key">
                            <div @click="drillInto(item)"
                                 class="bg-slate-50 p-3 rounded-2xl border border-slate-100 space-y-2"
                                 :class="isLeaf ? '' : 'active:scale-[0.99] transition cursor-pointer'">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="space-y-1 min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <template x-if="item.code">
                                                <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase font-mono"
                                                      :class="item.is_return ? 'bg-rose-100 text-rose-700' : 'bg-indigo-100 text-indigo-700'"
                                                      x-text="item.code"></span>
                                            </template>
                                            <span class="text-xs font-black text-slate-800 truncate" x-text="item.label"></span>
                                            <template x-if="item.bill_date">
                                                <span class="text-[8px] font-bold text-slate-400 font-mono" x-text="item.bill_date"></span>
                                            </template>
                                        </div>
                                        <div class="text-[9px] text-slate-400 truncate">
                                            <span class="font-bold text-slate-600" x-text="Number(item.total_invoices).toLocaleString()"></span> bills &middot;
                                            <span class="font-bold text-slate-600" x-text="Number(item.total_qty).toLocaleString()"></span> units
                                            <template x-if="item.bill_party">
                                                <span> &middot; <span class="font-bold text-slate-600" x-text="item.bill_party"></span></span>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="text-right flex items-center gap-2 flex-shrink-0">
                                        <div>
                                            <div class="text-xs font-black"
                                                 :class="item.is_return ? 'text-rose-600' : 'text-emerald-600'"
                                                 x-text="item.formatted_sales"></div>
                                            <div class="text-[8px] font-bold text-slate-400" x-text="item.share_percent + '%'"></div>
                                        </div>
                                        <template x-if="!isLeaf">
                                            <i class="fas fa-chevron-right text-[10px] text-slate-300"></i>
                                        </template>
                                    </div>
                                </div>

                                <div class="w-full bg-slate-200/70 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 rounded-full transition-all duration-300"
                                         :class="item.is_return ? 'bg-rose-500' : 'bg-indigo-600'"
                                         :style="'width: ' + Math.min(100, Math.max(3, Math.abs(item.share_percent))) + '%'"></div>
                                </div>

                                {{-- Target vs achievement, agent level only --}}
                                <template x-if="levelMeta && levelMeta.key === 'agent'">
                                    <div class="pt-1.5 border-t border-slate-200/70 space-y-1">
                                        <template x-if="item.target">
                                            <div class="space-y-1">
                                                <div class="flex items-center justify-between text-[9px] font-bold">
                                                    <span class="text-slate-500">Target
                                                        <span class="text-slate-800 font-black" x-text="item.formatted_target"></span>
                                                    </span>
                                                    <span class="font-black"
                                                          :class="item.achievement_percent >= 100 ? 'text-emerald-600' : (item.achievement_percent >= 75 ? 'text-amber-600' : 'text-rose-600')"
                                                          x-text="item.achievement_percent + '%'"></span>
                                                </div>
                                                <div class="w-full bg-slate-200/70 rounded-full h-1 overflow-hidden">
                                                    <div class="h-1 rounded-full"
                                                         :class="item.achievement_percent >= 100 ? 'bg-emerald-500' : (item.achievement_percent >= 75 ? 'bg-amber-500' : 'bg-rose-500')"
                                                         :style="'width: ' + Math.min(100, Math.max(2, item.achievement_percent)) + '%'"></div>
                                                </div>
                                                <template x-if="item.target_months < periodMonths">
                                                    <div class="text-[8px] font-bold text-amber-700"
                                                         x-text="'Target sirf ' + item.target_months + '/' + periodMonths + ' mahine ka set hai'"></div>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="!item.target">
                                            <div class="text-[9px] font-bold text-slate-400">Koi target set nahi</div>
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

</div>

@push('scripts')
<script>
/* Tapping a transaction-type chip reloads straight away -- no Apply needed for these. */
document.querySelectorAll('.mobile-type-toggle').forEach(el => {
    el.addEventListener('change', () => document.getElementById('mobileFilterForm').submit());
});

/* Tapping a category chip reloads straight away with multi-select support */
document.querySelectorAll('.mobile-cat-toggle').forEach(el => {
    el.addEventListener('change', () => document.getElementById('mobileFilterForm').submit());
});

/* Branches stay behind the Apply button so several can be ticked in one go. */
function clearMobileBranches() {
    document.querySelectorAll('.mobile-branch-checkbox').forEach(cb => { cb.checked = false; });
    document.getElementById('mobileFilterForm').submit();
}

/* Clear all selected categories and reload */
function clearAllCategories() {
    document.querySelectorAll('.mobile-cat-toggle').forEach(cb => { cb.checked = false; });
    document.getElementById('mobileFilterForm').submit();
}

function mobileSalesReport() {
    return {
        sheetOpen: false,
        loading: false,
        activeBranch: '',
        drillTotalSales: '₹ 0.00',
        drillInvoices: '0',
        drillQty: '0',

        /* Drill-down state. `levels` comes from the server, so the chain is never
           hardcoded here -- reordering it server-side just works. */
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

        selectPreset(presetKey) {
            document.getElementById('mobileDateRangeInput').value = presetKey;
            document.getElementById('mobileFilterForm').submit();
        },

        openDrilldown(branchName) {
            this.activeBranch = branchName;
            this.path = [];
            this.sheetOpen = true;
            this.fetchLevel();
        },

        closeDrilldown() {
            this.sheetOpen = false;
        },

        /* 0 = back to the branch itself, n = back to just after the nth crumb. */
        goToDepth(depth) {
            if (depth >= this.path.length) return;
            this.path = this.path.slice(0, depth);
            this.fetchLevel();
        },

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
         * One request per level. The page's query string rides along so the date range and
         * transaction-type chips stay applied all the way down the chain.
         */
        async fetchLevel() {
            this.loading = true;
            const token = ++this.requestToken;

            try {
                const params = new URLSearchParams(window.location.search);
                params.set('branch', this.activeBranch);

                (this.levels.length ? this.levels : this.knownLevelKeys)
                    .forEach(l => params.delete(typeof l === 'string' ? l : l.key));
                this.path.forEach(c => params.set(c.key, c.value));

                const res = await fetch("{{ route('mobile.sales-report.drilldown') }}?" + params.toString());
                const data = await res.json();

                // Ignore a slower earlier request finishing after a newer one.
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
                    this.path = data.path || [];
                } else {
                    this.items = [];
                    console.error('Drilldown error:', data.message);
                }
            } catch (err) {
                if (token === this.requestToken) {
                    this.items = [];
                    console.error('Mobile drilldown error:', err);
                }
            } finally {
                if (token === this.requestToken) this.loading = false;
            }
        }
    };
}

/**
 * Capture YoY Comparison Card as PNG image and share/redirect to WhatsApp
 */
async function shareYoYCardToWhatsApp() {
    const card = document.getElementById('yoyComparisonCard');
    const shareBtn = document.getElementById('yoyShareBtn');
    const shareBtnText = document.getElementById('yoyShareBtnText');
    if (!card) return;

    if (shareBtn) shareBtn.disabled = true;
    if (shareBtnText) shareBtnText.innerHTML = '<i class="fas fa-circle-notch fa-spin text-[9px]"></i> Sharing...';

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
                        fallbackDownloadAndWhatsApp(blob, fileName, text);
                    }
                }
            } else {
                fallbackDownloadAndWhatsApp(blob, fileName, text);
            }

            if (shareBtn) shareBtn.disabled = false;
            if (shareBtnText) shareBtnText.innerText = 'Share';
        }, 'image/png');
    } catch (err) {
        console.error('Share generation error:', err);
        window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(text), '_blank');
        if (shareBtn) shareBtn.disabled = false;
        if (shareBtnText) shareBtnText.innerText = 'Share';
    }
}

function fallbackDownloadAndWhatsApp(blob, fileName, text) {
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
@endpush
@endsection
