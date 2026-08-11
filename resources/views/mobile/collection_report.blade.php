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

    {{-- API Error from Controller --}}
    @if(!empty($error))
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-3xl p-4 flex items-start gap-3 text-xs font-bold">
        <div class="w-8 h-8 bg-amber-500 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
            <i class="fas fa-triangle-exclamation text-white text-xs"></i>
        </div>
        <div>
            <div class="font-black text-amber-900 mb-0.5">⚠️ ERP API Error</div>
            <div class="text-amber-700 font-semibold">{{ $error }}</div>
            <div class="text-amber-500 text-[10px] mt-1">Logic ERP server se data nahi aa raha. Thodi der baad retry karein.</div>
        </div>
    </div>
    @endif

    {{-- ===== HERO HEADER CARD ===== --}}
    @php
        // Calculate dynamic grand target based on fallback logic (since team targets might be set under individual agents)
        $dynGrandTarget = 0;
        foreach($dbTeams ?? [] as $team) {
            // Sum ONLY root level teams (parent_id is null) to prevent double counting of child teams/sub-teams!
            if ($team->parent_id !== null) {
                continue;
            }
            $tTargetAmt = $teamTargets[$team->id] ?? 0;
            if ($tTargetAmt == 0) {
                $sumTargets = function($tNode) use (&$sumTargets, $dbTeams, $agentTargets, $teamTargets) {
                    $sum = 0;
                    foreach ($tNode->agents ?? [] as $ag) {
                        $sum += ($agentTargets[$ag] ?? 0);
                    }
                    foreach ($dbTeams->where('parent_id', $tNode->id) as $chNode) {
                        $chTeamTarget = (float)($teamTargets[$chNode->id] ?? 0);
                        if ($chTeamTarget > 0) {
                            $sum += $chTeamTarget; // Child team has its own target set
                        } else {
                            $sum += $sumTargets($chNode); // Recurse further
                        }
                    }
                    return $sum;
                };
                $tTargetAmt = $sumTargets($team);
            }
            $dynGrandTarget += $tTargetAmt;
        }
        
        // Add ungrouped branches agent targets
        foreach($grouped ?? [] as $teamName => $agents) {
            $isCustomTeam = $dbTeams->contains('name', $teamName);
            if ($isCustomTeam) continue;
            foreach ($agents as $agentName => $agentRows) {
                $dynGrandTarget += ($agentTargets[$agentName] ?? 0);
            }
        }
        
        $grandTarget = $dynGrandTarget > 0 ? $dynGrandTarget : ($grandTarget ?? 0);

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

        // Mascot setup based on achievement level
        if ($hPercent >= 100) {
            // Happy, celebrating bug
            $mascotBodyClass = 'body-happy';
            $mascotAnimClass = 'anim-happy';
            $mascotEyes = '
                <div class="mascot-eye"><div class="pupil"></div></div>
                <div class="mascot-eye"><div class="pupil"></div></div>
            ';
            $mascotMouth = '<div class="mascot-mouth mouth-happy"></div>';
            $mascotDecorations = '
                <div class="decor-item decor-1">🎉</div>
                <div class="decor-item decor-2">✨</div>
                <div class="decor-item decor-3">👑</div>
            ';
            $mascotAntennaColor = '#064e3b';
        } elseif ($hPercent >= 75) {
            // Smart, smiling bug
            $mascotBodyClass = 'body-smart';
            $mascotAnimClass = 'anim-smart';
            $mascotEyes = '
                <div class="mascot-eye"><div class="pupil" style="transform: scaleY(0.2); top: 5px;"></div></div>
                <div class="mascot-eye"><div class="pupil"></div></div>
            ';
            $mascotMouth = '<div class="mascot-mouth"></div>'; // Smile arc
            $mascotDecorations = '
                <div class="decor-item decor-1">⭐</div>
                <div class="decor-item decor-2">👍</div>
            ';
            $mascotAntennaColor = '#115e59';
        } elseif ($hPercent >= 50) {
            // Working hard, determined bug
            $mascotBodyClass = 'body-determined';
            $mascotAnimClass = 'anim-determined';
            $mascotEyes = '
                <div class="mascot-eye"><div class="pupil" style="width: 5px; height: 5px;"></div></div>
                <div class="mascot-eye"><div class="pupil" style="width: 5px; height: 5px;"></div></div>
            ';
            $mascotMouth = '<div class="mascot-mouth mouth-determined"></div>';
            $mascotDecorations = '
                <div class="decor-item decor-1">🔥</div>
                <div class="decor-item decor-2">⚡</div>
            ';
            $mascotAntennaColor = '#78350f';
        } else {
            // Sad, shivering bug (needs push)
            $mascotBodyClass = 'body-sad';
            $mascotAnimClass = 'anim-sad';
            $mascotEyes = '
                <div class="mascot-eye"><div class="pupil" style="top: 5px;"><div class="tear-drop"></div></div></div>
                <div class="mascot-eye"><div class="pupil" style="top: 5px;"></div></div>
            ';
            $mascotMouth = '<div class="mascot-mouth mouth-sad"></div>';
            $mascotDecorations = '
                <div class="decor-item decor-1">🥺</div>
                <div class="decor-item decor-2">☁️</div>
            ';
            $mascotAntennaColor = '#881337';
        }
    @endphp
    <div class="relative overflow-hidden rounded-[2.5rem] p-6 shadow-xl transition-all duration-700"
         :style="hero ? 'background: ' + hero.gradient : null"
         style="background: linear-gradient(135deg, {{ $hPercent >= 100 ? '#10b981,#059669,#0d9488' : ($hPercent >= 75 ? '#10b981,#059669,#0f766e' : ($hPercent >= 50 ? '#f59e0b,#f97316,#d97706' : '#f43f5e,#e11d48,#b91c1c')) }})">
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
            <div class="bg-black/20 backdrop-blur-sm border border-white/15 rounded-3xl p-4 relative overflow-hidden">
                
                {{-- Row: Circular progress + Amount info --}}
                <div class="flex items-center gap-4">
                    
                    {{-- SVG Circular Progress Ring --}}
                    <div class="relative flex-shrink-0 w-20 h-20 relative z-10">
                        <!-- Circular progress SVG - reactive -->
                        <svg class="w-20 h-20 -rotate-90" viewBox="0 0 60 60">
                            <circle cx="30" cy="30" r="26"
                                    fill="none"
                                    stroke="rgba(255,255,255,0.15)"
                                    stroke-width="5"/>
                            <circle cx="30" cy="30" r="26"
                                    class="ring-anim"
                                    fill="none"
                                    :stroke="hero ? hero.barColor : '{{ $hBarColor }}'"
                                    stroke-width="5"
                                    stroke-linecap="round"
                                    :stroke-dasharray="hero ? hero.circ : {{ $circ }}"
                                    :stroke-dashoffset="hero ? hero.dashOffset : {{ $dashOffset }}"/>
                        </svg>
                        {{-- Center % label --}}
                        <div class="absolute inset-0 flex flex-col items-center justify-center float-anim">
                            <span class="text-base font-black text-white leading-none" x-text="hero ? hero.percent + '%' : '{{ $hPercent }}%'"></span>
                        </div>
                    </div>
                    
                    {{-- Right: Collection amount + target + badge --}}
                    <div class="flex-1 min-w-0 pr-[85px] relative z-10">
                        <div class="text-[8px] font-black text-white/50 uppercase tracking-widest mb-0.5"
                             x-text="hero && !hero.isRoot ? hero.title + ' — Collected' : 'Total Collected'">Total Collected</div>
                        <div class="text-3xl font-black text-white leading-none tracking-tight"
                             x-text="hero ? hero.total : '{{ $formatCr($grandTotal) }}'">{{ $formatCr($grandTotal) }}</div>
                        <div class="text-[9px] text-white/60 font-bold mt-1" x-show="hero ? hero.hasTarget : {{ $grandTarget > 0 ? 'true' : 'false' }}">
                            of <span class="text-white font-black" x-text="hero ? hero.target : '{{ $formatCr($grandTarget) }}'">{{ $formatCr($grandTarget ?? 0) }}</span> target
                        </div>
                        
                        {{-- Status Badge --}}
                        <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl badge-pulse border transition-all duration-500"
                             :style="hero ? 'background:'+hero.badgeBg+';border-color:'+hero.badgeBorder : ''">
                            <i class="fas float-anim text-[8px]" :class="hero ? hero.icon : '{{ $hIcon }}'" :style="hero ? 'color:'+hero.badgeText : ''"></i>
                            <span class="text-[8px] font-black tracking-widest" :style="hero ? 'color:'+hero.badgeText : ''" x-text="hero ? hero.label : '{{ $hLabel }}'">{{ $hLabel }}</span>
                        </div>
                    </div>
                </div>
                
                {{-- Progress Bar (linear, below) --}}
                <div class="mt-4 relative z-10" x-show="hero ? hero.hasTarget : {{ $grandTarget > 0 ? 'true' : 'false' }}">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[8px] text-white/40 font-bold uppercase tracking-widest">Achievement</span>
                        <span class="text-[8px] font-black text-white/70" x-text="hero ? hero.milestone : '{{ $hPercent >= 100 ? 'Completed' : ($hPercent >= 75 ? 'Almost there!' : ($hPercent >= 50 ? 'Halfway' : 'Keep going')) }}'"></span>
                    </div>
                    <div class="w-full bg-white/10 rounded-full h-3 overflow-hidden relative">
                        <div class="h-3 rounded-full relative overflow-hidden transition-all duration-700"
                             :style="'width:' + (hero ? hero.percent : {{ $hPercent }}) + '%; background:' + (hero ? hero.barColor : '{{ $hBarColor }}')">
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
                
                {{-- Stat pills row --}}
                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-white/10 relative z-10">
                    <div class="flex-1 bg-white/10 rounded-2xl py-2 px-3 text-center">
                        <div class="text-sm font-black text-white" x-text="hero ? hero.groups : '{{ $hGroups }}'">{{ $hGroups }}</div>
                        <div class="text-[7px] font-black text-white/50 uppercase tracking-widest mt-0.5" x-text="hero && !hero.isRoot ? 'Agents' : 'Groups'">Groups</div>
                    </div>
                    <div class="flex-1 bg-white/10 rounded-2xl py-2 px-3 text-center">
                        <div class="text-sm font-black text-white" x-text="hero ? hero.accounts : '{{ $hParties }}'">{{ $hParties }}</div>
                        <div class="text-[7px] font-black text-white/50 uppercase tracking-widest mt-0.5">Accounts</div>
                    </div>
                    <div class="flex-1 bg-white/10 rounded-2xl py-2 px-3 text-center">
                        <div class="text-sm font-black text-white" x-text="(hero ? hero.percent : {{ $hPercent }}) + '%'">{{ $hPercent }}%</div>
                        <div class="text-[7px] font-black text-white/50 uppercase tracking-widest mt-0.5">Achieved</div>
                    </div>
                </div>

                {{-- 3D Mascot bug/cartoon --}}
                <div class="mascot-wrapper {{ $mascotAnimClass }}">
                    <div class="worm-container">
                        {{-- Segments (tail to head, so head is on top) --}}
                        <div class="worm-segment segment-4 {{ $mascotBodyClass }}"></div>
                        <div class="worm-segment segment-3 {{ $mascotBodyClass }}"></div>
                        <div class="worm-segment segment-2 {{ $mascotBodyClass }}"></div>
                        <div class="worm-segment segment-1 {{ $mascotBodyClass }}"></div>
                        
                        {{-- Main Head --}}
                        <div class="worm-head {{ $mascotBodyClass }}">
                            <div class="antenna-left" style="color: {{ $mascotAntennaColor }}"><div class="antenna-tip"></div></div>
                            <div class="antenna-right" style="color: {{ $mascotAntennaColor }}"><div class="antenna-tip"></div></div>
                            <div class="mascot-face">
                                <div class="eye-container">
                                    {!! $mascotEyes !!}
                                </div>
                                {!! $mascotMouth !!}
                            </div>
                        </div>
                    </div>
                    {!! $mascotDecorations !!}
                </div>
            </div>
            @else
            {{-- No data yet - simple placeholder --}}
            <div class="bg-black/20 border border-white/10 rounded-3xl py-5 px-4 text-center">
                @if(!empty($error))
                <i class="fas fa-server text-white/30 text-2xl mb-2"></i>
                <div class="text-white/80 text-xs font-bold mb-1">ERP Server Unavailable</div>
                <div class="text-white/50 text-[10px] font-semibold">{{ $error }}</div>
                <div class="text-white/40 text-[9px] mt-2">Algebra ERP SQL Server se connection nahi ho pa raha. Thodi der baad try karein.</div>
                @else
                <i class="fas fa-chart-bar text-white/30 text-2xl mb-2"></i>
                <div class="text-white/60 text-xs font-bold">Apply filters to view collection data</div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Header Micro-animations CSS --}}
    <style>
    @keyframes shimmer {
        0% { background-position: -300px 0; }
        100% { background-position: 300px 0; }
    }
    @keyframes ringFill {
        from { stroke-dashoffset: 163.4; }
        to { stroke-dashoffset: {{ $dashOffset }}; }
    }
    @keyframes floatPulse {
        0%, 100% { transform: translateY(0) scale(1); }
        50% { transform: translateY(-3px) scale(1.03); }
    }
    @keyframes glowPulse {
        0%, 100% { opacity: 0.8; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.05); }
    }
    @keyframes slideUpFade {
        from {
            opacity: 0;
            transform: translateY(16px) scale(0.97);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
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
    .animate-card-entry {
        animation: slideUpFade 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    .progress-glow-bar {
        box-shadow: 0 0 8px currentColor;
    }
    .hover-scale {
        transition: transform 0.2s ease;
    }
    .hover-scale:hover {
        transform: scale(1.02);
    }

    /* Wiggling Caterpillar/Worm Mascot Styles */
    .mascot-wrapper {
        position: absolute;
        right: 14px;
        top: 24px;
        width: 100px;
        height: 60px;
        z-index: 5;
        pointer-events: none;
    }
    .worm-container {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
    }
    .worm-head {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        position: absolute;
        left: 0;
        z-index: 10;
        box-shadow: 
            inset -3px -3px 6px rgba(0,0,0,0.4),
            inset 3px 3px 6px rgba(255,255,255,0.3),
            0 4px 8px rgba(0,0,0,0.2);
        animation: wormSlither 1.6s infinite ease-in-out;
    }
    .worm-segment {
        border-radius: 50%;
        position: absolute;
        box-shadow: 
            inset -2px -2px 4px rgba(0,0,0,0.4),
            inset 2px 2px 4px rgba(255,255,255,0.3),
            0 3px 6px rgba(0,0,0,0.15);
        animation: wormSlither 1.6s infinite ease-in-out;
    }
    /* Segments positioning & scaling to taper off to the tail */
    .segment-1 {
        width: 30px;
        height: 30px;
        left: 28px;
        z-index: 9;
        animation-delay: -0.2s;
    }
    .segment-2 {
        width: 24px;
        height: 24px;
        left: 48px;
        z-index: 8;
        animation-delay: -0.4s;
    }
    .segment-3 {
        width: 18px;
        height: 18px;
        left: 64px;
        z-index: 7;
        animation-delay: -0.6s;
    }
    .segment-4 {
        width: 12px;
        height: 12px;
        left: 76px;
        z-index: 6;
        animation-delay: -0.8s;
    }

    /* Mascot body gradient coloring */
    .body-happy {
        background: radial-gradient(circle at 30% 30%, #34d399, #059669 65%, #064e3b);
    }
    .body-smart {
        background: radial-gradient(circle at 30% 30%, #2dd4bf, #0d9488 65%, #115e59);
    }
    .body-determined {
        background: radial-gradient(circle at 30% 30%, #fbbf24, #d97706 65%, #78350f);
    }
    .body-sad {
        background: radial-gradient(circle at 30% 30%, #fb7185, #e11d48 65%, #881337);
    }

    /* 3D Antennae */
    .antenna-left, .antenna-right {
        position: absolute;
        width: 3px;
        height: 10px;
        background: currentColor;
        bottom: 85%;
        border-radius: 3px;
        transform-origin: bottom center;
    }
    .antenna-left {
        left: 12px;
        transform: rotate(-25deg);
    }
    .antenna-right {
        right: 12px;
        transform: rotate(25deg);
    }
    .antenna-tip {
        position: absolute;
        width: 5px;
        height: 5px;
        background: currentColor;
        border-radius: 50%;
        top: -4px;
        left: -1px;
        box-shadow: inset -1px -1px 2px rgba(0,0,0,0.3);
    }

    /* Mascot Face */
    .mascot-face {
        width: 100%;
        height: 100%;
        position: relative;
    }

    /* 3D Eyes */
    .eye-container {
        display: flex;
        gap: 6px;
        position: absolute;
        top: 12px;
        left: 50%;
        transform: translateX(-50%);
    }
    .mascot-eye {
        width: 8px;
        height: 8px;
        background: white;
        border-radius: 50%;
        position: relative;
        box-shadow: inset 1px 1px 2px rgba(0,0,0,0.6);
        animation: blinkEye 4s infinite;
    }
    .pupil {
        width: 4px;
        height: 4px;
        background: #0f172a;
        border-radius: 50%;
        position: absolute;
        top: 2px;
        left: 2px;
    }
    .pupil::after {
        content: '';
        position: absolute;
        width: 1px;
        height: 1px;
        background: white;
        border-radius: 50%;
        top: 0.5px;
        left: 0.5px;
    }

    /* Tears for Sad Emotion */
    .tear-drop {
        position: absolute;
        width: 2.5px;
        height: 4px;
        background: #38bdf8;
        border-radius: 0 50% 50% 50%;
        transform: rotate(45deg);
        top: 6px;
        left: 2.5px;
        animation: fallTear 1.5s infinite linear;
    }

    /* Dynamic Mouth shapes */
    .mascot-mouth {
        position: absolute;
        bottom: 10px;
        left: 50%;
        transform: translateX(-50%);
        width: 10px;
        height: 5px;
        border-bottom: 2px solid #0f172a;
        border-radius: 0 0 6px 6px;
    }
    .mouth-sad {
        width: 8px;
        height: 3px;
        border-bottom: none;
        border-top: 2px solid #0f172a;
        border-radius: 6px 6px 0 0;
        bottom: 8px;
    }
    .mouth-determined {
        width: 7px;
        height: 2px;
        background: #0f172a;
        border-radius: 1px;
        border: none;
        bottom: 10px;
    }
    .mouth-happy {
        width: 14px;
        height: 7px;
        background: #e11d48;
        border-bottom: 1.5px solid #0f172a;
        border-radius: 0 0 14px 14px;
        overflow: hidden;
        position: absolute;
    }
    .mouth-happy::before {
        content: '';
        position: absolute;
        width: 8px;
        height: 3px;
        background: #fda4af;
        border-radius: 50%;
        bottom: -1px;
        left: 3px;
    }

    /* Floating Decor Items */
    .decor-item {
        position: absolute;
        font-size: 11px;
        opacity: 0;
        animation: floatDecor 3s infinite ease-out;
    }
    .decor-1 { top: -8px; left: -8px; animation-delay: 0s; }
    .decor-2 { top: -12px; right: -4px; animation-delay: 1s; }
    .decor-3 { bottom: 8px; right: -15px; animation-delay: 0.5s; }

    /* Mascot Animations */
    @keyframes blinkEye {
        0%, 90%, 100% { transform: scaleY(1); }
        95% { transform: scaleY(0.1); }
    }
    @keyframes fallTear {
        0% { transform: translateY(0) rotate(45deg); opacity: 1; }
        100% { transform: translateY(9px) rotate(45deg); opacity: 0; }
    }
    @keyframes floatDecor {
        0% { transform: translateY(8px) scale(0.5); opacity: 0; }
        50% { opacity: 1; }
        100% { transform: translateY(-16px) scale(1.1); opacity: 0; }
    }

    /* Mascot motion based on status */
    .worm-head, .worm-segment {
        animation-iteration-count: infinite;
        animation-timing-function: ease-in-out;
    }
    .anim-happy .worm-head, .anim-happy .worm-segment {
        animation-name: wormSlither, mascotHappyBounce;
        animation-duration: 1.2s, 1.2s;
    }
    .anim-smart .worm-head, .anim-smart .worm-segment {
        animation-name: wormSlither;
        animation-duration: 1.8s;
    }
    .anim-determined .worm-head, .anim-determined .worm-segment {
        animation-name: wormSlither;
        animation-duration: 0.9s;
    }
    .anim-sad .worm-head, .anim-sad .worm-segment {
        animation-name: wormSlitherSad, wormShiver;
        animation-duration: 2.2s, 0.12s;
        animation-timing-function: ease-in-out, linear;
    }

    @keyframes wormSlither {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
    }
    @keyframes wormSlitherSad {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(3px); }
    }
    @keyframes mascotHappyBounce {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
    }
    @keyframes wormShiver {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-0.8px); }
        75% { transform: translateX(0.8px); }
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
            
            // Recurse to sum agent targets if team target is 0
            if ($tTargetAmt == 0) {
                $sumTargets = function($tNode) use (&$sumTargets, $dbTeams, $agentTargets, $teamTargets) {
                    $sum = 0;
                    foreach ($tNode->agents ?? [] as $ag) {
                        $sum += ($agentTargets[$ag] ?? 0);
                    }
                    foreach ($dbTeams->where('parent_id', $tNode->id) as $chNode) {
                        $chTeamTarget = (float)($teamTargets[$chNode->id] ?? 0);
                        if ($chTeamTarget > 0) {
                            $sum += $chTeamTarget;
                        } else {
                            $sum += $sumTargets($chNode);
                        }
                    }
                    return $sum;
                };
                $tTargetAmt = $sumTargets($team);
            }
            $tPercent = $tTargetAmt > 0 ? min(100, round(($bSummary['total'] / $tTargetAmt) * 100)) : 0;
            
            // Choose color variables dynamically (light theme)
            if ($tTargetAmt == 0) {
                $cardBg = 'bg-gradient-to-br from-slate-50 to-white';
                $cardBorder = 'border-slate-150';
                $percentText = 'No Target';
                $percentColor = 'text-slate-500';
                $progressColor = 'bg-slate-200';
                $iconClass = 'fa-users';
                $iconBg = 'bg-slate-100 text-slate-500 border-slate-200';
                $nameColor = 'text-slate-800';
                $infoColor = 'text-slate-400';
                $chevBg = 'bg-slate-50 border-slate-150 text-slate-400';
                $progressBg = 'bg-slate-100';
            } elseif ($tPercent >= 100) {
                $cardBg = 'bg-gradient-to-br from-emerald-50 via-white to-emerald-50/20';
                $cardBorder = 'border-emerald-200/80';
                $percentText = $tPercent . '%';
                $percentColor = 'text-emerald-600';
                $progressColor = 'bg-emerald-400';
                $iconClass = 'fa-trophy';
                $iconBg = 'bg-emerald-100/80 text-emerald-600 border-emerald-200/50';
                $nameColor = 'text-emerald-950';
                $infoColor = 'text-emerald-700/80';
                $chevBg = 'bg-emerald-100/30 border-emerald-200/30 text-emerald-600';
                $progressBg = 'bg-emerald-100/50';
            } elseif ($tPercent >= 75) {
                $cardBg = 'bg-gradient-to-br from-teal-50 via-white to-teal-50/20';
                $cardBorder = 'border-teal-200/80';
                $percentText = $tPercent . '%';
                $percentColor = 'text-teal-600';
                $progressColor = 'bg-teal-400';
                $iconClass = 'fa-circle-check';
                $iconBg = 'bg-teal-100/80 text-teal-600 border-teal-200/50';
                $nameColor = 'text-teal-950';
                $infoColor = 'text-teal-700/80';
                $chevBg = 'bg-teal-100/30 border-teal-200/30 text-teal-600';
                $progressBg = 'bg-teal-100/50';
            } elseif ($tPercent >= 50) {
                $cardBg = 'bg-gradient-to-br from-amber-50 via-white to-amber-50/20';
                $cardBorder = 'border-amber-200/80';
                $percentText = $tPercent . '%';
                $percentColor = 'text-amber-600';
                $progressColor = 'bg-amber-400';
                $iconClass = 'fa-fire';
                $iconBg = 'bg-amber-100/80 text-amber-600 border-amber-200/50';
                $nameColor = 'text-amber-950';
                $infoColor = 'text-amber-700/80';
                $chevBg = 'bg-amber-100/30 border-amber-200/30 text-amber-600';
                $progressBg = 'bg-amber-100/50';
            } else {
                $cardBg = 'bg-gradient-to-br from-rose-50 via-white to-rose-50/20';
                $cardBorder = 'border-rose-200/60';
                $percentText = $tPercent . '%';
                $percentColor = 'text-rose-600';
                $progressColor = 'bg-rose-500';
                $iconClass = 'fa-bolt';
                $iconBg = 'bg-rose-100/80 text-rose-500 border-rose-200/40';
                $nameColor = 'text-rose-955';
                $infoColor = 'text-rose-700/80';
                $chevBg = 'bg-rose-100/30 border-rose-200/30 text-rose-600';
                $progressBg = 'bg-rose-100/50';
            }
            
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
             style="animation-delay: {{ $loop->index * 0.05 }}s;"
             class="overflow-hidden rounded-[1.8rem] shadow-sm border {{ $cardBorder }} {{ $cardBg }} backdrop-blur-xl transition-all duration-300 hover:scale-[1.01] hover:-translate-y-0.5 active:scale-[0.99] animate-card-entry">
            @if($hasChildren || $hasAgents)
            <button type="button" @click="drillDown('{{ $team->id }}', '{{ $teamName }}')" class="w-full text-left">
            @else
            <div class="w-full text-left">
            @endif
                <div class="p-4 relative">
                    {{-- Soft glow overlay --}}
                    @if($tTargetAmt > 0)
                    <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full blur-xl opacity-10 {{ $tPercent >= 100 ? 'bg-emerald-400' : ($tPercent >= 75 ? 'bg-teal-400' : ($tPercent >= 50 ? 'bg-amber-400' : 'bg-rose-400')) }}"></div>
                    @endif
                    
                    {{-- Title Row --}}
                    <div class="flex items-center justify-between relative z-10 mb-3">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center border flex-shrink-0 transition-transform duration-300 {{ $iconBg }}">
                                <i class="fas {{ $tTargetAmt > 0 ? $iconClass : ($isRoot ? 'fa-globe' : ($hasChildren ? 'fa-map-marker-alt' : 'fa-users')) }} text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="font-black {{ $nameColor }} text-base tracking-tight truncate uppercase flex items-center gap-1.5">
                                    {{ $teamName }}
                                </div>
                                <div class="text-[11px] {{ $infoColor }} font-bold mt-0.5">
                                    {{ $bSummary['agents'] }} Agents &middot; {{ $bSummary['parties'] }} A/C
                                </div>
                            </div>
                        </div>
                        @if($hasChildren || $hasAgents)
                        <div class="w-7 h-7 rounded-xl flex items-center justify-center transition-all duration-300 {{ $chevBg }} flex-shrink-0">
                            <i class="fas fa-chevron-right text-[9px]"></i>
                        </div>
                        @endif
                    </div>

                    {{-- Metrics Comparison Grid --}}
                    <div class="grid grid-cols-3 gap-2 py-3 px-3.5 bg-slate-50/70 rounded-2xl border border-slate-100 relative z-10">
                        <div>
                            <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Target</span>
                            <span class="block text-sm font-black text-slate-800 tracking-tight">
                                {{ $tTargetAmt > 0 ? $formatCr($tTargetAmt) : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Collected</span>
                            <span class="block text-sm font-black text-emerald-600 tracking-tight">
                                {{ $formatCr($bSummary['total']) }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Achieved</span>
                            @if($tTargetAmt > 0)
                            <span class="block text-sm font-black {{ $tPercent >= 100 ? 'text-emerald-600' : ($tPercent >= 75 ? 'text-teal-600' : ($tPercent >= 50 ? 'text-amber-600' : 'text-rose-600')) }} tracking-tight">
                                {{ $tPercent }}%
                            </span>
                            @else
                            <span class="block text-[10px] font-black text-slate-450 bg-slate-100 px-1.5 py-0.5 rounded inline-block">Not Set</span>
                            @endif
                        </div>
                    </div>

                    {{-- Progress Details --}}
                    @if($tTargetAmt > 0)
                    <div class="mt-3 relative z-10 space-y-2">
                        <div class="w-full {{ $progressBg }} rounded-full h-1.5 overflow-hidden border border-slate-100/50">
                            <div class="h-1.5 rounded-full relative overflow-hidden transition-all duration-1000 progress-glow-bar {{ $progressColor }}"
                                 style="width: {{ $tPercent > 0 ? min(100, $tPercent) : 0 }}%">
                                <div class="absolute inset-0 shimmer-anim" style="background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.4) 50%, transparent 100%); background-size: 150px 100%;"></div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-xs font-black uppercase tracking-wider">
                            @if($bSummary['total'] >= $tTargetAmt)
                            <span class="text-emerald-650 flex items-center gap-1">
                                <i class="fas fa-check-circle text-[10px]"></i> Target Achieved
                            </span>
                            <span class="text-emerald-650">0 Left</span>
                            @else
                            @php $gapAmt = $tTargetAmt - $bSummary['total']; @endphp
                            <span class="text-rose-605 flex items-center gap-1">
                                <i class="fas fa-exclamation-triangle text-[10px]"></i> Target Gap
                            </span>
                            <span class="text-rose-605 font-black">{{ $formatCr($gapAmt) }} left</span>
                            @endif
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
                        
                        // Select styling dynamically for agent cards (light theme)
                        if ($targetAmt == 0) {
                            $agentCardBg = 'bg-gradient-to-br from-slate-50 to-white';
                            $agentCardBorder = 'border-slate-150';
                            $agentPercentText = 'No Target';
                            $agentPercentColor = 'text-slate-500';
                            $agentProgressColor = 'bg-slate-200';
                            $agentIconBg = 'bg-slate-100 text-slate-500 border-slate-200';
                            $agentNameColor = 'text-slate-800';
                            $agentInfoColor = 'text-slate-400';
                            $agentChevBg = 'bg-slate-50 border-slate-155 text-slate-400';
                            $agentProgressBg = 'bg-slate-100';
                        } elseif ($percent >= 100) {
                            $agentCardBg = 'bg-gradient-to-br from-emerald-50 via-white to-emerald-50/20';
                            $agentCardBorder = 'border-emerald-200/80';
                            $agentPercentText = $percent . '%';
                            $agentPercentColor = 'text-emerald-600';
                            $agentProgressColor = 'bg-emerald-400';
                            $agentIconBg = 'bg-emerald-100/80 text-emerald-600 border-emerald-200/50';
                            $agentNameColor = 'text-emerald-950';
                            $agentInfoColor = 'text-emerald-700/80';
                            $agentChevBg = 'bg-emerald-100/30 border-emerald-200/30 text-emerald-600';
                            $agentProgressBg = 'bg-emerald-100/50';
                        } elseif ($percent >= 75) {
                            $agentCardBg = 'bg-gradient-to-br from-teal-50 via-white to-teal-50/20';
                            $agentCardBorder = 'border-teal-200/80';
                            $agentPercentText = $percent . '%';
                            $agentPercentColor = 'text-teal-600';
                            $agentProgressColor = 'bg-teal-400';
                            $agentIconBg = 'bg-teal-100/80 text-teal-600 border-teal-200/50';
                            $agentNameColor = 'text-teal-950';
                            $agentInfoColor = 'text-teal-700/80';
                            $agentChevBg = 'bg-teal-100/30 border-teal-200/30 text-teal-600';
                            $agentProgressBg = 'bg-teal-100/50';
                        } elseif ($percent >= 50) {
                            $agentCardBg = 'bg-gradient-to-br from-amber-50 via-white to-amber-50/20';
                            $agentCardBorder = 'border-amber-200/80';
                            $agentPercentText = $percent . '%';
                            $agentPercentColor = 'text-amber-600';
                            $agentProgressColor = 'bg-amber-400';
                            $agentIconBg = 'bg-amber-100/80 text-amber-600 border-amber-200/50';
                            $agentNameColor = 'text-amber-950';
                            $agentInfoColor = 'text-amber-700/80';
                            $agentChevBg = 'bg-amber-100/30 border-amber-200/30 text-amber-600';
                            $agentProgressBg = 'bg-amber-100/50';
                        } else {
                            $agentCardBg = 'bg-gradient-to-br from-rose-50 via-white to-rose-50/20';
                            $agentCardBorder = 'border-rose-200/60';
                            $agentPercentText = $percent . '%';
                            $agentPercentColor = 'text-rose-600';
                            $agentProgressColor = 'bg-rose-500';
                            $agentIconBg = 'bg-rose-100/80 text-rose-500 border-rose-200/40';
                            $agentNameColor = 'text-rose-950';
                            $agentInfoColor = 'text-rose-700/80';
                            $agentChevBg = 'bg-rose-100/30 border-rose-200/30 text-rose-600';
                            $agentProgressBg = 'bg-rose-100/50';
                        }
                    @endphp
                    
                    {{-- Agent card under its team --}}
                    <div x-show="currentParentId === '{{ $team->id }}'" x-transition x-cloak
                         style="animation-delay: {{ $loop->index * 0.05 }}s;"
                         class="overflow-hidden rounded-[1.8rem] shadow-sm border {{ $agentCardBorder }} {{ $agentCardBg }} backdrop-blur-xl transition-all duration-300 hover:scale-[1.01] hover:-translate-y-0.5 active:scale-[0.99] animate-card-entry">
                        <button type="button" @click="drillDown('{{ $agentId }}', '{{ $agentName }}')" class="w-full text-left p-4">
                            {{-- Agent Title Row --}}
                            <div class="flex items-center justify-between relative z-10 mb-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center border flex-shrink-0 {{ $agentIconBg }}">
                                        <i class="fas fa-user-tie text-xs"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-black {{ $agentNameColor }} text-sm truncate uppercase flex items-center gap-1.5">
                                            {{ $agentName }}
                                        </div>
                                        <div class="text-[10px] {{ $agentInfoColor }} font-bold mt-0.5">{{ count($agentRows) }} accounts</div>
                                    </div>
                                </div>
                                <div class="w-7 h-7 rounded-xl flex items-center justify-center transition-all duration-300 {{ $agentChevBg }} flex-shrink-0">
                                    <i class="fas fa-chevron-right text-[9px]"></i>
                                </div>
                            </div>
                            
                            {{-- Metrics Comparison Grid --}}
                            <div class="grid grid-cols-3 gap-2 py-3 px-3.5 bg-slate-50/70 rounded-2xl border border-slate-100 mb-3 relative z-10">
                                <div>
                                    <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Target</span>
                                    <span class="block text-sm font-black text-slate-800 tracking-tight">
                                        {{ $targetAmt > 0 ? $formatCr($targetAmt) : '—' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Collected</span>
                                    <span class="block text-sm font-black text-emerald-600 tracking-tight">
                                        {{ $formatCr($agentTotal) }}
                                    </span>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Achieved</span>
                                    @if($targetAmt > 0)
                                    <span class="block text-sm font-black {{ $percent >= 100 ? 'text-emerald-600' : ($percent >= 75 ? 'text-teal-600' : ($percent >= 50 ? 'text-amber-600' : 'text-rose-600')) }} tracking-tight">
                                        {{ $percent }}%
                                    </span>
                                    @else
                                    <span class="block text-[10px] font-black text-slate-455 bg-slate-100 px-1.5 py-0.5 rounded inline-block">Not Set</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Progress Details --}}
                            @if($targetAmt > 0)
                            <div class="space-y-2 relative z-10">
                                <div class="w-full {{ $agentProgressBg }} rounded-full h-1 overflow-hidden border border-slate-100/50">
                                    <div class="h-1 rounded-full relative overflow-hidden transition-all duration-1000 progress-glow-bar {{ $agentProgressColor }}"
                                         style="width: {{ $percent > 0 ? min(100, $percent) : 0 }}%">
                                        <div class="absolute inset-0 shimmer-anim" style="background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.4) 50%, transparent 100%); background-size: 150px 100%;"></div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between text-xs font-black uppercase tracking-wider">
                                    @if($agentTotal >= $targetAmt)
                                    <span class="text-emerald-650 flex items-center gap-1">
                                        <i class="fas fa-check-circle text-[10px]"></i> Target Achieved
                                    </span>
                                    <span class="text-emerald-650">0 Left</span>
                                    @else
                                    @php $gapAmt = $targetAmt - $agentTotal; @endphp
                                    <span class="text-rose-605 flex items-center gap-1">
                                        <i class="fas fa-exclamation-triangle text-[10px]"></i> Target Gap
                                    </span>
                                    <span class="text-rose-605 font-black">{{ $formatCr($gapAmt) }} left</span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </button>
                    </div>

                    {{-- Parties under this agent --}}
                    @foreach($agentRows as $party)
                        @php
                            $pName  = trim($party[$partyNameKey ?? 'AC_Name'] ?? $party['AC_Name'] ?? $party['AcName'] ?? $party['PartyName'] ?? '—');
                            $pCode  = trim($party['AC_Code'] ?? $party['ActCode'] ?? $party['Ac_Code'] ?? '');
                            $pCrAmt = $crField ? $parseAmt($party[$crField] ?? 0) : 0;
                        @endphp
                        <div x-show="currentParentId === '{{ $agentId }}'" x-transition x-cloak
                             style="animation-delay: {{ $loop->index * 0.03 }}s;"
                             class="bg-gradient-to-br from-slate-50 to-white border border-slate-150 rounded-2xl px-4 py-3.5 flex items-center justify-between shadow-sm animate-card-entry transition duration-200">
                            <div class="min-w-0 pr-2">
                                <div class="text-[8px] font-black text-indigo-500 font-mono tracking-tight">{{ $pCode }}</div>
                                <div class="text-[10px] font-bold text-slate-800 truncate mt-0.5">{{ $pName }}</div>
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
            
            // Calculate sum of agent targets for this ungrouped group
            $tTargetAmt = 0;
            foreach ($agents as $agentName => $agentRows) {
                $tTargetAmt += ($agentTargets[$agentName] ?? 0);
            }
            $tPercent = $tTargetAmt > 0 ? min(100, round(($bSummary['total'] / $tTargetAmt) * 100)) : 0;
            
            // Choose color variables dynamically (light theme)
            if ($tTargetAmt == 0) {
                $cardBg = 'bg-gradient-to-br from-slate-50 to-white';
                $cardBorder = 'border-slate-150';
                $percentText = 'No Target';
                $percentColor = 'text-slate-500';
                $progressColor = 'bg-slate-200';
                $iconClass = 'fa-building';
                $iconBg = 'bg-slate-100 text-slate-500 border-slate-200';
                $nameColor = 'text-slate-800';
                $infoColor = 'text-slate-400';
                $chevBg = 'bg-slate-50 border-slate-150 text-slate-400';
                $progressBg = 'bg-slate-100';
            } elseif ($tPercent >= 100) {
                $cardBg = 'bg-gradient-to-br from-emerald-50 via-white to-emerald-50/20';
                $cardBorder = 'border-emerald-200/80';
                $percentText = $tPercent . '%';
                $percentColor = 'text-emerald-600';
                $progressColor = 'bg-emerald-400';
                $iconClass = 'fa-trophy';
                $iconBg = 'bg-emerald-100/80 text-emerald-600 border-emerald-200/50';
                $nameColor = 'text-emerald-950';
                $infoColor = 'text-emerald-700/80';
                $chevBg = 'bg-emerald-100/30 border-emerald-200/30 text-emerald-600';
                $progressBg = 'bg-emerald-100/50';
            } elseif ($tPercent >= 75) {
                $cardBg = 'bg-gradient-to-br from-teal-50 via-white to-teal-50/20';
                $cardBorder = 'border-teal-200/80';
                $percentText = $tPercent . '%';
                $percentColor = 'text-teal-600';
                $progressColor = 'bg-teal-400';
                $iconClass = 'fa-circle-check';
                $iconBg = 'bg-teal-100/80 text-teal-600 border-teal-200/50';
                $nameColor = 'text-teal-950';
                $infoColor = 'text-teal-700/80';
                $chevBg = 'bg-teal-100/30 border-teal-200/30 text-teal-600';
                $progressBg = 'bg-teal-100/50';
            } elseif ($tPercent >= 50) {
                $cardBg = 'bg-gradient-to-br from-amber-50 via-white to-amber-50/20';
                $cardBorder = 'border-amber-200/80';
                $percentText = $tPercent . '%';
                $percentColor = 'text-amber-600';
                $progressColor = 'bg-amber-400';
                $iconClass = 'fa-fire';
                $iconBg = 'bg-amber-100/80 text-amber-600 border-amber-200/50';
                $nameColor = 'text-amber-955';
                $infoColor = 'text-amber-700/80';
                $chevBg = 'bg-amber-100/30 border-amber-200/30 text-teal-600';
                $progressBg = 'bg-amber-100/50';
            } else {
                $cardBg = 'bg-gradient-to-br from-rose-50 via-white to-rose-50/20';
                $cardBorder = 'border-rose-200/60';
                $percentText = $tPercent . '%';
                $percentColor = 'text-rose-600';
                $progressColor = 'bg-rose-500';
                $iconClass = 'fa-bolt';
                $iconBg = 'bg-rose-100/80 text-rose-500 border-rose-200/40';
                $nameColor = 'text-rose-955';
                $infoColor = 'text-rose-700/80';
                $chevBg = 'bg-rose-100/30 border-rose-200/30 text-rose-600';
                $progressBg = 'bg-rose-100/50';
            }
        @endphp
        
        <div x-show="currentParentId === 'root'" x-transition x-cloak
             style="animation-delay: {{ $loop->index * 0.05 }}s;"
             class="overflow-hidden rounded-[1.8rem] shadow-sm border {{ $cardBorder }} {{ $cardBg }} backdrop-blur-xl transition-all duration-300 hover:scale-[1.01] hover:-translate-y-0.5 active:scale-[0.99] animate-card-entry">
            <button type="button" @click="drillDown('{{ $tSlug }}', '{{ $teamName }}')" class="w-full text-left p-4">
                <div class="relative">
                    {{-- Soft glow overlay --}}
                    @if($tTargetAmt > 0)
                    <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full blur-xl opacity-10 {{ $tPercent >= 100 ? 'bg-emerald-400' : ($tPercent >= 75 ? 'bg-teal-400' : ($tPercent >= 50 ? 'bg-amber-400' : 'bg-rose-400')) }}"></div>
                    @endif
                    
                    {{-- Title Row --}}
                    <div class="flex items-center justify-between relative z-10 mb-3">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center border flex-shrink-0 transition-transform duration-300 {{ $iconBg }}">
                                <i class="fas {{ $tTargetAmt > 0 ? $iconClass : 'fa-building' }} text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="font-black {{ $nameColor }} text-base tracking-tight truncate uppercase flex items-center gap-1.5">
                                    {{ $teamName }}
                                </div>
                                <div class="text-[11px] {{ $infoColor }} font-bold mt-0.5">
                                    {{ $bSummary['agents'] }} Agents &middot; {{ $bSummary['parties'] }} A/C
                                </div>
                            </div>
                        </div>
                        <div class="w-7 h-7 rounded-xl flex items-center justify-center transition-all duration-300 {{ $chevBg }} flex-shrink-0">
                            <i class="fas fa-chevron-right text-[9px]"></i>
                        </div>
                    </div>

                    {{-- Metrics Comparison Grid --}}
                    <div class="grid grid-cols-3 gap-2 py-3 px-3.5 bg-slate-50/70 rounded-2xl border border-slate-100 relative z-10">
                        <div>
                            <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Target</span>
                            <span class="block text-sm font-black text-slate-800 tracking-tight">
                                {{ $tTargetAmt > 0 ? $formatCr($tTargetAmt) : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Collected</span>
                            <span class="block text-sm font-black text-emerald-600 tracking-tight">
                                {{ $formatCr($bSummary['total']) }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Achieved</span>
                            @if($tTargetAmt > 0)
                            <span class="block text-sm font-black {{ $tPercent >= 100 ? 'text-emerald-600' : ($tPercent >= 75 ? 'text-teal-600' : ($tPercent >= 50 ? 'text-amber-600' : 'text-rose-600')) }} tracking-tight">
                                {{ $tPercent }}%
                            </span>
                            @else
                            <span class="block text-[10px] font-black text-slate-450 bg-slate-100 px-1.5 py-0.5 rounded inline-block">Not Set</span>
                            @endif
                        </div>
                    </div>

                    {{-- Progress Details --}}
                    @if($tTargetAmt > 0)
                    <div class="mt-3 relative z-10 space-y-2">
                        <div class="w-full {{ $progressBg }} rounded-full h-1.5 overflow-hidden border border-slate-100/50">
                            <div class="h-1.5 rounded-full relative overflow-hidden transition-all duration-1000 progress-glow-bar {{ $progressColor }}"
                                 style="width: {{ $tPercent > 0 ? min(100, $tPercent) : 0 }}%">
                                <div class="absolute inset-0 shimmer-anim" style="background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.4) 50%, transparent 100%); background-size: 150px 100%;"></div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-xs font-black uppercase tracking-wider">
                            @if($bSummary['total'] >= $tTargetAmt)
                            <span class="text-emerald-650 flex items-center gap-1">
                                <i class="fas fa-check-circle text-[10px]"></i> Target Achieved
                            </span>
                            <span class="text-emerald-650">0 Left</span>
                            @else
                            @php $gapAmt = $tTargetAmt - $bSummary['total']; @endphp
                            <span class="text-rose-605 flex items-center gap-1">
                                <i class="fas fa-exclamation-triangle text-[10px]"></i> Target Gap
                            </span>
                            <span class="text-rose-605 font-black">{{ $formatCr($gapAmt) }} left</span>
                            @endif
                        </div>
                    </div>
                    @endif
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
            
            // Choose styling dynamically for ungrouped agent cards (light theme)
            if ($targetAmt == 0) {
                $agentCardBg = 'bg-gradient-to-br from-slate-50 to-white';
                $agentCardBorder = 'border-slate-150';
                $agentPercentText = 'No Target';
                $agentPercentColor = 'text-slate-500';
                $agentProgressColor = 'bg-slate-200';
                $agentIconBg = 'bg-slate-100 text-slate-500 border-slate-200';
                $agentNameColor = 'text-slate-800';
                $agentInfoColor = 'text-slate-400';
                $agentChevBg = 'bg-slate-50 border-slate-155 text-slate-400';
                $agentProgressBg = 'bg-slate-100';
            } elseif ($percent >= 100) {
                $agentCardBg = 'bg-gradient-to-br from-emerald-50 via-white to-emerald-50/20';
                $agentCardBorder = 'border-emerald-200/80';
                $agentPercentText = $percent . '%';
                $agentPercentColor = 'text-emerald-600';
                $agentProgressColor = 'bg-emerald-400';
                $agentIconBg = 'bg-emerald-100/80 text-emerald-600 border-emerald-200/50';
                $agentNameColor = 'text-emerald-950';
                $agentInfoColor = 'text-emerald-700/80';
                $agentChevBg = 'bg-emerald-100/30 border-emerald-200/30 text-emerald-600';
                $agentProgressBg = 'bg-emerald-100/50';
            } elseif ($percent >= 75) {
                $agentCardBg = 'bg-gradient-to-br from-teal-50 via-white to-teal-50/20';
                $agentCardBorder = 'border-teal-200/80';
                $agentPercentText = $percent . '%';
                $agentPercentColor = 'text-teal-600';
                $agentProgressColor = 'bg-teal-400';
                $agentIconBg = 'bg-teal-100/80 text-teal-600 border-teal-200/50';
                $agentNameColor = 'text-teal-950';
                $agentInfoColor = 'text-teal-700/80';
                $agentChevBg = 'bg-teal-100/30 border-teal-200/30 text-teal-600';
                $agentProgressBg = 'bg-teal-100/50';
            } elseif ($percent >= 50) {
                $agentCardBg = 'bg-gradient-to-br from-amber-50 via-white to-amber-50/20';
                $agentCardBorder = 'border-amber-200/80';
                $agentPercentText = $percent . '%';
                $agentPercentColor = 'text-amber-600';
                $agentProgressColor = 'bg-amber-400';
                $agentIconBg = 'bg-amber-100/80 text-amber-600 border-amber-200/50';
                $agentNameColor = 'text-amber-955';
                $agentInfoColor = 'text-amber-700/80';
                $agentChevBg = 'bg-amber-100/30 border-amber-200/30 text-teal-600';
                $agentProgressBg = 'bg-amber-100/50';
            } else {
                $agentCardBg = 'bg-gradient-to-br from-rose-50 via-white to-rose-50/20';
                $agentCardBorder = 'border-rose-200/60';
                $agentPercentText = $percent . '%';
                $agentPercentColor = 'text-rose-600';
                $agentProgressColor = 'bg-rose-500';
                $agentIconBg = 'bg-rose-100/80 text-rose-500 border-rose-200/40';
                $agentNameColor = 'text-rose-955';
                $agentInfoColor = 'text-rose-700/80';
                $agentChevBg = 'bg-rose-100/30 border-rose-200/30 text-rose-600';
                $agentProgressBg = 'bg-rose-100/50';
            }
        @endphp
        
        <div x-show="currentParentId === '{{ $tSlug }}'" x-transition x-cloak
             style="animation-delay: {{ $loop->index * 0.05 }}s;"
             class="overflow-hidden rounded-[1.8rem] shadow-sm border {{ $agentCardBorder }} {{ $agentCardBg }} backdrop-blur-xl transition-all duration-300 hover:scale-[1.01] hover:-translate-y-0.5 active:scale-[0.99] animate-card-entry">
            <button type="button" @click="drillDown('{{ $agentId }}', '{{ $agentName }}')" class="w-full text-left p-4">
                {{-- Agent Title Row --}}
                <div class="flex items-center justify-between relative z-10 mb-3">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center border flex-shrink-0 {{ $agentIconBg }}">
                            <i class="fas fa-user-tie text-xs"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="font-black {{ $agentNameColor }} text-sm truncate uppercase flex items-center gap-1.5">
                                {{ $agentName }}
                            </div>
                            <div class="text-[10px] {{ $agentInfoColor }} font-bold mt-0.5">{{ count($agentRows) }} accounts</div>
                        </div>
                    </div>
                    <div class="w-7 h-7 rounded-xl flex items-center justify-center transition-all duration-300 {{ $agentChevBg }} flex-shrink-0">
                        <i class="fas fa-chevron-right text-[9px]"></i>
                    </div>
                </div>
                
                {{-- Metrics Comparison Grid --}}
                <div class="grid grid-cols-3 gap-2 py-3 px-3.5 bg-slate-50/70 rounded-2xl border border-slate-100 mb-3 relative z-10">
                    <div>
                        <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Target</span>
                        <span class="block text-sm font-black text-slate-800 tracking-tight">
                            {{ $targetAmt > 0 ? $formatCr($targetAmt) : '—' }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Collected</span>
                        <span class="block text-sm font-black text-emerald-600 tracking-tight">
                            {{ $formatCr($agentTotal) }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Achieved</span>
                        @if($targetAmt > 0)
                        <span class="block text-sm font-black {{ $percent >= 100 ? 'text-emerald-600' : ($percent >= 75 ? 'text-teal-600' : ($percent >= 50 ? 'text-amber-600' : 'text-rose-600')) }} tracking-tight">
                            {{ $percent }}%
                        </span>
                        @else
                        <span class="block text-[10px] font-black text-slate-455 bg-slate-100 px-1.5 py-0.5 rounded inline-block">Not Set</span>
                        @endif
                    </div>
                </div>

                {{-- Progress Details --}}
                @if($targetAmt > 0)
                <div class="space-y-2 relative z-10">
                    <div class="w-full {{ $agentProgressBg }} rounded-full h-1 overflow-hidden border border-slate-100/50">
                        <div class="h-1 rounded-full relative overflow-hidden transition-all duration-1000 progress-glow-bar {{ $agentProgressColor }}"
                             style="width: {{ $percent > 0 ? min(100, $percent) : 0 }}%">
                            <div class="absolute inset-0 shimmer-anim" style="background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.4) 50%, transparent 100%); background-size: 150px 100%;"></div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-xs font-black uppercase tracking-wider">
                        @if($agentTotal >= $targetAmt)
                        <span class="text-emerald-650 flex items-center gap-1">
                            <i class="fas fa-check-circle text-[10px]"></i> Target Achieved
                        </span>
                        <span class="text-emerald-650">0 Left</span>
                        @else
                        @php $gapAmt = $targetAmt - $agentTotal; @endphp
                        <span class="text-rose-605 flex items-center gap-1">
                            <i class="fas fa-exclamation-triangle text-[10px]"></i> Target Gap
                        </span>
                        <span class="text-rose-605 font-black">{{ $formatCr($gapAmt) }} left</span>
                        @endif
                    </div>
                </div>
                @endif
            </button>
        </div>
        
        {{-- Parties under ungrouped agents --}}
        @foreach($agentRows as $party)
            @php
                $pName  = trim($party[$partyNameKey ?? 'AC_Name'] ?? $party['AC_Name'] ?? $party['AcName'] ?? $party['PartyName'] ?? '—');
                $pCode  = trim($party['AC_Code'] ?? $party['ActCode'] ?? $party['Ac_Code'] ?? '');
                $pCrAmt = $crField ? $parseAmt($party[$crField] ?? 0) : 0;
            @endphp
            <div x-show="currentParentId === '{{ $agentId }}'" x-transition x-cloak
                 style="animation-delay: {{ $loop->index * 0.03 }}s;"
                 class="bg-gradient-to-br from-slate-50 to-white border border-slate-150 rounded-2xl px-4 py-3.5 flex items-center justify-between shadow-sm animate-card-entry transition duration-200">
                <div class="min-w-0 pr-2">
                    <div class="text-[8px] font-black text-indigo-500 font-mono tracking-tight">{{ $pCode }}</div>
                    <div class="text-[10px] font-bold text-slate-800 truncate mt-0.5">{{ $pName }}</div>
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

@php
/* ===== BUILD crStats MAP FOR HERO CARD JS REACTIVITY ===== */
$crStatsMap = [];

// Root entry
$crStatsMap['root'] = [
    'title'    => 'All Groups',
    'total'    => round($grandTotal ?? 0, 2),
    'target'   => round($grandTarget ?? 0, 2),
    'percent'  => $hPercent,
    'groups'   => $hGroups,
    'accounts' => $hParties,
    'isRoot'   => true,
];

// DB Teams
foreach($dbTeams ?? [] as $team) {
    $tn = $team->name;
    $bs = $branchSummary[$tn] ?? ['total'=>0,'parties'=>0,'agents'=>0];
    $tt = $teamTargets[$team->id] ?? 0;
    if ($tt == 0) {
        $stFn = function($node) use (&$stFn, $dbTeams, $agentTargets, $teamTargets) {
            $s = 0;
            foreach ($node->agents ?? [] as $ag) { $s += ($agentTargets[$ag] ?? 0); }
            foreach ($dbTeams->where('parent_id', $node->id) as $ch) {
                $ct = (float)($teamTargets[$ch->id] ?? 0);
                $s += $ct > 0 ? $ct : $stFn($ch);
            }
            return $s;
        };
        $tt = $stFn($team);
    }
    $tp = $tt > 0 ? min(100, round(($bs['total'] / $tt) * 100)) : 0;
    // Count agents under this team
    $teamAgentCount = count($team->agents ?? []);
    $teamPartiesCount = 0;
    foreach($team->agents ?? [] as $an) {
        if (isset($grouped[$tn][$an])) $teamPartiesCount += count($grouped[$tn][$an]);
    }
    $crStatsMap[(string)$team->id] = [
        'title'    => $tn,
        'total'    => round($bs['total'], 2),
        'target'   => round($tt, 2),
        'percent'  => $tp,
        'groups'   => $teamAgentCount,
        'accounts' => $teamPartiesCount,
        'isRoot'   => false,
    ];
    // Agents under this team
    foreach($team->agents ?? [] as $agentName) {
        if (!isset($grouped[$tn][$agentName])) continue;
        $agRows = $grouped[$tn][$agentName];
        $agTotal = array_sum(array_map(fn($r) => $parseAmt($r[$crField ?? 'CrAmt'] ?? 0), $agRows));
        $agTarget = $agentTargets[$agentName] ?? 0;
        $agPct = $agTarget > 0 ? min(100, round(($agTotal / $agTarget) * 100)) : 0;
        $agId = 'agent_' . $team->id . '_' . Str::slug($agentName);
        $crStatsMap[$agId] = [
            'title'    => $agentName,
            'total'    => round($agTotal, 2),
            'target'   => round($agTarget, 2),
            'percent'  => $agPct,
            'groups'   => 1,
            'accounts' => count($agRows),
            'isRoot'   => false,
        ];
    }
}

// Ungrouped branches + their agents
foreach($grouped as $teamName => $agents) {
    if ($dbTeams->contains('name', $teamName)) continue;
    $bs = $branchSummary[$teamName] ?? ['total'=>0,'parties'=>0,'agents'=>0];
    $tt = 0;
    foreach ($agents as $an => $ar) { $tt += ($agentTargets[$an] ?? 0); }
    $tp = $tt > 0 ? min(100, round(($bs['total'] / $tt) * 100)) : 0;
    $tSlug = 'ungrouped_' . Str::slug($teamName);
    $crStatsMap[$tSlug] = [
        'title'    => $teamName,
        'total'    => round($bs['total'], 2),
        'target'   => round($tt, 2),
        'percent'  => $tp,
        'groups'   => count($agents),
        'accounts' => $bs['parties'],
        'isRoot'   => false,
    ];
    foreach($agents as $agentName => $agRows) {
        $agTotal = array_sum(array_map(fn($r) => $parseAmt($r[$crField ?? 'CrAmt'] ?? 0), $agRows));
        $agTarget = $agentTargets[$agentName] ?? 0;
        $agPct = $agTarget > 0 ? min(100, round(($agTotal / $agTarget) * 100)) : 0;
        $agId = 'agent_ungrouped_' . Str::slug($teamName) . '_' . Str::slug($agentName);
        $crStatsMap[$agId] = [
            'title'    => $agentName,
            'total'    => round($agTotal, 2),
            'target'   => round($agTarget, 2),
            'percent'  => $agPct,
            'groups'   => 1,
            'accounts' => count($agRows),
            'isRoot'   => false,
        ];
    }
}
@endphp

<script>
window.crStats = @json($crStatsMap);

function collectionApp() {
    return {
        showFilters: {{ request()->has('fetch') ? 'false' : 'true' }},
        currentParentId: 'root',
        currentTitle: 'All Groups',
        history: [],
        activeStats: window.crStats['root'] || null,

        get hero() {
            const s = this.activeStats || window.crStats['root'];
            if (!s) return null;
            const pct = Math.min(100, s.percent);
            // Color logic
            let gradient, barColor, badgeBg, badgeText, badgeBorder, label, icon;
            if (pct >= 100) {
                gradient = 'linear-gradient(135deg, #10b981, #059669, #0d9488)';
                barColor = '#34d399'; badgeBg = 'rgba(52,211,153,0.25)'; badgeText = '#a7f3d0';
                badgeBorder = 'rgba(52,211,153,0.4)'; label = 'TARGET HIT!'; icon = 'fa-trophy';
            } else if (pct >= 75) {
                gradient = 'linear-gradient(135deg, #10b981, #059669, #0f766e)';
                barColor = '#34d399'; badgeBg = 'rgba(52,211,153,0.2)'; badgeText = '#a7f3d0';
                badgeBorder = 'rgba(52,211,153,0.3)'; label = 'ON TRACK'; icon = 'fa-chart-line';
            } else if (pct >= 50) {
                gradient = 'linear-gradient(135deg, #f59e0b, #f97316, #d97706)';
                barColor = '#fbbf24'; badgeBg = 'rgba(251,191,36,0.2)'; badgeText = '#fde68a';
                badgeBorder = 'rgba(251,191,36,0.3)'; label = 'IN PROGRESS'; icon = 'fa-fire';
            } else {
                gradient = 'linear-gradient(135deg, #f43f5e, #e11d48, #b91c1c)';
                barColor = '#fb7185'; badgeBg = 'rgba(251,113,133,0.2)'; badgeText = '#fecdd3';
                badgeBorder = 'rgba(251,113,133,0.3)'; label = 'NEEDS PUSH'; icon = 'fa-bolt';
            }
            // Milestone label
            let milestone;
            if (pct >= 100) milestone = 'Completed';
            else if (pct >= 75) milestone = 'Almost there!';
            else if (pct >= 50) milestone = 'Halfway';
            else milestone = 'Keep going';

            // Circular dash
            const circ = 163.4;
            const dashOffset = circ - (circ * pct / 100);

            // Format total/target as Cr
            const fmt = (v) => {
                if (v >= 10000000) return '₹' + (v / 10000000).toFixed(2) + 'Cr';
                if (v >= 100000) return '₹' + (v / 100000).toFixed(2) + 'L';
                if (v >= 1000) return '₹' + (v / 1000).toFixed(1) + 'K';
                return '₹' + parseFloat(v).toFixed(0);
            };

            return {
                title: s.title,
                total: fmt(s.total),
                target: fmt(s.target),
                hasTarget: s.target > 0,
                percent: pct,
                groups: s.groups,
                accounts: s.accounts,
                isRoot: s.isRoot,
                gradient, barColor, badgeBg, badgeText, badgeBorder, label, icon,
                milestone, dashOffset, circ,
                mascotMood: pct >= 100 ? 'happy' : pct >= 75 ? 'good' : pct >= 50 ? 'mid' : 'sad',
            };
        },

        drillDown(parentId, title) {
            this.history.push({
                id: this.currentParentId,
                title: this.currentTitle
            });
            this.currentParentId = parentId;
            this.currentTitle = title;
            this.activeStats = window.crStats[parentId] || null;
        },
        
        goBack() {
            if (this.history.length > 0) {
                const prev = this.history.pop();
                this.currentParentId = prev.id;
                this.currentTitle = prev.title;
                this.activeStats = window.crStats[prev.id] || window.crStats['root'];
            }
        },
        
        goToLevel(id, title) {
            if (id === 'root') {
                this.currentParentId = 'root';
                this.currentTitle = 'All Groups';
                this.history = [];
                this.activeStats = window.crStats['root'];
            } else {
                const index = this.history.findIndex(h => h.id === id);
                if (index !== -1) {
                    this.currentParentId = id;
                    this.currentTitle = title || 'Detail';
                    this.history = this.history.slice(0, index);
                    this.activeStats = window.crStats[id] || null;
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
