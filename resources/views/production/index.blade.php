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
                @php
                    $unsyncedCount = $history->filter(function($p) {
                        return in_array($p->erp_push_status ?? 'pending', ['failed', 'pending', 'skipped']);
                    })->count();
                @endphp
                @if($unsyncedCount > 0 && Auth::user()->hasFeature('production', 'erp_bulk_retry'))
                <button type="button" @click="bulkRetryErp()" :disabled="bulkRetrying" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-[11px] font-black uppercase tracking-wider rounded-xl transition flex items-center gap-1.5 shadow-xs active:scale-95">
                    <i class="fas fa-rotate-right" :class="bulkRetrying ? 'fa-spin' : ''"></i>
                    <span>Sync to ERP ({{ $unsyncedCount }})</span>
                </button>
                @endif
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
                                        <div class="inline-flex items-center gap-1.5">
                                            <span class="inline-flex items-center gap-1 bg-rose-50 border border-rose-100 text-rose-600 font-black px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider @if(Auth::user()->hasFeature('production', 'view_details')) cursor-pointer hover:bg-rose-100/50 @endif" @if(Auth::user()->hasFeature('production', 'view_details')) @click="viewDetail({{ $production->id }})" @endif>
                                                <i class="fas fa-circle-exclamation text-rose-500"></i> Failed
                                            </span>
                                            @if(Auth::user()->hasFeature('production', 'erp_push'))
                                            <button type="button" @click.stop="retrySingleErp({{ $production->id }})" :disabled="retryingId === {{ $production->id }}" title="Retry ERP Push" class="inline-flex items-center gap-1 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider transition active:scale-95 shadow-xs">
                                                <i class="fas fa-rotate-right" :class="retryingId === {{ $production->id }} ? 'fa-spin' : ''"></i> Retry
                                            </button>
                                            @endif
                                        </div>
                                    @elseif($erpStatus === 'skipped')
                                        <div class="inline-flex items-center gap-1.5">
                                            <span class="inline-flex items-center gap-1 bg-slate-50 border border-slate-100 text-slate-400 font-black px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider">
                                                <i class="fas fa-minus-circle"></i> Skipped
                                            </span>
                                            @if(Auth::user()->hasFeature('production', 'erp_push'))
                                            <button type="button" @click.stop="retrySingleErp({{ $production->id }})" :disabled="retryingId === {{ $production->id }}" title="Push to ERP" class="inline-flex items-center gap-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider transition active:scale-95 shadow-xs">
                                                <i class="fas fa-paper-plane" :class="retryingId === {{ $production->id }} ? 'fa-spin' : ''"></i> Push
                                            </button>
                                            @endif
                                        </div>
                                    @else
                                        <div class="inline-flex items-center gap-1.5">
                                            <span class="inline-flex items-center gap-1 bg-amber-50 border border-amber-100 text-amber-600 font-black px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                            @if(Auth::user()->hasFeature('production', 'erp_push'))
                                            <button type="button" @click.stop="retrySingleErp({{ $production->id }})" :disabled="retryingId === {{ $production->id }}" title="Push to ERP" class="inline-flex items-center gap-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider transition active:scale-95 shadow-xs">
                                                <i class="fas fa-paper-plane" :class="retryingId === {{ $production->id }} ? 'fa-spin' : ''"></i> Push
                                            </button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    @if(Auth::user()->hasFeature('production', 'view_details'))
                                    <button @click="viewDetail({{ $production->id }})" title="View Details" class="bg-slate-50 text-slate-500 p-2 rounded-lg border border-slate-100 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition shadow-sm active:scale-95">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>
                                    @endif
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

                                <!-- Produced Finished Goods (SaveReceiptStock) -->
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                            <i class="fas fa-boxes-packing text-blue-600"></i> Finished Goods Receipt (SaveReceiptStock)
                                        </h4>
                                        <span x-show="selectedReceiptDoc" class="text-[9px] font-mono font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200" x-text="'Doc: ' + selectedReceiptDoc"></span>
                                    </div>
                                    <div class="divide-y divide-slate-100 border border-slate-100 rounded-2xl overflow-hidden bg-white">
                                        <template x-for="item in (selectedLog ? selectedLog.items : [])" :key="item.id">
                                            <div class="p-3.5 flex justify-between items-center hover:bg-slate-50/50">
                                                <div>
                                                    <div class="font-bold text-slate-700 text-xs" x-text="item.product_name"></div>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <span class="px-1.5 py-0.2 bg-slate-100 text-slate-500 text-[8px] font-black rounded uppercase" x-text="'Lot: ' + (item.batch_number || '--')"></span>
                                                        <div class="w-1 h-1 bg-slate-300 rounded-full"></div>
                                                        <span class="text-[9px] text-slate-400 font-bold" x-text="'Pack size: ' + (item.pack_size || 'N/A')"></span>
                                                    </div>
                                                    <div class="text-[8px] text-slate-400 font-bold mt-0.5" x-text="'MFG: ' + (item.mfg_date || '--') + ' | EXP: ' + (item.exp_date || '--')"></div>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <span class="text-sm font-black text-slate-800" x-text="parseFloat(item.quantity_box).toFixed(0)"></span>
                                                    <span class="text-[9px] font-black text-slate-400 uppercase block -mt-0.5">Boxes</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Issued Materials List (SaveIssueStock) -->
                                <div class="space-y-3" x-show="selectedIssueItems && selectedIssueItems.length > 0">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                            <i class="fas fa-arrow-up-from-bracket text-amber-600"></i> Materials Issued (SaveIssueStock)
                                        </h4>
                                        <span x-show="selectedIssueDoc" class="text-[9px] font-mono font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200" x-text="'Doc: ' + selectedIssueDoc"></span>
                                    </div>
                                    <div class="divide-y divide-slate-100 border border-slate-100 rounded-2xl overflow-hidden bg-white max-h-56 overflow-y-auto custom-scrollbar">
                                        <template x-for="rm in selectedIssueItems" :key="rm.item_code">
                                            <div class="p-3 flex justify-between items-center hover:bg-slate-50/50">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <span x-show="rm.type === 'formulation'" class="px-1.5 py-0.2 bg-amber-50 text-amber-700 border border-amber-200 text-[8px] font-black rounded uppercase">🧪 Chem</span>
                                                        <span x-show="rm.type !== 'formulation'" class="px-1.5 py-0.2 bg-blue-50 text-blue-700 border border-blue-200 text-[8px] font-black rounded uppercase">📦 Pack</span>
                                                        <span class="font-bold text-slate-700 text-xs" x-text="rm.name"></span>
                                                    </div>
                                                    <span class="text-[8px] font-mono text-slate-400 font-bold" x-text="rm.item_code"></span>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <span class="text-xs font-black text-slate-800" x-text="parseFloat(rm.quantity).toFixed(3)"></span>
                                                    <span class="text-[8px] font-black text-slate-400 uppercase ml-0.5" x-text="rm.uom"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- ERP Response Log Details -->
                                <div x-show="selectedLog && (selectedLog.erp_issue_response || selectedLog.erp_receipt_response)" class="space-y-3">
                                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-slate-500">ERP Sync Raw Response Logs</h4>
                                    <div class="bg-slate-900 p-4 rounded-2xl overflow-x-auto text-[10px] text-indigo-200 font-mono space-y-3 shadow-inner">
                                        <div x-show="selectedLog && selectedLog.erp_receipt_response">
                                            <div class="text-[8px] text-blue-400 font-bold uppercase tracking-wider mb-1">Receipt payload response (SaveReceiptStock):</div>
                                            <pre class="bg-slate-950 p-2.5 rounded-lg border border-slate-800 text-[9px] whitespace-pre-wrap max-h-28 overflow-y-auto custom-scrollbar" x-text="selectedLog ? safeJsonFormat(selectedLog.erp_receipt_response) : ''"></pre>
                                        </div>
                                        <div x-show="selectedLog && selectedLog.erp_issue_response">
                                            <div class="text-[8px] text-amber-400 font-bold uppercase tracking-wider mb-1">Issue payload response (SaveIssueStock):</div>
                                            <pre class="bg-slate-950 p-2.5 rounded-lg border border-slate-800 text-[9px] whitespace-pre-wrap max-h-28 overflow-y-auto custom-scrollbar" x-text="selectedLog ? safeJsonFormat(selectedLog.erp_issue_response) : ''"></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Drawer Footer -->
                            <div class="bg-slate-50 border-t border-slate-100 py-4 px-6 flex justify-between items-center">
                                <template x-if="selectedLog && selectedLog.erp_push_status !== 'success'">
                                    <button type="button" @click="retrySingleErp(selectedLog.id)" :disabled="retryingId === selectedLog.id" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition shadow-sm flex items-center gap-1.5 active:scale-95">
                                        <i class="fas fa-paper-plane" :class="retryingId === selectedLog.id ? 'fa-spin' : ''"></i>
                                        <span x-text="selectedLog.erp_push_status === 'failed' ? 'Retry ERP Sync Now' : 'Push to ERP Now'"></span>
                                    </button>
                                </template>
                                <div x-show="!selectedLog || selectedLog.erp_push_status === 'success'"></div>
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
                    <div class="bg-gradient-to-r from-indigo-700 via-indigo-600 to-purple-700 p-6 text-white relative">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center shadow-lg">
                                    <i class="fas fa-industry text-2xl text-amber-300"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-black uppercase tracking-tight flex items-center gap-2.5">
                                        <span x-text="isEditing ? 'Modify Production Batch' : 'Production Batch Execution'"></span>
                                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-400/40 text-emerald-200 text-[10px] font-black tracking-widest uppercase">
                                            Branch 2 (Factory)
                                        </span>
                                        <span x-show="step === 2" class="opacity-70 text-sm"> / Voucher Confirmation</span>
                                    </h2>
                                    <p class="text-indigo-200 text-xs font-semibold mt-0.5">
                                        <span x-show="step === 1">Finished Goods Stock Receipt (<code class="text-amber-200">SaveReceiptStock</code>) &amp; Material Issue (<code class="text-amber-200">SaveIssueStock</code>)</span>
                                        <span x-show="step === 2">Review dual voucher slips before pushing to Logic ERP</span>
                                    </p>
                                </div>
                            </div>
                            <button @click="closeModal()" class="text-white/60 hover:text-white transition">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <!-- Header wizard progress indicators -->
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-indigo-900/40">
                            <div class="h-full bg-amber-400 transition-all duration-300" :style="'width: ' + (step * 50) + '%'"></div>
                        </div>
                    </div>

                    <!-- Modal Content Body -->
                    <div class="flex-1 overflow-hidden flex flex-col p-6">
                        
                        <!-- Step 1: Dual Section Form (Receipt & Issue) -->
                        <div x-show="step === 1" class="flex flex-col h-full overflow-hidden space-y-4">
                            
                            <!-- Top Bar: Branch & Date & Formulation Toggle -->
                            <div class="bg-slate-50 border border-slate-200/70 p-3.5 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4 shrink-0">
                                <div class="flex flex-wrap items-center gap-4">
                                    <!-- Target Branch (Branch 2 Factory) -->
                                    <div class="space-y-1">
                                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Target Branch</span>
                                        <div class="flex items-center gap-2 bg-white border border-indigo-200 px-3 py-1.5 rounded-xl shadow-xs">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                            <span class="text-xs font-black text-indigo-700 uppercase">Factory (Branch 2)</span>
                                            <span class="text-[8px] font-black bg-indigo-50 text-indigo-500 px-1.5 py-0.5 rounded uppercase">Locked</span>
                                        </div>
                                    </div>

                                    <!-- Yield Date -->
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Production Date</label>
                                        <input type="date" x-model="productionDate" class="bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                                    </div>

                                    <!-- Product Type Filter -->
                                    @if(Auth::user()->hasFeature('production', 'type_filter'))
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Product Type Filter</label>
                                        <div class="flex items-center gap-1.5 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-xs focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:border-indigo-500">
                                            <i class="fas fa-filter text-indigo-500 text-xs"></i>
                                            <select x-model="typeFilter" @change="onTypeFilterChange()" class="bg-transparent border-none text-xs font-black text-slate-700 focus:ring-0 outline-none pr-4 py-0 cursor-pointer uppercase">
                                                <option value="">All Types</option>
                                                @foreach($productTypes as $type)
                                                <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    @endif
                                </div>

                                <!-- Material Issue Control Switches -->
                                <div class="flex items-center gap-3">
                                    <!-- Packaging Materials Toggle Switch -->
                                    @if(Auth::user()->hasFeature('production', 'packaging_toggle'))
                                    <div class="flex items-center gap-3 bg-white p-2.5 px-4 rounded-xl border border-indigo-100 shadow-xs">
                                        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                                             :class="includePackaging ? 'bg-blue-50 border border-blue-200 text-blue-600' : 'bg-slate-100 border border-slate-200 text-slate-400'">
                                            <i class="fas fa-box-open text-xs"></i>
                                        </div>
                                        <div>
                                            <span class="text-xs font-black text-slate-800 block">Packaging in Issue</span>
                                            <span class="text-[9px] font-bold text-slate-400 block -mt-0.5" x-text="includePackaging ? 'ON (Default): Packaging will be issued' : 'OFF: Packaging will NOT be issued'"></span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer ml-2">
                                            <input type="checkbox" x-model="includePackaging" @change="fetchConsolidatedRequirements()" class="sr-only peer">
                                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                        </label>
                                    </div>
                                    @endif

                                    <!-- Chemical Formulation Toggle Switch -->
                                    @if(Auth::user()->hasFeature('production', 'formulation_toggle'))
                                    <div class="flex items-center gap-3 bg-white p-2.5 px-4 rounded-xl border border-indigo-100 shadow-xs">
                                        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                                             :class="includeFormulation ? 'bg-amber-50 border border-amber-200 text-amber-600' : 'bg-slate-100 border border-slate-200 text-slate-400'">
                                            <i class="fas fa-flask text-xs"></i>
                                        </div>
                                        <div>
                                            <span class="text-xs font-black text-slate-800 block">Chemical Formulation in Issue</span>
                                            <span class="text-[9px] font-bold text-slate-400 block -mt-0.5" x-text="includeFormulation ? 'ON: Bulk Chemicals will be issued' : 'OFF (Default): Bulk Chemicals will NOT be issued'"></span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer ml-2">
                                            <input type="checkbox" x-model="includeFormulation" @change="fetchConsolidatedRequirements()" class="sr-only peer">
                                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
                                        </label>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Main Scrollable Body with Two Clear Sections -->
                            <div class="flex-1 overflow-y-auto pr-1 space-y-5 custom-scrollbar">

                                <!-- ============================================================ -->
                                <!-- SECTION 1: 📥 FINISHED GOODS STOCK RECEIPT (SaveReceiptStock) -->
                                <!-- ============================================================ -->
                                <div class="bg-white border-2 border-blue-100/80 rounded-2xl shadow-xs overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-50 via-indigo-50 to-white px-5 py-3 border-b border-blue-100 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-sm">
                                                <i class="fas fa-boxes-packing text-sm"></i>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-wide">1. Finished Goods Receipt (Stock In → Branch 2)</h3>
                                                    <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 font-mono text-[9px] font-bold">SaveReceiptStock</span>
                                                </div>
                                                <p class="text-[10px] text-slate-500 font-medium">Produced finished goods will be received into Factory (Branch 2) and pushed to ERP Receipt Register</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="addItem()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-wider rounded-xl transition shadow-xs flex items-center gap-1.5">
                                            <i class="fas fa-plus"></i> Add FG
                                        </button>
                                    </div>

                                    <!-- FG Items Table -->
                                    <div class="p-4 overflow-x-auto">
                                        <table class="w-full text-left">
                                            <thead>
                                                <tr class="border-b border-slate-100 text-[9px] font-black text-slate-400 uppercase tracking-widest">
                                                    <th class="pb-2.5 w-1/3">Finished Good Product *</th>
                                                    <th class="pb-2.5 w-28 text-center">Yield (Box) *</th>
                                                    <th class="pb-2.5 text-center w-28">Total Units</th>
                                                    <th class="pb-2.5 pl-4">Lot / Batch No *</th>
                                                    <th class="pb-2.5 pl-2">MFG Date *</th>
                                                    <th class="pb-2.5 pl-2">EXP Date *</th>
                                                    <th class="pb-2.5 w-10 text-right"></th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <template x-for="(item, index) in items" :key="index">
                                                    <tr class="hover:bg-slate-50/50">
                                                        <td class="py-3 align-middle pr-3">
                                                            <select x-model="item.product_id" @change="updateProductInfo(index)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all uppercase">
                                                                <option value="">Select Finished Good</option>
                                                                <template x-for="p in filteredProducts" :key="p.id">
                                                                    <option :value="p.id" :selected="item.product_id == p.id" x-text="p.name + (p.pack_name ? ' (' + p.pack_name + ')' : '')"></option>
                                                                </template>
                                                            </select>
                                                            <div x-show="item.pack_size" class="mt-1 px-2 py-0.2 bg-blue-50 text-blue-700 rounded text-[8px] font-black uppercase tracking-wider inline-block" x-text="'Pack size: ' + item.pack_size"></div>
                                                        </td>
                                                        <td class="py-3 align-middle w-28 text-center">
                                                            <input type="number" step="0.001" x-model="item.quantity" @input="updateQuantity(index)" class="w-24 mx-auto bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-center text-xs font-black text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="0">
                                                        </td>
                                                        <td class="py-3 align-middle w-28 text-center">
                                                            <span class="text-xs font-black text-indigo-600 block" x-text="calculateItemUnits(item)"></span>
                                                            <span class="text-[8px] font-bold text-slate-400 uppercase block -mt-0.5">Units</span>
                                                        </td>
                                                        <td class="py-3 align-middle pl-4 pr-2">
                                                            <input type="text" x-model="item.batch_number" @input="item.batch_number = $event.target.value.toUpperCase()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-[10px] font-bold text-slate-800 placeholder:text-slate-300 uppercase focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="BATCH NO">
                                                        </td>
                                                        <td class="py-3 align-middle pl-2 pr-2">
                                                            <input type="date" x-model="item.mfg_date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-[9px] font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                                        </td>
                                                        <td class="py-3 align-middle pl-2 pr-2">
                                                            <input type="date" x-model="item.exp_date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-[9px] font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                                        </td>
                                                        <td class="py-3 align-middle text-right">
                                                            <button type="button" @click="removeItem(index)" class="text-slate-300 hover:text-rose-500 p-2 rounded-lg hover:bg-rose-50 transition" title="Remove row">
                                                                <i class="fas fa-trash-can text-xs"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- ============================================================ -->
                                <!-- SECTION 2: 📤 MATERIALS ISSUE VOUCHER (SaveIssueStock)       -->
                                <!-- ============================================================ -->
                                <div class="bg-white border-2 border-amber-100/80 rounded-2xl shadow-xs overflow-hidden">
                                    <div class="bg-gradient-to-r from-amber-50 via-orange-50 to-white px-5 py-3 border-b border-amber-100 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-amber-600 text-white flex items-center justify-center shadow-sm">
                                                <i class="fas fa-arrow-up-from-bracket text-sm"></i>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-wide">2. Materials Issue Voucher (Stock Out ← Branch 2)</h3>
                                                    <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-mono text-[9px] font-bold">SaveIssueStock</span>
                                                </div>
                                                <p class="text-[10px] text-slate-500 font-medium">Required materials will be deducted from Factory (Branch 2) according to BOM and pushed to ERP Issue Register</p>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <span x-show="hasShortfall" class="px-2.5 py-1 rounded-full bg-rose-500 text-white text-[9px] font-black uppercase tracking-wider animate-pulse flex items-center gap-1">
                                                <i class="fas fa-triangle-exclamation"></i> Stock Shortfall
                                            </span>
                                            <span x-show="!hasShortfall && consolidatedRequirements.length > 0" class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-[9px] font-black uppercase tracking-wider flex items-center gap-1">
                                                <i class="fas fa-check-circle"></i> Stock Available
                                            </span>
                                        </div>
                                    </div>

                                    <div class="p-4">
                                        <!-- Loading State -->
                                        <div x-show="loadingConsolidated" class="py-8 text-center space-y-2">
                                            <i class="fas fa-circle-notch fa-spin text-amber-500 text-xl"></i>
                                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Calculating required materials & checking Branch 2 live stock...</p>
                                        </div>

                                        <!-- Direct Finished Goods Mode (Both OFF) -->
                                        <div x-show="!loadingConsolidated && !includePackaging && !includeFormulation" class="py-8 px-4 text-center space-y-2 bg-emerald-50/60 border border-emerald-200 rounded-2xl">
                                            <div class="w-12 h-12 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl shadow-xs">
                                                <i class="fas fa-boxes-packing"></i>
                                            </div>
                                            <p class="text-xs font-black text-emerald-900 uppercase tracking-wide">Direct Finished Goods Production Mode</p>
                                            <p class="text-xs text-emerald-700 font-bold">Both Packaging and Chemical Formulation issue are turned OFF.</p>
                                            <p class="text-[10px] text-emerald-600 max-w-lg mx-auto">Finished goods stock will be added directly to Branch 2 without deducting any raw materials or packing materials. In Logic ERP, only the Receipt Voucher (SaveReceiptStock) will be pushed, and the Issue Voucher will be skipped.</p>
                                        </div>

                                        <!-- Empty State (when at least one toggle is ON) -->
                                        <div x-show="!loadingConsolidated && consolidatedRequirements.length === 0 && (includePackaging || includeFormulation)" class="py-10 text-center space-y-2">
                                            <div class="w-12 h-12 mx-auto rounded-full bg-amber-50 text-amber-400 flex items-center justify-center text-xl">
                                                <i class="fas fa-boxes-stacked"></i>
                                            </div>
                                            <p class="text-xs font-bold text-slate-500">No materials calculated yet.</p>
                                            <p class="text-[10px] text-slate-400">Select finished goods and enter boxes above to automatically calculate issue materials.</p>
                                        </div>

                                        <!-- Materials List Table -->
                                        <div x-show="!loadingConsolidated && consolidatedRequirements.length > 0" class="overflow-x-auto">
                                            <table class="w-full text-left">
                                                <thead>
                                                    <tr class="border-b border-slate-100 text-[9px] font-black text-slate-400 uppercase tracking-widest">
                                                        <th class="pb-2.5">Category</th>
                                                        <th class="pb-2.5">Item Code & Name</th>
                                                        <th class="pb-2.5 text-right">Required Issue Qty</th>
                                                        <th class="pb-2.5 text-right">Branch 2 Live Stock</th>
                                                        <th class="pb-2.5 text-center">Availability Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    <template x-for="mat in consolidatedRequirements" :key="mat.item_code">
                                                        <tr class="hover:bg-slate-50/50">
                                                            <td class="py-2.5 align-middle">
                                                                <span x-show="mat.type === 'formulation'" class="px-2 py-0.5 bg-amber-50 text-amber-800 border border-amber-200 text-[8px] font-black rounded-md uppercase">
                                                                    🧪 Chemical
                                                                </span>
                                                                <span x-show="mat.type !== 'formulation'" class="px-2 py-0.5 bg-blue-50 text-blue-800 border border-blue-200 text-[8px] font-black rounded-md uppercase">
                                                                    📦 Packaging
                                                                </span>
                                                            </td>
                                                            <td class="py-2.5 align-middle">
                                                                <div class="font-bold text-xs text-slate-800" x-text="mat.name"></div>
                                                                <div class="text-[9px] font-mono font-bold text-slate-400" x-text="mat.item_code"></div>
                                                            </td>
                                                            <td class="py-2.5 align-middle text-right">
                                                                <span class="font-black text-slate-800 text-xs" x-text="parseFloat(mat.required_qty).toFixed(3)"></span>
                                                                <span class="text-[8px] font-black text-slate-400 uppercase ml-0.5" x-text="mat.uom"></span>
                                                            </td>
                                                            <td class="py-2.5 align-middle text-right">
                                                                <span class="font-black text-xs" :class="mat.is_available ? 'text-emerald-700' : 'text-rose-600'" x-text="parseFloat(mat.live_stock).toFixed(3)"></span>
                                                                <span class="text-[8px] font-black text-slate-400 uppercase ml-0.5" x-text="mat.uom"></span>
                                                            </td>
                                                            <td class="py-2.5 align-middle text-center">
                                                                <span x-show="mat.is_available" class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-full text-[8px] font-black uppercase">
                                                                    <i class="fas fa-check"></i> In Stock
                                                                </span>
                                                                <span x-show="!mat.is_available" class="inline-flex items-center gap-1 px-2 py-0.5 bg-rose-50 border border-rose-200 text-rose-700 rounded-full text-[8px] font-black uppercase">
                                                                    <i class="fas fa-circle-exclamation"></i> Shortfall: <span x-text="parseFloat(mat.shortfall).toFixed(2)"></span>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Step 2: Confirmation / Dual Voucher Slips Preview -->
                        <div x-show="step === 2" class="flex-1 overflow-y-auto px-8 py-6 bg-slate-50 border border-slate-100 rounded-3xl space-y-6 custom-scrollbar">
                            
                            <!-- Header info -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
                                <div>
                                    <h1 class="text-2xl font-black tracking-tight text-indigo-700 uppercase">Dual Voucher Verification</h1>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Target Location: Branch 2 (Factory) • Logic ERP Push Pre-Flight</p>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-black text-slate-800 uppercase block">Branch 2 (Factory)</span>
                                    <span class="text-[10px] font-bold text-indigo-500 uppercase block" x-text="formattedDate"></span>
                                </div>
                            </div>

                            <!-- Voucher 1: 📥 SaveReceiptStock Slip -->
                            <div class="bg-white border border-blue-200 rounded-2xl p-5 shadow-xs space-y-4">
                                <div class="flex items-center justify-between border-b border-blue-100 pb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-lg bg-blue-600 text-white flex items-center justify-center text-xs">
                                            <i class="fas fa-arrow-down-to-bracket"></i>
                                        </span>
                                        <h3 class="text-xs font-black text-slate-800 uppercase tracking-wide">Voucher 1: Stock Receipt Slip (<code class="text-blue-600 font-mono">SaveReceiptStock</code>)</h3>
                                    </div>
                                    <span class="text-[9px] font-bold bg-blue-50 text-blue-700 px-2 py-0.5 rounded border border-blue-200">Doc Prefix: REC | Godown: MAIN</span>
                                </div>

                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-[9px] font-black text-slate-400 uppercase tracking-widest">
                                            <th class="pb-2">Finished Good</th>
                                            <th class="pb-2 text-center">Batch / Lot No</th>
                                            <th class="pb-2 text-center">MFG / EXP</th>
                                            <th class="pb-2 text-right">Yield (Box)</th>
                                            <th class="pb-2 text-right">Total Units</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(item, index) in items" :key="index">
                                            <tr>
                                                <td class="py-3">
                                                    <div class="font-bold text-slate-800 text-xs" x-text="item.product_name"></div>
                                                    <div class="text-[8px] font-black text-indigo-400 uppercase mt-0.5" x-text="'Pack size: ' + item.pack_size"></div>
                                                </td>
                                                <td class="py-3 text-center">
                                                    <span class="px-2 py-0.5 bg-amber-50 border border-amber-100 text-amber-800 rounded font-black text-[9px] uppercase" x-text="item.batch_number || '--'"></span>
                                                </td>
                                                <td class="py-3 text-center text-[9px] font-bold text-slate-500">
                                                    <span x-text="item.mfg_date || '--'"></span> to <span x-text="item.exp_date || '--'"></span>
                                                </td>
                                                <td class="py-3 text-right font-black text-slate-800 text-sm" x-text="parseFloat(item.quantity).toFixed(0)"></td>
                                                <td class="py-3 text-right font-black text-indigo-600 text-sm" x-text="calculateItemUnits(item)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t border-slate-200 text-xs font-black">
                                            <td colspan="3" class="pt-3 text-right text-slate-400 uppercase tracking-widest">Total Produced Volume:</td>
                                            <td class="pt-3 text-right text-slate-800" x-text="totalQuantity + ' Boxes'"></td>
                                            <td class="pt-3 text-right text-indigo-600" x-text="totalUnitsCount + ' Units'"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Voucher 2: 📤 SaveIssueStock Slip -->
                            <div class="bg-white border border-amber-200 rounded-2xl p-5 shadow-xs space-y-4">
                                <div class="flex items-center justify-between border-b border-amber-100 pb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-lg bg-amber-600 text-white flex items-center justify-center text-xs">
                                            <i class="fas fa-arrow-up-from-bracket"></i>
                                        </span>
                                        <h3 class="text-xs font-black text-slate-800 uppercase tracking-wide">Voucher 2: Materials Issue Slip (<code class="text-amber-600 font-mono">SaveIssueStock</code>)</h3>
                                    </div>
                                    <span class="text-[9px] font-bold bg-amber-50 text-amber-700 px-2 py-0.5 rounded border border-amber-200">Doc Prefix: IS | IssueTo: CONSUMPTION</span>
                                </div>

                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-[9px] font-black text-slate-400 uppercase tracking-widest">
                                            <th class="pb-2">Category</th>
                                            <th class="pb-2">Material Name</th>
                                            <th class="pb-2">Item Code</th>
                                            <th class="pb-2 text-right">Quantity to Issue</th>
                                            <th class="pb-2 text-center">Branch 2 Stock Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="mat in consolidatedRequirements" :key="mat.item_code">
                                            <tr>
                                                <td class="py-2.5">
                                                    <span x-show="mat.type === 'formulation'" class="px-2 py-0.5 bg-amber-50 text-amber-800 border border-amber-200 text-[8px] font-black rounded uppercase">🧪 Chem</span>
                                                    <span x-show="mat.type !== 'formulation'" class="px-2 py-0.5 bg-blue-50 text-blue-800 border border-blue-200 text-[8px] font-black rounded uppercase">📦 Pack</span>
                                                </td>
                                                <td class="py-2.5 text-xs font-bold text-slate-800" x-text="mat.name"></td>
                                                <td class="py-2.5 text-[9px] font-mono text-slate-400 font-bold" x-text="mat.item_code"></td>
                                                <td class="py-2.5 text-right font-black text-slate-800 text-xs">
                                                    <span x-text="parseFloat(mat.required_qty).toFixed(3)"></span> <span class="text-[8px] text-slate-400 uppercase ml-0.5" x-text="mat.uom"></span>
                                                </td>
                                                <td class="py-2.5 text-center">
                                                    <span x-show="mat.is_available" class="text-[8px] font-black text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full uppercase">✓ Available</span>
                                                    <span x-show="!mat.is_available" class="text-[8px] font-black text-rose-700 bg-rose-50 px-2 py-0.5 rounded-full uppercase">⚠ Shortfall</span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Notice box -->
                            <div class="p-4 rounded-2xl border flex gap-4" 
                                 :class="(!includePackaging && !includeFormulation) ? 'bg-emerald-50 border-emerald-200' : (includeFormulation ? 'bg-amber-50 border-amber-200' : 'bg-blue-50 border-blue-200')">
                                <div class="p-3 rounded-xl flex items-center justify-center shrink-0" 
                                     :class="(!includePackaging && !includeFormulation) ? 'bg-emerald-600 text-white' : (includeFormulation ? 'bg-amber-500 text-white' : 'bg-blue-600 text-white')">
                                    <i :class="(!includePackaging && !includeFormulation) ? 'fas fa-boxes-packing text-lg' : (includeFormulation ? 'fas fa-flask text-lg' : 'fas fa-box-open text-lg')"></i>
                                </div>
                                <div>
                                    <h4 class="font-black text-xs uppercase tracking-wider" 
                                        :class="(!includePackaging && !includeFormulation) ? 'text-emerald-900' : (includeFormulation ? 'text-amber-900' : 'text-blue-900')">
                                        <span x-show="includePackaging && includeFormulation">Chemical Formulation + Packaging Deduction [ON]</span>
                                        <span x-show="includePackaging && !includeFormulation">Packaging Materials Only Deduction [Chemicals OFF]</span>
                                        <span x-show="!includePackaging && includeFormulation">Chemical Formulation Only Deduction [Packaging OFF]</span>
                                        <span x-show="!includePackaging && !includeFormulation">Direct Finished Goods Receipt [No Material Deductions]</span>
                                    </h4>
                                    <p class="text-[10px] font-bold leading-relaxed mt-1" 
                                       :class="(!includePackaging && !includeFormulation) ? 'text-emerald-700' : (includeFormulation ? 'text-amber-700' : 'text-blue-700')">
                                        <span x-show="includePackaging && includeFormulation">Finished goods will be received into Branch 2, and both Packaging + Chemical Formulation raw materials will be issued (deducted) and synced with ERP.</span>
                                        <span x-show="includePackaging && !includeFormulation">Finished goods will be received into Branch 2, and only Packaging materials (pouches, cartons, labels) will be issued. Bulk chemicals will remain untouched.</span>
                                        <span x-show="!includePackaging && includeFormulation">Finished goods will be received into Branch 2, and only Chemical Formulation raw materials will be issued. Packaging materials will remain untouched.</span>
                                        <span x-show="!includePackaging && !includeFormulation">Finished goods will be received into Branch 2 (SaveReceiptStock). No packaging or raw materials will be issued or deducted (SaveIssueStock will be skipped).</span>
                                    </p>
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
        branchCode: '2', // Fixed to Factory (Branch 2)
        branchName: 'Factory (Branch 2)',
        productionDate: '{{ date("Y-m-d") }}',
        items: [],
        typeFilter: '',
        includePackaging: true, // Default ON
        includeFormulation: false, // Default OFF
        
        // Consolidated requirements state for Issue Voucher
        consolidatedRequirements: [],
        loadingConsolidated: false,
        hasShortfall: false,

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
        selectedIssueItems: [],
        selectedReceiptDoc: '',
        selectedIssueDoc: '',
        loadingDetail: false,

        // Retry states
        retryingId: null,
        bulkRetrying: false,

        async retrySingleErp(id) {
            if (this.retryingId) return;
            if (!confirm(`Are you sure you want to retry pushing Production #BATCH-${String(id).padStart(5, '0')} to ERP?`)) return;

            this.retryingId = id;
            try {
                const response = await fetch(`{{ url('production') }}/${id}/retry-erp`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                if (data.success) {
                    alert('✓ ' + data.message);
                    location.reload();
                } else {
                    alert('✗ ' + data.message);
                }
            } catch (err) {
                console.error(err);
                alert('Communication error with server while retrying ERP push.');
            } finally {
                this.retryingId = null;
            }
        },

        async bulkRetryErp() {
            if (this.bulkRetrying) return;
            if (!confirm('Are you sure you want to retry pushing ALL failed production batches to ERP?')) return;

            this.bulkRetrying = true;
            try {
                const response = await fetch(`{{ route('production.bulk-retry-erp') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                if (data.success) {
                    alert('✓ ' + data.message);
                    location.reload();
                } else {
                    alert('Notice: ' + data.message);
                    location.reload();
                }
            } catch (err) {
                console.error(err);
                alert('Communication error with server while performing bulk retry.');
            } finally {
                this.bulkRetrying = false;
            }
        },

        init() {
            // Initialization
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

        onTypeFilterChange() {
            if (this.typeFilter) {
                this.items.forEach(item => {
                    if (item.product_id) {
                        const match = this.filteredProducts.find(p => p.id == item.product_id);
                        if (!match) {
                            item.product_id = '';
                            item.product_name = '';
                            item.pack_size = '';
                            item.unit_box = 1;
                        }
                    }
                });
            }
            this.fetchConsolidatedRequirements();
        },

        openModal() {
            this.isEditing = false;
            this.editId = null;
            this.showModal = true;
            this.step = 1;
            this.typeFilter = '';
            this.branchCode = '2';
            this.branchName = 'Factory (Branch 2)';
            this.includePackaging = true; // Default ON
            this.includeFormulation = false; // Default OFF
            this.consolidatedRequirements = [];
            this.hasShortfall = false;
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
                unit_box: 1,
                quantity: '',
                batch_number: '',
                mfg_date: '{{ date("Y-m-d") }}',
                exp_date: '',
                requirements: [],
                loadingRequirements: false,
                isPossible: true,
                requirementsError: ''
            });
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
                this.fetchConsolidatedRequirements();
            }
        },

        updateProductInfo(index) {
            const item = this.items[index];
            const p = this.finishedGoods.find(p => p.id == item.product_id);
            if (p) {
                item.product_name = p.name;
                item.pack_size = p.pack_name || 'N/A';
                item.unit_box = p.unit_box || 1;
                
                // Set default EXP date to 1 year out
                if (item.mfg_date) {
                    const mfg = new Date(item.mfg_date);
                    mfg.setFullYear(mfg.getFullYear() + 1);
                    item.exp_date = mfg.toISOString().split('T')[0];
                }
            } else {
                item.product_name = '';
                item.pack_size = '';
                item.unit_box = 1;
            }
            this.fetchConsolidatedRequirements();
        },

        updateQuantity(index) {
            this.fetchConsolidatedRequirements();
        },

        calculateItemUnits(item) {
            if (!item.product_id || !item.quantity) return 0;
            const p = this.finishedGoods.find(p => p.id == item.product_id);
            const uBox = p && p.unit_box ? parseFloat(p.unit_box) : (item.unit_box || 1);
            return Math.round(parseFloat(item.quantity) * uBox);
        },

        get totalUnitsCount() {
            return this.items.reduce((sum, item) => sum + (this.calculateItemUnits(item) || 0), 0);
        },

        get totalQuantity() {
            return this.items.reduce((sum, item) => sum + (parseFloat(item.quantity) || 0), 0);
        },

        get formattedDate() {
            if (!this.productionDate) return '';
            const options = { day: 'numeric', month: 'short', year: 'numeric' };
            return new Date(this.productionDate).toLocaleDateString('en-GB', options);
        },

        fetchConsolidatedRequirements() {
            const validItems = this.items
                .filter(i => i.product_id && parseFloat(i.quantity) > 0)
                .map(i => ({ product_id: i.product_id, quantity: parseFloat(i.quantity) }));

            if (validItems.length === 0) {
                this.consolidatedRequirements = [];
                this.hasShortfall = false;
                return;
            }

            this.loadingConsolidated = true;
            fetch("{{ route('production.check-stock') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    branch_code: this.branchCode || '2',
                    include_packaging: this.includePackaging,
                    include_formulation: this.includeFormulation,
                    items: validItems
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.consolidatedRequirements = data.requirements || [];
                    this.hasShortfall = !data.possible;
                } else {
                    this.consolidatedRequirements = [];
                    this.hasShortfall = false;
                }
            })
            .catch(err => {
                console.error('Error fetching consolidated requirements:', err);
                this.consolidatedRequirements = [];
            })
            .finally(() => {
                this.loadingConsolidated = false;
            });
        },

        goToPreview() {
            if (!this.productionDate) {
                alert('Please select Production Date');
                return;
            }
            if (this.items.some(i => !i.product_id || !i.quantity || !i.batch_number || !i.mfg_date || !i.exp_date)) {
                alert('Please ensure all required fields (Product, Yield Box, Batch No, MFG Date, EXP Date) are filled.');
                return;
            }
            if (this.hasShortfall) {
                if (!confirm('Warning: There is a stock shortfall for some materials in Branch 2 (Factory). Do you still want to proceed to preview?')) {
                    return;
                }
            }
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
                        this.branchCode = p.branch_code || '2';
                        this.productionDate = p.production_date;
                        this.typeFilter = '';
                        this.includePackaging = true;
                        this.includeFormulation = false;
                        this.items = p.items.map(i => ({
                            product_id: i.product_id,
                            product_name: i.product ? i.product.name : '',
                            pack_size: i.product ? i.product.pack_name : 'N/A',
                            unit_box: i.product ? (i.product.unit_box || 1) : 1,
                            quantity: i.quantity_box,
                            batch_number: i.batch_number,
                            mfg_date: i.mfg_date,
                            exp_date: i.exp_date,
                            requirements: [],
                            loadingRequirements: false,
                            isPossible: true,
                            requirementsError: ''
                        }));
                        this.showModal = true;
                        this.step = 1;
                        this.fetchConsolidatedRequirements();
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to load production for editing');
                });
        },

        deleteProduction(id) {
            if (confirm('Are you sure you want to delete this production entry? Stock will be reverted back.')) {
                const form = document.getElementById('deleteForm');
                form.action = `{{ url('production') }}/${id}`;
                form.submit();
            }
        },

        viewDetail(id) {
            this.loadingDetail = true;
            this.selectedLog = null;
            this.selectedIssueItems = [];
            this.selectedReceiptDoc = '';
            this.selectedIssueDoc = '';

            fetch(`{{ url('production') }}/${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.selectedLog = data.production;
                        this.selectedIssueItems = data.issue_items || [];
                        this.selectedReceiptDoc = data.receipt_doc_no || '';
                        this.selectedIssueDoc = data.issue_doc_no || '';
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
            document.getElementById('form_branch').value = this.branchCode || '2';
            
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
                const packInput = document.createElement('input');
                packInput.type = 'hidden';
                packInput.name = `${prefix}[include_packaging]`;
                packInput.value = this.includePackaging ? '1' : '0';
                itemsContainer.appendChild(packInput);

                const formInput = document.createElement('input');
                formInput.type = 'hidden';
                formInput.name = `${prefix}[include_formulation]`;
                formInput.value = this.includeFormulation ? '1' : '0';
                itemsContainer.appendChild(formInput);
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
