@extends('layouts.app')

@section('header', 'Teams Setup & Manager')

@section('content')
<div class="space-y-6">
    {{-- Top bar --}}
    <div class="bg-slate-900 rounded-3xl p-6 text-white flex flex-col md:flex-row md:items-center justify-between shadow-xl gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center text-xl">
                <i class="fas fa-network-wired text-indigo-400"></i>
            </div>
            <div>
                <h3 class="font-black text-lg tracking-tight">Teams Hierarchy & Assignment Manager</h3>
                <p class="text-slate-400 text-xs mt-0.5">Define regions, assign sales agents and billing branches in a clean, visual hierarchy.</p>
            </div>
        </div>
        
        <div>
            <button type="button" onclick="startNewTeam()" class="bg-blue-600 hover:bg-blue-700 text-white font-black text-xs py-3.5 px-6 rounded-2xl shadow-lg hover:shadow-blue-150 transition transform active:scale-98 flex items-center gap-2">
                <i class="fas fa-plus"></i> Create New Team
            </button>
        </div>
    </div>

    {{-- Main Workspace Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        {{-- Left: Teams List Tree (5 cols) --}}
        <div class="lg:col-span-5 bg-white rounded-3xl border border-gray-150 p-6 shadow-sm flex flex-col min-h-[60vh]">
            <div class="pb-3 border-b border-slate-100 mb-4 flex justify-between items-center">
                <span class="text-xs font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-sitemap text-indigo-500"></i> Region / Team Hierarchy
                </span>
                <span class="text-[10px] text-slate-400 font-bold">{{ count($dbTeams) }} Teams defined</span>
            </div>

            <div class="flex-1 overflow-y-auto max-h-[65vh] pr-1 space-y-1 custom-scrollbar">
                @php
                    $renderTree = function($team, $dbTeams, $level = 0) use (&$renderTree) {
                        $children = $dbTeams->where('parent_id', $team->id);
                        $indent = $level * 20;
                        ?>
                         <div class="team-tree-row relative" style="margin-left: <?php echo $indent; ?>px;">
                            <!-- Line connector -->
                            <?php if ($level > 0): ?>
                                <div class="absolute -left-[14px] top-1/2 -translate-y-1/2 w-3.5 h-[1px] bg-slate-200"></div>
                            <?php endif; ?>
                            
                            <button type="button" 
                                    onclick='selectTeam(<?php echo json_encode($team); ?>)'
                                    id="btn-team-<?php echo $team->id; ?>"
                                    class="w-full text-left py-3 px-4 rounded-2xl border border-gray-100 bg-white hover:bg-slate-50 hover:border-gray-250 transition flex items-center justify-between group team-btn-item shadow-sm mb-1.5">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-7 h-7 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                                        <i class="fas fa-users text-[10px]"></i>
                                    </div>
                                    <span class="font-black text-slate-800 text-xs truncate uppercase tracking-wide"><?php echo htmlspecialchars($team->name); ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[9px] bg-slate-100 text-slate-500 font-bold px-2 py-0.5 rounded-lg flex items-center gap-1">
                                        <?php echo count($team->agents ?: []); ?> <i class="fas fa-user-tie text-[8px] text-violet-500"></i>
                                    </span>
                                    <span class="text-[9px] bg-slate-100 text-slate-500 font-bold px-2 py-0.5 rounded-lg flex items-center gap-1">
                                        <?php echo count($team->branches ?: []); ?> <i class="fas fa-building text-[8px] text-blue-500"></i>
                                    </span>
                                </div>
                            </button>
                        </div>
                        <?php
                        foreach ($children as $child) {
                            $renderTree($child, $dbTeams, $level + 1);
                        }
                    };

                    $rootTeams = $dbTeams->whereNull('parent_id');
                @endphp

                @forelse($rootTeams as $team)
                    @php $renderTree($team, $dbTeams, 0); @endphp
                @empty
                    <div class="text-center py-20 text-slate-400 text-xs italic">
                        <i class="fas fa-inbox text-3xl text-slate-200 mb-3 block"></i>
                        No teams created yet. Click "Create New Team" above.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Right: Team Editor Form (7 cols) --}}
        <div class="lg:col-span-7">
            {{-- Placeholder Card when no team is selected --}}
            <div id="editor-placeholder" class="bg-white rounded-3xl border border-gray-150 p-12 text-center shadow-sm min-h-[60vh] flex flex-col items-center justify-center">
                <div class="w-20 h-20 bg-slate-50 border border-dashed border-slate-250 rounded-3xl flex items-center justify-center mb-5 text-indigo-400">
                    <i class="fas fa-network-wired text-3xl"></i>
                </div>
                <h4 class="font-black text-slate-800 text-sm uppercase tracking-wide">Select a Region or Team</h4>
                <p class="text-slate-400 text-xs mt-1 max-w-sm">Click on any team in the hierarchy on the left, or click "Create New Team" to start configuring parent-child relationships, agents, and branch assignments.</p>
            </div>

            {{-- Editor Form Card --}}
            <div id="editor-form-card" class="bg-white rounded-3xl border border-gray-150 p-6 shadow-sm min-h-[60vh] hidden">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-5">
                    <span class="text-xs font-black text-slate-600 uppercase tracking-widest flex items-center gap-2" id="editor-title">
                        <i class="fas fa-pencil text-blue-500"></i> Configure Team Settings
                    </span>
                    <span class="text-[9px] bg-indigo-50 text-indigo-700 font-bold px-2.5 py-1 rounded-xl uppercase tracking-wider hidden" id="edit-badge">Editing Team</span>
                    <span class="text-[9px] bg-emerald-50 text-emerald-700 font-bold px-2.5 py-1 rounded-xl uppercase tracking-wider hidden" id="create-badge">New Team Configuration</span>
                </div>

                {{-- Action form --}}
                <form id="teamConfigForm" method="POST" action="" class="space-y-5">
                    @csrf
                    <input type="hidden" name="_method" id="form-method" value="POST">

                    {{-- Team Name --}}
                    <div>
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 mb-1.5 block">Team / Region Name</label>
                        <input type="text" name="name" id="input-team-name" required placeholder="e.g. Maharashtra Team, Akola Region"
                               class="w-full border border-gray-200 rounded-2xl py-3 px-4 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-400 outline-none transition">
                    </div>

                    {{-- Parent Team select --}}
                    <div>
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 mb-1.5 block">Parent Team (Hierarchy Level)</label>
                        <select name="parent_id" id="select-parent-id" 
                                class="w-full border border-gray-200 rounded-2xl py-3 px-4 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-400 outline-none transition bg-white">
                            <option value="">None (Top Level Team / Region)</option>
                            @foreach($dbTeams as $optTeam)
                                <option value="{{ $optTeam->id }}" id="parent-opt-{{ $optTeam->id }}">{{ $optTeam->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1 italic ml-1">Example: Setting Maharashtra as parent of Akola places Akola under Maharashtra.</p>
                    </div>

                    {{-- Filter toggle bar --}}
                    <div class="flex items-center justify-between bg-slate-50/80 p-3 rounded-2xl border border-slate-150 mb-2">
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700 select-none">
                            <input type="checkbox" id="toggle-hide-assigned" checked onchange="applyAssignedFilter()" class="rounded border-slate-350 text-blue-600 focus:ring-blue-500">
                            <span>Hide members already assigned to other teams</span>
                        </label>
                        <span class="text-[10px] text-indigo-600 font-black bg-indigo-50 px-2 py-0.5 rounded-lg" id="assigned-filter-status">Unassigned Only</span>
                    </div>

                    {{-- Assignment grid: Agents & Branches --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        {{-- Assign Agents list --}}
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 block">Assign Agents (Salesmen)</label>
                            <input type="text" placeholder="Filter agents list..." oninput="filterSelectorList(this, 'agent-checkboxes-container')" 
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2 text-[11px] font-semibold outline-none focus:ring-1 focus:ring-blue-300">
                            
                            <div class="bg-slate-50/50 rounded-2xl p-3 border border-slate-100 max-h-56 overflow-y-auto space-y-1.5 custom-scrollbar" id="agent-checkboxes-container">
                                @foreach($allAgents as $agent)
                                @php $otherTeam = $agentToTeamMap[$agent] ?? null; @endphp
                                <label class="flex items-center justify-between p-2 bg-white rounded-xl border border-slate-100 cursor-pointer hover:bg-slate-50 transition-colors select-none item-label-row" 
                                       data-search-value="{{ strtolower($agent) }}"
                                       data-assigned-team="{{ $otherTeam }}">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <input type="checkbox" name="agents[]" value="{{ $agent }}" class="rounded border-slate-350 text-blue-600 focus:ring-blue-500 agent-cb">
                                        <span class="text-xs font-bold text-slate-700 truncate">{{ $agent }}</span>
                                    </div>
                                    @if($otherTeam)
                                        <span class="text-[8px] bg-slate-100 text-slate-400 font-bold px-1.5 py-0.5 rounded-md other-team-badge flex-shrink-0" data-team-name="{{ $otherTeam }}">
                                            In {{ $otherTeam }}
                                        </span>
                                    @endif
                                </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Assign Branches list --}}
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 block">Assign Branch Group Names</label>
                            <input type="text" placeholder="Filter branches list..." oninput="filterSelectorList(this, 'branch-checkboxes-container')" 
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2 text-[11px] font-semibold outline-none focus:ring-1 focus:ring-blue-300">
                            
                            <div class="bg-slate-50/50 rounded-2xl p-3 border border-slate-100 max-h-56 overflow-y-auto space-y-1.5 custom-scrollbar" id="branch-checkboxes-container">
                                @foreach($allBranches as $branch)
                                @php $otherTeam = $branchToTeamMap[$branch] ?? null; @endphp
                                <label class="flex items-center justify-between p-2 bg-white rounded-xl border border-slate-100 cursor-pointer hover:bg-slate-50 transition-colors select-none item-label-row" 
                                       data-search-value="{{ strtolower($branch) }}"
                                       data-assigned-team="{{ $otherTeam }}">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <input type="checkbox" name="branches[]" value="{{ $branch }}" class="rounded border-slate-350 text-blue-600 focus:ring-blue-500 branch-cb">
                                        <span class="text-xs font-bold text-slate-700 truncate">{{ $branch }}</span>
                                    </div>
                                    @if($otherTeam)
                                        <span class="text-[8px] bg-slate-100 text-slate-400 font-bold px-1.5 py-0.5 rounded-md other-team-badge flex-shrink-0" data-team-name="{{ $otherTeam }}">
                                            In {{ $otherTeam }}
                                        </span>
                                    @endif
                                </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Form Footer actions --}}
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
                        <div>
                            <button type="button" id="btn-delete-team" onclick="deleteActiveTeam()" class="bg-red-50 hover:bg-red-100 text-red-600 hover:text-red-700 font-black text-xs py-3 px-5 rounded-2xl transition flex items-center gap-1.5">
                                <i class="fas fa-trash-can"></i> Delete Team
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="cancelEditor()" class="bg-slate-100 hover:bg-slate-200 text-slate-650 font-bold py-3 px-5 rounded-2xl text-xs transition">
                                Cancel
                            </button>
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3 px-7 rounded-2xl text-xs shadow-lg shadow-emerald-100 transition transform active:scale-98">
                                <i class="fas fa-floppy-disk mr-1"></i> Save Team Configuration
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

{{-- Hidden Form for Deletion --}}
<form id="deleteTeamForm" method="POST" action="" class="hidden">
    @csrf
    @method('DELETE')
</form>

<script>
let activeTeam = null;

function selectTeam(team) {
    activeTeam = team;
    
    // Switch views
    document.getElementById('editor-placeholder').classList.add('hidden');
    const formCard = document.getElementById('editor-form-card');
    formCard.classList.remove('hidden');

    // Set badges
    document.getElementById('edit-badge').classList.remove('hidden');
    document.getElementById('create-badge').classList.add('hidden');
    document.getElementById('editor-title').innerHTML = `<i class="fas fa-pencil text-blue-500"></i> Configure Team: ${team.name}`;

    // Setup action URL
    const form = document.getElementById('teamConfigForm');
    form.action = `{{ url('reports/collection/teams') }}/${team.id}`;
    document.getElementById('form-method').value = 'PUT';

    // Populate name
    document.getElementById('input-team-name').value = team.name;

    // Reset parent options
    const parentSelect = document.getElementById('select-parent-id');
    parentSelect.value = team.parent_id || "";
    
    // Disable/hide self from parent selection to prevent loop
    Array.from(parentSelect.options).forEach(opt => {
        if (opt.value == team.id) {
            opt.disabled = true;
            opt.style.display = 'none';
        } else {
            opt.disabled = false;
            opt.style.display = '';
        }
    });

    // Populate Agents checkboxes
    const assignedAgents = team.agents || [];
    document.querySelectorAll('.agent-cb').forEach(cb => {
        cb.checked = assignedAgents.includes(cb.value);
        
        // Handle badge styling if assigned to this team
        const badge = cb.closest('label').querySelector('.other-team-badge');
        if (badge) {
            if (assignedAgents.includes(cb.value)) {
                badge.innerText = "Assigned here";
                badge.classList.remove('bg-slate-100', 'text-slate-400');
                badge.classList.add('bg-indigo-50', 'text-indigo-600');
            } else {
                const defaultTeam = badge.getAttribute('data-team-name');
                badge.innerText = `In ${defaultTeam}`;
                badge.classList.add('bg-slate-100', 'text-slate-400');
                badge.classList.remove('bg-indigo-50', 'text-indigo-600');
            }
        }
    });

    // Populate Branches checkboxes
    const assignedBranches = team.branches || [];
    document.querySelectorAll('.branch-cb').forEach(cb => {
        cb.checked = assignedBranches.includes(cb.value);
        
        const badge = cb.closest('label').querySelector('.other-team-badge');
        if (badge) {
            if (assignedBranches.includes(cb.value)) {
                badge.innerText = "Assigned here";
                badge.classList.remove('bg-slate-100', 'text-slate-400');
                badge.classList.add('bg-indigo-50', 'text-indigo-600');
            } else {
                const defaultTeam = badge.getAttribute('data-team-name');
                badge.innerText = `In ${defaultTeam}`;
                badge.classList.add('bg-slate-100', 'text-slate-400');
                badge.classList.remove('bg-indigo-50', 'text-indigo-600');
            }
        }
    });

    // Enable delete button
    document.getElementById('btn-delete-team').classList.remove('hidden');

    // Highlight selected team button in list
    document.querySelectorAll('.team-btn-item').forEach(btn => {
        btn.classList.remove('border-blue-500', 'bg-blue-50/50', 'ring-2', 'ring-blue-100');
    });
    const selectedBtn = document.getElementById('btn-team-' + team.id);
    if (selectedBtn) {
        selectedBtn.classList.add('border-blue-500', 'bg-blue-50/50', 'ring-2', 'ring-blue-100');
    }

    applyAssignedFilter();
}

function startNewTeam() {
    activeTeam = null;

    // Switch views
    document.getElementById('editor-placeholder').classList.add('hidden');
    const formCard = document.getElementById('editor-form-card');
    formCard.classList.remove('hidden');

    // Set badges
    document.getElementById('edit-badge').classList.add('hidden');
    document.getElementById('create-badge').classList.remove('hidden');
    document.getElementById('editor-title').innerHTML = `<i class="fas fa-plus text-emerald-500"></i> Define New Custom Team`;

    // Setup action URL
    const form = document.getElementById('teamConfigForm');
    form.action = "{{ route('reports.collection.teams.store') }}";
    document.getElementById('form-method').value = 'POST';

    // Reset inputs
    document.getElementById('input-team-name').value = "";
    
    const parentSelect = document.getElementById('select-parent-id');
    parentSelect.value = "";
    Array.from(parentSelect.options).forEach(opt => {
        opt.disabled = false;
        opt.style.display = '';
    });

    // Reset checkboxes
    document.querySelectorAll('.agent-cb').forEach(cb => {
        cb.checked = false;
        const badge = cb.closest('label').querySelector('.other-team-badge');
        if (badge) {
            const defaultTeam = badge.getAttribute('data-team-name');
            badge.innerText = `In ${defaultTeam}`;
            badge.classList.add('bg-slate-100', 'text-slate-400');
            badge.classList.remove('bg-indigo-50', 'text-indigo-600');
        }
    });
    document.querySelectorAll('.branch-cb').forEach(cb => {
        cb.checked = false;
        const badge = cb.closest('label').querySelector('.other-team-badge');
        if (badge) {
            const defaultTeam = badge.getAttribute('data-team-name');
            badge.innerText = `In ${defaultTeam}`;
            badge.classList.add('bg-slate-100', 'text-slate-400');
            badge.classList.remove('bg-indigo-50', 'text-indigo-600');
        }
    });

    // Hide delete button
    document.getElementById('btn-delete-team').classList.add('hidden');

    // Clear highlight
    document.querySelectorAll('.team-btn-item').forEach(btn => {
        btn.classList.remove('border-blue-500', 'bg-blue-50/50', 'ring-2', 'ring-blue-100');
    });

    applyAssignedFilter();
}

function cancelEditor() {
    activeTeam = null;
    document.getElementById('editor-placeholder').classList.remove('hidden');
    document.getElementById('editor-form-card').classList.add('hidden');
    
    // Clear highlight
    document.querySelectorAll('.team-btn-item').forEach(btn => {
        btn.classList.remove('border-blue-500', 'bg-blue-50/50', 'ring-2', 'ring-blue-100');
    });
}

function deleteActiveTeam() {
    if (!activeTeam) return;

    if (confirm(`Are you sure you want to delete the team "${activeTeam.name}"? Sub-teams, agents, and branch assignments will be adjusted accordingly.`)) {
        const form = document.getElementById('deleteTeamForm');
        form.action = `{{ url('reports/collection/teams') }}/${activeTeam.id}`;
        form.submit();
    }
}

function applyAssignedFilter() {
    const hideAssigned = document.getElementById('toggle-hide-assigned').checked;
    const activeTeamName = activeTeam ? activeTeam.name : null;
    const statusSpan = document.getElementById('assigned-filter-status');
    if (statusSpan) {
        statusSpan.innerText = hideAssigned ? 'Unassigned Only' : 'Showing All Members';
    }

    document.querySelectorAll('.item-label-row').forEach(row => {
        const assignedTeam = row.getAttribute('data-assigned-team');
        const isAssignedToOther = assignedTeam && assignedTeam !== activeTeamName;
        
        if (hideAssigned && isAssignedToOther) {
            row.classList.add('hidden-by-assigned-filter');
            row.style.display = 'none';
        } else {
            row.classList.remove('hidden-by-assigned-filter');
            row.style.display = '';
        }
    });

    // Re-apply text search filter if typed
    const agentSearch = document.querySelector('input[placeholder="Filter agents list..."]');
    if (agentSearch && agentSearch.value) {
        filterSelectorList(agentSearch, 'agent-checkboxes-container');
    }
    const branchSearch = document.querySelector('input[placeholder="Filter branches list..."]');
    if (branchSearch && branchSearch.value) {
        filterSelectorList(branchSearch, 'branch-checkboxes-container');
    }
}

function filterSelectorList(input, containerId) {
    const query = input.value.toLowerCase();
    const container = document.getElementById(containerId);
    const rows = container.querySelectorAll('.item-label-row');
    rows.forEach(row => {
        if (row.classList.contains('hidden-by-assigned-filter')) {
            row.style.display = 'none';
            return;
        }
        const val = row.getAttribute('data-search-value');
        if (val.includes(query)) {
            row.style.display = 'flex';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
@endsection
