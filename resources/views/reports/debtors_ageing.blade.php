@extends('layouts.app')

@section('header', 'Debtors Ageing Report')

@section('content')
<div class="space-y-6" x-data="debtorsAgeingTable(@js($rows), @js($totals))">

    {{-- Header Banner --}}
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 lg:p-8 text-white shadow-xl relative overflow-hidden border border-indigo-900/40">
        <div class="absolute -right-12 -top-12 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-12 -bottom-12 w-64 h-64 bg-rose-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-rose-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/30 border border-white/20 shrink-0">
                    <i class="fas fa-calendar-days text-2xl"></i>
                </div>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl md:text-3xl font-black tracking-tight">Debtors Ageing Report</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black tracking-wider uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Live ERP API
                        </span>
                    </div>
                    <p class="text-indigo-200/80 text-xs font-semibold tracking-wide mt-1">
                        Accounts Receivable Aging Analysis • Logic ERP (<span class="font-mono text-white/90">LogicLedgerAgeing</span>)
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Refresh Cache Button --}}
                <a href="{{ request()->fullUrlWithQuery(['refresh' => 1]) }}"
                   class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-white text-xs font-bold transition flex items-center gap-2 backdrop-blur-md active:scale-95 shadow-sm">
                    <i class="fas fa-rotate text-indigo-300"></i>
                    <span>Refresh API</span>
                </a>

                {{-- Export to Excel --}}
                <a href="{{ route('reports.debtors-ageing.excel', request()->query()) }}"
                   class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-black uppercase tracking-wider transition flex items-center gap-2 shadow-lg shadow-emerald-950/40 active:scale-95">
                    <i class="fas fa-file-excel text-sm"></i>
                    <span>Export Excel</span>
                </a>
            </div>
        </div>
    </div>

    @if(isset($error) && $error)
    <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-xl flex items-center justify-between text-rose-800 shadow-sm">
        <div class="flex items-center gap-3">
            <i class="fas fa-triangle-exclamation text-rose-500 text-xl"></i>
            <div>
                <p class="font-bold text-sm">API Communication Notice</p>
                <p class="text-xs text-rose-700 mt-0.5">{{ $error }}</p>
            </div>
        </div>
        <a href="{{ request()->fullUrlWithQuery(['refresh' => 1]) }}" class="px-3 py-1.5 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition">Retry</a>
    </div>
    @endif

    {{-- Executive Summary KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
        {{-- Total Outstanding --}}
        <div class="bg-white rounded-2xl p-4 border border-indigo-100 shadow-sm relative overflow-hidden group hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Outstanding</span>
                <span class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">
                    <i class="fas fa-coins"></i>
                </span>
            </div>
            <div class="text-lg font-black text-slate-800 tracking-tight" x-text="formatCurrency(computedTotals.closing)">
                ₹{{ number_format($totals['closing'], 2) }}
            </div>
            <div class="text-[10px] font-semibold text-slate-400 mt-1 flex items-center gap-1">
                <span class="font-bold text-indigo-600" x-text="computedRows.length"></span> Active Parties
            </div>
        </div>

        {{-- 0-30 Days --}}
        <div class="bg-white rounded-2xl p-4 border border-emerald-100 shadow-sm relative overflow-hidden group hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600">0 - 30 Days</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fas fa-circle-check"></i>
                </span>
            </div>
            <div class="text-lg font-black text-emerald-700 tracking-tight" x-text="formatCurrency(computedTotals.bucket_0_30)">
                ₹{{ number_format($totals['bucket_0_30'], 2) }}
            </div>
            <div class="text-[10px] font-semibold text-emerald-600/80 mt-1">
                Current / Normal
            </div>
        </div>

        {{-- 31-60 Days --}}
        <div class="bg-white rounded-2xl p-4 border border-amber-100 shadow-sm relative overflow-hidden group hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-600">31 - 60 Days</span>
                <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                    <i class="fas fa-clock"></i>
                </span>
            </div>
            <div class="text-lg font-black text-amber-700 tracking-tight" x-text="formatCurrency(computedTotals.bucket_31_60)">
                ₹{{ number_format($totals['bucket_31_60'], 2) }}
            </div>
            <div class="text-[10px] font-semibold text-amber-600/80 mt-1">
                Attention Required
            </div>
        </div>

        {{-- 61-90 Days --}}
        <div class="bg-white rounded-2xl p-4 border border-orange-100 shadow-sm relative overflow-hidden group hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-orange-600">61 - 90 Days</span>
                <span class="w-8 h-8 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-xs">
                    <i class="fas fa-triangle-exclamation"></i>
                </span>
            </div>
            <div class="text-lg font-black text-orange-700 tracking-tight" x-text="formatCurrency(computedTotals.bucket_61_90)">
                ₹{{ number_format($totals['bucket_61_90'], 2) }}
            </div>
            <div class="text-[10px] font-semibold text-orange-600/80 mt-1">
                Warning Bracket
            </div>
        </div>

        {{-- Over 90 Days --}}
        <div class="bg-rose-50/70 rounded-2xl p-4 border border-rose-200 shadow-sm relative overflow-hidden group hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-700">Over 90 Days</span>
                <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-xs animate-pulse">
                    <i class="fas fa-fire"></i>
                </span>
            </div>
            <div class="text-lg font-black text-rose-700 tracking-tight" x-text="formatCurrency(computedTotals.bucket_over_90)">
                ₹{{ number_format($totals['bucket_over_90'], 2) }}
            </div>
            <div class="text-[10px] font-black text-rose-600 mt-1 flex items-center gap-1">
                <span x-text="computedTotals.overdue_count"></span> High-Risk Parties
            </div>
        </div>

        {{-- Total Opening / Balance --}}
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Opening</span>
                <span class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-xs">
                    <i class="fas fa-scale-balanced"></i>
                </span>
            </div>
            <div class="text-lg font-black text-slate-700 tracking-tight" x-text="formatCurrency(computedTotals.opening)">
                ₹{{ number_format($totals['opening'], 2) }}
            </div>
            <div class="text-[10px] font-semibold text-slate-400 mt-1">
                Opening Balance
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="bg-slate-50/80 px-6 py-3.5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-filter text-indigo-500 text-xs"></i>
                <h3 class="text-xs font-black text-slate-700 uppercase tracking-wider">Search & Filters</h3>
            </div>
            <span class="text-[10px] font-semibold text-slate-400">
                Last Cached: {{ $lastCachedAt }}
            </span>
        </div>

        <form method="GET" action="{{ route('reports.debtors-ageing') }}" id="debtorsFilterForm" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                {{-- Branch Filter --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1.5 ml-1">
                        Branch Code & Location
                    </label>
                    <div class="relative">
                        <select name="branch" class="w-full border border-slate-200 rounded-xl py-2.5 px-3.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none appearance-none bg-white pr-9">
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

                {{-- Party / ActCode Filter --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1.5 ml-1">
                        Party (Account Code)
                    </label>
                    <div class="relative">
                        <select name="act_code" class="w-full border border-slate-200 rounded-xl py-2.5 px-3.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none appearance-none bg-white pr-9">
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

                {{-- From Date --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1.5 ml-1">
                        From Date
                    </label>
                    <input type="date" name="from_date" value="{{ $fromDate }}"
                           class="w-full border border-slate-200 rounded-xl py-2.5 px-3.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                {{-- To Date --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1.5 ml-1">
                        To Date
                    </label>
                    <input type="date" name="to_date" value="{{ $toDate }}"
                           class="w-full border border-slate-200 rounded-xl py-2.5 px-3.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>

            {{-- Filter Footer Buttons --}}
            <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">FY Presets:</span>
                    <button type="button" @click="setPresetDates('2026-04-01', '2027-03-31')"
                            class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 transition">
                        FY 2026-27
                    </button>
                    <button type="button" @click="setPresetDates('2025-04-01', '2026-03-31')"
                            class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 transition">
                        FY 2025-26
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.debtors-ageing') }}"
                       class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                        Reset
                    </a>
                    <button type="submit"
                            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-wider transition shadow-md shadow-indigo-100 flex items-center gap-2 active:scale-95">
                        <i class="fas fa-magnifying-glass"></i>
                        <span>Apply Filters</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Interactive Table Container --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        {{-- Table Toolbar --}}
        <div class="p-4 md:p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/50">
            {{-- Quick Client Search --}}
            <div class="relative w-full md:w-80">
                <input type="text" x-model="search"
                       placeholder="Quick search party name, code..."
                       class="w-full bg-white border border-slate-200 rounded-xl py-2 pl-9 pr-8 text-xs font-semibold text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 outline-none transition shadow-sm">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-slate-400">
                    <i class="fas fa-search text-xs"></i>
                </div>
                <button type="button" x-show="search" @click="search = ''" class="absolute inset-y-0 right-2.5 flex items-center text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times-circle text-xs"></i>
                </button>
            </div>

            {{-- Quick Segmented Risk Filter --}}
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 mr-1">View:</span>
                <button type="button" @click="riskFilter = 'all'"
                        :class="riskFilter === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                    <span>All</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full" :class="riskFilter === 'all' ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-600'" x-text="rawRows.length"></span>
                </button>
                <button type="button" @click="riskFilter = 'outstanding'"
                        :class="riskFilter === 'outstanding' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                    <span>Outstanding > 0</span>
                </button>
                <button type="button" @click="riskFilter = 'over90'"
                        :class="riskFilter === 'over90' ? 'bg-rose-600 text-white shadow-sm' : 'bg-white text-rose-600 hover:bg-rose-50 border border-rose-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                    <i class="fas fa-fire text-[10px]"></i>
                    <span>Over 90 Days</span>
                </button>
            </div>
        </div>

        {{-- Table Scroll Container --}}
        <div class="overflow-x-auto max-h-[650px] relative">
            <table class="w-full text-left border-collapse min-w-[1100px]">
                <thead class="sticky top-0 z-20 bg-slate-100/95 backdrop-blur-md border-b border-slate-200 shadow-xs">
                    <tr>
                        <th class="px-4 py-3.5 text-[10px] font-black text-slate-500 uppercase tracking-wider">Branch</th>
                        <th class="px-4 py-3.5 text-[10px] font-black text-slate-500 uppercase tracking-wider cursor-pointer hover:text-indigo-600" @click="toggleSort('act_name')">
                            Party Details
                            <i class="fas fa-sort text-[9px] ml-1 opacity-60"></i>
                        </th>
                        <th class="px-3 py-3.5 text-[10px] font-black text-slate-500 uppercase tracking-wider text-right">Credit Limit</th>
                        <th class="px-3 py-3.5 text-[10px] font-black text-slate-500 uppercase tracking-wider text-right">Opening</th>
                        <th class="px-3 py-3.5 text-[10px] font-black text-slate-500 uppercase tracking-wider text-right">Debit</th>
                        <th class="px-3 py-3.5 text-[10px] font-black text-slate-500 uppercase tracking-wider text-right">Credit</th>
                        <th class="px-3 py-3.5 text-[10px] font-black text-emerald-700 uppercase tracking-wider text-right bg-emerald-50/50 cursor-pointer hover:text-emerald-900" @click="toggleSort('0-30Days')">
                            0-30 Days
                            <i class="fas fa-sort text-[9px] ml-1 opacity-60"></i>
                        </th>
                        <th class="px-3 py-3.5 text-[10px] font-black text-amber-700 uppercase tracking-wider text-right bg-amber-50/50 cursor-pointer hover:text-amber-900" @click="toggleSort('31-60Days')">
                            31-60 Days
                            <i class="fas fa-sort text-[9px] ml-1 opacity-60"></i>
                        </th>
                        <th class="px-3 py-3.5 text-[10px] font-black text-orange-700 uppercase tracking-wider text-right bg-orange-50/50 cursor-pointer hover:text-orange-900" @click="toggleSort('61-90Days')">
                            61-90 Days
                            <i class="fas fa-sort text-[9px] ml-1 opacity-60"></i>
                        </th>
                        <th class="px-3 py-3.5 text-[10px] font-black text-rose-700 uppercase tracking-wider text-right bg-rose-50/70 cursor-pointer hover:text-rose-900" @click="toggleSort('Over90Days')">
                            Over 90 Days
                            <i class="fas fa-sort text-[9px] ml-1 opacity-60"></i>
                        </th>
                        <th class="px-4 py-3.5 text-[10px] font-black text-slate-700 uppercase tracking-wider text-right bg-indigo-50/60 cursor-pointer hover:text-indigo-900" @click="toggleSort('ClosingBalance')">
                            Outstanding (Closing)
                            <i class="fas fa-sort text-[9px] ml-1 opacity-60"></i>
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                    <template x-for="(row, idx) in paginatedRows" :key="idx">
                        <tr class="hover:bg-slate-50/80 transition group">
                            {{-- Branch --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black tracking-wide"
                                      :class="getBranchBadgeClass(row.Branch_Code)">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                                    <span x-text="row.Branch_Name"></span>
                                </span>
                            </td>

                            {{-- Party Details --}}
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-800 leading-snug" x-text="row.act_name"></div>
                                <div class="text-[10px] font-mono text-slate-400 mt-0.5">
                                    Code: #<span x-text="row.ACT_CODE"></span>
                                </div>
                            </td>

                            {{-- Credit Limit --}}
                            <td class="px-3 py-3 text-right font-mono text-slate-500 whitespace-nowrap"
                                x-text="row.Credit_Limit > 0 ? formatNumber(row.Credit_Limit) : '—'">
                            </td>

                            {{-- Opening --}}
                            <td class="px-3 py-3 text-right font-mono whitespace-nowrap"
                                :class="row.OpeningBalance < 0 ? 'text-rose-600' : 'text-slate-600'"
                                x-text="formatNumber(row.OpeningBalance)">
                            </td>

                            {{-- Debit --}}
                            <td class="px-3 py-3 text-right font-mono text-slate-600 whitespace-nowrap"
                                x-text="formatNumber(row.TotalDebit)">
                            </td>

                            {{-- Credit --}}
                            <td class="px-3 py-3 text-right font-mono text-slate-600 whitespace-nowrap"
                                x-text="formatNumber(row.TotalCredit)">
                            </td>

                            {{-- 0-30 Days --}}
                            <td class="px-3 py-3 text-right font-mono font-semibold text-emerald-700 bg-emerald-50/20 whitespace-nowrap"
                                x-text="row['0-30Days'] > 0 ? formatNumber(row['0-30Days']) : '—'">
                            </td>

                            {{-- 31-60 Days --}}
                            <td class="px-3 py-3 text-right font-mono font-semibold text-amber-700 bg-amber-50/20 whitespace-nowrap"
                                x-text="row['31-60Days'] > 0 ? formatNumber(row['31-60Days']) : '—'">
                            </td>

                            {{-- 61-90 Days --}}
                            <td class="px-3 py-3 text-right font-mono font-semibold text-orange-700 bg-orange-50/20 whitespace-nowrap"
                                x-text="row['61-90Days'] > 0 ? formatNumber(row['61-90Days']) : '—'">
                            </td>

                            {{-- Over 90 Days --}}
                            <td class="px-3 py-3 text-right font-mono font-bold bg-rose-50/30 whitespace-nowrap"
                                :class="row['Over90Days'] > 0 ? 'text-rose-700' : 'text-slate-400'">
                                <span x-show="row['Over90Days'] > 0" class="inline-flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                    <span x-text="formatNumber(row['Over90Days'])"></span>
                                </span>
                                <span x-show="!(row['Over90Days'] > 0)">—</span>
                            </td>

                            {{-- Closing / Total Outstanding --}}
                            <td class="px-4 py-3 text-right font-mono font-black text-slate-900 bg-indigo-50/30 whitespace-nowrap">
                                <span :class="row.ClosingBalance > 0 ? 'text-indigo-950 font-black' : 'text-slate-500'"
                                      x-text="formatNumber(row.ClosingBalance)">
                                </span>
                            </td>
                        </tr>
                    </template>

                    {{-- Empty State --}}
                    <tr x-show="computedRows.length === 0">
                        <td colspan="11" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-inbox text-3xl mb-3 text-slate-300"></i>
                            <p class="font-bold text-sm">No records match the current filter criteria.</p>
                            <p class="text-xs text-slate-400 mt-1">Try resetting the search keyword or branch selection.</p>
                        </td>
                    </tr>
                </tbody>

                {{-- Sticky Summary Footer --}}
                <tfoot class="sticky bottom-0 z-20 bg-slate-900 text-white font-mono text-xs font-black shadow-lg">
                    <tr>
                        <td class="px-4 py-3.5 uppercase tracking-wider" colspan="2">
                            Total (<span x-text="computedRows.length"></span> Parties)
                        </td>
                        <td class="px-3 py-3.5 text-right text-slate-400">—</td>
                        <td class="px-3 py-3.5 text-right whitespace-nowrap text-slate-300" x-text="formatNumber(computedTotals.opening)"></td>
                        <td class="px-3 py-3.5 text-right whitespace-nowrap text-slate-300" x-text="formatNumber(computedTotals.debit)"></td>
                        <td class="px-3 py-3.5 text-right whitespace-nowrap text-slate-300" x-text="formatNumber(computedTotals.credit)"></td>
                        <td class="px-3 py-3.5 text-right whitespace-nowrap text-emerald-400" x-text="formatNumber(computedTotals.bucket_0_30)"></td>
                        <td class="px-3 py-3.5 text-right whitespace-nowrap text-amber-400" x-text="formatNumber(computedTotals.bucket_31_60)"></td>
                        <td class="px-3 py-3.5 text-right whitespace-nowrap text-orange-400" x-text="formatNumber(computedTotals.bucket_61_90)"></td>
                        <td class="px-3 py-3.5 text-right whitespace-nowrap text-rose-400" x-text="formatNumber(computedTotals.bucket_over_90)"></td>
                        <td class="px-4 py-3.5 text-right whitespace-nowrap text-white bg-indigo-700/80" x-text="formatNumber(computedTotals.closing)"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Pagination & Count Footer --}}
        <div class="px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 bg-slate-50/50">
            <div>
                Showing <span class="font-bold text-slate-700" x-text="paginatedRows.length"></span> of
                <span class="font-bold text-slate-700" x-text="computedRows.length"></span> records
                <span x-show="computedRows.length !== rawRows.length" class="text-indigo-600 font-semibold">
                    (filtered from <span x-text="rawRows.length"></span> total)
                </span>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="perPage += 100" x-show="perPage < computedRows.length"
                        class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-100 rounded-lg text-xs font-bold text-slate-700 transition shadow-xs">
                    Load More (+100)
                </button>
                <button type="button" @click="perPage = computedRows.length" x-show="perPage < computedRows.length"
                        class="px-3 py-1.5 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 rounded-lg text-xs font-bold text-indigo-700 transition">
                    Show All
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Alpine.js Table Controller --}}
<script>
function debtorsAgeingTable(rawRows, initialTotals) {
    return {
        rawRows: rawRows || [],
        search: '',
        riskFilter: 'all',
        sortBy: 'ClosingBalance',
        sortAsc: false,
        perPage: 150,

        get computedRows() {
            let list = this.rawRows;

            // Search
            if (this.search.trim()) {
                const q = this.search.toLowerCase().trim();
                list = list.filter(r => {
                    const name = (r.act_name || '').toLowerCase();
                    const code = (r.ACT_CODE || '').toString().toLowerCase();
                    const branch = (r.Branch_Name || '').toLowerCase();
                    return name.includes(q) || code.includes(q) || branch.includes(q);
                });
            }

            // Risk Filter
            if (this.riskFilter === 'outstanding') {
                list = list.filter(r => (parseFloat(r.ClosingBalance) || 0) > 0);
            } else if (this.riskFilter === 'over90') {
                list = list.filter(r => (parseFloat(r['Over90Days']) || 0) > 0);
            }

            // Sorting
            list = [...list].sort((a, b) => {
                let va = a[this.sortBy];
                let vb = b[this.sortBy];

                if (typeof va === 'string' && isNaN(va)) {
                    return this.sortAsc ? va.localeCompare(vb) : vb.localeCompare(va);
                }

                va = parseFloat(va) || 0;
                vb = parseFloat(vb) || 0;
                return this.sortAsc ? va - vb : vb - va;
            });

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

        toggleSort(field) {
            if (this.sortBy === field) {
                this.sortAsc = !this.sortAsc;
            } else {
                this.sortBy = field;
                this.sortAsc = false;
            }
        },

        formatNumber(val) {
            const num = parseFloat(val);
            if (isNaN(num)) return '0.00';
            return num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatCurrency(val) {
            const num = parseFloat(val);
            if (isNaN(num)) return '₹0.00';
            return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
        },

        setPresetDates(from, to) {
            const form = document.getElementById('debtorsFilterForm');
            if (form) {
                form.querySelector('input[name="from_date"]').value = from;
                form.querySelector('input[name="to_date"]').value = to;
            }
        }
    };
}
</script>
@endsection
