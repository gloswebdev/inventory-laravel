@extends('layouts.app')

@section('header', 'Indent Manager')

@section('content')
@php
    if (!function_exists('getPackBadgeClass')) {
        function getPackBadgeClass($packName) {
            if (!$packName) return 'bg-slate-50 text-slate-500 border-slate-200';
            $name = strtoupper(trim($packName));
            if (str_contains($name, '1 KG') || str_contains($name, '1 LTR') || str_contains($name, '1KG') || str_contains($name, '1LTR')) {
                return 'bg-indigo-50 text-indigo-700 border-indigo-200/60';
            }
            if (str_contains($name, '500 GM') || str_contains($name, '500 ML') || str_contains($name, '500GM') || str_contains($name, '500ML')) {
                return 'bg-emerald-50 text-emerald-700 border-emerald-200/60';
            }
            if (str_contains($name, '250 GM') || str_contains($name, '250 ML') || str_contains($name, '250GM') || str_contains($name, '250ML')) {
                return 'bg-rose-50 text-rose-700 border-rose-200/60';
            }
            if (str_contains($name, '100 GM') || str_contains($name, '100 ML') || str_contains($name, '100GM') || str_contains($name, '100ML')) {
                return 'bg-amber-50 text-amber-700 border-amber-200/60';
            }
            if (str_contains($name, '50 GM') || str_contains($name, '50 ML') || str_contains($name, '50GM') || str_contains($name, '50ML')) {
                return 'bg-cyan-50 text-cyan-700 border-cyan-200/60';
            }
            if (str_contains($name, '5 LTR') || str_contains($name, '5 KG') || str_contains($name, '5LTR') || str_contains($name, '5KG')) {
                return 'bg-teal-50 text-teal-700 border-teal-200/60';
            }
            return 'bg-violet-50 text-violet-700 border-violet-200/60';
        }
    }
@endphp

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Left Panel: Bulk Entry (40%) -->
    <div class="lg:col-span-12 xl:col-span-5 flex flex-col gap-6">
        @if(Auth::user()->hasPermission('indent', 'create'))
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 overflow-hidden flex flex-col">
            <div class="bg-white px-7 py-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-200/50 flex-shrink-0">
                        <i class="fas fa-list-check text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-slate-800 tracking-tight leading-tight" id="entryTitle">Bulk Indent Entry</h3>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Step 1: Enter Demand</p>
                    </div>
                </div>
                <div id="editBadge" class="hidden bg-amber-50 text-amber-700 text-[10px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest border border-amber-200 shadow-sm">
                    Editing Mode
                </div>
            </div>
            
            <div class="p-7 bg-slate-50/30 flex-grow space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1.5 ml-1">Target Branch</label>
                        <select id="branch_code" class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-xs font-bold text-slate-700 shadow-sm" onchange="updateAllStock()">
                            <option value="">Consolidated View</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->code }}">{{ $branch->name }} ({{ $branch->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1.5 ml-1">Indent Date</label>
                        <input type="date" id="indent_date" value="{{ date('Y-m-d') }}" class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-xs font-bold text-slate-700 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1.5 ml-1">Order Unit</label>
                        <select id="global_unit" class="w-full bg-indigo-50 border border-indigo-200 rounded-xl py-2.5 px-3 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-xs font-black text-indigo-700 uppercase shadow-sm" onchange="syncGlobalUnit()">
                            <option value="box">Boxes</option>
                            <option value="kg">KG / LTR</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1.5 ml-1">Item Type</label>
                        <div class="w-full bg-emerald-50 border border-emerald-200 rounded-xl py-2.5 px-3 text-xs font-black text-emerald-700 shadow-sm flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                            Finished Good
                        </div>
                    </div>
                </div>

                <!-- Product Search Filter -->
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-search text-slate-400 group-focus-within:text-indigo-500 transition-colors"></i>
                    </div>
                    <input type="text" id="productSearchInput" oninput="filterProducts()" placeholder="Search product name or code..." class="w-full bg-white border border-slate-200 rounded-xl py-3 pl-11 pr-4 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-xs font-bold text-slate-700 shadow-sm" />
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                    <div class="max-h-[600px] overflow-y-auto overflow-x-hidden custom-scrollbar">
                        <table class="w-full text-left border-collapse relative table-fixed">
                            <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-200">
                                <tr>
                                    <th class="px-2 sm:px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200 w-1/2">Product Details</th>
                                    <th class="px-2 sm:px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center border-b border-slate-200 w-[20%]">Live Stock</th>
                                    <th class="px-2 sm:px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right border-b border-slate-200 w-[30%]">Order Qty</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($finishedGoods as $product)
                                <tr class="product-row hover:bg-slate-50/70 transition-colors group" 
                                    data-id="{{ $product->id }}" 
                                    data-name="{{ $product->name }}"
                                    data-code="{{ $product->item_code }}"
                                    data-pack="{{ $product->pack_name }}"
                                    data-unit-box="{{ $product->unit_box ?: 1 }}"
                                    data-weight-unit="{{ $product->weight_multiplier }}"
                                    data-uom="{{ $product->uom }}"
                                    data-type-id="{{ $product->product_type_id }}">
                                    <td class="px-2 sm:px-5 py-3 truncate">
                                        <div class="font-bold text-slate-700 text-xs sm:text-sm mb-1 truncate group-hover:text-indigo-700 transition-colors" title="{{ $product->name }}">{{ $product->name }}</div>
                                        <div class="flex flex-wrap items-center gap-1 sm:gap-2 mt-1">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] sm:text-[9px] font-mono font-bold bg-indigo-50 text-indigo-600 border border-indigo-100">{{ $product->item_code }}</span>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] sm:text-[9px] font-bold border {{ getPackBadgeClass($product->pack_name) }}">{{ $product->pack_name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-2 sm:px-5 py-3 text-center">
                                        <div class="stock-box text-[10px] sm:text-[11px] font-black text-emerald-600 leading-none">0.00</div>
                                        <div class="text-[7px] sm:text-[8px] font-black text-slate-400 uppercase tracking-widest mt-0.5">BOX</div>
                                        <div class="stock-kg text-[8px] sm:text-[9px] font-black text-slate-400 mt-1.5 leading-none">0.00</div>
                                        <div class="text-[7px] sm:text-[8px] font-black text-slate-300 uppercase tracking-widest">{{ $product->uom == 'Ltr' ? 'LTR' : 'KG' }}</div>
                                    </td>
                                    <td class="px-2 sm:px-5 py-3 text-right">
                                        <input type="number" 
                                               class="product-qty w-full max-w-[100px] ml-auto bg-slate-50 border border-slate-200 rounded-xl py-2 px-2 sm:px-3 text-xs sm:text-sm font-mono font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none text-right transition-all" 
                                               placeholder="0" step="1" min="0">
                                        <div class="selected-unit-label text-[8px] sm:text-[9px] font-bold text-indigo-500 mt-1.5 uppercase tracking-widest">BOXES</div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <button onclick="previewIndent()" class="w-full bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-indigo-200/50 transition duration-200 flex justify-center items-center gap-2 text-sm uppercase tracking-wider group mt-2">
                    <span>Generate Indent Preview</span>
                    <i class="fas fa-file-invoice transition-transform group-hover:rotate-12 text-lg"></i>
                </button>
            </div>
        </div>
        @else
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 p-8 text-center flex flex-col items-center justify-center h-full opacity-60">
            <i class="fas fa-lock text-slate-200 text-5xl mb-4"></i>
            <h3 class="text-slate-400 font-black uppercase tracking-widest text-sm">Creation Restricted</h3>
            <p class="text-slate-400 text-xs mt-2 max-w-xs">You don't have permissions to create new indents.</p>
        </div>
        @endif
    </div>

    <!-- Right Panel: Invoice Preview (60%) -->
    <div class="lg:col-span-12 xl:col-span-7">
        <div id="previewContainer" class="bg-white rounded-3xl shadow-xl border border-slate-100/80 overflow-hidden hidden animate-in fade-in slide-in-from-right duration-500 sticky top-6 flex-col">
            <div id="invoiceHeader" class="bg-slate-900 px-8 py-10 text-white relative">
                <div class="absolute top-0 right-0 p-4 opacity-10">
                    <i class="fas fa-file-invoice text-9xl"></i>
                </div>
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <div class="text-3xl font-black italic tracking-tighter mb-1 uppercase">Material Indent</div>
                        <div class="text-indigo-400 font-bold tracking-widest text-[10px] uppercase">Internal Document</div>
                    </div>
                    <div class="text-right">
                        <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Generated Date</div>
                        <div class="text-xl font-bold" id="displayDate">Feb 18, 2026</div>
                    </div>
                </div>
                <div class="mt-8 grid grid-cols-2 gap-8 border-t border-white/10 pt-8 relative z-10">
                    <div>
                        <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1 italic">Target Branch</div>
                        <div class="text-xl font-bold" id="displayBranch">All Branches (Consolidated)</div>
                    </div>
                    <div class="text-right">
                        <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1 italic">Status</div>
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-500/20 text-amber-300 rounded-full text-[10px] font-black uppercase tracking-widest">
                            <span class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></span>
                            Draft Preview
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8 bg-slate-50/30 flex-grow">
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest px-6">Product Details</th>
                                <th class="py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center px-4">Live Stock</th>
                                <th class="py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center px-4">Requirement</th>
                                <th class="py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right px-6">Final Boxes</th>
                            </tr>
                        </thead>
                        <tbody id="previewBody" class="divide-y divide-slate-100">
                            <!-- Preview rows injected here -->
                        </tbody>
                    </table>
                </div>

                <div class="mt-8 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Indent Volume</div>
                            <div class="text-xs text-slate-400 italic">Consolidated for all products</div>
                        </div>
                        <div class="text-right">
                            <div class="text-4xl font-black tracking-tight text-slate-800" id="totalIndentBoxes">0.00</div>
                            <div class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mt-1">TOTAL BOXES</div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-4 mt-8">
                    <button onclick="saveIndent()" class="flex-grow bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-emerald-200/50 transition duration-200 flex items-center justify-center gap-2 text-sm uppercase tracking-wider">
                        <i class="fas fa-save"></i> Save & Lock Indent
                    </button>
                    <button onclick="document.getElementById('previewContainer').classList.add('hidden')" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold py-4 px-8 rounded-xl transition duration-200 text-sm uppercase tracking-wider shadow-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <div id="noPreviewPlaceholder" class="bg-white rounded-3xl border border-dashed border-slate-300 h-[600px] flex flex-col items-center justify-center text-center p-10 group opacity-70">
            <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                <i class="fas fa-file-invoice text-slate-300 text-4xl"></i>
            </div>
            <h3 class="text-slate-500 font-black uppercase tracking-widest text-sm mb-2">No Preview Generated</h3>
            <p class="text-slate-400 text-xs max-w-xs">Fill in your requirements on the left and click "Generate Indent Preview" to see the invoice format here.</p>
        </div>
    </div>

    <!-- History: Full Width (Bottom) -->
    <div class="lg:col-span-12 mt-6">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 overflow-hidden flex flex-col">
            <div class="bg-white px-7 py-5 border-b border-slate-100 flex justify-between items-center z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center shadow-lg shadow-indigo-200/50 flex-shrink-0">
                        <i class="fas fa-history text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-slate-800 tracking-tight leading-tight">Datewise Indent History</h3>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Past indent records</p>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="px-7 py-5 bg-slate-50/50 border-b border-slate-100">
                <form action="{{ route('indent.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">From Date</label>
                        <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm shadow-sm">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">To Date</label>
                        <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm shadow-sm">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Target Branch</label>
                        <select name="branch_code" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm shadow-sm">
                            <option value="">All Branches</option>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->code }}" {{ request('branch_code') == $branch->code ? 'selected' : '' }}>
                                {{ $branch->name }} ({{ $branch->code }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Creator</label>
                        <select name="user_id" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm shadow-sm">
                            <option value="">All Users</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Status</label>
                        <select name="status" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm shadow-sm">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="partly completed" {{ request('status') == 'partly completed' ? 'selected' : '' }}>Partly Completed</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Fully Completed</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white px-6 py-2.5 rounded-xl font-bold transition shadow-lg shadow-indigo-200/50 uppercase text-xs tracking-wider">
                            <i class="fas fa-filter mr-2"></i>Apply
                        </button>
                        <a href="{{ route('indent.index') }}" class="bg-white border border-slate-200 text-slate-500 px-4 py-2.5 rounded-xl flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
                            <i class="fas fa-redo-alt text-xs"></i>
                        </a>
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto relative">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-200">
                        <tr>
                            <th class="px-7 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Indent ID</th>
                            <th class="px-7 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Indent Date</th>
                            <th class="px-7 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Target Branch</th>
                            <th class="px-7 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center border-b border-slate-200">User</th>
                            <th class="px-7 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center border-b border-slate-200">Status</th>
                            <th class="px-7 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right border-b border-slate-200">Volume</th>
                            <th class="px-7 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right border-b border-slate-200">Total Boxes</th>
                            <th class="px-7 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right border-b border-slate-200">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($history as $indent)
                        <tr class="hover:bg-slate-50/70 transition-colors group">
                            <td class="px-7 py-4 font-mono font-black text-indigo-600 tracking-tight">#IND-{{ $indent->id }}</td>
                            <td class="px-7 py-4 font-bold text-slate-700 text-sm">{{ date('d M, Y', strtotime($indent->indent_date)) }}</td>
                            <td class="px-7 py-4">
                                <span class="bg-slate-100 text-slate-600 font-bold px-3 py-1.5 rounded-lg text-xs">
                                    {{ $indent->branch_name }} <span class="text-[10px] text-slate-400 ml-1">({{ $indent->branch_code }})</span>
                                </span>
                            </td>
                            <td class="px-7 py-4 text-center">
                                <div class="text-sm font-bold text-slate-700">{{ $indent->user->name ?? 'System' }}</div>
                                <div class="text-[9px] text-slate-400 uppercase tracking-widest font-semibold mt-0.5">Creator</div>
                            </td>
                            <td class="px-7 py-4 text-center">
                                @if($indent->status == 'completed')
                                <span class="bg-emerald-50 text-emerald-600 px-2.5 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase border border-emerald-200">Completed</span>
                                @elseif($indent->status == 'partly completed')
                                <span class="bg-blue-50 text-blue-600 px-2.5 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase border border-blue-200">Partial</span>
                                @else
                                <span class="bg-amber-50 text-amber-600 px-2.5 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase border border-amber-200">Pending</span>
                                @endif
                            </td>
                            <td class="px-7 py-4 text-xs text-slate-500 text-right">
                                @php $itemNames = $indent->items->take(2)->pluck('product_name')->toArray(); @endphp
                                {{ implode(', ', $itemNames) }} {{ $indent->items->count() > 2 ? '... (+' . ($indent->items->count() - 2) . ' more)' : '' }}
                            </td>
                            <td class="px-7 py-4 text-right">
                                <span class="text-lg font-black text-slate-800">{{ number_format($indent->total_boxes, 0) }}</span>
                                <span class="text-[9px] font-bold text-slate-400 block uppercase tracking-widest mt-0.5">Boxes</span>
                            </td>
                            <td class="px-7 py-4 text-right">
                                <div class="flex justify-end gap-2 transition-opacity">
                                    <button onclick="viewIndent({{ $indent->id }})" title="View" class="w-8 h-8 flex items-center justify-center bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition shadow-sm border border-indigo-100/50"><i class="fas fa-eye text-xs"></i></button>
                                    <button onclick="viewProgress({{ $indent->id }})" title="View Progress (Asked vs Completed)" class="w-8 h-8 flex items-center justify-center bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition shadow-sm border border-blue-100/50"><i class="fas fa-list-check text-xs"></i></button>
                                    
                                    @if(Auth::user()->hasPermission('planning_bulk', 'print'))
                                    <button onclick="printIndent({{ $indent->id }})" title="Print" class="w-8 h-8 flex items-center justify-center bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition shadow-sm border border-indigo-100/50"><i class="fas fa-print text-xs"></i></button>
                                    @endif
                                    
                                    @if(Auth::user()->hasPermission('planning_bulk', 'excel'))
                                    <button onclick="exportExcel({{ $indent->id }})" title="Excel" class="w-8 h-8 flex items-center justify-center bg-green-50 text-green-600 rounded-lg hover:bg-green-600 hover:text-white transition shadow-sm border border-green-100/50"><i class="fas fa-file-excel text-xs"></i></button>
                                    @endif
                                    
                                    @if(Auth::user()->hasPermission('planning_bulk', 'pdf'))
                                    <button onclick="exportPdf({{ $indent->id }})" title="PDF" class="w-8 h-8 flex items-center justify-center bg-red-50 text-red-600 rounded-lg hover:bg-red-600 hover:text-white transition shadow-sm border border-red-100/50"><i class="fas fa-file-pdf text-xs"></i></button>
                                    @endif

                                    @if(Auth::user()->hasPermission('planning_process', 'edit'))
                                    <a href="{{ route('indent.process', $indent->id) }}" title="Process" class="w-8 h-8 flex items-center justify-center bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-600 hover:text-white transition shadow-sm border border-amber-100/50"><i class="fas fa-cog text-xs"></i></a>
                                    @endif

                                    @if(Auth::user()->hasFeature('indent', 'clone'))
                                    <button onclick="cloneIndent({{ $indent->id }})" title="Clone" class="w-8 h-8 flex items-center justify-center bg-violet-50 text-violet-600 rounded-lg hover:bg-violet-600 hover:text-white transition shadow-sm border border-violet-100/50"><i class="fas fa-copy text-xs"></i></button>
                                    @endif

                                    @if(Auth::user()->hasPermission('indent', 'edit'))
                                    <button onclick="editIndent({{ $indent->id }})" title="Edit" class="w-8 h-8 flex items-center justify-center bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-600 hover:text-white transition shadow-sm border border-slate-200"><i class="fas fa-edit text-xs"></i></button>
                                    @endif

                                    @if(Auth::user()->hasPermission('indent', 'delete'))
                                    <button onclick="deleteIndent({{ $indent->id }})" title="Delete" class="w-8 h-8 flex items-center justify-center bg-rose-50 text-rose-600 rounded-lg hover:bg-rose-600 hover:text-white transition shadow-sm border border-rose-100/50"><i class="fas fa-trash text-xs"></i></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-7 py-10 text-center text-slate-400 font-medium">No previous indents found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-7 py-4 border-t border-slate-100 bg-slate-50/50">
                {{ $history->appends(request()->query())->links() }}
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
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
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

<!-- Refresh Confirmation Modal -->
<div id="refreshModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col p-6 animate-in zoom-in duration-200">
        <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 flex-shrink-0">
                <i class="fas fa-redo-alt text-lg animate-spin" style="animation-duration: 3s;"></i>
            </div>
            <div>
                <h3 class="text-base font-black text-slate-800 tracking-tight">Sync Notification</h3>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Refresh Request</p>
            </div>
        </div>
        <div class="text-slate-600 text-sm mb-6 font-semibold leading-relaxed">
            Its time to refresh. Do you want to reload the page to sync with latest changes?
        </div>
        <div class="flex gap-3 justify-end">
            <button onclick="handleRefreshConfirm(false)" class="bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 px-5 py-2.5 rounded-xl font-bold transition text-xs uppercase tracking-wider shadow-sm">
                No
            </button>
            <button onclick="handleRefreshConfirm(true)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl font-bold transition text-xs uppercase tracking-wider shadow-lg shadow-indigo-200/50">
                Yes
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

    function getPackBadgeClass(packName) {
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
    }

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

    function filterProducts() {
        try {
            const query = document.getElementById('productSearchInput').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.product-row');
            
            rows.forEach(row => {
                const name = (row.dataset.name || '').toLowerCase();
                const code = (row.dataset.code || '').toLowerCase();
                
                if (name.includes(query) || code.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        } catch (e) {
            alert('Search Error: ' + e.message);
        }
    }

    function filterProductsByType() {
        const filterEl = document.getElementById('item_type_filter');
        if (!filterEl) return;
        const typeId = filterEl.value;
        const rows = document.querySelectorAll('.product-row');
        rows.forEach(row => {
            if (!typeId || row.dataset.typeId === typeId) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Call once on load
    document.addEventListener('DOMContentLoaded', () => {
        updateAllStock();
        filterProductsByType();
    });

    async function previewIndent() {
        const rows = document.querySelectorAll('.product-row');
        const previewBody = document.getElementById('previewBody');
        const unit = document.getElementById('global_unit').value;
        previewBody.innerHTML = '';
        
        let hasData = false;
        let grandTotalBoxes = 0;

        for (let row of rows) {
            const qtyInput = row.querySelector('.product-qty');
            const qtyValue = parseInt(qtyInput.value || 0, 10);
            
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
                tr.className = 'hover:bg-slate-50/70 transition-colors group';
                tr.innerHTML = `
                    <td class="py-4 px-6">
                        <div class="font-bold text-slate-800 text-sm group-hover:text-indigo-700 transition-colors">${pName}</div>
                        <div class="mt-1.5"><span class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] sm:text-[9px] font-bold border ${getPackBadgeClass(pPack)}">${pPack}</span></div>
                    </td>
                    <td class="py-4 text-center px-4">
                        <div class="text-xs font-black text-emerald-600">${parseFloat(stock.stock_boxes).toFixed(2)} BOX</div>
                        <div class="text-[9px] font-black text-slate-400 mt-1">${parseFloat(stock.stock).toFixed(2)} KG</div>
                    </td>
                    <td class="py-4 text-center px-4">
                        <div class="text-sm font-black text-slate-700">${qtyValue} <span class="text-[10px] text-slate-400 uppercase">${unit}</span></div>
                    </td>
                    <td class="py-4 text-right px-6">
                        <div class="text-lg font-black tracking-tight text-indigo-600">${finalBoxes.toFixed(0)}</div>
                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">EST. BOXES</div>
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

        fetch(`{{ url('indent-api/show') }}/${id}`)
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

    async function viewProgress(id) {
        const modal = document.getElementById('progressModal');
        const loader = `<tr><td colspan="4" class="py-10 text-center text-gray-400 italic">Analyzing progress...</td></tr>`;
        document.getElementById('progressTableBody').innerHTML = loader;
        modal.classList.remove('hidden');

        try {
            const baseUrl = "{{ url('indent-api/show') }}";
            const res = await fetch(`${baseUrl}/${id}`, {
                headers: { 'Accept': 'application/json' }
            });
            
            if (!res.ok) {
                const errorText = await res.text();
                throw new Error(`Server returned ${res.status}: ${errorText.substring(0, 100)}`);
            }
            
            const data = await res.json();
            
            if (data.success) {
                const indent = data.indent;
                if (document.getElementById('progressModalBranch')) {
                    const dateStr = indent.indent_date ? new Date(indent.indent_date).toLocaleDateString() : 'N/A';
                    document.getElementById('progressModalBranch').innerText = `${indent.branch_name || 'N/A'} (${indent.branch_code || 'N/A'}) | ${dateStr}`;
                }
                
                let html = '';
                if (indent.items && indent.items.length > 0) {
                    indent.items.forEach(item => {
                        const asked = parseFloat(item.final_qty_box || 0);
                        const completed = parseFloat(item.completed_qty || 0);
                        let statusHtml = '';
                        
                        if (asked > 0) {
                            if (completed >= asked) {
                                statusHtml = '<span class="text-green-600 font-black italic text-[10px] uppercase tracking-tighter">● FULLY DONE</span>';
                            } else if (completed > 0) {
                                const percent = Math.round((completed / asked) * 100);
                                statusHtml = `<span class="text-blue-500 font-black italic text-[10px] uppercase tracking-tighter">◌ PARTIAL (${percent}%)</span>`;
                            } else {
                                statusHtml = '<span class="text-amber-500 font-black italic text-[10px] uppercase tracking-tighter italic">◌ PENDING</span>';
                            }
                        } else {
                            statusHtml = '<span class="text-gray-400 font-black italic text-[10px] uppercase tracking-tighter">◌ N/A</span>';
                        }

                        html += `
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-5 font-bold text-gray-800 text-sm italic">
                                    ${item.product_name || 'Unknown Product'}
                                    <div class="text-[9px] text-gray-400 uppercase font-black tracking-widest">${item.product?.pack_name || ''}</div>
                                </td>
                                <td class="py-5 text-center font-black text-gray-500 text-lg">${asked.toFixed(0)}</td>
                                <td class="py-5 text-center font-black text-indigo-600 text-xl italic">${completed.toFixed(0)}</td>
                                <td class="py-5 text-right">${statusHtml}</td>
                            </tr>
                        `;
                    });
                } else {
                    html = '<tr><td colspan="4" class="py-10 text-center text-gray-400 italic">No items found in this indent.</td></tr>';
                }
                document.getElementById('progressTableBody').innerHTML = html;
            } else {
                throw new Error(data.message || 'Failed to load progress details.');
            }
        } catch (e) {
            console.error('Progress View Error:', e);
            alert('Error: ' + e.message);
            closeProgressModal();
        }
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
            const url = editingIndentId 
                ? `{{ url('indent-api/show') }}/${editingIndentId}/update` 
                : '{{ route("indent.store") }}';
            
            const res = await fetch(url, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                alert(editingIndentId ? 'Indent updated successfully!' : 'Indent saved successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to save indent'));
            }
        } catch (e) {
            alert('Communication error with server.');
        }
    }

    let editingIndentId = null;

    async function editIndent(id) {
        if(!confirm('This will populate the form with historical data and switch to Edit Mode. Continue?')) return;
        
        try {
            const res = await fetch(`{{ url('indent-api/show') }}/${id}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if(!data.success) throw new Error(data.message);
            
            const indent = data.indent;
            editingIndentId = id;
            
            // UI Updates
            document.getElementById('entryTitle').innerText = `EDIT INDENT #IND-${id}`;
            document.getElementById('editBadge').classList.remove('hidden');
            const createBadge = document.getElementById('createBadge');
            if (createBadge) {
                createBadge.classList.add('hidden');
            }
            document.getElementById('branch_code').value = indent.branch_code;
            document.getElementById('indent_date').value = indent.indent_date;
            
            // Clear current inputs
            document.querySelectorAll('.product-qty').forEach(input => input.value = '');
            
            // Populate inputs
            indent.items.forEach(item => {
                const row = document.querySelector(`.product-row[data-id="${item.product_id}"]`);
                if(row) {
                    const input = row.querySelector('.product-qty');
                    input.value = item.demand_qty;
                }
            });
            
            updateAllStock();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
        } catch(e) {
            alert('Error loading indent: ' + e.message);
        }
    }

    async function deleteIndent(id) {
        if(!confirm('Are you sure you want to PERMANENTLY delete this indent and all its items? This action cannot be undone.')) return;
        
        try {
            const res = await fetch(`{{ url('indent-api/show') }}/${id}`, {
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
                alert(data.message || 'Delete failed');
            }
        } catch(e) {
            console.error(e);
            alert('Delete failed: ' + e.message);
        }
    }

    async function cloneIndent(id) {
        if(!confirm('Create a new draft indent based on this one?')) return;
        
        try {
            const res = await fetch(`{{ url('indent-api/show') }}/${id}/clone`, {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if(data.success) {
                alert('Indent cloned! Redirecting...');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch(e) {
            alert('Failed to clone indent');
        }
    }

    let isConfirmingRefresh = false;

    function triggerRefreshModal() {
        if (isConfirmingRefresh) return;
        isConfirmingRefresh = true;
        const modal = document.getElementById('refreshModal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    window.handleRefreshConfirm = function(confirmRefresh) {
        const modal = document.getElementById('refreshModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        isConfirmingRefresh = false;
        if (confirmRefresh) {
            location.reload();
        }
    }

    // Auto-refresh check when tab is focused or every 30 seconds to sync with mobile changes
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !editingIndentId) {
            // Check if any quantity is entered before refreshing
            const hasData = Array.from(document.querySelectorAll('.product-qty')).some(i => i.value > 0);
            if (!hasData) {
                triggerRefreshModal();
            }
        }
    });

    setInterval(() => {
        if (document.visibilityState === 'visible' && !editingIndentId) {
            const hasData = Array.from(document.querySelectorAll('.product-qty')).some(i => i.value > 0);
            if (!hasData) {
                triggerRefreshModal();
            }
        }
    }, 30000);
</script>
@endsection
