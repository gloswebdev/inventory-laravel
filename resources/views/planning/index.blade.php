@extends('layouts.app')

@section('header', 'Indent Manager')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Left Panel: Bulk Entry (40%) -->
    <div class="lg:col-span-12 xl:col-span-5 flex flex-col gap-6">
        @if(Auth::user()->hasPermission('indent', 'create'))
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-bold flex items-center italic">
                    <i class="fas fa-list-check mr-2"></i> BULK INDENT ENTRY
                </h3>
                <div class="bg-white/20 text-white text-[10px] font-black px-2 py-1 rounded-lg uppercase tracking-widest">
                    Step 1: Enter Demand
                </div>
            </div>
            
            <div class="p-6 space-y-4">
                {{-- ... (Rest of the entry form content) ... --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-600 text-[10px] font-black uppercase tracking-widest mb-2 ml-1">Target Branch</label>
                        <select id="branch_code" class="w-full border border-gray-200 rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-indigo-500 outline-none transition bg-gray-50 text-sm font-bold" onchange="updateAllStock()">
                            <option value="">Consolidated View</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->code }}">{{ $branch->name }} ({{ $branch->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-600 text-[10px] font-black uppercase tracking-widest mb-2 ml-1">Indent Date</label>
                        <input type="date" id="indent_date" value="{{ date('Y-m-d') }}" class="w-full border border-gray-200 rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-indigo-500 outline-none transition bg-gray-50 text-sm font-bold">
                    </div>
                    <div>
                        <label class="block text-gray-600 text-[10px] font-black uppercase tracking-widest mb-2 ml-1">Order Unit (Global)</label>
                        <select id="global_unit" class="w-full border-2 border-indigo-200 rounded-xl py-2.4 px-4 focus:ring-2 focus:ring-indigo-500 outline-none transition bg-indigo-50 text-indigo-700 font-black text-sm uppercase" onchange="syncGlobalUnit()">
                            <option value="box">Boxes</option>
                            <option value="kg">KG / LTR</option>
                        </select>
                    </div>
                </div>

                <div class="border border-gray-100 rounded-2xl overflow-hidden mt-4">
                    <div class="max-h-[600px] overflow-y-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/80 sticky top-0 z-10 border-b border-gray-100">
                                    <th class="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest">Product Details</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Live Stock</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-widest w-32 text-right">Order Qty</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($finishedGoods as $product)
                                <tr class="product-row hover:bg-indigo-50/30 transition-colors" 
                                    data-id="{{ $product->id }}" 
                                    data-name="{{ $product->name }}"
                                    data-pack="{{ $product->pack_name }}"
                                    data-unit-box="{{ $product->unit_box ?: 1 }}"
                                    data-weight-unit="{{ $product->weight_unit ?: 1 }}"
                                    data-uom="{{ $product->uom }}">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-700 text-xs mb-0.5 line-clamp-1 truncate">{{ $product->name }}</div>
                                        <div class="text-[9px] font-black text-indigo-400 uppercase tracking-tighter flex items-center gap-1.5">
                                            <span>{{ $product->item_code }}</span>
                                            <span class="bg-indigo-50 text-indigo-500 px-1.5 py-0.5 rounded">{{ $product->pack_name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="stock-box text-[11px] font-black text-green-600 leading-none">0.00</div>
                                        <div class="text-[8px] font-black text-gray-400 uppercase tracking-tighter italic">BOX</div>
                                        <div class="stock-kg text-[9px] font-black text-gray-400 mt-1 leading-none">0.00</div>
                                        <div class="text-[7px] font-black text-gray-300 uppercase tracking-tighter italic">{{ $product->uom == 'Ltr' ? 'LTR' : 'KG' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <input type="number" 
                                               class="product-qty w-full border border-gray-200 rounded-lg py-2 px-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none text-right font-black" 
                                               placeholder="0" step="0.01">
                                        <div class="selected-unit-label text-[8px] font-bold text-indigo-400 mt-1 uppercase tracking-widest">BOXES</div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <button onclick="previewIndent()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 rounded-2xl shadow-lg shadow-indigo-200 transition-all transform hover:-translate-y-1 active:scale-[0.98] flex items-center justify-center gap-3 mt-4 group">
                    <span class="text-xs uppercase tracking-[0.2em]">Generate Indent Preview</span>
                    <i class="fas fa-file-invoice transition-transform group-hover:rotate-12"></i>
                </button>
            </div>
        </div>
        @else
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center flex flex-col items-center justify-center h-full opacity-60">
            <i class="fas fa-lock text-gray-200 text-5xl mb-4"></i>
            <h3 class="text-gray-400 font-black uppercase tracking-widest text-sm">Creation Restricted</h3>
            <p class="text-gray-400 text-xs mt-2 max-w-xs">You don't have permissions to create new indents.</p>
        </div>
        @endif
    </div>

    <!-- Right Panel: Invoice Preview (60%) -->
    <div class="lg:col-span-12 xl:col-span-7">
        <div id="previewContainer" class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden hidden animate-in fade-in slide-in-from-right duration-500 sticky top-6">
            <div id="invoiceHeader" class="bg-gray-900 px-8 py-10 text-white relative">
                <div class="absolute top-0 right-0 p-4 opacity-10">
                    <i class="fas fa-file-invoice text-9xl"></i>
                </div>
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <div class="text-3xl font-black italic tracking-tighter mb-1 uppercase">Material Indent</div>
                        <div class="text-indigo-400 font-bold tracking-widest text-[10px] uppercase">Internal Document</div>
                    </div>
                    <div class="text-right">
                        <div class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-1">Generated Date</div>
                        <div class="text-xl font-bold" id="displayDate">Feb 18, 2026</div>
                    </div>
                </div>
                <div class="mt-8 grid grid-cols-2 gap-8 border-t border-white/10 pt-8 relative z-10">
                    <div>
                        <div class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-1 italic">Target Branch</div>
                        <div class="text-xl font-bold" id="displayBranch">All Branches (Consolidated)</div>
                    </div>
                    <div class="text-right">
                        <div class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-1 italic">Status</div>
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-500/20 text-amber-300 rounded-full text-[10px] font-black uppercase tracking-widest">
                            <span class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></span>
                            Draft Preview
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <table class="w-full text-left">
                    <thead class="border-b-2 border-gray-900/5">
                        <tr>
                            <th class="py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Product Details</th>
                            <th class="py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center px-4">Live Stock</th>
                            <th class="py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center px-4">Requirement</th>
                            <th class="py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Final Boxes</th>
                        </tr>
                    </thead>
                    <tbody id="previewBody" class="divide-y divide-gray-50">
                        <!-- Preview rows injected here -->
                    </tbody>
                </table>

                <div class="mt-8 border-t-4 border-gray-900 pt-6">
                    <div class="flex justify-between items-center px-4">
                        <div>
                            <div class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1">Total Indent Volume</div>
                            <div class="text-xs text-gray-500 italic">Consolidated for all products</div>
                        </div>
                        <div class="text-right">
                            <div class="text-4xl font-black italic tracking-tighter" id="totalIndentBoxes">0.00</div>
                            <div class="text-[10px] font-black text-indigo-500 uppercase tracking-widest">TOTAL BOXES</div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-4 mt-8">
                    <button onclick="saveIndent()" class="flex-grow bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 rounded-2xl shadow-lg transition-all transform active:scale-95 flex items-center justify-center gap-3 uppercase tracking-widest text-xs">
                        <i class="fas fa-save"></i> Save & Lock Indent
                    </button>
                    <button onclick="document.getElementById('previewContainer').classList.add('hidden')" class="bg-gray-100 hover:bg-gray-200 text-gray-500 font-black py-4 px-6 rounded-2xl transition-all uppercase tracking-widest text-[10px]">
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <div id="noPreviewPlaceholder" class="bg-white rounded-2xl border-2 border-dashed border-gray-200 h-[600px] flex flex-col items-center justify-center text-center p-10 group opacity-50">
            <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                <i class="fas fa-file-invoice text-gray-300 text-4xl"></i>
            </div>
            <h3 class="text-gray-400 font-black uppercase tracking-widest text-sm mb-2">No Preview Generated</h3>
            <p class="text-gray-400 text-xs max-w-xs">Fill in your requirements on the left and click "Generate Indent Preview" to see the invoice format here.</p>
        </div>
    </div>

    <!-- History: Full Width (Bottom) -->
    <div class="lg:col-span-12 mt-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 px-8 py-6 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-gray-700 font-black flex items-center italic">
                    <i class="fas fa-history mr-3 text-indigo-500"></i> DATEWISE INDENT HISTORY
                </h3>
            </div>
            
            <!-- Filters -->
            <div class="px-8 py-6 bg-gray-50/50 border-b border-gray-100">
                <form action="{{ route('indent.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">From Date</label>
                        <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-2 font-bold text-gray-900 focus:ring-2 focus:ring-indigo-500 outline-none transition text-sm">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">To Date</label>
                        <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-2 font-bold text-gray-900 focus:ring-2 focus:ring-indigo-500 outline-none transition text-sm">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Target Branch</label>
                        <select name="branch_code" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-2 font-bold text-gray-900 focus:ring-2 focus:ring-indigo-500 outline-none transition text-sm">
                            <option value="">All Branches</option>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->code }}" {{ request('branch_code') == $branch->code ? 'selected' : '' }}>
                                {{ $branch->name }} ({{ $branch->code }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Creator</label>
                        <select name="user_id" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-2 font-bold text-gray-900 focus:ring-2 focus:ring-indigo-500 outline-none transition text-sm">
                            <option value="">All Users</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Status</label>
                        <select name="status" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-2 font-bold text-gray-900 focus:ring-2 focus:ring-indigo-500 outline-none transition text-sm">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="partly completed" {{ request('status') == 'partly completed' ? 'selected' : '' }}>Partly Completed</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Fully Completed</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-indigo-600 text-white px-6 py-2 rounded-xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-lg shadow-indigo-100 uppercase text-xs">
                            <i class="fas fa-filter mr-2"></i>Apply
                        </button>
                        <a href="{{ route('indent.index') }}" class="bg-gray-100 text-gray-500 px-4 py-2 rounded-xl flex items-center justify-center hover:bg-gray-200 transition border border-gray-200">
                            <i class="fas fa-redo-alt text-xs"></i>
                        </a>
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Indent Date</th>
                            <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Target Branch</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">User</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Status</th>
                            <th class="px-8 py-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Volume</th>
                            <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Total Boxes</th>
                            <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($history as $indent)
                        <tr class="hover:bg-indigo-50/20 transition-colors group">
                            <td class="px-8 py-4 font-bold text-gray-700">{{ date('d M, Y', strtotime($indent->indent_date)) }}</td>
                            <td class="px-8 py-4">
                                <span class="bg-indigo-50 text-indigo-600 font-bold px-3 py-1 rounded-lg text-xs">
                                    {{ $indent->branch_name }} ({{ $indent->branch_code }})
                                </span>
                            </td>
                            <td class="px-8 py-4 text-center">
                                <div class="text-xs font-black text-gray-600 uppercase tracking-tighter">{{ $indent->user->name ?? 'System' }}</div>
                                <div class="text-[8px] text-gray-400 italic font-bold">Creator</div>
                            </td>
                            <td class="px-8 py-4 text-center">
                                @if($indent->status == 'completed')
                                <span class="bg-green-100 text-green-600 px-2 py-1 rounded-lg text-[10px] font-black tracking-tighter uppercase border border-green-200">FULLY COMPLETED</span>
                                @elseif($indent->status == 'partly completed')
                                <span class="bg-blue-100 text-blue-600 px-2 py-1 rounded-lg text-[10px] font-black tracking-tighter uppercase border border-blue-200 italic">PARTLY COMPLETED</span>
                                @else
                                <span class="bg-amber-100 text-amber-600 px-2 py-1 rounded-lg text-[10px] font-black tracking-tighter uppercase border border-amber-200 italic">PENDING</span>
                                @endif
                            </td>
                            <td class="px-8 py-4 text-xs text-gray-500 italic text-right">
                                @php $itemNames = $indent->items->take(2)->pluck('product_name')->toArray(); @endphp
                                {{ implode(', ', $itemNames) }} {{ $indent->items->count() > 2 ? '... (+' . ($indent->items->count() - 2) . ' more)' : '' }}
                            </td>
                            <td class="px-8 py-4 text-right">
                                <span class="text-lg font-black italic tracking-tighter text-gray-800">{{ number_format($indent->total_boxes, 0) }}</span>
                                <span class="text-[9px] font-black text-gray-400 block -mt-1 uppercase tracking-tight">Boxes</span>
                            </td>
                            <td class="px-8 py-4 text-right">
                                <div class="flex justify-end gap-1.5">
                                    <button onclick="viewIndent({{ $indent->id }})" title="View" class="bg-indigo-100 text-indigo-600 p-2 rounded-lg hover:bg-indigo-600 hover:text-white transition"><i class="fas fa-eye text-xs"></i></button>
                                    <button onclick="viewProgress({{ $indent->id }})" title="View Progress (Asked vs Completed)" class="bg-blue-100 text-blue-600 p-2 rounded-lg hover:bg-blue-600 hover:text-white transition"><i class="fas fa-list-check text-xs"></i></button>
                                    
                                    @if(Auth::user()->hasPermission('planning_bulk', 'print'))
                                    <button onclick="printIndent({{ $indent->id }})" title="Print" class="bg-indigo-100 text-indigo-600 p-2 rounded-lg hover:bg-indigo-600 hover:text-white transition"><i class="fas fa-print text-xs"></i></button>
                                    @endif
                                    
                                    @if(Auth::user()->hasPermission('planning_bulk', 'excel'))
                                    <button onclick="exportExcel({{ $indent->id }})" title="Excel" class="bg-green-100 text-green-600 p-2 rounded-lg hover:bg-green-600 hover:text-white transition"><i class="fas fa-file-excel text-xs"></i></button>
                                    @endif
                                    
                                    @if(Auth::user()->hasPermission('planning_bulk', 'pdf'))
                                    <button onclick="exportPdf({{ $indent->id }})" title="PDF" class="bg-red-100 text-red-600 p-2 rounded-lg hover:bg-red-600 hover:text-white transition"><i class="fas fa-file-pdf text-xs"></i></button>
                                    @endif

                                    @if(Auth::user()->hasPermission('planning_process', 'edit'))
                                    <a href="{{ route('indent.process', $indent->id) }}" title="Process" class="bg-amber-100 text-amber-600 p-2 rounded-lg hover:bg-amber-600 hover:text-white transition"><i class="fas fa-cog text-xs"></i></a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-8 py-10 text-center text-gray-400 italic">No previous indents found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Indent Modal -->
<div id="viewModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-6 border-b flex justify-between items-center bg-gray-50">
            <div>
                <h3 class="text-xl font-black text-gray-800 italic">Indent Details</h3>
                <p id="modalBranch" class="text-xs font-bold text-indigo-600"></p>
            </div>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition p-2 hover:bg-gray-100 rounded-full">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="py-3 text-[10px] font-black text-gray-400 uppercase">Product</th>
                        <th class="py-3 text-[10px] font-black text-gray-400 uppercase text-center">Live Stock</th>
                        <th class="py-3 text-[10px] font-black text-gray-400 uppercase text-center">Required</th>
                        <th class="py-3 text-[10px] font-black text-gray-400 uppercase text-right">Final Boxes</th>
                    </tr>
                </thead>
                <tbody id="modalTableBody">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
        </div>
        <div class="p-6 border-t bg-gray-50 flex justify-between items-center">
            <div class="text-xs text-gray-400 font-bold uppercase italic" id="modalMeta"></div>
            <div class="flex gap-3">
                @if(Auth::user()->hasPermission('planning_bulk', 'print'))
                <button id="modalPrintBtn" class="bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                    <i class="fas fa-print mr-2"></i>PRINT INDENT
                </button>
                @endif
                <button onclick="closeModal()" class="bg-white border-2 border-gray-200 text-gray-600 px-6 py-2.5 rounded-xl font-black italic tracking-tighter hover:bg-gray-50 transition">
                    CLOSE
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Comparison / Progress Modal -->
<div id="progressModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col" @click.away="closeProgressModal()">
        <div class="p-8 border-b flex justify-between items-center bg-indigo-600 text-white">
            <div>
                <h3 class="text-2xl font-black italic tracking-tighter uppercase">Indent Completion Progress</h3>
                <p id="progressModalBranch" class="text-xs font-bold text-indigo-200 uppercase tracking-widest mt-1"></p>
            </div>
            <button onclick="closeProgressModal()" class="text-white/50 hover:text-white transition p-2 hover:bg-white/10 rounded-full">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto custom-scrollbar flex-1">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="py-4 text-[11px] font-black text-gray-400 uppercase tracking-widest">Product</th>
                        <th class="py-4 text-[11px] font-black text-gray-400 uppercase tracking-widest text-center">Asked (Box)</th>
                        <th class="py-4 text-[11px] font-black text-gray-400 uppercase tracking-widest text-center">Completed (Box)</th>
                        <th class="py-4 text-[11px] font-black text-gray-400 uppercase tracking-widest text-right">Status</th>
                    </tr>
                </thead>
                <tbody id="progressTableBody">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
        </div>
        <div class="p-8 border-t bg-gray-50 flex justify-end">
            <button onclick="closeProgressModal()" class="bg-indigo-600 text-white px-12 py-4 rounded-2xl font-black italic tracking-tighter hover:bg-indigo-700 transition shadow-xl shadow-indigo-100 uppercase">
                Got it
            </button>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>

<script>
    let stockCache = {};

    function syncGlobalUnit() {
        const unit = document.getElementById('global_unit').value;
        const labels = document.querySelectorAll('.selected-unit-label');
        labels.forEach(label => {
            label.innerText = unit === 'box' ? 'BOXES' : 'KG / LTR';
        });
    }

    // Global Bulk Stock Fetch
    async function updateAllStock() {
        const branchCode = document.getElementById('branch_code').value;
        const rows = document.querySelectorAll('.product-row');
        
        // Show loading state
        rows.forEach(r => r.style.opacity = '0.5');

        try {
            const res = await fetch(`{{ route('indent.bulk-stock') }}?branch_code=${branchCode}`);
            const data = await res.json();
            
            if (data.success && data.stocks) {
                stockCache = data.stocks;
                
                rows.forEach(row => {
                    const pId = row.dataset.id;
                    const stock = data.stocks[pId] || { stock: 0, stock_boxes: 0 };
                    
                    row.querySelector('.stock-box').innerText = parseFloat(stock.stock_boxes).toFixed(2);
                    row.querySelector('.stock-kg').innerText = parseFloat(stock.stock).toFixed(2);
                });
            }
        } catch(e) { 
            console.error("Bulk stock fetch error", e); 
        } finally {
            rows.forEach(r => r.style.opacity = '1');
        }
    }

    // Call once on load
    document.addEventListener('DOMContentLoaded', updateAllStock);

    async function previewIndent() {
        const rows = document.querySelectorAll('.product-row');
        const previewBody = document.getElementById('previewBody');
        const unit = document.getElementById('global_unit').value;
        previewBody.innerHTML = '';
        
        let hasData = false;
        let grandTotalBoxes = 0;

        for (let row of rows) {
            const qtyInput = row.querySelector('.product-qty');
            const qtyValue = parseFloat(qtyInput.value || 0);
            
            if (qtyValue > 0) {
                hasData = true;
                const pId = row.dataset.id;
                const pName = row.dataset.name;
                const pPack = row.dataset.pack;
                const unitBox = parseFloat(row.dataset.unitBox);
                const weightUnit = parseFloat(row.dataset.weightUnit);
                
                // Calculate Final Boxes
                let finalBoxes = 0;
                if (unit === 'box') {
                    finalBoxes = qtyValue;
                } else {
                    // Convert KG to Boxes
                    const weightPerBox = unitBox * weightUnit;
                    finalBoxes = weightPerBox > 0 ? (qtyValue / weightPerBox) : 0;
                }

                // Round to nearest integer (Standard Rounding: 10.1 -> 10, 10.5 -> 11)
                finalBoxes = Math.round(finalBoxes);

                grandTotalBoxes += finalBoxes;
                
                const stock = stockCache[pId] || {stock_boxes: 0, stock: 0};

                const tr = document.createElement('tr');
                tr.className = 'hover:bg-indigo-50/20 transition-colors';
                tr.innerHTML = `
                    <td class="py-4">
                        <div class="font-bold text-gray-800 text-sm italic">${pName}</div>
                        <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest">${pPack}</div>
                    </td>
                    <td class="py-4 text-center px-4">
                        <div class="text-xs font-black text-green-600">${parseFloat(stock.stock_boxes).toFixed(2)} BOX</div>
                        <div class="text-[9px] font-black text-gray-400 italic">${parseFloat(stock.stock).toFixed(2)} KG</div>
                    </td>
                    <td class="py-4 text-center px-4">
                        <div class="text-xs font-black text-gray-700">${qtyValue} <span class="uppercase">${unit}</span></div>
                    </td>
                    <td class="py-4 text-right">
                        <div class="text-lg font-black italic tracking-tighter text-gray-900">${finalBoxes.toFixed(0)}</div>
                        <div class="text-[8px] font-black text-indigo-400 uppercase tracking-widest">EST. BOXES</div>
                    </td>
                `;
                tr.dataset.pId = pId;
                tr.dataset.pName = pName;
                tr.dataset.qty = qtyValue;
                tr.dataset.unit = unit;
                tr.dataset.stockBox = stock.stock_boxes;
                tr.dataset.stockKg = stock.stock;
                tr.dataset.finalBoxes = finalBoxes;

                previewBody.appendChild(tr);
            }
        }

        if (!hasData) {
            alert('Please enter quantity for at least one product.');
            return;
        }

        const indentDateVal = document.getElementById('indent_date').value;
        const displayDate = document.getElementById('displayDate');
        if (displayDate && indentDateVal) {
            displayDate.innerText = new Date(indentDateVal).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
        }
        
        const branchSelect = document.getElementById('branch_code');
        const displayBranch = document.getElementById('displayBranch');
        if (displayBranch && branchSelect) {
            displayBranch.innerText = branchSelect.options[branchSelect.selectedIndex].text;
        }
        
        const totalIndentBoxes = document.getElementById('totalIndentBoxes');
        if (totalIndentBoxes) {
            totalIndentBoxes.innerText = grandTotalBoxes.toFixed(2);
        }

        const noPreview = document.getElementById('noPreviewPlaceholder');
        const previewCont = document.getElementById('previewContainer');
        
        if (noPreview) noPreview.classList.add('hidden');
        if (previewCont) {
            previewCont.classList.remove('hidden');
            // Force block display if hidden class is not enough
            previewCont.style.display = 'block';
        }
        
        // Scroll to preview on mobile
        if (window.innerWidth < 1024 && previewCont) {
            previewCont.scrollIntoView({ behavior: 'smooth' });
        }
    }

    // Modal Control
    function viewIndent(id) {
        const modal = document.getElementById('viewModal');
        const loader = `<tr class="modal-loading"><td colspan="4" class="py-10 text-center text-gray-400 italic">Loading details...</td></tr>`;
        document.getElementById('modalTableBody').innerHTML = loader;
        modal.classList.remove('hidden');

        fetch(`{{ url('indent/show') }}/${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const indent = data.indent;
                    document.getElementById('modalBranch').innerText = `${indent.branch_name} (${indent.branch_code}) - ${new Date(indent.indent_date).toLocaleDateString()}`;
                    document.getElementById('modalMeta').innerText = `Created by: ${indent.user?.name || 'System'} | ID: #${String(indent.id).padStart(5, '0')}`;
                    
                    let html = '';
                    indent.items.forEach(item => {
                        html += `
                            <tr class="border-b border-gray-50">
                                <td class="py-4 font-bold text-gray-800 text-sm italic">
                                    ${item.product_name}
                                    <div class="text-[9px] text-gray-400 uppercase">${item.product?.pack_name || ''}</div>
                                </td>
                                <td class="py-4 text-center">
                                    <div class="text-xs font-black text-green-600">${parseFloat(item.stock_box).toFixed(2)} BOX</div>
                                </td>
                                <td class="py-4 text-center font-bold text-gray-700">
                                    ${item.demand_qty} ${item.demand_unit.toUpperCase()}
                                </td>
                                <td class="py-4 text-right font-black text-lg italic text-indigo-600">
                                    ${parseFloat(item.final_qty_box).toFixed(0)}
                                </td>
                            </tr>
                        `;
                    });
                    document.getElementById('modalTableBody').innerHTML = html;
                    document.getElementById('modalPrintBtn').onclick = () => printIndent(id);
                }
            })
            .catch(e => {
                console.error(e);
                alert('Error loading indent details');
                closeModal();
            });
    }

    function closeModal() {
        document.getElementById('viewModal').classList.add('hidden');
    }

    function viewProgress(id) {
        const modal = document.getElementById('progressModal');
        const loader = `<tr><td colspan="4" class="py-10 text-center text-gray-400 italic">Analyzing progress...</td></tr>`;
        document.getElementById('progressTableBody').innerHTML = loader;
        modal.classList.remove('hidden');

        fetch(`{{ url('indent/show') }}/${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const indent = data.indent;
                    document.getElementById('progressModalBranch').innerText = `${indent.branch_name} (${indent.branch_code}) | ${new Date(indent.indent_date).toLocaleDateString()}`;
                    
                    let html = '';
                    indent.items.forEach(item => {
                        const asked = parseFloat(item.final_qty_box);
                        const completed = parseFloat(item.completed_qty || 0);
                        let statusHtml = '';
                        
                        if (completed >= asked && asked > 0) {
                            statusHtml = '<span class="text-green-600 font-black italic text-[10px] uppercase tracking-tighter">● FULLY DONE</span>';
                        } else if (completed > 0) {
                            statusHtml = '<span class="text-blue-500 font-black italic text-[10px] uppercase tracking-tighter">◌ PARTIAL (' + Math.round((completed/asked)*100) + '%)</span>';
                        } else {
                            statusHtml = '<span class="text-amber-500 font-black italic text-[10px] uppercase tracking-tighter italic">◌ PENDING</span>';
                        }

                        html += `
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-5 font-bold text-gray-800 text-sm italic">
                                    ${item.product_name}
                                    <div class="text-[9px] text-gray-400 uppercase font-black tracking-widest">${item.product?.pack_name || ''}</div>
                                </td>
                                <td class="py-5 text-center font-black text-gray-500 text-lg">${asked.toFixed(0)}</td>
                                <td class="py-5 text-center font-black text-indigo-600 text-xl italic">${completed.toFixed(0)}</td>
                                <td class="py-5 text-right">${statusHtml}</td>
                            </tr>
                        `;
                    });
                    document.getElementById('progressTableBody').innerHTML = html;
                }
            })
            .catch(e => {
                console.error(e);
                alert('Error loading progress details');
                closeProgressModal();
            });
    }

    function closeProgressModal() {
        document.getElementById('progressModal').classList.add('hidden');
    }

    function printIndent(id) {
        window.open(`{{ url('indent/show') }}/${id}/print`, '_blank');
    }

    function exportExcel(id) {
        window.location.href = `{{ url('indent/show') }}/${id}/excel`;
    }

    function exportPdf(id) {
        window.location.href = `{{ url('indent/show') }}/${id}/pdf`;
    }

    async function saveIndent() {
        const previewRows = document.getElementById('previewBody').querySelectorAll('tr');
        const products = [];
        
        previewRows.forEach(row => {
            products.push({
                id: row.dataset.pId,
                name: row.dataset.pName,
                demand_qty: row.dataset.qty,
                unit: row.dataset.unit,
                stock_box: row.dataset.stockBox,
                stock_kg: row.dataset.stockKg,
                final_qty_box: row.dataset.finalBoxes
            });
        });

        const payload = {
            branch_code: document.getElementById('branch_code').value,
            indent_date: document.getElementById('indent_date').value,
            products: products,
            _token: '{{ csrf_token() }}'
        };

        try {
            const res = await fetch('{{ route("indent.store") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                alert('Indent saved successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to save indent'));
            }
        } catch (e) {
            alert('Communication error with server.');
        }
    }
</script>
@endsection
