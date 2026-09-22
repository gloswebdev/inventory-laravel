@extends('layouts.app')

@section('content')
<div x-data="adjustmentManager()" class="min-h-screen bg-[#f8fafc] py-4">
    <div class="max-w-[98%] mx-auto space-y-6">
        
        <!-- Premium Header Banner -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-80 h-80 bg-indigo-50/40 rounded-full blur-3xl -mr-32 -mt-32"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-emerald-50/20 rounded-full blur-3xl -ml-24 -mb-24"></div>

            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6 z-10">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-gradient-to-tr from-indigo-600 to-violet-500 text-white rounded-2xl shadow-lg shadow-indigo-100 flex items-center justify-center text-2xl">
                        <i class="fas fa-scale-balanced"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-black text-slate-800 tracking-tight uppercase">Stock Adjustments</h1>
                            <span class="px-2.5 py-0.5 bg-indigo-50 border border-indigo-100 text-indigo-600 rounded-full text-[9px] font-black uppercase tracking-wider">Inventory Control</span>
                        </div>
                        <p class="text-xs text-slate-400 font-medium mt-0.5">Record manual stock receipts (+ inward) & stock issues (- outward) for any product type.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    @if($unsyncedCount > 0 && Auth::user()->hasFeature('adjustments', 'erp_bulk_retry'))
                    <button type="button" @click="bulkRetryErp()" :disabled="bulkRetrying" class="px-4 py-3 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-black uppercase tracking-wider rounded-xl transition flex items-center gap-2 shadow-xs active:scale-95 disabled:opacity-50">
                        <i class="fas fa-rotate-right" :class="bulkRetrying ? 'fa-spin' : ''"></i>
                        <span>Sync to ERP ({{ $unsyncedCount }})</span>
                    </button>
                    @endif

                    @if(Auth::user()->hasPermission('adjustments', 'create') && Auth::user()->hasFeature('adjustments', 'management'))
                    <button @click="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3.5 rounded-xl font-bold text-xs uppercase tracking-wider transition-all duration-200 shadow-md shadow-indigo-100 flex items-center gap-2 transform hover:-translate-y-0.5 active:translate-y-0">
                        <i class="fas fa-plus"></i> New Adjustment
                    </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- 4 KPI Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Adjustments -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xs flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Adjustments</div>
                    <div class="text-2xl font-black text-slate-800 tracking-tight mt-1">{{ number_format($totalAdjustments) }}</div>
                    <div class="text-[9px] font-bold text-slate-400 mt-0.5">Recorded Transactions</div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 text-indigo-500 flex items-center justify-center text-xl">
                    <i class="fas fa-list-check"></i>
                </div>
            </div>

            <!-- Stock Receipts (+) -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xs flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Stock Receipts (+)</div>
                    <div class="text-2xl font-black text-emerald-600 tracking-tight mt-1">{{ number_format($totalReceipts) }}</div>
                    <div class="text-[9px] font-bold text-slate-400 mt-0.5">Inward Additions</div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center text-xl">
                    <i class="fas fa-arrow-down-long"></i>
                </div>
            </div>

            <!-- Stock Issues (-) -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xs flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-black text-rose-500 uppercase tracking-widest">Stock Issues (-)</div>
                    <div class="text-2xl font-black text-rose-600 tracking-tight mt-1">{{ number_format($totalIssues) }}</div>
                    <div class="text-[9px] font-bold text-slate-400 mt-0.5">Outward Deductions</div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center text-xl">
                    <i class="fas fa-arrow-up-long"></i>
                </div>
            </div>

            <!-- Available Catalog Items -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xs flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Permitted Items</div>
                    <div class="text-2xl font-black text-slate-800 tracking-tight mt-1">{{ number_format(count($products)) }}</div>
                    <div class="text-[9px] font-bold text-slate-400 mt-0.5">Across Permitted Types</div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-slate-50 border border-slate-200 text-slate-500 flex items-center justify-center text-xl">
                    <i class="fas fa-boxes-stacked"></i>
                </div>
            </div>
        </div>

        @if(Auth::user()->hasFeature('adjustments', 'history'))
        <!-- History & Filter Section -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-sliders text-indigo-500"></i> Filters & Search
                </h3>

                <!-- Active filters counters -->
                <div class="text-[11px] font-bold text-slate-400 flex items-center gap-3">
                    <span>Showing <strong class="text-slate-700" x-text="filteredAdjustments.length"></strong> of <strong class="text-slate-700">{{ count($adjustments) }}</strong> logs</span>
                </div>
            </div>

            <!-- Filters Layout -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <!-- Search Input -->
                <div class="relative">
                    <input type="text" x-model="searchQuery" placeholder="Search product, code, reason, user..." class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-10 pr-4 text-xs font-semibold text-slate-700 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                </div>

                <!-- Action Type Filter -->
                <div>
                    <select x-model="typeFilter" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-xs font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                        <option value="">All Actions (Receipt & Issue)</option>
                        <option value="add">🟢 Stock Receipts (+)</option>
                        <option value="deduct">🔴 Stock Issues (-)</option>
                    </select>
                </div>

                @if(Auth::user()->hasFeature('adjustments', 'type_filter'))
                <!-- Product Type Filter -->
                <div>
                    <select x-model="productTypeFilter" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-xs font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                        <option value="">All Product Types</option>
                        @foreach($productTypes as $pt)
                        <option value="{{ $pt->id }}">{{ $pt->type_name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                @if($isBranchLocked && $lockedBranch)
                <!-- Locked Branch Indicator -->
                <div class="flex items-center gap-2 bg-amber-50/90 border border-amber-200 rounded-xl py-2.5 px-4 text-xs font-bold text-amber-800">
                    <i class="fas fa-lock text-amber-500 text-[11px]"></i>
                    <span>Branch: {{ $lockedBranch->name }} ({{ $lockedBranch->code }})</span>
                </div>
                @elseif(Auth::user()->hasFeature('adjustments', 'branch_select'))
                <!-- Branch Filter -->
                <div>
                    <select x-model="branchFilter" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-xs font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                        <option value="">All Branches / Sites</option>
                        @foreach($branches as $b)
                        <option value="{{ $b->code }}">{{ $b->name }} ({{ $b->code }})</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
        </div>

        <!-- History Table Card -->
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <th class="px-6 py-4">Voucher / Date</th>
                            <th class="px-6 py-4">Item & Code</th>
                            <th class="px-6 py-4">Category / Type</th>
                            <th class="px-6 py-4">Branch</th>
                            <th class="px-6 py-4 text-center">Action</th>
                            <th class="px-6 py-4 text-right">Adjusted Qty</th>
                            <th class="px-6 py-4">Reason / Remarks</th>
                            <th class="px-6 py-4">Recorded By</th>
                            <th class="px-6 py-4 text-center">ERP Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-bold text-slate-600">
                        <template x-for="adj in paginatedAdjustments" :key="adj.id">
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <!-- Voucher / Date -->
                                <td class="px-6 py-4">
                                    <div class="font-black text-slate-800" x-text="'#ADJ-' + String(adj.id).padStart(5, '0')"></div>
                                    <div class="text-[10px] text-slate-400 font-medium mt-0.5" x-text="formatDate(adj.created_at)"></div>
                                </td>

                                <!-- Item & Code -->
                                <td class="px-6 py-4">
                                    <div class="font-black text-slate-800 flex items-center gap-1.5 flex-wrap">
                                        <span x-text="adj.product ? adj.product.name : 'Unknown Product'"></span>
                                        <template x-if="adj.product && (adj.product.pack_name || adj.product.weight_unit)">
                                            <span class="px-1.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-200/80 rounded text-[8px] font-black" x-text="adj.product.pack_name || adj.product.weight_unit"></span>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-[9px] font-black text-indigo-500 uppercase tracking-wider" x-text="adj.product ? adj.product.item_code : 'N/A'"></span>
                                        <template x-if="adj.product && adj.product.uom">
                                            <span class="text-[9px] text-slate-400 font-bold" x-text="'• ' + adj.product.uom"></span>
                                        </template>
                                    </div>
                                </td>

                                <!-- Category / Type -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[9px] font-black uppercase" x-text="adj.product && adj.product.type ? adj.product.type.type_name : 'General'"></span>
                                        <template x-if="adj.product && adj.product.rm_type">
                                            <span class="px-1.5 py-0.2 bg-purple-50 text-purple-600 rounded text-[8px] font-black uppercase" x-text="adj.product.rm_type"></span>
                                        </template>
                                    </div>
                                </td>

                                <!-- Branch -->
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 bg-indigo-50/60 border border-indigo-100 text-indigo-700 rounded-lg text-[9px] font-black uppercase" x-text="adj.branch_name || 'Factory'"></span>
                                </td>

                                <!-- Action (Receipt vs Issue) -->
                                <td class="px-6 py-4 text-center">
                                    <template x-if="adj.adjustment_type === 'add'">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-700 text-[9px] font-black uppercase tracking-wider">
                                            <i class="fas fa-arrow-down text-[8px]"></i> Receipt (+)
                                        </span>
                                    </template>
                                    <template x-if="adj.adjustment_type === 'deduct'">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 border border-rose-100 text-rose-600 text-[9px] font-black uppercase tracking-wider">
                                            <i class="fas fa-arrow-up text-[8px]"></i> Issue (-)
                                        </span>
                                    </template>
                                </td>

                                <!-- Adjusted Qty -->
                                <td class="px-6 py-4 text-right">
                                    <div class="text-sm font-black" :class="adj.adjustment_type === 'add' ? 'text-emerald-600' : 'text-rose-600'" x-text="(adj.adjustment_type === 'add' ? '+' : '-') + parseFloat(adj.quantity).toFixed(3)"></div>
                                    <div class="text-[9px] text-slate-400 font-bold uppercase" x-text="adj.product ? adj.product.uom : ''"></div>
                                </td>

                                <!-- Reason -->
                                <td class="px-6 py-4 max-w-xs">
                                    <div class="text-[10px] text-slate-600 font-semibold truncate" :title="adj.reason" x-text="adj.reason || 'No remarks provided'"></div>
                                </td>

                                <!-- Recorded By -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-[9px] font-black uppercase" x-text="adj.user ? adj.user.name.charAt(0) : 'S'"></div>
                                        <span class="text-xs font-bold text-slate-700" x-text="adj.user ? adj.user.name : 'System'"></span>
                                    </div>
                                </td>

                                <!-- ERP Status -->
                                <td class="px-6 py-4 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <template x-if="adj.erp_push_status === 'success'">
                                            <span class="inline-flex items-center gap-1 bg-emerald-50 border border-emerald-100 text-emerald-700 font-black px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider">
                                                <i class="fas fa-check-circle"></i> Synced
                                            </span>
                                        </template>
                                        <template x-if="adj.erp_push_status === 'failed'">
                                            <div class="inline-flex items-center gap-1.5">
                                                <span class="inline-flex items-center gap-1 bg-rose-50 border border-rose-100 text-rose-600 font-black px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider">
                                                    <i class="fas fa-circle-exclamation"></i> Failed
                                                </span>
                                                @if(Auth::user()->hasFeature('adjustments', 'erp_push'))
                                                <button type="button" @click="retrySingleErp(adj.id)" :disabled="retryingId === adj.id" class="inline-flex items-center gap-1 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider transition active:scale-95 shadow-xs">
                                                    <i class="fas fa-rotate-right" :class="retryingId === adj.id ? 'fa-spin' : ''"></i> Retry
                                                </button>
                                                @endif
                                            </div>
                                        </template>
                                        <template x-if="adj.erp_push_status === 'skipped'">
                                            <div class="inline-flex items-center gap-1.5">
                                                <span class="inline-flex items-center gap-1 bg-slate-50 border border-slate-100 text-slate-400 font-black px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider">
                                                    <i class="fas fa-minus-circle"></i> Skipped
                                                </span>
                                                @if(Auth::user()->hasFeature('adjustments', 'erp_push'))
                                                <button type="button" @click="retrySingleErp(adj.id)" :disabled="retryingId === adj.id" class="inline-flex items-center gap-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider transition active:scale-95 shadow-xs">
                                                    <i class="fas fa-paper-plane" :class="retryingId === adj.id ? 'fa-spin' : ''"></i> Push
                                                </button>
                                                @endif
                                            </div>
                                        </template>
                                        <template x-if="adj.erp_push_status === 'pending'">
                                            <div class="inline-flex items-center gap-1.5">
                                                <span class="inline-flex items-center gap-1 bg-amber-50 border border-amber-100 text-amber-600 font-black px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider">
                                                    <i class="fas fa-clock"></i> Pending
                                                </span>
                                                @if(Auth::user()->hasFeature('adjustments', 'erp_push'))
                                                <button type="button" @click="retrySingleErp(adj.id)" :disabled="retryingId === adj.id" class="inline-flex items-center gap-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider transition active:scale-95 shadow-xs">
                                                    <i class="fas fa-paper-plane" :class="retryingId === adj.id ? 'fa-spin' : ''"></i> Push
                                                </button>
                                                @endif
                                            </div>
                                        </template>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="viewDetails(adj)" class="p-2 hover:bg-slate-100 text-slate-400 hover:text-indigo-600 rounded-lg transition-colors" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        @if(Auth::user()->role === 'admin' || Auth::user()->hasFeature('adjustments', 'delete'))
                                        <button type="button" @click="revertAdjustment(adj.id)" class="p-2 hover:bg-rose-50 text-slate-300 hover:text-rose-600 rounded-lg transition-colors" title="Revert / Delete Adjustment">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="filteredAdjustments.length === 0">
                            <td colspan="10" class="py-16 text-center text-slate-400 font-bold uppercase tracking-widest text-xs italic">
                                No stock adjustment logs matching your search and filter criteria.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>

    <!-- New Adjustment Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-all"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 max-w-2xl w-full overflow-hidden flex flex-col max-h-[90vh]"
             @click.outside="showModal = false"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="scale-100 opacity-100"
             x-transition:leave-end="scale-95 opacity-0">
            
            <!-- Modal Header -->
            <div class="p-6 text-white relative transition-colors duration-300"
                 :class="form.adjustment_type === 'add' ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : 'bg-gradient-to-r from-rose-600 to-pink-600'">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center text-xl text-white">
                        <i :class="form.adjustment_type === 'add' ? 'fas fa-arrow-down' : 'fas fa-arrow-up'"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-black uppercase tracking-tight" x-text="form.adjustment_type === 'add' ? 'Record Stock Receipt (+)' : 'Record Stock Issue (-)'"></h2>
                        <p class="text-xs text-white/80 font-medium" x-text="form.adjustment_type === 'add' ? 'Add inventory into warehouse / godown' : 'Deduct inventory from warehouse / godown'"></p>
                    </div>
                </div>
                <button @click="showModal = false" class="absolute top-6 right-6 w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto space-y-6 flex-1">
                
                <!-- Action Type Switcher -->
                <div class="grid grid-cols-2 gap-3 p-1.5 bg-slate-100 rounded-2xl">
                    @if(Auth::user()->hasFeature('adjustments', 'create_receipt'))
                    <button type="button" @click="form.adjustment_type = 'add'" class="py-3 rounded-xl font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2"
                            :class="form.adjustment_type === 'add' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200' : 'text-slate-600 hover:bg-white/60'">
                        <i class="fas fa-arrow-down text-xs"></i>
                        <span>Stock Receipt (+)</span>
                    </button>
                    @endif

                    @if(Auth::user()->hasFeature('adjustments', 'create_issue'))
                    <button type="button" @click="form.adjustment_type = 'deduct'" class="py-3 rounded-xl font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2"
                            :class="form.adjustment_type === 'deduct' ? 'bg-rose-600 text-white shadow-md shadow-rose-200' : 'text-slate-600 hover:bg-white/60'">
                        <i class="fas fa-arrow-up text-xs"></i>
                        <span>Stock Issue (-)</span>
                    </button>
                    @endif
                </div>

                <!-- Branch & Product Type Filter Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if($isBranchLocked && $lockedBranch)
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center justify-between">
                            <span>Branch / Location</span>
                            <span class="text-amber-600 bg-amber-50 border border-amber-200 text-[8px] font-black px-1.5 py-0.5 rounded flex items-center gap-1">
                                <i class="fas fa-lock text-[7px]"></i> Locked
                            </span>
                        </label>
                        <div class="relative">
                            <input type="text" readonly value="{{ $lockedBranch->name }} ({{ $lockedBranch->code }})" class="w-full bg-slate-100 border border-slate-200 rounded-xl py-3 px-4 text-xs font-bold text-slate-600 cursor-not-allowed">
                        </div>
                    </div>
                    @elseif(Auth::user()->hasFeature('adjustments', 'branch_select'))
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Branch / Location</label>
                        <select x-model="form.branch_code" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                            @foreach($branches as $b)
                            <option value="{{ $b->code }}">{{ $b->name }} ({{ $b->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    @if(Auth::user()->hasFeature('adjustments', 'type_filter'))
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Product Category / Type Filter</label>
                        <select x-model="modalTypeFilter" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                            <option value="">All Categories / Types</option>
                            @foreach($productTypes as $pt)
                            <option value="{{ $pt->id }}">{{ $pt->type_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>

                <!-- Product Selection -->
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Select Item to Adjust</label>
                    <select x-model="form.product_id" @change="onProductSelect()" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all">
                        <option value="">-- Choose Item (FG, Raw Material, Packing Material) --</option>
                        <template x-for="p in filteredProductsForModal" :key="p.id">
                            <option :value="p.id" x-text="p.name + (p.pack_name ? ' • ' + p.pack_name : (p.weight_unit ? ' • ' + p.weight_unit : '')) + ' [' + (p.item_code || 'NO-CODE') + '] (' + (p.uom || 'N/A') + ')'"></option>
                        </template>
                    </select>
                </div>

                <!-- Selected Item Summary Card & Live Calculation -->
                <div x-show="selectedProduct" class="p-5 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-4 transition-all">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-sm font-black text-slate-800 uppercase" x-text="selectedProduct ? selectedProduct.name : ''"></h4>
                                <template x-if="selectedProduct && (selectedProduct.pack_name || selectedProduct.weight_unit)">
                                    <span class="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded text-[9px] font-black uppercase" x-text="'Pack: ' + (selectedProduct.pack_name || selectedProduct.weight_unit)"></span>
                                </template>
                            </div>
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 border border-indigo-100 rounded text-[9px] font-black uppercase" x-text="selectedProduct ? selectedProduct.item_code : ''"></span>
                                <span class="px-2 py-0.5 bg-slate-200/60 text-slate-600 rounded text-[9px] font-black uppercase" x-text="selectedProduct && selectedProduct.type ? selectedProduct.type.type_name : 'General'"></span>
                                <template x-if="selectedProduct && selectedProduct.rm_type">
                                    <span class="px-2 py-0.5 bg-purple-50 text-purple-600 border border-purple-100 rounded text-[9px] font-black uppercase" x-text="selectedProduct.rm_type"></span>
                                </template>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Current Stock</div>
                            <div class="text-base font-black text-slate-800" x-text="selectedProduct ? parseFloat(selectedProduct.current_stock).toFixed(3) + ' ' + (selectedProduct.uom || '') : ''"></div>
                        </div>
                    </div>

                    <!-- Live Calculation Box -->
                    <div class="grid grid-cols-3 gap-3 p-3 bg-white rounded-xl border border-slate-200 text-center">
                        <div>
                            <div class="text-[8px] font-black text-slate-400 uppercase tracking-wider">Current</div>
                            <div class="text-xs font-black text-slate-700" x-text="selectedProduct ? parseFloat(selectedProduct.current_stock).toFixed(3) : '0.000'"></div>
                        </div>
                        <div>
                            <div class="text-[8px] font-black uppercase tracking-wider" :class="form.adjustment_type === 'add' ? 'text-emerald-600' : 'text-rose-600'" x-text="form.adjustment_type === 'add' ? 'Receipt (+)' : 'Issue (-)'"></div>
                            <div class="text-xs font-black" :class="form.adjustment_type === 'add' ? 'text-emerald-600' : 'text-rose-600'" x-text="(form.quantity ? (form.adjustment_type === 'add' ? '+' : '-') + parseFloat(form.quantity).toFixed(3) : '0.000')"></div>
                        </div>
                        <div>
                            <div class="text-[8px] font-black text-slate-400 uppercase tracking-wider">New Stock</div>
                            <div class="text-xs font-black text-indigo-600" x-text="calculatedNewStock"></div>
                        </div>
                    </div>

                    <!-- Insufficient Stock Warning -->
                    <div x-show="form.adjustment_type === 'deduct' && selectedProduct && parseFloat(form.quantity || 0) > parseFloat(selectedProduct.current_stock)" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-600 text-xs font-bold flex items-center gap-2">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>Warning: Issue quantity exceeds available stock!</span>
                    </div>
                </div>

                <!-- Quantity Input -->
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Adjustment Quantity</label>
                    <div class="relative">
                        <input type="number" step="0.0001" min="0.0001" x-model="form.quantity" placeholder="Enter quantity..." class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-4 pr-16 text-sm font-black text-slate-800 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all" required>
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400 uppercase" x-text="selectedProduct ? selectedProduct.uom : 'Units'"></span>
                    </div>
                </div>

                @if(Auth::user()->hasFeature('adjustments', 'reason_select'))
                <!-- Reason & Preset Chips -->
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Reason / Remarks</label>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" @click="form.reason = 'Physical Verification Difference'" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-[10px] font-bold transition">Physical Verification</button>
                        <button type="button" @click="form.reason = 'Damaged / Leakage Goods'" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-[10px] font-bold transition">Damaged Goods</button>
                        <button type="button" @click="form.reason = 'Testing / QC Sampling'" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-[10px] font-bold transition">QC Sample</button>
                        <button type="button" @click="form.reason = 'Wastage / Scrap Loss'" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-[10px] font-bold transition">Wastage / Scrap</button>
                        <button type="button" @click="form.reason = 'Opening Stock Entry'" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-[10px] font-bold transition">Opening Balance</button>
                        <button type="button" @click="form.reason = 'Internal Branch Transfer'" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-[10px] font-bold transition">Branch Transfer</button>
                    </div>
                    <textarea x-model="form.reason" rows="2" placeholder="Describe the reason for adjustment (e.g. physical count diff, damaged bottles, formula test)..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs font-semibold text-slate-700 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all"></textarea>
                </div>
                @endif

                @if(Auth::user()->hasFeature('adjustments', 'erp_push'))
                <!-- ERP Push Toggle -->
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <div>
                        <div class="text-xs font-black text-slate-800 uppercase">Synchronize with Logic ERP</div>
                        <p class="text-[10px] text-slate-400 font-medium">Instantly post this voucher to ERP Stock Register</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" x-model="form.sync_erp" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>
                @endif

            </div>

            <!-- Modal Footer -->
            <div class="p-6 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between gap-4">
                <button type="button" @click="showModal = false" class="px-6 py-3 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-black uppercase tracking-wider transition active:scale-95">
                    Cancel
                </button>
                <button type="button" @click="submitAdjustment()" :disabled="submitting || !form.product_id || !form.quantity" class="px-8 py-3.5 text-white rounded-xl text-xs font-black uppercase tracking-wider shadow-md transition-all flex items-center gap-2 active:scale-95 disabled:opacity-50"
                        :class="form.adjustment_type === 'add' ? 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-200' : 'bg-rose-600 hover:bg-rose-700 shadow-rose-200'">
                    <i class="fas fa-circle-notch fa-spin" x-show="submitting"></i>
                    <i class="fas fa-check" x-show="!submitting"></i>
                    <span x-text="submitting ? 'Recording...' : (form.adjustment_type === 'add' ? 'Save Stock Receipt (+)' : 'Save Stock Issue (-)')"></span>
                </button>
            </div>

        </div>
    </div>

    <!-- Details View Modal -->
    <div x-show="showDetailsModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-all"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 max-w-lg w-full overflow-hidden flex flex-col"
             @click.outside="showDetailsModal = false">
            
            <div class="p-6 bg-indigo-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                        <i class="fas fa-file-invoice text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black uppercase tracking-tight" x-text="selectedAdjustment ? '#ADJ-' + String(selectedAdjustment.id).padStart(5, '0') : ''"></h3>
                        <p class="text-[10px] text-white/80 font-bold uppercase tracking-widest">Adjustment Voucher Details</p>
                    </div>
                </div>
                <button @click="showDetailsModal = false" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 space-y-4" x-show="selectedAdjustment">
                <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100 text-xs">
                    <div>
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Date & Time</div>
                        <div class="font-black text-slate-800 mt-0.5" x-text="selectedAdjustment ? formatDate(selectedAdjustment.created_at) : ''"></div>
                    </div>
                    <div>
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Branch</div>
                        <div class="font-black text-slate-800 mt-0.5" x-text="selectedAdjustment ? (selectedAdjustment.branch_name || 'Factory') : ''"></div>
                    </div>
                    <div>
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Action Type</div>
                        <div class="font-black uppercase mt-0.5" :class="selectedAdjustment && selectedAdjustment.adjustment_type === 'add' ? 'text-emerald-600' : 'text-rose-600'" x-text="selectedAdjustment && selectedAdjustment.adjustment_type === 'add' ? 'Stock Receipt (+)' : 'Stock Issue (-)'"></div>
                    </div>
                    <div>
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Adjusted Quantity</div>
                        <div class="font-black text-slate-800 mt-0.5" x-text="selectedAdjustment ? parseFloat(selectedAdjustment.quantity).toFixed(3) + ' ' + (selectedAdjustment.product ? selectedAdjustment.product.uom : '') : ''"></div>
                    </div>
                    <div>
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Recorded By</div>
                        <div class="font-black text-slate-800 mt-0.5" x-text="selectedAdjustment && selectedAdjustment.user ? selectedAdjustment.user.name : 'System'"></div>
                    </div>
                    <div>
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">ERP Push Status</div>
                        <div class="font-black uppercase mt-0.5" :class="{
                            'text-emerald-600': selectedAdjustment && selectedAdjustment.erp_push_status === 'success',
                            'text-rose-600': selectedAdjustment && selectedAdjustment.erp_push_status === 'failed',
                            'text-slate-400': selectedAdjustment && selectedAdjustment.erp_push_status === 'skipped',
                            'text-amber-600': selectedAdjustment && selectedAdjustment.erp_push_status === 'pending'
                        }" x-text="selectedAdjustment ? selectedAdjustment.erp_push_status : ''"></div>
                    </div>
                    <div class="col-span-2" x-show="selectedAdjustment && selectedAdjustment.erp_doc_no">
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">ERP Document Number</div>
                        <div class="font-black text-emerald-700 mt-0.5" x-text="selectedAdjustment ? selectedAdjustment.erp_doc_no : ''"></div>
                    </div>
                </div>

                <!-- Product Details -->
                <div class="p-4 bg-white border border-slate-200 rounded-2xl space-y-2">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Item Info</div>
                    <div class="font-black text-slate-800 flex items-center gap-2 flex-wrap">
                        <span x-text="selectedAdjustment && selectedAdjustment.product ? selectedAdjustment.product.name : ''"></span>
                        <template x-if="selectedAdjustment && selectedAdjustment.product && (selectedAdjustment.product.pack_name || selectedAdjustment.product.weight_unit)">
                            <span class="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded text-[9px] font-black" x-text="'Pack: ' + (selectedAdjustment.product.pack_name || selectedAdjustment.product.weight_unit)"></span>
                        </template>
                    </div>
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
                        <span x-text="'Code: ' + (selectedAdjustment && selectedAdjustment.product ? selectedAdjustment.product.item_code : 'N/A')"></span>
                        <span>•</span>
                        <span x-text="'UOM: ' + (selectedAdjustment && selectedAdjustment.product ? selectedAdjustment.product.uom : 'N/A')"></span>
                    </div>
                </div>

                <!-- Reason -->
                <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Remarks / Note</div>
                    <p class="text-xs text-slate-700 font-medium italic mt-1" x-text="selectedAdjustment && selectedAdjustment.reason ? selectedAdjustment.reason : 'No remarks provided'"></p>
                </div>
            </div>

            <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                <button type="button" @click="showDetailsModal = false" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl text-xs font-black uppercase tracking-wider">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function adjustmentManager() {
    return {
        adjustments: @json($adjustments),
        products: @json($products),
        productTypes: @json($productTypes),
        branches: @json($branches),
        isBranchLocked: {{ ($isBranchLocked ?? false) ? 'true' : 'false' }},
        
        searchQuery: '',
        typeFilter: '',
        productTypeFilter: '',
        branchFilter: '{{ ($isBranchLocked ?? false) && ($lockedBranch ?? null) ? $lockedBranch->code : "" }}',
        
        modalTypeFilter: '',
        
        showModal: false,
        showDetailsModal: false,
        selectedAdjustment: null,
        selectedProduct: null,
        
        submitting: false,
        bulkRetrying: false,
        retryingId: null,
        
        form: {
            product_id: '',
            adjustment_type: '{{ Auth::user()->hasFeature("adjustments", "create_receipt") ? "add" : "deduct" }}',
            quantity: '',
            branch_code: '{{ ($isBranchLocked ?? false) && ($lockedBranch ?? null) ? $lockedBranch->code : (count($branches) > 0 ? $branches[0]->code : "2") }}',
            reason: '',
            sync_erp: true
        },

        get filteredProductsForModal() {
            if (!this.modalTypeFilter) {
                return this.products;
            }
            return this.products.filter(p => String(p.product_type_id) === String(this.modalTypeFilter));
        },

        get filteredAdjustments() {
            return this.adjustments.filter(adj => {
                // Search query
                if (this.searchQuery) {
                    const q = this.searchQuery.toLowerCase();
                    const pName = (adj.product ? adj.product.name : '').toLowerCase();
                    const pCode = (adj.product ? (adj.product.item_code || '') : '').toLowerCase();
                    const pPack = (adj.product ? (adj.product.pack_name || adj.product.weight_unit || '') : '').toLowerCase();
                    const reason = (adj.reason || '').toLowerCase();
                    const uName = (adj.user ? adj.user.name : '').toLowerCase();
                    const bName = (adj.branch_name || '').toLowerCase();
                    const idStr = String(adj.id);
                    if (!pName.includes(q) && !pCode.includes(q) && !pPack.includes(q) && !reason.includes(q) && !uName.includes(q) && !bName.includes(q) && !idStr.includes(q)) {
                        return false;
                    }
                }

                // Type filter (add / deduct)
                if (this.typeFilter && adj.adjustment_type !== this.typeFilter) {
                    return false;
                }

                // Product type filter
                if (this.productTypeFilter && adj.product) {
                    if (String(adj.product.product_type_id) !== String(this.productTypeFilter)) {
                        return false;
                    }
                }

                // Branch filter
                if (this.branchFilter && adj.branch_code !== this.branchFilter) {
                    return false;
                }

                return true;
            });
        },

        get paginatedAdjustments() {
            return this.filteredAdjustments;
        },

        get calculatedNewStock() {
            if (!this.selectedProduct) return '0.000';
            const cur = parseFloat(this.selectedProduct.current_stock || 0);
            const qty = parseFloat(this.form.quantity || 0);
            const result = this.form.adjustment_type === 'add' ? (cur + qty) : (cur - qty);
            return result.toFixed(3);
        },

        openModal() {
            this.form.product_id = '';
            this.form.quantity = '';
            this.form.reason = '';
            this.selectedProduct = null;
            this.showModal = true;
        },

        onProductSelect() {
            if (!this.form.product_id) {
                this.selectedProduct = null;
                return;
            }
            this.selectedProduct = this.products.find(p => String(p.id) === String(this.form.product_id)) || null;
        },

        viewDetails(adj) {
            this.selectedAdjustment = adj;
            this.showDetailsModal = true;
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        async submitAdjustment() {
            if (!this.form.product_id || !this.form.quantity) {
                alert('Please select a product and enter quantity.');
                return;
            }

            if (this.form.adjustment_type === 'deduct' && this.selectedProduct) {
                if (parseFloat(this.form.quantity) > parseFloat(this.selectedProduct.current_stock)) {
                    if (!confirm('Warning: Issue quantity exceeds available current stock! Do you still want to proceed?')) {
                        return;
                    }
                }
            }

            this.submitting = true;
            try {
                const response = await fetch("{{ route('adjustments.store') }}", {
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
                const res = await fetch(`{{ url('adjustments') }}/${id}/retry-erp`, {
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
            this.bulkRetrying = true;
            try {
                const res = await fetch("{{ route('adjustments.bulk-retry-erp') }}", {
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
                this.bulkRetrying = false;
            }
        },

        async revertAdjustment(id) {
            if (!confirm('Are you sure you want to revert/delete this adjustment? This will reverse the stock change in the ledger.')) {
                return;
            }

            try {
                const res = await fetch(`{{ url('adjustments') }}/${id}`, {
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
