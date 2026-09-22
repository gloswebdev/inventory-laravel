@extends('layouts.mobile')

@section('content')
<div class="space-y-3.5 pb-8" x-data="sales360Report()" x-cloak>

    {{-- SLIM HEADER --}}
    <div class="grad-indigo rounded-2xl p-3 px-4 text-white shadow-md shadow-indigo-200 relative overflow-hidden">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
        <div class="flex items-center justify-between gap-2 relative z-10">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-white/15 border border-white/25 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-circle-nodes text-sm"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-sm font-900 tracking-tight leading-none text-white truncate">360° Sales Explorer</h1>
                    <span class="text-[8px] text-indigo-100/80 font-black uppercase tracking-wider block mt-0.5 truncate">
                        Step-by-step Guided Drilldown
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <span x-show="loading" x-cloak>
                    <i class="fas fa-circle-notch fa-spin text-indigo-200 text-sm"></i>
                </span>
                <a href="{{ route('mobile.sales-report') }}" class="w-7 h-7 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 flex items-center justify-center text-xs text-white transition-all" title="Back to Sales Report">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- QUICK DATE PERIOD PRESETS BAR --}}
    <div class="bg-white/80 backdrop-blur-md rounded-2xl border border-white shadow-sm p-2.5">
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar -mx-0.5 px-0.5">
            <template x-for="preset in datePresets" :key="preset.key">
                <button type="button" @click="setDatePreset(preset.key)"
                    :class="filters.date_range === preset.key 
                        ? 'bg-indigo-600 text-white shadow-sm ring-1 ring-indigo-400/50' 
                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200/60'"
                    class="px-2.5 py-1 rounded-xl text-[10px] font-bold whitespace-nowrap transition-all flex-shrink-0"
                    x-text="preset.label"></button>
            </template>
        </div>
    </div>

    {{-- KPI SUMMARY CARDS --}}
    <div class="grid grid-cols-2 gap-2.5">
        <div class="bg-white/90 backdrop-blur-md rounded-2xl p-3 border border-emerald-100 shadow-sm">
            <div class="flex items-center justify-between mb-0.5">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Total Sales</span>
                <div class="w-5 h-5 rounded-md grad-emerald text-white flex items-center justify-center text-[9px]">
                    <i class="fas fa-indian-rupee-sign"></i>
                </div>
            </div>
            <div class="text-lg font-900 text-emerald-600 tracking-tight leading-tight" x-text="data.totals.formatted_sales"></div>
        </div>

        <div class="bg-white/90 backdrop-blur-md rounded-2xl p-3 border border-indigo-100 shadow-sm">
            <div class="flex items-center justify-between mb-0.5">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Avg Order Value</span>
                <div class="w-5 h-5 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center text-[9px]">
                    <i class="fas fa-scale-balanced"></i>
                </div>
            </div>
            <div class="text-sm font-900 text-indigo-950 truncate leading-tight" x-text="data.totals.formatted_aov"></div>
        </div>

        <div class="bg-white/90 backdrop-blur-md rounded-2xl p-3 border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-0.5">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Total Bills</span>
                <div class="w-5 h-5 rounded-md bg-violet-50 text-violet-600 flex items-center justify-center text-[9px]">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
            <div class="text-base font-900 text-slate-800 tracking-tight leading-tight" x-text="numberFmt(data.totals.total_invoices)"></div>
        </div>

        <div class="bg-white/90 backdrop-blur-md rounded-2xl p-3 border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-0.5">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider" x-text="data.totals.qty_kg_available ? 'Total Qty (KG/LTR)' : 'Total Qty'"></span>
                <div class="w-5 h-5 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center text-[9px]">
                    <i class="fas fa-boxes-stacked"></i>
                </div>
            </div>
            <div class="text-base font-900 text-slate-800 tracking-tight leading-tight"
                 x-text="data.totals.qty_kg_available ? qtyFmt(data.totals.total_qty_kg) : numberFmt(data.totals.total_qty)"></div>
        </div>
    </div>

    {{-- STEP 1: SABSE PEHLE - BRANCHES CLICKABLE MULTI-SELECT --}}
    <div class="bg-white/95 backdrop-blur-xl rounded-[1.75rem] border border-white shadow-sm p-3.5 space-y-2.5">
        <div class="flex items-center justify-between px-1">
            <div class="flex items-center gap-1.5">
                <span class="w-5 h-5 rounded-full bg-indigo-600 text-white text-[10px] font-black flex items-center justify-center shadow-sm">1</span>
                <span class="text-[10px] font-black text-slate-700 uppercase tracking-wider">Select Branches (Multi-Select)</span>
            </div>
            <button type="button" @click="toggleAllBranches()" 
                class="text-[9px] font-black px-2 py-0.5 rounded-md transition-all"
                :class="filters.branches.length === 0 ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-indigo-600 hover:bg-slate-200'">
                <span x-text="filters.branches.length === 0 ? '✓ All Branches Active' : 'Select All Branches'"></span>
            </button>
        </div>

        {{-- Branch Chips Grid --}}
        <div class="flex flex-wrap gap-1.5">
            <template x-for="b in filterOptions.branches" :key="b">
                <button type="button" @click="toggleFilter('branches', b)"
                    :class="filters.branches.includes(b) 
                        ? 'grad-indigo text-white shadow-md shadow-indigo-600/30 ring-2 ring-indigo-400 font-900 scale-[1.02]' 
                        : (filters.branches.length === 0 ? 'bg-slate-100 text-slate-700 border border-slate-200/80 hover:bg-slate-200' : 'bg-slate-50 text-slate-400 border border-slate-200/50 opacity-70')"
                    class="px-3 py-1.5 rounded-xl text-[10px] font-bold transition-all flex items-center gap-1.5 active:scale-95">
                    <span>🏢</span>
                    <span x-text="b"></span>
                    <span x-show="filters.branches.includes(b)" class="text-[9px] font-black text-indigo-200">✓</span>
                </button>
            </template>
        </div>

        <div class="text-[8px] text-slate-400 font-bold px-1" x-show="filters.branches.length > 0">
            Selected: <span class="text-indigo-600 font-black" x-text="filters.branches.join(', ')"></span>
        </div>
    </div>

    {{-- STEP 2: NICHE POOCHEGA - KAISE DEKHNA HAI? (CATEGORYWISE YA AGENTWISE) --}}
    <div class="bg-white/95 backdrop-blur-xl rounded-[1.75rem] border border-white shadow-sm p-3.5 space-y-2.5">
        <div class="flex items-center justify-between px-1">
            <div class="flex items-center gap-1.5">
                <span class="w-5 h-5 rounded-full bg-indigo-600 text-white text-[10px] font-black flex items-center justify-center shadow-sm">2</span>
                <span class="text-[10px] font-black text-slate-700 uppercase tracking-wider">Report kis roop mein dekhna hai?</span>
            </div>
            <span class="text-[9px] font-black px-2 py-0.5 rounded-full text-white transition-all shadow-sm"
                  :class="tabMeta().color"
                  x-text="tabMeta().label"></span>
        </div>

        {{-- Primary Two Big Choices: Category-wise vs Agent-wise --}}
        <div class="grid grid-cols-2 gap-2">
            {{-- Category-wise Button --}}
            <button type="button" @click="activeTab = 'category'"
                :class="activeTab === 'category' 
                    ? 'grad-amber text-white shadow-lg shadow-amber-500/25 ring-2 ring-amber-400 scale-[1.02]' 
                    : 'bg-slate-50 text-slate-700 border border-slate-200 hover:bg-slate-100'"
                class="p-3 rounded-2xl transition-all flex items-center gap-2.5 text-left active:scale-98">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-lg flex-shrink-0"
                     :class="activeTab === 'category' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-700'">
                    🧪
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-900 leading-tight truncate">Category-wise</div>
                    <div class="text-[8px] font-bold opacity-80 truncate mt-0.5">Kaunsi category biki</div>
                </div>
            </button>

            {{-- Agent-wise Button --}}
            <button type="button" @click="activeTab = 'agent'"
                :class="activeTab === 'agent' 
                    ? 'grad-violet text-white shadow-lg shadow-violet-500/25 ring-2 ring-violet-400 scale-[1.02]' 
                    : 'bg-slate-50 text-slate-700 border border-slate-200 hover:bg-slate-100'"
                class="p-3 rounded-2xl transition-all flex items-center gap-2.5 text-left active:scale-98">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-lg flex-shrink-0"
                     :class="activeTab === 'agent' ? 'bg-white/20 text-white' : 'bg-violet-100 text-violet-700'">
                    👤
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-900 leading-tight truncate">Agent-wise</div>
                    <div class="text-[8px] font-bold opacity-80 truncate mt-0.5">Kin agents ne becha</div>
                </div>
            </button>
        </div>

        {{-- Secondary Quick Options: Product-wise / Party-wise --}}
        <div class="flex items-center gap-1.5 pt-1">
            <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider flex-shrink-0">Other views:</span>
            <button type="button" @click="activeTab = 'product'"
                :class="activeTab === 'product' ? 'grad-cyan text-white shadow-sm ring-1 ring-cyan-300 font-900' : 'bg-slate-100 text-slate-600 border border-slate-200'"
                class="px-2.5 py-1 rounded-xl text-[9px] font-bold transition-all flex items-center gap-1">
                <span>📦 Product-wise</span>
            </button>
            <button type="button" @click="activeTab = 'party'"
                :class="activeTab === 'party' ? 'grad-slate text-white shadow-sm ring-1 ring-slate-400 font-900' : 'bg-slate-100 text-slate-600 border border-slate-200'"
                class="px-2.5 py-1 rounded-xl text-[9px] font-bold transition-all flex items-center gap-1">
                <span>🏪 Party-wise</span>
            </button>
            <button type="button" @click="activeTab = 'branch'"
                :class="activeTab === 'branch' ? 'grad-indigo text-white shadow-sm ring-1 ring-indigo-300 font-900' : 'bg-slate-100 text-slate-600 border border-slate-200'"
                class="px-2.5 py-1 rounded-xl text-[9px] font-bold transition-all flex items-center gap-1">
                <span>🏢 Branch Summary</span>
            </button>
        </div>
    </div>

    {{-- BREADCRUMB TRAIL (CURRENT DRILLDOWN PATH) --}}
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white rounded-2xl p-2.5 px-3.5 shadow-md flex items-center justify-between gap-2 overflow-x-auto no-scrollbar">
        <div class="flex items-center gap-1.5 min-w-0 flex-shrink-0">
            <span class="text-[8px] font-black text-indigo-300 uppercase tracking-wider flex items-center gap-1">
                <i class="fas fa-compass text-indigo-400"></i> Path:
            </span>

            {{-- Branch scope pill --}}
            <span class="bg-white/10 px-2 py-0.5 rounded-lg text-[9px] font-bold text-indigo-200 flex items-center gap-1">
                <span>🏢</span>
                <span x-text="filters.branches.length === 0 ? 'All Branches' : (filters.branches.length === 1 ? filters.branches[0] : filters.branches.length + ' Branches')"></span>
            </span>

            {{-- Active Filter Breadcrumb Chips --}}
            <template x-for="chip in activeNonBranchChips()" :key="chip.dim + ':' + chip.value">
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <i class="fas fa-chevron-right text-[8px] text-indigo-400/60"></i>
                    <span :class="chip.color" class="pl-2 pr-1 py-0.5 rounded-lg text-[9px] font-bold flex items-center gap-1 text-white shadow-sm">
                        <span x-text="chip.icon"></span>
                        <span class="max-w-[90px] truncate" x-text="chip.value"></span>
                        <button type="button" @click="removeChip(chip)" class="opacity-80 hover:opacity-100 text-xs ml-0.5" title="Remove step">&times;</button>
                    </span>
                </div>
            </template>
        </div>

        <button type="button" @click="resetDrilldown()" x-show="hasActiveNonBranchFilters()" x-cloak
            class="grad-rose text-white px-2 py-1 rounded-lg text-[8px] font-bold flex-shrink-0 shadow-sm">
            <i class="fas fa-rotate-left mr-0.5"></i> Reset
        </button>
    </div>

    {{-- RESULTS SECTION: CARDS & DRILLDOWN TRIGGER --}}
    <div class="space-y-3">
        
        {{-- Section Heading --}}
        <div class="flex items-center justify-between px-1">
            <div class="flex items-center gap-2">
                <span class="text-xl" x-text="tabMeta().icon"></span>
                <div>
                    <h2 class="text-xs font-900 text-slate-800 tracking-tight" x-text="tabMeta().label + ' Breakdown'"></h2>
                    <span class="text-[9px] text-indigo-600 font-bold block" x-show="currentRows().length">
                        👉 Kisi bhi card par click karein aage drilldown karne ke liye
                    </span>
                </div>
            </div>
            <span class="text-[10px] font-black text-slate-500" x-show="currentRows().length">
                <span x-text="currentRows().length"></span> Items
            </span>
        </div>

        {{-- Unavailable State --}}
        <template x-if="!currentBreakdown().available">
            <div class="bg-white/70 backdrop-blur-sm p-8 rounded-[1.75rem] text-center border-dashed border-2 border-slate-200">
                <i class="fas fa-circle-info text-slate-300 text-2xl mb-2"></i>
                <div class="text-[11px] font-bold text-slate-400">This breakdown isn't available on the current data source.</div>
            </div>
        </template>

        {{-- Empty Results State --}}
        <template x-if="currentBreakdown().available && currentRows().length === 0">
            <div class="bg-white/70 backdrop-blur-sm p-8 rounded-[1.75rem] text-center border-dashed border-2 border-slate-200">
                <i class="fas fa-filter-circle-xmark text-slate-300 text-2xl mb-2"></i>
                <div class="text-[12px] font-900 text-slate-700 mb-1">No sales match this combination</div>
                <div class="text-[10px] text-slate-400 mb-3">Try changing branches or resetting drilldown filters.</div>
                <button type="button" @click="resetDrilldown()" class="text-[11px] font-bold text-rose-600 bg-rose-50 border border-rose-200 px-4 py-2 rounded-xl">Reset Filters</button>
            </div>
        </template>

        {{-- Data Cards List --}}
        <template x-if="currentBreakdown().available && currentRows().length > 0">
            <div class="space-y-2.5">
                {{-- Segmented Composition Bar --}}
                <div class="flex h-2.5 rounded-full overflow-hidden shadow-inner bg-slate-200/70">
                    <template x-for="(row, idx) in currentRows()" :key="'seg-' + row.label">
                        <div :class="tabMeta().color" :style="'width:' + Math.max(row.share_percent, 1) + '%; opacity:' + (1 - idx * 0.07)"
                             :title="row.label + ': ' + row.share_percent + '%'"></div>
                    </template>
                </div>

                {{-- Interactive Cards --}}
                <div class="space-y-2.5">
                    <template x-for="row in currentRows()" :key="row.label">
                        <div @click="openDrillSheet(tabMeta().filterKey, row)"
                            class="bg-white/95 backdrop-blur-xl rounded-[1.5rem] p-3.5 border shadow-sm hover:shadow-md transition-all cursor-pointer active:scale-98 group"
                            :class="filters[tabMeta().filterKey].includes(row.label) ? tabMeta().ring + ' border-2 bg-indigo-50/20' : 'border-slate-100'">
                            
                            {{-- Top Header Row --}}
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-6 h-6 rounded-lg flex items-center justify-center text-[10px] font-black text-white flex-shrink-0 shadow-sm"
                                        :class="row.rank === 1 ? 'bg-amber-500' : (row.rank === 2 ? 'bg-slate-400' : (row.rank === 3 ? 'bg-amber-700' : 'bg-slate-300'))"
                                        x-text="row.rank"></span>
                                    <div class="text-xs font-900 text-slate-800 truncate group-hover:text-indigo-600 transition-colors" x-text="row.label"></div>
                                </div>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <div class="text-xs font-900 text-emerald-600 font-mono" x-text="row.formatted_sales"></div>
                                    <span class="text-[8px] font-black text-indigo-600 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md flex items-center gap-0.5 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                                        Drill <i class="fas fa-chevron-right text-[6px]"></i>
                                    </span>
                                </div>
                            </div>

                            {{-- Share Progress Bar --}}
                            <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden mb-2">
                                <div class="h-1.5 rounded-full transition-all duration-500" :class="tabMeta().color"
                                     :style="'width: ' + Math.min(100, Math.max(2, row.share_percent)) + '%'"></div>
                            </div>

                            {{-- Metrics Grid --}}
                            <div class="grid grid-cols-3 gap-2 bg-slate-50/90 rounded-xl p-2 text-center border border-slate-100">
                                <div>
                                    <div class="text-[10px] font-900 text-slate-700" x-text="row.share_percent + '%'"></div>
                                    <div class="text-[7px] text-slate-400 font-bold uppercase">Share</div>
                                </div>
                                <div>
                                    <div class="text-[10px] font-900 text-slate-700"
                                         x-text="data.totals.qty_kg_available ? qtyFmt(row.total_qty_kg) : numberFmt(row.total_qty)"></div>
                                    <div class="text-[7px] text-slate-400 font-bold uppercase" x-text="data.totals.qty_kg_available ? 'Kg/Ltr' : 'Qty'"></div>
                                </div>
                                <div>
                                    <div class="text-[10px] font-900 text-slate-700" x-text="numberFmt(row.total_invoices)"></div>
                                    <div class="text-[7px] text-slate-400 font-bold uppercase">Bills</div>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Others Bucket --}}
                    <template x-if="currentOthers()">
                        <div class="bg-slate-50/90 rounded-[1.5rem] p-3.5 border border-slate-200 border-dashed">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <div class="text-[11px] font-900 text-slate-500">
                                    Others <span class="font-normal" x-text="'(' + currentOthers().count + ' more)'"></span>
                                </div>
                                <div class="text-xs font-900 text-slate-500 font-mono" x-text="currentOthers().formatted_sales"></div>
                            </div>
                            <div class="text-[9px] text-slate-400 font-bold"
                                 x-text="currentOthers().share_percent + '% share · ' + numberFmt(currentOthers().total_invoices) + ' bills' + (data.totals.qty_kg_available ? ' · ' + qtyFmt(currentOthers().total_qty_kg) + ' kg/ltr' : '')"></div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- GUIDED DRILL-DOWN ACTION SHEET (THE CORE POPUP) --}}
    <div x-show="drillSheetOpen" x-cloak class="fixed inset-0 z-[110] flex items-end justify-center"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="drillSheetOpen = false"></div>
        <div class="relative w-full max-w-lg bg-white rounded-t-[2.5rem] p-5 pb-8 shadow-2xl flex flex-col z-10 border-t border-slate-100 max-h-[85vh] overflow-y-auto"
             x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
            
            <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-3 flex-shrink-0 cursor-pointer" @click="drillSheetOpen = false"></div>
            
            {{-- Selected Item Banner --}}
            <template x-if="selectedDrill">
                <div class="space-y-3.5">
                    <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-3.5 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 text-[9px] font-black text-slate-400 uppercase tracking-wider mb-0.5">
                                <span x-text="dimMeta[selectedDrill.tabKey]?.icon"></span>
                                <span x-text="dimMeta[selectedDrill.tabKey]?.label"></span> Selected
                            </div>
                            <div class="text-sm font-900 text-slate-800 truncate" x-text="selectedDrill.label"></div>
                            <div class="text-[10px] text-slate-500 font-bold mt-0.5" x-text="selectedDrill.bills + ' bills · ' + selectedDrill.qty"></div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-xs font-900 text-emerald-600 font-mono" x-text="selectedDrill.formatted_sales"></div>
                            <div class="text-[9px] font-black text-slate-400" x-text="selectedDrill.share_percent + '% share'"></div>
                        </div>
                    </div>

                    <div>
                        <div class="text-[12px] font-900 text-slate-800 mb-0.5">🔍 Agla kya dekhna chahte hain?</div>
                        <div class="text-[9px] text-slate-400 font-bold">
                            <span class="text-indigo-600 font-black" x-text="selectedDrill.label"></span> ko aage kis roop mein analyze karein:
                        </div>
                    </div>

                    {{-- Next Dimension Options --}}
                    <div class="space-y-2">
                        <template x-for="nextDim in getAvailableNextDims()" :key="nextDim.key">
                            <button type="button" @click="executeDrill(nextDim.key)"
                                class="w-full text-left p-3 rounded-2xl border border-slate-200 hover:border-indigo-400 bg-white hover:bg-indigo-50/40 transition-all flex items-center justify-between group active:scale-[0.99] shadow-sm">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg flex-shrink-0 text-white shadow-sm"
                                         :class="nextDim.color">
                                        <span x-text="nextDim.icon"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-900 text-slate-800 group-hover:text-indigo-900" x-text="nextDim.label"></div>
                                        <div class="text-[9px] text-slate-500 font-medium truncate" x-text="getNextDimDescription(nextDim.key, selectedDrill.label)"></div>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right text-slate-300 group-hover:text-indigo-600 group-hover:translate-x-1 transition-all text-xs"></i>
                            </button>
                        </template>
                    </div>

                    {{-- Bottom Action Buttons --}}
                    <div class="pt-2 border-t border-slate-100 flex items-center gap-2">
                        <button type="button" @click="toggleOnlyFilter()"
                            class="flex-1 py-2.5 rounded-xl text-[10px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition-all text-center">
                            Filter this &amp; stay here
                        </button>
                        <button type="button" @click="drillSheetOpen = false"
                            class="py-2.5 px-4 rounded-xl text-[10px] font-bold text-slate-400 hover:text-slate-600 transition-all">
                            Cancel
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function sales360Report() {
    return {
        loading: false,
        requestToken: 0,
        fetchTimer: null,
        activeTab: 'category', // Default to Category-wise as requested!

        // Guided Drilldown Sheet State
        drillSheetOpen: false,
        selectedDrill: null,

        datePresets: [
            { key: 'this_fy',    label: '🟢 This FY' },
            { key: 'prev_fy',    label: '📅 Last FY' },
            { key: 'this_month', label: '📆 This Month' },
            { key: 'last_month', label: '⏮️ Last Month' },
            { key: 'today',      label: '⭐ Today' },
            { key: 'all_time',   label: '⚡ All Time' },
        ],

        tabs: [
            { key: 'category', label: 'Category-wise', shortLabel: 'Category', icon: '🧪', color: 'grad-amber' },
            { key: 'agent',    label: 'Agent-wise',    shortLabel: 'Agent',    icon: '👤', color: 'grad-violet' },
            { key: 'product',  label: 'Product-wise',  shortLabel: 'Product',  icon: '📦', color: 'grad-cyan' },
            { key: 'party',    label: 'Party-wise',    shortLabel: 'Party',    icon: '🏪', color: 'grad-slate' },
            { key: 'branch',   label: 'Branch-wise',   shortLabel: 'Branch',   icon: '🏢', color: 'grad-indigo' },
        ],

        dimMeta: {
            branch:   { filterKey: 'branches',   label: 'Branch-wise',   icon: '🏢', color: 'grad-indigo', ring: 'ring-2 ring-indigo-400' },
            category: { filterKey: 'categories', label: 'Category-wise', icon: '🧪', color: 'grad-amber',  ring: 'ring-2 ring-amber-400' },
            agent:    { filterKey: 'agents',     label: 'Agent-wise',    icon: '👤', color: 'grad-violet', ring: 'ring-2 ring-violet-400' },
            product:  { filterKey: 'products',   label: 'Product-wise',  icon: '📦', color: 'grad-cyan',   ring: 'ring-2 ring-cyan-400' },
            party:    { filterKey: 'parties',    label: 'Party-wise',    icon: '🏪', color: 'grad-slate',  ring: 'ring-2 ring-slate-400' },
        },

        filters: {
            date_range: @json($filters_echo['date_range'] ?? 'this_fy'),
            from_date: @json($filters_echo['from_date'] ?? null),
            to_date: @json($filters_echo['to_date'] ?? null),
            txn_types: @json($filters_echo['txn_types'] ?? $default_txn_types ?? []),
            branches: @json($filters_echo['branches'] ?? []),
            categories: @json($filters_echo['categories'] ?? []),
            agents: @json($filters_echo['agents'] ?? []),
            products: @json($filters_echo['products'] ?? []),
            parties: @json($filters_echo['parties'] ?? []),
        },

        data: @json([
            'totals' => $totals,
            'breakdowns' => $breakdowns,
        ]),

        filterOptions: @json($filter_options),
        txnTypeOptions: @json($txn_type_options),

        numberFmt(n) {
            return new Intl.NumberFormat('en-IN').format(Math.round(n || 0));
        },
        qtyFmt(n) {
            return new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0);
        },

        tabMeta() {
            return this.dimMeta[this.activeTab] || this.dimMeta['category'];
        },

        currentBreakdown() {
            return this.data.breakdowns[this.activeTab] || { available: false, rows: [], others: null };
        },
        currentRows() {
            return this.currentBreakdown().rows || [];
        },
        currentOthers() {
            return this.currentBreakdown().others || null;
        },

        // Branch multi-select helper: Toggle all branches
        toggleAllBranches() {
            this.filters.branches = [];
            this.scheduleFetch();
        },

        // Guided Drilldown Methods
        openDrillSheet(filterKey, row) {
            this.selectedDrill = {
                filterKey: filterKey,
                tabKey: this.activeTab,
                label: row.label,
                formatted_sales: row.formatted_sales,
                share_percent: row.share_percent,
                total_qty: row.total_qty,
                total_qty_kg: row.total_qty_kg,
                qty: this.data.totals.qty_kg_available ? this.qtyFmt(row.total_qty_kg) + ' kg/ltr' : this.numberFmt(row.total_qty) + ' units',
                bills: this.numberFmt(row.total_invoices),
            };
            this.drillSheetOpen = true;
        },

        getAvailableNextDims() {
            if (!this.selectedDrill) return [];
            const currentTab = this.selectedDrill.tabKey;
            
            // Priority order of suggestions based on current tab
            let order = [];
            if (currentTab === 'category') {
                order = ['agent', 'party', 'product'];
            } else if (currentTab === 'agent') {
                order = ['category', 'product', 'party'];
            } else if (currentTab === 'product') {
                order = ['party', 'agent', 'category'];
            } else if (currentTab === 'party') {
                order = ['product', 'agent', 'category'];
            } else if (currentTab === 'branch') {
                order = ['category', 'agent', 'product', 'party'];
            }

            return order
                .map(k => this.tabs.find(t => t.key === k))
                .filter(t => {
                    if (!t) return false;
                    const fKey = this.dimMeta[t.key]?.filterKey;
                    // Don't show if already filtered by this dimension
                    return !this.filters[fKey] || this.filters[fKey].length === 0;
                });
        },

        getNextDimDescription(nextKey, currentLabel) {
            const currentTab = this.selectedDrill ? this.selectedDrill.tabKey : '';
            if (currentTab === 'category') {
                if (nextKey === 'agent') return `${currentLabel} ko kin agents ne becha?`;
                if (nextKey === 'party') return `${currentLabel} kin parties/dealers ne khareeda?`;
                if (nextKey === 'product') return `${currentLabel} ke kaunse products bike?`;
            } else if (currentTab === 'agent') {
                if (nextKey === 'category') return `${currentLabel} ne kaunsi categories bechi?`;
                if (nextKey === 'product') return `${currentLabel} ne kaunse products beche?`;
                if (nextKey === 'party') return `${currentLabel} ne kin parties ko becha?`;
            } else if (currentTab === 'product') {
                if (nextKey === 'party') return `${currentLabel} kin parties ne khareeda?`;
                if (nextKey === 'agent') return `${currentLabel} kis agent ne becha?`;
                if (nextKey === 'category') return `Category breakdown for ${currentLabel}`;
            } else if (currentTab === 'party') {
                if (nextKey === 'product') return `Is party ne kaunse products khareede?`;
                if (nextKey === 'agent') return `Is party ko kis agent ne becha?`;
                if (nextKey === 'category') return `Is party ne kaunsi categories li?`;
            } else if (currentTab === 'branch') {
                if (nextKey === 'category') return `${currentLabel} mein kaunsi categories biki?`;
                if (nextKey === 'agent') return `${currentLabel} mein kin agents ne becha?`;
                if (nextKey === 'product') return `${currentLabel} mein kaunse products bike?`;
                if (nextKey === 'party') return `${currentLabel} mein kin parties ne khareeda?`;
            }
            return `Explore ${nextKey}-wise details`;
        },

        executeDrill(targetDimKey) {
            if (!this.selectedDrill) return;
            const filterKey = this.selectedDrill.filterKey;
            const label = this.selectedDrill.label;

            // Add selected item to filter
            if (!this.filters[filterKey].includes(label)) {
                this.filters[filterKey].push(label);
            }

            // Switch to requested next dimension
            this.activeTab = targetDimKey;
            this.drillSheetOpen = false;
            this.scheduleFetch();
        },

        toggleOnlyFilter() {
            if (!this.selectedDrill) return;
            this.toggleFilter(this.selectedDrill.filterKey, this.selectedDrill.label);
            this.drillSheetOpen = false;
        },

        toggleFilter(dim, value) {
            const arr = this.filters[dim];
            const idx = arr.indexOf(value);
            if (idx === -1) arr.push(value); else arr.splice(idx, 1);
            this.scheduleFetch();
        },
        setDatePreset(key) {
            this.filters.date_range = key;
            this.scheduleFetch();
        },
        resetDrilldown() {
            this.filters.categories = [];
            this.filters.agents = [];
            this.filters.products = [];
            this.filters.parties = [];
            this.activeTab = 'category';
            this.scheduleFetch();
        },
        activeNonBranchChips() {
            const chips = [];
            const push = (dim, icon, color) => this.filters[dim].forEach(v => chips.push({ dim, value: v, icon, color }));
            push('categories', '🧪', 'grad-amber');
            push('agents', '👤', 'grad-violet');
            push('products', '📦', 'grad-cyan');
            push('parties', '🏪', 'grad-slate');
            return chips;
        },
        hasActiveNonBranchFilters() {
            return this.activeNonBranchChips().length > 0;
        },
        removeChip(chip) {
            this.toggleFilter(chip.dim, chip.value);
        },

        scheduleFetch() {
            clearTimeout(this.fetchTimer);
            this.fetchTimer = setTimeout(() => this.fetchData(), 300);
        },
        fetchData() {
            this.loading = true;
            const token = ++this.requestToken;
            const params = new URLSearchParams();

            if (this.filters.date_range) params.append('date_range', this.filters.date_range);
            if (this.filters.date_range === 'custom') {
                if (this.filters.from_date) params.append('from_date', this.filters.from_date);
                if (this.filters.to_date) params.append('to_date', this.filters.to_date);
            }
            this.filters.txn_types.forEach(t => params.append('txn_types[]', t));
            this.filters.branches.forEach(v => params.append('branches[]', v));
            this.filters.categories.forEach(v => params.append('categories[]', v));
            this.filters.agents.forEach(v => params.append('agents[]', v));
            this.filters.products.forEach(v => params.append('products[]', v));
            this.filters.parties.forEach(v => params.append('parties[]', v));

            fetch(`{{ route('mobile.sales-360.data') }}?${params.toString()}`)
                .then(r => r.json())
                .then(json => {
                    if (token !== this.requestToken) return;
                    this.data = json;
                })
                .finally(() => {
                    if (token === this.requestToken) this.loading = false;
                });
        },
    };
}
</script>
@endpush
