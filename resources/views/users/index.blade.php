@extends('layouts.app')

@section('header', 'User Management')

@section('content')
<div x-data="userManager()" class="min-h-screen bg-[#f8fafc] py-8">
    <div class="max-w-[95%] mx-auto">
        <!-- Dashboard Header -->
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100/50 p-8 mb-8 border border-indigo-50/50 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-50/30 rounded-full -mr-32 -mt-32 blur-3xl"></div>
            
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="bg-indigo-600 text-white p-3 rounded-2xl shadow-lg shadow-indigo-200">
                            <i class="fas {{ $currentType === 'mobile' ? 'fa-mobile-alt' : ($currentType === 'desktop' ? 'fa-desktop' : 'fa-users-cog') }} text-xl"></i>
                        </div>
                    </div>
                    <h1 class="text-3xl font-black text-gray-900 italic tracking-tighter uppercase">
                        {{ $currentType === 'mobile' ? 'Mobile User Manager' : ($currentType === 'desktop' ? 'Desktop User Manager' : 'User Manager') }}
                    </h1>
                    <p class="text-gray-400 font-bold text-[10px] uppercase tracking-widest flex items-center gap-2">
                        Manage roles, module access, and granular permissions for {{ $currentType ?? 'all' }} users
                    </p>
                </div>

                <div class="flex gap-4">
                    <button @click.stop="openModal()" class="bg-indigo-600 text-white px-8 py-4 rounded-2xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-xl shadow-indigo-100 flex items-center gap-3 uppercase">
                        <i class="fas fa-user-plus"></i> Create New User
                    </button>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100/50 overflow-hidden border border-indigo-50/50">
            <div class="p-8 border-b border-gray-50 bg-gray-50/30 flex justify-between items-center">
                <h3 class="text-gray-700 font-black flex items-center italic uppercase tracking-tighter">
                    <i class="fas fa-list mr-3 text-indigo-500"></i> Registered Users
                </h3>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest">User Details</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest">Username</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest">Role</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Permissions</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($users as $user)
                        <tr class="hover:bg-indigo-50/20 transition-all group">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-black italic text-sm">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-800 text-sm italic">{{ $user->name }}</div>
                                        <div class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">Joined {{ $user->created_at->format('d M, Y') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <span class="bg-gray-100 text-gray-600 font-black px-3 py-1 rounded-lg text-[10px] uppercase">
                                    {{ $user->username }}
                                </span>
                            </td>
                            <td class="px-8 py-5">
                                <span class="{{ $user->role === 'admin' ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600' }} font-black px-3 py-1 rounded-lg text-[10px] uppercase tracking-tighter italic">
                                    {{ $user->role }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                @if($user->role === 'admin')
                                    <span class="text-[10px] font-black text-indigo-600 uppercase italic tracking-widest">Full Access Control</span>
                                @else
                                    <div class="flex justify-center gap-1">
                                        <span class="text-[9px] font-black text-gray-400 uppercase">{{ $user->permissions->count() }} Modules Enabled</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-8 py-5 text-right">
                                <div class="flex justify-end gap-2">
                                    <button @click.stop="editUser({{ $user->id }})" title="Edit Permissions" class="bg-blue-100 text-blue-600 p-2.5 rounded-xl hover:bg-blue-600 hover:text-white transition shadow-sm"><i class="fas fa-shield-halved text-xs"></i></button>
                                    <button @click.stop="cloneUser({{ $user->id }})" title="Clone User" class="bg-violet-100 text-violet-600 p-2.5 rounded-xl hover:bg-violet-600 hover:text-white transition shadow-sm"><i class="fas fa-copy text-xs"></i></button>
                                    @if($user->id !== auth()->id())
                                    <button @click="deleteUser({{ $user->id }})" title="Delete User" class="bg-red-100 text-red-600 p-2.5 rounded-xl hover:bg-red-600 hover:text-white transition shadow-sm"><i class="fas fa-trash-alt text-xs"></i></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <template x-if="showModal">
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            
            <div class="bg-white w-full max-w-5xl h-[90vh] rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col"
                 @click.outside="closeModal(false)">
                
                <!-- Modal Header -->
                <div class="bg-indigo-600 p-8 text-white relative">
                    <div class="flex items-center gap-4">
                        <div class="bg-white/20 p-3 rounded-2xl">
                            <i class="fas fa-user-shield text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black italic tracking-tighter uppercase" x-text="isEditing ? 'Edit User Permissions' : 'Create New User Account'"></h2>
                            <p class="text-indigo-100 font-bold text-[10px] uppercase tracking-widest mt-1">Configure credentials and granular module access</p>
                        </div>
                    </div>
                    <button @click="closeModal(true)" class="absolute top-8 right-8 text-white/50 hover:text-white transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-10 custom-scrollbar">
                    <form id="userForm" @submit.prevent="submitUser()">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10 pb-10 border-b border-gray-100">
                            <!-- Left Column: User Details -->
                            <div class="space-y-6">
                                <div class="space-y-2">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Full Name</label>
                                    <input type="text" x-model="userData.name" class="w-full bg-gray-50 border-2 border-gray-100 rounded-2xl px-6 py-4 font-bold text-gray-900 focus:border-indigo-500 focus:ring-0 transition" placeholder="e.g. Rahul Sharma">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Username</label>
                                    <input type="text" x-model="userData.username" class="w-full bg-gray-50 border-2 border-gray-100 rounded-2xl px-6 py-4 font-bold text-gray-900 focus:border-indigo-500 focus:ring-0 transition" placeholder="rahul.admin">
                                </div>
                            </div>
                            <!-- Right Column: Security -->
                            <div class="space-y-6">
                                <div class="space-y-2">
                                    <select x-model="userData.role" class="w-full bg-gray-50 border-2 border-gray-100 rounded-2xl px-6 py-4 font-black italic text-gray-900 focus:border-indigo-500 focus:ring-0 transition uppercase tracking-tighter">
                                        <option value="user">Standard User</option>
                                        <option value="admin">System Administrator</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Primary Interface</label>
                                    <div class="flex gap-4">
                                        <button type="button" @click="userData.interface_type = 'desktop'" :class="userData.interface_type === 'desktop' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-gray-100 text-gray-400'" class="flex-1 py-3 px-4 rounded-xl font-black italic text-xs uppercase transition tracking-tight">Desktop</button>
                                        <button type="button" @click="userData.interface_type = 'mobile'" :class="userData.interface_type === 'mobile' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-gray-100 text-gray-400'" class="flex-1 py-3 px-4 rounded-xl font-black italic text-xs uppercase transition tracking-tight">Mobile PWA</button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1" x-text="isEditing ? 'New Password (Optional)' : 'Password'"></label>
                                        <input type="password" x-model="userData.password" class="w-full bg-gray-50 border-2 border-gray-100 rounded-2xl px-6 py-4 font-bold text-gray-900 focus:border-indigo-500 focus:ring-0 transition">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Confirm</label>
                                        <input type="password" x-model="userData.password_confirmation" class="w-full bg-gray-50 border-2 border-gray-100 rounded-2xl px-6 py-4 font-bold text-gray-900 focus:border-indigo-500 focus:ring-0 transition">
                                    </div>
                                </div>
                                <div class="md:col-span-2 space-y-4 bg-gray-50/50 p-6 rounded-3xl border border-gray-100">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1 flex items-center gap-2">
                                        <i class="fas fa-map-marker-alt text-indigo-500"></i> Branch Access Control
                                    </label>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        @foreach($branches as $branch)
                                        <label class="flex items-center gap-3 p-3 bg-white rounded-xl border border-gray-100 cursor-pointer hover:border-indigo-300 transition group">
                                            <input type="checkbox" value="{{ $branch->id }}" x-model="userData.branches" class="w-4 h-4 rounded border-2 border-gray-200 text-indigo-600 focus:ring-0 transition">
                                            <div>
                                                <div class="text-[10px] font-black italic uppercase leading-tight group-hover:text-indigo-600 transition">{{ $branch->name }}</div>
                                                <div class="text-[8px] font-bold text-gray-400 uppercase tracking-widest">{{ $branch->code }}</div>
                                            </div>
                                        </label>
                                        @endforeach
                                    </div>
                                    <p class="text-[8px] font-bold text-gray-400 uppercase italic">Admins automatically have access to all branches regardless of selection.</p>
                                </div>

                                <div class="md:col-span-2 space-y-4 bg-gray-50/50 p-6 rounded-3xl border border-gray-100">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1 flex items-center gap-2">
                                        <i class="fas fa-box text-indigo-500"></i> Product Type Access
                                    </label>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                        @foreach($productTypes as $type)
                                        <label class="flex items-center gap-3 p-3 bg-white rounded-xl border border-gray-100 cursor-pointer hover:border-indigo-300 transition group">
                                            <input type="checkbox" value="{{ $type->id }}" x-model="userData.product_types" class="w-4 h-4 rounded border-2 border-gray-200 text-indigo-600 focus:ring-0 transition">
                                            <div class="text-[10px] font-black italic uppercase leading-tight group-hover:text-indigo-600 transition">{{ $type->type_name }}</div>
                                        </label>
                                        @endforeach
                                    </div>
                                    <p class="text-[8px] font-bold text-gray-400 uppercase italic">Restricts products visible to this user by their type.</p>
                                </div>

                                <div class="md:col-span-2 space-y-4 bg-gray-50/50 p-6 rounded-3xl border border-gray-100">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1 flex items-center gap-2">
                                        <i class="fas fa-flask text-teal-500"></i> RM Type Access control
                                    </label>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        @foreach($rmTypes as $rm)
                                        <label class="flex items-center gap-3 p-3 bg-white rounded-xl border border-gray-100 cursor-pointer hover:border-indigo-300 transition group">
                                            <input type="checkbox" value="{{ $rm->id }}" x-model="userData.rm_types" class="w-4 h-4 rounded border-2 border-gray-200 text-teal-600 focus:ring-0 transition">
                                            <div class="text-[10px] font-black italic uppercase leading-tight group-hover:text-teal-600 transition">{{ $rm->value }}</div>
                                        </label>
                                        @endforeach
                                    </div>
                                    <p class="text-[8px] font-bold text-gray-400 uppercase italic">Restricts RM products visible to this user by their attribute type.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Permission Matrix -->
                        <div x-show="userData.role === 'user'" x-transition>
                            <h3 class="text-lg font-black italic text-gray-800 uppercase tracking-tighter mb-6 flex items-center gap-3">
                                <i class="fas fa-key text-indigo-500"></i> Permission Matrix
                            </h3>

                            <div class="bg-gray-50/50 rounded-3xl p-2 border border-indigo-50">
                                <table class="w-full">
                                    <thead>
                                        <tr>
                                            <th class="px-8 py-5 text-[10px] font-black text-gray-400 uppercase text-left">Module Name</th>
                                            <th class="px-2 py-5 text-[10px] font-black text-gray-400 uppercase text-center">View</th>
                                            <th class="px-2 py-5 text-[10px] font-black text-gray-400 uppercase text-center">Create</th>
                                            <th class="px-2 py-5 text-[10px] font-black text-gray-400 uppercase text-center">Edit</th>
                                            <th class="px-2 py-5 text-[10px] font-black text-gray-400 uppercase text-center">Del</th>
                                            <th class="px-2 py-5 text-[10px] font-black text-gray-400 uppercase text-center text-indigo-500">Prn</th>
                                            <th class="px-2 py-5 text-[10px] font-black text-gray-400 uppercase text-center text-green-500">Xls</th>
                                            <th class="px-2 py-5 text-[10px] font-black text-gray-400 uppercase text-center text-red-500">Pdf</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white">
                                        <!-- Desktop Modules Header -->
                                        <tr x-show="userData.interface_type === 'desktop'">
                                            <td colspan="8" class="bg-gray-100/50 px-8 py-2 text-[9px] font-black text-gray-500 uppercase tracking-widest italic">Desktop Interface Modules</td>
                                        </tr>
                                        @foreach($modules as $key => $name)
                                        @if(!str_starts_with($key, 'mobile_'))
                                        <tr x-show="userData.interface_type === 'desktop'" class="hover:bg-white transition-colors group">
                                            <td class="px-8 py-4">
                                                <div class="font-bold text-gray-700 italic text-sm">{{ $name }}</div>
                                                <div class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">{{ $key }}</div>
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.view" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all checked:animate-bounce-short">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.create" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.edit" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.delete" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.print" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.excel" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.pdf" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                        </tr>
                                        @if(isset($moduleFeatures[$key]))
                                        <tr x-show="userData.interface_type === 'desktop'" class="bg-gray-50/20">
                                            <td colspan="8" class="px-8 py-2 border-t border-gray-100">
                                                <div class="flex flex-wrap gap-4">
                                                    @foreach($moduleFeatures[$key] as $fKey => $fLabel)
                                                    <label class="flex items-center gap-2 cursor-pointer group">
                                                        <div class="relative inline-flex items-center cursor-pointer">
                                                            <input type="checkbox" x-model="userData.permissions.{{ $key }}.features.{{ $fKey }}" class="sr-only peer">
                                                            <div class="w-7 h-4 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-600"></div>
                                                        </div>
                                                        <span class="text-[9px] font-black italic uppercase text-gray-400 group-hover:text-indigo-600 transition tracking-tighter">{{ $fLabel }}</span>
                                                    </label>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
                                        @endif
                                        @endforeach

                                        <!-- Mobile Modules Header -->
                                        <tr x-show="userData.interface_type === 'mobile'">
                                            <td colspan="8" class="bg-indigo-50/50 px-8 py-2 text-[9px] font-black text-indigo-500 uppercase tracking-widest italic">Mobile PWA Modules</td>
                                        </tr>
                                        @foreach($modules as $key => $name)
                                        @if(str_starts_with($key, 'mobile_'))
                                        <tr x-show="userData.interface_type === 'mobile'" class="hover:bg-white transition-colors group">
                                            <td class="px-8 py-4">
                                                <div class="font-bold text-gray-700 italic text-sm">{{ $name }}</div>
                                                <div class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">{{ $key }}</div>
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.view" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all checked:animate-bounce-short">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.create" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.edit" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.delete" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.print" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.excel" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" x-model="userData.permissions.{{ $key }}.pdf" class="w-4 h-4 rounded border-2 border-indigo-100 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                                            </td>
                                        </tr>
                                        @if(isset($moduleFeatures[$key]))
                                        <tr x-show="userData.interface_type === 'mobile'" class="bg-indigo-50/10">
                                            <td colspan="8" class="px-8 py-2 border-t border-indigo-100/50">
                                                <div class="flex flex-wrap gap-4">
                                                    @foreach($moduleFeatures[$key] as $fKey => $fLabel)
                                                    <label class="flex items-center gap-2 cursor-pointer group">
                                                        <div class="relative inline-flex items-center cursor-pointer">
                                                            <input type="checkbox" x-model="userData.permissions.{{ $key }}.features.{{ $fKey }}" class="sr-only peer">
                                                            <div class="w-7 h-4 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-teal-500"></div>
                                                        </div>
                                                        <span class="text-[9px] font-black italic uppercase text-gray-400 group-hover:text-teal-600 transition tracking-tighter">{{ $fLabel }}</span>
                                                    </label>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
                                        @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div x-show="userData.role === 'admin'" class="p-12 text-center bg-amber-50 rounded-[2.5rem] border-2 border-dashed border-amber-200" x-transition>
                            <div class="bg-amber-400 text-white w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl shadow-amber-200">
                                <i class="fas fa-crown text-2xl"></i>
                            </div>
                            <h4 class="text-xl font-black italic text-amber-700 uppercase tracking-tighter">Administrator Level Access</h4>
                            <p class="text-sm font-bold text-amber-600/80 mt-2">Admins automatically have full View, Create, Update, and Delete rights across all system modules.</p>
                        </div>
                    </form>
                </div>

                <!-- Modal Footer -->
                <div class="p-8 border-t bg-gray-50 flex items-center justify-between gap-4">
                    <button @click="closeModal(true)" class="px-8 py-4 bg-white border-2 border-gray-100 text-gray-500 rounded-2xl font-black italic tracking-tighter hover:bg-gray-200 transition uppercase">
                        Cancel
                    </button>
                    <button @click="submitUser()" class="px-12 py-4 bg-indigo-600 text-white rounded-2xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-xl shadow-indigo-100 uppercase flex items-center gap-3">
                        <i class="fas fa-save"></i>
                        <span x-text="isEditing ? 'Save Changes' : 'Create Account'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- Hidden Submit Form -->
    <form id="userSubmitForm" action="" method="POST" style="display: none;">
        @csrf
        <div id="method_field"></div>
        <input type="hidden" name="name" id="form_name">
        <input type="hidden" name="username" id="form_username">
        <input type="hidden" name="role" id="form_role">
        <input type="hidden" name="interface_type" id="form_interface_type">
        <input type="hidden" name="password" id="form_password">
        <input type="hidden" name="password_confirmation" id="form_password_confirmation">
        <div id="form_permissions"></div>
        <div id="form_branches"></div>
        <div id="form_product_types"></div>
        <div id="form_rm_types"></div>
    </form>

    <form id="deleteUserForm" action="" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    <!-- Clone User Modal -->
    <template x-if="showCloneModal">
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            
            <div class="bg-white w-full max-w-lg rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col"
                 @click.outside="showCloneModal = false">
                
                <!-- Clone Modal Header -->
                <div class="bg-gradient-to-r from-violet-600 to-purple-600 p-8 text-white relative">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="bg-white/20 p-3 rounded-2xl">
                            <i class="fas fa-copy text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black italic tracking-tighter uppercase">Clone User</h2>
                            <p class="text-violet-100 font-bold text-[10px] uppercase tracking-widest mt-1">Duplicate user with all permissions</p>
                        </div>
                    </div>
                    <button @click="showCloneModal = false" class="absolute top-8 right-8 text-white/50 hover:text-white transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                <!-- Clone Modal Body -->
                <div class="p-8 space-y-5">
                    <div class="bg-violet-50 border border-violet-200 rounded-2xl p-4 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center text-white font-black italic text-sm flex-shrink-0" x-text="cloneSourceName.charAt(0)"></div>
                        <div>
                            <div class="text-[9px] font-black text-violet-400 uppercase tracking-widest">Cloning From</div>
                            <div class="text-sm font-black text-violet-800 italic" x-text="cloneSourceName"></div>
                        </div>
                        <div class="ml-auto">
                            <span class="bg-violet-200 text-violet-700 px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest" x-text="cloneSourceType"></span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">New User Name</label>
                        <input type="text" x-model="cloneData.name" class="w-full bg-gray-50 border-2 border-gray-100 rounded-2xl px-6 py-3.5 font-bold text-gray-900 focus:border-violet-500 focus:ring-0 transition" placeholder="e.g. Rahul Sharma">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">New Username</label>
                        <input type="text" x-model="cloneData.username" class="w-full bg-gray-50 border-2 border-gray-100 rounded-2xl px-6 py-3.5 font-bold text-gray-900 focus:border-violet-500 focus:ring-0 transition" placeholder="e.g. rahul.user">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Interface Type</label>
                        <div class="flex gap-4">
                            <button type="button" @click="cloneData.interface_type = 'desktop'" :class="cloneData.interface_type === 'desktop' ? 'bg-violet-600 text-white shadow-lg' : 'bg-gray-100 text-gray-400'" class="flex-1 py-3 px-4 rounded-xl font-black italic text-xs uppercase transition tracking-tight">Desktop</button>
                            <button type="button" @click="cloneData.interface_type = 'mobile'" :class="cloneData.interface_type === 'mobile' ? 'bg-violet-600 text-white shadow-lg' : 'bg-gray-100 text-gray-400'" class="flex-1 py-3 px-4 rounded-xl font-black italic text-xs uppercase transition tracking-tight">Mobile PWA</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Password</label>
                            <input type="password" x-model="cloneData.password" class="w-full bg-gray-50 border-2 border-gray-100 rounded-2xl px-6 py-3.5 font-bold text-gray-900 focus:border-violet-500 focus:ring-0 transition" placeholder="Set password">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Confirm</label>
                            <input type="password" x-model="cloneData.password_confirmation" class="w-full bg-gray-50 border-2 border-gray-100 rounded-2xl px-6 py-3.5 font-bold text-gray-900 focus:border-violet-500 focus:ring-0 transition" placeholder="Confirm">
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 flex items-start gap-2">
                        <i class="fas fa-info-circle text-amber-500 mt-0.5"></i>
                        <p class="text-[10px] font-bold text-amber-700 leading-relaxed">All permissions, branch access, product type access, and RM type access will be copied from the source user.</p>
                    </div>
                </div>

                <!-- Clone Modal Footer -->
                <div class="p-8 border-t bg-gray-50 flex items-center justify-between gap-4">
                    <button @click="showCloneModal = false" class="px-8 py-3.5 bg-white border-2 border-gray-100 text-gray-500 rounded-2xl font-black italic tracking-tighter hover:bg-gray-200 transition uppercase text-sm">
                        Cancel
                    </button>
                    <button @click="submitClone()" class="px-10 py-3.5 bg-gradient-to-r from-violet-600 to-purple-600 text-white rounded-2xl font-black italic tracking-tighter hover:from-violet-700 hover:to-purple-700 transition shadow-xl shadow-violet-100 uppercase flex items-center gap-3 text-sm">
                        <i class="fas fa-copy"></i>
                        Clone User
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- Hidden Clone Submit Form -->
    <form id="cloneUserForm" action="" method="POST" style="display: none;">
        @csrf
        <input type="hidden" name="name" id="clone_form_name">
        <input type="hidden" name="username" id="clone_form_username">
        <input type="hidden" name="interface_type" id="clone_form_interface_type">
        <input type="hidden" name="password" id="clone_form_password">
        <input type="hidden" name="password_confirmation" id="clone_form_password_confirmation">
    </form>
</div>

<script>
function userManager() {
    return {
        showModal: false,
        isEditing: false,
        editId: null,
        showCloneModal: false,
        cloneSourceId: null,
        cloneSourceName: '',
        cloneSourceType: '',
        cloneData: {
            name: '',
            username: '',
            interface_type: 'desktop',
            password: '',
            password_confirmation: ''
        },
        userData: {
            name: '',
            username: '',
            role: 'user',
            interface_type: 'desktop',
            password: '',
            password_confirmation: '',
            permissions: {},
            branches: [],
            product_types: [],
            rm_types: []
        },
        modules: @json(array_keys($modules)),

        init() {
            this.resetUserData();
        },

        resetUserData() {
            this.userData = {
                name: '',
                username: '',
                role: 'user',
                interface_type: '{{ $currentType ?? "desktop" }}',
                password: '',
                password_confirmation: '',
                permissions: {},
                branches: [],
                product_types: [],
                rm_types: []
            };
            this.modules.forEach(m => {
                this.userData.permissions[m] = { 
                    view: false, create: false, edit: false, delete: false, 
                    print: false, excel: false, pdf: false,
                    features: {} 
                };
            });
        },

        openModal() {
            this.isEditing = false;
            this.editId = null;
            this.resetUserData();
            this.showModal = true;
        },

        closeModal(force = false) {
            if (force || confirm('Discard changes and close?')) {
                this.showModal = false;
            }
        },

        cloneUser(id) {
            const user = @json($users).find(u => u.id == id);
            if (user) {
                this.cloneSourceId = user.id;
                this.cloneSourceName = user.name;
                this.cloneSourceType = user.interface_type || 'desktop';
                this.cloneData = {
                    name: user.name + ' (Copy)',
                    username: user.username + '_copy',
                    interface_type: user.interface_type || 'desktop',
                    password: '',
                    password_confirmation: ''
                };
                this.showCloneModal = true;
            }
        },

        submitClone() {
            if (!this.cloneData.name.trim()) {
                alert('Please enter a name for the new user.');
                return;
            }
            if (!this.cloneData.username.trim()) {
                alert('Please enter a username for the new user.');
                return;
            }
            if (!this.cloneData.password) {
                alert('Please set a password for the new user.');
                return;
            }
            if (this.cloneData.password !== this.cloneData.password_confirmation) {
                alert('Passwords do not match.');
                return;
            }

            const form = document.getElementById('cloneUserForm');
            form.action = `{{ url('users') }}/${this.cloneSourceId}/clone`;
            document.getElementById('clone_form_name').value = this.cloneData.name;
            document.getElementById('clone_form_username').value = this.cloneData.username;
            document.getElementById('clone_form_interface_type').value = this.cloneData.interface_type;
            document.getElementById('clone_form_password').value = this.cloneData.password;
            document.getElementById('clone_form_password_confirmation').value = this.cloneData.password_confirmation;
            form.submit();
        },

        editUser(id) {
            const user = @json($users).find(u => u.id == id);
            if (user) {
                this.isEditing = true;
                this.editId = user.id;
                this.userData = {
                    name: user.name,
                    username: user.username,
                    role: user.role,
                    interface_type: user.interface_type || 'desktop',
                    password: '',
                    password_confirmation: '',
                    permissions: {},
                    branches: user.branches.map(b => b.id.toString()),
                    product_types: user.product_types.map(t => t.id.toString()),
                    rm_types: user.permitted_attributes.map(a => a.id.toString())
                };
                
                // Prefill permissions
                this.modules.forEach(m => {
                    const p = user.permissions.find(perm => perm.page_key == m);
                    
                    // Normalize features to booleans
                    let normalizedFeatures = {};
                    if (p && p.features) {
                        Object.keys(p.features).forEach(fk => {
                            normalizedFeatures[fk] = !!p.features[fk];
                        });
                    }

                    this.userData.permissions[m] = {
                        view: p ? !!p.can_view : false,
                        create: p ? !!p.can_create : false,
                        edit: p ? !!p.can_edit : false,
                        delete: p ? !!p.can_delete : false,
                        print: p ? !!p.can_print : false,
                        excel: p ? !!p.can_export_excel : false,
                        pdf: p ? !!p.can_export_pdf : false,
                        features: normalizedFeatures
                    };
                });
                
                this.showModal = true;
            }
        },

        deleteUser(id) {
            if (confirm('Are you sure you want to delete this user? This cannot be undone.')) {
                const form = document.getElementById('deleteUserForm');
                form.action = `{{ url('users') }}/${id}`;
                form.submit();
            }
        },

        submitUser() {
            const form = document.getElementById('userSubmitForm');
            const methodField = document.getElementById('method_field');
            
            if (this.isEditing) {
                form.action = `{{ url('users') }}/${this.editId}`;
                methodField.innerHTML = '<input type="hidden" name="_method" value="PUT">';
            } else {
                form.action = `{{ route('users.store') }}`;
                methodField.innerHTML = '';
            }

            document.getElementById('form_name').value = this.userData.name;
            document.getElementById('form_username').value = this.userData.username;
            document.getElementById('form_role').value = this.userData.role;
            document.getElementById('form_interface_type').value = this.userData.interface_type;
            document.getElementById('form_password').value = this.userData.password;
            document.getElementById('form_password_confirmation').value = this.userData.password_confirmation;
            
            const permissionsContainer = document.getElementById('form_permissions');
            permissionsContainer.innerHTML = '';
            
            if (this.userData.role === 'user') {
                Object.keys(this.userData.permissions).forEach(pageKey => {
                    // Filter: only allow permissions matching the interface type
                    const isMobileKey = pageKey.startsWith('mobile_');
                    if (this.userData.interface_type === 'mobile' && !isMobileKey) return;
                    if (this.userData.interface_type === 'desktop' && isMobileKey) return;

                    const rights = this.userData.permissions[pageKey];
                    Object.keys(rights).forEach(type => {
                        if (type === 'features') {
                            Object.keys(rights.features).forEach(fKey => {
                                if (rights.features[fKey]) {
                                    const input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = `permissions[${pageKey}][features][${fKey}]`;
                                    input.value = '1';
                                    permissionsContainer.appendChild(input);
                                }
                            });
                        } else if (rights[type]) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = `permissions[${pageKey}][${type}]`;
                            input.value = '1';
                            permissionsContainer.appendChild(input);
                        }
                    });
                });
            }

            const branchesContainer = document.getElementById('form_branches');
            branchesContainer.innerHTML = '';
            this.userData.branches.forEach(branchId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'branches[]';
                input.value = branchId;
                branchesContainer.appendChild(input);
            });

            const productTypesContainer = document.getElementById('form_product_types');
            productTypesContainer.innerHTML = '';
            this.userData.product_types.forEach(typeId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'product_types[]';
                input.value = typeId;
                productTypesContainer.appendChild(input);
            });

            const rmTypesContainer = document.getElementById('form_rm_types');
            rmTypesContainer.innerHTML = '';
            this.userData.rm_types.forEach(attributeId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'rm_types[]';
                input.value = attributeId;
                rmTypesContainer.appendChild(input);
            });

            form.submit();
        }
    }
}
</script>

<style>
    @keyframes bounce-short {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-3px); }
    }
    .checked\:animate-bounce-short:checked {
        animation: bounce-short 0.3s ease-in-out;
    }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>
@endsection
