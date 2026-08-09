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
                <div class="flex items-center gap-2">
                    <button @click="cloneUser({{ json_encode($user) }})" class="w-10 h-10 flex items-center justify-center text-violet-400 bg-violet-50/50 rounded-xl active:scale-90 transition-all" title="Clone User">
                        <i class="fas fa-copy text-xs"></i>
                    </button>
                    <button @click="editUser({{ json_encode($user) }})" class="w-10 h-10 flex items-center justify-center text-indigo-400 bg-indigo-50/50 rounded-xl active:scale-90 transition-all" title="Edit Permissions">
                        <i class="fas fa-shield-halved text-xs"></i>
                    </button>
                </div>
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

    <!-- Permissions Editor (Full Modal) -->
    <div x-show="showEdit" x-cloak class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showEdit = false"></div>
        <div class="relative w-full max-w-lg bg-white rounded-[3rem] p-8 space-y-6 shadow-2xl animate-slide-up overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-900 text-slate-800 font-900 tracking-tighter" x-text="currentUser?.name"></h3>
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-indigo-500">Full Permission Control</p>
                </div>
                <button @click="showEdit = false" class="w-10 h-10 bg-white/60 backdrop-blur-md rounded-2xl flex items-center justify-center text-slate-400">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="space-y-6">
                <!-- Branch Permissions -->
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-3 flex items-center gap-2">
                        <i class="fas fa-map-marker-alt text-indigo-500"></i> Location Access
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($branches as $branch)
                        <label class="flex items-center gap-3 p-3 bg-white/60 backdrop-blur-md rounded-2xl border-2 border-transparent transition-all cursor-pointer" :class="permForm.branches.includes({{ $branch->id }}) ? 'border-indigo-500 bg-white shadow-sm' : 'border-slate-100'">
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
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-3 flex items-center gap-2">
                        <i class="fas fa-box text-indigo-500"></i> Product Type Access
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($productTypes as $type)
                        <label class="flex items-center gap-3 p-3 bg-white/60 backdrop-blur-md rounded-2xl border-2 border-transparent transition-all cursor-pointer" :class="permForm.product_types.includes({{ $type->id }}) ? 'border-indigo-500 bg-white shadow-sm' : 'border-slate-100'">
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
                <div class="space-y-3">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-3 flex items-center gap-2">
                        <i class="fas fa-flask text-teal-500"></i> RM Type Access Control
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($rmTypes as $rm)
                        <label class="flex items-center gap-3 p-3 bg-white/60 backdrop-blur-md rounded-2xl border-2 border-transparent transition-all cursor-pointer" :class="permForm.rm_types.includes({{ $rm->id }}) ? 'border-teal-500 bg-white shadow-sm' : 'border-slate-100'">
                            <input type="checkbox" value="{{ $rm->id }}" x-model="permForm.rm_types" class="hidden">
                            <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all flex-shrink-0" :class="permForm.rm_types.includes({{ $rm->id }}) ? 'border-teal-500 bg-teal-500' : 'border-slate-300'">
                                <i class="fas fa-check text-[8px] text-white"></i>
                            </div>
                            <span class="text-[10px] font-bold text-slate-700 leading-tight">{{ $rm->value }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- Full Permission Matrix -->
                <div class="space-y-4" x-show="currentUser?.role !== 'admin'">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-3 flex items-center gap-2">
                        <i class="fas fa-key text-indigo-500"></i> Permission Matrix
                    </label>

                    <!-- Desktop Modules -->
                    <template x-if="currentUser?.interface_type === 'desktop'">
                        <div class="space-y-3">
                            <div class="text-[8px] font-black text-slate-500 uppercase tracking-widest ml-3 bg-slate-100 inline-block px-3 py-1 rounded-full">Desktop Modules</div>
                            @foreach($modules as $key => $name)
                            @if(!str_starts_with($key, 'mobile_'))
                            <div class="bg-white/60 backdrop-blur-md rounded-2xl border border-slate-100 overflow-hidden">
                                <!-- Module Header with checkboxes -->
                                <div class="p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <div class="text-xs font-bold text-slate-700">{{ $name }}</div>
                                            <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">{{ $key }}</div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-7 gap-2">
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].view" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-slate-400 uppercase">View</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].create" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-slate-400 uppercase">Create</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].edit" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-slate-400 uppercase">Edit</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].delete" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-slate-400 uppercase">Del</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].print" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-indigo-400 uppercase">Prn</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].excel" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-green-500 uppercase">Xls</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].pdf" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-red-400 uppercase">Pdf</span>
                                        </label>
                                    </div>
                                </div>
                                @if(isset($moduleFeatures[$key]))
                                <!-- Feature Toggles -->
                                <div class="px-4 pb-3 pt-2 border-t border-slate-100 flex flex-wrap gap-3">
                                    @foreach($moduleFeatures[$key] as $fKey => $fLabel)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <div class="relative inline-flex items-center">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].features.{{ $fKey }}" class="sr-only peer">
                                            <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-500"></div>
                                        </div>
                                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-tight">{{ $fLabel }}</span>
                                    </label>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </template>

                    <!-- Mobile Modules -->
                    <template x-if="currentUser?.interface_type === 'mobile'">
                        <div class="space-y-3">
                            <div class="text-[8px] font-black text-indigo-500 uppercase tracking-widest ml-3 bg-indigo-50 inline-block px-3 py-1 rounded-full">Mobile PWA Modules</div>
                            @foreach($modules as $key => $name)
                            @if(str_starts_with($key, 'mobile_'))
                            <div class="bg-white/60 backdrop-blur-md rounded-2xl border border-slate-100 overflow-hidden">
                                <!-- Module Header with checkboxes -->
                                <div class="p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <div class="text-xs font-bold text-slate-700">{{ $name }}</div>
                                            <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">{{ $key }}</div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-7 gap-2">
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].view" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-slate-400 uppercase">View</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].create" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-slate-400 uppercase">Create</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].edit" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-slate-400 uppercase">Edit</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].delete" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-slate-400 uppercase">Del</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].print" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-indigo-400 uppercase">Prn</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].excel" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-green-500 uppercase">Xls</span>
                                        </label>
                                        <label class="flex flex-col items-center gap-1 cursor-pointer">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].pdf" class="w-4 h-4 rounded border-2 border-indigo-200 text-indigo-600 focus:ring-0">
                                            <span class="text-[7px] font-black text-red-400 uppercase">Pdf</span>
                                        </label>
                                    </div>
                                </div>
                                @if(isset($moduleFeatures[$key]))
                                <!-- Feature Toggles -->
                                <div class="px-4 pb-3 pt-2 border-t border-slate-100 flex flex-wrap gap-3">
                                    @foreach($moduleFeatures[$key] as $fKey => $fLabel)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <div class="relative inline-flex items-center">
                                            <input type="checkbox" x-model="permForm.permissions['{{ $key }}'].features.{{ $fKey }}" class="sr-only peer">
                                            <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-teal-500"></div>
                                        </div>
                                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-tight">{{ $fLabel }}</span>
                                    </label>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </template>

                    <!-- Admin Badge -->
                    <div x-show="currentUser?.role === 'admin'" class="p-6 text-center bg-amber-50 rounded-2xl border-2 border-dashed border-amber-200">
                        <div class="bg-amber-400 text-white w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-crown text-lg"></i>
                        </div>
                        <h4 class="text-sm font-black text-amber-700 uppercase tracking-tight">Admin — Full Access</h4>
                        <p class="text-[10px] font-bold text-amber-600/80 mt-1">Admins have unrestricted access to all modules.</p>
                    </div>
                </div>
            </div>

            <button @click="savePermissions" class="w-full grad-indigo p-6 rounded-[2rem] text-white font-900 uppercase tracking-widest text-xs shadow-xl active:scale-95 transition-all">
                <i class="fas fa-save mr-2"></i> Save All Permissions
            </button>
        </div>
    </div>

    <!-- Clone User Modal -->
    <div x-show="showClone" x-cloak class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showClone = false"></div>
        <div class="relative w-full max-w-lg bg-white rounded-[3rem] p-8 space-y-6 shadow-2xl animate-slide-up overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-900 text-slate-800 font-900 tracking-tighter">Clone User</h3>
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-violet-500">Duplicate with all permissions</p>
                </div>
                <button @click="showClone = false" class="w-10 h-10 bg-white/60 backdrop-blur-md rounded-2xl flex items-center justify-center text-slate-400">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Source User Badge -->
            <div class="bg-violet-50 border border-violet-200 rounded-2xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-violet-500 flex items-center justify-center text-white font-black text-sm flex-shrink-0" x-text="cloneSource?.name?.charAt(0) || '?'"></div>
                <div>
                    <div class="text-[8px] font-black text-violet-400 uppercase tracking-widest">Cloning From</div>
                    <div class="text-sm font-black text-violet-800" x-text="cloneSource?.name"></div>
                </div>
                <span class="ml-auto bg-violet-200 text-violet-700 px-3 py-1 rounded-lg text-[8px] font-black uppercase" x-text="cloneSource?.interface_type"></span>
            </div>

            <div class="space-y-4">
                <input type="text" x-model="cloneForm.name" placeholder="New User Name" class="w-full bg-white/60 backdrop-blur-md border-2 border-slate-100 rounded-2xl py-4 px-6 text-sm font-bold text-slate-700 focus:border-violet-500 outline-none transition">
                <input type="text" x-model="cloneForm.username" placeholder="New Username" class="w-full bg-white/60 backdrop-blur-md border-2 border-slate-100 rounded-2xl py-4 px-6 text-sm font-bold text-slate-700 focus:border-violet-500 outline-none transition">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Interface Type</label>
                    <div class="flex bg-white/50 p-1.5 rounded-2xl border border-slate-100">
                        <button type="button" @click="cloneForm.interface_type = 'desktop'" :class="cloneForm.interface_type === 'desktop' ? 'bg-violet-600 text-white shadow-lg' : 'text-slate-400'" class="flex-1 py-2.5 text-[10px] font-black rounded-xl transition-all uppercase tracking-wider text-center">Desktop</button>
                        <button type="button" @click="cloneForm.interface_type = 'mobile'" :class="cloneForm.interface_type === 'mobile' ? 'bg-violet-600 text-white shadow-lg' : 'text-slate-400'" class="flex-1 py-2.5 text-[10px] font-black rounded-xl transition-all uppercase tracking-wider text-center">Mobile</button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <input type="password" x-model="cloneForm.password" placeholder="Password" class="bg-white/60 backdrop-blur-md border-2 border-slate-100 rounded-2xl py-4 px-6 text-sm font-bold text-slate-700 focus:border-violet-500 outline-none transition">
                    <input type="password" x-model="cloneForm.password_confirmation" placeholder="Confirm" class="bg-white/60 backdrop-blur-md border-2 border-slate-100 rounded-2xl py-4 px-6 text-sm font-bold text-slate-700 focus:border-violet-500 outline-none transition">
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 flex items-start gap-2">
                    <i class="fas fa-info-circle text-amber-500 mt-0.5"></i>
                    <p class="text-[9px] font-bold text-amber-700 leading-relaxed">All permissions, branch access, product type access, and RM type access will be copied.</p>
                </div>
            </div>
            <button @click="submitClone" class="w-full bg-gradient-to-r from-violet-600 to-purple-600 p-5 rounded-2xl text-white font-900 uppercase tracking-widest text-xs shadow-xl active:scale-95 transition-all flex items-center justify-center gap-2">
                <i class="fas fa-copy"></i> Clone User
            </button>
        </div>
    </div>
</div>

<script>
function usersApp() {
    const allModules = @json(array_keys($modules));
    const allModuleFeatures = @json($moduleFeatures);

    function buildEmptyPermissions() {
        const perms = {};
        allModules.forEach(m => {
            perms[m] = {
                view: false, create: false, edit: false, delete: false,
                print: false, excel: false, pdf: false,
                features: {}
            };
            if (allModuleFeatures[m]) {
                Object.keys(allModuleFeatures[m]).forEach(fk => {
                    perms[m].features[fk] = false;
                });
            }
        });
        return perms;
    }

    return {
        showAddUser: false,
        showEdit: false,
        showClone: false,
        currentUser: null,
        cloneSource: null,
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
            permissions: buildEmptyPermissions()
        },
        cloneForm: {
            name: '',
            username: '',
            interface_type: 'desktop',
            password: '',
            password_confirmation: ''
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

            // Build fresh permissions and fill from user data
            this.permForm.permissions = buildEmptyPermissions();
            user.permissions.forEach(p => {
                if (this.permForm.permissions[p.page_key]) {
                    this.permForm.permissions[p.page_key].view = !!p.can_view;
                    this.permForm.permissions[p.page_key].create = !!p.can_create;
                    this.permForm.permissions[p.page_key].edit = !!p.can_edit;
                    this.permForm.permissions[p.page_key].delete = !!p.can_delete;
                    this.permForm.permissions[p.page_key].print = !!p.can_print;
                    this.permForm.permissions[p.page_key].excel = !!p.can_export_excel;
                    this.permForm.permissions[p.page_key].pdf = !!p.can_export_pdf;

                    if (p.features && typeof p.features === 'object') {
                        Object.keys(p.features).forEach(fk => {
                            if (this.permForm.permissions[p.page_key].features.hasOwnProperty(fk)) {
                                this.permForm.permissions[p.page_key].features[fk] = !!p.features[fk];
                            }
                        });
                    }
                }
            });

            this.showEdit = true;
        },

        cloneUser(user) {
            this.cloneSource = user;
            this.cloneForm = {
                name: user.name + ' (Copy)',
                username: user.username + '_copy',
                interface_type: user.interface_type || 'desktop',
                password: '',
                password_confirmation: ''
            };
            this.showClone = true;
        },

        async submitClone() {
            if (!this.cloneForm.name.trim() || !this.cloneForm.username.trim()) {
                alert('Please enter name and username.');
                return;
            }
            if (!this.cloneForm.password) {
                alert('Please set a password.');
                return;
            }
            if (this.cloneForm.password !== this.cloneForm.password_confirmation) {
                alert('Passwords do not match.');
                return;
            }

            // Submit via hidden form to use the desktop clone route
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/users/${this.cloneSource.id}/clone`;
            form.style.display = 'none';

            const csrf = document.createElement('input');
            csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            ['name', 'username', 'interface_type', 'password', 'password_confirmation'].forEach(field => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = field; input.value = this.cloneForm[field];
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        },

        async savePermissions() {
            // Build the payload matching what the backend expects
            const payload = {
                branches: this.permForm.branches,
                product_types: this.permForm.product_types,
                rm_types: this.permForm.rm_types,
                permissions: {}
            };

            // Only send permissions matching the user's interface type
            const isMobile = this.currentUser.interface_type === 'mobile';
            Object.keys(this.permForm.permissions).forEach(pageKey => {
                const isMobileKey = pageKey.startsWith('mobile_');
                if (isMobile && !isMobileKey) return;
                if (!isMobile && isMobileKey) return;

                const perm = this.permForm.permissions[pageKey];
                // Check if any permission is enabled
                const hasAny = perm.view || perm.create || perm.edit || perm.delete || perm.print || perm.excel || perm.pdf ||
                    Object.values(perm.features || {}).some(v => v);

                if (hasAny) {
                    payload.permissions[pageKey] = {
                        view: perm.view,
                        create: perm.create,
                        edit: perm.edit,
                        delete: perm.delete,
                        print: perm.print,
                        excel: perm.excel,
                        pdf: perm.pdf,
                        features: perm.features || {}
                    };
                }
            });

            const res = await fetch(`/mobile/users/${this.currentUser.id}/permissions`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                this.showEdit = false;
                alert('Permissions updated successfully!');
                window.location.reload();
            } else {
                alert(data.message || 'Error saving permissions.');
            }
        }
    }
}
</script>
@endsection
