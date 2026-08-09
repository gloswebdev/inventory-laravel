@extends('layouts.mobile')

@section('content')
@php
    $formatCr = function($amount) {
        $amount = (float) $amount;
        if ($amount >= 10000000) return '₹' . number_format($amount / 10000000, 2) . 'Cr';
        if ($amount >= 100000) return '₹' . number_format($amount / 100000, 2) . 'L';
        if ($amount >= 1000) return '₹' . number_format($amount / 1000, 1) . 'K';
        return '₹' . number_format($amount, 0);
    };

    // Compute grand totals for hero card
    $grandTargetAmt = 0;
    $grandAgentTargets = 0;
    foreach($dbTeams ?? [] as $team) {
        if ($team->parent_id !== null) continue;
        $tAmt = (float)($teamTargets[$team->id] ?? 0);
        if ($tAmt == 0) {
            // Recursive: if a child team has its OWN team target, use that; otherwise sum agent targets
            $sumFn = function($node) use (&$sumFn, $dbTeams, $targets, $teamTargets) {
                $s = 0;
                foreach ($node->agents ?? [] as $ag) { $s += (float)($targets[$ag] ?? 0); }
                foreach ($dbTeams->where('parent_id', $node->id) as $ch) {
                    $chTeamTarget = (float)($teamTargets[$ch->id] ?? 0);
                    if ($chTeamTarget > 0) {
                        $s += $chTeamTarget; // Child team has its own target, use it
                    } else {
                        $s += $sumFn($ch); // Recurse further
                    }
                }
                return $s;
            };
            $tAmt = $sumFn($team);
        }
        $grandTargetAmt += $tAmt;
    }
    // Count agents with targets set
    $setCount = 0;
    $totalAgents = count($agentOptions ?? []);
    foreach ($targets ?? [] as $k => $v) { if ($v > 0) $setCount++; }
    $setPercent = $totalAgents > 0 ? round(($setCount / $totalAgents) * 100) : 0;

    // Hero card gradient
    if ($setPercent >= 80) {
        $heroGrad = 'from-emerald-500 via-green-500 to-teal-600';
        $heroBarColor = '#34d399';
        $heroLabel = 'WELL SET'; $heroIcon = 'fa-trophy';
        $heroBadgeBg = 'bg-emerald-400/30'; $heroBadgeText = 'text-emerald-200';
        $heroGlow = 'shadow-emerald-300/50';
    } elseif ($setPercent >= 50) {
        $heroGrad = 'from-amber-500 via-orange-500 to-amber-600';
        $heroBarColor = '#fbbf24';
        $heroLabel = 'IN PROGRESS'; $heroIcon = 'fa-fire';
        $heroBadgeBg = 'bg-amber-400/20'; $heroBadgeText = 'text-amber-200';
        $heroGlow = 'shadow-amber-300/40';
    } else {
        $heroGrad = 'from-indigo-500 via-indigo-600 to-violet-700';
        $heroBarColor = '#818cf8';
        $heroLabel = 'SETUP TARGETS'; $heroIcon = 'fa-bullseye';
        $heroBadgeBg = 'bg-indigo-400/20'; $heroBadgeText = 'text-indigo-200';
        $heroGlow = 'shadow-indigo-300/40';
    }
    $monthLabel = \Carbon\Carbon::parse($targetMonth . '-01')->format('M Y');
@endphp

<div class="space-y-4 pb-24" x-data="targetsDrillApp()">

    {{-- Success Flash Message --}}
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl px-4 py-3 text-xs font-bold flex items-center gap-2">
        <i class="fas fa-check-circle text-emerald-500"></i>
        {{ session('success') }}
    </div>
    @endif

    {{-- ===== HERO HEADER CARD ===== --}}
    <div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br {{ $heroGrad }} p-6 shadow-xl {{ $heroGlow }}">
        <div class="absolute -top-10 -right-10 w-52 h-52 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-36 h-36 bg-black/10 rounded-full blur-2xl"></div>
        
        <div class="relative z-10">
            {{-- Top bar --}}
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/30">
                        <i class="fas fa-bullseye text-white text-xs"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-white tracking-tight leading-none">Targets</h2>
                        <p class="text-[8px] font-black uppercase tracking-[0.2em] text-white/60 mt-0.5">{{ $monthLabel }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('mobile.collection-report') }}" 
                       class="w-10 h-10 bg-white/15 text-white border border-white/20 rounded-2xl flex items-center justify-center active:scale-90 transition">
                        <i class="fas fa-chart-bar text-xs"></i>
                    </a>
                </div>
            </div>

            {{-- Stats --}}
            <div class="bg-black/20 backdrop-blur-sm border border-white/15 rounded-3xl p-4">
                <div class="flex items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="text-[8px] font-black text-white/50 uppercase tracking-widest mb-0.5">Grand Target</div>
                        <div class="text-3xl font-black text-white leading-none tracking-tight">{{ $formatCr($grandTargetAmt) }}</div>
                        <div class="mt-2 inline-flex items-center gap-1.5 {{ $heroBadgeBg }} border border-white/20 px-2.5 py-1 rounded-xl">
                            <i class="fas {{ $heroIcon }} {{ $heroBadgeText }} text-[8px]"></i>
                            <span class="text-[8px] font-black {{ $heroBadgeText }} tracking-widest">{{ $heroLabel }}</span>
                        </div>
                    </div>
                </div>
                
                {{-- Progress --}}
                <div class="mt-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[8px] text-white/40 font-bold uppercase tracking-widest">Agents Configured</span>
                        <span class="text-[8px] font-black text-white/70">{{ $setCount }}/{{ $totalAgents }}</span>
                    </div>
                    <div class="w-full bg-white/10 rounded-full h-3 overflow-hidden relative">
                        <div class="h-3 rounded-full relative overflow-hidden transition-all duration-1000"
                             style="width: {{ $setPercent }}%; background: {{ $heroBarColor }};">
                            <div class="absolute inset-0 shimmer-anim-t"></div>
                        </div>
                    </div>
                </div>

                {{-- Stat pills --}}
                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-white/10">
                    <div class="flex-1 bg-white/10 rounded-2xl py-2 px-3 text-center">
                        <div class="text-sm font-black text-white">{{ count($dbTeams ?? []) }}</div>
                        <div class="text-[7px] font-black text-white/50 uppercase tracking-widest mt-0.5">Teams</div>
                    </div>
                    <div class="flex-1 bg-white/10 rounded-2xl py-2 px-3 text-center">
                        <div class="text-sm font-black text-white">{{ $totalAgents }}</div>
                        <div class="text-[7px] font-black text-white/50 uppercase tracking-widest mt-0.5">Agents</div>
                    </div>
                    <div class="flex-1 bg-white/10 rounded-2xl py-2 px-3 text-center">
                        <div class="text-sm font-black text-white">{{ $setPercent }}%</div>
                        <div class="text-[7px] font-black text-white/50 uppercase tracking-widest mt-0.5">Setup</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Month Filter --}}
    <div class="bg-white/75 backdrop-blur-xl border border-white/60 shadow-sm p-5 rounded-[2.2rem]">
        <div class="flex items-center justify-between">
            <span class="text-xs font-black text-slate-500 uppercase tracking-widest">Target Month</span>
            <form method="GET" action="{{ route('mobile.agent-targets.index') }}" id="mobileMonthFilterForm">
                <input type="month" name="month" value="{{ $targetMonth }}" onchange="document.getElementById('mobileMonthFilterForm').submit()"
                    class="border border-gray-200 rounded-xl py-2 px-4 text-xs font-bold text-slate-700 outline-none cursor-pointer bg-white">
            </form>
        </div>
        @if(count($configuredMonths ?? []) > 0)
        <div class="mt-3.5 pt-3.5 border-t border-gray-150/60">
            <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block mb-2">Months with targets set:</span>
            <div class="flex flex-wrap gap-2">
                @foreach($configuredMonths as $m)
                    <a href="{{ route('mobile.agent-targets.index', ['month' => $m]) }}" 
                       class="px-2.5 py-1.5 rounded-xl text-[10px] font-bold border transition {{ $m === $targetMonth ? 'bg-indigo-600 text-white border-indigo-600 shadow' : 'bg-indigo-50/70 text-indigo-700 border-indigo-100' }}">
                        {{ \Carbon\Carbon::parse($m . '-01')->format('M Y') }}
                    </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Breadcrumb --}}
    <div class="bg-white/90 backdrop-blur-xl border border-slate-100 rounded-3xl p-4 shadow-sm flex items-center justify-between" x-show="currentParentId !== 'root'" x-cloak>
        <div class="flex items-center flex-wrap gap-2 text-xs font-bold text-slate-500">
            <button type="button" @click="goToLevel('root')" class="text-indigo-600 hover:text-indigo-800 transition">All Teams</button>
            <template x-for="(crumb, idx) in history" :key="idx">
                <div class="flex items-center gap-2">
                    <i class="fas fa-chevron-right text-[8px] text-slate-300"></i>
                    <button type="button" @click="goToLevel(crumb.id, crumb.title)" class="text-indigo-600 hover:text-indigo-800 transition" x-text="crumb.title"></button>
                </div>
            </template>
            <i class="fas fa-chevron-right text-[8px] text-slate-300"></i>
            <span class="text-slate-800 font-black" x-text="currentTitle"></span>
        </div>
        <button type="button" @click="goBack()" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-[10px] font-black transition active:scale-95">
            <i class="fas fa-arrow-left text-[8px]"></i> Back
        </button>
    </div>

    {{-- Quick Stats Row (root only) --}}
    <div class="grid grid-cols-3 gap-3" x-show="currentParentId === 'root'">
        <div class="bg-white/80 backdrop-blur-xl border border-white/60 rounded-2xl p-3.5 text-center shadow-sm">
            <div class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Grand Target</div>
            <div class="text-base font-black text-indigo-600">{{ $formatCr($grandTargetAmt) }}</div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl border border-white/60 rounded-2xl p-3.5 text-center shadow-sm">
            <div class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Teams</div>
            <div class="text-base font-black text-slate-800">{{ count($dbTeams ?? []) }}</div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl border border-white/60 rounded-2xl p-3.5 text-center shadow-sm">
            <div class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Month</div>
            <div class="text-[11px] font-black text-indigo-600 leading-tight mt-0.5">{{ $monthLabel }}</div>
        </div>
    </div>

    {{-- ===== DRILL-DOWN LIST ===== --}}
    <form method="POST" action="{{ route('mobile.team-targets.store') }}" id="targetsDrillForm">
        @csrf
        <input type="hidden" name="month" value="{{ $targetMonth }}">

        <div class="space-y-3">
            {{-- 1. Database Teams (Root and Sub-Teams) --}}
            @foreach($dbTeams as $team)
            @php
                $teamName = $team->name;
                $tTargetAmt = (float)($teamTargets[$team->id] ?? 0);
                
                // Sum targets recursively (includes child team targets + agent targets)
                $sumAgentTargets = function($tNode) use (&$sumAgentTargets, $dbTeams, $targets, $teamTargets) {
                    $sum = 0;
                    foreach ($tNode->agents ?? [] as $ag) {
                        $sum += (float)($targets[$ag] ?? 0);
                    }
                    foreach ($dbTeams->where('parent_id', $tNode->id) as $chNode) {
                        $chTeamTarget = (float)($teamTargets[$chNode->id] ?? 0);
                        if ($chTeamTarget > 0) {
                            $sum += $chTeamTarget; // Child team has own target
                        } else {
                            $sum += $sumAgentTargets($chNode); // Recurse further
                        }
                    }
                    return $sum;
                };
                $agentTargetSum = $sumAgentTargets($team);
                $effectiveTarget = $tTargetAmt > 0 ? $tTargetAmt : $agentTargetSum;
                
                $memberCount = count($team->agents ?? []);
                $membersWithTarget = 0;
                foreach ($team->agents ?? [] as $ag) {
                    if (($targets[$ag] ?? 0) > 0) $membersWithTarget++;
                }
                $setupPercent = $memberCount > 0 ? round(($membersWithTarget / $memberCount) * 100) : 0;
                
                // Choose color scheme
                if ($effectiveTarget == 0) {
                    $cardBg = 'bg-gradient-to-br from-slate-50 to-white';
                    $cardBorder = 'border-slate-150';
                    $percentText = 'No Target';
                    $percentColor = 'text-slate-500';
                    $progressColor = 'bg-slate-200';
                    $iconBg = 'bg-slate-100 text-slate-500 border-slate-200';
                    $nameColor = 'text-slate-800';
                    $infoColor = 'text-slate-400';
                    $chevBg = 'bg-slate-50 border-slate-150 text-slate-400';
                    $progressBg = 'bg-slate-100';
                } elseif ($setupPercent >= 100) {
                    $cardBg = 'bg-gradient-to-br from-emerald-50 via-white to-emerald-50/20';
                    $cardBorder = 'border-emerald-200/80';
                    $percentText = '100%';
                    $percentColor = 'text-emerald-600';
                    $progressColor = 'bg-emerald-400';
                    $iconBg = 'bg-emerald-100/80 text-emerald-600 border-emerald-200/50';
                    $nameColor = 'text-emerald-950';
                    $infoColor = 'text-emerald-700/80';
                    $chevBg = 'bg-emerald-100/30 border-emerald-200/30 text-emerald-600';
                    $progressBg = 'bg-emerald-100/50';
                } elseif ($setupPercent >= 50) {
                    $cardBg = 'bg-gradient-to-br from-amber-50 via-white to-amber-50/20';
                    $cardBorder = 'border-amber-200/80';
                    $percentText = $setupPercent . '%';
                    $percentColor = 'text-amber-600';
                    $progressColor = 'bg-amber-400';
                    $iconBg = 'bg-amber-100/80 text-amber-600 border-amber-200/50';
                    $nameColor = 'text-amber-950';
                    $infoColor = 'text-amber-700/80';
                    $chevBg = 'bg-amber-100/30 border-amber-200/30 text-amber-600';
                    $progressBg = 'bg-amber-100/50';
                } else {
                    $cardBg = 'bg-gradient-to-br from-rose-50 via-white to-rose-50/20';
                    $cardBorder = 'border-rose-200/60';
                    $percentText = $setupPercent . '%';
                    $percentColor = 'text-rose-600';
                    $progressColor = 'bg-rose-500';
                    $iconBg = 'bg-rose-100/80 text-rose-500 border-rose-200/40';
                    $nameColor = 'text-rose-950';
                    $infoColor = 'text-rose-700/80';
                    $chevBg = 'bg-rose-100/30 border-rose-200/30 text-rose-600';
                    $progressBg = 'bg-rose-100/50';
                }
                
                $childrenTeams = $dbTeams->where('parent_id', $team->id);
                $hasChildren = $childrenTeams->count() > 0;
                $hasAgents = $memberCount > 0;
                $isRoot = $team->parent_id === null;
                $parentIdStr = $team->parent_id ? (string)$team->parent_id : 'root';
            @endphp

            <div x-show="currentParentId === '{{ $parentIdStr }}'" x-transition x-cloak
                 style="animation-delay: {{ $loop->index * 0.05 }}s;"
                 class="overflow-hidden rounded-[1.8rem] shadow-sm border {{ $cardBorder }} {{ $cardBg }} backdrop-blur-xl transition-all duration-300 hover:scale-[1.01] hover:-translate-y-0.5 active:scale-[0.99] animate-card-entry-t">
                
                @if($hasChildren || $hasAgents)
                <button type="button" @click="drillDown('{{ $team->id }}', '{{ addslashes($teamName) }}')" class="w-full text-left">
                @else
                <div class="w-full text-left">
                @endif
                    <div class="p-4 relative">
                        @if($effectiveTarget > 0)
                        <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full blur-xl opacity-10 {{ $setupPercent >= 100 ? 'bg-emerald-400' : ($setupPercent >= 50 ? 'bg-amber-400' : 'bg-rose-400') }}"></div>
                        @endif
                        
                        <div class="flex items-center justify-between relative z-10">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center border flex-shrink-0 {{ $iconBg }}">
                                    <i class="fas {{ $isRoot ? 'fa-globe' : ($hasChildren ? 'fa-map-marker-alt' : 'fa-users') }} text-sm"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-black {{ $nameColor }} text-sm tracking-tight truncate uppercase flex items-center gap-1.5">
                                        {{ $teamName }}
                                        @if($effectiveTarget > 0)
                                        <span class="text-[8px] font-black tracking-widest px-2 py-0.5 rounded-lg {{ $setupPercent >= 100 ? 'bg-emerald-500/10 text-emerald-700 border border-emerald-500/20' : ($setupPercent >= 50 ? 'bg-amber-500/10 text-amber-700 border border-amber-500/20' : 'bg-rose-500/10 text-rose-700 border border-rose-500/15') }}">
                                            {{ $percentText }}
                                        </span>
                                        @endif
                                    </div>
                                    <div class="text-[9px] {{ $infoColor }} font-bold mt-0.5">
                                        {{ $memberCount }} Agents &middot; {{ $membersWithTarget }}/{{ $memberCount }} Set
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2 ml-2 flex-shrink-0 relative z-10">
                                <div class="text-right">
                                    <div class="text-slate-400 font-bold text-[8px] uppercase tracking-wider">Target</div>
                                    <div class="text-indigo-600 font-black text-base leading-none mt-0.5">{{ $formatCr($effectiveTarget) }}</div>
                                </div>
                                @if($hasChildren || $hasAgents)
                                <div class="w-8 h-8 rounded-xl flex items-center justify-center transition-all {{ $chevBg }}">
                                    <i class="fas fa-chevron-right text-[10px]"></i>
                                </div>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Progress bar --}}
                        <div class="mt-3.5 pt-3 border-t border-slate-100 relative z-10">
                            <div class="flex items-center justify-between mb-1.5 text-[8px] font-bold uppercase tracking-widest {{ $infoColor }}">
                                <span>Team Target: <strong class="{{ $nameColor }}">{{ $tTargetAmt > 0 ? $formatCr($tTargetAmt) : '—' }}</strong></span>
                                <span>Agents: <strong class="{{ $percentColor }}">{{ $formatCr($agentTargetSum) }}</strong></span>
                            </div>
                            <div class="w-full {{ $progressBg }} rounded-full h-2 overflow-hidden relative border border-slate-100">
                                <div class="h-2 rounded-full relative overflow-hidden transition-all duration-1000 progress-glow-bar-t {{ $progressColor }}"
                                     style="width: {{ $setupPercent > 0 ? $setupPercent : 0 }}%">
                                    <div class="absolute inset-0 shimmer-anim-t"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @if($hasChildren || $hasAgents)
                </button>
                @else
                </div>
                @endif
            </div>
            @endforeach

            {{-- 2. Agents under each team --}}
            @foreach($dbTeams as $team)
                @php $teamName = $team->name; @endphp
                @foreach($team->agents ?? [] as $agentName)
                @php
                    $agentTarget = (float)($targets[$agentName] ?? 0);
                    $agentId = 'agent_' . $team->id . '_' . Str::slug($agentName);
                    
                    if ($agentTarget > 0) {
                        $agentCardBg = 'bg-gradient-to-br from-emerald-50 via-white to-emerald-50/20';
                        $agentCardBorder = 'border-emerald-200/80';
                        $agentIconBg = 'bg-emerald-100/80 text-emerald-600 border-emerald-200/50';
                        $agentNameColor = 'text-emerald-950';
                        $agentInfoColor = 'text-emerald-700/80';
                        $agentProgressColor = 'bg-emerald-400';
                        $agentProgressBg = 'bg-emerald-100/50';
                    } else {
                        $agentCardBg = 'bg-gradient-to-br from-slate-50 to-white';
                        $agentCardBorder = 'border-slate-150';
                        $agentIconBg = 'bg-slate-100 text-slate-500 border-slate-200';
                        $agentNameColor = 'text-slate-800';
                        $agentInfoColor = 'text-slate-400';
                        $agentProgressColor = 'bg-slate-200';
                        $agentProgressBg = 'bg-slate-100';
                    }
                @endphp
                <div x-show="currentParentId === '{{ $team->id }}'" x-transition x-cloak
                     style="animation-delay: {{ $loop->index * 0.05 }}s;"
                     class="overflow-hidden rounded-[1.8rem] shadow-sm border {{ $agentCardBorder }} {{ $agentCardBg }} backdrop-blur-xl transition-all duration-300 hover:scale-[1.01] active:scale-[0.99] animate-card-entry-t">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center border flex-shrink-0 {{ $agentIconBg }}">
                                    <i class="fas fa-user text-sm"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-black {{ $agentNameColor }} text-sm tracking-tight truncate">{{ $agentName }}</div>
                                    <div class="text-[9px] {{ $agentInfoColor }} font-bold mt-0.5">
                                        @if($agentTarget > 0)
                                        Target: <strong>{{ $formatCr($agentTarget) }}</strong>
                                        @else
                                        <span class="text-slate-400 italic">No target set</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2 flex-shrink-0" onclick="event.stopPropagation()">
                                <input type="number" 
                                       name="agent_targets[{{ $agentName }}]" 
                                       value="{{ $targets[$agentName] ?? '' }}" 
                                       step="0.01" 
                                       placeholder="₹ Target"
                                       oninput="recalcTeamTotal('{{ $team->id }}')"
                                       class="w-28 border border-gray-200 rounded-xl py-2 px-3 text-xs font-bold text-slate-800 text-right outline-none bg-white/80 focus:ring-2 focus:ring-indigo-300 transition agent-input-{{ $team->id }}">
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            @endforeach

            {{-- Team target input card (shown inside drill-down for team) --}}
            @foreach($dbTeams as $team)
            @php $hasAgents = count($team->agents ?? []) > 0; @endphp
            @if($hasAgents)
            <div x-show="currentParentId === '{{ $team->id }}'" x-transition x-cloak
                 class="overflow-hidden rounded-[1.8rem] shadow-sm border border-indigo-200/80 bg-gradient-to-br from-indigo-50 via-white to-indigo-50/20 backdrop-blur-xl animate-card-entry-t">
                <div class="p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center border bg-indigo-100/80 text-indigo-600 border-indigo-200/50">
                                <i class="fas fa-bullseye text-sm"></i>
                            </div>
                            <div>
                                <div class="font-black text-indigo-800 text-sm tracking-tight">Team Target</div>
                                <div class="text-[9px] text-indigo-500 font-bold mt-0.5" id="team-agent-sum-{{ $team->id }}">
                                    Agent Sum: {{ $formatCr(collect($team->agents ?? [])->sum(fn($ag) => (float)($targets[$ag] ?? 0))) }}
                                </div>
                            </div>
                        </div>
                        <input type="number" 
                               name="targets[{{ $team->id }}]" 
                               value="{{ $teamTargets[$team->id] ?? '' }}" 
                               step="0.01" 
                               placeholder="₹ Team Target"
                               class="w-32 border border-indigo-200 rounded-xl py-2 px-3 text-xs font-bold text-indigo-800 text-right outline-none bg-white/80 focus:ring-2 focus:ring-indigo-300 transition">
                    </div>
                </div>
            </div>

            {{-- Save button inside drill-down --}}
            <div x-show="currentParentId === '{{ $team->id }}'" x-transition x-cloak>
                <button type="submit"
                        class="w-full bg-gradient-to-r from-indigo-500 to-violet-600 text-white font-black text-xs uppercase tracking-widest rounded-2xl py-4 shadow-lg shadow-indigo-200 active:scale-95 transition-transform flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i>
                    Save {{ $team->name }} Targets
                </button>
            </div>
            @endif
            @endforeach

            {{-- Root-level Save button --}}
            <div x-show="currentParentId === 'root'" x-cloak>
                @if(count($dbTeams ?? []) > 0)
                <button type="submit"
                        class="w-full bg-gradient-to-r from-indigo-500 to-violet-600 text-white font-black text-xs uppercase tracking-widest rounded-2xl py-4 shadow-lg shadow-indigo-200 active:scale-95 transition-transform flex items-center justify-center gap-2 mt-2">
                    <i class="fas fa-save"></i>
                    Save All Targets
                </button>
                @endif
            </div>
        </div>
    </form>
</div>

{{-- Styles --}}
<style>
@keyframes slideUpFadeT {
    from { opacity: 0; transform: translateY(16px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-card-entry-t {
    animation: slideUpFadeT 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
.shimmer-anim-t {
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.45) 50%, transparent 100%);
    background-size: 300px 100%;
    animation: shimmerT 2.2s infinite linear;
}
@keyframes shimmerT {
    0% { background-position: -300px 0; }
    100% { background-position: 300px 0; }
}
.progress-glow-bar-t {
    box-shadow: 0 0 8px currentColor;
}
</style>

<script>
function targetsDrillApp() {
    return {
        currentParentId: 'root',
        currentTitle: 'All Teams',
        history: [],
        
        drillDown(parentId, title) {
            this.history.push({
                id: this.currentParentId,
                title: this.currentTitle
            });
            this.currentParentId = parentId;
            this.currentTitle = title;
        },
        
        goBack() {
            if (this.history.length > 0) {
                const prev = this.history.pop();
                this.currentParentId = prev.id;
                this.currentTitle = prev.title;
            }
        },
        
        goToLevel(id, title) {
            if (id === 'root') {
                this.currentParentId = 'root';
                this.currentTitle = 'All Teams';
                this.history = [];
            } else {
                const index = this.history.findIndex(h => h.id === id);
                if (index !== -1) {
                    this.currentParentId = id;
                    this.currentTitle = title || 'Detail';
                    this.history = this.history.slice(0, index);
                }
            }
        }
    };
}

function recalcTeamTotal(teamId) {
    const inputs = document.querySelectorAll(`.agent-input-${teamId}`);
    let total = 0;
    inputs.forEach(input => {
        const val = parseFloat(input.value);
        if (!isNaN(val)) total += val;
    });
    const badge = document.getElementById(`team-agent-sum-${teamId}`);
    if (badge) {
        let formatted;
        if (total >= 10000000) formatted = '₹' + (total/10000000).toFixed(2) + 'Cr';
        else if (total >= 100000) formatted = '₹' + (total/100000).toFixed(2) + 'L';
        else if (total >= 1000) formatted = '₹' + (total/1000).toFixed(1) + 'K';
        else formatted = '₹' + total.toLocaleString('en-IN');
        badge.innerHTML = `Agent Sum: ${formatted}`;
    }
}
</script>
@endsection
