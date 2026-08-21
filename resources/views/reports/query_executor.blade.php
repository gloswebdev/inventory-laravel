@extends('layouts.app')

@section('content')
<div class="space-y-6">

    {{-- TOP BANNER / HEADER --}}
    <div class="rounded-3xl p-6 sm:p-8 text-white relative overflow-hidden shadow-xl"
         style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);">
        {{-- Background decorative shapes --}}
        <div class="absolute -right-10 -bottom-10 w-64 h-64 rounded-full bg-indigo-500/10 blur-3xl pointer-events-none"></div>
        <div class="absolute right-1/3 -top-10 w-48 h-48 rounded-full bg-violet-400/10 blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-amber-300 text-lg shadow-inner">
                        <i class="fas fa-terminal"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black tracking-tight">MSSQL Query Executor & Bridge</h1>
                        <p class="text-xs text-indigo-200 font-medium">Run live SQL queries on Local PC MSSQL database & import data into InvoFlow</p>
                    </div>
                </div>
            </div>

            {{-- LIVE BRIDGE STATUS & CONFIG --}}
            <div class="flex flex-wrap items-center gap-3">
                <div class="px-4 py-2 rounded-2xl backdrop-blur-md border flex items-center gap-3 {{ $isAgentOnline ? 'bg-emerald-500/15 border-emerald-400/30 text-emerald-300' : 'bg-amber-500/15 border-amber-400/30 text-amber-300' }}">
                    <span class="relative flex h-3 w-3">
                        @if($isAgentOnline)
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        @else
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                        @endif
                    </span>
                    <div class="text-xs">
                        <div class="font-black flex items-center gap-1.5">
                            {{ $isAgentOnline ? 'Local Bridge Connected' : 'Bridge Agent Offline' }}
                        </div>
                        <div class="text-[10px] opacity-80">
                            @if($isAgentOnline)
                                MSSQL Active &middot; Last ping {{ $lastSeenDiff }}
                            @else
                                Last seen: {{ $lastSeenDiff }} &middot; Run agent on PC
                            @endif
                        </div>
                    </div>
                </div>

                <button onclick="document.getElementById('bridgeSettingsModal').classList.remove('hidden')"
                    class="px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/10 text-white text-xs font-black transition flex items-center gap-2">
                    <i class="fas fa-plug text-indigo-300"></i>
                    <span>Bridge Setup</span>
                </button>
            </div>
        </div>
    </div>

    {{-- FLASH MESSAGES --}}
    @if(session('system_success'))
    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-2">
            <i class="fas fa-circle-check text-emerald-600 text-sm"></i>
            <span>{{ session('system_success') }}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fas fa-times"></i></button>
    </div>
    @endif

    {{-- MAIN 2-COLUMN GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- LEFT 8 COLS: SQL QUERY EDITOR --}}
        <div class="lg:col-span-8 space-y-6">

            <div class="rounded-3xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-50 flex flex-wrap items-center justify-between gap-3 bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-code text-indigo-600 text-sm"></i>
                        <span class="text-xs font-black text-slate-800 uppercase tracking-wider">SQL Query Console</span>
                    </div>

                    {{-- SAVED PRESET DROPDOWN --}}
                    <div class="flex items-center gap-2">
                        <select id="savedQuerySelect" onchange="loadSavedQuery(this.value)"
                            class="text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl px-3 py-1.5 focus:outline-none focus:border-indigo-500">
                            <option value="">⚡ Load Saved Preset...</option>
                            @foreach($savedQueries as $sq)
                            <option value="{{ $sq->id }}">
                                {{ $sq->is_favorite ? '⭐ ' : '' }}{{ $sq->title }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="p-6">
                    <form id="queryForm" onsubmit="executeMssqlQuery(event)">
                        @csrf
                        <div class="relative rounded-2xl overflow-hidden border border-slate-800 bg-[#0f172a] shadow-inner mb-4">
                            <div class="px-4 py-2 bg-[#1e293b] border-b border-slate-800 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                                <span class="flex items-center gap-2"><i class="fas fa-database text-amber-400"></i> MSSQL Server (Local)</span>
                                <span class="text-[10px] text-slate-400">Supports SELECT, WITH, EXEC</span>
                            </div>
                            <textarea id="sqlQuery" name="query_sql" rows="7" spellcheck="false"
                                class="w-full bg-transparent text-emerald-400 font-mono text-xs p-4 focus:outline-none resize-y"
                                placeholder="SELECT TOP 50 vouch_date, vouch_num, act_name, item_hd_name, tot_qty, calc_net_amt&#10;FROM Tran_Sale_Header&#10;ORDER BY vouch_date DESC">{{ $activeJob ? $activeJob->query_sql : "SELECT TOP 50 vouch_date, vouch_num, act_name, item_hd_name, tot_qty, calc_net_amt \nFROM Tran_Sale_Header \nORDER BY vouch_date DESC" }}</textarea>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="openSaveModal()"
                                    class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold transition flex items-center gap-1.5">
                                    <i class="fas fa-bookmark text-amber-500"></i>
                                    Save as Preset
                                </button>
                                <button type="button" onclick="document.getElementById('sqlQuery').value=''"
                                    class="px-3 py-2.5 rounded-xl border border-slate-200 text-slate-400 hover:text-red-600 hover:bg-red-50 text-xs font-bold transition">
                                    <i class="fas fa-trash-can"></i>
                                </button>
                            </div>

                            <button type="submit" id="runQueryBtn"
                                class="px-6 py-2.5 rounded-xl font-black text-xs text-white transition-all hover:opacity-95 active:scale-95 shadow-lg shadow-indigo-600/25 flex items-center gap-2"
                                style="background: linear-gradient(135deg, #4f46e5, #4338ca);">
                                <i class="fas fa-bolt text-amber-300"></i>
                                <span>Execute on Local MSSQL</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- LIVE EXECUTION STATUS SPINNER --}}
            <div id="executionStatusCard" class="hidden rounded-3xl border border-indigo-100 bg-indigo-50/70 p-6 text-center shadow-sm">
                <div class="flex flex-col items-center justify-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-xl shadow-lg shadow-indigo-600/30">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <div>
                        <div class="text-sm font-black text-slate-800" id="statusMainText">Dispatched to Local Bridge...</div>
                        <div class="text-xs text-slate-500 mt-0.5" id="statusSubText">Local Python Agent running query on MSSQL (<span id="timerSpan">0s</span>)</div>
                    </div>
                </div>
            </div>

        </div>

        {{-- RIGHT 4 COLS: PRESETS & INFO --}}
        <div class="lg:col-span-4 space-y-6">

            {{-- SAVED PRESETS LIST --}}
            <div class="rounded-3xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-bookmark text-amber-500 text-sm"></i>
                        <span class="text-xs font-black text-slate-800 uppercase tracking-wider">Saved Presets ({{ $savedQueries->count() }})</span>
                    </div>
                </div>

                <div class="p-4 divide-y divide-slate-100 max-h-72 overflow-y-auto">
                    @forelse($savedQueries as $sq)
                    <div class="py-2.5 flex items-center justify-between gap-2 group">
                        <div class="min-w-0 cursor-pointer" onclick="loadSavedQueryById({{ $sq->id }})">
                            <div class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 truncate flex items-center gap-1.5">
                                @if($sq->is_favorite) <span class="text-amber-500">★</span> @endif
                                {{ $sq->title }}
                            </div>
                            @if($sq->target_table)
                            <div class="text-[10px] text-slate-400 truncate">Target: <code class="text-indigo-500 font-bold">{{ $sq->target_table }}</code></div>
                            @endif
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <button onclick="loadSavedQueryById({{ $sq->id }})" title="Load SQL"
                                class="w-7 h-7 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">
                                <i class="fas fa-arrow-left"></i>
                            </button>
                            <form method="POST" action="{{ route('reports.query-executor.delete-template', $sq->id) }}" onsubmit="return confirm('Delete preset?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Delete" class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-red-50 text-slate-400 hover:text-red-600 flex items-center justify-center text-xs">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    @empty
                    <div class="py-6 text-center text-xs text-slate-400 italic">
                        Koi preset saved nahi hai.<br>Upar "Save as Preset" se save karein.
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- RECENT EXECUTIONS --}}
            <div class="rounded-3xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-history text-slate-500 text-sm"></i>
                        <span class="text-xs font-black text-slate-800 uppercase tracking-wider">Recent Executions</span>
                    </div>
                </div>
                <div class="p-4 divide-y divide-slate-100 max-h-60 overflow-y-auto">
                    @forelse($recentJobs as $job)
                    <div class="py-2 flex items-center justify-between gap-2 text-xs">
                        <div class="min-w-0">
                            <div class="font-bold text-slate-700 truncate font-mono text-[11px]">{{ Str::limit($job->query_sql, 40) }}</div>
                            <div class="text-[10px] text-slate-400 flex items-center gap-2">
                                <span>{{ $job->created_at->diffForHumans() }}</span>
                                @if($job->status === 'completed')
                                <span class="text-emerald-600 font-bold">&bull; {{ $job->row_count }} rows ({{ $job->execution_seconds }}s)</span>
                                @elseif($job->status === 'failed')
                                <span class="text-red-500 font-bold">&bull; Failed</span>
                                @else
                                <span class="text-amber-500 font-bold">&bull; {{ ucfirst($job->status) }}</span>
                                @endif
                            </div>
                        </div>
                        <button onclick="loadJobResult('{{ $job->job_token }}')" class="px-2 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-black">
                            View
                        </button>
                    </div>
                    @empty
                    <div class="py-4 text-center text-xs text-slate-400 italic">No recent executions</div>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

    {{-- RESULTS & SMART IMPORT SECTION --}}
    <div id="resultsContainer" class="hidden space-y-4">
        <div class="rounded-3xl border border-slate-100 bg-white shadow-sm overflow-hidden">

            {{-- RESULTS ACTION BAR --}}
            <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4 bg-slate-50/70">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-sm font-black">
                            <i class="fas fa-table-cells"></i>
                        </div>
                        <div>
                            <div class="text-sm font-black text-slate-800">Query Results</div>
                            <div class="text-[10px] text-slate-500">
                                <span id="resRowCount" class="font-bold text-slate-700">0</span> rows returned in <span id="resTime">0s</span>
                            </div>
                        </div>
                    </div>

                    <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

                    {{-- SELECT ALL / COUNT CHIP --}}
                    <div class="px-3 py-1.5 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-black flex items-center gap-2">
                        <span id="selectedCountBadge">0</span> rows selected
                    </div>
                </div>

                {{-- SEARCH & EXPORT --}}
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-2.5 text-xs text-slate-400"></i>
                        <input type="text" id="gridSearchInput" onkeyup="filterResultGrid()" placeholder="Filter in results..."
                            class="text-xs bg-white border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 focus:outline-none focus:border-indigo-500 w-48">
                    </div>

                    <a href="#" id="csvExportBtn" class="px-3 py-1.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold transition flex items-center gap-1.5">
                        <i class="fas fa-file-csv text-emerald-600"></i> Export CSV
                    </a>
                </div>
            </div>

            {{-- SMART DB IMPORT BAR --}}
            <div class="px-6 py-3.5 bg-gradient-to-r from-indigo-50/90 via-violet-50/90 to-purple-50/90 border-b border-indigo-100 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs font-black text-indigo-900 flex items-center gap-1.5">
                        <i class="fas fa-database text-indigo-600"></i> Import Target:
                    </span>

                    <select id="importTargetTable" class="text-xs font-bold text-slate-700 bg-white border border-indigo-200 rounded-xl px-3 py-1.5 focus:outline-none focus:border-indigo-500">
                        @foreach($targetTables as $tblKey => $tblMeta)
                        <option value="{{ $tblKey }}">{{ $tblMeta['label'] }}</option>
                        @endforeach
                    </select>

                    <label class="flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer font-semibold select-none">
                        <input type="checkbox" id="truncateOldCheck" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Truncate table pehle (Overwrite all)</span>
                    </label>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" onclick="executeDatabaseImport()" id="importBtn"
                        class="px-5 py-2 rounded-xl font-black text-xs text-white transition-all hover:opacity-90 active:scale-95 shadow-md shadow-emerald-600/20 flex items-center gap-2"
                        style="background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fas fa-cloud-arrow-down"></i>
                        <span>Insert Selected to Database</span>
                    </button>
                </div>
            </div>

            {{-- DYNAMIC DATA GRID TABLE --}}
            <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
                <table class="w-full text-left text-xs divide-y divide-slate-100" id="resultTable">
                    <thead class="bg-slate-50 text-[11px] font-black text-slate-600 uppercase tracking-wider sticky top-0 z-10 shadow-sm">
                        <tr id="tableHeaderRow">
                            <th class="p-3 w-10 text-center bg-slate-50">
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAllRows(this.checked)"
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            </th>
                            {{-- Dynamic TH injected via JS --}}
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 bg-white" id="tableBody">
                        {{-- Dynamic TR injected via JS --}}
                    </tbody>
                </table>
            </div>

            {{-- PAGINATION TOOLBAR --}}
            <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
                <div class="flex items-center gap-2">
                    <span>Showing <strong id="pageStartNum" class="text-slate-700">0</strong> - <strong id="pageEndNum" class="text-slate-700">0</strong> of <strong id="totalRowsNum" class="text-slate-700">0</strong> rows</span>
                    <span class="text-slate-300">&bull;</span>
                    <select id="pageSizeSelect" onchange="changePageSize(this.value)" class="text-xs bg-white border border-slate-200 rounded-lg px-2 py-1 focus:outline-none">
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                        <option value="250">250 per page</option>
                        <option value="500">500 per page</option>
                        <option value="all">All rows</option>
                    </select>
                </div>
                <div class="flex items-center gap-1.5" id="paginationButtons">
                    <button onclick="goToPage(1)" class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40" id="firstPageBtn">First</button>
                    <button onclick="goToPage(currentPage - 1)" class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40" id="prevPageBtn">Prev</button>
                    <span class="px-3 py-1 font-bold text-slate-700">Page <span id="currentPageSpan">1</span> / <span id="totalPagesSpan">1</span></span>
                    <button onclick="goToPage(currentPage + 1)" class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40" id="nextPageBtn">Next</button>
                    <button onclick="goToPage(totalPages)" class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40" id="lastPageBtn">Last</button>
                </div>
            </div>

        </div>
    </div>

</div>

{{-- MODAL: SAVE AS PRESET --}}
<div id="savePresetModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-slate-100 animate-in fade-in zoom-in duration-150">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2 text-sm font-black text-slate-800">
                <i class="fas fa-bookmark text-amber-500"></i>
                <span>Save Query Preset</span>
            </div>
            <button onclick="document.getElementById('savePresetModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('reports.query-executor.save-template') }}">
            @csrf
            <input type="hidden" name="query_sql" id="modalSqlInput">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Preset Title:</label>
                    <input type="text" name="title" required placeholder="e.g. Today's Sales Invoices"
                        class="w-full text-xs border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Target Table (Optional):</label>
                    <select name="target_table" class="w-full text-xs border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500">
                        <option value="">-- None / Custom --</option>
                        @foreach($targetTables as $tblKey => $tblMeta)
                        <option value="{{ $tblKey }}">{{ $tblMeta['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Description (Optional):</label>
                    <input type="text" name="description" placeholder="Notes for this query..."
                        class="w-full text-xs border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500">
                </div>

                <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_favorite" value="1" class="rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                    <span>Mark as Favorite (Shows at top)</span>
                </label>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" onclick="document.getElementById('savePresetModal').classList.add('hidden')"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 rounded-xl font-black text-xs text-white bg-indigo-600 hover:bg-indigo-700">Save Preset</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL: BRIDGE SETUP & SECRET TOKEN --}}
<div id="bridgeSettingsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-xl w-full shadow-2xl border border-slate-100 animate-in fade-in zoom-in duration-150 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2 text-sm font-black text-slate-800">
                <i class="fas fa-plug text-indigo-600"></i>
                <span>Local Python Bridge Configuration</span>
            </div>
            <button onclick="document.getElementById('bridgeSettingsModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="space-y-4 text-xs">
            <div class="p-4 bg-indigo-50 rounded-2xl border border-indigo-100 text-indigo-900">
                <div class="font-bold mb-1 flex items-center gap-1.5"><i class="fas fa-circle-info text-indigo-600"></i> Python Bridge Setup Guide:</div>
                <ol class="list-decimal list-inside space-y-1 text-[11px] text-indigo-800">
                    <li>Local PC folder <code class="bg-white px-1.5 py-0.5 rounded font-mono text-indigo-700">local_bridge/</code> me jao.</li>
                    <li><code class="bg-white px-1.5 py-0.5 rounded font-mono text-indigo-700">bridge_config.json</code> me apna MSSQL Database aur Token enter karo.</li>
                    <li><strong class="text-indigo-950">start_bridge.bat</strong> double-click karo ya Task Scheduler me setup karo!</li>
                </ol>
            </div>

            <form method="POST" action="{{ route('reports.query-executor.bridge-settings') }}">
                @csrf
                <div class="mb-4">
                    <label class="block font-bold text-slate-700 mb-1">Bridge Secret Token (Must match in bridge_config.json):</label>
                    <div class="flex items-center gap-2">
                        <input type="text" name="bridge_secret_token" id="bridgeSecretTokenInput" value="{{ $bridgeToken }}" required
                            class="flex-1 font-mono text-xs border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500">
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('bridgeSecretTokenInput').value); alert('Token copied!');"
                            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl text-xs">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 font-mono text-[11px] text-slate-600 mb-4">
                    <div><strong>Hostinger API Base URL:</strong></div>
                    <div class="text-indigo-600 font-bold">{{ url('/api/v1/bridge') }}</div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" onclick="document.getElementById('bridgeSettingsModal').classList.add('hidden')"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100">Close</button>
                    <button type="submit"
                        class="px-5 py-2 rounded-xl font-black text-xs text-white bg-indigo-600 hover:bg-indigo-700">Save Token</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- JAVASCRIPT LOGIC --}}
<script>
let currentJobToken = null;
let pollTimer = null;
let elapsedSeconds = 0;
let elapsedTimer = null;
let currentResultRows = [];
let filteredResultRows = [];
let currentResultColumns = [];
let selectedRowIndexes = new Set();

// Pagination State
let currentPage = 1;
let pageSize = 50;
let totalPages = 1;

const DISPATCH_URL = "{{ route('reports.query-executor.dispatch') }}";
const STATUS_BASE_URL = "{{ url('reports/query-executor/status') }}";
const IMPORT_URL = "{{ route('reports.query-executor.import') }}";
const EXPORT_BASE_URL = "{{ url('reports/query-executor/export-csv') }}";

// JSON map of all presets
const savedQueriesData = @json($savedQueries->keyBy('id'));

// Load preset query from dropdown or card
function loadSavedQuery(id) {
    if (!id || !savedQueriesData[id]) return;
    const sq = savedQueriesData[id];
    document.getElementById('sqlQuery').value = sq.query_sql;
    if (sq.target_table) {
        document.getElementById('importTargetTable').value = sq.target_table;
    }
}

function loadSavedQueryById(id) {
    const select = document.getElementById('savedQuerySelect');
    if (select) select.value = id;
    loadSavedQuery(id);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function openSaveModal() {
    const sql = document.getElementById('sqlQuery').value.trim();
    if (!sql) {
        alert('Please enter a SQL query first.');
        return;
    }
    document.getElementById('modalSqlInput').value = sql;
    document.getElementById('savePresetModal').classList.remove('hidden');
}

// Execute Query
async function executeMssqlQuery(e) {
    e.preventDefault();
    const sql = document.getElementById('sqlQuery').value.trim();
    if (!sql) {
        alert('Please enter a SQL query.');
        return;
    }

    const btn = document.getElementById('runQueryBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Dispatching...';

    // Show status card
    document.getElementById('executionStatusCard').classList.remove('hidden');
    document.getElementById('statusMainText').textContent = 'Dispatched to Local Bridge...';
    document.getElementById('statusSubText').innerHTML = 'Local Python Agent running query on MSSQL (<span id="timerSpan">0s</span>)';
    document.getElementById('resultsContainer').classList.add('hidden');

    elapsedSeconds = 0;
    clearInterval(elapsedTimer);
    elapsedTimer = setInterval(() => {
        elapsedSeconds++;
        const el = document.getElementById('timerSpan');
        if (el) el.textContent = elapsedSeconds + 's';
    }, 1000);

    try {
        const res = await fetch(DISPATCH_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ query_sql: sql })
        });

        const data = await res.json();
        if (!data.success) {
            clearInterval(elapsedTimer);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bolt text-amber-300"></i> Execute on Local MSSQL';
            document.getElementById('executionStatusCard').classList.add('hidden');
            alert('Error: ' + data.message);
            return;
        }

        currentJobToken = data.job_token;
        startPollingJobStatus(currentJobToken);

    } catch (err) {
        clearInterval(elapsedTimer);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-bolt text-amber-300"></i> Execute on Local MSSQL';
        document.getElementById('executionStatusCard').classList.add('hidden');
        alert('Network Error: ' + err.message);
    }
}

// Poll Job Status
function startPollingJobStatus(token) {
    clearInterval(pollTimer);
    let attempts = 0;

    pollTimer = setInterval(async () => {
        attempts++;
        if (attempts > 300) { // 5 minutes timeout for huge datasets (39k+ rows)
            clearInterval(pollTimer);
            clearInterval(elapsedTimer);
            resetExecutionBtn();
            alert('Query execution timed out. Please check if Python Bridge Agent is running on PC.');
            return;
        }

        try {
            const res = await fetch(`${STATUS_BASE_URL}/${token}`);
            const data = await res.json();

            if (data.status === 'running') {
                document.getElementById('statusMainText').textContent = 'MSSQL Executing & Uploading Data...';
            } else if (data.status === 'completed') {
                clearInterval(pollTimer);
                clearInterval(elapsedTimer);
                resetExecutionBtn();
                document.getElementById('executionStatusCard').classList.add('hidden');
                renderResults(data);
            } else if (data.status === 'failed') {
                clearInterval(pollTimer);
                clearInterval(elapsedTimer);
                resetExecutionBtn();
                document.getElementById('executionStatusCard').classList.add('hidden');
                alert('MSSQL Execution Error:\n' + data.error_message);
            }
        } catch (e) {
            console.error('Polling error:', e);
        }
    }, 1000);
}

function resetExecutionBtn() {
    const btn = document.getElementById('runQueryBtn');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-bolt text-amber-300"></i> Execute on Local MSSQL';
}

// Load past job result directly
async function loadJobResult(token) {
    document.getElementById('executionStatusCard').classList.remove('hidden');
    document.getElementById('statusMainText').textContent = 'Loading query result...';

    try {
        const res = await fetch(`${STATUS_BASE_URL}/${token}`);
        const data = await res.json();

        if (data.status === 'completed') {
            document.getElementById('executionStatusCard').classList.add('hidden');
            renderResults(data);
            window.scrollTo({ top: document.getElementById('resultsContainer').offsetTop - 20, behavior: 'smooth' });
        } else if (data.status === 'pending' || data.status === 'running') {
            // Still in progress, start polling seamlessly!
            startPollingJobStatus(token);
        } else {
            document.getElementById('executionStatusCard').classList.add('hidden');
            alert('Job ' + data.status + (data.error_message ? ': ' + data.error_message : ''));
        }
    } catch (e) {
        document.getElementById('executionStatusCard').classList.add('hidden');
        alert('Error loading job: ' + e.message);
    }
}

// Render dynamic results
function renderResults(data) {
    currentResultRows = data.rows || [];
    filteredResultRows = currentResultRows;
    currentResultColumns = data.columns || (currentResultRows.length > 0 ? Object.keys(currentResultRows[0]) : []);
    currentJobToken = data.job_token;

    document.getElementById('resRowCount').textContent = currentResultRows.length.toLocaleString();
    document.getElementById('resTime').textContent = (data.execution_seconds || 0) + 's';
    document.getElementById('csvExportBtn').href = `${EXPORT_BASE_URL}/${currentJobToken}`;

    // Render table headers
    const headerRow = document.getElementById('tableHeaderRow');
    headerRow.innerHTML = `
        <th class="p-3 w-10 text-center bg-slate-50">
            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAllRows(this.checked)" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
        </th>
    `;
    currentResultColumns.forEach(col => {
        const th = document.createElement('th');
        th.className = 'p-3 text-[11px] font-black text-slate-600 bg-slate-50 whitespace-nowrap';
        th.textContent = col;
        headerRow.appendChild(th);
    });

    // Select all rows by default
    selectedRowIndexes.clear();
    for (let i = 0; i < currentResultRows.length; i++) {
        selectedRowIndexes.add(i);
    }
    updateSelectedCount();

    currentPage = 1;
    renderCurrentPage();
    document.getElementById('resultsContainer').classList.remove('hidden');
}

function renderCurrentPage() {
    const total = filteredResultRows.length;
    document.getElementById('totalRowsNum').textContent = total.toLocaleString();

    if (pageSize === 'all') {
        totalPages = 1;
        currentPage = 1;
    } else {
        totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
    }

    const startIdx = pageSize === 'all' ? 0 : (currentPage - 1) * pageSize;
    const endIdx = pageSize === 'all' ? total : Math.min(startIdx + pageSize, total);

    document.getElementById('pageStartNum').textContent = total === 0 ? 0 : (startIdx + 1).toLocaleString();
    document.getElementById('pageEndNum').textContent = endIdx.toLocaleString();
    document.getElementById('currentPageSpan').textContent = currentPage;
    document.getElementById('totalPagesSpan').textContent = totalPages;

    document.getElementById('firstPageBtn').disabled = currentPage === 1;
    document.getElementById('prevPageBtn').disabled = currentPage === 1;
    document.getElementById('nextPageBtn').disabled = currentPage === totalPages;
    document.getElementById('lastPageBtn').disabled = currentPage === totalPages;

    const pageRows = filteredResultRows.slice(startIdx, endIdx);
    renderTableRows(pageRows, startIdx);
}

function renderTableRows(pageRows, startOffset) {
    const tbody = document.getElementById('tableBody');
    tbody.innerHTML = '';

    pageRows.forEach((row, localIdx) => {
        const realIdx = startOffset + localIdx;
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50/80 transition-colors border-b border-slate-100';

        let cellsHtml = `
            <td class="p-3 text-center">
                <input type="checkbox" class="row-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    data-index="${realIdx}" ${selectedRowIndexes.has(realIdx) ? 'checked' : ''} onchange="toggleSingleRow(${realIdx}, this.checked)">
            </td>
        `;

        currentResultColumns.forEach(col => {
            let val = row[col];
            if (val === null || val === undefined) {
                val = '<span class="text-slate-300 italic">NULL</span>';
            } else if (typeof val === 'object') {
                val = `<span class="font-mono text-[10px] text-slate-500 truncate max-w-xs block">${JSON.stringify(val)}</span>`;
            } else {
                val = `<span class="truncate max-w-xs block" title="${String(val)}">${String(val)}</span>`;
            }
            cellsHtml += `<td class="p-3 text-slate-700 whitespace-nowrap">${val}</td>`;
        });

        tr.innerHTML = cellsHtml;
        tbody.appendChild(tr);
    });

    const selectAllCb = document.getElementById('selectAllCheckbox');
    if (selectAllCb) {
        selectAllCb.checked = selectedRowIndexes.size === currentResultRows.length && currentResultRows.length > 0;
    }
}

function changePageSize(val) {
    pageSize = val === 'all' ? 'all' : parseInt(val, 10);
    currentPage = 1;
    renderCurrentPage();
}

function goToPage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderCurrentPage();
}

function toggleSelectAllRows(checked) {
    if (checked) {
        for (let i = 0; i < currentResultRows.length; i++) selectedRowIndexes.add(i);
    } else {
        selectedRowIndexes.clear();
    }
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = checked);
    updateSelectedCount();
}

function toggleSingleRow(idx, checked) {
    if (checked) {
        selectedRowIndexes.add(idx);
    } else {
        selectedRowIndexes.delete(idx);
    }
    const selectAllCb = document.getElementById('selectAllCheckbox');
    if (selectAllCb) {
        selectAllCb.checked = selectedRowIndexes.size === currentResultRows.length;
    }
    updateSelectedCount();
}

function updateSelectedCount() {
    document.getElementById('selectedCountBadge').textContent = selectedRowIndexes.size.toLocaleString();
}

function filterResultGrid() {
    const query = document.getElementById('gridSearchInput').value.toLowerCase().trim();
    if (!query) {
        filteredResultRows = currentResultRows;
    } else {
        filteredResultRows = currentResultRows.filter(row => {
            return Object.values(row).some(v => String(v || '').toLowerCase().includes(query));
        });
    }
    currentPage = 1;
    renderCurrentPage();
}

// Database Import Execution (Chunked for Large Datasets)
async function executeDatabaseImport() {
    if (selectedRowIndexes.size === 0) {
        alert('Please select at least 1 row to import into database.');
        return;
    }

    const targetTable = document.getElementById('importTargetTable').value;
    const truncateOld = document.getElementById('truncateOldCheck').checked;

    if (!confirm(`Are you sure you want to insert ${selectedRowIndexes.size.toLocaleString()} rows into '${targetTable}'?`)) {
        return;
    }

    const btn = document.getElementById('importBtn');
    btn.disabled = true;

    // Extract selected rows
    const selectedRows = [];
    selectedRowIndexes.forEach(idx => {
        if (currentResultRows[idx]) {
            selectedRows.push(currentResultRows[idx]);
        }
    });

    const totalSelected = selectedRows.length;
    const chunkSize = 1500;
    const totalChunks = Math.ceil(totalSelected / chunkSize);
    let totalImported = 0;

    try {
        for (let i = 0; i < totalChunks; i++) {
            const chunkRows = selectedRows.slice(i * chunkSize, (i + 1) * chunkSize);
            const isFirstChunk = (i === 0);
            const percent = Math.round(((i + 1) / totalChunks) * 100);

            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Importing ${Math.min((i + 1) * chunkSize, totalSelected).toLocaleString()} / ${totalSelected.toLocaleString()} (${percent}%)...`;

            const res = await fetch(IMPORT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    target_table: targetTable,
                    rows: chunkRows,
                    truncate_old: (isFirstChunk && truncateOld) ? 1 : 0,
                    chunk_index: i,
                    total_chunks: totalChunks
                })
            });

            const data = await res.json();
            if (!data.success) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cloud-arrow-down"></i> Insert Selected to Database';
                alert(`Import failed on chunk ${i + 1}/${totalChunks}: ` + data.message);
                return;
            }
            totalImported += (data.count || chunkRows.length);
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cloud-arrow-down"></i> Insert Selected to Database';
        alert(`🎉 Successfully imported all ${totalImported.toLocaleString()} rows into '${targetTable}'!`);

    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cloud-arrow-down"></i> Insert Selected to Database';
        alert('Network error during import: ' + err.message);
    }
}
</script>
@endsection
