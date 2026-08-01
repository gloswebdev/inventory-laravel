@extends('layouts.app')
@section('header', 'Targets Configuration (Teams & Agents)')

@section('content')
<div class="space-y-6">

    {{-- Success Flash Message --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-2xl px-5 py-3.5 flex items-center gap-3 text-sm font-semibold">
        <i class="fas fa-circle-check text-green-500 text-base"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    {{-- Info Header --}}
    <div class="bg-gradient-to-br from-indigo-700 to-violet-800 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden">
        <div class="absolute -right-8 -top-8 w-44 h-44 bg-white/10 rounded-full blur-xl"></div>
        <div class="absolute -left-6 -bottom-6 w-32 h-32 bg-white/5 rounded-full blur-lg"></div>
        <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-white/15 border border-white/20 flex items-center justify-center shadow-lg">
                    <i class="fas fa-bullseye text-2xl text-violet-200"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Sales targets setting</h1>
                    <p class="text-violet-200 text-xs font-bold uppercase tracking-widest mt-0.5">
                        Define and Edit Monthly Goals for Teams and Agents
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Month Filter Selector --}}
    <div class="bg-white rounded-3xl shadow-sm border border-gray-150 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest">Select Target Month</h3>
            <p class="text-xs text-slate-400 mt-1">Goal settings automatically refresh for the chosen month.</p>
        </div>
        <form method="GET" action="{{ route('reports.agent-targets.index') }}" id="monthFilterForm">
            <input type="month" name="month" value="{{ $targetMonth }}" onchange="document.getElementById('monthFilterForm').submit()"
                class="border border-gray-200 rounded-2xl py-3 px-5 text-sm font-semibold focus:ring-2 focus:ring-indigo-400 outline-none transition bg-slate-50/50 cursor-pointer">
        </form>
    </div>

    {{-- Tabs to Toggle between Teams Targets and Agents Targets --}}
    <div class="flex border-b border-gray-250 gap-4">
        <button onclick="switchTab('teamTab', 'agentTab', this)" class="tab-btn pb-3 px-4 font-black text-sm text-indigo-600 border-b-2 border-indigo-600 outline-none">
            <i class="fas fa-people-group mr-1.5"></i> Team Targets
        </button>
        <button onclick="switchTab('agentTab', 'teamTab', this)" class="tab-btn pb-3 px-4 font-bold text-sm text-slate-400 hover:text-slate-700 outline-none">
            <i class="fas fa-user-tie mr-1.5"></i> Agent Targets
        </button>
    </div>

    {{-- ═══ 1. TEAMS TARGET SECTION ═══ --}}
    <div id="teamTab" class="tab-content space-y-6">
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-xs font-black text-slate-600 uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-users-gear text-indigo-500"></i>
                    Team goals allocation (Month: {{ \Carbon\Carbon::parse($targetMonth.'-01')->format('F Y') }})
                </h3>
            </div>

            <form method="POST" action="{{ route('reports.team-targets.store') }}" class="p-6">
                @csrf
                <input type="hidden" name="month" value="{{ $targetMonth }}">

                <div class="space-y-4">
                    @forelse($dbTeams ?? [] as $tIdx => $team)
                    @php $tSlug = 'team_acc_' . $team->id; @endphp
                    <div class="border border-gray-150 rounded-2xl overflow-hidden shadow-sm">
                        {{-- Accordion Header --}}
                        <div class="flex flex-col md:flex-row md:items-center justify-between px-6 py-4 bg-slate-50 hover:bg-slate-100/70 transition cursor-pointer select-none gap-4"
                             onclick="toggleAccordion('{{ $tSlug }}')">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                                    <i class="fas fa-people-group text-xs"></i>
                                </div>
                                <div>
                                    <span class="font-bold text-slate-800 text-sm block">{{ $team->name }}</span>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                                        👉 Click to view & edit targets for {{ count($team->agents ?? []) }} Members
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-4" onclick="event.stopPropagation()">
                                <div class="relative rounded-2xl shadow-sm w-60">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 font-bold text-xs">
                                        ₹
                                    </div>
                                    <input type="number" 
                                           name="targets[{{ $team->id }}]" 
                                           value="{{ $teamTargets[$team->id] ?? '' }}" 
                                           step="0.01" 
                                           placeholder="Set team target limit..."
                                           class="block w-full rounded-2xl border border-gray-200 py-2.5 pl-8 pr-4 text-sm font-semibold text-slate-800 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 outline-none transition bg-white">
                                </div>
                                <i class="fas fa-chevron-down text-slate-400 text-xs transition-transform duration-200 accordion-chev" id="{{ $tSlug }}_chev"></i>
                            </div>
                        </div>

                        {{-- Accordion Body (Team Members with Target inputs) --}}
                        <div id="{{ $tSlug }}" class="hidden border-t border-gray-150 bg-white">
                            <div class="p-5">
                                <div class="bg-indigo-50/40 text-indigo-850 px-4 py-2 text-xs font-bold rounded-xl mb-3 flex items-center gap-1.5">
                                    <i class="fas fa-user-gear"></i> Set targets for individual members in {{ $team->name }}
                                </div>
                                <table class="min-w-full text-left border-collapse text-xs">
                                    <thead class="bg-slate-50 text-[9px] font-black text-slate-500 uppercase tracking-widest border-b border-gray-100">
                                        <tr>
                                            <th class="py-2.5 px-4 text-center w-12">#</th>
                                            <th class="py-2.5 px-4">Member Name</th>
                                            <th class="py-2.5 px-4 text-right w-64">Member Target (₹)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        @php $mIdx = 0; @endphp
                                        @forelse($team->agents ?? [] as $member)
                                        @php $mIdx++; @endphp
                                        <tr>
                                            <td class="py-2.5 px-4 text-gray-400 font-bold text-center">{{ $mIdx }}</td>
                                            <td class="py-2.5 px-4 font-bold text-slate-700">{{ $member }}</td>
                                            <td class="py-2.5 px-4">
                                                <div class="relative rounded-xl shadow-sm">
                                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 font-bold text-xs">
                                                        ₹
                                                    </div>
                                                    <input type="number" 
                                                           name="agent_targets[{{ $member }}]" 
                                                           value="{{ $targets[$member] ?? '' }}" 
                                                           step="0.01" 
                                                           placeholder="Set member target..."
                                                           class="block w-full rounded-xl border border-gray-200 py-1.5 pl-6 pr-3 text-xs font-semibold text-slate-800 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-150 outline-none transition bg-white">
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="3" class="py-4 text-center text-slate-400 italic">No assigned members in this team.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="py-8 text-center text-slate-400 italic text-xs">
                        No custom teams created yet. Define teams on the Collection Report page first.
                    </div>
                    @endforelse
                </div>

                @if(count($dbTeams ?? []) > 0)
                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-black py-3 px-8 rounded-2xl shadow-lg hover:shadow-indigo-150 text-sm tracking-wide transition transform active:scale-98">
                        <i class="fas fa-circle-check mr-2"></i> Save Team & Member Targets
                    </button>
                </div>
                @endif
            </form>
        </div>
    </div>

    {{-- ═══ 2. AGENTS TARGET SECTION ═══ --}}
    <div id="agentTab" class="tab-content hidden space-y-6">
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-xs font-black text-slate-600 uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-user-tie text-indigo-500"></i>
                    Agent goals allocation (Month: {{ \Carbon\Carbon::parse($targetMonth.'-01')->format('F Y') }})
                </h3>
            </div>

            <form method="POST" action="{{ route('reports.agent-targets.store') }}" class="p-6">
                @csrf
                <input type="hidden" name="month" value="{{ $targetMonth }}">

                <div class="overflow-x-auto rounded-2xl border border-gray-100">
                    <table class="min-w-full text-left border-collapse">
                        <thead class="bg-slate-50/80 border-b border-gray-100 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                            <tr>
                                <th class="py-3 px-6 text-center w-16">#</th>
                                <th class="py-3 px-6">Salesman / Agent Name</th>
                                <th class="py-3 px-6 text-right w-72">Collection Target (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($agentOptions as $idx => $agent)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3.5 px-6 text-gray-400 font-bold text-center">{{ $idx + 1 }}</td>
                                <td class="py-3.5 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-violet-50 flex items-center justify-center text-violet-600">
                                            <i class="fas fa-user-tie text-xs"></i>
                                        </div>
                                        <span class="font-bold text-slate-800 text-sm">{{ $agent }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-6">
                                    <div class="relative rounded-2xl shadow-sm">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 font-bold text-xs">
                                            ₹
                                        </div>
                                        <input type="number" 
                                               name="targets[{{ $agent }}]" 
                                               value="{{ $targets[$agent] ?? '' }}" 
                                               step="0.01" 
                                               placeholder="Enter goal limit..."
                                               class="block w-full rounded-2xl border border-gray-200 py-2.5 pl-8 pr-4 text-sm font-semibold text-slate-800 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 outline-none transition bg-white">
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-black py-3 px-8 rounded-2xl shadow-lg hover:shadow-indigo-150 text-sm tracking-wide transition transform active:scale-98">
                        <i class="fas fa-circle-check mr-2"></i> Save Agent Targets
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function switchTab(activeId, inactiveId, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600', 'font-black');
        b.classList.add('text-slate-400', 'hover:text-slate-700', 'font-bold');
    });
    btn.classList.remove('text-slate-400', 'hover:text-slate-700', 'font-bold');
    btn.classList.add('text-indigo-600', 'border-b-2', 'border-indigo-600', 'font-black');

    document.getElementById(activeId).classList.remove('hidden');
    document.getElementById(inactiveId).classList.add('hidden');
}

function toggleAccordion(slug) {
    const el = document.getElementById(slug);
    const chev = document.getElementById(slug + '_chev');
    if (!el) return;
    const isHidden = el.classList.contains('hidden');
    if (isHidden) {
        el.classList.remove('hidden');
        if (chev) chev.style.transform = 'rotate(180deg)';
    } else {
        el.classList.add('hidden');
        if (chev) chev.style.transform = '';
    }
}
</script>
@endsection
