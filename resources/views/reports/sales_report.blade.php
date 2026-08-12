@extends('layouts.app')

@section('header', 'Sales Report')

@section('content')
<div class="space-y-6">

    {{-- Header Card --}}
    <div class="bg-gradient-to-br from-indigo-600 to-purple-600 rounded-2xl p-6 text-white shadow-lg shadow-indigo-100 relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full"></div>
        <div class="absolute -left-6 -bottom-6 w-28 h-28 bg-white/5 rounded-full"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-white/20 border border-white/30 flex items-center justify-center shadow-lg">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Party-Wise Product-Wise Sales</h1>
                    <p class="text-indigo-100 text-[11px] font-bold uppercase tracking-widest mt-0.5">
                        Algebra ERP — PartyWiseProductWiseSales API
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="syncSalesData()" id="sync_sales_btn"
                    class="bg-white/10 hover:bg-white/20 border border-white/20 hover:border-white/30 text-white font-bold py-2 px-4 rounded-xl text-xs flex items-center gap-2 transition active:scale-95">
                    <i class="fas fa-rotate mr-0.5" id="sync_icon"></i>
                    <span id="sync_btn_text">Sync Database Now</span>
                </button>
                <div class="flex items-center gap-2 bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-xs font-bold">
                    <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
                    Synced Database
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-filter text-indigo-500 text-sm"></i>
            <h3 class="text-xs font-black text-slate-600 uppercase tracking-widest">Filter Parameters</h3>
        </div>
        <form method="GET" action="{{ route('reports.sales-report') }}" id="salesFilterForm" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">From Date</label>
                    <input type="date" name="from_date"
                        value="{{ $fromDate ?? $defaults['from_date'] }}"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">To Date</label>
                    <input type="date" name="to_date"
                        value="{{ $toDate ?? $defaults['to_date'] }}"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Party Code</label>
                    <input type="text" name="act_code"
                        value="{{ $actCode ?? $defaults['act_code'] }}"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Agent Code</label>
                    <input type="text" name="agent_code"
                        value="{{ $agentCode ?? $defaults['agent_code'] }}"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Item Code</label>
                    <input type="text" name="item"
                        value="{{ $item ?? $defaults['item'] }}"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">User Code</label>
                    <input type="text" name="usercode"
                        value="{{ $usercode ?? $defaults['usercode'] }}"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Branch</label>
                    <input type="text" name="branch"
                        value="{{ $branch ?? $defaults['branch'] }}"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-4 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition">
                </div>
            </div>
            <div class="flex gap-3 mt-4">
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm text-sm flex items-center gap-2 transition active:scale-95">
                    <i class="fas fa-search"></i> Fetch Report
                </button>
                <a href="{{ route('reports.sales-report') }}"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2.5 px-5 rounded-xl text-sm flex items-center gap-2 transition">
                    <i class="fas fa-rotate-left"></i> Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Error Alert --}}
    @if(!empty($error))
    <div class="bg-red-50 border border-red-200 rounded-2xl p-4 text-red-700 flex items-center gap-3 text-sm font-semibold">
        <i class="fas fa-exclamation-circle text-lg"></i>
        <span>{{ $error }}</span>
    </div>
    @endif

    @if($totalDbCount == 0)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center text-slate-500">
        <i class="fas fa-database text-5xl mb-4 block text-slate-300"></i>
        <h3 class="text-base font-black text-slate-700 tracking-tight">Database is Empty</h3>
        <p class="text-xs text-slate-400 mt-1 mb-6 max-w-md mx-auto leading-relaxed">
            Sales data has not been synced to the local database yet. Please click the sync button to populate the database from Algebra ERP.
        </p>
        <button type="button" onclick="syncSalesData()" 
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-xl shadow-md text-sm flex items-center gap-2 transition active:scale-95 mx-auto">
            <i class="fas fa-rotate"></i> Sync Data from ERP
        </button>
    </div>
    @endif

    {{-- Report Grid Card --}}
    @if(request()->hasAny(['from_date', 'to_date', 'act_code', 'agent_code', 'item', 'usercode', 'branch']) && $totalDbCount > 0)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        
        @if(empty($reportData))
        <div class="p-12 text-center text-gray-400">
            <i class="fas fa-folder-open text-4xl mb-3 block text-gray-300"></i>
            <span class="font-bold text-sm">No records found for the selected filter parameters.</span>
        </div>
        @else
        
        @php
            $firstRow = $reportData[0] ?? [];
            $headers = array_keys($firstRow);
            
            if (!function_exists('cleanHeader')) {
                function cleanHeader($key) {
                    $spaced = preg_replace('/(?<!^)[A-Z]/', ' $0', str_replace('_', ' ', $key));
                    return ucwords(strtolower($spaced));
                }
            }
        @endphp

        {{-- Table header bar --}}
        <div class="px-6 py-4 border-b border-gray-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-black text-slate-800 tracking-tight">Sales Records</h3>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Found {{ count($reportData) }} rows in Database</p>
            </div>
            
            <div class="flex items-center gap-3">
                <div class="relative">
                    <input type="text" id="salesSearchInput" onkeyup="searchTable()" placeholder="Local search..." 
                           class="border border-gray-200 rounded-xl py-1.5 pl-9 pr-4 text-xs focus:ring-2 focus:ring-indigo-400 outline-none w-52 transition font-medium">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                </div>
            </div>
        </div>

        {{-- Dynamic Data Table --}}
        <div class="overflow-x-auto max-h-[60vh] overflow-y-auto content-scroll relative">
            <table class="w-full text-left border-collapse text-xs">
                <thead class="sticky top-0 z-20 bg-slate-50 shadow-sm before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-200">
                    <tr>
                        @foreach($headers as $header)
                        <th class="py-3 px-4 font-black text-slate-500 uppercase tracking-widest border-r border-gray-200">
                            {{ cleanHeader($header) }}
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="salesTbody">
                    @foreach($reportData as $row)
                    <tr class="hover:bg-slate-50/50 transition-colors sales-row">
                        @foreach($headers as $header)
                        @php
                            $val = $row[$header] ?? '';
                            $isNumeric = is_numeric($val) && !preg_match('/(code|id|date|phone)/i', $header);
                            $isAmount = $isNumeric && preg_match('/(amount|amt|rate|price|value|gst|tax|val)/i', $header);
                        @endphp
                        <td class="py-3 px-4 border-r border-gray-50 text-gray-700 font-medium {{ $isNumeric ? 'text-right' : '' }}">
                            @if($isNumeric)
                                @if($isAmount)
                                    ₹{{ number_format((float)$val, 2) }}
                                @else
                                    {{ number_format((float)$val, 2) }}
                                @endif
                            @else
                                {{ $val }}
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination Bar --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-slate-50" id="paginationBar">
            <div class="flex items-center gap-3 text-xs text-slate-500 font-medium">
                <span>Show</span>
                <select id="pageSizeSelect" onchange="changePageSize()" class="border border-gray-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none">
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
let allRows = [];
let filteredRows = [];
let currentPage = 1;
let pageSize = 50;

function initPagination() {
    allRows = Array.from(document.querySelectorAll('#salesTbody .sales-row'));
    filteredRows = [...allRows];
    renderPage();
}

function renderPage() {
    const total = filteredRows.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * pageSize;
    const end = Math.min(start + pageSize, total);

    // Show/hide rows
    allRows.forEach(r => r.style.display = 'none');
    filteredRows.forEach((r, i) => {
        if (i >= start && i < end) {
            r.style.display = '';
        }
    });

    // Update info text
    const infoText = total === 0 ? "Showing 0 of 0 rows" : `Showing ${start + 1} to ${end} of ${total} rows`;
    const pageInfo = document.getElementById('pageInfo');
    if (pageInfo) pageInfo.innerText = infoText;

    // Render pagination buttons
    renderPaginationButtons(totalPages);
}

function renderPaginationButtons(totalPages) {
    const container = document.getElementById('paginationBtns');
    if (!container) return;
    container.innerHTML = '';

    if (totalPages <= 1) return;

    // Helper: add btn
    const addBtn = (page, text, active = false, disabled = false) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.innerText = text;
        btn.className = `px-3 py-1.5 text-xs font-bold rounded-lg transition active:scale-95 ${
            active 
                ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-100' 
                : disabled 
                    ? 'text-gray-300 cursor-not-allowed' 
                    : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'
        }`;
        if (!disabled) {
            btn.onclick = () => {
                currentPage = page;
                renderPage();
            };
        }
        container.appendChild(btn);
    };

    // Prev Button
    addBtn(currentPage - 1, 'Previous', false, currentPage === 1);

    // Page numbers with ellipsis logic
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
        addBtn(1, '1');
        if (startPage > 2) {
            const span = document.createElement('span');
            span.innerText = '...';
            span.className = 'px-1 text-gray-400 font-bold';
            container.appendChild(span);
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        addBtn(i, i.toString(), i === currentPage);
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const span = document.createElement('span');
            span.innerText = '...';
            span.className = 'px-1 text-gray-400 font-bold';
            container.appendChild(span);
        }
        addBtn(totalPages, totalPages.toString());
    }

    // Next Button
    addBtn(currentPage + 1, 'Next', false, currentPage === totalPages);
}

function changePageSize() {
    const select = document.getElementById('pageSizeSelect');
    if (!select) return;
    pageSize = parseInt(select.value);
    currentPage = 1;
    renderPage();
}

function searchTable() {
    const input = document.getElementById('salesSearchInput');
    if (!input) return;
    const filter = input.value.toUpperCase();

    filteredRows = allRows.filter(row => {
        return row.innerText.toUpperCase().includes(filter);
    });

    currentPage = 1;
    renderPage();
}

// Auto-run on load
document.addEventListener('DOMContentLoaded', () => {
    initPagination();
});

function syncSalesData() {
    const btn = document.getElementById('sync_sales_btn');
    const text = document.getElementById('sync_btn_text');
    const icon = document.getElementById('sync_icon');

    if (!btn) return;

    btn.disabled = true;
    if (text) text.innerText = 'Syncing...';
    if (icon) icon.className = 'fas fa-rotate fa-spin mr-0.5';

    fetch("{{ route('reports.sales-report.sync') }}", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}"
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert("Error: " + data.message);
            btn.disabled = false;
            if (text) text.innerText = 'Sync Database Now';
            if (icon) icon.className = 'fas fa-rotate mr-0.5';
        }
    })
    .catch(err => {
        alert("Connection Error: " + err.message);
        btn.disabled = false;
        if (text) text.innerText = 'Sync Database Now';
        if (icon) icon.className = 'fas fa-rotate mr-0.5';
    });
}
</script>
@endsection
