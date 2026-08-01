@extends('layouts.mobile')

@section('content')
@php
    $parseAmt = fn($v) => is_numeric(str_replace(',', '', (string)$v)) ? (float)str_replace(',', '', (string)$v) : 0;
@endphp

<div class="space-y-6 pb-20" x-data="collectionReportApp()">
    
    {{-- Success/Error Flash Messages --}}
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-150 text-emerald-800 rounded-3xl p-4 flex items-center gap-3 text-xs font-bold shadow-sm shadow-emerald-50">
        <i class="fas fa-circle-check text-emerald-500 text-sm"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="bg-rose-50 border border-rose-150 text-rose-800 rounded-3xl p-4 flex items-center gap-3 text-xs font-bold shadow-sm shadow-rose-50">
        <i class="fas fa-circle-exclamation text-rose-500 text-sm"></i>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- Header Banner --}}
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-blue-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Collection</h2>
                <p class="text-[9px] font-black uppercase tracking-wider text-slate-400 mt-1">Analyzer & Teams</p>
            </div>
            <div class="flex gap-2">
                <button @click="showFilters = !showFilters" :class="showFilters ? 'bg-blue-50 text-blue-500' : 'bg-white text-slate-400'" 
                        class="w-11 h-11 rounded-2xl flex items-center justify-center border border-white/60 shadow-md transition active:scale-90">
                    <i class="fas fa-filter text-xs"></i>
                </button>
                @if(Auth::user()->hasFeature('mobile_collection', 'target_setting') || Auth::user()->role === 'admin')
                <a href="{{ route('mobile.agent-targets.index') }}" 
                   class="w-11 h-11 bg-white text-indigo-500 rounded-2xl flex items-center justify-center border border-white/60 shadow-md transition active:scale-90">
                    <i class="fas fa-bullseye text-xs"></i>
                </a>
                @endif
                @if(Auth::user()->hasFeature('mobile_collection', 'team_management') || Auth::user()->role === 'admin')
                <button @click="openCreateTeamModal()" 
                        class="w-11 h-11 bg-white text-emerald-500 rounded-2xl flex items-center justify-center border border-white/60 shadow-md transition active:scale-90">
                    <i class="fas fa-plus text-xs"></i>
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Filters Card --}}
    <div x-show="showFilters" x-cloak x-transition 
         class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 p-6 rounded-[2.5rem] border border-white/80 space-y-4">
        <form method="GET" action="{{ route('mobile.collection-report') }}" id="mobileFilterForm">
            <input type="hidden" name="fetch" value="1">
            
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="space-y-1">
                    <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest ml-2">From Date</label>
                    <input type="date" name="from_date" value="{{ $fromDate ?? $defaults['from_date'] }}" 
                           class="w-full bg-white/60 border-none rounded-xl py-3 px-4 text-xs font-bold text-slate-700">
                </div>
                <div class="space-y-1">
                    <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest ml-2">To Date</label>
                    <input type="date" name="to_date" value="{{ $toDate ?? $defaults['to_date'] }}" 
                           class="w-full bg-white/60 border-none rounded-xl py-3 px-4 text-xs font-bold text-slate-700">
                </div>
            </div>

            @if(Auth::user()->hasFeature('mobile_collection', 'agent_filter') || Auth::user()->role === 'admin')
            <div class="space-y-1 mb-3">
                <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest ml-2">Agent</label>
                <select name="agent_filter" class="w-full bg-white/60 border-none rounded-xl py-3 px-4 text-xs font-bold text-slate-700">
                    <option value="">All Agents</option>
                    @foreach($agentOptions ?? [] as $opt)
                        <option value="{{ $opt }}" {{ ($agentFilter ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(Auth::user()->hasFeature('mobile_collection', 'branch_filter') || Auth::user()->role === 'admin')
            <div class="space-y-1 mb-4">
                <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest ml-2">Group Name (Multiple)</label>
                <select name="branch_filter[]" multiple class="w-full bg-white/60 border-none rounded-xl p-2.5 text-xs font-semibold text-slate-700" size="3">
                    @foreach($branchOptions ?? [] as $opt)
                        <option value="{{ $opt }}" {{ in_array($opt, $branchFilter ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="space-y-2 mb-2">
                <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest ml-2 block">Select Teams (Group Filters)</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($dbTeams ?? [] as $team)
                        @php $isActive = in_array($team->id, $selectedTeams ?? []); @endphp
                        <div class="inline-flex items-center bg-white border border-slate-200/60 rounded-xl overflow-hidden shadow-sm">
                            <button type="button" 
                                    onclick="toggleMobileTeamFilter('{{ $team->id }}')" 
                                    id="mob_btn_team_{{ $team->id }}"
                                    class="px-3 py-2 text-[10px] font-bold border-r border-slate-100 transition-colors {{ $isActive ? 'bg-blue-500 text-white border-blue-600' : 'bg-white text-slate-700' }}">
                                <i class="fas fa-users text-[9px] mr-1 opacity-70"></i> {{ $team->name }}
                            </button>
                            @if(Auth::user()->hasFeature('mobile_collection', 'team_management') || Auth::user()->role === 'admin')
                                <button type="button"
                                        @click="openEditTeamModal({{ json_encode($team) }})"
                                        class="px-2 py-2 text-[9px] text-blue-500 hover:text-blue-700 bg-white hover:bg-slate-50 transition border-r border-slate-100">
                                    <i class="fas fa-pencil"></i>
                                </button>
                                <button type="button" 
                                        @click="deleteMobileTeam('{{ $team->id }}', '{{ addslashes($team->name) }}')"
                                        class="px-2 py-2 text-[9px] text-red-500 hover:text-red-700 bg-white hover:bg-slate-50 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            @endif
                        </div>
                        @if($isActive)
                            <input type="hidden" name="teams[]" value="{{ $team->id }}" id="mob_input_team_{{ $team->id }}">
                        @endif
                    @endforeach
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white font-bold rounded-xl py-3 text-xs mt-4">
                View Mobile Report
            </button>
        </form>
    </div>

    @if(isset($grouped) && count($grouped) > 0)
    {{-- Summary Stats --}}
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white/70 border border-white/60 p-4 rounded-3xl shadow-sm">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Grand Total</div>
            <div class="text-base font-black text-emerald-600 mt-1">₹{{ number_format($grandTotal ?? 0, 0) }}</div>
        </div>
        <div class="bg-white/70 border border-white/60 p-4 rounded-3xl shadow-sm">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Active Groups</div>
            <div class="text-base font-black text-slate-800 mt-1">{{ count($grouped) }}</div>
        </div>
    </div>

    {{-- Group lists accordion --}}
    <div class="space-y-3">
        @foreach($grouped as $teamName => $agents)
        @php
            $bSummary = $branchSummary[$teamName] ?? ['total'=>0,'parties'=>0,'agents'=>0];
            $tSlug = 'mob_t_' . Str::slug($teamName);
            
            $matchingDbTeam = $dbTeams->firstWhere('name', $teamName);
            $tTargetAmt = $matchingDbTeam ? ($teamTargets[$matchingDbTeam->id] ?? 0) : 0;
            $tPercent = $tTargetAmt > 0 ? min(100, round(($bSummary['total'] / $tTargetAmt) * 100)) : 0;
        @endphp

        <div class="bg-white rounded-3xl overflow-hidden shadow-md border border-slate-100">
            {{-- Header --}}
            <div class="p-4 bg-slate-900 text-white flex items-center justify-between" onclick="toggleMobAccordion('{{ $tSlug }}')">
                <div class="overflow-hidden pr-3">
                    <span class="font-black text-sm block truncate">{{ $teamName }}</span>
                    <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">
                        {{ $bSummary['agents'] }} Agents · {{ $bSummary['parties'] }} A/C
                    </span>
                </div>
                <div class="text-right flex items-center gap-2">
                    <div>
                        <div class="text-emerald-400 font-black text-sm">₹{{ number_format($bSummary['total'], 0) }}</div>
                        @if($tTargetAmt > 0)
                            <div class="text-[8px] text-slate-400 font-bold">Goal: ₹{{ number_format($tTargetAmt, 0) }} ({{ $tPercent }}%)</div>
                        @endif
                    </div>
                    <i class="fas fa-chevron-down text-[10px] text-slate-400 transition-transform ml-1.5" id="{{ $tSlug }}_chev"></i>
                </div>
            </div>

            {{-- Team targets progress bar if exists --}}
            @if($tTargetAmt > 0)
            <div class="bg-slate-950 px-4 py-2 border-t border-slate-800">
                <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ $tPercent }}%"></div>
                </div>
            </div>
            @endif

            {{-- Body nested --}}
            <div id="{{ $tSlug }}" class="hidden divide-y divide-gray-100 bg-white">
                @foreach($agents as $agentName => $agentRows)
                @php
                    $agentTotal = array_sum(array_map(fn($r) => $parseAmt($r[$crField] ?? 0), $agentRows));
                    $agentSlug = $tSlug . '_ag_' . Str::slug($agentName);
                    
                    $targetAmt = $agentTargets[$agentName] ?? 0;
                    $percent = $targetAmt > 0 ? min(100, round(($agentTotal / $targetAmt) * 100)) : 0;
                    $progressColor = $percent >= 100 ? 'bg-emerald-500' : ($percent >= 50 ? 'bg-amber-500' : 'bg-rose-500');
                @endphp
                
                <div class="bg-white divide-y divide-slate-50">
                    {{-- Agent Row --}}
                    <div class="py-3 px-4 flex justify-between items-center bg-slate-50/50" onclick="toggleMobAgentDetail('{{ $agentSlug }}')">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-user-tie text-violet-500 text-xs"></i>
                            <span class="font-bold text-slate-800 text-xs">{{ $agentName }}</span>
                            <span class="text-[9px] bg-slate-200 text-slate-600 font-bold px-1.5 rounded">{{ count($agentRows) }}</span>
                        </div>
                        <div class="text-right flex items-center gap-2">
                            <div>
                                <span class="font-black text-slate-800 text-xs">₹{{ number_format($agentTotal, 0) }}</span>
                                @if($targetAmt > 0)
                                    <span class="text-[8px] text-slate-400 block font-bold">Goal: ₹{{ number_format($targetAmt, 0) }} ({{ round(($agentTotal / $targetAmt) * 100) }}%)</span>
                                @endif
                            </div>
                            <i class="fas fa-chevron-down text-[8px] text-slate-400 transition-transform" id="{{ $agentSlug }}_chev"></i>
                        </div>
                    </div>
                    
                    {{-- Agent progress indicator --}}
                    @if($targetAmt > 0)
                    <div class="px-4 py-1.5 bg-slate-50/20">
                        <div class="w-full bg-slate-100 rounded-full h-1 overflow-hidden">
                            <div class="{{ $progressColor }} h-1" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                    @endif

                    {{-- Parties expanded list --}}
                    <div id="{{ $agentSlug }}" class="hidden bg-indigo-50/10 p-3 space-y-2">
                        <div class="text-[8px] font-black text-indigo-700 uppercase tracking-widest mb-1">Accounts ({{ $agentName }}):</div>
                        <div class="space-y-1.5">
                            @foreach($agentRows as $party)
                            @php
                                $pName  = trim($party[$partyNameKey ?? 'AC_Name'] ?? $party['AC_Name'] ?? $party['AcName'] ?? $party['PartyName'] ?? '—');
                                $pCode  = trim($party['AC_Code'] ?? $party['ActCode'] ?? $party['Ac_Code'] ?? '');
                                $pCrAmt = $crField ? $parseAmt($party[$crField] ?? 0) : 0;
                            @endphp
                            <div class="bg-white p-2.5 rounded-xl border border-gray-150 flex justify-between items-center text-[11px]">
                                <div class="overflow-hidden pr-2">
                                    <span class="font-black text-indigo-600 text-[9px] font-mono block">{{ $pCode }}</span>
                                    <span class="font-bold text-slate-700 block truncate">{{ $pName }}</span>
                                </div>
                                <span class="font-black text-emerald-600 flex-shrink-0 text-right">
                                    {{ $pCrAmt > 0 ? '₹' . number_format($pCrAmt, 0) : '—' }}
                                </span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="bg-white rounded-3xl p-16 text-center text-slate-400 text-xs italic">
        Select filter options and click View Mobile Report.
    </div>
    @endif

    <!-- Create/Edit Team Modal -->
    @if(Auth::user()->hasFeature('mobile_collection', 'team_management') || Auth::user()->role === 'admin')
    <div x-show="showTeamModal" x-cloak class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showTeamModal = false"></div>
        <div class="relative w-full max-w-lg bg-white rounded-[3rem] p-8 space-y-6 shadow-2xl overflow-y-auto max-h-[90vh] z-10">
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-black text-slate-800 tracking-tight" x-text="isEditing ? 'Edit Custom Team' : 'New Custom Team'"></h3>
                <button @click="showTeamModal = false" class="w-10 h-10 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-400">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form :action="teamForm.action" method="POST" id="teamForm">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="space-y-4">
                    <div>
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest ml-2 mb-1.5 block">Team Name</label>
                        <input type="text" name="name" x-model="teamForm.name" required placeholder="e.g. Team West Coast"
                               class="w-full bg-slate-50 border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>

                    <!-- Assign Agents Checkbox List -->
                    <div class="space-y-2">
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest ml-2 block">Assign Agents</label>
                        <div class="bg-slate-50/50 rounded-[2rem] p-4 border border-slate-100 max-h-40 overflow-y-auto space-y-2">
                            @foreach($agentOptions ?? [] as $opt)
                            <label class="flex items-center gap-3 p-2.5 bg-white rounded-xl border border-slate-100 cursor-pointer hover:bg-slate-50 transition-colors">
                                <input type="checkbox" name="agents[]" value="{{ $opt }}" x-model="teamForm.agents" class="rounded border-slate-350 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs font-bold text-slate-750">{{ $opt }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Assign Branches/Groups Checkbox List -->
                    <div class="space-y-2">
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest ml-2 block">Assign Group Names</label>
                        <div class="bg-slate-50/50 rounded-[2rem] p-4 border border-slate-100 max-h-40 overflow-y-auto space-y-2">
                            @foreach($branchOptions ?? [] as $opt)
                            <label class="flex items-center gap-3 p-2.5 bg-white rounded-xl border border-slate-100 cursor-pointer hover:bg-slate-50 transition-colors">
                                <input type="checkbox" name="branches[]" value="{{ $opt }}" x-model="teamForm.branches" class="rounded border-slate-350 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs font-bold text-slate-750">{{ $opt }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full bg-emerald-655 text-white p-5 rounded-2xl font-black uppercase tracking-widest text-xs shadow-xl active:scale-95 transition-all mt-6" style="background-color: #059669;">
                    Save Team
                </button>
            </form>
        </div>
    </div>

    <!-- Hidden Delete Team Form -->
    <form id="deleteMobileTeamForm" method="POST" action="" class="hidden">
        @csrf
        @method('DELETE')
    </form>
    @endif

</div>

<script>
function collectionReportApp() {
    return {
        showFilters: {{ request()->has('fetch') ? 'false' : 'true' }},
        showTeamModal: false,
        isEditing: false,
        teamForm: {
            id: '',
            name: '',
            agents: [],
            branches: [],
            action: '{{ route('mobile.reports.collection.teams.store') }}'
        },
        openCreateTeamModal() {
            this.isEditing = false;
            this.teamForm = {
                id: '',
                name: '',
                agents: [],
                branches: [],
                action: '{{ route('mobile.reports.collection.teams.store') }}'
            };
            this.showTeamModal = true;
        },
        openEditTeamModal(team) {
            this.isEditing = true;
            this.teamForm = {
                id: team.id,
                name: team.name,
                agents: team.agents || [],
                branches: team.branches || [],
                action: '/mobile/collection/teams/' + team.id
            };
            this.showTeamModal = true;
        },
        deleteMobileTeam(id, name) {
            if (confirm('Are you sure you want to delete "' + name + '"?')) {
                const form = document.getElementById('deleteMobileTeamForm');
                form.action = '/mobile/collection/teams/' + id;
                form.submit();
            }
        }
    }
}

function toggleMobileTeamFilter(teamId) {
    const btn = document.getElementById('mob_btn_team_' + teamId);
    const form = document.getElementById('mobileFilterForm');
    let input = document.getElementById('mob_input_team_' + teamId);
    
    if (btn.classList.contains('bg-blue-500')) {
        btn.classList.remove('bg-blue-500', 'text-white', 'border-blue-600');
        btn.classList.add('bg-white', 'text-slate-700');
        if (input) input.remove();
    } else {
        btn.classList.add('bg-blue-500', 'text-white', 'border-blue-600');
        btn.classList.remove('bg-white', 'text-slate-700');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'teams[]';
            input.value = teamId;
            input.id = 'mob_input_team_' + teamId;
            form.appendChild(input);
        }
    }
}

function toggleMobAccordion(slug) {
    const body = document.getElementById(slug);
    const chev = document.getElementById(slug + '_chev');
    if (!body) return;
    if (body.classList.contains('hidden')) {
        body.classList.remove('hidden');
        if (chev) chev.style.transform = 'rotate(180deg)';
    } else {
        body.classList.add('hidden');
        if (chev) chev.style.transform = '';
    }
}

function toggleMobAgentDetail(slug) {
    const body = document.getElementById(slug);
    const chev = document.getElementById(slug + '_chev');
    if (!body) return;
    if (body.classList.contains('hidden')) {
        body.classList.remove('hidden');
        if (chev) chev.style.transform = 'rotate(180deg)';
    } else {
        body.classList.add('hidden');
        if (chev) chev.style.transform = '';
    }
}
</script>
@endsection
