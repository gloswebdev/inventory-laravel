@extends('layouts.mobile')

@section('content')
@php
    $parseAmt = fn($v) => is_numeric(str_replace(',', '', (string)$v)) ? (float)str_replace(',', '', (string)$v) : 0;

    $formatIndian = function($num) {
        $num = str_replace([',', ' '], '', (string)$num);
        if (!is_numeric($num)) return $num;
        $num = round((float)$num);
        $numStr = (string)$num;
        $isNeg = str_starts_with($numStr, '-');
        if ($isNeg) $numStr = substr($numStr, 1);
        $len = strlen($numStr);
        if ($len <= 3) return ($isNeg ? '-' : '') . $numStr;
        $lastThree = substr($numStr, -3);
        $remaining = substr($numStr, 0, -3);
        $groups = [];
        while (strlen($remaining) > 0) {
            if (strlen($remaining) > 2) { $groups[] = substr($remaining, -2); $remaining = substr($remaining, 0, -2); }
            else { $groups[] = $remaining; $remaining = ''; }
        }
        $groups = array_reverse($groups);
        return ($isNeg ? '-' : '') . implode(',', $groups) . ',' . $lastThree;
    };

    $formatCr = function($num) use ($formatIndian) {
        $n = is_numeric(str_replace(',', '', (string)$num)) ? (float)str_replace(',', '', (string)$num) : 0;
        if ($n >= 10000000) return '₹' . round($n / 10000000, 2) . 'Cr';
        if ($n >= 100000) return '₹' . round($n / 100000, 2) . 'L';
        return '₹' . $formatIndian($n);
    };
    
    // Month name helper
    $monthName = isset($monthFilter) ? date('M Y', strtotime($monthFilter . '-01')) : date('M Y');


@endphp

<div class="space-y-5 pb-28" x-data="collectionApp()">

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-3xl p-4 flex items-center gap-3 text-xs font-bold">
        <div class="w-8 h-8 bg-emerald-500 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-check text-white text-xs"></i>
        </div>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-3xl p-4 flex items-center gap-3 text-xs font-bold">
        <div class="w-8 h-8 bg-rose-500 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-exclamation text-white text-xs"></i>
        </div>
        {{ session('error') }}
    </div>
    @endif

    {{-- ===== HERO HEADER CARD ===== --}}
    @php
        $hPercent    = (isset($grandTotal, $grandTarget) && $grandTarget > 0)
                        ? min(100, round(($grandTotal / $grandTarget) * 100))
                        : 0;
        // Dynamic colour tokens based on achievement %
        if ($hPercent >= 100) {
            $hGradFrom   = 'from-emerald-500'; $hGradVia = 'via-green-500'; $hGradTo = 'to-teal-600';
            $hBarColor   = '#34d399';   // emerald-400
            $hBadgeBg    = 'bg-emerald-400/30'; $hBadgeText = 'text-emerald-200'; $hBadgeBorder = 'border-emerald-300/40';
            $hLabel      = 'TARGET HIT!'; $hIcon = 'fa-trophy'; $hGlow = 'shadow-emerald-300/50';
        } elseif ($hPercent >= 75) {
            $hGradFrom   = 'from-emerald-500'; $hGradVia = 'via-emerald-600'; $hGradTo = 'to-teal-700';
            $hBarColor   = '#34d399';
            $hBadgeBg    = 'bg-emerald-400/20'; $hBadgeText = 'text-emerald-200'; $hBadgeBorder = 'border-emerald-300/30';
            $hLabel      = 'ON TRACK'; $hIcon = 'fa-chart-line'; $hGlow = 'shadow-emerald-300/40';
        } elseif ($hPercent >= 50) {
            $hGradFrom   = 'from-amber-500'; $hGradVia = 'via-orange-500'; $hGradTo = 'to-amber-600';
            $hBarColor   = '#fbbf24';   // amber-400
            $hBadgeBg    = 'bg-amber-400/20'; $hBadgeText = 'text-amber-200'; $hBadgeBorder = 'border-amber-300/30';
            $hLabel      = 'IN PROGRESS'; $hIcon = 'fa-fire'; $hGlow = 'shadow-amber-300/40';
        } else {
            $hGradFrom   = 'from-rose-500'; $hGradVia = 'via-rose-600'; $hGradTo = 'to-red-700';
            $hBarColor   = '#fb7185';   // rose-400
            $hBadgeBg    = 'bg-rose-400/20'; $hBadgeText = 'text-rose-200'; $hBadgeBorder = 'border-rose-300/30';
            $hLabel      = 'NEEDS PUSH'; $hIcon = 'fa-bolt'; $hGlow = 'shadow-rose-300/40';
        }
        // SVG circular progress (r=26, circumference ≈ 163.4)
        $circ        = 163.4;
        $dashOffset  = $circ - ($circ * $hPercent / 100);
        // Total stats
        $hParties = 0;
        foreach ($branchSummary ?? [] as $s) { $hParties += $s['parties']; }
        $hGroups  = count($grouped ?? []);
    @endphp
    <div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br {{ $hGradFrom }} {{ $hGradVia }} {{ $hGradTo }} p-6 shadow-xl {{ $hGlow }}">
        <div class="absolute -top-10 -right-10 w-52 h-52 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-36 h-36 bg-black/10 rounded-full blur-2xl"></div>
        
        <div class="relative z-10">
            {{-- Top bar: title + action buttons --}}
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/30">
                        <i class="fas fa-wallet text-white text-xs"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-white tracking-tight leading-none">Collections</h2>
                        <p class="text-[8px] font-black uppercase tracking-[0.2em] text-white/60 mt-0.5">{{ $monthName }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="showFilters = !showFilters"
                            :class="showFilters ? 'bg-white text-emerald-600' : 'bg-white/15 text-white border-white/20'"
                            class="w-10 h-10 rounded-2xl flex items-center justify-center border transition active:scale-90">
                        <i class="fas fa-sliders text-xs"></i>
                    </button>
                    @if(Auth::user()->hasFeature('mobile_collection', 'target_setting') || Auth::user()->role === 'admin')
                    <a href="{{ route('mobile.agent-targets.index') }}"
                       class="w-10 h-10 bg-white/15 text-white border border-white/20 rounded-2xl flex items-center justify-center active:scale-90 transition">
                        <i class="fas fa-bullseye text-xs"></i>
                    </a>
                    @endif
                    @if(Auth::user()->hasPermission('mobile_teams_setup', 'view') || Auth::user()->role === 'admin')
                    <a href="{{ route('mobile.teams.setup') }}"
                       class="w-10 h-10 bg-white/15 text-white border border-white/20 rounded-2xl flex items-center justify-center active:scale-90 transition">
                        <i class="fas fa-network-wired text-xs"></i>
                    </a>
                    @endif
                </div>
            </div>

            {{-- Achievement Progress Block --}}
            @if(isset($grandTotal) && $grandTotal > 0)
            <div class="bg-black/20 backdrop-blur-sm border border-white/15 rounded-3xl p-4">
                
                {{-- Row: Circular progress + Amount info --}}
                <div class="flex items-center gap-4">
                    
                    {{-- SVG Circular Progress Ring --}}
                    <div class="relative flex-shrink-0 w-20 h-20">
                        <svg class="w-20 h-20 -rotate-90" viewBox="0 0 60 60">
                            {{-- Track ring --}}
                            <circle cx="30" cy="30" r="26"
                                    fill="none"
                                    stroke="rgba(255,255,255,0.15)"
                                    stroke-width="5"/>
                            {{-- Progress arc --}}
                            <circle cx="30" cy="30" r="26"
                                    class="ring-anim"
                                    fill="none"
                                    stroke="{{ $hBarColor }}"
                                    stroke-width="5"
                                    stroke-linecap="round"
                                    stroke-dasharray="{{ $circ }}"
                                    stroke-dashoffset="{{ $dashOffset }}"/>
                        </svg>
                        {{-- Center % label --}}
                        <div class="absolute inset-0 flex flex-col items-center justify-center float-anim">
                            <span class="text-base font-black text-white leading-none">{{ $hPercent }}<span class="text-[9px]">%</span></span>
                        </div>
                    </div>
                    
                    {{-- Right: Collection amount + target + badge --}}
                    <div class="flex-1 min-w-0">
                        <div class="text-[8px] font-black text-white/50 uppercase tracking-widest mb-0.5">Total Collected</div>
                        <div class="text-3xl font-black text-white leading-none tracking-tight">{{ $formatCr($grandTotal) }}</div>
                        @if(isset($grandTarget) && $grandTarget > 0)
                        <div class="text-[9px] text-white/60 font-bold mt-1">
                            of <span class="text-white font-black">{{ $formatCr($grandTarget) }}</span> target
                        </div>
                        @endif
                        
                        {{-- Status Badge --}}
                        <div class="mt-2 inline-flex items-center gap-1.5 {{ $hBadgeBg }} border {{ $hBadgeBorder }} px-2.5 py-1 rounded-xl badge-pulse">
                            <i class="fas {{ $hIcon }} {{ $hBadgeText }} text-[8px] float-anim"></i>
                            <span class="text-[8px] font-black {{ $hBadgeText }} tracking-widest">{{ $hLabel }}</span>
                        </div>
                    </div>
                </div>
                
                {{-- Progress Bar (linear, below) --}}
                @if(isset($grandTarget) && $grandTarget > 0)
                <div class="mt-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[8px] text-white/40 font-bold uppercase tracking-widest">Achievement</span>
                        <span class="text-[8px] font-black text-white/70">
                            @if($hPercent >= 100)
                            <i class="fas fa-check-circle text-emerald-300 mr-1"></i>Completed
                            @elseif($hPercent >= 75)
                            <i class="fas fa-arrow-trend-up text-emerald-300 mr-1"></i>Almost there!
                            @elseif($hPercent >= 50)
                            <i class="fas fa-fire text-amber-300 mr-1"></i>Halfway
                            @else
                            <i class="fas fa-bolt text-rose-300 mr-1"></i>Keep going
                            @endif
                        </span>
                    </div>
                    <div class="w-full bg-white/10 rounded-full h-3 overflow-hidden relative">
                        <div class="h-3 rounded-full relative overflow-hidden transition-all duration-1000"
                             style="width: {{ $hPercent }}%; background: {{ $hBarColor }};">
                            {{-- Shimmer animation --}}
                            <div class="absolute inset-0 shimmer-anim" style="background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.35) 50%, transparent 100%); background-size: 200px 100%;"></div>
                        </div>
                        {{-- Milestone markers --}}
                        <div class="absolute top-0 left-[25%] h-3 w-px bg-white/30"></div>
                        <div class="absolute top-0 left-[50%] h-3 w-px bg-white/30"></div>
                        <div class="absolute top-0 left-[75%] h-3 w-px bg-white/30"></div>
                    </div>
                    <div class="flex justify-between mt-1 px-px">
                        <span class="text-[7px] text-white/30 font-bold">0</span>
                        <span class="text-[7px] text-white/30 font-bold">25%</span>
                        <span class="text-[7px] text-white/30 font-bold">50%</span>
                        <span class="text-[7px] text-white/30 font-bold">75%</span>
                        <span class="text-[7px] text-white/30 font-bold">100%</span>
                    </div>
                </div>
                @endif
                
                {{-- Stat pills row --}}
                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-white/10">
                    <div class="flex-1 bg-white/10 rounded-2xl py-2 px-3 text-center">
                        <div class="text-sm font-black text-white">{{ $hGroups }}</div>
                        <div class="text-[7px] font-black text-white/50 uppercase tracking-widest mt-0.5">Groups</div>
                    </div>
                    <div class="flex-1 bg-white/10 rounded-2xl py-2 px-3 text-center">
                        <div class="text-sm font-black text-white">{{ $hParties }}</div>
                        <div class="text-[7px] font-black text-white/50 uppercase tracking-widest mt-0.5">Accounts</div>
                    </div>
                    <div class="flex-1 bg-white/10 rounded-2xl py-2 px-3 text-center">
                        <div class="text-sm font-black text-white">{{ $hPercent }}%</div>
                        <div class="text-[7px] font-black text-white/50 uppercase tracking-widest mt-0.5">Achieved</div>
                    </div>
                </div>
            </div>
            @else
            {{-- No data yet - simple placeholder --}}
            <div class="bg-black/20 border border-white/10 rounded-3xl py-5 px-4 text-center">
                <i class="fas fa-chart-bar text-white/30 text-2xl mb-2"></i>
                <div class="text-white/60 text-xs font-bold">Apply filters to view collection data</div>
            </div>
            @endif
        </div>
    </div>

    {{-- Header Micro-animations CSS --}}
    <style>
    @@keyframes shimmer {
        0% { background-position: -300px 0; }
        100% { background-position: 300px 0; }
    }
    @@keyframes ringFill {
        from { stroke-dashoffset: 163.4; }
        to { stroke-dashoffset: {{ $dashOffset }}; }
    }
    @@keyframes floatPulse {
        0%, 100% { transform: translateY(0) scale(1); }
        50% { transform: translateY(-3px) scale(1.03); }
    }
    @@keyframes glowPulse {
        0%, 100% { opacity: 0.8; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.05); }
    }
    .shimmer-anim {
        background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.45) 50%, transparent 100%);
        background-size: 300px 100%;
        animation: shimmer 2.2s infinite linear;
    }
    .ring-anim {
        stroke-dashoffset: 163.4;
        animation: ringFill 1.4s cubic-bezier(0.4, 0, 0.2, 1) forwards 0.2s;
    }
    .float-anim {
        animation: floatPulse 3s ease-in-out infinite;
    }
    .badge-pulse {
        animation: glowPulse 2.5s ease-in-out infinite;
    }
    </style>


    {{-- ===== FILTER PANEL (collapsible) ===== --}}
    <div x-show="showFilters" x-cloak x-transition
         class="bg-white/80 backdrop-blur-xl border border-white/60 shadow-lg shadow-slate-100/40 p-5 rounded-[2rem] space-y-4">
        
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-[10px] font-black text-slate-600 uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-sliders text-slate-400"></i> Report Filters
            </h3>
            <span class="text-[8px] font-black text-slate-400 bg-slate-100 px-2 py-1 rounded-lg uppercase">{{ $monthName }}</span>
        </div>
        
        <form method="GET" action="{{ route('mobile.collection-report') }}" id="mobileFilterForm">
            <input type="hidden" name="fetch" value="1">
            
            {{-- Month Selector --}}
            <div class="space-y-1.5 mb-4">
                <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Month</label>
                <select name="month_filter" id="mob-month-filter" onchange="handleMobMonthChange(this.value)"
                        class="w-full bg-white border border-slate-200 rounded-2xl py-3 px-4 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-emerald-400 outline-none transition">
                    <option value="custom">Custom Date Range</option>
                    @foreach($monthOptions ?? [] as $ym => $label)
                    <option value="{{ $ym }}" {{ ($monthFilter ?? '') === $ym ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            
            {{-- Date Range --}}
            <div id="mob-date-range-row" class="{{ isset($monthFilter) && $monthFilter !== 'custom' ? 'hidden' : '' }}">
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="space-y-1.5">
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">From Date</label>
                        <input type="date" name="from_date" id="mob-from-date" value="{{ $fromDate ?? date('Y-m-01') }}"
                               class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-emerald-300 outline-none transition">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">To Date</label>
                        <input type="date" name="to_date" id="mob-to-date" value="{{ $toDate ?? date('Y-m-t') }}"
                               class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-emerald-300 outline-none transition">
                    </div>
                </div>
            </div>

            @if(Auth::user()->hasFeature('mobile_collection', 'agent_filter') || Auth::user()->role === 'admin')
            <div class="space-y-1.5 mb-4">
                <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Agent / Salesman</label>
                <select name="agent_filter"
                        class="w-full bg-white border border-slate-200 rounded-2xl py-3 px-4 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-emerald-400 outline-none transition">
                    <option value="">All Agents</option>
                    @foreach($agentOptions ?? [] as $opt)
                    <option value="{{ $opt }}" {{ ($agentFilter ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(Auth::user()->hasFeature('mobile_collection', 'branch_filter') || Auth::user()->role === 'admin')
            <div class="space-y-1.5 mb-4">
                <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Group / Branch (Multiple)</label>
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
                    <select name="branch_filter[]" multiple
                            class="w-full py-3 px-4 text-xs font-semibold text-slate-700 focus:ring-0 outline-none" size="3" style="border:none;">
                        @foreach($branchOptions ?? [] as $opt)
                        <option value="{{ $opt }}" {{ in_array($opt, $branchFilter ?? []) ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <p class="text-[8px] text-slate-400 font-bold ml-1">Hold Ctrl/Cmd to select multiple</p>
            </div>
            @endif

            {{-- Team Filter Chips --}}
            @if(count($dbTeams ?? []) > 0)
            <div class="space-y-2 mb-4">
                <div class="flex items-center justify-between">
                    <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Team Filter</label>
                    @if(Auth::user()->hasFeature('mobile_teams_setup', 'view') || Auth::user()->role === 'admin' || Auth::user()->hasPermission('mobile_teams_setup', 'view'))
                    <a href="{{ route('mobile.teams.setup') }}" class="text-[9px] font-black text-emerald-600 flex items-center gap-1">
                        <i class="fas fa-network-wired text-[8px]"></i> Setup Teams
                    </a>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($dbTeams as $team)
                    @php $isActive = in_array($team->id, $selectedTeams ?? []); @endphp
                    <button type="button" onclick="toggleMobileTeamFilter('{{ $team->id }}')"
                            id="mob_btn_team_{{ $team->id }}"
                            class="px-3 py-2 rounded-xl text-[10px] font-black transition-all active:scale-95 flex items-center gap-1.5 border {{ $isActive ? 'bg-emerald-500 text-white border-emerald-600 shadow-sm shadow-emerald-200' : 'bg-white text-slate-600 border-slate-200' }}">
                        <i class="fas fa-users text-[8px] opacity-70"></i>
                        {{ $team->name }}
                    </button>
                    @if($isActive)
                    <input type="hidden" name="teams[]" value="{{ $team->id }}" id="mob_input_team_{{ $team->id }}">
                    @endif
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Hide Zero Collection --}}
            <div class="bg-slate-50 border border-slate-100 rounded-2xl p-3.5 mb-4 flex items-center justify-between">
                <label class="flex items-center gap-2.5 cursor-pointer text-xs font-black text-slate-700 select-none" for="mob-hide-zero">
                    <div class="w-7 h-7 bg-slate-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-eye-slash text-slate-400 text-[9px]"></i>
                    </div>
                    Hide Zero Collection Parties
                </label>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="mob-hide-zero" name="hide_zero_collection" value="1" {{ request()->boolean('hide_zero_collection') ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                </label>
            </div>

            <button type="submit"
                    class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-black text-xs uppercase tracking-widest rounded-2xl py-4 shadow-lg shadow-emerald-200 active:scale-95 transition-transform flex items-center justify-center gap-2">
                <i class="fas fa-chart-bar"></i>
                View Collection Report
            </button>
        </form>
    </div>

    {{-- ===== REPORT CONTENT ===== --}}
    @if(isset($grouped) && count($grouped) > 0)
    
    {{-- Breadcrumb Trail --}}
    <div class="bg-white/90 backdrop-blur-xl border border-slate-100 rounded-3xl p-4 shadow-sm flex items-center justify-between" x-show="currentParentId !== 'root'" x-cloak>
        <div class="flex items-center flex-wrap gap-2 text-xs font-bold text-slate-500">
            <button type="button" @click="goToLevel('root')" class="text-indigo-600 hover:text-indigo-800 transition">All Groups</button>
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

    {{-- Quick Stats Row (Only visible at root or when customized) --}}
    <div class="grid grid-cols-3 gap-3" x-show="currentParentId === 'root'">
        <div class="bg-white/80 backdrop-blur-xl border border-white/60 rounded-2xl p-3.5 text-center shadow-sm">
            <div class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Total</div>
            <div class="text-base font-black text-emerald-600">{{ $formatCr($grandTotal ?? 0) }}</div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl border border-white/60 rounded-2xl p-3.5 text-center shadow-sm">
            <div class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Groups</div>
            <div class="text-base font-black text-slate-800">{{ count($grouped) }}</div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl border border-white/60 rounded-2xl p-3.5 text-center shadow-sm">
            <div class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Month</div>
            <div class="text-[11px] font-black text-indigo-600 leading-tight mt-0.5">{{ $monthName }}</div>
        </div>
    </div>

    {{-- Flat Drill-down List --}}
    <div class="space-y-3">
        {{-- 1. Database Teams (Root and Sub-Teams) --}}
        @foreach($dbTeams as $team)
        @php
            $teamName = $team->name;
            $bSummary = $branchSummary[$teamName] ?? ['total'=>0,'parties'=>0,'agents'=>0];
            $tTargetAmt = $teamTargets[$team->id] ?? 0;
            $tPercent = $tTargetAmt > 0 ? min(100, round(($bSummary['total'] / $tTargetAmt) * 100)) : 0;
            $tBarColor = $tPercent >= 100 ? 'bg-emerald-400' : ($tPercent >= 60 ? 'bg-amber-400' : 'bg-rose-400');
            $childrenTeams = $dbTeams->where('parent_id', $team->id);
            $hasChildren = $childrenTeams->count() > 0;
            $directAgentNames = $team->agents ?: [];
            $hasAgents = false;
            foreach ($directAgentNames as $agentName) {
                if (isset($grouped[$teamName][$agentName])) {
                    $hasAgents = true;
                    break;
                }
            }
            $isRoot = $team->parent_id === null;
            $parentIdStr = $team->parent_id ? (string)$team->parent_id : 'root';
        @endphp
        
        <div x-show="currentParentId === '{{ $parentIdStr }}'" x-transition x-cloak
             class="overflow-hidden rounded-[1.8rem] shadow-md border border-white/60 bg-white/80 backdrop-blur-xl transition duration-200">
            @if($hasChildren || $hasAgents)
            <button type="button" @click="drillDown('{{ $team->id }}', '{{ $teamName }}')" class="w-full text-left">
            @else
            <div class="w-full text-left">
            @endif
                <div class="{{ $isRoot ? 'bg-gradient-to-r from-slate-800 to-slate-900' : 'bg-gradient-to-r from-slate-700 to-slate-800' }} p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 flex-shrink-0">
                                @if($isRoot)
                                    <i class="fas fa-globe text-white text-sm"></i>
                                @elseif($hasChildren)
                                    <i class="fas fa-map-marker-alt text-white text-sm"></i>
                                @else
                                    <i class="fas fa-users text-white text-sm"></i>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="font-black text-white text-sm tracking-tight truncate uppercase">{{ $teamName }}</div>
                                <div class="text-[9px] text-white/50 font-bold mt-0.5">
                                    {{ $bSummary['agents'] }} Agents &middot; {{ $bSummary['parties'] }} A/C
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 ml-2 flex-shrink-0">
                            <div class="text-right">
                                <div class="text-emerald-400 font-black text-sm leading-none">{{ $formatCr($bSummary['total']) }}</div>
                                @if($tTargetAmt > 0)
                                <div class="text-[9px] text-white/50 font-bold mt-0.5">{{ $tPercent }}% of goal</div>
                                @endif
                            </div>
                            @if($hasChildren || $hasAgents)
                            <div class="w-7 h-7 bg-white/10 rounded-xl flex items-center justify-center border border-white/20">
                                <i class="fas fa-chevron-right text-white/60 text-[10px]"></i>
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    @if($tTargetAmt > 0)
                    <div class="mt-3">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[8px] text-white/40 font-bold uppercase tracking-widest">Progress</span>
                            <span class="text-[8px] text-white/60 font-black">Target: {{ $formatCr($tTargetAmt) }}</span>
                        </div>
                        <div class="w-full bg-white/10 rounded-full h-1.5 overflow-hidden">
                            <div class="{{ $tBarColor }} h-1.5 rounded-full transition-all" style="width: {{ $tPercent }}%"></div>
                        </div>
                    </div>
                    @endif
                </div>
            @if($hasChildren || $hasAgents)
            </button>
            @else
            </div>
            @endif
        </div>
        @endforeach

        {{-- Agents and Parties under database teams --}}
        @foreach($dbTeams as $team)
            @php
                $teamName = $team->name;
                $directAgentNames = $team->agents ?: [];
            @endphp
            @foreach($directAgentNames as $agentName)
                @if(isset($grouped[$teamName][$agentName]))
                    @php
                        $agentRows = $grouped[$teamName][$agentName];
                        $agentTotal = array_sum(array_map(fn($r) => $parseAmt($r[$crField] ?? 0), $agentRows));
                        $agentId = 'agent_' . $team->id . '_' . Str::slug($agentName);
                        $targetAmt = $agentTargets[$agentName] ?? 0;
                        $percent = $targetAmt > 0 ? min(100, round(($agentTotal / $targetAmt) * 100)) : 0;
                        $progressColor = $percent >= 100 ? 'bg-emerald-400' : ($percent >= 60 ? 'bg-amber-400' : 'bg-rose-400');
                        $progressTextColor = $percent >= 100 ? 'text-emerald-600' : ($percent >= 60 ? 'text-amber-600' : 'text-rose-500');
                    @endphp
                    
                    {{-- Agent card under its team --}}
                    <div x-show="currentParentId === '{{ $team->id }}'" x-transition x-cloak
                         class="overflow-hidden rounded-[1.8rem] shadow-sm border border-slate-100 bg-white hover:bg-slate-50/50 transition">
                        <button type="button" @click="drillDown('{{ $agentId }}', '{{ $agentName }}')" class="w-full text-left px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 bg-violet-50 border border-violet-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-user-tie text-violet-500 text-[10px]"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-black text-slate-800 text-xs truncate">{{ $agentName }}</div>
                                    <div class="text-[8px] text-slate-400 font-bold mt-0.5">{{ count($agentRows) }} accounts</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 ml-2 flex-shrink-0">
                                <div class="text-right">
                                    <div class="font-black text-slate-800 text-xs">{{ $formatCr($agentTotal) }}</div>
                                    @if($targetAmt > 0)
                                    <div class="text-[8px] {{ $progressTextColor }} font-black">{{ $percent }}% of goal</div>
                                    @endif
                                </div>
                                <div class="w-7 h-7 bg-slate-100 rounded-xl flex items-center justify-center border border-slate-200">
                                    <i class="fas fa-chevron-right text-slate-400 text-[9px]"></i>
                                </div>
                            </div>
                        </button>
                        
                        @if($targetAmt > 0)
                        <div class="px-4 pb-2">
                            <div class="w-full bg-slate-100 rounded-full h-1 overflow-hidden">
                                <div class="{{ $progressColor }} h-1 rounded-full" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Parties under this agent --}}
                    @foreach($agentRows as $party)
                        @php
                            $pName  = trim($party[$partyNameKey ?? 'AC_Name'] ?? $party['AC_Name'] ?? $party['AcName'] ?? $party['PartyName'] ?? '—');
                            $pCode  = trim($party['AC_Code'] ?? $party['ActCode'] ?? $party['Ac_Code'] ?? '');
                            $pCrAmt = $crField ? $parseAmt($party[$crField] ?? 0) : 0;
                        @endphp
                        <div x-show="currentParentId === '{{ $agentId }}'" x-transition x-cloak
                             class="bg-white border border-slate-100 rounded-2xl px-4 py-3.5 flex items-center justify-between shadow-sm">
                            <div class="min-w-0 pr-2">
                                <div class="text-[8px] font-black text-indigo-500 font-mono tracking-tight">{{ $pCode }}</div>
                                <div class="text-[10px] font-bold text-slate-700 truncate mt-0.5">{{ $pName }}</div>
                            </div>
                            <div class="font-black text-emerald-600 text-[11px] flex-shrink-0">
                                {!! $pCrAmt > 0 ? '₹' . $formatIndian($pCrAmt) : '<span class="text-slate-300">—</span>' !!}
                            </div>
                        </div>
                    @endforeach
                @endif
            @endforeach
        @endforeach

        {{-- 2. Non-team / ungrouped branches --}}
        @foreach($grouped as $teamName => $agents)
        @php
            $isCustomTeam = $dbTeams->contains('name', $teamName);
            if ($isCustomTeam) continue;
            $bSummary = $branchSummary[$teamName] ?? ['total'=>0,'parties'=>0,'agents'=>0];
            $tSlug = 'ungrouped_' . Str::slug($teamName);
        @endphp
        
        <div x-show="currentParentId === 'root'" x-transition x-cloak
             class="overflow-hidden rounded-[1.8rem] shadow-md border border-white/60 bg-white/80 backdrop-blur-xl transition duration-200">
            <button type="button" @click="drillDown('{{ $tSlug }}', '{{ $teamName }}')" class="w-full text-left">
                <div class="bg-gradient-to-r from-slate-700 to-slate-800 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20">
                                <i class="fas fa-building text-white text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="font-black text-white text-sm truncate uppercase">{{ $teamName }}</div>
                                <div class="text-[9px] text-white/50 font-bold mt-0.5">
                                    {{ $bSummary['agents'] }} Agents &middot; {{ $bSummary['parties'] }} A/C
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 ml-2 flex-shrink-0">
                            <div class="font-black text-emerald-400 text-sm">{{ $formatCr($bSummary['total']) }}</div>
                            <div class="w-7 h-7 bg-white/10 rounded-xl flex items-center justify-center border border-white/20">
                                <i class="fas fa-chevron-right text-white/60 text-[10px]"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </button>
        </div>
        
        {{-- Agents under ungrouped branches --}}
        @foreach($agents as $agentName => $agentRows)
        @php
            $agentTotal = array_sum(array_map(fn($r) => $parseAmt($r[$crField] ?? 0), $agentRows));
            $agentId = 'agent_ungrouped_' . Str::slug($teamName) . '_' . Str::slug($agentName);
            $targetAmt = $agentTargets[$agentName] ?? 0;
            $percent = $targetAmt > 0 ? min(100, round(($agentTotal / $targetAmt) * 100)) : 0;
            $progressColor = $percent >= 100 ? 'bg-emerald-400' : ($percent >= 60 ? 'bg-amber-400' : 'bg-rose-400');
            $progressTextColor = $percent >= 100 ? 'text-emerald-600' : ($percent >= 60 ? 'text-amber-600' : 'text-rose-500');
        @endphp
        
        <div x-show="currentParentId === '{{ $tSlug }}'" x-transition x-cloak
             class="overflow-hidden rounded-[1.8rem] shadow-sm border border-slate-100 bg-white hover:bg-slate-50/50 transition">
            <button type="button" @click="drillDown('{{ $agentId }}', '{{ $agentName }}')" class="w-full text-left px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 bg-violet-50 border border-violet-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-user-tie text-violet-500 text-[10px]"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="font-black text-slate-800 text-xs truncate">{{ $agentName }}</div>
                        <div class="text-[8px] text-slate-400 font-bold mt-0.5">{{ count($agentRows) }} accounts</div>
                    </div>
                </div>
                <div class="flex items-center gap-2 ml-2 flex-shrink-0">
                    <div class="text-right">
                        <div class="font-black text-slate-800 text-xs">{{ $formatCr($agentTotal) }}</div>
                        @if($targetAmt > 0)
                        <div class="text-[8px] {{ $progressTextColor }} font-black">{{ $percent }}% of goal</div>
                        @endif
                    </div>
                    <div class="w-7 h-7 bg-slate-100 rounded-xl flex items-center justify-center border border-slate-200">
                        <i class="fas fa-chevron-right text-slate-400 text-[9px]"></i>
                    </div>
                </div>
            </button>
            
            @if($targetAmt > 0)
            <div class="px-4 pb-2">
                <div class="w-full bg-slate-100 rounded-full h-1 overflow-hidden">
                    <div class="{{ $progressColor }} h-1 rounded-full" style="width: {{ $percent }}%"></div>
                </div>
            </div>
            @endif
        </div>
        
        {{-- Parties under ungrouped agents --}}
        @foreach($agentRows as $party)
            @php
                $pName  = trim($party[$partyNameKey ?? 'AC_Name'] ?? $party['AC_Name'] ?? $party['AcName'] ?? $party['PartyName'] ?? '—');
                $pCode  = trim($party['AC_Code'] ?? $party['ActCode'] ?? $party['Ac_Code'] ?? '');
                $pCrAmt = $crField ? $parseAmt($party[$crField] ?? 0) : 0;
            @endphp
            <div x-show="currentParentId === '{{ $agentId }}'" x-transition x-cloak
                 class="bg-white border border-slate-100 rounded-2xl px-4 py-3.5 flex items-center justify-between shadow-sm">
                <div class="min-w-0 pr-2">
                    <div class="text-[8px] font-black text-indigo-500 font-mono tracking-tight">{{ $pCode }}</div>
                    <div class="text-[10px] font-bold text-slate-700 truncate mt-0.5">{{ $pName }}</div>
                </div>
                <div class="font-black text-emerald-600 text-[11px] flex-shrink-0">
                    {!! $pCrAmt > 0 ? '₹' . $formatIndian($pCrAmt) : '<span class="text-slate-300">—</span>' !!}
                </div>
            </div>
        @endforeach
        @endforeach
        @endforeach
    </div>
    
    @else
    {{-- Empty state --}}
    <div class="bg-white/80 backdrop-blur-xl border border-white/60 rounded-[2.5rem] p-12 text-center shadow-sm">
        <div class="w-20 h-20 bg-emerald-50 rounded-3xl flex items-center justify-center mx-auto mb-5 border border-emerald-100">
            <i class="fas fa-chart-bar text-3xl text-emerald-300"></i>
        </div>
        <h3 class="font-black text-slate-700 text-sm uppercase tracking-wide">No Data Yet</h3>
        <p class="text-slate-400 text-xs mt-2 font-bold leading-relaxed">
            Use the filter panel above to select a month and<br>tap <span class="text-emerald-600">View Collection Report</span>.
        </p>
        <button @click="showFilters = true"
                class="mt-5 bg-gradient-to-r from-emerald-500 to-teal-600 text-white px-6 py-3 rounded-2xl text-xs font-black uppercase tracking-widest active:scale-95 transition-transform shadow-lg shadow-emerald-200">
            <i class="fas fa-filter mr-2"></i> Open Filters
        </button>
    </div>
    @endif

</div>

<script>
function collectionApp() {
    return {
        showFilters: {{ request()->has('fetch') ? 'false' : 'true' }},
        currentParentId: 'root',
        currentTitle: 'All Groups',
        history: [], // array of { id, title }
        
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
                this.currentTitle = 'All Groups';
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

function toggleMobileTeamFilter(teamId) {
    const btn = document.getElementById('mob_btn_team_' + teamId);
    const form = document.getElementById('mobileFilterForm');
    let input = document.getElementById('mob_input_team_' + teamId);
    
    if (btn.classList.contains('bg-emerald-500')) {
        btn.classList.remove('bg-emerald-500', 'text-white', 'border-emerald-600', 'shadow-sm', 'shadow-emerald-200');
        btn.classList.add('bg-white', 'text-slate-600', 'border-slate-200');
        if (input) input.remove();
    } else {
        btn.classList.add('bg-emerald-500', 'text-white', 'border-emerald-600', 'shadow-sm', 'shadow-emerald-200');
        btn.classList.remove('bg-white', 'text-slate-600', 'border-slate-200');
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

function handleMobMonthChange(val) {
    const row = document.getElementById('mob-date-range-row');
    if (val === 'custom') {
        row.classList.remove('hidden');
    } else {
        row.classList.add('hidden');
        if (val) {
            const parts = val.split('-');
            const year = parseInt(parts[0]);
            const month = parseInt(parts[1]);
            const from = val + '-01';
            const lastDay = new Date(year, month, 0).getDate();
            const to = val + '-' + String(lastDay).padStart(2, '0');
            const fromInp = document.getElementById('mob-from-date');
            const toInp = document.getElementById('mob-to-date');
            if (fromInp) fromInp.value = from;
            if (toInp) toInp.value = to;
        }
    }
}
</script>
@endsection
