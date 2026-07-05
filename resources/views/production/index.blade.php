@extends('layouts.app')

@section('content')
<div x-data="productionManager()" class="min-h-screen bg-[#f8fafc] py-4">
    <div class="max-w-[98%] mx-auto space-y-6">
        
        <!-- Premium Header Banner -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 relative overflow-hidden">
            <!-- Background glow accent -->
            <div class="absolute top-0 right-0 w-80 h-80 bg-indigo-50/40 rounded-full blur-3xl -mr-32 -mt-32"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-amber-50/20 rounded-full blur-3xl -ml-24 -mb-24"></div>

            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6 z-10">
                <div class="flex items-center gap-4">
                    <div class="bg-indigo-600 text-white p-4 rounded-2xl shadow-lg shadow-indigo-100 flex items-center justify-center">
                        <i class="fas fa-boxes-stacked text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-slate-800 tracking-tight uppercase">Production Batches</h1>
                        <p class="text-xs text-slate-400 font-medium mt-0.5">Record yields, track batch history, and sync live raw material deductions.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    @if(Auth::user()->hasPermission('production', 'create') && Auth::user()->hasFeature('production', 'management'))
                    <button @click="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3.5 rounded-xl font-bold text-xs uppercase tracking-wider transition-all duration-200 shadow-md shadow-indigo-100 flex items-center gap-2 transform hover:-translate-y-0.5 active:translate-y-0">
                        <i class="fas fa-plus"></i> New Production Entry
                    </button>
                    @endif
                </div>
            </div>
        </div>

        @if(Auth::user()->hasFeature('production', 'history'))
        <!-- Dashboard Filters & Statistics Bar -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-sliders text-indigo-500"></i> Filters & Search
                </h3>
            </div>

            <!-- Inputs layout -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
                <!-- Search input -->
                <div class="relative">
                    <input type="text" x-model="searchQuery" placeholder="Search batch ID, branch, product..." class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-10 pr-4 text-xs font-semibold text-slate-700 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                </div>

                <!-- Branch filter -->
                <div class="relative">
                    <select x-model="filterBranch" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-xs font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 appearance-none transition-all">
                        <option value="">All Branches</option>
                        @foreach($branches as $br)
                        <option value="{{ $br->code }}">{{ $br->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- ERP Status filter -->
                <div class="relative">
                    <select x-model="filterErpStatus" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-xs font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 appearance-none transition-all">
                        <option value="">All Sync Statuses</option>
                        <option value="success">Synced ✓</option>
                        <option value="failed">Failed ✗</option>
                        <option value="skipped">Skipped −</option>
                        <option value="pending">Pending ⌛</option>
                    </select>
                </div>

                <!-- From Date filter -->
                <div>
                    <input type="date" x-model="filterFromDate" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-xs font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                </div>

                <!-- To Date filter -->
                <div>
                    <input type="date" x-model="filterToDate" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-xs font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                </div>
            </div>
        </div>

        <!-- Production Batches History Table -->
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-100">
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Entry Date / ID</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Target Branch</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Items Count</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Total Volume</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Recorded By</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">ERP Status</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($history as $production)
                        @php
                            $prodItemsText = strtolower($production->items->pluck('product_name')->implode(' '));
                            $erpStatus = $production->erp_push_status ?? 'pending';
                        @endphp
                        <tr x-show="matchesFilters($el)"
                            data-id="{{ $production->id }}"
                            data-branch-code="{{ $production->branch_code }}"
                            data-erp-status="{{ $erpStatus }}"
                            data-date="{{ $production->production_date }}"
                            data-branch-name="{{ strtolower($production->branch_name) }}"
                            data-user-name="{{ strtolower($production->user->name ?? 'system') }}"
                            data-products="{{ strtolower($prodItemsText) }}"
                            class="hover:bg-indigo-50/10 transition-all duration-150 group">
                            
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 text-sm">{{ date('d M, Y', strtotime($production->production_date)) }}</div>
                                <div class="text-[9px] text-slate-400 font-bold">#BATCH-{{ str_pad($production->id, 5, '0', STR_PAD_LEFT) }}</div>
                            </td>
                            
                            <td class="px-6 py-4">
                                <span class="bg-indigo-50/80 border border-indigo-100/50 text-indigo-600 font-black px-2.5 py-1 rounded-lg text-[10px] tracking-wide uppercase">
                                    {{ $production->branch_name }} ({{ $production->branch_code }})
                                </span>
                            </td>
                            
                            <td class="px-6 py-4 text-center">
                                <div class="text-xs font-semibold text-slate-600">{{ $production->items->count() }} Products</div>
                            </td>
                            
                            <td class="px-6 py-4 text-center">
                                <span class="text-sm font-black text-slate-800">{{ number_format($production->items->sum('quantity_box'), 0) }}</span>
                                <span class="text-[9px] font-black text-slate-400 uppercase block -mt-0.5">Boxes</span>
                            </td>
                            
                            <td class="px-6 py-4 text-center">
                                <div class="text-xs font-bold text-slate-600 uppercase">{{ $production->user->name ?? 'System' }}</div>
                            </td>
                            
                            <td class="px-6 py-4 text-center">
                                <div class="inline-flex">
                                    @if($erpStatus === 'success')
                                        <span class="inline-flex items-center gap-1 bg-emerald-50 border border-emerald-100 text-emerald-700 font-black px-2.5 py-1 rounded-full text-[9px] uppercase tracking-wider">
                                            <i class="fas fa-check-circle"></i> Synced
                                        </span>
                                    @elseif($erpStatus === 'failed')
                                        <span class="inline-flex items-center gap-1 bg-rose-50 border border-rose-100 text-rose-600 font-black px-2.5 py-1 rounded-full text-[9px] uppercase tracking-wider cursor-pointer hover:bg-rose-100/50" @click="viewDetail({{ $production->id }})">
                                            <i class="fas fa-circle-exclamation text-rose-500"></i> Failed
                                        </span>
                                    @elseif($erpStatus === 'skipped')
                                        <span class="inline-flex items-center gap-1 bg-slate-50 border border-slate-100 text-slate-400 font-black px-2.5 py-1 rounded-full text-[9px] uppercase tracking-wider">
                                            <i class="fas fa-minus-circle"></i> Skipped
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 bg-amber-50 border border-amber-100 text-amber-600 font-black px-2.5 py-1 rounded-full text-[9px] uppercase tracking-wider">
                                            <i class="fas fa-clock"></i> Pending
                                        </span>
                                    @endif
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button @click="viewDetail({{ $production->id }})" title="View Details" class="bg-slate-50 text-slate-500 p-2 rounded-lg border border-slate-100 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition shadow-sm active:scale-95">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>
                                    @if(Auth::user()->hasPermission('production', 'edit'))
                                    <button @click="editProduction({{ $production->id }})" title="Edit Batch" class="bg-slate-50 text-slate-500 p-2 rounded-lg border border-slate-100 hover:bg-blue-600 hover:text-white hover:border-blue-600 transition shadow-sm active:scale-95">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    @endif
                                    @if(Auth::user()->hasPermission('production', 'delete'))
                                    <button @click="deleteProduction({{ $production->id }})" title="Delete & Revert Stock" class="bg-slate-50 text-slate-500 p-2 rounded-lg border border-slate-100 hover:bg-rose-600 hover:text-white hover:border-rose-600 transition shadow-sm active:scale-95">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-20 text-center text-slate-400 italic font-bold uppercase tracking-widest bg-white">No production records found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Details Side Drawer (Slide-In) -->
        <div x-show="showDetailDrawer" x-cloak class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
            <div class="absolute inset-0 overflow-hidden">
                <!-- Overlay background -->
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" 
                     x-show="showDetailDrawer"
                     x-transition:enter="ease-in-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in-out duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @click="showDetailDrawer = false"></div>

                <!-- Sliding panel -->
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div class="pointer-events-auto w-screen max-w-xl"
                         x-show="showDetailDrawer"
                         x-transition:enter="transform transition ease-in-out duration-300 sm:duration-400"
                         x-transition:enter-start="translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transform transition ease-in-out duration-200 sm:duration-300"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="translate-x-full">
                        
                        <div class="flex h-full flex-col overflow-y-scroll bg-white shadow-2xl rounded-l-3xl border-l border-slate-100 relative">
                            
                            <!-- Loading overlay inside drawer -->
                            <div x-show="loadingDetail" class="absolute inset-0 bg-white/80 backdrop-blur-xs flex flex-col items-center justify-center z-20">
                                <i class="fas fa-circle-notch fa-spin text-indigo-600 text-3xl"></i>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-3">Loading details...</span>
                            </div>

                            <!-- Drawer Header -->
                            <div class="bg-slate-900 px-6 py-7 text-white relative">
                                <div class="flex items-center gap-3">
                                    <div class="bg-white/10 p-3 rounded-2xl">
                                        <i class="fas fa-id-card text-xl"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-black tracking-tight uppercase" x-text="selectedLog ? 'Batch #' + String(selectedLog.id).padStart(5, '0') : ''"></h2>
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-1">Detailed Yield & ERP Push Report</p>
                                    </div>
                                </div>
                                <button @click="showDetailDrawer = false" class="absolute top-6 right-6 w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                                    <i class="fas fa-times text-sm"></i>
                                </button>
                            </div>

                            <!-- Drawer Body -->
                            <div class="flex-1 py-6 px-6 space-y-6" x-show="selectedLog">
                                <!-- Log Info Panel -->
                                <div class="grid grid-cols-2 gap-4 bg-slate-50 border border-slate-100 p-4 rounded-2xl">
                                    <div>
                                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Production Date</span>
                                        <span class="text-xs font-bold text-slate-700 uppercase" x-text="selectedLog ? formatDateString(selectedLog.production_date) : '--'"></span>
                                    </div>
                                    <div>
                                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Branch Location</span>
                                        <span class="text-xs font-bold text-slate-700 uppercase" x-text="selectedLog ? selectedLog.branch_name + ' (' + selectedLog.branch_code + ')' : '--'"></span>
                                    </div>
                                    <div class="mt-2">
                                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">Recorded By</span>
                                        <span class="text-xs font-bold text-slate-700 uppercase" x-text="selectedLog && selectedLog.user ? selectedLog.user.name : 'System'"></span>
                                    </div>
                                    <div class="mt-2">
                                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest block">ERP Push Status</span>
                                        <div class="mt-0.5">
                                            <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-wider" :class="{
                                                'bg-emerald-50 border border-emerald-100 text-emerald-700': selectedLog && selectedLog.erp_push_status === 'success',
                                                'bg-rose-50 border border-rose-100 text-rose-600': selectedLog && selectedLog.erp_push_status === 'failed',
                                                'bg-slate-50 border border-slate-100 text-slate-400': selectedLog && selectedLog.erp_push_status === 'skipped',
                                                'bg-amber-50 border border-amber-100 text-amber-600': selectedLog && (selectedLog.erp_push_status === 'pending' || !selectedLog.erp_push_status)
                                            }" x-text="selectedLog ? selectedLog.erp_push_status || 'pending' : 'pending'"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Produced Items List -->
                                <div class="space-y-3">
                                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Produced Finished Goods</h4>
                                    <div class="divide-y divide-slate-100 border border-slate-100 rounded-2xl overflow-hidden bg-white">
                                        <template x-for="item in (selectedLog ? selectedLog.items : [])" :key="item.id">
                                            <div class="p-4 flex justify-between items-center hover:bg-slate-50/50">
                                                <div>
                                                    <div class="font-bold text-slate-700 text-xs" x-text="item.product_name"></div>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <span class="px-1.5 py-0.2 bg-slate-100 text-slate-500 text-[8px] font-black rounded uppercase" x-text="'Lot: ' + item.batch_number"></span>
                                                        <div class="w-1 h-1 bg-slate-300 rounded-full"></div>
                                                        <span class="text-[9px] text-slate-400 font-bold" x-text="'Pack size: ' + (item.pack_size || 'N/A')"></span>
                                                    </div>
                                                    <div class="text-[8px] text-slate-400 font-bold mt-0.5" x-text="'MFG: ' + item.mfg_date + ' | EXP: ' + item.exp_date"></div>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <span class="text-sm font-black text-slate-800" x-text="parseFloat(item.quantity_box).toFixed(0)"></span>
                                                    <span class="text-[9px] font-black text-slate-400 uppercase block -mt-0.5">Boxes</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- ERP Response Log Details -->
                                <div x-show="selectedLog && (selectedLog.erp_issue_response || selectedLog.erp_receipt_response)" class="space-y-3">
                                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-rose-500">ERP Sync Raw Response Logs</h4>
                                    <div class="bg-slate-900 p-4 rounded-2xl overflow-x-auto text-[10px] text-indigo-200 font-mono space-y-3 shadow-inner">
                                        <div x-show="selectedLog && selectedLog.erp_issue_response">
                                            <div class="text-[8px] text-indigo-400 font-bold uppercase tracking-wider mb-1">Issue payload response:</div>
                                            <pre class="bg-slate-950 p-2.5 rounded-lg border border-slate-800 text-[9px] whitespace-pre-wrap max-h-32 overflow-y-auto custom-scrollbar" x-text="selectedLog ? safeJsonFormat(selectedLog.erp_issue_response) : ''"></pre>
                                        </div>
                                        <div x-show="selectedLog && selectedLog.erp_receipt_response">
                                            <div class="text-[8px] text-indigo-400 font-bold uppercase tracking-wider mb-1">Receipt payload response:</div>
                                            <pre class="bg-slate-950 p-2.5 rounded-lg border border-slate-800 text-[9px] whitespace-pre-wrap max-h-32 overflow-y-auto custom-scrollbar" x-text="selectedLog ? safeJsonFormat(selectedLog.erp_receipt_response) : ''"></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Drawer Footer -->
                            <div class="bg-slate-50 border-t border-slate-100 py-4 px-6 flex justify-end gap-3">
                                <button @click="showDetailDrawer = false" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 px-6 py-2.5 rounded-xl text-xs font-bold uppercase">
                                    Close Details
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Interactive Production Input Modal -->
        <div x-show="showModal" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
                
                <div class="bg-white w-full max-w-6xl h-[90vh] rounded-[2rem] shadow-2xl overflow-hidden flex flex-col"
                     @click.outside="closeModal()">
                    
                    <!-- Modal Header -->
                    <div class="bg-indigo-600 p-6 text-white relative">
                        <div class="flex items-center gap-4">
                            <div class="bg-white/10 p-3 rounded-xl flex items-center justify-center">
                                <i class="fas fa-industry text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-black uppercase tracking-tight">
                                    <span x-text="isEditing ? 'Modify Production Batch' : 'Log New Production Yield'"></span>
                                    <span x-show="step === 2" class="opacity-70"> / Confirmation</span>
                                </h2>
                                <p class="text-indigo-200 text-[10px] font-bold uppercase tracking-widest mt-1">
                                    <span x-show="step === 1" x-text="isEditing ? 'Update existing yield records and reverse ledger counts' : 'Submit yields across multiple finished products'"></span>
                                    <span x-show="step === 2">Review batch slip details before transaction</span>
                                </p>
                            </div>
                        </div>
                        <button @click="closeModal()" class="absolute top-6 right-6 text-white/50 hover:text-white transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                        
                        <!-- Header wizard progress indicators -->
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-indigo-700">
                            <div class="h-full bg-amber-500 transition-all duration-300" :style="'width: ' + (step * 50) + '%'"></div>
                        </div>
                    </div>

                    <!-- Modal Content Body -->
                    <div class="flex-1 overflow-hidden flex flex-col p-6">
                        
                        <!-- Step 1: Input Form -->
                        <div x-show="step === 1" class="flex flex-col h-full overflow-hidden space-y-6">
                            <!-- Batch metadata selectors -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-slate-50 p-4 border border-slate-100 rounded-2xl">
                                <div class="space-y-1.5">
                                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Target Production Branch</label>
                                    <select x-model="branchCode" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-black text-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all cursor-not-allowed" disabled>
                                        @foreach($branches as $branch)
                                        <option value="{{ $branch->code }}">{{ $branch->name }} ({{ $branch->code }})</option>
                                        @endforeach
                                    </select>
                                    <p class="text-[8px] font-bold text-slate-400 mt-1 uppercase">* Automatically locked to Factory Branch (2)</p>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Yield Record Date</label>
                                    <input type="date" x-model="productionDate" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                                </div>
                                @if(Auth::user()->hasFeature('production', 'type_filter'))
                                <div class="space-y-1.5">
                                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1 flex items-center gap-1.5">
                                        <i class="fas fa-filter text-[9px] text-slate-400"></i> Quick Filter Product Type
                                    </label>
                                    <select x-model="typeFilter" class="w-full bg-indigo-50/50 border border-indigo-100 rounded-xl px-4 py-2.5 text-xs font-black text-indigo-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 appearance-none transition-all uppercase tracking-wide">
                                        <option value="">Show All Products</option>
                                        @foreach($productTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                            </div>

                            <!-- Products Entry list -->
                            <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar">
                                <table class="w-full text-left">
                                    <thead class="sticky top-0 bg-white z-10 border-b border-slate-100">
                                        <tr>
                                            <th class="pb-3 text-[10px] font-black text-slate-400 uppercase tracking-widest w-1/3">Finished Product Name *</th>
                                            <th class="pb-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center w-24">Yield (Box) *</th>
                                            <th class="pb-3 text-[10px] font-black text-slate-400 uppercase tracking-widest pl-6">Lot Batch / MFG / EXP Dates *</th>
                                            <th class="pb-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right w-12"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(item, index) in items" :key="index">
                                            <tr class="group hover:bg-slate-50/20">
                                                <td class="py-4 align-top pr-4">
                                                    <select x-model="item.product_id" @change="updateProductInfo(index)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all uppercase tracking-tight">
                                                        <option value="">Select Finished Good</option>
                                                        @foreach($finishedGoods as $p)
                                                        <option value="{{ $p->id }}" 
                                                                x-show="!typeFilter || {{ $p->product_type_id ?? 0 }} == typeFilter">
                                                            {{ $p->name }} ({{ $p->pack_name ?? 'N/A' }})
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                    <div x-show="item.pack_size" class="mt-1 px-2.5 py-0.5 bg-indigo-50 text-indigo-600 rounded-md text-[9px] font-black uppercase tracking-wider inline-block" x-text="'Pack size: ' + item.pack_size"></div>
                                                </td>
                                                <td class="py-4 align-top w-24">
                                                    <input type="number" step="0.001" x-model="item.quantity" @input="fetchRequirements(index)" class="w-24 bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-center text-xs font-black text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" placeholder="0">
                                                </td>
                                                <td class="py-4 align-top pl-6 space-y-3">
                                                    <!-- Lot parameters -->
                                                    <div class="flex gap-2">
                                                        <div class="flex-1 relative">
                                                            <input type="text" x-model="item.batch_number" @input="item.batch_number = $event.target.value.toUpperCase()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-[10px] font-bold text-slate-800 placeholder:text-slate-300 uppercase focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all" placeholder="BATCH NO">
                                                            <div x-show="!item.batch_number" class="absolute top-1/2 right-3 -translate-y-1/2 text-rose-400"><i class="fas fa-circle text-[5px]"></i></div>
                                                        </div>
                                                        <div class="w-32 relative">
                                                            <input type="date" x-model="item.mfg_date" title="MFG Date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-[9px] font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                                                            <div x-show="!item.mfg_date" class="absolute top-1/2 right-3 -translate-y-1/2 text-rose-400"><i class="fas fa-circle text-[5px]"></i></div>
                                                        </div>
                                                        <div class="w-32 relative">
                                                            <input type="date" x-model="item.exp_date" title="EXP Date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-[9px] font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                                                            <div x-show="!item.exp_date" class="absolute top-1/2 right-3 -translate-y-1/2 text-rose-400"><i class="fas fa-circle text-[5px]"></i></div>
                                                        </div>
                                                    </div>

                                                    <!-- Loading indicator -->
                                                    <div x-show="item.loadingRequirements" class="bg-slate-50 border border-slate-100 rounded-xl p-4 flex items-center justify-center gap-3">
                                                        <i class="fas fa-circle-notch fa-spin text-indigo-500"></i>
                                                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Checking raw material stock availability...</span>
                                                    </div>

                                                    <!-- Recipe explosion summary details -->
                                                    <div x-show="item.requirements && item.requirements.length > 0 && !item.loadingRequirements" class="bg-slate-50 border border-slate-200/60 rounded-2xl p-4 space-y-3">
                                                        <div class="flex items-center justify-between border-b border-slate-200/50 pb-2">
                                                            <span class="text-[9px] font-black text-indigo-500 uppercase tracking-widest flex items-center gap-1.5"><i class="fas fa-flask"></i> Recipe Deduction Preview</span>
                                                            <span x-show="!item.isPossible" class="bg-rose-500 text-white px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-wider animate-pulse">Insufficient Stock</span>
                                                        </div>
                                                        
                                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                            <template x-for="req in item.requirements" :key="req.item_code">
                                                                <div class="p-2.5 bg-white border border-slate-100 rounded-xl flex items-center justify-between">
                                                                    <div class="min-w-0 flex-1 pr-2">
                                                                        <div class="text-[10px] font-bold text-slate-700 truncate" x-text="req.name"></div>
                                                                        <div class="text-[7px] font-black text-slate-400 uppercase tracking-wider" x-text="req.item_code"></div>
                                                                    </div>
                                                                    <div class="text-right flex gap-3 shrink-0">
                                                                        <div>
                                                                            <span class="font-black text-slate-800 text-[10px]" x-text="parseFloat(req.required_qty).toFixed(2)"></span>
                                                                            <span class="text-[7px] font-black text-slate-400 uppercase block -mt-0.5">Need</span>
                                                                        </div>
                                                                        <div>
                                                                            <span class="font-black text-[10px]" :class="req.live_stock < req.required_qty ? 'text-rose-500' : 'text-emerald-600'" x-text="parseFloat(req.live_stock).toFixed(2)"></span>
                                                                            <span class="text-[7px] font-black text-slate-400 uppercase block -mt-0.5">Stock</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    <div x-show="item.requirementsError" class="text-[9px] text-rose-500 font-bold uppercase tracking-wider italic flex items-center gap-1.5 pl-1">
                                                        <i class="fas fa-circle-exclamation text-[10px]"></i>
                                                        <span x-text="item.requirementsError"></span>
                                                    </div>
                                                </td>
                                                <td class="py-4 text-right align-top">
                                                    <button @click="removeItem(index)" class="text-slate-300 hover:text-rose-500 p-2.5 rounded-lg hover:bg-rose-50 transition active:scale-90" title="Delete product item row">
                                                        <i class="fas fa-trash-can text-sm"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>

                                <!-- Add Row Button -->
                                <button @click="addItem()" class="mt-4 w-full py-3.5 border-2 border-dashed border-slate-200 bg-slate-50/30 rounded-2xl text-slate-400 font-bold hover:bg-slate-50 hover:text-indigo-500 hover:border-indigo-200 transition-all flex items-center justify-center gap-2 text-xs active:scale-[0.99]">
                                    <i class="fas fa-plus-circle"></i> ADD ANOTHER FINISHED GOOD
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Confirmation / Slip preview -->
                        <div x-show="step === 2" class="flex-1 overflow-y-auto px-12 py-8 bg-slate-50 border border-slate-100 rounded-3xl space-y-6">
                            <div class="flex justify-between items-start border-b border-slate-200/60 pb-6">
                                <div>
                                    <h1 class="text-3xl font-black tracking-tighter text-indigo-600">PRODUCTION RECEIPT SLIP</h1>
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-1" x-text="isEditing ? 'BATCH UPDATE PREVIEW' : 'NEW YIELD ENTRY PREVIEW'"></p>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-black text-slate-800 uppercase" x-text="branchName"></div>
                                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest" x-text="'Branch Code: ' + branchCode"></div>
                                    <div class="mt-2 text-xs font-black text-indigo-500 uppercase tracking-wider" x-text="formattedDate"></div>
                                </div>
                            </div>

                            <!-- Yield slip listing -->
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-slate-200 pb-3 text-left">
                                        <th class="pb-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Product description</th>
                                        <th class="pb-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Batch details</th>
                                        <th class="pb-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Yield Quantity</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(item, index) in items" :key="index">
                                        <tr>
                                            <td class="py-4">
                                                <div class="font-bold text-slate-800 text-xs" x-text="item.product_name"></div>
                                                <div class="text-[8px] font-black text-indigo-400 uppercase mt-0.5" x-text="'Pack size: ' + item.pack_size"></div>
                                            </td>
                                            <td class="py-4 text-center">
                                                <div class="inline-flex flex-col items-center">
                                                    <span class="px-2 py-0.5 bg-amber-50 border border-amber-100 text-amber-800 rounded font-black text-[9px] uppercase tracking-wider mb-1" x-text="'Lot: ' + (item.batch_number || 'N/A')"></span>
                                                    <div class="text-[8px] font-bold text-slate-400">
                                                        MFG: <span x-text="item.mfg_date || '--'"></span> | EXP: <span x-text="item.exp_date || '--'"></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 text-right">
                                                <span class="text-lg font-black text-slate-900" x-text="parseFloat(item.quantity).toFixed(0)"></span>
                                                <span class="text-[9px] font-black text-slate-400 uppercase block -mt-1">Boxes</span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-slate-200">
                                        <td colspan="2" class="py-6 text-right font-black text-slate-400 uppercase tracking-widest text-xs">Total Batch Produced Volume:</td>
                                        <td class="py-6 text-right">
                                            <span class="text-2xl font-black text-indigo-600 tracking-tighter" x-text="totalQuantity"></span>
                                            <span class="text-[9px] font-black text-indigo-300 uppercase block -mt-1">Total Boxes</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>

                            <!-- Warning box -->
                            <div class="p-4 bg-amber-50 rounded-2xl border border-amber-100 flex gap-4">
                                <div class="bg-amber-500 text-white p-3 rounded-xl flex items-center justify-center shrink-0">
                                    <i class="fas fa-circle-exclamation text-lg"></i>
                                </div>
                                <div>
                                    <h4 class="font-black text-amber-800 text-xs uppercase tracking-wider">Inventory Update Notice</h4>
                                    <p class="text-[10px] font-bold text-amber-600 leading-relaxed mt-1" x-text="isEditing ? 'This update will reverse previous stock changes and apply new values for products and raw materials.' : 'This entry will automatically adjust the inventory levels for products and their corresponding raw materials.'"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer Actions -->
                    <div class="p-6 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between">
                        <button @click="closeModal()" class="px-6 py-3 border border-slate-200 hover:bg-slate-50 text-slate-500 rounded-xl font-bold text-xs uppercase tracking-wider transition active:scale-95">
                            Cancel
                        </button>
                        <div class="flex gap-3">
                            <template x-if="step === 2">
                                <button @click="step = 1" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl font-bold text-xs uppercase tracking-wider transition active:scale-95">
                                    Back to edit
                                </button>
                            </template>
                            <button @click="step === 1 ? goToPreview() : submitProduction()" 
                                    class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition active:scale-95 shadow-md shadow-indigo-100 flex items-center gap-2">
                                <i :class="step === 1 ? 'fas fa-eye' : (isEditing ? 'fas fa-save' : 'fas fa-cloud-arrow-up')"></i>
                                <span x-text="step === 1 ? 'Preview slip' : (isEditing ? 'Update & Save changes' : 'Confirm & Save Production')"></span>
                            </button>
                        </div>
                    </div>
                </div>
        </div>
    </div>
</div>

<!-- Forms for Submission -->
<form id="productionSubmitForm" action="" method="POST" style="display: none;">
    @csrf
    <div id="method_field"></div>
    <input type="hidden" name="production_date" id="form_date">
    <input type="hidden" name="branch_code" id="form_branch">
    <div id="form_items"></div>
</form>

<form id="deleteForm" action="" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<script>
function productionManager() {
    return {
        // Form state
        showModal: false,
        isEditing: false,
        editId: null,
        step: 1,
        branchCode: '2',
        branchName: 'Factory',
        productionDate: '{{ date("Y-m-d") }}',
        items: [],
        typeFilter: '',
        
        // Masters
        branches: @json($branches),
        finishedGoods: @json($finishedGoods),
        productTypes: @json($productTypes),

        // Live filtering states (server-side rows x-show query)
        searchQuery: '',
        filterBranch: '',
        filterErpStatus: '',
        filterFromDate: '',
        filterToDate: '',

        // Selected detail log
        showDetailDrawer: false,
        selectedLog: null,
        loadingDetail: false,

        init() {
            // No-op
        },

        matchesFilters(el) {
            const id = el.getAttribute('data-id');
            const branchCode = el.getAttribute('data-branch-code');
            const erpStatus = el.getAttribute('data-erp-status');
            const productionDate = el.getAttribute('data-date');
            const branchName = el.getAttribute('data-branch-name');
            const userName = el.getAttribute('data-user-name');
            const productsText = el.getAttribute('data-products');

            // Search query filter
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                const matchBatchId = ('#batch-' + String(id).padStart(5, '0')).includes(q) || String(id).includes(q);
                const matchBranch = branchName.includes(q);
                const matchUser = userName.includes(q);
                const matchProduct = productsText.includes(q);
                
                if (!matchBatchId && !matchBranch && !matchUser && !matchProduct) {
                    return false;
                }
            }
            
            // Branch filter
            if (this.filterBranch && branchCode != this.filterBranch) {
                return false;
            }
            
            // ERP status filter
            if (this.filterErpStatus && erpStatus !== this.filterErpStatus) {
                return false;
            }
            
            // Date filters
            if (this.filterFromDate && productionDate < this.filterFromDate) {
                return false;
            }
            if (this.filterToDate && productionDate > this.filterToDate) {
                return false;
            }
            
            return true;
        },

        get filteredProducts() {
            if (!this.typeFilter) return this.finishedGoods;
            return this.finishedGoods.filter(p => p.product_type_id == this.typeFilter);
        },

        openModal() {
            this.isEditing = false;
            this.editId = null;
            this.showModal = true;
            this.step = 1;
            this.typeFilter = '';
            this.branchCode = '2';
            this.items = [];
            this.addItem();
        },

        closeModal() {
            const hasData = this.items.some(i => i.product_id || i.quantity || i.batch_number);
            if (hasData) {
                if (confirm('Discard changes and close modal?')) {
                    this.showModal = false;
                    this.isEditing = false;
                    this.items = [];
                }
            } else {
                this.showModal = false;
                this.isEditing = false;
                this.items = [];
            }
        },

        addItem() {
            this.items.push({
                product_id: '',
                product_name: '',
                pack_size: '',
                quantity: '',
                batch_number: '',
                mfg_date: '{{ date("Y-m-d") }}',
                exp_date: '',
                requirements: [],
                loadingRequirements: false,
                isPossible: true,
                requirementsError: '',
                showRecipeCollapse: false
            });
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },

        updateProductInfo(index) {
            const item = this.items[index];
            const p = this.finishedGoods.find(p => p.id == item.product_id);
            if (p) {
                item.product_name = p.name;
                item.pack_size = p.pack_name || 'N/A';
                
                // Set default EXP date to 1 year out
                if (item.mfg_date) {
                    const mfg = new Date(item.mfg_date);
                    mfg.setFullYear(mfg.getFullYear() + 1);
                    item.exp_date = mfg.toISOString().split('T')[0];
                }
                
                this.fetchRequirements(index);
            }
        },

        fetchRequirements(index) {
            const item = this.items[index];
            if (!item.product_id || !item.quantity || item.quantity <= 0) {
                item.requirements = [];
                return;
            }

            item.loadingRequirements = true;
            item.requirementsError = '';

            fetch("{{ route('production.check-stock') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    product_id: item.product_id,
                    quantity: item.quantity,
                    branch_code: this.branchCode
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    item.requirements = data.requirements;
                    item.isPossible = data.possible;
                } else {
                    item.requirements = [];
                    item.isPossible = true; // No recipe means we don't block
                    item.requirementsError = data.message;
                }
            })
            .catch(err => {
                console.error(err);
                item.requirementsError = 'Failed to load requirements';
            })
            .finally(() => {
                item.loadingRequirements = false;
            });
        },

        goToPreview() {
            if (!this.branchCode || !this.productionDate) {
                alert('Please fill Branch and Date');
                return;
            }
            if (this.items.some(i => !i.product_id || !i.quantity || !i.batch_number || !i.mfg_date || !i.exp_date)) {
                alert('Please ensure all required fields, including Batch No, MFG and EXP dates are filled for all products.');
                return;
            }
            if (this.items.some(i => !i.isPossible)) {
                alert('CRITICAL: Some products cannot be produced due to Raw Material shortfall in Factory.');
                return;
            }
            const b = this.branches.find(b => b.code == this.branchCode);
            this.branchName = b ? b.name : this.branchCode;
            this.step = 2;
        },

        editProduction(id) {
            fetch(`{{ url('production') }}/${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const p = data.production;
                        this.editId = p.id;
                        this.isEditing = true;
                        this.branchCode = p.branch_code;
                        this.productionDate = p.production_date;
                        this.typeFilter = '';
                        this.items = p.items.map(i => ({
                            product_id: i.product_id,
                            product_name: i.product_name,
                            pack_size: i.pack_size,
                            quantity: i.quantity_box,
                            batch_number: i.batch_number,
                            mfg_date: i.mfg_date,
                            exp_date: i.exp_date,
                            requirements: [],
                            loadingRequirements: false,
                            isPossible: true,
                            requirementsError: '',
                            showRecipeCollapse: false
                        }));
                        this.showModal = true;
                        this.step = 1;
                    }
                });
        },

        deleteProduction(id) {
            if (confirm('Are you sure you want to delete this production entry? Stock will be reverted back.')) {
                const form = document.getElementById('deleteForm');
                form.action = `{{ url('production') }}/${id}`;
                form.submit();
            }
        },

        get totalQuantity() {
            return this.items.reduce((sum, item) => sum + (parseFloat(item.quantity) || 0), 0);
        },

        get formattedDate() {
            if (!this.productionDate) return '';
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            return new Date(this.productionDate).toLocaleDateString('en-GB', options);
        },

        viewDetail(id) {
            this.loadingDetail = true;
            fetch(`{{ url('production') }}/${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.selectedLog = data.production;
                        this.showDetailDrawer = true;
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to load batch details');
                })
                .finally(() => {
                    this.loadingDetail = false;
                });
        },

        formatDateString(dateStr) {
            if (!dateStr) return '--';
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            return new Date(dateStr).toLocaleDateString('en-GB', options);
        },

        safeJsonFormat(str) {
            if (!str) return '';
            try {
                if (typeof str === 'object') {
                    return JSON.stringify(str, null, 2);
                }
                const parsed = JSON.parse(str);
                return JSON.stringify(parsed, null, 2);
            } catch (e) {
                return str;
            }
        },

        submitProduction() {
            const form = document.getElementById('productionSubmitForm');
            const methodField = document.getElementById('method_field');
            
            if (this.isEditing) {
                form.action = `{{ url('production') }}/${this.editId}`;
                methodField.innerHTML = '<input type="hidden" name="_method" value="PUT">';
            } else {
                form.action = `{{ route('production.store') }}`;
                methodField.innerHTML = '';
            }

            document.getElementById('form_date').value = this.productionDate;
            document.getElementById('form_branch').value = this.branchCode;
            
            const itemsContainer = document.getElementById('form_items');
            itemsContainer.innerHTML = '';
            
            this.items.forEach((item, index) => {
                const prefix = `items[${index}]`;
                const fields = ['product_id', 'quantity', 'batch_number', 'mfg_date', 'exp_date'];
                fields.forEach(field => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `${prefix}[${field}]`;
                    input.value = item[field];
                    itemsContainer.appendChild(input);
                });
            });

            form.submit();
        }
    }
}
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
@endsection
