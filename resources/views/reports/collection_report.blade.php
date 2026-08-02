@extends('layouts.app')
@section('header', 'Collection Report')

@section('content')
@php
    $parseAmt = fn($v) => is_numeric(str_replace(',', '', (string)$v)) ? (float)str_replace(',', '', (string)$v) : 0;
@endphp

<style>
    .team-btn.active {
        background-color: #3b82f6 !important;
        color: #ffffff !important;
        border-color: #2563eb !important;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
</style>

<div class="space-y-6">
{{-- ═══ HEADER ═══ --}}
<div class="bg-gradient-to-br from-blue-700 to-indigo-800 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden">
    <div class="absolute -right-8 -top-8 w-44 h-44 bg-white/10 rounded-full blur-xl"></div>
    <div class="absolute -left-6 -bottom-6 w-32 h-32 bg-white/5 rounded-full blur-lg"></div>
    <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-white/15 border border-white/20 flex items-center justify-center shadow-lg">
                <i class="fas fa-chart-line text-2xl text-blue-200"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black tracking-tight">Collection Analyzer</h1>
                <p class="text-blue-200 text-xs font-bold uppercase tracking-widest mt-0.5">
                    Teamwise / Agentwise Sales Grouping
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if(Auth::user()->hasPermission('collection_report', 'create') || Auth::user()->role === 'admin')
            <a href="{{ route('reports.agent-targets.index') }}"
               class="bg-indigo-600 hover:bg-indigo-750 border border-indigo-500 rounded-2xl px-5 py-2.5 text-xs font-bold tracking-wider uppercase transition flex items-center gap-2">
                <i class="fas fa-bullseye"></i>
                <span>Set targets</span>
            </a>
            <button onclick="toggleTeamCreatorModal(true)"
               class="bg-emerald-600 hover:bg-emerald-700 border border-emerald-500 rounded-2xl px-5 py-2.5 text-xs font-bold tracking-wider uppercase transition flex items-center gap-2">
                <i class="fas fa-plus"></i>
                <span>Create Custom Team</span>
            </button>
            @endif
            <a href="{{ route('reports.collection', array_merge(request()->except('refresh_party_master'), ['refresh_party_master' => 1])) }}"
               class="bg-white/10 hover:bg-white/20 border border-white/20 rounded-2xl px-5 py-2.5 text-xs font-bold tracking-wider uppercase transition">
                <i class="fas fa-arrows-rotate text-blue-300"></i>
            </a>
        </div>
    </div>
</div>

{{-- ═══ MODERN FILTERS LAYOUT ═══ --}}
<div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-slate-50 px-6 py-4 border-b border-gray-100">
        <h3 class="text-xs font-black text-slate-600 uppercase tracking-widest flex items-center gap-2">
            <i class="fas fa-filter text-blue-500 text-sm"></i>
            Interactive Filtering Controls
        </h3>
    </div>
    
    <form method="GET" action="{{ route('reports.collection') }}" id="collectionFilterForm" class="p-6 space-y-6">
        <input type="hidden" name="fetch" value="1">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Date Ranges --}}
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">From Date</label>
                <input type="date" name="from_date"
                    value="{{ $fromDate ?? $defaults['from_date'] }}"
                    class="w-full border border-gray-200 rounded-2xl py-3 px-4 text-sm focus:ring-2 focus:ring-blue-400 outline-none transition bg-slate-50/50">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">To Date</label>
                <input type="date" name="to_date"
                    value="{{ $toDate ?? $defaults['to_date'] }}"
                    class="w-full border border-gray-200 rounded-2xl py-3 px-4 text-sm focus:ring-2 focus:ring-blue-400 outline-none transition bg-slate-50/50">
            </div>

            {{-- Agent Filter --}}
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">
                    <i class="fas fa-user-tie text-violet-400 mr-1.5"></i>Agent Filter
                </label>
                <div class="relative">
                    <select name="agent_filter"
                        class="w-full border border-gray-200 rounded-2xl py-3 px-4 pr-9 text-sm focus:ring-2 focus:ring-violet-400 outline-none transition appearance-none bg-white">
                        <option value="">All Agents</option>
                        @foreach($agentOptions ?? [] as $opt)
                            <option value="{{ $opt }}" {{ ($agentFilter ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-violet-400">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Group Name / Branch Multiple Select --}}
        <div>
            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">
                <i class="fas fa-network-wired text-blue-500 mr-1.5"></i>Group Name / Branch (Hold Ctrl to select multiple)
            </label>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
                <div class="lg:col-span-8">
                    <select name="branch_filter[]" multiple size="4"
                        class="w-full border border-gray-200 rounded-2xl p-3 text-xs focus:ring-2 focus:ring-blue-400 outline-none transition bg-slate-50/20">
                        @foreach($branchOptions ?? [] as $opt)
                            <option value="{{ $opt }}" {{ in_array($opt, $branchFilter ?? []) ? 'selected' : '' }} class="p-1 rounded mb-0.5">
                                {{ $opt }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-4 text-xs text-slate-400 leading-relaxed">
                    💡 If nothing is highlighted, it will display <strong>All Group Names</strong>. Hold <kbd class="px-1 py-0.5 bg-slate-100 rounded border">Ctrl</kbd> to toggle multiple selections.
                </div>
            </div>
        </div>

        {{-- Team Maker row --}}
        <div class="border-t border-slate-100 pt-5">
            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3 ml-1">
                <i class="fas fa-users-gear text-emerald-500 mr-1.5"></i>Select Teams (Group Filters)
            </label>
            <div class="flex flex-wrap gap-2.5 items-center">
                @forelse($dbTeams ?? [] as $team)
                    @php $isActive = in_array($team->id, $selectedTeams ?? []); @endphp
                    <div class="inline-flex items-center bg-white border border-gray-200 rounded-xl overflow-hidden hover:border-slate-350 transition">
                        <button type="button" 
                            onclick="toggleTeamFilter('{{ $team->id }}')"
                            id="btn_team_{{ $team->id }}"
                            class="team-btn px-4 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition border-r border-gray-100 {{ $isActive ? 'active' : '' }}">
                            <i class="fas fa-users mr-1.5"></i> {{ $team->name }}
                        </button>
                        
                        {{-- Edit team button --}}
                        <button type="button"
                                onclick='openEditTeamModal(@json($team))'
                                class="px-2.5 py-2 text-xs text-blue-500 hover:text-blue-700 hover:bg-blue-50 transition border-r border-gray-100">
                            <i class="fas fa-pencil text-[10px]"></i>
                        </button>

                        {{-- Delete team action --}}
                        <button type="button" 
                                onclick="deleteTeam('{{ $team->id }}', '{{ $team->name }}')"
                                class="px-2.5 py-2 text-xs text-red-400 hover:text-red-600 hover:bg-red-50 transition duration-150">
                            <i class="fas fa-trash text-[10px]"></i>
                        </button>
                    </div>

                    {{-- Hidden inputs to submit selected teams --}}
                    @if($isActive)
                        <input type="hidden" name="teams[]" value="{{ $team->id }}" id="input_team_{{ $team->id }}">
                    @endif
                @empty
                    <p class="text-xs text-slate-400 italic">No custom teams created yet. Click "Create Custom Team" above to configure one.</p>
                @endforelse
                
                @if(!empty($selectedTeams))
                    <button type="button" onclick="clearTeams()" class="text-xs text-rose-500 font-bold hover:underline ml-auto flex items-center gap-1">
                        <i class="fas fa-trash-can"></i> Reset Teams
                    </button>
                @endif
            </div>
        </div>

        {{-- Form submission button block --}}
        <div class="border-t border-slate-100 pt-5 flex justify-center">
            <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-black py-3.5 px-10 rounded-2xl shadow-lg hover:shadow-blue-150 text-sm tracking-wide transition transform active:scale-98">
                <i class="fas fa-table-list mr-2"></i> View Report Teamwise / Agentwise
            </button>
        </div>
    </form>
</div>

{{-- ═══ SUMMARY CARDS ═══ --}}
@if(isset($grouped) && count($grouped) > 0)
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-3xl border border-gray-150 shadow-sm p-5 flex items-center justify-between">
        <div>
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Records</div>
            <div class="text-2xl font-black text-slate-800">{{ number_format($totalParties ?? 0) }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-500"><i class="fas fa-list-check"></i></div>
    </div>
    <div class="bg-white rounded-3xl border border-gray-150 shadow-sm p-5 flex items-center justify-between">
        <div>
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Active Group Names</div>
            <div class="text-2xl font-black text-blue-600">{{ number_format(count($grouped)) }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500"><i class="fas fa-network-wired"></i></div>
    </div>
    <div class="bg-white rounded-3xl border border-gray-150 shadow-sm p-5 flex items-center justify-between">
        <div>
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Sales Agents</div>
            <div class="text-2xl font-black text-violet-600">{{ number_format($totalAgents ?? 0) }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center text-violet-500"><i class="fas fa-user-tie"></i></div>
    </div>
    <div class="bg-emerald-500 rounded-3xl p-5 text-white flex items-center justify-between shadow-lg shadow-emerald-100">
        <div>
            <div class="text-[9px] font-bold text-emerald-100 uppercase tracking-widest mb-1">Grand Collection</div>
            <div class="text-2xl font-black">₹{{ number_format($grandTotal ?? 0, 0) }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white"><i class="fas fa-circle-dollar-to-slot"></i></div>
    </div>
</div>
@endif

{{-- ═══ REPORT RENDER ═══ --}}
@if(!isset($grouped))
<div class="bg-white rounded-3xl border border-gray-100 shadow-sm py-24 text-center">
    <div class="w-20 h-20 bg-slate-50 border border-dashed border-slate-200 rounded-3xl flex items-center justify-center mx-auto mb-5">
        <i class="fas fa-chart-pie text-3xl text-blue-400/80"></i>
    </div>
    <p class="text-slate-500 font-black text-base font-bold">Filters are set.</p>
    <p class="text-slate-400 text-xs mt-1">Please click the big <strong>View Report</strong> button above to load the analyzer.</p>
</div>
@elseif(count($grouped) === 0)
<div class="bg-white rounded-3xl border border-gray-100 shadow-sm py-16 text-center">
    <i class="fas fa-inbox text-5xl text-slate-200 mb-3 animate-bounce"></i>
    <p class="text-slate-400 font-bold text-sm">No data returned for selected filters.</p>
</div>
@else

{{-- Group Name wise Accordions --}}
@php
    $groupColors = ['blue','indigo','violet','purple','fuchsia','pink','rose','orange','amber','emerald'];
    $colorIndex = 0;
@endphp

@foreach($grouped as $branchName => $agents)
@php
    $bColor = $groupColors[$colorIndex % count($groupColors)];
    $bSummary = $branchSummary[$branchName] ?? ['total'=>0,'parties'=>0,'agents'=>0];
    $colorIndex++;
    $branchSlug = 'grp_' . Str::slug($branchName);

    // Find database Team ID if exists
    $matchingDbTeam = $dbTeams->firstWhere('name', $branchName);
    $tTargetAmt = $matchingDbTeam ? ($teamTargets[$matchingDbTeam->id] ?? 0) : 0;
    $tPercent = $tTargetAmt > 0 ? min(100, round(($bSummary['total'] / $tTargetAmt) * 100)) : 0;
@endphp

<div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden" id="{{ $branchSlug }}">
    {{-- Group Header Bar --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between px-6 py-5 bg-slate-900 cursor-pointer select-none gap-4"
         onclick="toggleBranch('{{ $branchSlug }}')">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-2xl bg-white/10 flex items-center justify-center">
                <i class="fas fa-users-rectangle text-{{ $bColor }}-400 text-sm"></i>
            </div>
            <div>
                <div class="text-white font-black text-base tracking-tight">{{ $branchName }}</div>
                <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-0.5">
                    {{ $bSummary['agents'] }} agents &nbsp;·&nbsp; {{ number_format($bSummary['parties']) }} parties / accounts
                </div>
            </div>
        </div>
        
        {{-- Team Target Progress Bar --}}
        @if($tTargetAmt > 0)
        <div class="flex-1 max-w-md mx-0 md:mx-6">
            <div class="flex items-center justify-between text-[10px] font-black text-slate-400 mb-1">
                <span>Target Progress: {{ round(($bSummary['total'] / $tTargetAmt) * 100) }}%</span>
                <span>Goal: ₹{{ number_format($tTargetAmt, 0) }}</span>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-700">
                <div class="bg-gradient-to-r from-emerald-500 to-teal-400 h-2 rounded-full" style="width: {{ $tPercent }}%"></div>
            </div>
        </div>
        @endif

        <div class="flex items-center gap-5">
            <div class="text-right">
                <div class="text-emerald-400 font-black text-lg">₹{{ number_format($bSummary['total'], 0) }}</div>
                <div class="text-slate-500 text-[9px] font-bold uppercase tracking-widest">Team Collection</div>
            </div>
            <i class="fas fa-chevron-down text-slate-400 text-xs transition-transform duration-200 branch-chevron" id="{{ $branchSlug }}_chev"></i>
        </div>
    </div>

    {{-- Agent rows nested --}}
    <div id="{{ $branchSlug }}_body" class="branch-body">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-gray-100 text-[9px] font-black text-slate-500 uppercase tracking-widest">
                    <tr>
                        <th class="py-3 px-6 border-r border-gray-100 text-center w-14">#</th>
                        <th class="py-3 px-6 border-r border-gray-100">Salesman / Agent Name</th>
                        <th class="py-3 px-6 border-r border-gray-100 text-center w-20">Parties</th>
                        <th class="py-3 px-6 border-r border-gray-100 text-right w-36">Actual Collection</th>
                        <th class="py-3 px-6 border-r border-gray-100 text-right w-36">Monthly Target</th>
                        <th class="py-3 px-6 border-r border-gray-100 text-center w-48">Achievement Progress</th>
                        <th class="py-3 px-6 text-center w-24">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                @php $agentIdx = 0; @endphp
                @foreach($agents as $agentName => $agentRows)
                @php
                    $agentTotal = array_sum(array_map(fn($r) => $parseAmt($r[$crField] ?? 0), $agentRows));
                    $agentParties = count($agentRows);
                    $agentSlug = $branchSlug . '_ag_' . Str::slug($agentName);
                    $agentIdx++;

                    // Target details
                    $targetAmt = $agentTargets[$agentName] ?? 0;
                    $percent = $targetAmt > 0 ? min(100, round(($agentTotal / $targetAmt) * 100)) : 0;
                    $progressColor = 'bg-slate-300';
                    if ($targetAmt > 0) {
                        if ($percent >= 100) $progressColor = 'bg-emerald-500';
                        elseif ($percent >= 50) $progressColor = 'bg-amber-500';
                        else $progressColor = 'bg-rose-550';
                    }
                @endphp

                {{-- Agent Summary Row --}}
                <tr class="hover:bg-slate-50/50 transition-colors cursor-pointer agent-row text-xs"
                    onclick="toggleAgentDetail('{{ $agentSlug }}', this)">
                    <td class="py-3 px-6 border-r border-gray-50 text-gray-400 font-bold text-center">{{ $agentIdx }}</td>
                    <td class="py-3 px-6 border-r border-gray-50">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-violet-50 flex items-center justify-center flex-shrink-0 text-violet-600">
                                <i class="fas fa-user-tie text-xs"></i>
                            </div>
                            <span class="font-bold text-slate-800 text-[13px]">{{ $agentName }}</span>
                        </div>
                    </td>
                    <td class="py-3 px-6 border-r border-gray-50 text-center">
                        <span class="bg-blue-50 text-blue-700 border border-blue-100 font-black text-[10px] px-2 py-0.5 rounded-lg">
                            {{ $agentParties }}
                        </span>
                    </td>
                    <td class="py-3 px-6 border-r border-gray-50 text-right font-black text-slate-800">
                        ₹{{ number_format($agentTotal, 0) }}
                    </td>
                    <td class="py-3 px-6 border-r border-gray-50 text-right font-bold text-slate-500">
                        @if($targetAmt > 0)
                            ₹{{ number_format($targetAmt, 0) }}
                        @else
                            <span class="text-gray-300 italic text-[11px]">Not Set</span>
                        @endif
                    </td>
                    <td class="py-3 px-6 border-r border-gray-50">
                        @if($targetAmt > 0)
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-[10px] font-black">
                                    <span class="text-slate-500">{{ round(($agentTotal / $targetAmt) * 100) }}%</span>
                                    <span class="text-slate-400">₹{{ number_format(max(0, $targetAmt - $agentTotal), 0) }} left</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                    <div class="{{ $progressColor }} h-1.5 rounded-full" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @else
                            <span class="text-gray-300 text-[11px] block text-center">—</span>
                        @endif
                    </td>
                    <td class="py-3 px-6 text-center">
                        <span class="inline-flex items-center gap-1 text-[10px] font-black text-slate-400 hover:text-blue-600 transition-colors">
                            <i class="fas fa-chevron-down text-[9px] agent-chev transition-transform duration-200" id="{{ $agentSlug }}_chev"></i>
                            <span class="agent-chev-text">Expand</span>
                        </span>
                    </td>
                </tr>

                {{-- Expanded Party List details --}}
                <tr id="{{ $agentSlug }}" class="agent-detail hidden">
                    <td colspan="5" class="p-0 bg-slate-50/30">
                        <div class="px-8 py-5">
                            <div class="flex items-center justify-between mb-4">
                                <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-1.5">
                                    <i class="fas fa-building-user text-violet-500"></i> Account Listings under {{ $agentName }}
                                </div>
                                <input type="text"
                                    placeholder="Instant Filter accounts..."
                                    oninput="filterAgentParties(this, '{{ $agentSlug }}_tbody')"
                                    class="border border-gray-200 rounded-xl px-4 py-2 text-xs font-semibold focus:ring-2 focus:ring-violet-300 outline-none w-52 bg-white">
                            </div>

                            <div class="rounded-2xl overflow-hidden border border-gray-150 shadow-sm bg-white">
                                <table class="min-w-full text-left border-collapse">
                                    <thead class="bg-indigo-50/50 text-[9px] font-black text-indigo-700 uppercase tracking-widest border-b border-indigo-100">
                                        <tr>
                                            <th class="py-2.5 px-4 border-r border-indigo-150/40 text-center w-12">#</th>
                                            <th class="py-2.5 px-4 border-r border-indigo-150/40 w-28">A/C Code</th>
                                            <th class="py-2.5 px-4 border-r border-indigo-150/40">Party Name</th>
                                            <th class="py-2.5 px-4 border-r border-indigo-150/40">Town / Location</th>
                                            @if($crField)
                                            <th class="py-2.5 px-4 text-right w-36">Collection</th>
                                            @endif
                                            @if($drField)
                                            <th class="py-2.5 px-4 text-right border-l border-indigo-150/40 w-36">Debit</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100" id="{{ $agentSlug }}_tbody">
                                        @foreach($agentRows as $pi => $party)
                                        @php
                                            $pName  = trim($party[$partyNameKey ?? 'AC_Name'] ?? $party['AC_Name'] ?? $party['AcName'] ?? $party['PartyName'] ?? '—');
                                            $pCode  = trim($party['AC_Code'] ?? $party['ActCode'] ?? $party['Ac_Code'] ?? '');
                                            $pTown  = $party['_TownName'] ?? '—';
                                            $pCrAmt = $crField ? $parseAmt($party[$crField] ?? 0) : 0;
                                            $pDrAmt = $drField ? $parseAmt($party[$drField] ?? 0) : 0;
                                        @endphp
                                        <tr class="hover:bg-slate-50 transition-colors party-row text-xs">
                                            <td class="py-2 px-4 border-r border-gray-100 text-gray-400 font-bold text-center">{{ $pi + 1 }}</td>
                                            <td class="py-2 px-4 border-r border-gray-100 font-mono text-[10px] text-indigo-600 font-black">
                                                {{ $pCode ?: '—' }}
                                            </td>
                                            <td class="py-2 px-4 border-r border-gray-100 font-bold text-slate-800">{{ $pName }}</td>
                                            <td class="py-2 px-4 border-r border-gray-100 text-slate-500">
                                                @if($pTown && $pTown !== '—')
                                                <span class="flex items-center gap-1.5"><i class="fas fa-location-dot text-rose-500 text-[9px]"></i>{{ $pTown }}</span>
                                                @else<span class="text-gray-300">—</span>@endif
                                            </td>
                                            @if($crField)
                                            <td class="py-2 px-4 text-right font-black text-emerald-700">
                                                {{ $pCrAmt > 0 ? '₹' . number_format($pCrAmt, 0) : '—' }}
                                            </td>
                                            @endif
                                            @if($drField)
                                            <td class="py-2 px-4 text-right border-l border-gray-100 font-bold text-rose-600">
                                                {{ $pDrAmt > 0 ? '₹' . number_format($pDrAmt, 0) : '—' }}
                                            </td>
                                            @endif
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    {{-- Sub totals --}}
                                    <tfoot class="bg-indigo-50 border-t border-indigo-100 text-xs font-black text-indigo-900">
                                        <tr>
                                            <td colspan="4" class="py-2 px-4 text-right uppercase tracking-widest text-[9px] border-r border-indigo-100">Sub-total →</td>
                                            @if($crField)
                                            <td class="py-2 px-4 text-right text-emerald-700">₹{{ number_format($agentTotal, 0) }}</td>
                                            @endif
                                            @if($drField)
                                            @php $agentDrTotal = array_sum(array_map(fn($r) => $parseAmt($r[$drField] ?? 0), $agentRows)); @endphp
                                            <td class="py-2 px-4 text-right border-l border-indigo-100 text-rose-700">₹{{ number_format($agentDrTotal, 0) }}</td>
                                            @endif
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforeach

                {{-- Branch total summary row --}}
                <tr class="bg-slate-800 border-t border-slate-700">
                    <td colspan="3" class="py-3 px-6 text-right text-slate-400 text-[10px] font-black uppercase tracking-widest">
                        Group Total ({{ $bSummary['agents'] }} Agents, {{ number_format($bSummary['parties']) }} Accounts) →
                    </td>
                    <td class="py-3 px-6 text-right font-black text-emerald-400 text-base">
                        ₹{{ number_format($bSummary['total'], 0) }}
                    </td>
                    <td></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endforeach

{{-- GRAND TOTAL --}}
<div class="bg-slate-900 rounded-3xl p-6 flex items-center justify-between shadow-xl">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center">
            <i class="fas fa-chart-line text-emerald-400"></i>
        </div>
        <div>
            <div class="text-white font-black text-base">Grand Total</div>
            <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-0.5">
                {{ count($grouped) }} groups · {{ number_format($totalAgents) }} agents · {{ number_format($totalParties) }} accounts
            </div>
        </div>
    </div>
    <div class="text-emerald-400 font-black text-2xl">₹{{ number_format($grandTotal, 0) }}</div>
</div>

@endif
</div>

{{-- ═══ CREATE TEAM MODAL (Overlay) ═══ --}}
<div id="teamCreatorModal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 max-w-2xl w-full mx-4 overflow-hidden transform transition duration-300">
        <div class="bg-slate-900 px-6 py-4 flex items-center justify-between text-white">
            <h3 class="font-black text-sm uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-users-gear text-emerald-400"></i>
                Define New Custom Sales Team
            </h3>
            <button onclick="toggleTeamCreatorModal(false)" class="text-slate-400 hover:text-white transition">
                <i class="fas fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('reports.collection.teams.store') }}" class="p-6 space-y-5">
            @csrf
            
            {{-- Team Name --}}
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Team Name</label>
                <input type="text" name="name" required placeholder="e.g. Team West Coast"
                    class="w-full border border-gray-200 rounded-xl py-3 px-4 text-sm focus:ring-2 focus:ring-blue-400 outline-none transition">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Select Agents --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">
                        Assign Agents (Hold Ctrl to select multiple)
                    </label>
                    <select name="agents[]" multiple size="6"
                        class="w-full border border-gray-200 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-blue-400 outline-none transition">
                        @foreach($agentOptions ?? [] as $opt)
                            <option value="{{ $opt }}" class="p-1 rounded mb-0.5">{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Select Group/Branches --}}
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">
                        Assign Group Names (Hold Ctrl to select multiple)
                    </label>
                    <select name="branches[]" multiple size="6"
                        class="w-full border border-gray-200 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-blue-400 outline-none transition">
                        @foreach($branchOptions ?? [] as $opt)
                            <option value="{{ $opt }}" class="p-1 rounded mb-0.5">{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <button type="button" onclick="toggleTeamCreatorModal(false)"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-650 font-bold py-2.5 px-5 rounded-xl text-xs transition">
                    Cancel
                </button>
                <button type="submit"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-black py-2.5 px-6 rounded-xl text-xs shadow-md transition active:scale-95">
                    Save Team
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Hidden Form for Deleting Teams --}}
<form id="deleteTeamForm" method="POST" action="" class="hidden">
    @csrf
    @method('DELETE')
</form>

<script>
// Modal helpers
function toggleTeamCreatorModal(show) {
    const modal = document.getElementById('teamCreatorModal');
    if (show) {
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
        // Reset modal to create mode
        resetModalToCreateMode();
    }
}

function resetModalToCreateMode() {
    const modal = document.getElementById('teamCreatorModal');
    modal.querySelector('h3').innerHTML = '<i class="fas fa-users-gear text-emerald-400"></i> Define New Custom Sales Team';
    const form = modal.querySelector('form');
    form.action = "{{ route('reports.collection.teams.store') }}";
    
    // Remove PUT method spoof if exists
    const putInput = form.querySelector('input[name="_method"]');
    if (putInput) putInput.remove();
    
    form.reset();
    
    // Clear selects selection
    form.querySelectorAll('select').forEach(select => {
        Array.from(select.options).forEach(opt => opt.selected = false);
    });
}

function openEditTeamModal(team) {
    const modal = document.getElementById('teamCreatorModal');
    modal.querySelector('h3').innerHTML = '<i class="fas fa-pencil text-blue-400"></i> Edit Team: ' + team.name;
    
    const form = modal.querySelector('form');
    form.action = "{{ url('reports/collection/teams') }}/" + team.id;
    
    // Insert PUT method spoof
    let putInput = form.querySelector('input[name="_method"]');
    if (!putInput) {
        putInput = document.createElement('input');
        putInput.type = 'hidden';
        putInput.name = '_method';
        putInput.value = 'PUT';
        form.appendChild(putInput);
    }
    
    // Populate Name
    form.querySelector('input[name="name"]').value = team.name;
    
    // Populate Agents select
    const agentSelect = form.querySelector('select[name="agents[]"]');
    if (agentSelect && Array.isArray(team.agents)) {
        Array.from(agentSelect.options).forEach(opt => {
            opt.selected = team.agents.includes(opt.value);
        });
    }
    
    // Populate Branches select
    const branchSelect = form.querySelector('select[name="branches[]"]');
    if (branchSelect && Array.isArray(team.branches)) {
        Array.from(branchSelect.options).forEach(opt => {
            opt.selected = team.branches.includes(opt.value);
        });
    }
    
    modal.classList.remove('hidden');
}

// Delete custom team
function deleteTeam(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"?')) {
        const form = document.getElementById('deleteTeamForm');
        form.action = "{{ url('reports/collection/teams') }}/" + id;
        form.submit();
    }
}

// ── Team Maker interaction ──────────────────────────────────────────────────
function toggleTeamFilter(teamId) {
    const btn = document.getElementById('btn_team_' + teamId);
    const form = document.getElementById('collectionFilterForm');
    
    let input = document.getElementById('input_team_' + teamId);
    
    if (btn.classList.contains('active')) {
        btn.classList.remove('active');
        if (input) input.remove();
    } else {
        btn.classList.add('active');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'teams[]';
            input.value = teamId;
            input.id = 'input_team_' + teamId;
            form.appendChild(input);
        }
    }
}

function clearTeams() {
    document.querySelectorAll('.team-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('input[name="teams[]"]').forEach(input => input.remove());
    document.getElementById('collectionFilterForm').submit();
}

// ── Branch Accordion toggle ─────────────────────────────────────────────────
function toggleBranch(branchId) {
    const body = document.getElementById(branchId + '_body');
    const chev = document.getElementById(branchId + '_chev');
    if (!body) return;
    const isOpen = !body.classList.contains('hidden');
    if (isOpen) {
        body.classList.add('hidden');
        chev.style.transform = 'rotate(-90deg)';
    } else {
        body.classList.remove('hidden');
        chev.style.transform = 'rotate(0deg)';
    }
}

// ── Agent detail accordion toggle ──────────────────────────────────────────
function toggleAgentDetail(agentId, row) {
    const detail = document.getElementById(agentId);
    const chev   = document.getElementById(agentId + '_chev');
    if (!detail) return;

    const isOpen = !detail.classList.contains('hidden');

    const parentContainer = row.closest('.branch-body');
    if (parentContainer) {
        parentContainer.querySelectorAll('.agent-detail').forEach(d => {
            if (d.id !== agentId && !d.classList.contains('hidden')) {
                d.classList.add('hidden');
                const c = document.getElementById(d.id + '_chev');
                if (c) c.style.transform = '';
                const txt = d.previousElementSibling?.querySelector('.agent-chev-text');
                if (txt) txt.textContent = 'Expand';
            }
        });
    }

    if (isOpen) {
        detail.classList.add('hidden');
        if (chev) chev.style.transform = '';
        const txt = row.querySelector('.agent-chev-text');
        if (txt) txt.textContent = 'Expand';
    } else {
        detail.classList.remove('hidden');
        if (chev) chev.style.transform = 'rotate(180deg)';
        const txt = row.querySelector('.agent-chev-text');
        if (txt) txt.textContent = 'Collapse';
        setTimeout(() => detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 100);
    }
}

// ── Local search filter ─────────────────────────────────────────────────────
function filterAgentParties(input, tbodyId) {
    const q     = input.value.toLowerCase().trim();
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    tbody.querySelectorAll('.party-row').forEach(row => {
        row.style.display = q ? (row.textContent.toLowerCase().includes(q) ? '' : 'none') : '';
    });
}
</script>
@endsection
