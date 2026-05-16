@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="settingsApp()">
    <!-- Header Block -->
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden mb-2">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 font-900 tracking-tighter">Settings</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">System & Branch Mappings</p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="showAddBranch = true" class="w-10 h-10 grad-cyan rounded-xl flex items-center justify-center text-white shadow-lg shadow-cyan-100 border-2 border-white">
                <i class="fas fa-plus text-xs"></i>
            </button>
        </div>
    </div>
</div>

    <!-- API Status Cards -->
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-5 rounded-[2rem] border border-white/80 space-y-2">
            <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Inventory API</p>
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-emerald-500 rounded-full shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                <span class="text-xs font-bold text-slate-700">Healthy</span>
            </div>
        </div>
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-5 rounded-[2rem] border border-white/80 space-y-2">
            <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Product API</p>
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-emerald-500 rounded-full shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                <span class="text-xs font-bold text-slate-700">Healthy</span>
            </div>
        </div>
    </div>

    <!-- Tabs Header -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 no-scrollbar px-1" x-data="{ activeTab: 'branches' }">
        <button @click="activeTab = 'branches'" :class="activeTab === 'branches' ? 'bg-indigo-500 text-white' : 'bg-white text-slate-400'" class="px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md">Branches</button>
        <button @click="activeTab = 'types'" :class="activeTab === 'types' ? 'bg-indigo-500 text-white' : 'bg-white text-slate-400'" class="px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md">Types</button>
        <button @click="activeTab = 'groups'" :class="activeTab === 'groups' ? 'bg-indigo-500 text-white' : 'bg-white text-slate-400'" class="px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md">Groups</button>
    </div>

    <!-- Branch List Section -->
    <div x-show="activeTab === 'branches'" class="space-y-4">
        <div class="flex items-center justify-between px-3">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Branch Mappings</h3>
            <button @click="showAddBranch = true" class="text-indigo-500 text-[10px] font-black uppercase tracking-widest">+ Add New</button>
        </div>
        
        <div class="space-y-3">
            @foreach($branches as $branch)
            <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-6 rounded-[2rem] border border-white/80 flex items-center justify-between group">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white/60 backdrop-blur-md rounded-2xl flex items-center justify-center text-slate-400 font-black text-xs shadow-inner">
                        {{ $branch->code }}
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 font-900 tracking-tight">{{ $branch->name }}</h4>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">ERP Sync Active</p>
                    </div>
                </div>
                <button @click="deleteBranch({{ $branch->id }})" class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center text-rose-300 hover:text-rose-500 transition-colors">
                    <i class="fas fa-trash-can text-xs"></i>
                </button>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Types List Section -->
    <div x-show="activeTab === 'types'" class="space-y-4" x-init="fetchTypes()">
        <div class="flex items-center justify-between px-3">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Product Categories</h3>
            <button @click="showAddType = true" class="text-indigo-500 text-[10px] font-black uppercase tracking-widest">+ Add New</button>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <template x-for="type in types" :key="type.id">
                <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-5 rounded-2xl border border-white/80 flex flex-col items-center justify-center text-center gap-2">
                    <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-400">
                        <i class="fas fa-layer-group text-xs"></i>
                    </div>
                    <span class="text-[10px] font-black text-slate-700 uppercase tracking-tight" x-text="type.type_name"></span>
                </div>
            </template>
        </div>
    </div>

    <!-- Groups List Section -->
    <div x-show="activeTab === 'groups'" class="space-y-4" x-init="fetchGroups()">
        <div class="flex items-center justify-between px-3">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Business Groups</h3>
            <button @click="showAddGroup = true" class="text-indigo-500 text-[10px] font-black uppercase tracking-widest">+ Add New</button>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <template x-for="group in groups" :key="group.id">
                <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-5 rounded-2xl border border-white/80 flex flex-col items-center justify-center text-center gap-2">
                    <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-500">
                        <i class="fas fa-tags text-xs"></i>
                    </div>
                    <span class="text-[10px] font-black text-slate-700 uppercase tracking-tight" x-text="group.group_name"></span>
                </div>
            </template>
        </div>
    </div>

    <!-- Add Branch Modal (Already Exists, will move scripts below) -->

    <!-- Add Branch Modal -->
    <div x-show="showAddBranch" 
         x-cloak 
         class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAddBranch = false"></div>
        
        <div class="relative w-full max-w-lg bg-white rounded-[3rem] p-8 space-y-8 shadow-2xl animate-slide-up">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-900 text-slate-800 font-900 tracking-tighter">New Branch</h3>
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Map ERP Location Code</p>
                </div>
                <button @click="showAddBranch = false" class="w-10 h-10 bg-white/60 backdrop-blur-md rounded-2xl flex items-center justify-center text-slate-400">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="space-y-6">
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Branch Code (ERP)</label>
                    <input type="text" x-model="branchForm.code" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700" placeholder="e.g. 101">
                </div>
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Branch Name</label>
                    <input type="text" x-model="branchForm.name" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700" placeholder="e.g. Factory Site A">
                </div>
            </div>

            <button @click="saveBranch" class="w-full grad-cyan p-5 rounded-2xl text-white font-900 uppercase tracking-widest text-xs shadow-xl shadow-cyan-100 active:scale-95 transition-all">
                Add Mapping
            </button>
        </div>
    </div>

    <!-- Add Type Modal -->
    <div x-show="showAddType" x-cloak class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAddType = false"></div>
        <div class="relative w-full max-w-lg bg-white rounded-[3rem] p-8 space-y-8 shadow-2xl animate-slide-up">
            <h3 class="text-2xl font-900 text-slate-800 font-900 tracking-tighter">New Category</h3>
            <input type="text" x-model="typeForm.type_name" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700" placeholder="e.g. Semi Finished Good">
            <button @click="saveType" class="w-full grad-indigo p-5 rounded-2xl text-white font-900 uppercase tracking-widest text-xs shadow-xl active:scale-95 transition-all">Save Category</button>
        </div>
    </div>

    <!-- Add Group Modal -->
    <div x-show="showAddGroup" x-cloak class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAddGroup = false"></div>
        <div class="relative w-full max-w-lg bg-white rounded-[3rem] p-8 space-y-8 shadow-2xl animate-slide-up">
            <h3 class="text-2xl font-900 text-slate-800 font-900 tracking-tighter">New Group</h3>
            <input type="text" x-model="groupForm.group_name" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700" placeholder="e.g. Aluminium Section">
            <button @click="saveGroup" class="w-full grad-amber p-5 rounded-2xl text-white font-900 uppercase tracking-widest text-xs shadow-xl active:scale-95 transition-all">Save Group</button>
        </div>
    </div>
</div>

<script>
function settingsApp() {
    return {
        activeTab: 'branches',
        showAddBranch: false,
        showAddType: false,
        showAddGroup: false,
        types: [],
        groups: [],
        branchForm: { code: '', name: '' },
        typeForm: { type_name: '' },
        groupForm: { group_name: '' },

        async fetchTypes() {
            const res = await fetch('{{ route('mobile.settings.product-types') }}');
            const data = await res.json();
            if (data.success) this.types = data.types;
        },
        async fetchGroups() {
            const res = await fetch('{{ route('mobile.settings.product-groups') }}');
            const data = await res.json();
            if (data.success) this.groups = data.groups;
        },
        async saveBranch() {
            const res = await fetch('{{ route('mobile.settings.branch.store') }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: JSON.stringify(this.branchForm)
            });
            const data = await res.json();
            if (data.success) window.location.reload();
            else alert(data.message);
        },
        async saveType() {
            const res = await fetch('{{ route('mobile.settings.product-types.store') }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: JSON.stringify(this.typeForm)
            });
            const data = await res.json();
            if (data.success) { this.showAddType = false; this.fetchTypes(); }
            else alert(data.message);
        },
        async saveGroup() {
            const res = await fetch('{{ route('mobile.settings.product-groups.store') }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: JSON.stringify(this.groupForm)
            });
            const data = await res.json();
            if (data.success) { this.showAddGroup = false; this.fetchGroups(); }
            else alert(data.message);
        },
        async deleteBranch(id) {
            if (!confirm('Delete this mapping?')) return;
            const res = await fetch(`/mobile/settings/branch/${id}`, {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
            });
            if ((await res.json()).success) window.location.reload();
        }
    }
}
</script>
@endsection
