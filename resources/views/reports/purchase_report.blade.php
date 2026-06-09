@extends('layouts.app')

@section('header', 'Purchase Report')

@section('content')
<div class="space-y-6">

    {{-- Header Card --}}
    <div class="bg-gradient-to-br from-amber-600 to-orange-600 rounded-2xl p-6 text-white shadow-lg shadow-amber-100 relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full"></div>
        <div class="absolute -left-6 -bottom-6 w-28 h-28 bg-white/5 rounded-full"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-white/20 border border-white/30 flex items-center justify-center shadow-lg">
                    <i class="fas fa-shopping-cart text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Purchase Register</h1>
                    <p class="text-amber-100 text-[11px] font-bold uppercase tracking-widest mt-0.5">
                        Algebra ERP — LogicPurchaseRegisterDetail
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-white/10 border border-white/20 rounded-xl px-4 py-2 text-sm font-bold">
                <div class="w-2 h-2 bg-green-300 rounded-full animate-pulse"></div>
                Live from ERP API
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-filter text-amber-500 text-sm"></i>
            <h3 class="text-xs font-black text-slate-600 uppercase tracking-widest">Filter Parameters</h3>
        </div>
        <form method="GET" action="{{ route('reports.purchase') }}" id="purchaseFilterForm" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">From Date</label>
                    <input type="date" name="from_date"
                        value="{{ $fromDate ?? $defaults['from_date'] }}"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">To Date</label>
                    <input type="date" name="to_date"
                        value="{{ $toDate ?? $defaults['to_date'] }}"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Account</label>
                    <div class="relative">
                        <select name="account"
                            class="w-full border border-gray-200 rounded-xl py-2.5 px-4 pr-9 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition appearance-none bg-white">
                            <option value="all" {{ ($account ?? 'all') === 'all' ? 'selected' : '' }}>— All Accounts —</option>
                            @if(!empty($accountOptions ?? []))
                                @foreach($accountOptions as $opt)
                                    <option value="{{ $opt }}" {{ ($account ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            @elseif(!empty($account) && $account !== 'all')
                                <option value="{{ $account }}" selected>{{ $account }}</option>
                            @endif
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-amber-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Item</label>
                    <div class="relative">
                        <select name="item"
                            class="w-full border border-gray-200 rounded-xl py-2.5 px-4 pr-9 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition appearance-none bg-white">
                            <option value="all" {{ ($item ?? 'all') === 'all' ? 'selected' : '' }}>— All Items —</option>
                            @if(!empty($itemOptions ?? []))
                                @foreach($itemOptions as $opt)
                                    <option value="{{ $opt['name'] }}" {{ ($item ?? '') === $opt['name'] ? 'selected' : '' }}>{{ $opt['name'] }}</option>
                                @endforeach
                            @elseif(!empty($item) && $item !== 'all')
                                <option value="{{ $item }}" selected>{{ $item }}</option>
                            @endif
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-indigo-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Branch</label>
                    <input type="text" name="branch"
                        value="{{ $branch ?? $defaults['branch'] }}"
                        placeholder="all"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition">
                </div>

                {{-- Rm Type Dropdown --}}
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Rm Type</label>
                    <div class="relative">
                        <select name="rm_type"
                            class="w-full border border-gray-200 rounded-xl py-2.5 px-4 pr-9 text-sm focus:ring-2 focus:ring-teal-400 outline-none transition appearance-none bg-white">
                            <option value="">— All Rm Types —</option>
                            @if(!empty($rmTypeOptions ?? []))
                                @foreach($rmTypeOptions as $opt)
                                    <option value="{{ $opt }}" {{ ($rmType ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            @elseif(!empty($rmType))
                                <option value="{{ $rmType }}" selected>{{ $rmType }}</option>
                            @endif
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-teal-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>

                {{-- Types Dropdown --}}
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Types</label>
                    <div class="relative">
                        <select name="types"
                            class="w-full border border-gray-200 rounded-xl py-2.5 px-4 pr-9 text-sm focus:ring-2 focus:ring-violet-400 outline-none transition appearance-none bg-white">
                            <option value="">— All Types —</option>
                            @if(!empty($typesOptions ?? []))
                                @foreach($typesOptions as $opt)
                                    <option value="{{ $opt }}" {{ ($types ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            @elseif(!empty($types))
                                <option value="{{ $types }}" selected>{{ $types }}</option>
                            @endif
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-violet-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex gap-3 mt-4">
                <button type="submit"
                    class="bg-amber-600 hover:bg-amber-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm text-sm flex items-center gap-2 transition active:scale-95">
                    <i class="fas fa-search"></i> Fetch Report
                </button>
                <a href="{{ route('reports.purchase') }}"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2.5 px-5 rounded-xl text-sm flex items-center gap-2 transition">
                    <i class="fas fa-rotate-left"></i> Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Error --}}
    @if(isset($error))
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl px-5 py-4 flex items-center gap-3 text-sm font-medium">
        <i class="fas fa-circle-exclamation text-red-500 text-base"></i>
        <div>
            <div class="font-black">API Error</div>
            <div class="font-medium opacity-80 text-xs mt-0.5">{{ $error }}</div>
        </div>
    </div>
    @endif

    {{-- Summary Stats --}}
    @if(!empty($reportData))
    @php
        $totalQty        = collect($reportData)->sum(fn($r) => (float)str_replace(',', '', $r['Qty'] ?? 0));
        $totalGrossAmt   = collect($reportData)->sum(fn($r) => (float)str_replace(',', '', $r['Calc_Gross_Amt'] ?? 0));
        $totalNetAmt     = collect($reportData)->sum(fn($r) => (float)str_replace(',', '', $r['Calc_Net_Amt'] ?? 0));
        $totalGST        = collect($reportData)->sum(fn($r) => (float)str_replace(',', '', $r['GST'] ?? 0));
        $uniqueSuppliers = collect($reportData)->pluck('SupplierName')->unique()->count();
        $uniqueItems     = collect($reportData)->pluck('User_Code')->unique()->count();
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Records</div>
            <div class="text-xl font-black text-slate-800">{{ number_format(count($reportData)) }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Suppliers</div>
            <div class="text-xl font-black text-violet-600">{{ number_format($uniqueSuppliers) }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Unique Items</div>
            <div class="text-xl font-black text-indigo-600">{{ number_format($uniqueItems) }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Qty</div>
            <div class="text-xl font-black text-slate-700">{{ number_format($totalQty, 2) }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Total GST</div>
            <div class="text-xl font-black text-rose-600">₹{{ number_format($totalGST, 0) }}</div>
        </div>
        <div class="bg-amber-50 rounded-2xl border border-amber-100 shadow-sm p-4">
            <div class="text-[9px] font-black text-amber-600 uppercase tracking-widest mb-1">Net Amount</div>
            <div class="text-xl font-black text-amber-700">₹{{ number_format($totalNetAmt, 0) }}</div>
        </div>
    </div>
    @endif

    {{-- Data Table --}}
    @if(isset($reportData))
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-slate-800 px-6 py-4 flex items-center justify-between">
            <h3 class="text-white font-bold flex items-center gap-2 text-sm">
                <i class="fas fa-table-cells text-amber-400"></i>
                Purchase Register Detail
                @if(!empty($reportData))
                <span class="ml-2 px-2 py-0.5 bg-amber-500/20 text-amber-300 text-[9px] font-bold rounded border border-amber-400/30">
                    {{ count($reportData) }} records
                </span>
                @endif
            </h3>
            @if(!empty($reportData))
            <input type="text" id="purchaseSearch" placeholder="Search in results..." onkeyup="filterTable()"
                class="bg-white/10 border border-white/20 text-white placeholder-white/40 text-xs font-medium rounded-xl px-4 py-2 focus:outline-none focus:ring-2 focus:ring-amber-400 w-56">
            @endif
        </div>

        @if(empty($reportData))
        <div class="py-16 text-center">
            <i class="fas fa-inbox text-4xl text-slate-200 mb-4"></i>
            <p class="text-slate-400 font-bold text-sm">No data. Set filters and click <strong>Fetch Report</strong>.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left border-collapse" id="purchaseTable">
                <thead class="bg-amber-50 border-b border-amber-100 text-[8px] font-black text-amber-700 uppercase tracking-widest whitespace-nowrap">
                    <tr>
                        <th class="py-3 px-3 text-center border-r border-amber-100">#</th>
                        <th class="py-3 px-3 border-r border-amber-100">Branch</th>
                        <th class="py-3 px-3 border-r border-amber-100 cursor-pointer hover:bg-amber-100 transition" onclick="sortTable(2)">Date <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-3 border-r border-amber-100 cursor-pointer hover:bg-amber-100 transition" onclick="sortTable(3)">Supplier <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-3 border-r border-amber-100">Bill No</th>
                        <th class="py-3 px-3 border-r border-amber-100">Rm Type</th>
                        <th class="py-3 px-3 border-r border-amber-100">Types</th>
                        <th class="py-3 px-3 border-r border-amber-100">Item Code</th>
                        <th class="py-3 px-3 border-r border-amber-100 cursor-pointer hover:bg-amber-100 transition" onclick="sortTable(8)">Item Name <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-3 border-r border-amber-100">Pack</th>
                        <th class="py-3 px-3 border-r border-amber-100 text-right cursor-pointer hover:bg-amber-100 transition" onclick="sortTable(10)">Qty <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-3 border-r border-amber-100 text-right">Cases</th>
                        <th class="py-3 px-3 border-r border-amber-100 text-right">Rate</th>
                        <th class="py-3 px-3 border-r border-amber-100 text-right">GST</th>
                        <th class="py-3 px-3 border-r border-amber-100 text-right">TCS</th>
                        <th class="py-3 px-3 border-r border-amber-100 text-right">Scheme</th>
                        <th class="py-3 px-3 border-r border-amber-100 text-right">Adjust</th>
                        <th class="py-3 px-3 border-r border-amber-100 text-right cursor-pointer hover:bg-amber-100 transition" onclick="sortTable(17)">Gross Amt <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-3 border-r border-amber-100 text-right cursor-pointer hover:bg-amber-100 transition" onclick="sortTable(18)">Net Amt <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-3 text-right">Purity%</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-xs font-medium text-gray-700" id="purchaseTbody">
                    @foreach($reportData as $index => $row)
                    @php
                        $grossAmt = (float)str_replace(',', '', $row['Calc_Gross_Amt'] ?? 0);
                        $netAmt   = (float)str_replace(',', '', $row['Calc_Net_Amt']   ?? 0);
                        $gst      = (float)str_replace(',', '', $row['GST']            ?? 0);
                        $tcs      = (float)str_replace(',', '', $row['Tcs']            ?? 0);
                        $scheme   = (float)str_replace(',', '', $row['SchemeRs']       ?? 0);
                        $adjust   = (float)str_replace(',', '', $row['AdjustRs']       ?? 0);
                        $qty      = (float)str_replace(',', '', $row['Qty']            ?? 0);
                        $cases    = (float)str_replace(',', '', $row['Cases']          ?? 0);
                        $rate     = (float)str_replace(',', '', $row['CaseRate']       ?? 0);
                        $purity   = $row['Purity'] ?? '';
                    @endphp
                    <tr class="hover:bg-amber-50/40 transition-colors purchase-row" data-index="{{ $index }}">
                        <td class="py-2.5 px-3 text-center text-gray-400 font-bold border-r border-gray-50 row-num">{{ $index + 1 }}</td>

                        {{-- Branch --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 whitespace-nowrap">
                            <span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-[9px] font-black uppercase">
                                {{ $row['Branch_Name'] ?? '—' }}
                            </span>
                        </td>

                        {{-- Date --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 whitespace-nowrap text-gray-500 text-[11px]">
                            {{ $row['Vouch_Date'] ?? '—' }}
                        </td>

                        {{-- Supplier --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 font-bold text-gray-800 max-w-[200px] truncate" title="{{ $row['SupplierName'] ?? '' }}">
                            {{ $row['SupplierName'] ?? '—' }}
                        </td>

                        {{-- Bill No --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 font-black text-indigo-600 whitespace-nowrap">
                            {{ $row['Bill_No'] ?? '—' }}
                        </td>

                        {{-- Rm Type (GroupName4) --}}
                        <td class="py-2.5 px-3 border-r border-gray-50">
                            @if(isset($row['GroupName4']) && $row['GroupName4'])
                            <span class="bg-teal-50 text-teal-700 border border-teal-100 px-1.5 py-0.5 rounded text-[9px] font-black">{{ $row['GroupName4'] }}</span>
                            @else
                            <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        {{-- Types (GroupName5) --}}
                        <td class="py-2.5 px-3 border-r border-gray-50">
                            @if(isset($row['GroupName5']) && $row['GroupName5'])
                            <span class="bg-violet-50 text-violet-700 border border-violet-100 px-1.5 py-0.5 rounded text-[9px] font-black">{{ $row['GroupName5'] }}</span>
                            @else
                            <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        {{-- Item Code --}}
                        <td class="py-2.5 px-3 border-r border-gray-50">
                            <span class="bg-indigo-50 text-indigo-700 border border-indigo-100 px-1.5 py-0.5 rounded text-[9px] font-black uppercase">
                                {{ $row['User_Code'] ?? '—' }}
                            </span>
                        </td>

                        {{-- Item Name --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 font-bold text-gray-700 max-w-[180px] truncate" title="{{ $row['Item_Hd_Name'] ?? '' }}">
                            {{ $row['Item_Hd_Name'] ?? '—' }}
                        </td>

                        {{-- Pack --}}
                        <td class="py-2.5 px-3 border-r border-gray-50">
                            @if(isset($row['Pack_Name']) && $row['Pack_Name'])
                            <span class="bg-amber-50 text-amber-700 border border-amber-100 px-1.5 py-0.5 rounded text-[9px] font-black">{{ $row['Pack_Name'] }}</span>
                            @else
                            <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        {{-- Qty --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 text-right font-black text-gray-800">
                            {{ number_format($qty, 2) }}
                        </td>

                        {{-- Cases --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 text-right text-gray-500">
                            {{ number_format($cases, 2) }}
                        </td>

                        {{-- Rate --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 text-right font-bold text-gray-600">
                            ₹{{ number_format($rate, 2) }}
                        </td>

                        {{-- GST --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 text-right text-rose-600 font-bold">
                            {{ $gst > 0 ? '₹' . number_format($gst, 0) : '—' }}
                        </td>

                        {{-- TCS --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 text-right text-gray-500">
                            {{ $tcs > 0 ? '₹' . number_format($tcs, 2) : '—' }}
                        </td>

                        {{-- Scheme --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 text-right text-green-600 font-bold">
                            {{ $scheme > 0 ? '₹' . number_format($scheme, 2) : '—' }}
                        </td>

                        {{-- Adjust --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 text-right text-gray-500">
                            {{ $adjust > 0 ? '₹' . number_format($adjust, 2) : '—' }}
                        </td>

                        {{-- Gross Amount --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 text-right font-bold text-gray-700">
                            ₹{{ number_format($grossAmt, 0) }}
                        </td>

                        {{-- Net Amount --}}
                        <td class="py-2.5 px-3 border-r border-gray-50 text-right font-black text-amber-700">
                            ₹{{ number_format($netAmt, 0) }}
                        </td>

                        {{-- Purity --}}
                        <td class="py-2.5 px-3 text-right">
                            @if($purity !== '' && $purity !== null)
                            <span class="bg-emerald-50 text-emerald-700 border border-emerald-100 px-1.5 py-0.5 rounded text-[9px] font-black">{{ $purity }}%</span>
                            @else
                            <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                {{-- Footer totals --}}
                <tfoot class="bg-amber-50 border-t-2 border-amber-200 text-xs font-black text-amber-800">
                    <tr>
                        <td colspan="10" class="py-3 px-3 text-right uppercase tracking-widest text-[9px]">Totals →</td>
                        <td class="py-3 px-3 text-right">{{ number_format($totalQty, 2) }}</td>
                        <td class="py-3 px-3 text-right">—</td>
                        <td class="py-3 px-3 text-right">—</td>
                        <td class="py-3 px-3 text-right text-rose-700">₹{{ number_format($totalGST, 0) }}</td>
                        <td class="py-3 px-3 text-right">—</td>
                        <td class="py-3 px-3 text-right">—</td>
                        <td class="py-3 px-3 text-right">—</td>
                        <td class="py-3 px-3 text-right">₹{{ number_format($totalGrossAmt, 0) }}</td>
                        <td class="py-3 px-3 text-right text-amber-700">₹{{ number_format($totalNetAmt, 0) }}</td>
                        <td class="py-3 px-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Pagination Controls --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-slate-50" id="paginationBar">
            <div class="flex items-center gap-3 text-xs text-slate-500 font-medium">
                <span>Show</span>
                <select id="pageSizeSelect" onchange="changePageSize()" class="border border-gray-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-amber-400 outline-none">
                    <option value="25">25</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                    <option value="9999999">All</option>
                </select>
                <span>rows per page</span>
                <span class="ml-2 text-slate-400" id="pageInfo"></span>
            </div>
            <div class="flex items-center gap-1" id="paginationBtns"></div>
        </div>
        @endif
    </div>
    @endif

</div>

<script>
// ── Pagination ──────────────────────────────────────────────────────────────
let allRows      = [];
let filteredRows = [];
let currentPage  = 1;
let pageSize     = 50;

function initPagination() {
    allRows      = Array.from(document.querySelectorAll('#purchaseTbody .purchase-row'));
    filteredRows = [...allRows];
    renderPage();
}

function renderPage() {
    const total = filteredRows.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * pageSize;
    const end   = Math.min(start + pageSize, total);

    // Show/hide rows
    allRows.forEach(r => r.style.display = 'none');
    filteredRows.forEach((r, i) => {
        r.style.display = (i >= start && i < end) ? '' : 'none';
        // Update row numbers
        const numCell = r.querySelector('.row-num');
        if (numCell) numCell.textContent = i + 1;
    });

    // Page info
    const info = document.getElementById('pageInfo');
    if (info) info.textContent = total > 0 ? `Showing ${start+1}–${end} of ${total}` : 'No results';

    // Pagination buttons
    renderPaginationBtns(totalPages);
}

function renderPaginationBtns(totalPages) {
    const container = document.getElementById('paginationBtns');
    if (!container) return;

    const btnBase = 'px-3 py-1.5 rounded-lg text-xs font-bold transition focus:outline-none';
    const btnActive = 'bg-amber-500 text-white shadow';
    const btnInactive = 'bg-white border border-gray-200 text-slate-600 hover:bg-amber-50 hover:border-amber-300';
    const btnDisabled = 'bg-white border border-gray-100 text-gray-300 cursor-not-allowed';

    let html = '';

    // Prev
    if (currentPage > 1) {
        html += `<button class="${btnBase} ${btnInactive}" onclick="goToPage(${currentPage-1})"><i class="fas fa-chevron-left"></i></button>`;
    } else {
        html += `<button class="${btnBase} ${btnDisabled}" disabled><i class="fas fa-chevron-left"></i></button>`;
    }

    // Page numbers (smart window)
    const window_size = 5;
    let startPage = Math.max(1, currentPage - Math.floor(window_size/2));
    let endPage   = Math.min(totalPages, startPage + window_size - 1);
    if (endPage - startPage < window_size - 1) startPage = Math.max(1, endPage - window_size + 1);

    if (startPage > 1) {
        html += `<button class="${btnBase} ${btnInactive}" onclick="goToPage(1)">1</button>`;
        if (startPage > 2) html += `<span class="px-2 text-gray-400 text-xs font-bold">…</span>`;
    }
    for (let p = startPage; p <= endPage; p++) {
        html += `<button class="${btnBase} ${p === currentPage ? btnActive : btnInactive}" onclick="goToPage(${p})">${p}</button>`;
    }
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<span class="px-2 text-gray-400 text-xs font-bold">…</span>`;
        html += `<button class="${btnBase} ${btnInactive}" onclick="goToPage(${totalPages})">${totalPages}</button>`;
    }

    // Next
    if (currentPage < totalPages) {
        html += `<button class="${btnBase} ${btnInactive}" onclick="goToPage(${currentPage+1})"><i class="fas fa-chevron-right"></i></button>`;
    } else {
        html += `<button class="${btnBase} ${btnDisabled}" disabled><i class="fas fa-chevron-right"></i></button>`;
    }

    container.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    renderPage();
    document.getElementById('purchaseTable')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function changePageSize() {
    pageSize = parseInt(document.getElementById('pageSizeSelect').value);
    currentPage = 1;
    renderPage();
}

// ── Search Filter ────────────────────────────────────────────────────────────
function filterTable() {
    const q = document.getElementById('purchaseSearch').value.toLowerCase().trim();
    filteredRows = q
        ? allRows.filter(row => row.textContent.toLowerCase().includes(q))
        : [...allRows];
    currentPage = 1;
    renderPage();
}

// ── Column Sort ──────────────────────────────────────────────────────────────
let sortDir = {};
function sortTable(colIndex) {
    const dir = (sortDir[colIndex] = !(sortDir[colIndex]));
    filteredRows.sort((a, b) => {
        const aText = a.cells[colIndex]?.textContent.trim() || '';
        const bText = b.cells[colIndex]?.textContent.trim() || '';
        const aNum  = parseFloat(aText.replace(/[₹,%\s]/g,''));
        const bNum  = parseFloat(bText.replace(/[₹,%\s]/g,''));
        if (!isNaN(aNum) && !isNaN(bNum)) return dir ? aNum - bNum : bNum - aNum;
        return dir ? aText.localeCompare(bText) : bText.localeCompare(aText);
    });
    // Also re-sort allRows for consistent state
    allRows.sort((a, b) => {
        const ai = filteredRows.indexOf(a);
        const bi = filteredRows.indexOf(b);
        return (ai === -1 ? 9999 : ai) - (bi === -1 ? 9999 : bi);
    });
    // Re-append in sorted order
    const tbody = document.getElementById('purchaseTbody');
    filteredRows.forEach(r => tbody.appendChild(r));
    allRows.filter(r => !filteredRows.includes(r)).forEach(r => tbody.appendChild(r));
    renderPage();
}

// Init on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('purchaseTbody')) initPagination();
});
</script>
@endsection
