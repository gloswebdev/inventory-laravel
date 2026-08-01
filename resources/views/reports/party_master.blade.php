@extends('layouts.app')

@section('header', 'Party Master')

@section('content')
<div class="space-y-6">

    {{-- Header Card --}}
    <div class="bg-gradient-to-br from-indigo-600 to-violet-600 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full"></div>
        <div class="absolute -left-6 -bottom-6 w-28 h-28 bg-white/5 rounded-full"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-white/20 border border-white/30 flex items-center justify-center shadow-lg">
                    <i class="fas fa-users-viewfinder text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Party Master List</h1>
                    <p class="text-indigo-100 text-[11px] font-bold uppercase tracking-widest mt-0.5">
                        Algebra ERP — Live Sync Directory
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('reports.party-master', ['refresh' => 1]) }}"
                   class="bg-white/10 hover:bg-white/20 border border-white/20 rounded-xl px-4 py-2 text-sm font-bold transition flex items-center gap-2">
                    <i class="fas fa-rotate"></i> Refresh Master Data
                </a>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-filter text-indigo-500 text-sm"></i>
            <h3 class="text-xs font-black text-slate-600 uppercase tracking-widest">Filter Directory</h3>
        </div>
        <form method="GET" action="{{ route('reports.party-master') }}" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                {{-- Branch renamed to Group Name Filter --}}
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Group Name</label>
                    <div class="relative">
                        <select name="branch_filter"
                            class="w-full border border-gray-200 rounded-xl py-2.5 px-4 pr-9 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition appearance-none bg-white">
                            <option value="">— All Group Names —</option>
                            @foreach($branchOptions as $opt)
                                <option value="{{ $opt }}" {{ ($branchFilter ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-indigo-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>

                {{-- Agent Filter --}}
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Agent / Salesman</label>
                    <div class="relative">
                        <select name="agent_filter"
                            class="w-full border border-gray-200 rounded-xl py-2.5 px-4 pr-9 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition appearance-none bg-white">
                            <option value="">— All Agents —</option>
                            @foreach($agentOptions as $opt)
                                <option value="{{ $opt }}" {{ ($agentFilter ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-indigo-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm text-sm flex items-center gap-2 transition active:scale-95">
                        <i class="fas fa-search"></i> Apply
                    </button>
                    <a href="{{ route('reports.party-master') }}"
                        class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2.5 px-5 rounded-xl text-sm flex items-center gap-2 transition">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Data Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-slate-800 px-6 py-4 flex items-center justify-between">
            <h3 class="text-white font-bold flex items-center gap-2 text-sm">
                <i class="fas fa-table-cells text-indigo-400"></i>
                Parties List
                <span class="ml-2 px-2 py-0.5 bg-indigo-500/20 text-indigo-300 text-[9px] font-bold rounded border border-indigo-400/30">
                    {{ count($reportData) }} records
                </span>
            </h3>
            <input type="text" id="partySearch" placeholder="Search party details..." onkeyup="filterPartyTable()"
                class="bg-white/10 border border-white/20 text-white placeholder-white/40 text-xs font-medium rounded-xl px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400 w-60">
        </div>

        @if(empty($reportData))
        <div class="py-16 text-center">
            <i class="fas fa-inbox text-4xl text-slate-200 mb-4"></i>
            <p class="text-slate-400 font-bold text-sm">No party records found.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left border-collapse" id="partyTable">
                <thead class="bg-indigo-50 border-b border-indigo-100 text-[8px] font-black text-indigo-700 uppercase tracking-widest whitespace-nowrap">
                    <tr>
                        <th class="py-3 px-4 text-center border-r border-indigo-100">#</th>
                        <th class="py-3 px-4 border-r border-indigo-100">Party Name</th>
                        <th class="py-3 px-4 border-r border-indigo-100">Agent Name</th>
                        <th class="py-3 px-4 border-r border-indigo-100">Agent Code</th>
                        <th class="py-3 px-4 border-r border-indigo-100">Town / City</th>
                        <th class="py-3 px-4">Group Name</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-xs font-medium text-gray-700" id="partyTbody">
                    @foreach($reportData as $index => $row)
                    <tr class="hover:bg-indigo-50/20 transition-colors party-row">
                        <td class="py-2.5 px-4 text-center text-gray-400 font-bold border-r border-gray-50 row-num">{{ $index + 1 }}</td>
                        <td class="py-2.5 px-4 border-r border-gray-50 font-bold text-gray-800">{{ $row['PartyName'] ?: '—' }}</td>
                        <td class="py-2.5 px-4 border-r border-gray-50 text-indigo-600 font-semibold">{{ $row['AgentName'] ?: '—' }}</td>
                        <td class="py-2.5 px-4 border-r border-gray-50 font-mono text-gray-500 text-[10px]">{{ $row['AgentCode'] ?: '—' }}</td>
                        <td class="py-2.5 px-4 border-r border-gray-50 text-gray-600">{{ $row['TownName'] ?: '—' }}</td>
                        <td class="py-2.5 px-4 font-bold text-slate-700">
                            {{ $row['BranchName'] ?: '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-slate-50" id="partyPaginationBar">
            <div class="flex items-center gap-3 text-xs text-slate-500 font-medium">
                <span>Show</span>
                <select id="partyPageSizeSelect" onchange="changePartyPageSize()"
                    class="border border-gray-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none">
                    <option value="25">25</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                    <option value="9999999">All</option>
                </select>
                <span>rows per page</span>
                <span class="ml-2 text-slate-400" id="partyPageInfo"></span>
            </div>
            <div class="flex items-center gap-1" id="partyPaginationBtns"></div>
        </div>
        @endif
    </div>

</div>

<script>
let partyAllRows      = [];
let partyFilteredRows = [];
let partyCurrentPage  = 1;
let partyPageSize     = 50;

function initPartyPagination() {
    partyAllRows      = Array.from(document.querySelectorAll('#partyTbody .party-row'));
    partyFilteredRows = [...partyAllRows];
    renderPartyPage();
}

function renderPartyPage() {
    const total      = partyFilteredRows.length;
    const totalPages = Math.max(1, Math.ceil(total / partyPageSize));
    if (partyCurrentPage > totalPages) partyCurrentPage = totalPages;

    const start = (partyCurrentPage - 1) * partyPageSize;
    const end   = Math.min(start + partyPageSize, total);

    partyAllRows.forEach(r => r.style.display = 'none');
    partyFilteredRows.forEach((r, i) => {
        r.style.display = (i >= start && i < end) ? '' : 'none';
        const numCell = r.querySelector('.row-num');
        if (numCell) numCell.textContent = i + 1;
    });

    const info = document.getElementById('partyPageInfo');
    if (info) info.textContent = total > 0 ? `Showing ${start+1}–${end} of ${total}` : 'No results';

    renderPartyPaginationBtns(totalPages);
}

function renderPartyPaginationBtns(totalPages) {
    const container = document.getElementById('partyPaginationBtns');
    if (!container) return;

    const base     = 'px-3 py-1.5 rounded-lg text-xs font-bold transition focus:outline-none';
    const active   = 'bg-indigo-500 text-white shadow';
    const inactive = 'bg-white border border-gray-200 text-slate-600 hover:bg-indigo-50 hover:border-indigo-300';
    const disabled = 'bg-white border border-gray-100 text-gray-300 cursor-not-allowed';

    let html = partyCurrentPage > 1
        ? `<button class="${base} ${inactive}" onclick="partyGoToPage(${partyCurrentPage-1})"><i class="fas fa-chevron-left"></i></button>`
        : `<button class="${base} ${disabled}" disabled><i class="fas fa-chevron-left"></i></button>`;

    const ws = 5;
    let sp = Math.max(1, partyCurrentPage - Math.floor(ws/2));
    let ep = Math.min(totalPages, sp + ws - 1);
    if (ep - sp < ws - 1) sp = Math.max(1, ep - ws + 1);

    if (sp > 1) {
        html += `<button class="${base} ${inactive}" onclick="partyGoToPage(1)">1</button>`;
        if (sp > 2) html += `<span class="px-2 text-gray-400 text-xs font-bold">…</span>`;
    }
    for (let p = sp; p <= ep; p++)
        html += `<button class="${base} ${p === partyCurrentPage ? active : inactive}" onclick="partyGoToPage(${p})">${p}</button>`;
    if (ep < totalPages) {
        if (ep < totalPages - 1) html += `<span class="px-2 text-gray-400 text-xs font-bold">…</span>`;
        html += `<button class="${base} ${inactive}" onclick="partyGoToPage(${totalPages})">${totalPages}</button>`;
    }

    html += partyCurrentPage < totalPages
        ? `<button class="${base} ${inactive}" onclick="partyGoToPage(${partyCurrentPage+1})"><i class="fas fa-chevron-right"></i></button>`
        : `<button class="${base} ${disabled}" disabled><i class="fas fa-chevron-right"></i></button>`;

    container.innerHTML = html;
}

function partyGoToPage(page) {
    partyCurrentPage = page;
    renderPartyPage();
    document.getElementById('partyTable')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function changePartyPageSize() {
    partyPageSize    = parseInt(document.getElementById('partyPageSizeSelect').value);
    partyCurrentPage = 1;
    renderPartyPage();
}

function filterPartyTable() {
    const q = document.getElementById('partySearch').value.toLowerCase().trim();
    partyFilteredRows = q
        ? partyAllRows.filter(r => r.textContent.toLowerCase().includes(q))
        : [...partyAllRows];
    partyCurrentPage = 1;
    renderPartyPage();
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('partyTbody')) initPartyPagination();
});
</script>
@endsection
