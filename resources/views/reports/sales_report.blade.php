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
                        <h1 class="text-2xl font-black tracking-tight text-white">Sales Report Engine</h1>
                        @if(($totalSyncedRecords ?? 0) > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            {{ number_format($totalSyncedRecords) }} Records Synced
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            Direct / Ready to Sync
                        </span>
                        @endif
                    </div>
                    <p class="text-indigo-200/70 text-xs font-semibold mt-0.5">
                        @if(($totalSyncedRecords ?? 0) > 0)
                        Fast Cloud Database &middot; Last Synced: <span class="text-white font-mono font-bold">{{ $lastSyncTime }}</span>
                        @else
                        Database: <span class="text-white font-mono font-bold">{{ $dbName }}</span> ({{ $dbHost }}:1433)
                        @endif
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
                <span class="text-xs font-black uppercase tracking-wider text-slate-300">SQL Query Window</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Query Presets --}}
                <div class="relative">
                    <select id="queryPresetSelect" onchange="loadPreset(this.value)"
                        class="bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer font-medium">
                        <option value="default">⚡ Default Sales Report (Latest 50)</option>
                        <option value="fy2627">🟢 Current Year Sales (FY 2026-2027)</option>
                        <option value="fy2526">📅 Previous Year Sales (FY 2025-2026)</option>
                        <option value="fy2425">🏛️ Historical Year Sales (FY 2024-2025)</option>
                        <option value="year_comparison">📈 3-Year Growth Comparison (2024-2027)</option>
                        <option value="branch_summary">🏢 Branch Wise Sales Summary</option>
                        <option value="party_summary">👤 Party Wise Sales Summary</option>
                        <option value="item_summary">📦 Item Wise Sales Summary</option>
                        <option value="group_summary">🏷️ Group Wise Sales Summary</option>
                    </select>
                </div>

                {{-- Limit quick selector --}}
                <div class="relative">
                    <select id="limitSelect" onchange="applyLimit(this.value)"
                        class="bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer font-mono">
                        <option value="50">Limit 50</option>
                        <option value="100">Limit 100</option>
                        <option value="500">Limit 500</option>
                        <option value="1000">Limit 1000</option>
                        <option value="5000">Limit 5000</option>
                    </select>
                </div>

                <button type="button" onclick="copyQuery()" 
                    class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold py-1.5 px-3 rounded-xl transition flex items-center gap-1.5" title="Copy SQL">
                    <i class="fas fa-copy"></i>
                    <span class="hidden sm:inline">Copy</span>
                </button>

                <button type="button" onclick="resetQuery()" 
                    class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold py-1.5 px-3 rounded-xl transition flex items-center gap-1.5" title="Reset Default Query">
                    <i class="fas fa-rotate-left"></i>
                    <span class="hidden sm:inline">Reset</span>
                </button>
            </div>
        </div>

        {{-- Textarea SQL Editor --}}
        <div class="p-4 bg-slate-950">
            <textarea id="sqlQueryTextarea" rows="8" spellcheck="false"
                class="w-full bg-slate-950 text-emerald-400 font-mono text-xs p-3 rounded-xl border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none leading-relaxed resize-y selection:bg-indigo-600 selection:text-white"
                placeholder="Write your SELECT SQL query here...">{{ $defaultQuery }}</textarea>
            <div class="flex items-center justify-between text-[11px] text-slate-500 mt-2 px-1">
                <span><i class="fas fa-keyboard mr-1 text-slate-600"></i> Press <strong>Ctrl + Enter</strong> to execute</span>
                <span id="queryLength"></span>
            </div>
        </div>

        {{-- Execution Footer Bar --}}
        <div class="bg-slate-900 px-5 py-3 border-t border-slate-800 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3 text-xs text-slate-400" id="queryStats">
                <i class="fas fa-circle-info text-indigo-400"></i>
                <span>Ready to execute SQL query</span>
            </div>

            <button type="button" onclick="executeQuery()" id="runQueryBtn"
                class="bg-gradient-to-r from-indigo-600 to-indigo-500 hover:from-indigo-500 hover:to-indigo-400 active:scale-95 text-white font-black py-2.5 px-6 rounded-xl text-xs flex items-center gap-2 transition shadow-lg shadow-indigo-600/30">
                <i class="fas fa-play text-[10px]" id="runIcon"></i>
                <span id="runBtnText">Execute Query</span>
            </button>
        </div>
    </div>

    {{-- Error Alert Banner --}}
    <div id="errorAlert" class="hidden rounded-2xl bg-red-500/10 border border-red-500/30 p-4 text-red-300 text-xs">
        <div class="flex items-start gap-3">
            <i class="fas fa-triangle-exclamation text-red-400 text-base mt-0.5"></i>
            <div class="space-y-1">
                <div class="font-bold text-red-200">Execution Failed</div>
                <div id="errorMessage" class="font-mono text-[11px] break-all leading-relaxed"></div>
            </div>
        </div>
    </div>

    {{-- Results Table Section --}}
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden" id="resultsCard">
        
        {{-- Results Header --}}
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold text-sm">
                    <i class="fas fa-table-cells"></i>
                </div>
                <div>
                    <h2 class="text-sm font-black text-slate-800">Query Results</h2>
                    <div class="text-[11px] text-slate-400 font-medium" id="resultMeta">No query executed yet</div>
                </div>
            </div>

            {{-- Table Search Filter --}}
            <div class="flex items-center gap-2">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="tableFilterInput" oninput="filterResults(this.value)" placeholder="Search in results..."
                        class="bg-slate-50 border border-slate-200 text-slate-800 text-xs rounded-xl pl-8 pr-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-48 sm:w-64 font-medium transition">
                </div>
            </div>
        </div>

        {{-- Loading Spinner --}}
        <div id="tableLoading" class="hidden p-16 text-center text-slate-400">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-indigo-500 border-t-transparent mb-3"></div>
            <div class="text-xs font-bold text-slate-600">Executing Query...</div>
            <div class="text-[11px] text-slate-400 mt-1">Please wait while results are processed</div>
        </div>

        {{-- Table Container --}}
        <div id="tableContainer" class="hidden overflow-x-auto max-h-[600px] border-b border-slate-100">
            <table class="w-full text-xs text-left border-collapse" id="resultsTable">
                <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider sticky top-0 z-10 border-b border-slate-200" id="tableHead">
                    {{-- Dynamically generated columns --}}
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700" id="tableBody">
                    {{-- Dynamically generated rows --}}
                </tbody>
            </table>
        </div>

        {{-- Empty State Placeholder --}}
        <div id="emptyPlaceholder" class="p-16 text-center text-slate-400">
            <i class="fas fa-file-lines text-4xl mb-3 block text-slate-200"></i>
            <span class="font-bold text-sm text-slate-600">No data found</span>
            <p class="text-xs mt-1">Execute a query to see your results here.</p>
        </div>

        {{-- Pagination & Footer --}}
        <div id="paginationBar" class="hidden px-6 py-4 bg-slate-50 flex items-center justify-between text-xs font-medium text-slate-500">
            <div id="paginationInfo">Showing 0 of 0 entries</div>
            <div class="flex items-center gap-1" id="paginationButtons"></div>
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
    fy2627: `SELECT 
    branch_name,
    vouch_date,
    vouch_num,
    act_name,
    item_hd_name,
    user_code,
    tot_qty,
    rate,
    calc_net_amt_n,
    group_name,
    customer_name
FROM mssql_sales_records
WHERE financial_year = '2026-2027'
ORDER BY vouch_date DESC
LIMIT 100;`,
    fy2526: `SELECT 
    branch_name,
    vouch_date,
    vouch_num,
    act_name,
    item_hd_name,
    user_code,
    tot_qty,
    rate,
    calc_net_amt_n,
    group_name,
    customer_name
FROM mssql_sales_records
WHERE financial_year = '2025-2026'
ORDER BY vouch_date DESC
LIMIT 100;`,
    fy2425: `SELECT 
    branch_name,
    vouch_date,
    vouch_num,
    act_name,
    item_hd_name,
    user_code,
    tot_qty,
    rate,
    calc_net_amt_n,
    group_name,
    customer_name
FROM mssql_sales_records
WHERE financial_year = '2024-2025'
ORDER BY vouch_date DESC
LIMIT 100;`,
    year_comparison: `SELECT 
    financial_year,
    COUNT(DISTINCT vouch_num) AS total_invoices,
    SUM(tot_qty) AS total_quantity_sold,
    SUM(calc_net_amt_n) AS total_revenue_rs
FROM mssql_sales_records
GROUP BY financial_year
ORDER BY financial_year DESC;`,
    branch_summary: `SELECT 
    financial_year,
    branch_name,
    COUNT(DISTINCT vouch_num) AS total_bills,
    SUM(tot_qty) AS total_qty,
    SUM(calc_net_amt_n) AS total_net_amount
FROM mssql_sales_records
GROUP BY financial_year, branch_name
ORDER BY financial_year DESC, total_net_amount DESC;`,
    party_summary: `SELECT 
    act_name,
    COUNT(DISTINCT vouch_num) AS total_bills,
    SUM(tot_qty) AS total_qty,
    SUM(calc_net_amt_n) AS total_net_amount
FROM mssql_sales_records
GROUP BY act_name
ORDER BY total_net_amount DESC
LIMIT 100;`,
    item_summary: `SELECT 
    item_hd_name,
    user_code,
    group_name,
    SUM(tot_qty) AS total_qty_sold,
    SUM(calc_net_amt_n) AS total_net_amount,
    AVG(rate) AS avg_rate
FROM mssql_sales_records
GROUP BY item_hd_name, user_code, group_name
ORDER BY total_net_amount DESC
LIMIT 100;`,
    group_summary: `SELECT 
    group_name,
    COUNT(DISTINCT item_hd_name) AS total_items,
    SUM(tot_qty) AS total_qty_sold,
    SUM(calc_net_amt_n) AS total_revenue
FROM mssql_sales_records
GROUP BY group_name
ORDER BY total_revenue DESC;`
};

document.addEventListener('DOMContentLoaded', function () {
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

function loadPreset(val) {
    if (PRESETS[val]) {
        document.getElementById('sqlQueryTextarea').value = PRESETS[val];
    }
}

function applyLimit(limit) {
    let query = document.getElementById('sqlQueryTextarea').value;
    if (/LIMIT\s+\d+/i.test(query)) {
        query = query.replace(/LIMIT\s+\d+/i, `LIMIT ${limit}`);
    } else if (/SELECT\s+TOP\s+\d+/i.test(query)) {
        query = query.replace(/SELECT\s+TOP\s+\d+/i, `SELECT TOP ${limit}`);
    } else {
        query = query.trim().replace(/;?\s*$/, ` LIMIT ${limit};`);
    }
    document.getElementById('sqlQueryTextarea').value = query;
}

function resetQuery() {
    document.getElementById('sqlQueryTextarea').value = DEFAULT_QUERY;
    document.getElementById('queryPresetSelect').value = 'default';
}

function copyQuery() {
    const query = document.getElementById('sqlQueryTextarea').value;
    navigator.clipboard.writeText(query).then(() => {
        alert('SQL query copied to clipboard!');
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
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || CSRF_TOKEN;
        const response = await fetch(EXECUTE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ query: query })
        });

        if (response.status === 419) {
            throw new Error('Session expired or CSRF token mismatch. Please refresh the page (F5) and try again.');
        }

        let data = {};
        try {
            data = await response.json();
        } catch (e) {
            throw new Error(`Server returned unexpected response (HTTP ${response.status}). Please check server logs or refresh the page.`);
        }

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
