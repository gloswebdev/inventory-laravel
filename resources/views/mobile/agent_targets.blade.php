@extends('layouts.mobile')

@section('content')
<div class="space-y-6 pb-20" x-data="mobileTargetsApp()">

    {{-- Success Flash Message --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-2xl px-4 py-3 text-xs font-semibold">
        <span>{{ session('success') }}</span>
    </div>
    @endif

    {{-- Header Banner --}}
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
            <div>
                <h2 class="text-xl font-black text-slate-800 tracking-tight">Set Targets</h2>
                <p class="text-[9px] font-black uppercase tracking-wider text-slate-400 mt-1">Teams & Agents</p>
            </div>
            <a href="{{ route('mobile.collection-report') }}" 
               class="w-11 h-11 bg-white text-slate-400 rounded-2xl flex items-center justify-center border border-white/60 shadow-md">
                <i class="fas fa-file-invoice text-xs"></i>
            </a>
        </div>
    </div>

    {{-- Month Filter Selector --}}
    <div class="bg-white/75 backdrop-blur-xl border border-white/60 shadow-sm p-5 rounded-[2.2rem]">
        <div class="flex items-center justify-between">
            <span class="text-xs font-black text-slate-500 uppercase tracking-widest">Target Month</span>
            <form method="GET" action="{{ route('mobile.agent-targets.index') }}" id="mobileMonthFilterForm">
                <input type="month" name="month" value="{{ $targetMonth }}" onchange="document.getElementById('mobileMonthFilterForm').submit()"
                    class="border border-gray-200 rounded-xl py-2 px-4 text-xs font-bold text-slate-700 outline-none cursor-pointer bg-white">
            </form>
        </div>
        
        {{-- Target Set Months status list --}}
        <div class="mt-3.5 pt-3.5 border-t border-gray-150/60">
            <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block mb-2">Months with targets set:</span>
            <div class="flex flex-wrap gap-2">
                @forelse($configuredMonths ?? [] as $m)
                    <a href="{{ route('mobile.agent-targets.index', ['month' => $m]) }}" 
                       class="px-2.5 py-1.5 rounded-xl text-[10px] font-bold border transition {{ $m === $targetMonth ? 'bg-indigo-600 text-white border-indigo-600 shadow' : 'bg-indigo-50/70 text-indigo-700 border-indigo-100' }}">
                        {{ \Carbon\Carbon::parse($m . '-01')->format('M Y') }}
                    </a>
                @empty
                    <span class="text-[10px] text-gray-300 italic">No targets set yet</span>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Tabs to Toggle between Teams Targets and Agents Targets --}}
    <div class="flex border-b border-gray-200 gap-2 px-2">
        <button onclick="switchMobileTab('mTeamTab', 'mAgentTab', this)" class="mob-tab-btn pb-2.5 px-3 font-black text-xs text-indigo-600 border-b-2 border-indigo-600 outline-none">
            Team Targets
        </button>
        <button onclick="switchMobileTab('mAgentTab', 'mTeamTab', this)" class="mob-tab-btn pb-2.5 px-3 font-bold text-xs text-slate-400 hover:text-slate-700 outline-none">
            Agent Targets
        </button>
    </div>

    {{-- ═══ 1. TEAMS TARGET SECTION ═══ --}}
    <div id="mTeamTab" class="mob-tab-content space-y-4">
        <form method="POST" action="{{ route('mobile.team-targets.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="month" value="{{ $targetMonth }}">

            <div class="space-y-3">
                @forelse($dbTeams ?? [] as $team)
                @php 
                    $tSlug = 'mob_t_target_acc_' . $team->id; 
                    $teamAgentsTotal = 0;
                    foreach($team->agents ?? [] as $member) {
                        $teamAgentsTotal += (float)($targets[$member] ?? 0);
                    }
                @endphp
                <div class="bg-white rounded-3xl border border-gray-150 overflow-hidden shadow-sm">
                    {{-- Accordion Header --}}
                    <div class="flex justify-between items-center p-4 bg-slate-50 cursor-pointer" onclick="toggleMobAccordion('{{ $tSlug }}')">
                        <div class="overflow-hidden pr-3">
                            <span class="font-bold text-xs text-slate-800 block truncate">{{ $team->name }}</span>
                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">
                                {{ count($team->agents ?? []) }} Members
                                <span id="total-agents-target-{{ $team->id }}">
                                    • Total: ₹{{ fmod($teamAgentsTotal, 1) == 0 ? number_format($teamAgentsTotal, 0) : number_format($teamAgentsTotal, 2) }}
                                </span>
                            </span>
                        </div>
                        <div class="flex items-center gap-3" onclick="event.stopPropagation()">
                            <input type="number" 
                                   name="targets[{{ $team->id }}]" 
                                   value="{{ $teamTargets[$team->id] ?? '' }}" 
                                   step="0.01" 
                                   placeholder="Set limit..."
                                   class="w-28 border border-gray-200 rounded-lg py-1.5 px-2.5 text-xs font-semibold text-slate-800 text-right outline-none bg-white">
                            <i class="fas fa-chevron-down text-[9px] text-slate-400 transition-transform" id="{{ $tSlug }}_chev"></i>
                        </div>
                    </div>

                    {{-- Accordion Body (Members targets inputs) --}}
                    <div id="{{ $tSlug }}" class="hidden border-t border-gray-100 p-3 bg-white space-y-3.5">
                        @forelse($team->agents ?? [] as $member)
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-600 block truncate max-w-[130px]">{{ $member }}</span>
                            <div class="relative">
                                <input type="number" 
                                       name="agent_targets[{{ $member }}]" 
                                       value="{{ $targets[$member] ?? '' }}" 
                                       step="0.01" 
                                       placeholder="₹ Member Target"
                                       oninput="updateTeamAgentsTotal({{ $team->id }})"
                                       class="w-36 border border-gray-200 rounded-lg py-1.5 px-2.5 text-xs font-semibold text-slate-850 text-right outline-none bg-slate-50/50 agent-target-input-{{ $team->id }}">
                            </div>
                        </div>
                        @empty
                        <div class="text-center text-slate-400 italic text-[11px]">No members assigned.</div>
                        @endforelse
                    </div>
                </div>
                @empty
                <div class="text-center py-10 text-slate-400 italic text-xs">
                    No teams configured yet.
                </div>
                @endforelse
            </div>

            @if(count($dbTeams ?? []) > 0)
            <button type="submit" class="w-full bg-indigo-600 text-white font-bold rounded-2xl py-4 text-xs shadow-md">
                Save Team & Member Targets
            </button>
            @endif
        </form>
    </div>

    {{-- ═══ 2. AGENTS TARGET SECTION ═══ --}}
    <div id="mAgentTab" class="mob-tab-content hidden space-y-4">
        <form method="POST" action="{{ route('mobile.agent-targets.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="month" value="{{ $targetMonth }}">

            <div class="bg-white rounded-3xl border border-gray-100 p-4 space-y-3.5 shadow-sm">
                @foreach($agentOptions as $agent)
                <div class="flex items-center justify-between">
                    <div class="overflow-hidden pr-2">
                        <span class="font-bold text-slate-800 text-xs block truncate">{{ $agent }}</span>
                    </div>
                    <input type="number" 
                           name="targets[{{ $agent }}]" 
                           value="{{ $targets[$agent] ?? '' }}" 
                           step="0.01" 
                           placeholder="Set target..."
                           class="w-36 border border-gray-200 rounded-lg py-1.5 px-2.5 text-xs font-semibold text-slate-800 text-right outline-none bg-slate-50/50">
                </div>
                @endforeach
            </div>

            <button type="submit" class="w-full bg-indigo-600 text-white font-bold rounded-2xl py-4 text-xs shadow-md">
                Save Agent Targets
            </button>
        </form>
    </div>

</div>

<script>
function mobileTargetsApp() {
    return {}
}

function switchMobileTab(activeId, inactiveId, btn) {
    document.querySelectorAll('.mob-tab-btn').forEach(b => {
        b.classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600', 'font-black');
        b.classList.add('text-slate-400', 'hover:text-slate-700', 'font-bold');
    });
    btn.classList.remove('text-slate-400', 'hover:text-slate-700', 'font-bold');
    btn.classList.add('text-indigo-600', 'border-b-2', 'border-indigo-600', 'font-black');

    document.getElementById(activeId).classList.remove('hidden');
    document.getElementById(inactiveId).classList.add('hidden');
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

function updateTeamAgentsTotal(teamId) {
    const inputs = document.querySelectorAll(`.agent-target-input-${teamId}`);
    let total = 0;
    inputs.forEach(input => {
        const val = parseFloat(input.value);
        if (!isNaN(val)) {
            total += val;
        }
    });
    const badge = document.getElementById(`total-agents-target-${teamId}`);
    if (badge) {
        const formattedTotal = total % 1 === 0 ? total.toLocaleString('en-IN') : total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        badge.innerHTML = ` • Total: ₹${formattedTotal}`;
    }
}
</script>
@endsection
