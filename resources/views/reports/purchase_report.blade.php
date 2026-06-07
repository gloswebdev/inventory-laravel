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
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">From Date</label>
                    <input type="date" name="from_date"
                        value="{{ request('from_date', $defaults['from_date']) }}"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">To Date</label>
                    <input type="date" name="to_date"
                        value="{{ request('to_date', $defaults['to_date']) }}"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Account</label>
                    <input type="text" name="account"
                        value="{{ request('account', $defaults['account']) }}"
                        placeholder="all"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Item</label>
                    <input type="text" name="item"
                        value="{{ request('item', $defaults['item']) }}"
                        placeholder="all"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Branch</label>
                    <input type="text" name="branch"
                        value="{{ request('branch', $defaults['branch']) }}"
                        placeholder="all"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition">
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
        $totalQty   = collect($reportData)->sum(fn($r) => (float)($r['Qty'] ?? 0));
        $totalAmt   = collect($reportData)->sum(fn($r) => (float)($r['Amount'] ?? 0));
        $uniqueItems = collect($reportData)->pluck('User_Code')->unique()->count();
        $uniqueParties = collect($reportData)->pluck('Account_Name')->unique()->count();
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Rows</div>
            <div class="text-2xl font-black text-slate-800">{{ number_format(count($reportData)) }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Unique Items</div>
            <div class="text-2xl font-black text-indigo-600">{{ number_format($uniqueItems) }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Unique Parties</div>
            <div class="text-2xl font-black text-emerald-600">{{ number_format($uniqueParties) }}</div>
        </div>
        <div class="bg-amber-50 rounded-2xl border border-amber-100 shadow-sm p-5">
            <div class="text-[10px] font-black text-amber-600 uppercase tracking-widest mb-1">Total Amount</div>
            <div class="text-2xl font-black text-amber-700">₹{{ number_format($totalAmt, 2) }}</div>
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
            {{-- Search box --}}
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
                <thead class="bg-amber-50 border-b border-amber-100 text-[9px] font-black text-amber-700 uppercase tracking-widest">
                    <tr>
                        <th class="py-3 px-4 text-center border-r border-amber-100">#</th>
                        <th class="py-3 px-4 border-r border-amber-100 cursor-pointer hover:bg-amber-100 transition" onclick="sortTable(1)">Date <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-4 border-r border-amber-100 cursor-pointer hover:bg-amber-100 transition" onclick="sortTable(2)">Bill No <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-4 border-r border-amber-100 cursor-pointer hover:bg-amber-100 transition" onclick="sortTable(3)">Party / Account <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-4 border-r border-amber-100 cursor-pointer hover:bg-amber-100 transition" onclick="sortTable(4)">Item Code <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-4 border-r border-amber-100 cursor-pointer hover:bg-amber-100 transition" onclick="sortTable(5)">Item Name <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-4 border-r border-amber-100 cursor-pointer hover:bg-amber-100 transition text-right" onclick="sortTable(6)">Qty <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-4 border-r border-amber-100 text-right">Unit</th>
                        <th class="py-3 px-4 border-r border-amber-100 cursor-pointer hover:bg-amber-100 transition text-right" onclick="sortTable(8)">Rate <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-4 border-r border-amber-100 cursor-pointer hover:bg-amber-100 transition text-right" onclick="sortTable(9)">Amount <i class="fas fa-sort ml-1 opacity-40"></i></th>
                        <th class="py-3 px-4 text-center">Branch</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-xs font-medium text-gray-700" id="purchaseTbody">
                    @foreach($reportData as $index => $row)
                    <tr class="hover:bg-amber-50/40 transition-colors purchase-row">
                        <td class="py-3 px-4 text-center text-gray-400 font-bold border-r border-gray-50">{{ $index + 1 }}</td>
                        <td class="py-3 px-4 border-r border-gray-50 whitespace-nowrap text-gray-500">
                            {{ isset($row['Date']) ? \Carbon\Carbon::parse($row['Date'])->format('d M Y') : '—' }}
                        </td>
                        <td class="py-3 px-4 border-r border-gray-50 font-bold text-indigo-600 whitespace-nowrap">
                            {{ $row['Bill_No'] ?? '—' }}
                        </td>
                        <td class="py-3 px-4 border-r border-gray-50 font-bold text-gray-800 max-w-[180px] truncate" title="{{ $row['Account_Name'] ?? '' }}">
                            {{ $row['Account_Name'] ?? '—' }}
                        </td>
                        <td class="py-3 px-4 border-r border-gray-50">
                            <span class="bg-indigo-50 text-indigo-700 border border-indigo-100 px-1.5 py-0.5 rounded text-[9px] font-black uppercase">
                                {{ $row['User_Code'] ?? '—' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 border-r border-gray-50 text-gray-700 max-w-[160px] truncate" title="{{ $row['Item_Name'] ?? '' }}">
                            {{ $row['Item_Name'] ?? '—' }}
                        </td>
                        <td class="py-3 px-4 border-r border-gray-50 text-right font-black text-gray-800">
                            {{ number_format((float)($row['Qty'] ?? 0), 2) }}
                        </td>
                        <td class="py-3 px-4 border-r border-gray-50 text-right text-gray-400 text-[10px] font-bold uppercase">
                            {{ $row['Unit'] ?? '' }}
                        </td>
                        <td class="py-3 px-4 border-r border-gray-50 text-right font-bold text-gray-600">
                            ₹{{ number_format((float)($row['CaseRate'] ?? $row['Rate'] ?? 0), 2) }}
                        </td>
                        <td class="py-3 px-4 border-r border-gray-50 text-right font-black text-amber-700">
                            ₹{{ number_format((float)($row['Amount'] ?? 0), 2) }}
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if(isset($row['Branch_Code']) && $row['Branch_Code'])
                            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[9px] font-black uppercase">
                                {{ $row['Branch_Code'] }}
                            </span>
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
                        <td colspan="6" class="py-3 px-4 text-right uppercase tracking-widest">Totals</td>
                        <td class="py-3 px-4 text-right">{{ number_format($totalQty, 2) }}</td>
                        <td class="py-3 px-4"></td>
                        <td class="py-3 px-4"></td>
                        <td class="py-3 px-4 text-right text-amber-700">₹{{ number_format($totalAmt, 2) }}</td>
                        <td class="py-3 px-4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </div>
    @endif

</div>

<script>
function filterTable() {
    const q = document.getElementById('purchaseSearch').value.toLowerCase();
    document.querySelectorAll('#purchaseTbody .purchase-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

let sortDir = {};
function sortTable(colIndex) {
    const tbody = document.getElementById('purchaseTbody');
    const rows  = Array.from(tbody.querySelectorAll('tr'));
    const dir   = (sortDir[colIndex] = !(sortDir[colIndex])); // toggle
    rows.sort((a, b) => {
        const aText = a.cells[colIndex]?.textContent.trim() || '';
        const bText = b.cells[colIndex]?.textContent.trim() || '';
        const aNum  = parseFloat(aText.replace(/[₹,]/g,''));
        const bNum  = parseFloat(bText.replace(/[₹,]/g,''));
        if (!isNaN(aNum) && !isNaN(bNum)) return dir ? aNum - bNum : bNum - aNum;
        return dir ? aText.localeCompare(bText) : bText.localeCompare(aText);
    });
    rows.forEach(r => tbody.appendChild(r));
}
</script>
@endsection
