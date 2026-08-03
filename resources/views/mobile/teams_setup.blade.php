@extends('layouts.mobile')

@section('content')
<div class="space-y-5 pb-24" x-data="teamsApp()" @open-edit-modal.window="openEdit($event.detail)" x-init="init()">

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-3xl p-4 flex items-center gap-3 text-xs font-bold shadow-sm">
        <div class="w-8 h-8 bg-emerald-500 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-check text-white text-xs"></i>
        </div>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error') || $errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-3xl p-4 flex items-center gap-3 text-xs font-bold shadow-sm">
        <div class="w-8 h-8 bg-rose-500 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-exclamation text-white text-xs"></i>
        </div>
        <span>{{ session('error') ?? $errors->first() }}</span>
    </div>
    @endif

    {{-- Header --}}
    <div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-violet-600 via-violet-700 to-indigo-800 p-6 shadow-xl shadow-violet-200/50">
        <div class="absolute -top-8 -right-8 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
        <div class="absolute -bottom-6 -left-6 w-32 h-32 bg-indigo-400/20 rounded-full blur-xl"></div>
        
        <div class="relative z-10 flex items-start justify-between">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <a href="{{ route('mobile.collection-report') }}" class="w-8 h-8 bg-white/15 rounded-xl flex items-center justify-center text-white/80 active:scale-90 transition-transform border border-white/20">
                        <i class="fas fa-arrow-left text-[10px]"></i>
                    </a>
                    <div class="w-8 h-8 bg-white/15 rounded-xl flex items-center justify-center border border-white/20">
                        <i class="fas fa-network-wired text-white text-[10px]"></i>
                    </div>
                </div>
                <h2 class="text-2xl font-black text-white tracking-tight leading-none">Teams Setup</h2>
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-violet-200 mt-1">Hierarchy & Assignment Manager</p>
                <div class="flex items-center gap-2 mt-3">
                    <span class="text-[10px] font-black text-violet-200 bg-white/10 px-3 py-1 rounded-xl border border-white/20">
                        <i class="fas fa-layer-group mr-1 text-[8px]"></i>{{ count($dbTeams) }} Teams
                    </span>
                    <span class="text-[10px] font-black text-violet-200 bg-white/10 px-3 py-1 rounded-xl border border-white/20">
                        <i class="fas fa-user-tie mr-1 text-[8px]"></i>{{ count($allAgents) }} Agents
                    </span>
                </div>
            </div>
            @if(Auth::user()->hasFeature('mobile_teams_setup', 'create') || Auth::user()->role === 'admin')
            <button @click="openCreate()" 
                    class="w-12 h-12 bg-white text-violet-600 rounded-2xl flex items-center justify-center shadow-lg shadow-violet-900/20 active:scale-90 transition-transform border border-white/80 font-black text-lg">
                <i class="fas fa-plus"></i>
            </button>
            @endif
        </div>
    </div>

    {{-- Teams List - Hierarchical --}}
    <div class="space-y-3">
        @forelse($dbTeams->whereNull('parent_id') as $rootTeam)
            @include('mobile.partials.team_card', ['team' => $rootTeam, 'dbTeams' => $dbTeams, 'agentToTeamMap' => $agentToTeamMap, 'branchToTeamMap' => $branchToTeamMap, 'level' => 0])
        @empty
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 rounded-[2.5rem] p-12 text-center shadow-sm">
            <div class="w-16 h-16 bg-violet-50 rounded-3xl flex items-center justify-center mx-auto mb-4 border border-violet-100">
                <i class="fas fa-network-wired text-2xl text-violet-300"></i>
            </div>
            <h4 class="font-black text-slate-700 text-sm uppercase tracking-wide">No Teams Yet</h4>
            <p class="text-slate-400 text-xs mt-1 font-bold">Tap the <span class="text-violet-600">+</span> button above to create your first team.</p>
        </div>
        @endforelse
    </div>
    
    {{-- All Available Agents Reference --}}
    @if(count($allAgents) > 0)
    <div class="bg-white/70 backdrop-blur-xl border border-white/60 rounded-[2rem] p-5 shadow-sm">
        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <i class="fas fa-users text-slate-300"></i>All Available Agents ({{ count($allAgents) }})
        </div>
        <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto">
            @foreach($allAgents as $agent)
            @php $assignedTo = $agentToTeamMap[$agent] ?? null; @endphp
            <span class="text-[9px] font-bold px-2.5 py-1 rounded-lg border {{ $assignedTo ? 'text-violet-700 bg-violet-50 border-violet-100' : 'text-slate-500 bg-slate-50 border-slate-100' }}">
                {{ $agent }}
                @if($assignedTo)
                <span class="text-[7px] text-violet-400 font-black ml-0.5">({{ $assignedTo }})</span>
                @endif
            </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Create/Edit Team Bottom Sheet Modal --}}
    <div x-show="showModal" x-cloak 
         class="fixed inset-0 z-[200] flex items-end justify-center"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal()"></div>
        
        {{-- Bottom Sheet --}}
        <div class="relative w-full max-w-lg bg-white rounded-t-[2.5rem] shadow-2xl max-h-[92vh] flex flex-col"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             style="transform: translateY(0);">
            
            {{-- Sheet Handle --}}
            <div class="flex justify-center pt-4 pb-2 flex-shrink-0">
                <div class="w-10 h-1 bg-slate-200 rounded-full"></div>
            </div>
            
            {{-- Modal Header --}}
            <div class="px-6 pb-4 flex items-center justify-between flex-shrink-0 border-b border-slate-100">
                <div>
                    <h3 class="text-lg font-black text-slate-800 tracking-tight" x-text="isEditing ? 'Edit Team Configuration' : 'Create New Team'"></h3>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-0.5" x-text="isEditing ? 'Modify team settings & assignments' : 'Define team structure & assign members'"></p>
                </div>
                <button @click="closeModal()" class="w-10 h-10 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-400 active:scale-90 transition-transform">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            
            {{-- Form Body --}}
            <div class="flex-1 overflow-y-auto">
                <form :action="formAction" method="POST" id="mobileTeamForm" class="p-6 space-y-5">
                    @csrf
                    <input type="hidden" name="_method" :value="isEditing ? 'PUT' : 'POST'">
                    
                    {{-- Team Name --}}
                    <div class="space-y-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Team / Region Name *</label>
                        <input type="text" name="name" x-model="form.name" required
                               placeholder="e.g., Maharashtra Region, Akola Team"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3.5 px-4 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-violet-400 focus:border-violet-400 outline-none transition">
                    </div>
                    
                    {{-- Parent Team --}}
                    <div class="space-y-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Parent Team (Hierarchy)</label>
                        <select name="parent_id" x-model="form.parent_id"
                                class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3.5 px-4 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-violet-400 outline-none transition">
                            <option value="">None (Top-Level Region)</option>
                            @foreach($dbTeams as $optTeam)
                            <option value="{{ $optTeam->id }}" :disabled="isEditing && form.editId == {{ $optTeam->id }}">
                                {{ $optTeam->name }}
                            </option>
                            @endforeach
                        </select>
                        <p class="text-[9px] text-slate-400 font-bold ml-1">Set a parent to nest this team inside a region.</p>
                    </div>
                    
                    {{-- Hide assigned toggle --}}
                    <div class="bg-violet-50/50 p-3.5 rounded-2xl border border-violet-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-violet-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-filter text-violet-500 text-[10px]"></i>
                            </div>
                            <label class="text-xs font-black text-slate-700 cursor-pointer" for="hideAssigned">Hide already-assigned members</label>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="hideAssigned" class="sr-only peer" checked onchange="applyMobileAssignedFilter()">
                            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-violet-500"></div>
                        </label>
                    </div>
                    
                    {{-- Assign Agents --}}
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Assign Agents</label>
                            <span class="text-[9px] font-black text-violet-600 bg-violet-50 px-2 py-0.5 rounded-lg" x-text="form.agents.length + ' selected'"></span>
                        </div>
                        <input type="text" placeholder="Search agents..." 
                               oninput="filterMobileList(this, 'agent-list-mob')"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 text-xs font-bold text-slate-700 outline-none focus:ring-1 focus:ring-violet-300 transition">
                        <div class="space-y-1.5 max-h-48 overflow-y-auto bg-slate-50/50 rounded-2xl p-3 border border-slate-100" id="agent-list-mob">
                            @foreach($allAgents as $agent)
                            @php $otherTeam = $agentToTeamMap[$agent] ?? null; @endphp
                            <label class="agent-mob-item flex items-center justify-between p-2.5 bg-white rounded-xl border border-slate-100 cursor-pointer hover:border-violet-200 hover:bg-violet-50/30 transition-all select-none"
                                   data-search="{{ strtolower($agent) }}"
                                   data-assigned-team="{{ $otherTeam ?? '' }}">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-5 h-5 flex-shrink-0">
                                        <input type="checkbox" name="agents[]" value="{{ $agent }}" 
                                               x-model="form.agents"
                                               class="w-4 h-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                    </div>
                                    <span class="text-xs font-bold text-slate-700 truncate">{{ $agent }}</span>
                                </div>
                                @if($otherTeam)
                                <span class="text-[8px] font-black text-slate-400 bg-slate-100 px-2 py-0.5 rounded-lg other-team-badge flex-shrink-0" data-team-name="{{ $otherTeam }}">
                                    {{ $otherTeam }}
                                </span>
                                @endif
                            </label>
                            @endforeach
                        </div>
                    </div>
                    
                    {{-- Assign Branches --}}
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Assign Branches</label>
                            <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded-lg" x-text="form.branches.length + ' selected'"></span>
                        </div>
                        <input type="text" placeholder="Search branches..." 
                               oninput="filterMobileList(this, 'branch-list-mob')"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 text-xs font-bold text-slate-700 outline-none focus:ring-1 focus:ring-blue-300 transition">
                        <div class="space-y-1.5 max-h-48 overflow-y-auto bg-slate-50/50 rounded-2xl p-3 border border-slate-100" id="branch-list-mob">
                            @foreach($allBranches as $branch)
                            @php $otherTeam = $branchToTeamMap[$branch] ?? null; @endphp
                            <label class="branch-mob-item flex items-center justify-between p-2.5 bg-white rounded-xl border border-slate-100 cursor-pointer hover:border-blue-200 hover:bg-blue-50/30 transition-all select-none"
                                   data-search="{{ strtolower($branch) }}"
                                   data-assigned-team="{{ $otherTeam ?? '' }}">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-5 h-5 flex-shrink-0">
                                        <input type="checkbox" name="branches[]" value="{{ $branch }}"
                                               x-model="form.branches"
                                               class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </div>
                                    <span class="text-xs font-bold text-slate-700 truncate">{{ $branch }}</span>
                                </div>
                                @if($otherTeam)
                                <span class="text-[8px] font-black text-slate-400 bg-slate-100 px-2 py-0.5 rounded-lg other-team-badge flex-shrink-0" data-team-name="{{ $otherTeam }}">
                                    {{ $otherTeam }}
                                </span>
                                @endif
                            </label>
                            @endforeach
                        </div>
                    </div>
                </form>
            </div>
            
            {{-- Footer Actions --}}
            <div class="px-6 py-4 border-t border-slate-100 bg-white flex-shrink-0 flex items-center gap-3">
                <button @click="closeModal()" 
                        class="flex-1 py-3.5 bg-slate-100 text-slate-600 font-black text-xs uppercase tracking-widest rounded-2xl active:scale-95 transition-transform">
                    Cancel
                </button>
                <button type="submit" form="mobileTeamForm"
                        class="flex-1 py-3.5 bg-gradient-to-r from-violet-600 to-indigo-600 text-white font-black text-xs uppercase tracking-widest rounded-2xl shadow-lg shadow-violet-200 active:scale-95 transition-transform flex items-center justify-center gap-2">
                    <i class="fas fa-floppy-disk"></i>
                    <span x-text="isEditing ? 'Update Team' : 'Create Team'"></span>
                </button>
            </div>
        </div>
    </div>
    
    {{-- Hidden Delete Form --}}
    <form id="deleteMobTeamForm" method="POST" action="" class="hidden">
        @csrf
        @method('DELETE')
    </form>

</div>

<script>
function teamsApp() {
    return {
        showModal: false,
        isEditing: false,
        formAction: '{{ route("mobile.reports.collection.teams.store") }}',
        form: {
            editId: null,
            name: '',
            parent_id: '',
            agents: [],
            branches: []
        },
        
        init() {
            this.$nextTick(() => applyMobileAssignedFilter());
        },
        
        openCreate() {
            this.isEditing = false;
            this.formAction = '{{ route("mobile.reports.collection.teams.store") }}';
            this.form = { editId: null, name: '', parent_id: '', agents: [], branches: [] };
            this.showModal = true;
            this.$nextTick(() => applyMobileAssignedFilter());
        },
        
        openEdit(teamData) {
            this.isEditing = true;
            this.form = {
                editId: teamData.id,
                name: teamData.name,
                parent_id: teamData.parent_id ? String(teamData.parent_id) : '',
                agents: teamData.agents || [],
                branches: teamData.branches || []
            };
            this.formAction = '/mobile/collection/teams/' + teamData.id;
            this.showModal = true;
            this.$nextTick(() => {
                updateBadgesForCurrentTeam(teamData.name, teamData.agents || [], teamData.branches || []);
                applyMobileAssignedFilter();
            });
        },
        
        closeModal() {
            this.showModal = false;
        }
    };
}

function editTeamMobile(btn) {
    try {
        const teamData = (btn instanceof HTMLElement) ? JSON.parse(btn.getAttribute('data-team')) : btn;
        window.dispatchEvent(new CustomEvent('open-edit-modal', { detail: teamData }));
    } catch (e) {
        console.error('Error opening edit modal:', e);
    }
}

function deleteMobTeam(id, name) {
    if (confirm('Delete team "' + name + '"? Sub-teams will become top-level.')) {
        const form = document.getElementById('deleteMobTeamForm');
        form.action = '/mobile/collection/teams/' + id;
        form.submit();
    }
}

function toggleTeamDetails(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const teamId = id.replace('detail_', '');
    const icon = document.getElementById('toggle_icon_' + teamId);
    el.classList.toggle('hidden');
    if (icon) {
        icon.style.transform = el.classList.contains('hidden') ? '' : 'rotate(180deg)';
    }
}

function filterMobileList(input, containerId) {
    const q = input.value.toLowerCase();
    document.getElementById(containerId).querySelectorAll('label').forEach(row => {
        if (row.classList.contains('hidden-by-filter')) return;
        const val = (row.getAttribute('data-search') || '').toLowerCase();
        row.style.display = val.includes(q) ? 'flex' : 'none';
    });
}

function applyMobileAssignedFilter() {
    const hide = document.getElementById('hideAssigned')?.checked;
    const currentTeamName = document.querySelector('input[name="name"]')?.value || null;
    
    document.querySelectorAll('.agent-mob-item, .branch-mob-item').forEach(row => {
        const assignedTeam = row.getAttribute('data-assigned-team');
        const isOther = assignedTeam && assignedTeam !== currentTeamName;
        if (hide && isOther) {
            row.style.display = 'none';
            row.classList.add('hidden-by-filter');
        } else {
            row.classList.remove('hidden-by-filter');
            row.style.display = 'flex';
        }
    });
}

function updateBadgesForCurrentTeam(teamName, agents, branches) {
    document.querySelectorAll('.agent-mob-item').forEach(row => {
        const agentName = row.querySelector('input[name="agents[]"]')?.value;
        const badge = row.querySelector('.other-team-badge');
        if (!badge) return;
        if (agentName && agents.includes(agentName)) {
            badge.textContent = 'Assigned here';
            badge.classList.add('bg-violet-100', 'text-violet-600');
            badge.classList.remove('bg-slate-100', 'text-slate-400');
        } else {
            badge.textContent = badge.getAttribute('data-team-name');
            badge.classList.remove('bg-violet-100', 'text-violet-600');
            badge.classList.add('bg-slate-100', 'text-slate-400');
        }
    });
    document.querySelectorAll('.branch-mob-item').forEach(row => {
        const branchName = row.querySelector('input[name="branches[]"]')?.value;
        const badge = row.querySelector('.other-team-badge');
        if (!badge) return;
        if (branchName && branches.includes(branchName)) {
            badge.textContent = 'Assigned here';
            badge.classList.add('bg-blue-100', 'text-blue-600');
            badge.classList.remove('bg-slate-100', 'text-slate-400');
        } else {
            badge.textContent = badge.getAttribute('data-team-name');
            badge.classList.remove('bg-blue-100', 'text-blue-600');
            badge.classList.add('bg-slate-100', 'text-slate-400');
        }
    });
}
</script>
@endsection
