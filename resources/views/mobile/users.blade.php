@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="usersApp()">
    <!-- Header Block -->
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden mb-2">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 font-900 tracking-tighter">User Manager</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Access Control & Roles</p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="showAddUser = true" class="w-10 h-10 grad-indigo rounded-xl flex items-center justify-center text-white shadow-lg shadow-indigo-100 border-2 border-white">
                <i class="fas fa-user-plus text-xs"></i>
            </button>
        </div>
    </div>
</div>

    <!-- Search & Type Filters -->
    <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-6 rounded-[2.5rem] border border-white/50 space-y-4 mb-6">
        <form method="GET" action="{{ route('mobile.users') }}" class="space-y-4">
            <div class="relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search name or username..."
                       class="w-full pl-11 pr-10 py-3.5 bg-white/70 backdrop-blur-xl border border-white/80 rounded-2xl text-sm font-bold text-slate-700 placeholder:text-slate-400 outline-none focus:ring-2 focus:ring-indigo-500 transition-all shadow-sm">
                @if(request('search'))
                <a href="{{ route('mobile.users', request()->except('search')) }}" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times text-sm"></i>
                </a>
                @endif
            </div>

            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-3 mb-2 block">Interface Type</label>
                <div class="flex bg-white/50 backdrop-blur-md p-1.5 rounded-2xl border border-white/50">
                    <a href="{{ route('mobile.users', request()->except('type')) }}"
                       class="flex-1 py-2 text-[10px] font-black rounded-xl transition-all uppercase tracking-wider text-center {{ !request('type') ? 'bg-white text-indigo-600 shadow-md' : 'text-slate-400' }}">
                        All
                    </a>
                    <a href="{{ route('mobile.users', array_merge(request()->except('type'), ['type' => 'desktop'])) }}"
                       class="flex-1 py-2 text-[10px] font-black rounded-xl transition-all uppercase tracking-wider text-center {{ request('type') === 'desktop' ? 'bg-white text-indigo-600 shadow-md' : 'text-slate-400' }}">
                        Desktop
                    </a>
                    <a href="{{ route('mobile.users', array_merge(request()->except('type'), ['type' => 'mobile'])) }}"
                       class="flex-1 py-2 text-[10px] font-black rounded-xl transition-all uppercase tracking-wider text-center {{ request('type') === 'mobile' ? 'bg-white text-indigo-600 shadow-md' : 'text-slate-400' }}">
                        Mobile
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- User List -->
    <div class="space-y-4">
        @foreach($users as $user)
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-6 rounded-[2.5rem] border border-white/80 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-black text-xl {{ $user->role === 'admin' ? 'bg-indigo-100 text-indigo-500' : 'bg-slate-100 text-slate-500' }}">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 font-900 tracking-tight">{{ $user->name }}</h4>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="px-2 py-0.5 bg-white/60 backdrop-blur-md text-[8px] font-black uppercase tracking-widest text-slate-400 rounded-lg">@ {{ $user->username }}</span>
                            <span class="px-2 py-0.5 {{ $user->interface_type === 'mobile' ? 'bg-cyan-50 text-cyan-500' : 'bg-amber-50 text-amber-500' }} text-[8px] font-black uppercase tracking-widest rounded-lg">{{ $user->interface_type }}</span>
                        </div>
                    </div>
                </div>
                <button @click="editUser({{ json_encode($user) }})" class="w-10 h-10 flex items-center justify-center text-indigo-400 bg-indigo-50/50 rounded-xl active:scale-90 transition-all">
                    <i class="fas fa-shield-halved text-xs"></i>
                </button>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Add User Modal -->
    <div x-show="showAddUser" x-cloak class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAddUser = false"></div>
        <div class="relative w-full max-w-lg bg-white rounded-[3rem] p-8 space-y-6 shadow-2xl animate-slide-up overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-900 text-slate-800 font-900 tracking-tighter">New Access</h3>
                <button @click="showAddUser = false" class="w-10 h-10 bg-white/60 backdrop-blur-md rounded-2xl flex items-center justify-center text-slate-400">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-4">
                <input type="text" x-model="form.name" placeholder="Full Name" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700">
                <input type="text" x-model="form.username" placeholder="Username" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700">
                <div class="grid grid-cols-2 gap-4">
                    <select x-model="form.role" class="bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700">
                        <option value="user">Standard User</option>
                        <option value="admin">Administrator</option>
                    </select>
                    <select x-model="form.interface_type" class="bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700">
                        <option value="mobile">Mobile View</option>
                        <option value="desktop">Desktop View</option>
                    </select>
                </div>
                <input type="password" x-model="form.password" placeholder="Password (Min 4 chars)" class="w-full bg-white/60 backdrop-blur-md border-none rounded-2xl py-4 px-6 text-sm font-bold text-slate-700">

                <!-- Branch / Location Access -->
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Location Access</label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($branches as $branch)
                        <label class="flex items-center gap-3 p-3 bg-white/60 backdrop-blur-md rounded-2xl border-2 border-transparent transition-all cursor-pointer"
                               :class="form.branches.includes({{ $branch->id }}) ? 'border-indigo-500 bg-white shadow-sm' : 'border-slate-100'">
                            <input type="checkbox" value="{{ $branch->id }}" x-model="form.branches" class="hidden">
                            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all flex-shrink-0"
                                 :class="form.branches.includes({{ $branch->id }}) ? 'border-indigo-500 bg-indigo-500' : 'border-slate-300'">
                                <i class="fas fa-check text-[7px] text-white"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-700 leading-tight">{{ $branch->name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- Product Type Access -->
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Product Type Access</label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($productTypes as $type)
                        <label class="flex items-center gap-3 p-3 bg-white/60 backdrop-blur-md rounded-2xl border-2 border-transparent transition-all cursor-pointer"
                               :class="form.product_types.includes({{ $type->id }}) ? 'border-indigo-500 bg-white shadow-sm' : 'border-slate-100'">
                            <input type="checkbox" value="{{ $type->id }}" x-model="form.product_types" class="hidden">
                            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all flex-shrink-0"
                                 :class="form.product_types.includes({{ $type->id }}) ? 'border-indigo-500 bg-indigo-500' : 'border-slate-300'">
                                <i class="fas fa-check text-[7px] text-white"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-700 leading-tight">{{ $type->type_name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- RM Type Access Control -->
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">RM Type Access Control</label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($rmTypes as $rm)
                        <label class="flex items-center gap-3 p-3 bg-white/60 backdrop-blur-md rounded-2xl border-2 border-transparent transition-all cursor-pointer"
                               :class="form.rm_types.includes({{ $rm->id }}) ? 'border-teal-500 bg-white shadow-sm' : 'border-slate-100'">
                            <input type="checkbox" value="{{ $rm->id }}" x-model="form.rm_types" class="hidden">
                            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all flex-shrink-0"
                                 :class="form.rm_types.includes({{ $rm->id }}) ? 'border-teal-500 bg-teal-500' : 'border-slate-300'">
                                <i class="fas fa-check text-[7px] text-white"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-700 leading-tight">{{ $rm->value }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <button @click="saveUser" class="w-full grad-indigo p-5 rounded-2xl text-white font-900 uppercase tracking-widest text-xs shadow-xl active:scale-95 transition-all">Create User</button>
        </div>
    </div>

    <!-- Permissions Editor (Modal) -->
    <div x-show="showEdit" x-cloak class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showEdit = false"></div>
        <div class="relative w-full max-w-lg bg-white rounded-[3rem] p-8 space-y-8 shadow-2xl animate-slide-up overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-900 text-slate-800 font-900 tracking-tighter" x-text="currentUser?.name"></h3>
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-indigo-500">Security Configuration</p>
                </div>
                <button @click="showEdit = false" class="w-10 h-10 bg-white/60 backdrop-blur-md rounded-2xl flex items-center justify-center text-slate-400">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="space-y-8">
                <!-- Branch Permissions -->
                <div class="space-y-4">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-3">Location Access</label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($branches as $branch)
                        <label class="flex items-center gap-3 p-4 bg-white/60 backdrop-blur-md rounded-2xl border-2 border-transparent transition-all cursor-pointer" :class="permForm.branches.includes({{ $branch->id }}) ? 'border-indigo-500 bg-white shadow-sm' : 'border-slate-100'">
                            <input type="checkbox" value="{{ $branch->id }}" x-model="permForm.branches" class="hidden">
                            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all flex-shrink-0" :class="permForm.branches.includes({{ $branch->id }}) ? 'border-indigo-500 bg-indigo-500' : 'border-slate-300'">
                                <i class="fas fa-check text-[8px] text-white"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-700">{{ $branch->name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- Product Type Permissions -->
                <div class="space-y-4">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-3">Product Type Access</label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($productTypes as $type)
                        <label class="flex items-center gap-3 p-4 bg-white/60 backdrop-blur-md rounded-2xl border-2 border-transparent transition-all cursor-pointer" :class="permForm.product_types.includes({{ $type->id }}) ? 'border-indigo-500 bg-white shadow-sm' : 'border-slate-100'">
                            <input type="checkbox" value="{{ $type->id }}" x-model="permForm.product_types" class="hidden">
                            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all flex-shrink-0" :class="permForm.product_types.includes({{ $type->id }}) ? 'border-indigo-500 bg-indigo-500' : 'border-slate-300'">
                                <i class="fas fa-check text-[8px] text-white"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-700 leading-tight">{{ $type->type_name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- RM Type Permissions -->
                <div class="space-y-4">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-3">RM Type Access Control</label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($rmTypes as $rm)
                        <label class="flex items-center gap-3 p-4 bg-white/60 backdrop-blur-md rounded-2xl border-2 border-transparent transition-all cursor-pointer" :class="permForm.rm_types.includes({{ $rm->id }}) ? 'border-teal-500 bg-white shadow-sm' : 'border-slate-100'">
                            <input type="checkbox" value="{{ $rm->id }}" x-model="permForm.rm_types" class="hidden">
                            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all flex-shrink-0" :class="permForm.rm_types.includes({{ $rm->id }}) ? 'border-teal-500 bg-teal-500' : 'border-slate-300'">
                                <i class="fas fa-check text-[8px] text-white"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-700 leading-tight">{{ $rm->value }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- Feature Permissions -->
                <div class="space-y-6">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-3">Feature Matrix</label>
                    
                    @foreach($modules as $key => $name)
                    @if(isset($moduleFeatures[$key]))
                    <div class="space-y-3">
                        <div class="text-[9px] font-black text-indigo-400 uppercase tracking-widest ml-3 bg-indigo-50/50 inline-block px-3 py-1 rounded-full">{{ $name }}</div>
                        <div class="space-y-2">
                            @foreach($moduleFeatures[$key] as $fKey => $fName)
                            <label class="flex items-center justify-between p-4 bg-white/60 backdrop-blur-md rounded-2xl active:bg-slate-100 transition-all">
                                <span class="text-xs font-bold text-slate-600">{{ $fName }}</span>
                                <div class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" x-model="permForm.features['{{ $key }}']['{{ $fKey }}']" class="sr-only peer">
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-500"></div>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>

            <button @click="savePermissions" class="w-full grad-indigo p-6 rounded-[2rem] text-white font-900 uppercase tracking-widest text-xs shadow-xl active:scale-95 transition-all">
                Authorize Changes
            </button>
        </div>
    </div>
</div>

<script>
function usersApp() {
    return {
        showAddUser: false,
        showEdit: false,
        currentUser: null,
        form: {
            name: '',
            username: '',
            role: 'user',
            interface_type: 'mobile',
            password: '',
            branches: [],
            product_types: [],
            rm_types: []
        },
        permForm: {
            branches: [],
            product_types: [],
            rm_types: [],
            features: {}
        },
        init() {
            // Pre-fill features structure based on PHP data
            const features = @json($moduleFeatures);
            Object.keys(features).forEach(key => {
                this.permForm.features[key] = {};
                Object.keys(features[key]).forEach(fKey => {
                    this.permForm.features[key][fKey] = false;
                });
            });
        },
        async saveUser() {
            if (!this.form.name || !this.form.username || !this.form.password) {
                alert('Please fill all required fields.');
                return;
            }
            const res = await fetch('{{ route('mobile.users.store') }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: JSON.stringify(this.form)
            });
            const data = await res.json();
            if (data.success) {
                this.showAddUser = false;
                this.form = { name: '', username: '', role: 'user', interface_type: 'mobile', password: '', branches: [], product_types: [], rm_types: [] };
                window.location.reload();
            } else {
                alert(data.message);
            }
        },
        editUser(user) {
            this.currentUser = user;
            this.permForm.branches = user.branches.map(b => b.id);
            this.permForm.product_types = user.product_types ? user.product_types.map(t => t.id) : [];
            this.permForm.rm_types = user.permitted_attributes ? user.permitted_attributes.map(a => a.id) : [];
            
            // Reset features
            Object.keys(this.permForm.features).forEach(key => {
                Object.keys(this.permForm.features[key]).forEach(fKey => {
                    this.permForm.features[key][fKey] = false;
                });
            });

            // Map existing permissions
            user.permissions.forEach(p => {
                if (p.features && typeof p.features === 'object') {
                    Object.keys(p.features).forEach(fKey => {
                        if (this.permForm.features[p.page_key] && this.permForm.features[p.page_key].hasOwnProperty(fKey)) {
                            this.permForm.features[p.page_key][fKey] = !!p.features[fKey];
                        }
                    });
                }
            });
            
            this.showEdit = true;
        },
        async savePermissions() {
            const res = await fetch(`/mobile/users/${this.currentUser.id}/permissions`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: JSON.stringify(this.permForm)
            });
            const data = await res.json();
            if (data.success) {
                this.showEdit = false;
                alert('Privileges updated successfully!');
                window.location.reload();
            }
        }
    }
}
</script>
@endsection
