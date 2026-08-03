{{-- Mobile Collection Team Card Partial (Recursive) --}}
@php
    $teamName = $team->name;
    $bSummary = $branchSummary[$teamName] ?? ['total'=>0,'parties'=>0,'agents'=>0];
    $tSlug = 'mob_t_' . Str::slug($teamName) . '_' . $team->id;
    $tTargetAmt = $teamTargets[$team->id] ?? 0;
    $tPercent = $tTargetAmt > 0 ? min(100, round(($bSummary['total'] / $tTargetAmt) * 100)) : 0;
    $tBarColor = $tPercent >= 100 ? 'bg-emerald-400' : ($tPercent >= 60 ? 'bg-amber-400' : 'bg-rose-400');
    $childrenTeams = $dbTeams->where('parent_id', $team->id);
    $directAgentNames = $team->agents ?: [];
    $isRoot = $team->parent_id === null;
@endphp

<div class="overflow-hidden rounded-[1.8rem] shadow-md border border-white/60 mb-3 bg-white/80 backdrop-blur-xl">
    <button type="button" onclick="toggleMobAccordion('{{ $tSlug }}')" class="w-full text-left">
        <div class="{{ $isRoot ? 'bg-gradient-to-r from-slate-800 to-slate-900' : ($team->parent_id && $dbTeams->find($team->parent_id)?->parent_id !== null ? 'bg-gradient-to-r from-slate-600 to-slate-700' : 'bg-gradient-to-r from-slate-700 to-slate-800') }} p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 flex-shrink-0">
                        @if($isRoot)
                            <i class="fas fa-globe text-white text-sm"></i>
                        @elseif($childrenTeams->count() > 0)
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
                    <div class="w-7 h-7 bg-white/10 rounded-xl flex items-center justify-center border border-white/20">
                        <i class="fas fa-chevron-down text-white/60 text-[10px] transition-transform" id="{{ $tSlug }}_chev"></i>
                    </div>
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
    </button>
    
    <div id="{{ $tSlug }}" class="hidden">
        @if($childrenTeams->count() > 0)
        <div class="p-3 pb-1">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1 flex items-center gap-1.5">
                <i class="fas fa-sitemap text-slate-300 text-[7px]"></i>Sub-Regions / Sub-Teams
            </div>
            <div class="space-y-2 border-l-2 border-slate-100 ml-2 pl-3">
                @foreach($childrenTeams as $child)
                    @include('mobile.partials.collection_team_card', [
                        'team' => $child,
                        'dbTeams' => $dbTeams,
                        'grouped' => $grouped,
                        'branchSummary' => $branchSummary,
                        'teamTargets' => $teamTargets,
                        'agentTargets' => $agentTargets,
                        'crField' => $crField,
                        'drField' => $drField,
                        'partyNameKey' => $partyNameKey,
                        'parseAmt' => $parseAmt,
                        'formatIndian' => $formatIndian,
                        'formatCr' => $formatCr
                    ])
                @endforeach
            </div>
        </div>
        @endif
        
        @foreach($directAgentNames as $agentName)
            @if(!isset($grouped[$teamName][$agentName])) @continue @endif
            @php
                $agentRows = $grouped[$teamName][$agentName];
                $agentTotal = array_sum(array_map(fn($r) => $parseAmt($r[$crField] ?? 0), $agentRows));
                $agentSlug = $tSlug . '_ag_' . Str::slug($agentName);
                $targetAmt = $agentTargets[$agentName] ?? 0;
                $percent = $targetAmt > 0 ? min(100, round(($agentTotal / $targetAmt) * 100)) : 0;
                $progressColor = $percent >= 100 ? 'bg-emerald-400' : ($percent >= 60 ? 'bg-amber-400' : 'bg-rose-400');
                $progressTextColor = $percent >= 100 ? 'text-emerald-600' : ($percent >= 60 ? 'text-amber-600' : 'text-rose-500');
            @endphp
            
            <div class="px-3 pb-2 pt-1">
                <div class="bg-white border border-slate-100 rounded-2xl overflow-hidden shadow-sm">
                    <button type="button" onclick="toggleMobAgentDetail('{{ $agentSlug }}')" class="w-full text-left px-4 py-3 flex items-center justify-between bg-white hover:bg-slate-50/50 transition-colors">
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
                            <i class="fas fa-chevron-down text-slate-300 text-[9px] transition-transform" id="{{ $agentSlug }}_chev"></i>
                        </div>
                    </button>
                    
                    @if($targetAmt > 0)
                    <div class="px-4 pb-1.5">
                        <div class="w-full bg-slate-100 rounded-full h-1 overflow-hidden">
                            <div class="{{ $progressColor }} h-1 rounded-full" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                    @endif
                    
                    <div id="{{ $agentSlug }}" class="hidden border-t border-slate-50 bg-slate-50/30 px-3 py-2 space-y-1.5">
                        @foreach($agentRows as $party)
                            @php
                                $pName  = trim($party[$partyNameKey ?? 'AC_Name'] ?? $party['AC_Name'] ?? $party['AcName'] ?? $party['PartyName'] ?? '—');
                                $pCode  = trim($party['AC_Code'] ?? $party['ActCode'] ?? $party['Ac_Code'] ?? '');
                                $pCrAmt = $crField ? $parseAmt($party[$crField] ?? 0) : 0;
                            @endphp
                            <div class="bg-white border border-slate-100/80 rounded-xl px-3 py-2.5 flex items-center justify-between">
                                <div class="min-w-0 pr-2">
                                    <div class="text-[8px] font-black text-indigo-500 font-mono tracking-tight">{{ $pCode }}</div>
                                    <div class="text-[10px] font-bold text-slate-700 truncate mt-0.5">{{ $pName }}</div>
                                </div>
                                <div class="font-black text-emerald-600 text-[11px] flex-shrink-0">
                                    {!! $pCrAmt > 0 ? '₹' . $formatIndian($pCrAmt) : '<span class="text-slate-300">—</span>' !!}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
