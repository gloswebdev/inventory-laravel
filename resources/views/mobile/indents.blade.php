@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="indentApp()">
    <!-- Header & Action -->
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden mb-2">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 font-900 tracking-tighter italic uppercase">Indents</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Bulk Indent Management</p>
        </div>
        @if(Auth::user()->hasFeature('mobile_indents', 'bulk_entry'))
        <button @click="showEntry = !showEntry" 
                :class="showEntry ? 'bg-rose-50 text-rose-500 border-rose-100/50' : 'grad-violet text-white shadow-lg shadow-violet-200 border-2 border-white'"
                class="w-14 h-14 rounded-2xl flex items-center justify-center transition-all active:scale-90">
            <i class="fas" :class="showEntry ? 'fa-times text-lg' : 'fa-plus text-xl'"></i>
        </button>
        @endif
    </div>
</div>

    <!-- Bulk Entry Section (Collapsible) -->
    <div x-show="showEntry" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 -translate-y-10 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100" class="space-y-8">
        <div class="bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-indigo-100/30 hover:shadow-xl transition-all p-8 rounded-[3rem] space-y-8 relative overflow-hidden border border-white/80">
            <div class="absolute -top-10 -right-10 opacity-5 -rotate-12">
                <i class="fas fa-list-check text-[12rem] text-violet-900"></i>
            </div>

            <div class="grid grid-cols-2 gap-5 relative z-10">
                <div class="space-y-3">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Target Date</label>
                    <input type="date" x-model="form.indent_date" class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60 rounded-2xl py-4 px-5 text-xs font-bold text-slate-800 font-900 focus:ring-2 focus:ring-violet-500 shadow-md transition-all">
                </div>
                <div class="space-y-3">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Display Unit</label>
                    <div class="relative">
                        <select x-model="form.global_unit" {{ !Auth::user()->hasFeature('mobile_indents', 'unit_toggle') ? 'disabled' : '' }} class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60 rounded-2xl py-4 px-5 text-xs font-black {{ Auth::user()->hasFeature('mobile_indents', 'unit_toggle') ? 'text-violet-600' : 'text-slate-400 opacity-50' }} focus:ring-2 focus:ring-violet-500 shadow-md appearance-none transition-all">
                            <option value="box">📦 BOXES</option>
                            <option value="kg">⚖️ KG / LTR</option>
                        </select>
                        <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                            <i class="fas fa-ruler-combined text-[10px]"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-3 relative z-10">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Inventory Source</label>
                <div class="relative">
                    <select x-model="form.branch_code" @change="updateGlobalStocks" class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60 rounded-2xl py-4 px-6 text-xs font-bold text-slate-800 font-900 focus:ring-2 focus:ring-violet-500 appearance-none transition-all">
                        @if(count($branches) > 1)
                        <option value="">Permitted Branches (Consolidated)</option>
                        @endif
                        @foreach($branches as $branch)
                        <option value="{{ $branch->code }}">{{ $branch->name }} ({{ $branch->code }})</option>
                        @endforeach
                    </select>
                    <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                        <i class="fas fa-warehouse text-[10px]"></i>
                    </div>
                </div>
            </div>

            <div class="space-y-3 relative z-10">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Item Type</label>
                <div class="relative">
                    <select x-model="selectedTypeId" class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60 rounded-2xl py-4 px-6 text-xs font-bold text-slate-800 font-900 focus:ring-2 focus:ring-violet-500 appearance-none transition-all">
                        <option value="">All Types</option>
                        @foreach($productTypes as $type)
                        <option value="{{ $type->id }}" {{ $type->id == $defaultTypeId ? 'selected' : '' }}>{{ $type->type_name }}</option>
                        @endforeach
                    </select>
                    <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                        <i class="fas fa-filter text-[10px]"></i>
                    </div>
                </div>
            </div>

            <!-- Product Rows -->
            <div class="space-y-5 relative z-10">
                <template x-for="(row, index) in form.products" :key="index">
                    <div class="p-6 bg-white/60 border border-white/60 rounded-[2.5rem] space-y-5 shadow-md active:scale-[0.98] transition-transform">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <template x-if="row.id">
                                    <div class="space-y-1">
                                        <div class="text-[13px] font-900 text-slate-800 font-900 truncate uppercase" x-text="row.name"></div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border" :class="getPackColor(row.pack)" x-text="row.pack || '---'"></span>
                                            <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest" x-text="row.item_code"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!row.id">
                                    <div class="relative">
                                        <select x-model="row.id" @change="onProductSelect(index)" class="w-full bg-white/40 backdrop-blur-sm border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-violet-500 appearance-none">
                                            <option value="">Select Item...</option>
                                            <template x-for="p in filteredProductsByType" :key="p.id">
                                                <option :value="p.id" x-text="p.name + ' (' + (p.pack_name || '---') + ') | Stock: ' + (stockMap[p.id] ? stockMap[p.id].stock_box : '0')"></option>
                                            </template>
                                        </select>
                                        <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                                            <i class="fas fa-search text-[10px]"></i>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <button @click="removeRow(index)" class="w-10 h-10 flex items-center justify-center text-slate-300 hover:text-rose-500 transition-colors">
                                <i class="fas fa-minus-circle text-sm"></i>
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-4 items-end">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between px-2">
                                    <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Available</label>
                                    <div class="text-[9px] font-black text-emerald-500" x-text="row.stock_box + ' BOX'"></div>
                                </div>
                                <div class="bg-indigo-50/30 rounded-2xl py-2 px-4 text-[10px] font-black text-indigo-500/70 border border-indigo-50 flex justify-between items-center group">
                                    <span x-text="parseFloat(row.stock_kg).toFixed(2)"></span>
                                    <span class="text-[8px] opacity-60 font-black uppercase tracking-widest" x-text="row.uom"></span>
                                </div>
                            </div>
                            <div class="relative">
                                <input type="number" 
                                       x-model="row.demand_qty" 
                                       placeholder="Load" 
                                       class="w-full bg-white border-2 border-white/60 rounded-2xl py-3 px-4 text-[13px] font-900 text-slate-800 font-900 focus:ring-2 focus:ring-violet-500 shadow-inner text-right pr-12">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[8px] font-black text-violet-400 uppercase tracking-widest" x-text="form.global_unit"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="grid grid-cols-2 gap-5 relative z-10">
                <button @click="openSearch" class="py-4 border-2 border-dashed border-violet-200 rounded-3xl text-[10px] font-black text-violet-500 uppercase tracking-widest hover:bg-violet-50/50 transition-all flex items-center justify-center gap-3">
                    <i class="fas fa-grid-2-plus text-xs"></i>
                    <span>Quick Select</span>
                </button>
                <button @click="addRow" class="py-4 border-2 border-dashed border-white/70 rounded-3xl text-[10px] font-black text-slate-400 uppercase tracking-widest hover:bg-white/60 backdrop-blur-md transition-all flex items-center justify-center gap-3">
                    <i class="fas fa-plus text-xs"></i>
                    <span>New Row</span>
                </button>
            </div>

            <button @click="previewIndent" :disabled="form.products.length === 0" class="w-full grad-violet p-1 rounded-[2.5rem] shadow-xl shadow-violet-100 transition-all active:scale-[0.98] disabled:opacity-50">
                <div class="bg-white/10 p-5 rounded-[2.4rem] flex items-center justify-center gap-4 text-white font-900 italic tracking-tight uppercase text-sm border border-white/20">
                    <i class="fas fa-paper-plane"></i>
                    <span>Preview & Finalize</span>
                </div>
            </button>
        </div>
    </div>

    <!-- History Logs Section -->
    <div x-show="!showEntry" x-transition:enter="transition ease-out duration-700" x-transition:enter-start="opacity-0 translate-y-10" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-8">
        <div class="flex items-center justify-between px-3 relative" x-data="{ showFilters: {{ request()->anyFilled(['from_date', 'to_date', 'branch_code', 'user_id', 'status']) ? 'true' : 'false' }} }">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2"><i class="fas fa-clock-rotate-left text-indigo-400"></i> Transaction Logs</h3>
            <div class="flex items-center gap-4">
                <button @click="showFilters = !showFilters" class="text-[10px] font-black flex items-center gap-2 transition-all p-2 rounded-xl" :class="showFilters ? 'bg-indigo-100 text-indigo-600' : 'text-slate-400 hover:text-slate-600'">
                    <i class="fas fa-sliders text-xs"></i>
                    <span x-text="showFilters ? 'HIDE' : 'FILTER'"></span>
                </button>
                <div class="h-4 w-[1px] bg-slate-200"></div>
                <div class="text-[10px] font-black text-slate-800 font-900 tracking-tighter">{{ $indents->total() }} LOGS</div>
            </div>

            <!-- Mobile Filters (Collapsible) -->
            <div x-show="showFilters" x-transition x-cloak class="absolute left-0 right-0 top-full mt-6 glass-premium p-8 rounded-[3rem] shadow-2xl z-50 space-y-6 mx-2 border border-white/80">
                <form action="{{ route('mobile.indents') }}" method="GET" class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Start Date</label>
                            <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60 rounded-2xl py-3 px-4 text-[11px] font-bold text-slate-800 font-900 shadow-inner">
                        </div>
                        <div class="space-y-3">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">End Date</label>
                            <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full bg-white/40 backdrop-blur-sm border-2 border-white/60 rounded-2xl py-3 px-4 text-[11px] font-bold text-slate-800 font-900 shadow-inner">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Status</label>
                            <select name="status" class="w-full bg-white/40 backdrop-blur-sm border-none rounded-2xl py-3.5 px-4 text-[10px] font-bold text-slate-800 font-900 appearance-none shadow-md">
                                <option value="">All Workflow</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="partly completed" {{ request('status') == 'partly completed' ? 'selected' : '' }}>Partial</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Finalized</option>
                            </select>
                        </div>
                        <div class="space-y-3">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Branch</label>
                            <select name="branch_code" class="w-full bg-white/40 backdrop-blur-sm border-none rounded-2xl py-3.5 px-4 text-[10px] font-bold text-slate-800 font-900 appearance-none shadow-md">
                                <option value="">All Access</option>
                                @foreach($branches as $branch)
                                <option value="{{ $branch->code }}" {{ request('branch_code') == $branch->code ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if(Auth::user()->hasFeature('mobile_indents', 'user_filter'))
                    <div class="space-y-3">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Filtered User</label>
                        <div class="relative">
                            <select name="user_id" class="w-full bg-white/40 backdrop-blur-sm border-none rounded-2xl py-4 px-6 text-[10px] font-bold text-slate-800 font-900 appearance-none shadow-md">
                                <option value="">All Registered Users</option>
                                @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                                <i class="fas fa-user-tag text-[10px]"></i>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="flex gap-4 pt-3">
                        <button type="submit" class="flex-1 grad-violet text-white py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-xl shadow-violet-100 italic">Filter Logs</button>
                        <a href="{{ route('mobile.indents') }}" class="w-14 bg-slate-100 text-slate-500 rounded-2xl flex items-center justify-center transition-all active:scale-90 hover:bg-slate-200">
                            <i class="fas fa-rotate-right text-xs"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Indent Cards -->
        <div class="space-y-6">
            @if(Auth::user()->hasFeature('mobile_indents', 'history'))
            @foreach($indents as $indent)
            @php
                $statusColor = [
                    'completed' => 'grad-emerald shadow-emerald-200/50',
                    'partly completed' => 'grad-indigo shadow-indigo-200/50',
                    'pending' => 'grad-amber shadow-amber-200/50',
                ][strtolower($indent->status)] ?? 'grad-amber shadow-amber-200/50';
                
                $iconColor = [
                    'completed' => 'text-emerald-600 bg-emerald-50',
                    'partly completed' => 'text-indigo-600 bg-indigo-50',
                    'pending' => 'text-amber-600 bg-amber-50',
                ][strtolower($indent->status)] ?? 'text-amber-600 bg-amber-50';
            @endphp
            <div class="group relative bg-white/70 backdrop-blur-xl p-7 rounded-[2.5rem] flex flex-col gap-5 transition-all border border-white/80 shadow-lg shadow-indigo-100/20 hover:shadow-xl hover:shadow-indigo-100/40 overflow-hidden">
                <div class="absolute top-0 left-0 w-2 h-full {{ $statusColor }} opacity-80"></div>
                
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 {{ $iconColor }} rounded-[1.2rem] flex items-center justify-center transition-transform group-hover:scale-105 shadow-inner border border-white/60">
                            <i class="fas fa-file-invoice-dollar text-xl"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[9px] font-black uppercase tracking-widest {{ str_replace('grad-', 'text-', explode(' ', $statusColor)[0]) }} bg-white/50 px-2 py-0.5 rounded-md border border-white/60">
                                    {{ $indent->status ?: 'PENDING' }}
                                </span>
                                <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                                <span class="text-[9px] text-slate-400 font-900 tracking-tighter uppercase">{{ $indent->items_count }} Items</span>
                            </div>
                            <div class="text-[11px] font-black text-indigo-600 tracking-widest mt-2 uppercase">#IND-{{ $indent->id }}</div>
                            <div class="text-lg font-900 text-slate-800 tracking-tight truncate max-w-[150px] leading-tight mt-0.5 group-hover:text-indigo-600 transition-colors">{{ $indent->branch_name }}</div>
                            <div class="text-[10px] text-slate-400 font-bold mt-1.5 flex items-center gap-1.5">
                                <i class="far fa-calendar-alt"></i>
                                {{ date('d M, Y', strtotime($indent->indent_date)) }}
                            </div>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="text-3xl font-900 text-slate-800 tracking-tighter leading-none">{{ number_format($indent->total_boxes, 0) }}</div>
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mt-1.5">Boxes</div>
                    </div>
                </div>
                
                <div class="border-t border-white/60 pt-4 mt-2">
                    <!-- Actions Row: Horizontally Scrollable -->
                    <div class="flex items-center gap-3 overflow-x-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none] pb-2 -mx-2 px-2 snap-x">
                        @if(Auth::user()->hasPermission('mobile_indents', 'view'))
                        <button @click="viewIndentDetails({{ $indent->id }})" class="w-10 h-10 bg-indigo-50/50 rounded-2xl flex items-center justify-center text-indigo-500 border border-indigo-100/50 transition-all active:scale-90 hover:bg-indigo-500 hover:text-white shrink-0 snap-center shadow-sm" title="View"><i class="fas fa-eye text-sm"></i></button>
                        @endif
                        @if(Auth::user()->hasFeature('mobile_indents', 'process'))
                        <button @click="viewProgressDetails({{ $indent->id }})" class="w-10 h-10 bg-amber-50/50 rounded-2xl flex items-center justify-center text-amber-500 border border-amber-100/50 transition-all active:scale-90 hover:bg-amber-500 hover:text-white shrink-0 snap-center shadow-sm" title="Progress"><i class="fas fa-list-check text-sm"></i></button>
                        @endif
                        @if(Auth::user()->hasPermission('mobile_indents', 'print'))
                        <a href="{{ route('mobile.indents.print', $indent->id) }}" target="_blank" class="w-10 h-10 bg-blue-50/50 rounded-2xl flex items-center justify-center text-blue-500 border border-blue-100/50 transition-all active:scale-90 hover:bg-blue-500 hover:text-white shrink-0 snap-center shadow-sm" title="Print"><i class="fas fa-print text-sm"></i></a>
                        @endif
                        @if(Auth::user()->hasPermission('mobile_indents', 'excel'))
                        <a href="{{ route('mobile.indents.excel', $indent->id) }}" class="w-10 h-10 bg-emerald-50/50 rounded-2xl flex items-center justify-center text-emerald-500 border border-emerald-100/50 transition-all active:scale-90 hover:bg-emerald-500 hover:text-white shrink-0 snap-center shadow-sm" title="Excel"><i class="fas fa-file-excel text-sm"></i></a>
                        @endif
                        @if(Auth::user()->hasPermission('mobile_indents', 'pdf'))
                        <a href="{{ route('mobile.indents.pdf', $indent->id) }}" class="w-10 h-10 bg-rose-50/50 rounded-2xl flex items-center justify-center text-rose-500 border border-rose-100/50 transition-all active:scale-90 hover:bg-rose-500 hover:text-white shrink-0 snap-center shadow-sm" title="PDF"><i class="fas fa-file-pdf text-sm"></i></a>
                        @endif
                        @if(Auth::user()->hasFeature('mobile_indents', 'process'))
                        <a href="{{ route('mobile.indents.process', $indent->id) }}" class="w-10 h-10 bg-slate-50/50 rounded-2xl flex items-center justify-center text-slate-500 border border-slate-200/50 transition-all active:scale-90 hover:bg-slate-600 hover:text-white shrink-0 snap-center shadow-sm" title="Status"><i class="fas fa-gear text-sm"></i></a>
                        @endif

                        @if(Auth::user()->hasFeature('mobile_indents', 'clone'))
                        <button @click="cloneIndent({{ $indent->id }})" class="w-10 h-10 bg-violet-50/50 rounded-2xl flex items-center justify-center text-violet-500 border border-violet-100/50 transition-all active:scale-90 hover:bg-violet-500 hover:text-white shrink-0 snap-center shadow-sm" title="Clone"><i class="fas fa-copy text-sm"></i></button>
                        @endif

                        @if(Auth::user()->hasFeature('mobile_indents', 'edit'))
                        <button @click="editIndent({{ $indent->id }})" class="w-10 h-10 bg-slate-50/50 rounded-2xl flex items-center justify-center text-slate-500 border border-slate-200/50 transition-all active:scale-90 hover:bg-slate-600 hover:text-white shrink-0 snap-center shadow-sm" title="Edit"><i class="fas fa-edit text-sm"></i></button>
                        @endif

                        @if(Auth::user()->hasFeature('mobile_indents', 'delete'))
                        <button @click="deleteIndent({{ $indent->id }})" class="w-10 h-10 bg-rose-50/50 rounded-2xl flex items-center justify-center text-rose-500 border border-rose-100/50 transition-all active:scale-90 hover:bg-rose-500 hover:text-white shrink-0 snap-center shadow-sm" title="Delete"><i class="fas fa-trash text-sm"></i></button>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
            @else
            <div class="p-12 text-center bg-white/40 backdrop-blur-sm rounded-[3rem] border-2 border-dashed border-white/60 shadow-inner">
                <div class="w-20 h-20 bg-white/50 rounded-full flex items-center justify-center mx-auto mb-5 border border-white/60 shadow-sm">
                    <i class="fas fa-lock text-slate-300 text-3xl"></i>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">History Access Restricted</p>
            </div>
            @endif
        </div>

        <div class="mt-10 px-4 pb-20 scale-90 origin-top">
            {{ $indents->onEachSide(0)->links() }}
        </div>
    </div>

    <!-- View Indent Modal -->
    <div x-show="viewModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-[3rem] w-full max-w-lg overflow-hidden shadow-2xl animate-in zoom-in duration-300">
            <div class="grad-violet p-8 text-white relative border-b border-white/10">
                <h3 class="text-2xl font-900 italic tracking-tighter uppercase">Indent Details</h3>
                <p class="text-white/70 text-[10px] font-bold uppercase tracking-widest mt-1" x-text="selectedIndent?.branch_name"></p>
                <button @click="viewModal = false" class="absolute top-8 right-8 w-10 h-10 bg-white/10 rounded-full flex items-center justify-center text-white border border-white/20 active:scale-90 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-8 space-y-6 max-h-[60vh] overflow-y-auto custom-scrollbar bg-white/60 backdrop-blur-md/30">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white p-5 rounded-3xl border border-white/60 shadow-md">
                        <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Indent ID</div>
                        <div class="text-xs font-900 text-indigo-600 italic mt-1" x-text="'#IND-' + selectedIndent?.id"></div>
                    </div>
                    <div class="bg-white p-5 rounded-3xl border border-white/60 shadow-md">
                        <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Creator</div>
                        <div class="text-xs font-900 text-slate-700 mt-1 truncate" x-text="selectedIndent?.user?.name || 'System'"></div>
                    </div>
                    <div class="bg-white p-5 rounded-3xl border border-white/60 shadow-md">
                        <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Indent Date</div>
                        <div class="text-xs font-900 text-slate-700 mt-1" x-text="selectedIndent ? new Date(selectedIndent.indent_date).toLocaleDateString('en-GB') : ''"></div>
                    </div>
                    <div class="bg-white p-5 rounded-3xl border border-white/60 shadow-md">
                        <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Total Load</div>
                        <div class="text-xs font-900 text-slate-700 mt-1" x-text="selectedIndent?.total_boxes + ' BOXES'"></div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Products Requested</div>
                    <template x-for="item in selectedIndent?.items" :key="item.id">
                        <div class="flex items-center justify-between p-5 bg-white/70 backdrop-blur-md border border-white/80 rounded-[2rem] shadow-md transform transition-all hover:scale-[1.02] hover:shadow-lg hover:shadow-indigo-100/50">
                            <div class="flex-1 min-w-0 pr-4">
                                <div class="text-[11px] font-900 text-slate-800 font-900 uppercase truncate" x-text="item.product_name"></div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[8px] bg-slate-100/80 text-slate-500 px-2 py-0.5 rounded font-black uppercase tracking-widest" x-text="item.product?.item_code"></span>
                                    <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border" :class="getPackColor(item.product?.pack_name)" x-text="item.product?.pack_name || '---'"></span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <div class="flex items-center gap-1.5 bg-emerald-50 px-2 py-1 rounded-lg">
                                        <span class="text-[7px] font-black text-emerald-500 uppercase">Live Stock</span>
                                        <span class="text-[9px] font-900 text-emerald-600" x-text="parseFloat(item.stock_box).toFixed(2) + ' BOX'"></span>
                                    </div>
                                    <div class="flex items-center gap-1.5 bg-amber-50 px-2 py-1 rounded-lg">
                                        <span class="text-[7px] font-black text-amber-500 uppercase">Final Boxes</span>
                                        <span class="text-[9px] font-900 text-amber-600" x-text="parseFloat(item.final_qty_box).toFixed(2) + ' BOX'"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-[13px] font-900 text-violet-600 bg-violet-50 px-4 py-2 rounded-xl shadow-inner border border-violet-100" x-text="item.demand_qty + ' ' + (item.demand_unit || 'BOX').toUpperCase()"></div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="p-8 border-t border-white/60">
                <button @click="viewModal = false" class="w-full py-5 grad-slate text-white rounded-[1.5rem] font-900 italic tracking-tight uppercase text-xs shadow-xl active:scale-95 transition-all">Close Viewer</button>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div x-show="progressModal" x-cloak class="fixed inset-0 z-[110] flex items-center justify-center p-6 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-[3rem] w-full max-w-lg overflow-hidden shadow-2xl animate-in zoom-in duration-300">
            <div class="grad-indigo p-8 text-white relative">
                <h3 class="text-2xl font-900 italic tracking-tighter uppercase leading-none">Completion Status</h3>
                <p class="text-indigo-200 text-[10px] font-black uppercase tracking-[0.2em] mt-3" x-text="(selectedIndent?.branch_name || '') + ' | ' + (selectedIndent ? new Date(selectedIndent.indent_date).toLocaleDateString('en-GB') : '')"></p>
                <button @click="progressModal = false" class="absolute top-8 right-8 w-10 h-10 bg-white/10 rounded-full flex items-center justify-center text-white border border-white/20 active:scale-90 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-8 space-y-4 max-h-[60vh] overflow-y-auto no-scrollbar">
                <div class="grid grid-cols-4 gap-4 px-4 py-2 text-[8px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">
                    <div class="col-span-2">Product</div>
                    <div class="text-center">Asked</div>
                    <div class="text-right">Packed</div>
                </div>
                <template x-for="item in selectedIndent?.items" :key="item.id">
                    <div class="grid grid-cols-4 gap-4 items-center p-4 bg-white/70 backdrop-blur-xl rounded-2xl border border-white/80 shadow-sm hover:shadow-md transition-all">
                        <div class="col-span-2">
                            <div class="text-[11px] font-900 text-slate-800 font-900 uppercase truncate" x-text="item.product_name"></div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[8px] bg-slate-100/80 text-slate-500 px-2 py-0.5 rounded font-black uppercase tracking-widest" x-text="item.product?.item_code"></span>
                                <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border" :class="getPackColor(item.product?.pack_name)" x-text="item.product?.pack_name || '---'"></span>
                            </div>
                        </div>
                        <div class="text-center text-[12px] font-900 text-slate-600" x-text="item.final_qty_box"></div>
                        <div class="text-right">
                            <div class="text-[12px] font-900" :class="Number(item.completed_qty) >= Number(item.final_qty_box) ? 'text-emerald-600' : 'text-amber-600'" x-text="item.completed_qty || 0"></div>
                            <div class="text-[8px] font-black uppercase tracking-tighter" :class="Number(item.completed_qty) >= Number(item.final_qty_box) ? 'text-emerald-400' : 'text-amber-400'" x-text="Number(item.completed_qty) >= Number(item.final_qty_box) ? 'Done' : 'Pending'"></div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="p-8 border-t border-white/60">
                <button @click="progressModal = false" class="w-full py-5 grad-indigo text-white rounded-[1.5rem] font-900 italic tracking-tight uppercase text-xs shadow-xl active:scale-95 transition-all">Got It</button>
            </div>
        </div>
    </div>

    <!-- Searchable Multi-Select Modal -->
    <div x-show="showSearch" x-cloak class="fixed inset-0 z-[60] flex flex-col glass-premium backdrop-blur-3xl animate-in fade-in duration-500">
        <div class="p-8 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-900 text-slate-800 font-900 tracking-tighter">Bulk Selector</h3>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1 italic">Multi-item rapid selection</p>
                </div>
                <button @click="showSearch = false" class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-500 hover:text-rose-500 transition-all active:scale-90">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="relative group">
                <i class="fas fa-search absolute left-6 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-violet-500 transition-colors"></i>
                <input type="text" x-model="searchQuery" placeholder="Search product name or code..." class="w-full bg-white/60 backdrop-blur-md border-2 border-white/60 rounded-[1.5rem] py-5 pl-14 pr-6 text-sm font-bold focus:ring-2 focus:ring-violet-500 shadow-xl shadow-indigo-100/30 transition-all">
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-8 space-y-4 pb-32 custom-scrollbar">
            <template x-for="p in filteredProductsByTypeAndSearch" :key="p.id">
                <label class="flex items-center gap-5 p-5 bg-white/70 backdrop-blur-xl rounded-[2rem] border-2 border-white/80 transition-all active:scale-[0.98] cursor-pointer" :class="selectedProducts.includes(p.id) ? 'border-violet-400 bg-violet-50/50 shadow-xl shadow-violet-200/50' : 'hover:border-violet-200 hover:shadow-lg'">
                    <input type="checkbox" :value="p.id" x-model="selectedProducts" class="w-7 h-7 rounded-xl text-violet-600 border-white/70 focus:ring-violet-500 shadow-md">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <div class="text-[13px] font-900 text-slate-800 font-900 truncate uppercase" x-text="p.name"></div>
                            <div class="text-[10px] font-black text-violet-600 bg-violet-50 px-2.5 py-1 rounded-lg shadow-md border border-violet-100/50" x-text="'Stock: ' + (stockMap[p.id] ? stockMap[p.id].stock_box : '0')"></div>
                        </div>
                        <div class="flex items-center gap-3 mt-1.5">
                            <span class="text-[9px] bg-slate-100/80 text-slate-500 px-2.5 py-1 rounded-lg font-black uppercase tracking-widest shadow-inner" x-text="p.item_code"></span>
                            <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                            <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border" :class="getPackColor(p.pack_name)" x-text="p.pack_name || '---'"></span>
                        </div>
                    </div>
                </label>
            </template>
        </div>

        <div class="fixed bottom-0 left-0 w-full p-8 grad-violet border-t border-white/20">
            <div class="max-w-md mx-auto flex items-center gap-6">
                <div>
                    <div class="text-[10px] font-black text-violet-200 uppercase tracking-widest leading-none">Catalog Batch</div>
                    <div class="text-2xl font-900 text-white tracking-tighter mt-2" x-text="selectedProducts.length + ' SELECTION'"></div>
                </div>
                <button @click="confirmSelection" class="flex-1 bg-white text-violet-600 font-900 py-5 rounded-[1.5rem] shadow-2xl shadow-violet-900/20 uppercase italic tracking-tight transition-all active:scale-95 flex items-center justify-center gap-3">
                    <i class="fas fa-layer-group text-sm"></i>
                    <span>CONFIRM BATCH</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Invoice Preview Modal -->
    <div x-show="showPreview" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-6 bg-slate-900/80 backdrop-blur-md animate-in zoom-in duration-500">
        <div class="bg-white w-full max-w-sm rounded-[3.5rem] overflow-hidden shadow-2xl flex flex-col max-h-[85vh] border-4 border-white">
            <div class="grad-violet p-10 text-white relative">
                <div class="absolute -top-6 -right-6 opacity-10">
                    <i class="fas fa-file-invoice text-[10rem]"></i>
                </div>
                <h3 class="text-3xl font-900 italic tracking-tighter uppercase relative z-10 leading-none">Preview</h3>
                <p class="text-[9px] font-black uppercase tracking-[0.3em] text-violet-200 mt-3 relative z-10">Draft Indent Verification</p>
                
                <div class="mt-8 flex justify-between items-end relative z-10 border-t border-white/20 pt-6">
                    <div>
                        <div class="text-[8px] font-black text-violet-200 uppercase tracking-widest">Target Location</div>
                        <div class="text-[13px] font-900 truncate max-w-[120px]" x-text="getBranchName()"></div>
                    </div>
                    <div class="text-right">
                        <div class="text-[8px] font-black text-violet-200 uppercase tracking-widest">Period</div>
                        <div class="text-[13px] font-900" x-text="form.indent_date"></div>
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-8 space-y-5 custom-scrollbar bg-white/60 backdrop-blur-md/30">
                <template x-for="p in form.products" :key="p.id">
                    <div class="flex items-center justify-between p-4 bg-white rounded-2xl border border-white/60 shadow-md group">
                        <div class="flex-1 min-w-0 pr-5">
                            <div class="text-[11px] font-900 text-slate-800 font-900 truncate uppercase" x-text="p.name"></div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[8px] bg-slate-100/80 text-slate-500 px-2 py-0.5 rounded font-black uppercase tracking-widest" x-text="p.item_code"></span>
                                <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border" :class="getPackColor(p.pack)" x-text="p.pack || '---'"></span>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-[13px] font-900 text-slate-800 font-900 tracking-tighter" x-text="p.demand_qty + ' ' + form.global_unit.toUpperCase()"></div>
                            <div class="text-[9px] text-violet-500 font-black italic tracking-tighter mt-0.5" x-text="calculateFinal(p) + ' BOX EQUIV.'"></div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="p-8 bg-white border-t border-white/60 space-y-6">
                <div class="flex items-center justify-between px-3 p-4 bg-white/60 backdrop-blur-md rounded-2xl border border-white/60">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Load</span>
                    <span class="text-3xl font-900 text-slate-900 tracking-tighter" x-text="getGrandTotal() + ' BOX'"></span>
                </div>
                <div class="flex gap-4">
                    <button @click="showPreview = false" class="flex-1 bg-slate-100 text-slate-400 font-900 py-5 rounded-[1.5rem] uppercase tracking-widest text-[10px] active:scale-95 transition-all">Revise</button>
                    <button @click="submitIndent" :disabled="submitting" class="flex-[2] grad-violet text-white font-900 py-5 rounded-[1.5rem] shadow-xl shadow-violet-100 uppercase italic tracking-tight flex items-center justify-center gap-3 active:scale-95 transition-all disabled:opacity-50">
                        <template x-if="!submitting">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-check-double"></i>
                                <span>Commit</span>
                            </div>
                        </template>
                        <template x-if="submitting">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-spinner animate-spin"></i>
                                <span>Processing</span>
                            </div>
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function indentApp() {
    return {
        showEntry: false,
        showSearch: false,
        showPreview: false,
        viewModal: false,
        progressModal: false,
        selectedIndent: null,
        submitting: false,
        searchQuery: '',
        selectedProducts: [],
        form: {
            indent_date: '{{ date("Y-m-d") }}',
            branch_code: '',
            global_unit: 'box',
            products: []
        },
        products: @json($products),
        branches: @json($branches),
        stockMap: {},
        externalStock: {},
        editingIndentId: null,
        selectedTypeId: '{{ $defaultTypeId }}',

        getPackColor(packName) {
            if (!packName) return 'bg-slate-50 text-slate-500 border-slate-200';
            const name = packName.toUpperCase().trim();
            if (name.includes('1 KG') || name.includes('1 LTR') || name.includes('1KG') || name.includes('1LTR')) {
                return 'bg-indigo-50 text-indigo-700 border-indigo-200/60';
            }
            if (name.includes('500 GM') || name.includes('500 ML') || name.includes('500GM') || name.includes('500ML')) {
                return 'bg-emerald-50 text-emerald-700 border-emerald-200/60';
            }
            if (name.includes('250 GM') || name.includes('250 ML') || name.includes('250GM') || name.includes('250ML')) {
                return 'bg-rose-50 text-rose-700 border-rose-200/60';
            }
            if (name.includes('100 GM') || name.includes('100 ML') || name.includes('100GM') || name.includes('100ML')) {
                return 'bg-amber-50 text-amber-700 border-amber-200/60';
            }
            if (name.includes('50 GM') || name.includes('50 ML') || name.includes('50GM') || name.includes('50ML')) {
                return 'bg-cyan-50 text-cyan-700 border-cyan-200/60';
            }
            if (name.includes('5 LTR') || name.includes('5 KG') || name.includes('5LTR') || name.includes('5KG')) {
                return 'bg-teal-50 text-teal-700 border-teal-200/60';
            }
            return 'bg-violet-50 text-violet-700 border-violet-200/60';
        },
        
        get filteredProductsByType() {
            if (!this.selectedTypeId) return this.products;
            return this.products.filter(p => p.product_type_id == this.selectedTypeId);
        },

        get filteredProductsByTypeAndSearch() {
            let list = this.products;
            if (this.selectedTypeId) {
                list = list.filter(p => p.product_type_id == this.selectedTypeId);
            }
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                list = list.filter(p => 
                    p.name.toLowerCase().includes(q) || 
                    (p.item_code && p.item_code.toLowerCase().includes(q))
                );
            }
            return list;
        },

        init() {
            // Auto-select if only one branch
            if (this.branches.length === 1) {
                this.form.branch_code = this.branches[0].code;
            }
            this.updateGlobalStocks();
            
            // Auto-refresh when tab is focused or every 30 seconds to sync with desktop changes
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && !this.showEntry && !this.showPreview && !this.viewModal && !this.progressModal) {
                    location.reload();
                }
            });
            
            setInterval(() => {
                if (document.visibilityState === 'visible' && !this.showEntry && !this.showPreview && !this.viewModal && !this.progressModal && !this.submitting) {
                    location.reload();
                }
            }, 30000);
        },

        async updateGlobalStocks() {
            try {
                const response = await fetch("{{ route('mobile.fg-stock') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ 
                        product_ids: this.products.map(p => p.id), 
                        branch_code: this.form.branch_code 
                    })
                });
                const res = await response.json();
                if (res.success) {
                    res.stocks.forEach(stockItem => {
                        this.stockMap[stockItem.id] = stockItem;
                    });
                }
            } catch (e) {}
        },

        openSearch() {
            this.searchQuery = '';
            this.selectedProducts = this.form.products.map(p => p.id);
            this.showSearch = true;
        },

        confirmSelection() {
            const selectedIds = this.selectedProducts.map(id => Number(id));
            const newProducts = this.products.filter(p => selectedIds.includes(Number(p.id)));
            
            this.form.products = newProducts.map(p => {
                const pId = Number(p.id);
                const existing = this.form.products.find(ep => Number(ep.id) === pId);
                return {
                    id: p.id,
                    name: p.name,
                    item_code: p.item_code,
                    pack: p.pack_name,
                    uom: p.uom,
                    unit_box: p.unit_box || 1,
                    weight_unit: p.weight_unit || 1,
                    demand_qty: existing ? existing.demand_qty : '',
                    stock_box: existing ? existing.stock_box : 0,
                    stock_kg: existing ? existing.stock_kg : 0
                };
            });

            this.showSearch = false;
            this.updateAllStocks();
        },

        onProductSelect(index) {
            const row = this.form.products[index];
            const p = this.products.find(p => p.id == row.id);
            if (p) {
                row.name = p.name;
                row.item_code = p.item_code;
                row.pack = p.pack_name;
                row.uom = p.uom;
                row.unit_box = p.unit_box || 1;
                row.weight_unit = p.weight_unit || 1;
                this.updateAllStocks();
            }
        },

        addRow() {
            this.form.products.push({ id: '', name: '', demand_qty: '', stock_box: 0, stock_kg: 0 });
        },

        removeRow(index) {
            this.form.products.splice(index, 1);
        },

        async updateAllStocks() {
            if (this.form.products.length === 0) return;
            
            try {
                const response = await fetch("{{ route('mobile.fg-stock') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ 
                        product_ids: this.form.products.filter(p => p.id).map(p => p.id), 
                        branch_code: this.form.branch_code 
                    })
                });
                const res = await response.json();
                if (res.success) {
                    res.stocks.forEach(stockItem => {
                        const product = this.form.products.find(p => p.id == stockItem.id);
                        if (product) {
                            product.stock_box = stockItem.stock_box;
                            product.stock_kg = stockItem.stock_kg;
                        }
                    });
                }
            } catch (e) {}
        },

        getBranchName() {
            if (!this.form.branch_code) return 'Consolidated Access';
            const b = this.branches.find(b => b.code === this.form.branch_code);
            return b ? b.name : this.form.branch_code;
        },

        calculateFinal(p) {
            if (!p.demand_qty) return 0;
            if (this.form.global_unit === 'box') return parseFloat(p.demand_qty);
            return parseFloat(p.demand_qty / (p.weight_unit || 1)).toFixed(2);
        },

        getGrandTotal() {
            return this.form.products.reduce((acc, p) => acc + parseFloat(this.calculateFinal(p)), 0).toFixed(0);
        },

        previewIndent() {
            if (this.form.products.some(p => !p.id || !p.demand_qty)) {
                alert('Ensure all quantities are accurately populated.');
                return;
            }
            this.showPreview = true;
        },

        async submitIndent() {
            this.submitting = true;
            try {
                const payload = {
                    ...this.form,
                    products: this.form.products.map(p => ({
                        ...p,
                        unit: this.form.global_unit,
                        final_qty_box: this.calculateFinal(p)
                    }))
                };

                const url = this.editingIndentId 
                    ? `{{ url('indent-api/show') }}/${this.editingIndentId}/update` 
                    : "{{ route('mobile.indents.store') }}";

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json', 
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                    },
                    body: JSON.stringify(payload)
                });

                const res = await response.json();
                if (res.success) {
                    alert(res.message || 'Indent processed successfully!');
                    window.location.href = "{{ route('mobile.indents') }}";
                } else {
                    alert(res.message);
                }
            } catch (e) {
                alert('Connection failure. Try again.');
            } finally {
                this.submitting = false;
            }
        },

        async viewIndentDetails(id) {
            try {
                const res = await fetch("{{ url('indent-api/show') }}/" + id, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedIndent = data.indent;
                    this.viewModal = true;
                } else {
                    alert('Could not load indent details');
                }
            } catch(e) { 
                alert("Failed to fetch details"); 
            }
        },

        async viewProgressDetails(id) {
            try {
                const res = await fetch("{{ url('indent-api/show') }}/" + id, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedIndent = data.indent;
                    this.progressModal = true;
                } else {
                    alert('Could not load progress data');
                }
            } catch(e) { 
                alert("Failed to fetch progress"); 
            }
        },

        async editIndent(id) {
            if(!confirm('Switch to Edit Mode for Indent #IND-' + id + '?')) return;
            try {
                const res = await fetch("{{ url('indent-api/show') }}/" + id, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    const indent = data.indent;
                    this.editingIndentId = id;
                    this.form.branch_code = indent.branch_code;
                    this.form.indent_date = indent.indent_date;
                    this.form.products = indent.items.map(item => ({
                        id: item.product_id,
                        name: item.product_name,
                        item_code: item.product?.item_code || '',
                        demand_qty: item.demand_qty,
                        unit_box: item.product?.unit_box || 1,
                        weight_unit: item.product?.weight_multiplier || 1,
                        stock_box: item.stock_box,
                        stock_kg: item.stock_kg
                    }));
                    this.showEntry = true;
                    alert('Form populated for Editing #IND-' + id);
                }
            } catch(e) { alert("Failed to load indent for editing"); }
        },

        async deleteIndent(id) {
            if(!confirm('Permanently DELETE Indent #IND-' + id + '?')) return;
            try {
                const res = await fetch("{{ url('indent-api/show') }}/" + id, {
                    method: 'DELETE',
                    headers: { 
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if(data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error occurred');
                }
            } catch(e) { 
                console.error(e);
                alert("Delete failed: " + e.message); 
            }
        },

        async cloneIndent(id) {
            if(!confirm('Clone Indent #IND-' + id + ' to a new draft?')) return;
            try {
                const res = await fetch("{{ url('indent-api/show') }}/" + id + "/clone", {
                    method: 'POST',
                    headers: { 
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if(data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch(e) { alert("Clone failed"); }
        }
    }
}
</script>

<style>
    /* Mobile Pagination Styling */
    .pagination { @apply flex justify-center gap-2 mt-10; }
    .page-item { @apply w-11 h-11 rounded-2xl flex items-center justify-center text-[10px] font-black border-2 border-slate-50 transition-all bg-white text-slate-400 shadow-md active:scale-90; }
    .page-item.active { @apply bg-violet-600 border-violet-600 text-white shadow-xl shadow-violet-100; }
    .page-item.disabled { @apply opacity-20 grayscale pointer-events-none; }
    .page-link { @apply w-full h-full flex items-center justify-center; }
    
    [x-cloak] { display: none !important; }
    
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.05); border-radius: 10px; }
</style>
@endsection
