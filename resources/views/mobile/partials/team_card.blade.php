{{-- Mobile Team Card Partial - Recursive Blade Include --}}
@php
    $agentCount = count($team->agents ?: []);
    $branchCount = count($team->branches ?: []);
    $children = $dbTeams->where('parent_id', $team->id);
    $childCount = $children->count();
    $isRoot = $team->parent_id === null;
    $headerGradient = $isRoot
        ? 'bg-gradient-to-r from-violet-600 to-indigo-600'
        : ($level === 1 ? 'bg-gradient-to-r from-slate-700 to-slate-800' : 'bg-gradient-to-r from-slate-600 to-slate-700');
@endphp

<div class="{{ $level > 0 ? 'mt-2' : '' }}">
    <div class="bg-white/80 backdrop-blur-xl border border-white/60 shadow-sm rounded-[1.5rem] overflow-hidden">
        
        {{-- Team Header --}}
        <div class="{{ $headerGradient }} p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0 border border-white/30">
                        @if($isRoot)
                            <i class="fas fa-globe text-white text-sm"></i>
                        @elseif($level === 1)
                            <i class="fas fa-map-marker-alt text-white text-sm"></i>
                        @else
                            <i class="fas fa-users text-white text-sm"></i>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="font-black text-white text-sm tracking-tight uppercase truncate">{{ $team->name }}</div>
                        <div class="flex items-center gap-2 mt-0.5">
                            @if($team->parent_id)
                                <span class="text-[8px] text-white/60 font-bold">Sub-team</span>
                            @else
                                <span class="text-[8px] text-white/60 font-bold">Top Level</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if(Auth::user()->hasFeature('mobile_teams_setup', 'edit') || Auth::user()->role === 'admin')
                    <button data-team="{{ json_encode(['id'=>$team->id, 'name'=>$team->name, 'agents'=>$team->agents ?: [], 'branches'=>$team->branches ?: [], 'parent_id'=>$team->parent_id]) }}"
                            onclick="editTeamMobile(this)"
                            class="w-8 h-8 bg-white/20 border border-white/30 rounded-xl flex items-center justify-center text-white active:scale-90 transition-transform">
                        <i class="fas fa-pencil text-[10px]"></i>
                    </button>
                    @endif
                    @if(Auth::user()->hasFeature('mobile_teams_setup', 'delete') || Auth::user()->role === 'admin')
                    <button onclick="deleteMobTeam({{ $team->id }}, '{{ addslashes($team->name) }}')"
                            class="w-8 h-8 bg-rose-500/80 border border-rose-400/30 rounded-xl flex items-center justify-center text-white active:scale-90 transition-transform">
                        <i class="fas fa-trash text-[10px]"></i>
                    </button>
                    @endif
                </div>
            </div>
        </div>
        
        {{-- Stats Row --}}
        <div class="px-4 py-3 flex items-center justify-between bg-white/40">
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-1.5 text-[10px] font-black text-violet-700 bg-violet-50 px-2.5 py-1 rounded-xl border border-violet-100">
                    <i class="fas fa-user-tie text-violet-500 text-[8px]"></i>
                    {{ $agentCount }} {{ $agentCount === 1 ? 'Agent' : 'Agents' }}
                </div>
                <div class="flex items-center gap-1.5 text-[10px] font-black text-blue-700 bg-blue-50 px-2.5 py-1 rounded-xl border border-blue-100">
                    <i class="fas fa-building text-blue-500 text-[8px]"></i>
                    {{ $branchCount }} {{ $branchCount === 1 ? 'Branch' : 'Branches' }}
                </div>
                @if($childCount > 0)
                <div class="flex items-center gap-1.5 text-[10px] font-black text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-xl border border-emerald-100">
                    <i class="fas fa-sitemap text-emerald-500 text-[8px]"></i>
                    {{ $childCount }} Sub
                </div>
                @endif
            </div>
            
            @if($agentCount > 0 || $branchCount > 0)
            <button onclick="toggleTeamDetails('detail_{{ $team->id }}')" 
                    class="w-7 h-7 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 active:scale-90 transition-transform">
                <i class="fas fa-chevron-down text-[10px] transition-transform" id="toggle_icon_{{ $team->id }}"></i>
            </button>
            @endif
        </div>
        
        {{-- Expanded details: agents & branches --}}
        @if($agentCount > 0 || $branchCount > 0)
        <div id="detail_{{ $team->id }}" class="hidden border-t border-slate-100 bg-white/60">
            @if($agentCount > 0)
            <div class="px-4 pt-3 pb-2">
                <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-2">Assigned Agents</div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($team->agents ?: [] as $agent)
                    <span class="text-[9px] font-black text-violet-700 bg-violet-50 px-2.5 py-1 rounded-lg border border-violet-100 flex items-center gap-1">
                        <i class="fas fa-user-tie text-violet-400 text-[7px]"></i>
                        {{ $agent }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
            @if($branchCount > 0)
            <div class="px-4 pt-2 pb-3">
                <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-2">Assigned Branches</div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($team->branches ?: [] as $branch)
                    <span class="text-[9px] font-black text-blue-700 bg-blue-50 px-2.5 py-1 rounded-lg border border-blue-100 flex items-center gap-1">
                        <i class="fas fa-building text-blue-400 text-[7px]"></i>
                        {{ $branch }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>
    
    {{-- Recursive children --}}
    @if($childCount > 0)
    <div class="border-l-2 border-violet-200/60 ml-5 pl-2 mt-2 space-y-2">
        @foreach($children as $childTeam)
            @include('mobile.partials.team_card', [
                'team' => $childTeam,
                'dbTeams' => $dbTeams,
                'agentToTeamMap' => $agentToTeamMap,
                'branchToTeamMap' => $branchToTeamMap,
                'level' => $level + 1
            ])
        @endforeach
    </div>
    @endif
</div>
