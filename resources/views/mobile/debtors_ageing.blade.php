@extends('layouts.mobile')

@section('content')
<div class="space-y-4 pb-12" x-data="mobileDebtorsAgeing(@js($rows), @js($totals))">

    {{-- Top Header Banner --}}
    <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 rounded-[2rem] p-5 text-white shadow-xl relative overflow-hidden border border-indigo-900/40">
        <div class="absolute -right-8 -top-8 w-36 h-36 bg-indigo-500/15 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute -left-6 bottom-0 w-32 h-32 bg-rose-500/15 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10">
            <div class="flex items-center justify-between mb-3">
                <a href="{{ route('mobile.dashboard') }}"
                   class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 flex items-center justify-center text-white text-xs active:scale-95 transition">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ request()->fullUrlWithQuery(['refresh' => 1]) }}"
                       class="px-2.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-white text-[10px] font-bold flex items-center gap-1.5 active:scale-95 transition">
                        <i class="fas fa-rotate text-indigo-300"></i>
                        <span>Refresh</span>
                    </a>
                    <button type="button" @click="showFilter = !showFilter"
                            class="px-3 py-1.5 rounded-xl bg-indigo-600 text-white text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5 shadow-md active:scale-95 transition">
                        <i class="fas fa-filter text-[9px]"></i>
                        <span x-text="showFilter ? 'Hide Filter' : 'Filter'"></span>
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-gradient-to-br from-indigo-500 to-rose-600 rounded-2xl flex items-center justify-center text-white shadow-md border border-white/20 shrink-0">
                    <i class="fas fa-calendar-days text-lg"></i>
                </div>
                <div>
                    <h1 class="text-lg font-900 tracking-tight leading-tight">Debtors Ageing</h1>
                    <p class="text-indigo-200/80 text-[9px] font-bold uppercase tracking-widest mt-0.5">
                        Accounts Receivable Analysis
                    </p>
                </div>
            </div>
        </div>
    </div>

    @if(isset($error) && $error)
    <div class="bg-rose-50 border border-rose-200 p-3.5 rounded-2xl text-rose-800 text-xs shadow-xs flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <i class="fas fa-triangle-exclamation text-rose-500"></i>
            <span>{{ $error }}</span>
        </div>
        <a href="{{ request()->fullUrlWithQuery(['refresh' => 1]) }}" class="text-[10px] font-black uppercase tracking-wider text-rose-700 underline">Retry</a>
    </div>
    @endif

    {{-- Filter Drawer / Accordion --}}
    <div x-show="showFilter" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="bg-white/90 backdrop-blur-xl rounded-[1.5rem] border border-white shadow-md p-4 space-y-3">
        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5 border-b border-slate-100 pb-2">
            <i class="fas fa-sliders text-indigo-500"></i> Report Filter Parameters
        </div>

        <form method="GET" action="{{ route('mobile.debtors-ageing') }}" class="space-y-3" id="mobileFilterForm">
            {{-- Branch --}}
            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 ml-1">Branch</label>
                <div class="relative">
                    <select name="branch" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none appearance-none">
                        @if(Auth::user()->role === 'admin' || count(Auth::user()->getPermittedBranchCodes()) > 1)
                            <option value="ALL" {{ $selectedBranch === 'ALL' ? 'selected' : '' }}>— ALL Branches —</option>
                        @endif
                        @foreach($branchOptions as $b)
                            <option value="{{ $b->code }}" {{ (string)$selectedBranch === (string)$b->code ? 'selected' : '' }}>
                                {{ $b->name }} (Code: {{ $b->code }})
                            </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            {{-- Party Code / Account --}}
            <div>
                <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 ml-1">Party (Account)</label>
                <div class="relative">
                    <select name="act_code" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none appearance-none">
                        <option value="all" {{ $selectedActCode === 'all' ? 'selected' : '' }}>— ALL Parties —</option>
                        @foreach($partyOptions as $party)
                            <option value="{{ $party['code'] }}" {{ $selectedActCode === $party['code'] ? 'selected' : '' }}>
                                {{ $party['name'] }} (#{{ $party['code'] }})
                            </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            {{-- Dates --}}
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 ml-1">From Date</label>
                    <input type="date" name="from_date" value="{{ $fromDate }}"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-2.5 text-xs font-bold text-slate-700 outline-none">
                </div>
                <div>
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 ml-1">To Date</label>
                    <input type="date" name="to_date" value="{{ $toDate }}"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-2.5 text-xs font-bold text-slate-700 outline-none">
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('mobile.debtors-ageing') }}" class="text-[10px] font-bold text-slate-400 hover:text-slate-600">Reset</a>
                <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-black uppercase tracking-wider shadow-md active:scale-95 transition">
                    Apply Filter
                </button>
            </div>
        </form>
    </div>

    {{-- Quick KPI Metrics Bar --}}
    <div class="grid grid-cols-2 gap-2.5">
        <div class="bg-white/80 backdrop-blur-md rounded-2xl p-3 border border-white shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">Total Outstanding</span>
                <span class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-[10px]">
                    <i class="fas fa-coins"></i>
                </span>
            </div>
            <div class="text-base font-black text-slate-900 tracking-tight mt-1" x-text="formatCurrency(computedTotals.closing)">
                ₹{{ number_format($totals['closing'], 2) }}
            </div>
            <div class="text-[9px] font-bold text-slate-400 mt-0.5">
                <span x-text="computedRows.length"></span> Active Parties
            </div>
        </div>

        <div class="bg-rose-50/80 backdrop-blur-md rounded-2xl p-3 border border-rose-100 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[9px] font-black uppercase tracking-wider text-rose-700">Over 90 Days</span>
                <span class="w-6 h-6 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center text-[10px] animate-pulse">
                    <i class="fas fa-fire"></i>
                </span>
            </div>
            <div class="text-base font-black text-rose-700 tracking-tight mt-1" x-text="formatCurrency(computedTotals.bucket_over_90)">
                ₹{{ number_format($totals['bucket_over_90'], 2) }}
            </div>
            <div class="text-[9px] font-bold text-rose-600 mt-0.5">
                <span x-text="computedTotals.overdue_count"></span> High-Risk Parties
            </div>
        </div>
    </div>

    {{-- Search and Risk Filter Controls --}}
    <div class="space-y-2">
        {{-- Search Input --}}
        <div class="relative">
            <input type="text" x-model="search"
                   placeholder="Search debtor name, code, branch..."
                   class="w-full bg-white border border-slate-200 rounded-2xl py-2.5 pl-9 pr-8 text-xs font-bold text-slate-800 placeholder-slate-400 shadow-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-slate-400">
                <i class="fas fa-search text-xs"></i>
            </div>
            <button type="button" x-show="search" @click="search = ''" class="absolute inset-y-0 right-3 flex items-center text-slate-400">
                <i class="fas fa-times-circle text-xs"></i>
            </button>
        </div>

        {{-- Segment Filter Chips --}}
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
            <button type="button" @click="riskFilter = 'all'"
                    :class="riskFilter === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200'"
                    class="px-3 py-1.5 rounded-xl text-[11px] font-black whitespace-nowrap transition active:scale-95 flex items-center gap-1.5">
                <span>All</span>
                <span class="text-[9px] px-1.5 py-0.2 rounded-full" :class="riskFilter === 'all' ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-600'" x-text="rawRows.length"></span>
            </button>
            <button type="button" @click="riskFilter = 'outstanding'"
                    :class="riskFilter === 'outstanding' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200'"
                    class="px-3 py-1.5 rounded-xl text-[11px] font-black whitespace-nowrap transition active:scale-95">
                <span>Balance > 0</span>
            </button>
            <button type="button" @click="riskFilter = 'over90'"
                    :class="riskFilter === 'over90' ? 'bg-rose-600 text-white shadow-xs' : 'bg-white text-rose-600 border border-rose-200'"
                    class="px-3 py-1.5 rounded-xl text-[11px] font-black whitespace-nowrap transition active:scale-95 flex items-center gap-1">
                <i class="fas fa-fire text-[9px]"></i>
                <span>Over 90d (<span x-text="totals.overdue_count"></span>)</span>
            </button>
        </div>
    </div>

    {{-- Debtors Cards List --}}
    <div class="space-y-3">
        <template x-for="(row, idx) in paginatedRows" :key="idx">
            <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm hover:shadow-md transition relative overflow-hidden"
                 x-data="{ expanded: false }">

                {{-- Status Tag Bar --}}
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[9px] font-black tracking-wide"
                          :class="getBranchBadgeClass(row.Branch_Code)">
                        <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                        <span x-text="row.Branch_Name"></span>
                    </span>

                    <div class="flex items-center gap-1.5">
                        <span class="text-[9px] font-mono text-slate-400 font-bold">
                            #<span x-text="row.ACT_CODE"></span>
                        </span>
                        <span x-show="row['Over90Days'] > 0"
                              class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider bg-rose-100 text-rose-700 flex items-center gap-1">
                            <span class="w-1 h-1 rounded-full bg-rose-500 animate-ping"></span>
                            90d+
                        </span>
                    </div>
                </div>

                {{-- Party Name & Outstanding --}}
                <div class="flex items-start justify-between gap-3 mb-2.5">
                    <div class="flex-grow">
                        <h3 class="text-xs font-900 text-slate-800 leading-snug line-clamp-2" x-text="row.act_name"></h3>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-sm font-black text-indigo-950 tracking-tight" x-text="formatCurrency(row.ClosingBalance)"></div>
                        <div class="text-[8px] font-black uppercase tracking-wider text-slate-400 mt-0.5">Outstanding</div>
                    </div>
                </div>

                {{-- Ageing Multi-color Visual Distribution Bar --}}
                <div class="w-full h-1.5 bg-slate-100 rounded-full flex overflow-hidden mb-3">
                    <div class="bg-emerald-500 h-full transition-all" :style="'width: ' + getPercent(row['0-30Days'], row.ClosingBalance) + '%'"></div>
                    <div class="bg-amber-400 h-full transition-all" :style="'width: ' + getPercent(row['31-60Days'], row.ClosingBalance) + '%'"></div>
                    <div class="bg-orange-500 h-full transition-all" :style="'width: ' + getPercent(row['61-90Days'], row.ClosingBalance) + '%'"></div>
                    <div class="bg-rose-500 h-full transition-all" :style="'width: ' + getPercent(row['Over90Days'], row.ClosingBalance) + '%'"></div>
                </div>

                {{-- Ageing Buckets Mini Grid --}}
                <div class="grid grid-cols-4 gap-1.5 text-center bg-slate-50/80 p-2 rounded-xl border border-slate-100">
                    <div>
                        <div class="text-[7.5px] font-black uppercase tracking-widest text-emerald-600">0-30d</div>
                        <div class="text-[10px] font-black text-slate-700 mt-0.5 truncate" x-text="row['0-30Days'] > 0 ? formatShort(row['0-30Days']) : '—'"></div>
                    </div>
                    <div>
                        <div class="text-[7.5px] font-black uppercase tracking-widest text-amber-600">31-60d</div>
                        <div class="text-[10px] font-black text-slate-700 mt-0.5 truncate" x-text="row['31-60Days'] > 0 ? formatShort(row['31-60Days']) : '—'"></div>
                    </div>
                    <div>
                        <div class="text-[7.5px] font-black uppercase tracking-widest text-orange-600">61-90d</div>
                        <div class="text-[10px] font-black text-slate-700 mt-0.5 truncate" x-text="row['61-90Days'] > 0 ? formatShort(row['61-90Days']) : '—'"></div>
                    </div>
                    <div>
                        <div class="text-[7.5px] font-black uppercase tracking-widest text-rose-600">90d+</div>
                        <div class="text-[10px] font-black mt-0.5 truncate"
                             :class="row['Over90Days'] > 0 ? 'text-rose-600 font-black' : 'text-slate-400'"
                             x-text="row['Over90Days'] > 0 ? formatShort(row['Over90Days']) : '—'"></div>
                    </div>
                </div>

                {{-- Expandable Ledger Details --}}
                <div x-show="expanded" x-cloak class="mt-3 pt-3 border-t border-slate-100 space-y-2 text-xs">
                    <div class="grid grid-cols-2 gap-2 text-[10px]">
                        <div class="bg-white p-2 rounded-lg border border-slate-100">
                            <span class="text-slate-400 font-bold uppercase tracking-wider block text-[8px]">Opening Balance</span>
                            <span class="font-black text-slate-700 mt-0.5 block" x-text="formatCurrency(row.OpeningBalance)"></span>
                        </div>
                        <div class="bg-white p-2 rounded-lg border border-slate-100">
                            <span class="text-slate-400 font-bold uppercase tracking-wider block text-[8px]">Credit Limit</span>
                            <span class="font-black text-slate-700 mt-0.5 block" x-text="row.Credit_Limit > 0 ? formatCurrency(row.Credit_Limit) : 'No Limit'"></span>
                        </div>
                        <div class="bg-white p-2 rounded-lg border border-slate-100">
                            <span class="text-slate-400 font-bold uppercase tracking-wider block text-[8px]">Total Debit</span>
                            <span class="font-black text-slate-700 mt-0.5 block" x-text="formatCurrency(row.TotalDebit)"></span>
                        </div>
                        <div class="bg-white p-2 rounded-lg border border-slate-100">
                            <span class="text-slate-400 font-bold uppercase tracking-wider block text-[8px]">Total Credit</span>
                            <span class="font-black text-slate-700 mt-0.5 block" x-text="formatCurrency(row.TotalCredit)"></span>
                        </div>
                    </div>
                </div>

                {{-- Toggle Expand Button --}}
                <button type="button" @click="expanded = !expanded"
                        class="w-full text-center text-[9px] font-black uppercase tracking-widest text-indigo-500 hover:text-indigo-700 pt-2 transition flex items-center justify-center gap-1">
                    <span x-text="expanded ? 'Less Details' : 'More Details'"></span>
                    <i class="fas fa-chevron-down text-[8px] transition-transform" :class="expanded ? 'rotate-180' : ''"></i>
                </button>
            </div>
        </template>

        {{-- Empty State --}}
        <div x-show="computedRows.length === 0" class="bg-white/60 p-8 rounded-2xl text-center border-dashed border-2 border-slate-200">
            <div class="w-10 h-10 rounded-full bg-slate-100 mx-auto flex items-center justify-center text-slate-400 mb-2">
                <i class="fas fa-inbox"></i>
            </div>
            <p class="text-xs font-bold text-slate-700">No debtors match your filter</p>
            <p class="text-[10px] text-slate-400 mt-0.5">Try clearing the search query or changing branch filter.</p>
        </div>

        {{-- Load More Button --}}
        <div x-show="perPage < computedRows.length" class="pt-2 text-center">
            <button type="button" @click="perPage += 50"
                    class="w-full py-3 bg-white hover:bg-slate-50 border border-slate-200 rounded-2xl text-xs font-black uppercase tracking-wider text-slate-700 shadow-sm active:scale-95 transition">
                Load More (+50) · Showing <span x-text="paginatedRows.length"></span> of <span x-text="computedRows.length"></span>
            </button>
        </div>
    </div>
</div>

{{-- Mobile Debtors Controller --}}
<script>
function mobileDebtorsAgeing(rawRows, initialTotals) {
    return {
        rawRows: rawRows || [],
        totals: initialTotals || {},
        showFilter: false,
        search: '',
        riskFilter: 'all',
        perPage: 40,

        get computedRows() {
            let list = this.rawRows;

            if (this.search.trim()) {
                const q = this.search.toLowerCase().trim();
                list = list.filter(r => {
                    const name = (r.act_name || '').toLowerCase();
                    const code = (r.ACT_CODE || '').toString().toLowerCase();
                    const branch = (r.Branch_Name || '').toLowerCase();
                    return name.includes(q) || code.includes(q) || branch.includes(q);
                });
            }

            if (this.riskFilter === 'outstanding') {
                list = list.filter(r => (parseFloat(r.ClosingBalance) || 0) > 0);
            } else if (this.riskFilter === 'over90') {
                list = list.filter(r => (parseFloat(r['Over90Days']) || 0) > 0);
            }

            return list;
        },

        get paginatedRows() {
            return this.computedRows.slice(0, this.perPage);
        },

        get computedTotals() {
            const list = this.computedRows;
            const res = {
                opening: 0,
                debit: 0,
                credit: 0,
                bucket_0_30: 0,
                bucket_31_60: 0,
                bucket_61_90: 0,
                bucket_over_90: 0,
                closing: 0,
                overdue_count: 0
            };

            for (let i = 0; i < list.length; i++) {
                const r = list[i];
                res.opening += parseFloat(r.OpeningBalance) || 0;
                res.debit += parseFloat(r.TotalDebit) || 0;
                res.credit += parseFloat(r.TotalCredit) || 0;
                res.bucket_0_30 += parseFloat(r['0-30Days']) || 0;
                res.bucket_31_60 += parseFloat(r['31-60Days']) || 0;
                res.bucket_61_90 += parseFloat(r['61-90Days']) || 0;
                const o90 = parseFloat(r['Over90Days']) || 0;
                res.bucket_over_90 += o90;
                res.closing += parseFloat(r.ClosingBalance) || 0;
                if (o90 > 0) res.overdue_count++;
            }

            return res;
        },

        formatCurrency(val) {
            const num = parseFloat(val);
            if (isNaN(num)) return '₹0.00';
            return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatShort(val) {
            const num = parseFloat(val);
            if (isNaN(num) || num === 0) return '—';
            if (Math.abs(num) >= 10000000) {
                return (num / 10000000).toFixed(2) + 'Cr';
            }
            if (Math.abs(num) >= 100000) {
                return (num / 100000).toFixed(2) + 'L';
            }
            if (Math.abs(num) >= 1000) {
                return (num / 1000).toFixed(1) + 'k';
            }
            return num.toFixed(0);
        },

        getPercent(bucket, total) {
            const b = parseFloat(bucket) || 0;
            const t = parseFloat(total) || 0;
            if (t <= 0 || b <= 0) return 0;
            return Math.min(100, Math.round((b / t) * 100));
        },

        getBranchBadgeClass(code) {
            const map = {
                '2': 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                '3': 'bg-teal-50 text-teal-700 border border-teal-200',
                '6': 'bg-cyan-50 text-cyan-700 border border-cyan-200',
                '7': 'bg-amber-50 text-amber-700 border border-amber-200',
                '13': 'bg-purple-50 text-purple-700 border border-purple-200',
                '14': 'bg-rose-50 text-rose-700 border border-rose-200',
            };
            return map[String(code)] || 'bg-slate-100 text-slate-700 border border-slate-200';
        }
    };
}
</script>
@endsection
