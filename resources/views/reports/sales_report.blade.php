@extends('layouts.app')

@section('header', 'Sales Report')

@section('content')
<div class="space-y-6" id="salesReportApp">

    {{-- Top Banner / Header Card --}}
    <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-indigo-900 rounded-3xl p-6 text-white shadow-xl shadow-indigo-950/20 relative overflow-hidden border border-indigo-500/20">
        <div class="absolute -right-12 -top-12 w-48 h-48 bg-indigo-500/10 rounded-full blur-2xl"></div>
        <div class="absolute -left-8 -bottom-8 w-36 h-36 bg-purple-500/10 rounded-full blur-2xl"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center shadow-inner text-indigo-300">
                    <i class="fas fa-database text-xl"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-black tracking-tight text-white">Sales Report Query Engine</h1>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            MS SQL Live
                        </span>
                    </div>
                    <p class="text-indigo-200/70 text-xs font-semibold mt-0.5">
                        Direct connection to <span class="text-white font-mono font-bold">{{ $dbName }}</span> ({{ $dbHost }}:1433)
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <form id="exportForm" method="POST" action="{{ route('reports.sales-report.export') }}">
                    @csrf
                    <input type="hidden" name="query" id="exportQueryInput" value="">
                    <button type="button" onclick="submitExport()" id="exportBtn"
                        class="bg-white/10 hover:bg-white/20 border border-white/20 hover:border-white/30 text-white font-bold py-2.5 px-4 rounded-xl text-xs flex items-center gap-2 transition active:scale-95 shadow-sm">
                        <i class="fas fa-file-csv text-emerald-400"></i>
                        <span>Export CSV</span>
                    </button>
                </form>
                <button type="button" onclick="toggleFullScreen()" id="fsBtn"
                    class="bg-white/10 hover:bg-white/20 border border-white/20 hover:border-white/30 text-white font-bold py-2.5 px-3 rounded-xl text-xs flex items-center gap-1.5 transition active:scale-95" title="Toggle Fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- SQL Query Editor Card --}}
    <div class="bg-slate-900 rounded-2xl shadow-xl border border-slate-800 overflow-hidden text-slate-100">
        {{-- Editor Toolbar --}}
        <div class="bg-slate-950/80 px-5 py-3 border-b border-slate-800 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="w-3 h-3 rounded-full bg-red-500/80 inline-block"></span>
                <span class="w-3 h-3 rounded-full bg-amber-500/80 inline-block"></span>
                <span class="w-3 h-3 rounded-full bg-emerald-500/80 inline-block"></span>
                <div class="h-4 w-px bg-slate-800 mx-1"></div>
                <i class="fas fa-terminal text-indigo-400 text-xs"></i>
                <span class="text-xs font-black uppercase tracking-wider text-slate-300">MS SQL Query Window</span>
                <span class="text-[10px] text-slate-500 font-mono hidden sm:inline">(Press <kbd class="px-1.5 py-0.5 bg-slate-800 border border-slate-700 rounded text-slate-300">Ctrl</kbd> + <kbd class="px-1.5 py-0.5 bg-slate-800 border border-slate-700 rounded text-slate-300">Enter</kbd> to run)</span>
            </div>

            <div class="flex items-center gap-2">
                {{-- Quick Presets --}}
                <select id="queryPresetSelect" onchange="loadPresetQuery()"
                    class="bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1 text-xs text-slate-300 font-semibold focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="default">📋 Sale Report Query (Default)</option>
                    <option value="top10">⚡ Top 10 Records</option>
                    <option value="top100">📊 Top 100 Records</option>
                    <option value="branch_summary">🏢 Branch-Wise Sales Summary</option>
                    <option value="party_summary">👥 Party-Wise Sales Summary</option>
                    <option value="item_summary">📦 Item-Wise Sales Summary</option>
                </select>

                {{-- Limit modifier --}}
                <select id="limitSelector" onchange="applyLimitToQuery()"
                    class="bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1 text-xs text-slate-300 font-semibold focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="TOP 50" selected>TOP 50</option>
                    <option value="TOP 10">TOP 10</option>
                    <option value="TOP 100">TOP 100</option>
                    <option value="TOP 500">TOP 500</option>
                    <option value="TOP 1000">TOP 1000</option>
                </select>
            </div>
        </div>

        {{-- Query Textarea --}}
        <div class="relative p-4 bg-slate-900 font-mono text-xs">
            <textarea id="sqlQueryTextarea" rows="9" spellcheck="false"
                class="w-full bg-slate-950/70 border border-slate-800 rounded-xl p-4 font-mono text-[12px] leading-relaxed text-indigo-100 placeholder-slate-600 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-y transition shadow-inner"
                placeholder="Enter SQL SELECT query here...">{{ $defaultQuery }}</textarea>
        </div>

        {{-- Editor Action Bar --}}
        <div class="bg-slate-950 px-5 py-3 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button type="button" onclick="executeQuery()" id="runQueryBtn"
                    class="bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white font-bold py-2 px-5 rounded-xl text-xs flex items-center gap-2 transition shadow-lg shadow-indigo-600/30">
                    <i class="fas fa-play text-[10px]" id="runIcon"></i>
                    <span id="runBtnText">Execute Query</span>
                </button>

                <button type="button" onclick="resetToDefaultQuery()"
                    class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold py-2 px-3.5 rounded-xl text-xs flex items-center gap-1.5 transition">
                    <i class="fas fa-rotate-left text-[11px]"></i>
                    <span>Reset</span>
                </button>

                <button type="button" onclick="copyQuery()"
                    class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold py-2 px-3.5 rounded-xl text-xs flex items-center gap-1.5 transition" title="Copy SQL to Clipboard">
                    <i class="fas fa-copy text-[11px]"></i>
                    <span id="copyText">Copy SQL</span>
                </button>
            </div>

            <div class="flex items-center gap-3 text-[11px] text-slate-400 font-mono">
                <span id="queryStats" class="flex items-center gap-1.5 text-slate-400">
                    <i class="fas fa-circle-check text-emerald-400 text-xs"></i>
                    Ready to execute
                </span>
            </div>
        </div>
    </div>

    {{-- Error Banner (Hidden by default) --}}
    <div id="errorAlert" class="hidden bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl p-4 flex items-start gap-3 text-xs font-semibold shadow-sm">
        <i class="fas fa-triangle-exclamation text-rose-500 text-base mt-0.5 shrink-0"></i>
        <div class="flex-1">
            <div class="font-bold text-rose-900 text-sm mb-0.5">Execution Failed</div>
            <div id="errorMessage" class="font-mono break-all text-[11px] text-rose-700"></div>
        </div>
        <button type="button" onclick="document.getElementById('errorAlert').classList.add('hidden')" class="text-rose-400 hover:text-rose-600">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- Results Container Card --}}
    <div id="resultsCard" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        
        {{-- Results Header & Filter Bar --}}
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/70 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                    <i class="fas fa-table-list text-sm"></i>
                </div>
                <div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-700">Query Results</h3>
                    <div class="text-[11px] text-slate-500 font-medium" id="resultMeta">
                        No query executed yet
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Live Search Filter --}}
                <div class="relative">
                    <input type="text" id="tableFilterInput" onkeyup="filterTableResults()" placeholder="Search in results..."
                        class="w-64 border border-slate-200 rounded-xl py-1.5 pl-8 pr-3 text-xs font-medium text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition bg-white">
                    <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                </div>

                {{-- Page size selector --}}
                <div class="flex items-center gap-1.5 text-xs text-slate-500 font-semibold">
                    <span class="text-[11px]">Rows:</span>
                    <select id="pageSize" onchange="changePageSize()"
                        class="border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none bg-white">
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                        <option value="500">500</option>
                        <option value="999999">All</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Loading Overlay / Skeleton --}}
        <div id="tableLoading" class="hidden p-12 text-center text-slate-500">
            <div class="inline-block w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mb-3"></div>
            <div class="text-xs font-bold uppercase tracking-wider text-slate-600">Querying MS SQL Database...</div>
            <div class="text-[11px] text-slate-400 mt-1 font-mono">Fetching rows from LOGICDBSY</div>
        </div>

        {{-- Table Container --}}
        <div id="tableContainer" class="overflow-x-auto max-h-[65vh] overflow-y-auto content-scroll relative">
            <table class="w-full text-left border-collapse text-xs" id="resultsTable">
                <thead class="sticky top-0 z-20 bg-slate-100 shadow-sm before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-300" id="tableHead">
                    {{-- Dynamically generated columns --}}
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700" id="tableBody">
                    {{-- Dynamically generated rows --}}
                </tbody>
            </table>
        </div>

        {{-- Empty State Placeholder --}}
        <div id="emptyPlaceholder" class="p-12 text-center text-slate-400">
            <i class="fas fa-file-lines text-4xl mb-3 block text-slate-300"></i>
            <span class="font-bold text-sm text-slate-600">Click "Execute Query" above to fetch sales records</span>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                You can modify the SQL query or select preset queries from the toolbar above.
            </p>
        </div>

        {{-- Pagination & Footer Info --}}
        <div id="paginationBar" class="hidden px-6 py-3.5 border-t border-slate-100 bg-slate-50/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
            <div class="text-slate-500 font-medium" id="paginationInfo">
                Showing 0 to 0 of 0 entries
            </div>
            <div class="flex items-center gap-1" id="paginationButtons">
                {{-- Dynamic pagination buttons --}}
            </div>
        </div>

    </div>

</div>

{{-- Scripts --}}
<script>
const EXECUTE_URL = "{{ route('reports.sales-report.execute') }}";
const CSRF_TOKEN = "{{ csrf_token() }}";
const DEFAULT_QUERY = @json($defaultQuery);

let rawColumns = [];
let rawData = [];
let filteredData = [];
let currentPage = 1;
let rowsPerPage = 50;
let sortColumn = null;
let sortDirection = 'asc';

// Presets mapping
const PRESETS = {
    default: DEFAULT_QUERY,
    top10: DEFAULT_QUERY.replace(/TOP\s+\d+/i, 'TOP 10'),
    top100: DEFAULT_QUERY.replace(/TOP\s+\d+/i, 'TOP 100'),
    branch_summary: `SELECT 
    BM.branch_name,
    COUNT(DISTINCT HD.vouch_code) AS total_bills,
    SUM(CASE WHEN TXN.sale_or_sr = 'SR' THEN TXN.Tot_Qty * -1 ELSE TXN.Tot_Qty END) AS total_qty,
    SUM(CASE WHEN TXN.sale_or_sr = 'SR' THEN TXN.Calc_Net_Amt * -1 ELSE TXN.Calc_Net_Amt END) AS total_net_amount
FROM Sl_Txn20252026 AS TXN
INNER JOIN Sl_Head20252026 AS HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0
LEFT JOIN Branch_Mst AS BM ON HD.Branch_Code = BM.Branch_Code
GROUP BY BM.branch_name
ORDER BY total_net_amount DESC;`,
    party_summary: `SELECT TOP 50
    ACT.act_name,
    COUNT(DISTINCT HD.vouch_code) AS total_bills,
    SUM(CASE WHEN TXN.sale_or_sr = 'SR' THEN TXN.Tot_Qty * -1 ELSE TXN.Tot_Qty END) AS total_qty,
    SUM(CASE WHEN TXN.sale_or_sr = 'SR' THEN TXN.Calc_Net_Amt * -1 ELSE TXN.Calc_Net_Amt END) AS total_net_amount
FROM Sl_Txn20252026 AS TXN
INNER JOIN Sl_Head20252026 AS HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0
LEFT JOIN Accounts AS ACT ON HD.cust_code = ACT.act_code
GROUP BY ACT.act_name
ORDER BY total_net_amount DESC;`,
    item_summary: `SELECT TOP 50
    IMD.User_Code,
    IMH.item_hd_name,
    PM.Pack_Name,
    GM1.group_name,
    SUM(CASE WHEN TXN.sale_or_sr = 'SR' THEN TXN.Tot_Qty * -1 ELSE TXN.Tot_Qty END) AS total_qty,
    SUM(CASE WHEN TXN.sale_or_sr = 'SR' THEN TXN.Calc_Net_Amt * -1 ELSE TXN.Calc_Net_Amt END) AS total_net_amount
FROM Sl_Txn20252026 AS TXN
INNER JOIN Sl_Head20252026 AS HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0
LEFT JOIN It_Mst_Det AS IMD ON TXN.Item_Det_Code = IMD.Item_Det_Code
LEFT JOIN It_Mst_Hd AS IMH ON IMD.Item_Hd_Code = IMH.Item_Hd_Code
LEFT JOIN Pack_Mst AS PM ON IMD.Pack_Code = PM.Pack_Code
LEFT JOIN Group_Mst AS GM1 ON IMH.Group_Code = GM1.Group_Code
GROUP BY IMD.User_Code, IMH.item_hd_name, PM.Pack_Name, GM1.group_name
ORDER BY total_net_amount DESC;`
};

document.addEventListener('DOMContentLoaded', function () {
    // Enable Ctrl + Enter to run query
    const textarea = document.getElementById('sqlQueryTextarea');
    textarea.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            executeQuery();
        }
    });

    // Auto execute on page load
    executeQuery();
});

function loadPresetQuery() {
    const val = document.getElementById('queryPresetSelect').value;
    if (PRESETS[val]) {
        document.getElementById('sqlQueryTextarea').value = PRESETS[val];
    }
}

function applyLimitToQuery() {
    const limit = document.getElementById('limitSelector').value;
    let query = document.getElementById('sqlQueryTextarea').value;
    if (/SELECT\s+TOP\s+\d+/i.test(query)) {
        query = query.replace(/SELECT\s+TOP\s+\d+/i, `SELECT ${limit}`);
    } else if (/^SELECT\s+/i.test(query.trim())) {
        query = query.trim().replace(/^SELECT\s+/i, `SELECT ${limit} `);
    }
    document.getElementById('sqlQueryTextarea').value = query;
}

function resetToDefaultQuery() {
    document.getElementById('sqlQueryTextarea').value = DEFAULT_QUERY;
    document.getElementById('queryPresetSelect').value = 'default';
}

function copyQuery() {
    const query = document.getElementById('sqlQueryTextarea').value;
    navigator.clipboard.writeText(query).then(() => {
        const copyText = document.getElementById('copyText');
        copyText.innerText = 'Copied!';
        setTimeout(() => { copyText.innerText = 'Copy SQL'; }, 2000);
    });
}

function submitExport() {
    const query = document.getElementById('sqlQueryTextarea').value;
    document.getElementById('exportQueryInput').value = query;
    document.getElementById('exportForm').submit();
}

function toggleFullScreen() {
    const card = document.getElementById('resultsCard');
    if (!document.fullscreenElement) {
        card.requestFullscreen().catch(err => {
            alert(`Error attempting fullscreen: ${err.message}`);
        });
    } else {
        document.exitFullscreen();
    }
}

async function executeQuery() {
    const query = document.getElementById('sqlQueryTextarea').value.trim();
    if (!query) {
        alert('Please enter a SQL query');
        return;
    }

    // UI Loading state
    const runBtn = document.getElementById('runQueryBtn');
    const runIcon = document.getElementById('runIcon');
    const runBtnText = document.getElementById('runBtnText');
    const loadingDiv = document.getElementById('tableLoading');
    const emptyPlaceholder = document.getElementById('emptyPlaceholder');
    const tableContainer = document.getElementById('tableContainer');
    const paginationBar = document.getElementById('paginationBar');
    const errorAlert = document.getElementById('errorAlert');

    errorAlert.classList.add('hidden');
    emptyPlaceholder.classList.add('hidden');
    tableContainer.classList.add('hidden');
    paginationBar.classList.add('hidden');
    loadingDiv.classList.remove('hidden');

    runBtn.disabled = true;
    runIcon.className = "fas fa-circle-notch fa-spin text-[10px]";
    runBtnText.innerText = "Executing...";

    try {
        const response = await fetch(EXECUTE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ query: query })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            rawColumns = data.columns || [];
            rawData = data.rows || [];
            filteredData = [...rawData];

            document.getElementById('queryStats').innerHTML = `
                <i class="fas fa-check-circle text-emerald-400"></i>
                <span>${data.count} rows in <strong>${data.execution_time_ms} ms</strong></span>
            `;

            document.getElementById('resultMeta').innerText = 
                `${data.count} records retrieved (${rawColumns.length} columns) • ${data.execution_time_ms} ms`;

            if (rawData.length === 0) {
                loadingDiv.classList.add('hidden');
                emptyPlaceholder.classList.remove('hidden');
                emptyPlaceholder.querySelector('span').innerText = 'Query executed successfully, but returned 0 rows.';
            } else {
                loadingDiv.classList.add('hidden');
                tableContainer.classList.remove('hidden');
                paginationBar.classList.remove('hidden');
                
                renderTableHeaders();
                currentPage = 1;
                renderTableData();
            }
        } else {
            throw new Error(data.message || 'Failed to execute query');
        }
    } catch (err) {
        loadingDiv.classList.add('hidden');
        emptyPlaceholder.classList.remove('hidden');
        errorAlert.classList.remove('hidden');
        document.getElementById('errorMessage').innerText = err.message;
        document.getElementById('queryStats').innerHTML = `
            <i class="fas fa-times-circle text-rose-400"></i>
            <span class="text-rose-400">Execution Error</span>
        `;
    } finally {
        runBtn.disabled = false;
        runIcon.className = "fas fa-play text-[10px]";
        runBtnText.innerText = "Execute Query";
    }
}

function renderTableHeaders() {
    const thead = document.getElementById('tableHead');
    let html = '<tr class="border-b border-slate-200 text-[11px] font-black uppercase tracking-wider text-slate-600 select-none">';
    
    // Line index column
    html += '<th class="py-2.5 px-3 text-center bg-slate-200/60 w-12 border-r border-slate-200 text-slate-400 font-mono">#</th>';

    rawColumns.forEach(col => {
        const isSorted = sortColumn === col;
        const icon = isSorted 
            ? (sortDirection === 'asc' ? '<i class="fas fa-arrow-up-short-wide text-indigo-600 ml-1"></i>' : '<i class="fas fa-arrow-down-wide-short text-indigo-600 ml-1"></i>')
            : '<i class="fas fa-sort text-slate-300 ml-1 opacity-0 group-hover:opacity-100 transition-opacity"></i>';
        
        html += `
            <th onclick="sortTable('${col}')" 
                class="py-2.5 px-3 border-r border-slate-200 hover:bg-slate-200/50 cursor-pointer transition whitespace-nowrap group">
                <div class="flex items-center justify-between gap-2">
                    <span>${formatHeaderName(col)}</span>
                    ${icon}
                </div>
            </th>
        `;
    });

    html += '</tr>';
    thead.innerHTML = html;
}

function formatHeaderName(name) {
    return name
        .replace(/_/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase());
}

function sortTable(col) {
    if (sortColumn === col) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = col;
        sortDirection = 'asc';
    }

    filteredData.sort((a, b) => {
        let v1 = a[col] ?? '';
        let v2 = b[col] ?? '';

        // Check numeric
        if (!isNaN(v1) && !isNaN(v2) && v1 !== '' && v2 !== '') {
            return sortDirection === 'asc' ? Number(v1) - Number(v2) : Number(v2) - Number(v1);
        }

        return sortDirection === 'asc' 
            ? String(v1).localeCompare(String(v2))
            : String(v2).localeCompare(String(v1));
    });

    renderTableHeaders();
    renderTableData();
}

function filterTableResults() {
    const q = document.getElementById('tableFilterInput').value.toLowerCase().trim();
    if (!q) {
        filteredData = [...rawData];
    } else {
        filteredData = rawData.filter(row => {
            return rawColumns.some(col => {
                const val = row[col];
                return val !== null && val !== undefined && String(val).toLowerCase().includes(q);
            });
        });
    }

    currentPage = 1;
    renderTableData();
}

function changePageSize() {
    rowsPerPage = parseInt(document.getElementById('pageSize').value);
    currentPage = 1;
    renderTableData();
}

function renderTableData() {
    const tbody = document.getElementById('tableBody');
    const total = filteredData.length;
    const totalPages = Math.max(1, Math.ceil(total / rowsPerPage));

    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * rowsPerPage;
    const end = Math.min(start + rowsPerPage, total);
    const pageRows = filteredData.slice(start, end);

    if (pageRows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${rawColumns.length + 1}" class="p-8 text-center text-slate-400 font-semibold">No records match your search filter.</td></tr>`;
        updatePaginationUI(0, 0, 0, 1);
        return;
    }

    let html = '';
    pageRows.forEach((row, idx) => {
        const rowNum = start + idx + 1;
        html += `<tr class="hover:bg-indigo-50/30 transition-colors text-[11px] font-medium border-b border-slate-100">`;
        html += `<td class="py-2 px-3 text-center text-slate-400 font-mono bg-slate-50/50 border-r border-slate-100">${rowNum}</td>`;

        rawColumns.forEach(col => {
            let val = row[col];
            let displayVal = val;
            let alignClass = 'text-left';
            let fontClass = 'text-slate-700';

            if (val === null || val === undefined) {
                displayVal = '<span class="text-slate-300">—</span>';
            } else if (typeof val === 'number' || (!isNaN(val) && val !== '' && !String(val).startsWith('0') && String(val).length < 15)) {
                // If amount or qty
                const num = parseFloat(val);
                if (col.toLowerCase().includes('amt') || col.toLowerCase().includes('rate') || col.toLowerCase().includes('tax') || col.toLowerCase().includes('discount') || col.toLowerCase().includes('rs')) {
                    displayVal = '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    alignClass = 'text-right';
                    fontClass = 'font-mono text-slate-800 font-semibold';
                } else if (col.toLowerCase().includes('qty') || col.toLowerCase().includes('weight') || col.toLowerCase().includes('cf')) {
                    displayVal = num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    alignClass = 'text-right';
                    fontClass = 'font-mono text-indigo-700 font-bold';
                }
            }

            // Highlights
            if (col.toLowerCase().includes('branch')) {
                fontClass = 'font-bold text-indigo-900';
            } else if (col.toLowerCase().includes('vouch_num') || col.toLowerCase().includes('user_code')) {
                fontClass = 'font-mono font-bold text-slate-900';
            }

            html += `<td class="py-2 px-3 border-r border-slate-100 ${alignClass} ${fontClass} whitespace-nowrap">${displayVal}</td>`;
        });

        html += `</tr>`;
    });

    tbody.innerHTML = html;
    updatePaginationUI(start + 1, end, total, totalPages);
}

function updatePaginationUI(start, end, total, totalPages) {
    const info = document.getElementById('paginationInfo');
    if (total === 0) {
        info.innerText = 'Showing 0 to 0 of 0 entries';
    } else {
        info.innerText = `Showing ${start} to ${end} of ${total} entries`;
    }

    const container = document.getElementById('paginationButtons');
    container.innerHTML = '';

    if (totalPages <= 1) return;

    // Helper to create page button
    const createBtn = (p, label, isActive = false, isDisabled = false) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.innerHTML = label;
        btn.className = `px-2.5 py-1 text-xs font-bold rounded-lg transition active:scale-95 ${
            isActive 
                ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-200' 
                : isDisabled 
                    ? 'text-slate-300 cursor-not-allowed' 
                    : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
        }`;
        if (!isDisabled) {
            btn.onclick = () => {
                currentPage = p;
                renderTableData();
            };
        }
        return btn;
    };

    // Prev
    container.appendChild(createBtn(currentPage - 1, '<i class="fas fa-chevron-left text-[10px]"></i>', false, currentPage === 1));

    // Page Numbers
    let startP = Math.max(1, currentPage - 2);
    let endP = Math.min(totalPages, currentPage + 2);

    if (startP > 1) {
        container.appendChild(createBtn(1, '1'));
        if (startP > 2) {
            const span = document.createElement('span');
            span.innerText = '...';
            span.className = 'px-1 text-slate-400 font-bold';
            container.appendChild(span);
        }
    }

    for (let p = startP; p <= endP; p++) {
        container.appendChild(createBtn(p, p.toString(), p === currentPage));
    }

    if (endP < totalPages) {
        if (endP < totalPages - 1) {
            const span = document.createElement('span');
            span.innerText = '...';
            span.className = 'px-1 text-slate-400 font-bold';
            container.appendChild(span);
        }
        container.appendChild(createBtn(totalPages, totalPages.toString()));
    }

    // Next
    container.appendChild(createBtn(currentPage + 1, '<i class="fas fa-chevron-right text-[10px]"></i>', false, currentPage === totalPages));
}
</script>
@endsection
