@extends('layouts.mobile')

@section('content')
<div class="space-y-6 pb-20" x-data="mobileAdjustmentApp()">
    
    <!-- Premium Header Block -->
    <div class="flex items-center justify-between bg-white/50 backdrop-blur-2xl p-6 rounded-[2.5rem] border border-white/70 shadow-xl shadow-indigo-100/20 relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center justify-between w-full">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-black text-slate-800 tracking-tighter">Adjustments</h2>
                    <span class="px-2 py-0.5 bg-indigo-50 border border-indigo-100 text-indigo-600 rounded-full text-[8px] font-black uppercase tracking-wider">Inventory</span>
                </div>
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Stock Issue (-) & Receipt (+)</p>
            </div>
            
            <div class="flex items-center gap-2">
                @if($unsyncedCount > 0 && Auth::user()->hasFeature('mobile_adjustments', 'erp_bulk_retry'))
                <button @click="bulkRetryErp()" :disabled="retryingBulk" class="w-12 h-12 bg-indigo-600 text-white rounded-2xl flex items-center justify-center text-sm shadow-md shadow-indigo-200 border-2 border-white transition-all active:scale-90 disabled:opacity-50" title="Sync All to ERP">
                    <i class="fas fa-rotate-right" :class="{ 'fa-spin': retryingBulk }"></i>
                </button>
                @endif

                @if(Auth::user()->hasPermission('mobile_adjustments', 'create') && Auth::user()->hasFeature('mobile_adjustments', 'management'))
                <button @click="openModal()" class="w-12 h-12 bg-gradient-to-tr from-emerald-600 to-teal-500 text-white rounded-2xl flex items-center justify-center text-lg shadow-lg shadow-emerald-200 border-2 border-white transition-all active:scale-90" title="New Adjustment">
                    <i class="fas fa-plus"></i>
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Quick KPI Stats Cards -->
    <div class="grid grid-cols-3 gap-3">
        <!-- Receipts (+) -->
        <div class="bg-white/70 backdrop-blur-xl border border-white/80 shadow-lg shadow-indigo-100/20 p-4 rounded-[2rem] text-center">
            <div class="text-[8px] font-black text-emerald-600 uppercase tracking-widest mb-0.5">Receipts (+)</div>
            <div class="text-lg font-black text-emerald-600 tracking-tight">{{ number_format($totalReceipts) }}</div>
            <div class="text-[7px] font-bold text-slate-400 uppercase tracking-tighter">Inward</div>
        </div>

        <!-- Issues (-) -->
        <div class="bg-white/70 backdrop-blur-xl border border-white/80 shadow-lg shadow-indigo-100/20 p-4 rounded-[2rem] text-center">
            <div class="text-[8px] font-black text-rose-500 uppercase tracking-widest mb-0.5">Issues (-)</div>
            <div class="text-lg font-black text-rose-600 tracking-tight">{{ number_format($totalIssues) }}</div>
            <div class="text-[7px] font-bold text-slate-400 uppercase tracking-tighter">Outward</div>
        </div>

        <!-- Total Items -->
        <div class="bg-white/70 backdrop-blur-xl border border-white/80 shadow-lg shadow-indigo-100/20 p-4 rounded-[2rem] text-center">
            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Catalog</div>
            <div class="text-lg font-black text-slate-800 tracking-tight">{{ number_format(count($products)) }}</div>
            <div class="text-[7px] font-bold text-slate-400 uppercase tracking-tighter">Items</div>
        </div>
    </div>

    @if(Auth::user()->hasFeature('mobile_adjustments', 'history'))
    <!-- Search & Filter Bar -->
    <div class="space-y-3">
        <!-- Search Input -->
        <div class="relative">
            <input type="text" x-model="searchQuery" placeholder="Search item, code, reason..." class="w-full bg-white/70 backdrop-blur-xl border border-white/80 shadow-sm rounded-2xl py-3 pl-11 pr-4 text-xs font-bold text-slate-800 placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500 transition-all">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
        </div>

        <!-- Action Quick Filter Pills -->
        <div class="flex gap-2 overflow-x-auto no-scrollbar py-1">
            <button @click="typeFilter = ''" class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase tracking-wider shrink-0 transition-all"
                    :class="typeFilter === '' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-white/70 border border-slate-200/70 text-slate-500'">
                All Logs
            </button>
            <button @click="typeFilter = 'add'" class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase tracking-wider shrink-0 transition-all"
                    :class="typeFilter === 'add' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-100' : 'bg-white/70 border border-slate-200/70 text-slate-500'">
                🟢 Receipts (+)
            </button>
            <button @click="typeFilter = 'deduct'" class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase tracking-wider shrink-0 transition-all"
                    :class="typeFilter === 'deduct' ? 'bg-rose-600 text-white shadow-md shadow-rose-100' : 'bg-white/70 border border-slate-200/70 text-slate-500'">
                🔴 Issues (-)
            </button>
        </div>
    </div>

    <!-- History Logs List -->
    <div class="space-y-4">
        <div class="flex items-center justify-between px-3">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Recent Adjustments</h3>
            <span class="text-[8px] font-black text-slate-400 bg-slate-200/50 px-2 py-0.5 rounded-full" x-text="filteredAdjustments.length + ' Logs'"></span>
        </div>

        <template x-for="adj in filteredAdjustments" :key="adj.id">
            <div class="bg-white/70 backdrop-blur-xl border border-white/80 shadow-lg shadow-indigo-100/20 p-5 rounded-[2.5rem] space-y-3 relative overflow-hidden transition-all">
                <!-- Card Header -->
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3.5">
                        <div class="w-11 h-11 rounded-2xl flex items-center justify-center text-sm shadow-sm border border-white"
                             :class="adj.adjustment_type === 'add' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'">
                            <i :class="adj.adjustment_type === 'add' ? 'fas fa-arrow-down' : 'fas fa-arrow-up'"></i>
                        </div>
                        <div>
                            <div class="text-[11px] font-black text-slate-800 uppercase tracking-tight flex items-center gap-1.5 flex-wrap">
                                <span x-text="adj.product ? adj.product.name : 'Unknown Product'"></span>
                                <template x-if="adj.product && (adj.product.pack_name || adj.product.weight_unit)">
                                    <span class="px-1.5 py-0.2 bg-amber-50 text-amber-700 border border-amber-200/80 rounded text-[7px] font-black" x-text="adj.product.pack_name || adj.product.weight_unit"></span>
                                </template>
                            </div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="text-[8px] font-black text-indigo-500 uppercase tracking-wider" x-text="adj.product ? adj.product.item_code : 'N/A'"></span>
                                <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                                <span class="px-1.5 py-0.2 bg-slate-100 text-slate-500 text-[7px] font-black uppercase rounded" x-text="adj.product && adj.product.type ? adj.product.type.type_name : 'General'"></span>
                                <template x-if="adj.product && adj.product.rm_type">
                                    <span class="px-1.5 py-0.2 bg-purple-50 text-purple-600 text-[7px] font-black uppercase rounded" x-text="adj.product.rm_type"></span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Quantity Badge -->
                    <div class="text-right">
                        <div class="text-sm font-black tracking-tight"
                             :class="adj.adjustment_type === 'add' ? 'text-emerald-600' : 'text-rose-600'"
                             x-text="(adj.adjustment_type === 'add' ? '+' : '-') + parseFloat(adj.quantity).toFixed(2)">
                        </div>
                        <div class="text-[7px] font-black text-slate-400 uppercase tracking-widest" x-text="adj.product ? adj.product.uom : ''"></div>
                    </div>
                </div>

                <!-- Meta row (Branch, User, Time) -->
                <div class="flex items-center justify-between text-[8px] font-bold text-slate-400 pt-1 border-t border-slate-100/60">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-lg font-black uppercase" x-text="adj.branch_name || 'Factory'"></span>
                        <div class="w-1 h-1 bg-slate-300 rounded-full"></div>
                        <span x-text="formatDate(adj.created_at)"></span>
                    </div>

                    <div class="flex items-center gap-1">
                        <i class="fas fa-user text-[7px] text-slate-300"></i>
                        <span x-text="adj.user ? adj.user.name : 'System'"></span>
                    </div>
                </div>

                <!-- Reason quote if present -->
                <template x-if="adj.reason">
                    <div class="p-2.5 bg-slate-50/70 border border-slate-100 rounded-xl text-[9px] font-medium text-slate-600 italic">
                        "<span x-text="adj.reason"></span>"
                    </div>
                </template>

                <!-- ERP & Actions Row -->
                <div class="flex items-center justify-between pt-1">
                    <div>
                        <template x-if="adj.erp_push_status === 'success'">
                            <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[8px] font-black uppercase flex items-center gap-1">
                                <i class="fas fa-check text-[7px]"></i> Synced
                            </span>
                        </template>
                        <template x-if="adj.erp_push_status === 'failed'">
                            <div class="flex items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-600 text-[8px] font-black uppercase flex items-center gap-1">
                                    <i class="fas fa-xmark text-[7px]"></i> Failed
                                </span>
                                @if(Auth::user()->hasFeature('mobile_adjustments', 'erp_push'))
                                <button @click="retrySingleErp(adj.id)" :disabled="retryingId === adj.id" class="px-2 py-0.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-[8px] font-black uppercase tracking-wider shadow-sm flex items-center gap-1 active:scale-95 transition-all">
                                    <i class="fas fa-arrows-rotate text-[7px]" :class="{ 'fa-spin': retryingId === adj.id }"></i>
                                    <span>Retry</span>
                                </button>
                                @endif
                            </div>
                        </template>
                        <template x-if="adj.erp_push_status === 'skipped'">
                            <div class="flex items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 text-[8px] font-black uppercase flex items-center gap-1">
                                    <i class="fas fa-minus text-[7px]"></i> Skipped
                                </span>
                                @if(Auth::user()->hasFeature('mobile_adjustments', 'erp_push'))
                                <button @click="retrySingleErp(adj.id)" :disabled="retryingId === adj.id" class="px-2 py-0.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[8px] font-black uppercase tracking-wider shadow-sm flex items-center gap-1 active:scale-95 transition-all">
                                    <i class="fas fa-paper-plane text-[7px]" :class="{ 'fa-spin': retryingId === adj.id }"></i>
                                    <span>Push</span>
                                </button>
                                @endif
                            </div>
                        </template>
                        <template x-if="adj.erp_push_status === 'pending'">
                            <div class="flex items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[8px] font-black uppercase flex items-center gap-1">
                                    <i class="fas fa-clock text-[7px]"></i> Pending
                                </span>
                                @if(Auth::user()->hasFeature('mobile_adjustments', 'erp_push'))
                                <button @click="retrySingleErp(adj.id)" :disabled="retryingId === adj.id" class="px-2 py-0.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[8px] font-black uppercase tracking-wider shadow-sm flex items-center gap-1 active:scale-95 transition-all">
                                    <i class="fas fa-paper-plane text-[7px]" :class="{ 'fa-spin': retryingId === adj.id }"></i>
                                    <span>Push</span>
                                </button>
                                @endif
                            </div>
                        </template>
                    </div>

                    @if(Auth::user()->role === 'admin' || Auth::user()->hasFeature('mobile_adjustments', 'delete'))
                    <button @click="deleteAdjustment(adj.id)" class="px-2.5 py-1 text-rose-500 hover:bg-rose-50 rounded-lg text-[9px] font-bold uppercase transition-colors flex items-center gap-1">
                        <i class="fas fa-trash-can text-[8px]"></i>
                        <span>Revert</span>
                    </button>
                    @endif
                </div>
            </div>
        </template>

        <div x-show="filteredAdjustments.length === 0" class="py-16 text-center bg-white/40 border border-white rounded-[2.5rem] text-slate-400 italic text-[10px] font-bold uppercase tracking-widest">
            No stock adjustments found.
        </div>
    </div>
    @endif

    <!-- Create Adjustment Bottom Sheet Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex flex-col justify-end bg-black/60 backdrop-blur-sm transition-all"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="bg-white rounded-t-[3rem] shadow-2xl max-h-[90vh] flex flex-col overflow-hidden w-full"
             @click.outside="showModal = false"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full">
            
            <!-- Sheet Header -->
            <div class="p-6 text-white relative flex-shrink-0 transition-colors duration-300"
                 :class="form.adjustment_type === 'add' ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : 'bg-gradient-to-r from-rose-600 to-pink-600'">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-lg text-white">
                        <i :class="form.adjustment_type === 'add' ? 'fas fa-arrow-down' : 'fas fa-arrow-up'"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black uppercase tracking-tight leading-none" x-text="form.adjustment_type === 'add' ? 'Stock Receipt (+)' : 'Stock Issue (-)'"></h3>
                        <p class="text-white/80 text-[8px] font-bold uppercase tracking-widest mt-1" x-text="form.adjustment_type === 'add' ? 'Inward Inventory Addition' : 'Outward Inventory Deduction'"></p>
                    </div>
                </div>
                <button @click="showModal = false" class="absolute top-6 right-6 w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Sheet Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-5">
                
                <!-- Action Switcher -->
                <div class="grid grid-cols-2 gap-2.5 p-1.5 bg-slate-100 rounded-2xl">
                    @if(Auth::user()->hasFeature('mobile_adjustments', 'create_receipt'))
                    <button type="button" @click="form.adjustment_type = 'add'" class="py-3 rounded-xl font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2"
                            :class="form.adjustment_type === 'add' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200' : 'text-slate-600'">
                        <i class="fas fa-arrow-down text-[10px]"></i>
                        <span>Receipt (+)</span>
                    </button>
                    @endif

                    @if(Auth::user()->hasFeature('mobile_adjustments', 'create_issue'))
                    <button type="button" @click="form.adjustment_type = 'deduct'" class="py-3 rounded-xl font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2"
                            :class="form.adjustment_type === 'deduct' ? 'bg-rose-600 text-white shadow-md shadow-rose-200' : 'text-slate-600'">
                        <i class="fas fa-arrow-up text-[10px]"></i>
                        <span>Issue (-)</span>
                    </button>
                    @endif
                </div>

                @if($isBranchLocked && $lockedBranch)
                <!-- Locked Branch Location -->
                <div class="space-y-1.5">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block ml-1 flex items-center justify-between">
                        <span>Branch / Location</span>
                        <span class="text-amber-600 bg-amber-50 border border-amber-200 text-[8px] font-black px-1.5 py-0.5 rounded flex items-center gap-1">
                            <i class="fas fa-lock text-[7px]"></i> Locked
                        </span>
                    </label>
                    <input type="text" readonly value="{{ $lockedBranch->name }} ({{ $lockedBranch->code }})" class="w-full bg-slate-100 border border-slate-200 rounded-2xl py-3 px-4 text-xs font-bold text-slate-600 cursor-not-allowed">
                </div>
                @elseif(Auth::user()->hasFeature('mobile_adjustments', 'branch_select'))
                <!-- Branch Selector -->
                <div class="space-y-1.5">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block ml-1">Branch / Location</label>
                    <select x-model="form.branch_code" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3 px-4 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                        @foreach($branches as $b)
                        <option value="{{ $b->code }}">{{ $b->name }} ({{ $b->code }})</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <!-- Product Selection Button (Opens Drawer) -->
                <div class="space-y-1.5">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block ml-1">Select Product to Adjust</label>
                    <button type="button" @click="showProductPicker = true" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3.5 px-4 text-left flex items-center justify-between active:bg-slate-100 transition-all">
                        <div class="min-w-0 pr-2">
                            <div class="text-xs font-black text-slate-800 truncate flex items-center gap-1.5">
                                <span x-text="selectedProduct ? selectedProduct.name : 'Tap to select product...'"></span>
                                <template x-if="selectedProduct && (selectedProduct.pack_name || selectedProduct.weight_unit)">
                                    <span class="px-1.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded text-[8px] font-black shrink-0" x-text="selectedProduct.pack_name || selectedProduct.weight_unit"></span>
                                </template>
                            </div>
                            <div class="text-[8px] font-bold text-slate-400 uppercase mt-0.5" x-text="selectedProduct ? (selectedProduct.item_code + ' • ' + (selectedProduct.uom || '')) : 'All types supported (FG, RM, PM)'"></div>
                        </div>
                        <i class="fas fa-chevron-right text-slate-400 text-xs shrink-0"></i>
                    </button>
                </div>

                <!-- Selected Item Summary & Live Stock Calculation -->
                <div x-show="selectedProduct" class="p-4 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Stock Impact</span>
                        <span class="text-xs font-black text-slate-800" x-text="'Current: ' + (selectedProduct ? parseFloat(selectedProduct.current_stock).toFixed(2) + ' ' + (selectedProduct.uom || '') : '')"></span>
                    </div>

                    <div class="grid grid-cols-3 gap-2 p-2.5 bg-white rounded-xl border border-slate-200 text-center">
                        <div>
                            <div class="text-[7px] font-black text-slate-400 uppercase">Available</div>
                            <div class="text-[11px] font-black text-slate-700" x-text="selectedProduct ? parseFloat(selectedProduct.current_stock).toFixed(2) : '0.00'"></div>
                        </div>
                        <div>
                            <div class="text-[7px] font-black uppercase" :class="form.adjustment_type === 'add' ? 'text-emerald-600' : 'text-rose-600'" x-text="form.adjustment_type === 'add' ? 'Receipt (+)' : 'Issue (-)'"></div>
                            <div class="text-[11px] font-black" :class="form.adjustment_type === 'add' ? 'text-emerald-600' : 'text-rose-600'" x-text="(form.quantity ? (form.adjustment_type === 'add' ? '+' : '-') + parseFloat(form.quantity).toFixed(2) : '0.00')"></div>
                        </div>
                        <div>
                            <div class="text-[7px] font-black text-slate-400 uppercase">New Stock</div>
                            <div class="text-[11px] font-black text-indigo-600" x-text="calculatedNewStock"></div>
                        </div>
                    </div>

                    <!-- Insufficient Warning -->
                    <div x-show="form.adjustment_type === 'deduct' && selectedProduct && parseFloat(form.quantity || 0) > parseFloat(selectedProduct.current_stock)" class="p-2 bg-rose-50 border border-rose-200 rounded-xl text-rose-600 text-[9px] font-bold flex items-center gap-1.5">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>Quantity exceeds current stock!</span>
                    </div>
                </div>

                <!-- Quantity -->
                <div class="space-y-1.5">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block ml-1">Quantity</label>
                    <div class="relative">
                        <input type="number" step="0.0001" min="0.0001" x-model="form.quantity" placeholder="0.000" class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-3.5 pl-4 pr-16 text-sm font-black text-slate-800 placeholder:text-slate-300 focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400 uppercase" x-text="selectedProduct ? selectedProduct.uom : 'Units'"></span>
                    </div>
                </div>

                @if(Auth::user()->hasFeature('mobile_adjustments', 'reason_select'))
                <!-- Reason & Chips -->
                <div class="space-y-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block ml-1">Reason / Note</label>
                    <div class="flex gap-1.5 overflow-x-auto no-scrollbar py-1">
                        <button type="button" @click="form.reason = 'Physical Count Diff'" class="px-3 py-1 bg-slate-100 rounded-lg text-[9px] font-bold text-slate-600 shrink-0">Physical Count</button>
                        <button type="button" @click="form.reason = 'Damaged / Leakage'" class="px-3 py-1 bg-slate-100 rounded-lg text-[9px] font-bold text-slate-600 shrink-0">Damaged</button>
                        <button type="button" @click="form.reason = 'Testing / QC Sample'" class="px-3 py-1 bg-slate-100 rounded-lg text-[9px] font-bold text-slate-600 shrink-0">QC Sample</button>
                        <button type="button" @click="form.reason = 'Opening Balance'" class="px-3 py-1 bg-slate-100 rounded-lg text-[9px] font-bold text-slate-600 shrink-0">Opening Stock</button>
                        <button type="button" @click="form.reason = 'Internal Transfer'" class="px-3 py-1 bg-slate-100 rounded-lg text-[9px] font-bold text-slate-600 shrink-0">Transfer</button>
                    </div>
                    <textarea x-model="form.reason" rows="2" placeholder="Why are you adjusting this stock?" class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-3.5 text-xs font-bold text-slate-700 placeholder:text-slate-300 focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all"></textarea>
                </div>
                @endif

                @if(Auth::user()->hasFeature('mobile_adjustments', 'erp_push'))
                <!-- ERP Sync Toggle -->
                <div class="flex items-center justify-between p-3.5 bg-slate-50 rounded-2xl border border-slate-100">
                    <div>
                        <div class="text-[11px] font-black text-slate-800 uppercase">Sync to ERP</div>
                        <p class="text-[8px] text-slate-400 font-bold uppercase">Post voucher to ERP register</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" x-model="form.sync_erp" class="sr-only peer">
                        <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>
                @endif

            </div>

            <!-- Sheet Footer -->
            <div class="p-5 border-t bg-slate-50/70 flex gap-3 flex-shrink-0">
                <button type="button" @click="showModal = false" class="flex-1 py-3.5 bg-white border border-slate-200 text-slate-600 rounded-2xl font-black uppercase text-xs active:scale-[0.97] transition-all">
                    Cancel
                </button>
                <button type="button" @click="submitAdjustment()" :disabled="submitting || !form.product_id || !form.quantity" class="flex-1 py-3.5 text-white rounded-2xl font-black uppercase text-xs shadow-lg transition-all flex items-center justify-center gap-2 active:scale-[0.97] disabled:opacity-50"
                        :class="form.adjustment_type === 'add' ? 'bg-emerald-600 shadow-emerald-200' : 'bg-rose-600 shadow-rose-200'">
                    <i class="fas fa-circle-notch fa-spin text-xs" x-show="submitting"></i>
                    <span x-text="submitting ? 'Saving...' : (form.adjustment_type === 'add' ? 'Save Receipt (+)' : 'Save Issue (-)')"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Product Picker Bottom Drawer -->
    <div x-show="showProductPicker" x-cloak class="fixed inset-0 z-50 flex flex-col justify-end bg-black/60 backdrop-blur-sm transition-all"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="bg-white rounded-t-[3rem] shadow-2xl max-h-[85vh] flex flex-col overflow-hidden w-full"
             @click.outside="showProductPicker = false">
            
            <div class="bg-indigo-600 p-6 text-white relative flex-shrink-0">
                <h3 class="text-lg font-black uppercase tracking-tight leading-none">Select Product</h3>
                <p class="text-indigo-100 text-[8px] font-bold uppercase tracking-widest mt-1">FG, Raw Materials, Packing Materials</p>
                <button @click="showProductPicker = false" class="absolute top-6 right-6 w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Search and Filter in Picker -->
            <div class="p-4 border-b border-slate-100 bg-slate-50 space-y-2 flex-shrink-0">
                <div class="relative">
                    <input type="text" x-model="pickerSearch" placeholder="Search product name or code..." class="w-full bg-white border border-slate-200 rounded-xl py-2.5 pl-10 pr-4 text-xs font-bold text-slate-800 placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                </div>

                @if(Auth::user()->hasFeature('mobile_adjustments', 'type_filter'))
                <div class="flex gap-1.5 overflow-x-auto no-scrollbar py-1">
                    <button @click="pickerTypeFilter = ''" class="px-3 py-1 rounded-full text-[8px] font-black uppercase tracking-wider shrink-0"
                            :class="pickerTypeFilter === '' ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-500'">
                        All Types
                    </button>
                    <template x-for="t in productTypes" :key="t.id">
                        <button @click="pickerTypeFilter = t.id" class="px-3 py-1 rounded-full text-[8px] font-black uppercase tracking-wider shrink-0"
                                :class="pickerTypeFilter == t.id ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-500'" x-text="t.type_name">
                        </button>
                    </template>
                </div>
                @endif
            </div>

            <!-- Products List in Picker -->
            <div class="flex-1 overflow-y-auto p-4 space-y-2">
                <template x-for="p in filteredProductsForPicker" :key="p.id">
                    <div @click="selectProduct(p)" class="p-3.5 bg-white border border-slate-100 rounded-2xl hover:border-indigo-100 active:bg-indigo-50/20 transition-all flex items-center justify-between cursor-pointer">
                        <div class="min-w-0 pr-3">
                            <div class="text-[11px] font-black text-slate-800 uppercase tracking-tight truncate flex items-center gap-1.5">
                                <span x-text="p.name"></span>
                                <template x-if="p.pack_name || p.weight_unit">
                                    <span class="px-1.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-200/80 rounded text-[8px] font-black shrink-0" x-text="p.pack_name || p.weight_unit"></span>
                                </template>
                            </div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="text-[8px] font-black text-indigo-500 uppercase tracking-wider" x-text="p.item_code"></span>
                                <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                                <span class="text-[8px] font-bold text-slate-400 uppercase" x-text="p.type ? p.type.type_name : 'General'"></span>
                                <template x-if="p.rm_type">
                                    <span class="text-[7px] font-black text-purple-600 uppercase" x-text="'(' + p.rm_type + ')'"></span>
                                </template>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-xs font-black text-slate-800" x-text="parseFloat(p.current_stock).toFixed(2)"></div>
                            <div class="text-[7px] font-bold text-slate-400 uppercase" x-text="p.uom"></div>
                        </div>
                    </div>
                </template>

                <div x-show="filteredProductsForPicker.length === 0" class="text-center py-10 text-slate-400 italic text-[10px] font-bold uppercase tracking-widest">
                    No products matching search criteria.
                </div>
            </div>

        </div>
    </div>

</div>

<script>
function mobileAdjustmentApp() {
    return {
        adjustments: @json($adjustments),
        products: @json($products),
        productTypes: @json($productTypes),
        branches: @json($branches),
        isBranchLocked: {{ ($isBranchLocked ?? false) ? 'true' : 'false' }},

        searchQuery: '',
        typeFilter: '',
        
        showModal: false,
        showProductPicker: false,
        pickerSearch: '',
        pickerTypeFilter: '',

        selectedProduct: null,
        submitting: false,
        retryingBulk: false,
        retryingId: null,

        form: {
            product_id: '',
            adjustment_type: '{{ Auth::user()->hasFeature("mobile_adjustments", "create_receipt") ? "add" : "deduct" }}',
            quantity: '',
            branch_code: '{{ ($isBranchLocked ?? false) && ($lockedBranch ?? null) ? $lockedBranch->code : (count($branches) > 0 ? $branches[0]->code : "2") }}',
            reason: '',
            sync_erp: true
        },

        get filteredAdjustments() {
            return this.adjustments.filter(adj => {
                if (this.searchQuery) {
                    const q = this.searchQuery.toLowerCase();
                    const pName = (adj.product ? adj.product.name : '').toLowerCase();
                    const pCode = (adj.product ? (adj.product.item_code || '') : '').toLowerCase();
                    const pPack = (adj.product ? (adj.product.pack_name || adj.product.weight_unit || '') : '').toLowerCase();
                    const reason = (adj.reason || '').toLowerCase();
                    if (!pName.includes(q) && !pCode.includes(q) && !pPack.includes(q) && !reason.includes(q)) {
                        return false;
                    }
                }

                if (this.typeFilter && adj.adjustment_type !== this.typeFilter) {
                    return false;
                }

                return true;
            });
        },

        get filteredProductsForPicker() {
            return this.products.filter(p => {
                if (this.pickerTypeFilter && String(p.product_type_id) !== String(this.pickerTypeFilter)) {
                    return false;
                }
                if (this.pickerSearch) {
                    const q = this.pickerSearch.toLowerCase();
                    const name = (p.name || '').toLowerCase();
                    const code = (p.item_code || '').toLowerCase();
                    const pack = (p.pack_name || p.weight_unit || '').toLowerCase();
                    if (!name.includes(q) && !code.includes(q) && !pack.includes(q)) {
                        return false;
                    }
                }
                return true;
            });
        },

        get calculatedNewStock() {
            if (!this.selectedProduct) return '0.00';
            const cur = parseFloat(this.selectedProduct.current_stock || 0);
            const qty = parseFloat(this.form.quantity || 0);
            const result = this.form.adjustment_type === 'add' ? (cur + qty) : (cur - qty);
            return result.toFixed(2);
        },

        openModal() {
            this.form.product_id = '';
            this.form.quantity = '';
            this.form.reason = '';
            this.selectedProduct = null;
            this.showModal = true;
        },

        selectProduct(p) {
            this.selectedProduct = p;
            this.form.product_id = p.id;
            this.showProductPicker = false;
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        },

        async submitAdjustment() {
            if (!this.form.product_id || !this.form.quantity) {
                alert('Please select a product and enter quantity.');
                return;
            }

            if (this.form.adjustment_type === 'deduct' && this.selectedProduct) {
                if (parseFloat(this.form.quantity) > parseFloat(this.selectedProduct.current_stock)) {
                    if (!confirm('Warning: Issue quantity exceeds available stock! Do you want to proceed?')) {
                        return;
                    }
                }
            }

            this.submitting = true;
            try {
                const response = await fetch("{{ route('mobile.adjustments.store') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(this.form)
                });
                const res = await response.json();
                if (res.success) {
                    alert(res.message);
                    window.location.reload();
                } else {
                    alert(res.message || 'Failed to record adjustment.');
                }
            } catch (e) {
                alert('An error occurred while saving the adjustment.');
            } finally {
                this.submitting = false;
            }
        },

        async retrySingleErp(id) {
            if (!id) return;
            this.retryingId = id;
            try {
                const res = await fetch(`{{ url('mobile/adjustments') }}/${id}/retry-erp`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message || 'ERP sync succeeded!');
                    window.location.reload();
                } else {
                    alert(data.message || 'ERP sync failed.');
                }
            } catch (e) {
                alert('Network error while syncing to ERP.');
            } finally {
                this.retryingId = null;
            }
        },

        async bulkRetryErp() {
            this.retryingBulk = true;
            try {
                const res = await fetch("{{ route('mobile.adjustments.bulk-retry-erp') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message || 'Bulk sync complete!');
                    window.location.reload();
                } else {
                    alert(data.message || 'Bulk sync finished with errors.');
                }
            } catch (e) {
                alert('Network error during bulk ERP sync.');
            } finally {
                this.retryingBulk = false;
            }
        },

        async deleteAdjustment(id) {
            if (!confirm('Are you sure you want to revert/delete this adjustment? This will reverse the stock change.')) {
                return;
            }

            try {
                const res = await fetch(`{{ url('mobile/adjustments') }}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message || 'Adjustment reverted successfully.');
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to revert adjustment.');
                }
            } catch (e) {
                alert('Network error while reverting adjustment.');
            }
        }
    }
}
</script>
@endsection
