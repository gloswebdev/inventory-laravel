@extends('layouts.mobile')

@section('content')
<div class="space-y-8 pb-10" x-data="indentApp()">
    <!-- Header & Action -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-900 text-slate-800 tracking-tighter">Orders</h2>
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Bulk Indent Management</p>
        </div>
        <button @click="showEntry = !showEntry" 
                :class="showEntry ? 'bg-rose-50 text-rose-500 border-rose-100/50' : 'grad-violet text-white shadow-lg shadow-violet-200 border-2 border-white'"
                class="w-14 h-14 rounded-2xl flex items-center justify-center transition-all active:scale-90">
            <i class="fas" :class="showEntry ? 'fa-times text-lg' : 'fa-plus text-xl'"></i>
        </button>
    </div>

    <!-- Bulk Entry Section (Collapsible) -->
    <div x-show="showEntry" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 -translate-y-10 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100" class="space-y-8">
        <div class="glass-premium p-8 rounded-[3rem] space-y-8 relative overflow-hidden border border-white/80">
            <div class="absolute -top-10 -right-10 opacity-5 -rotate-12">
                <i class="fas fa-list-check text-[12rem] text-violet-900"></i>
            </div>

            <div class="grid grid-cols-2 gap-5 relative z-10">
                <div class="space-y-3">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Target Date</label>
                    <input type="date" x-model="form.indent_date" class="w-full bg-slate-50/50 border-2 border-slate-100/50 rounded-2xl py-4 px-5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-violet-500 shadow-sm transition-all">
                </div>
                <div class="space-y-3">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Display Unit</label>
                    <select x-model="form.global_unit" class="w-full bg-slate-50/50 border-2 border-slate-100/50 rounded-2xl py-4 px-5 text-xs font-black text-violet-600 focus:ring-2 focus:ring-violet-500 shadow-sm appearance-none transition-all">
                        <option value="box">📦 BOXES</option>
                        <option value="kg">⚖️ KG / LTR</option>
                    </select>
                </div>
            </div>

            <div class="space-y-3 relative z-10">
                <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Inventory Source</label>
                <div class="relative">
                    <select x-model="form.branch_code" @change="updateGlobalStocks" class="w-full bg-slate-50/50 border-2 border-slate-100/50 rounded-2xl py-4 px-6 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-violet-500 appearance-none transition-all">
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

            <!-- Product Rows -->
            <div class="space-y-5 relative z-10">
                <template x-for="(row, index) in form.products" :key="index">
                    <div class="p-6 bg-white/60 border border-slate-100 rounded-[2.5rem] space-y-5 shadow-sm active:scale-[0.98] transition-transform">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <template x-if="row.id">
                                    <div class="space-y-1">
                                        <div class="text-[13px] font-900 text-slate-800 truncate uppercase" x-text="row.name"></div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="px-2 py-0.5 bg-violet-50 text-violet-500 rounded text-[8px] font-black uppercase tracking-widest" x-text="row.pack || '---'"></span>
                                            <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest" x-text="row.item_code"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!row.id">
                                    <div class="relative">
                                        <select x-model="row.id" @change="onProductSelect(index)" class="w-full bg-slate-50/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-violet-500 appearance-none">
                                            <option value="">Select Item...</option>
                                            <template x-for="p in products" :key="p.id">
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
                                       class="w-full bg-white border-2 border-slate-100 rounded-2xl py-3 px-4 text-[13px] font-900 text-slate-800 focus:ring-2 focus:ring-violet-500 shadow-inner text-right pr-12">
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
                <button @click="addRow" class="py-4 border-2 border-dashed border-slate-200 rounded-3xl text-[10px] font-black text-slate-400 uppercase tracking-widest hover:bg-slate-50 transition-all flex items-center justify-center gap-3">
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
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Transaction Logs</h3>
            <div class="flex items-center gap-4">
                <button @click="showFilters = !showFilters" class="text-[10px] font-black flex items-center gap-2 transition-all p-2 rounded-xl" :class="showFilters ? 'bg-violet-100 text-violet-600' : 'text-slate-400 hover:text-slate-600'">
                    <i class="fas fa-sliders text-xs"></i>
                    <span x-text="showFilters ? 'HIDE' : 'FILTER'"></span>
                </button>
                <div class="h-4 w-[1px] bg-slate-200"></div>
                <div class="text-[10px] font-black text-slate-800 tracking-tighter">{{ $indents->total() }} LOGS</div>
            </div>

            <!-- Mobile Filters (Collapsible) -->
            <div x-show="showFilters" x-transition x-cloak class="absolute left-0 right-0 top-full mt-6 glass-premium p-8 rounded-[3rem] shadow-2xl z-50 space-y-6 mx-2 border border-white/80">
                <form action="{{ route('mobile.indents') }}" method="GET" class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Start Date</label>
                            <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full bg-slate-50/50 border-2 border-slate-100/50 rounded-2xl py-3 px-4 text-[11px] font-bold text-slate-800 shadow-inner">
                        </div>
                        <div class="space-y-3">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">End Date</label>
                            <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full bg-slate-50/50 border-2 border-slate-100/50 rounded-2xl py-3 px-4 text-[11px] font-bold text-slate-800 shadow-inner">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Status</label>
                            <select name="status" class="w-full bg-slate-50/50 border-none rounded-2xl py-3.5 px-4 text-[10px] font-bold text-slate-800 appearance-none shadow-sm">
                                <option value="">All Workflow</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="partly completed" {{ request('status') == 'partly completed' ? 'selected' : '' }}>Partial</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Finalized</option>
                            </select>
                        </div>
                        <div class="space-y-3">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] ml-3">Branch</label>
                            <select name="branch_code" class="w-full bg-slate-50/50 border-none rounded-2xl py-3.5 px-4 text-[10px] font-bold text-slate-800 appearance-none shadow-sm">
                                <option value="">All Access</option>
                                @foreach($branches as $branch)
                                <option value="{{ $branch->code }}" {{ request('branch_code') == $branch->code ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
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
            <a href="{{ route('mobile.indents.process', $indent->id) }}" class="group relative glass-premium p-6 rounded-[2.8rem] flex items-center justify-between transition-all active:scale-[0.98] block border border-white/80 hover:shadow-xl hover:shadow-indigo-100/50">
                <div class="absolute top-0 left-0 w-2 h-full {{ $statusColor }}"></div>
                
                <div class="flex items-center gap-5">
                    <div class="w-14 h-14 {{ $iconColor }} rounded-[1.6rem] flex items-center justify-center transition-transform group-hover:rotate-6">
                        <i class="fas fa-file-invoice-dollar text-xl"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <div class="text-[9px] font-black uppercase tracking-[0.15em] {{ str_replace('grad-', 'text-', explode(' ', $statusColor)[0]) }}">
                                {{ $indent->status ?: 'PENDING' }}
                            </div>
                            <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                            <span class="text-[9px] text-slate-400 font-900 tracking-tighter uppercase italic">{{ $indent->items_count }} Items</span>
                        </div>
                        <div class="text-[14px] font-900 text-slate-800 truncate max-w-[160px] leading-tight mt-1.5 uppercase tracking-tighter group-hover:text-indigo-600 transition-colors">{{ $indent->branch_name }}</div>
                        <div class="text-[10px] text-slate-400 font-bold italic mt-1.5 flex items-center gap-2">
                            <i class="far fa-calendar-alt text-[8px]"></i>
                            {{ date('d M, Y', strtotime($indent->indent_date)) }}
                        </div>
                    </div>
                </div>
                
                <div class="text-right flex flex-col items-end gap-3 shrink-0">
                    <div>
                        <div class="text-2xl font-900 text-slate-800 tracking-tighter leading-none">{{ number_format($indent->total_boxes, 0) }}</div>
                        <div class="text-[8px] font-black text-slate-400 uppercase tracking-[0.2em] mt-2">Boxes</div>
                    </div>
                    <div class="flex gap-2">
                        <object><a href="{{ route('mobile.indents.excel', $indent->id) }}" class="w-8 h-8 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 border border-emerald-100 transition-all active:scale-90 hover:grad-emerald hover:text-white"><i class="fas fa-file-excel text-xs"></i></a></object>
                        <object><a href="{{ route('mobile.indents.pdf', $indent->id) }}" class="w-8 h-8 bg-rose-50 rounded-xl flex items-center justify-center text-rose-600 border border-rose-100 transition-all active:scale-90 hover:grad-rose hover:text-white"><i class="fas fa-file-pdf text-xs"></i></a></object>
                    </div>
                </div>
            </a>
            @endforeach
        </div>

        <div class="mt-10 px-4 pb-20 scale-90 origin-top">
            {{ $indents->onEachSide(0)->links() }}
        </div>
    </div>

    <!-- Searchable Multi-Select Modal -->
    <div x-show="showSearch" x-cloak class="fixed inset-0 z-[60] flex flex-col glass-premium backdrop-blur-3xl animate-in fade-in duration-500">
        <div class="p-8 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-900 text-slate-800 tracking-tighter">Bulk Selector</h3>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1 italic">Multi-item rapid selection</p>
                </div>
                <button @click="showSearch = false" class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-500 hover:text-rose-500 transition-all active:scale-90">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="relative group">
                <i class="fas fa-search absolute left-6 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-violet-500 transition-colors"></i>
                <input type="text" x-model="searchQuery" placeholder="Search product name or code..." class="w-full bg-white/60 backdrop-blur-md border-2 border-slate-100 rounded-[1.5rem] py-5 pl-14 pr-6 text-sm font-bold focus:ring-2 focus:ring-violet-500 shadow-xl shadow-indigo-100/30 transition-all">
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-8 space-y-4 pb-32 custom-scrollbar">
            <template x-for="p in filteredProducts" :key="p.id">
                <label class="flex items-center gap-5 p-5 bg-white/50 rounded-[2rem] border-2 border-slate-100 transition-all active:scale-[0.98] cursor-pointer" :class="selectedProducts.includes(p.id) ? 'border-violet-500 bg-violet-50/20 shadow-lg shadow-violet-100/50' : 'hover:border-slate-200'">
                    <input type="checkbox" :value="p.id" x-model="selectedProducts" class="w-7 h-7 rounded-xl text-violet-600 border-slate-200 focus:ring-violet-500 shadow-sm">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <div class="text-[13px] font-900 text-slate-800 truncate uppercase" x-text="p.name"></div>
                            <div class="text-[10px] font-black text-violet-600 bg-violet-50 px-2.5 py-1 rounded-lg shadow-sm border border-violet-100/50" x-text="'Stock: ' + (stockMap[p.id] ? stockMap[p.id].stock_box : '0')"></div>
                        </div>
                        <div class="flex items-center gap-3 mt-1.5">
                            <span class="text-[9px] bg-slate-100/80 text-slate-500 px-2.5 py-1 rounded-lg font-black uppercase tracking-widest shadow-inner" x-text="p.item_code"></span>
                            <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                            <span class="text-[9px] text-slate-400 font-black uppercase tracking-widest italic" x-text="p.pack_name"></span>
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

            <div class="flex-1 overflow-y-auto p-8 space-y-5 custom-scrollbar bg-slate-50/30">
                <template x-for="p in form.products" :key="p.id">
                    <div class="flex items-center justify-between p-4 bg-white rounded-2xl border border-slate-100 shadow-sm group">
                        <div class="flex-1 min-w-0 pr-5">
                            <div class="text-[11px] font-900 text-slate-800 truncate uppercase" x-text="p.name"></div>
                            <div class="text-[8px] text-slate-400 font-black uppercase tracking-widest mt-1" x-text="p.item_code"></div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-[13px] font-900 text-slate-800 tracking-tighter" x-text="p.demand_qty + ' ' + form.global_unit.toUpperCase()"></div>
                            <div class="text-[9px] text-violet-500 font-black italic tracking-tighter mt-0.5" x-text="calculateFinal(p) + ' BOX EQUIV.'"></div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="p-8 bg-white border-t border-slate-100 space-y-6">
                <div class="flex items-center justify-between px-3 p-4 bg-slate-50 rounded-2xl border border-slate-100">
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
        
        get filteredProducts() {
            if (!this.searchQuery) return this.products;
            const q = this.searchQuery.toLowerCase();
            return this.products.filter(p => 
                p.name.toLowerCase().includes(q) || 
                (p.item_code && p.item_code.toLowerCase().includes(q))
            );
        },

        init() {
            // Auto-select if only one branch
            if (this.branches.length === 1) {
                this.form.branch_code = this.branches[0].code;
            }
            this.updateGlobalStocks();
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

                const response = await fetch("{{ route('mobile.indents.store') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify(payload)
                });

                const res = await response.json();
                if (res.success) {
                    window.location.href = res.redirect;
                } else {
                    alert(res.message);
                }
            } catch (e) {
                alert('Connection failure. Try again.');
            } finally {
                this.submitting = false;
            }
        }
    }
}
</script>

<style>
    /* Mobile Pagination Styling */
    .pagination { @apply flex justify-center gap-2 mt-10; }
    .page-item { @apply w-11 h-11 rounded-2xl flex items-center justify-center text-[10px] font-black border-2 border-slate-50 transition-all bg-white text-slate-400 shadow-sm active:scale-90; }
    .page-item.active { @apply bg-violet-600 border-violet-600 text-white shadow-xl shadow-violet-100; }
    .page-item.disabled { @apply opacity-20 grayscale pointer-events-none; }
    .page-link { @apply w-full h-full flex items-center justify-center; }
    
    [x-cloak] { display: none !important; }
    
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.05); border-radius: 10px; }
</style>
@endsection
