@extends('layouts.app')

@section('header', 'Production Planning')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 overflow-hidden flex flex-col">
        {{-- Header Section --}}
        <div class="bg-white px-7 py-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-200/50 flex-shrink-0">
                    <i class="fas fa-microchip text-white"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800 tracking-tight leading-tight">Production Planning</h3>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Calculate Requirements (MRP)</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2 flex-wrap">
                @if(count($indents) > 0)
                <button type="button" onclick="openIndentPlanModal()" class="bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 font-bold py-2 px-3 rounded-xl text-xs transition duration-200 flex items-center gap-2 shadow-sm">
                    <i class="fas fa-file-import"></i> By Indent
                </button>
                @endif
                
                @if(Auth::user()->hasFeature('indent', 'type_filter'))
                <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 shadow-sm">
                    <i class="fas fa-filter text-slate-400 text-[10px]"></i>
                    <select id="global_type_filter" onchange="applyGlobalTypeFilter()" class="bg-transparent border-none text-xs font-bold text-indigo-600 focus:ring-0 outline-none pr-6 py-0.5">
                        <option value="">All Types</option>
                        @foreach($productTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                <select id="global_type_filter" style="display: none;"><option value=""></option></select>
                @endif

                @if(Auth::user()->hasFeature('indent', 'bulk_add'))
                <button type="button" onclick="toggleBulkModal()" class="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 font-bold py-2 px-3 rounded-xl text-xs transition duration-200 flex items-center gap-2 shadow-sm">
                    <i class="fas fa-layer-group"></i> Bulk Add
                </button>
                @endif
            </div>
        </div>

        <div class="p-7 bg-slate-50/30 flex-grow">
            <div id="productInputList" class="space-y-3">
                <div class="flex gap-3 product-row items-center bg-white p-2 rounded-2xl border border-slate-200 shadow-sm transition hover:border-indigo-300">
                    <div class="flex-grow">
                        <select class="w-full bg-transparent border-none py-2 px-3 text-sm font-bold text-slate-700 focus:ring-0 outline-none product-select">
                            <option value="" data-type-id="">Select Finished Good</option>
                            @foreach($finishedGoods as $product)
                                <option value="{{ $product->id }}" data-type-id="{{ $product->product_type_id }}">{{ $product->name }} ({{ $product->pack_name }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-32 relative">
                        <input type="number" step="0.001" placeholder="Qty" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-sm font-mono font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all demand-qty text-right pr-4">
                    </div>
                    <button type="button" class="w-10 h-10 flex-shrink-0 rounded-xl text-slate-300 hover:text-red-500 hover:bg-red-50 transition flex items-center justify-center remove-row" style="display:none;">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
            
            <div class="mt-5 p-4 bg-indigo-50/50 rounded-2xl border border-indigo-100 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                @if(Auth::user()->hasFeature('indent', 'branch_select'))
                <div class="flex-grow">
                    <label class="block text-indigo-800 text-[10px] font-black uppercase tracking-widest mb-1.5 ml-1">Check Stock At</label>
                    <select id="branch_code" class="w-full bg-white border border-indigo-200 rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-xs font-bold text-indigo-900 shadow-sm">
                        <option value="">Consolidated View (All Branches)</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->code }}">{{ $branch->name }} ({{ $branch->code }})</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="flex items-center gap-2">
                    <button type="button" onclick="clearAllRows()" class="h-10 px-4 bg-white border border-red-100 text-red-500 hover:bg-red-50 text-[10px] font-black uppercase tracking-widest rounded-xl transition flex items-center gap-2 shadow-sm whitespace-nowrap">
                        <i class="fas fa-trash-can"></i> Clear
                    </button>
                    <button type="button" onclick="addProductRow()" class="h-10 px-4 bg-white border border-indigo-200 text-indigo-600 hover:bg-indigo-50 hover:border-indigo-300 text-[10px] font-black uppercase tracking-widest rounded-xl transition flex items-center gap-2 shadow-sm whitespace-nowrap">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>
        </div>

        <div class="p-7 border-t border-slate-100 bg-white rounded-b-3xl">
            @if(Auth::user()->hasPermission('indent', 'create'))
            <button type="button" onclick="calculateIndent()" class="bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-bold py-3.5 px-4 rounded-xl w-full shadow-lg shadow-indigo-200/50 transition duration-200 flex justify-center items-center gap-2 text-sm uppercase tracking-wider">
                <i class="fas fa-microchip text-lg"></i> Explode Recipe & Generate Report
            </button>
            @else
            <div class="bg-amber-50 border border-dashed border-amber-300 rounded-xl p-4 text-center">
                <p class="text-amber-700 text-[11px] font-black uppercase tracking-widest"><i class="fas fa-lock mr-1"></i> Access Restricted</p>
                <p class="text-amber-600 text-xs mt-1">You don't have permission to generate plans.</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Results -->
    <!-- Results -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 overflow-hidden flex flex-col" id="resultSection" style="display:none;">
        <div class="bg-white px-7 py-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-200/50 flex-shrink-0">
                    <i class="fas fa-clipboard-check text-white"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800 tracking-tight leading-tight">Planning Results</h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Material requirements overview</p>
                </div>
            </div>
            
            @if(Auth::user()->hasPermission('indent', 'excel'))
            <button onclick="exportIndent()" class="bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 text-xs font-bold py-2 px-4 rounded-xl shadow-sm transition flex items-center gap-2">
                <i class="fas fa-file-excel"></i> Export To Excel
            </button>
            @endif
        </div>

        <div class="p-7 bg-slate-50/30 flex-grow">
            <!-- Production Summary -->
            <div class="mb-8">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center">
                    <i class="fas fa-industry mr-2 text-indigo-400"></i> Planning Summary
                </h4>
                <div id="productionSummary" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                    <!-- Will be populated by JS -->
                </div>
            </div>

            <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center">
                <i class="fas fa-boxes-stacked mr-2 text-indigo-400"></i> Consolidated Material Requirements
            </h4>
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto max-h-[500px]">
                    <table class="min-w-full bg-white text-left border-collapse relative">
                        <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-200">
                            <tr>
                                <th class="py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Raw Material</th>
                                <th class="py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right border-b border-slate-200">Required</th>
                                <th class="py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right border-b border-slate-200">Stock</th>
                                <th class="py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right border-b border-slate-200">Shortfall</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" id="resultBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Indent Selection Modal --}}
<div id="indentPlanModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-4 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-8 py-6 border-b flex justify-between items-center bg-amber-500 text-white">
            <h3 class="font-black italic uppercase tracking-wider flex items-center"><i class="fas fa-file-import mr-3"></i> Plan Production by Indent</h3>
            <button onclick="closeIndentPlanModal()" class="text-white/70 hover:text-white transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <div class="p-6 bg-gray-50 border-b">
             <form id="indentFilterForm" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Branch</label>
                    <select id="f_branch" class="w-full border border-gray-200 rounded-lg py-1.5 px-2 text-xs font-bold focus:ring-2 focus:ring-amber-500">
                        <option value="">All Branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->code }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Status</label>
                    <select id="f_status" class="w-full border border-gray-200 rounded-lg py-1.5 px-2 text-xs font-bold focus:ring-2 focus:ring-amber-500">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="partly completed">Partly Completed</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button type="button" onclick="filterIndents()" class="w-full bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 font-black py-2 rounded-lg text-xs uppercase tracking-widest transition">
                        <i class="fas fa-search mr-1"></i> Search Indents
                    </button>
                </div>
             </form>
        </div>

        <div class="overflow-y-auto flex-grow">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 italic">
                        <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Date / ID</th>
                        <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Branch</th>
                        <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Items</th>
                        <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="indentListTable" class="divide-y divide-gray-50">
                    @forelse($indents as $indent)
                    <tr class="hover:bg-amber-50/30 transition-colors indent-row" 
                        data-branch="{{ $indent->branch_code }}" 
                        data-status="{{ $indent->status }}"
                        data-id="{{ $indent->id }}">
                        <td class="px-8 py-4">
                            <div class="font-bold text-gray-700 text-sm">{{ date('d M, Y', strtotime($indent->indent_date)) }}</div>
                            <div class="text-[9px] font-black text-amber-500 uppercase">ID: #{{ str_pad($indent->id, 5, '0', STR_PAD_LEFT) }}</div>
                        </td>
                        <td class="px-8 py-4">
                            <div class="text-xs font-bold text-gray-600">{{ $indent->branch_name }}</div>
                            <div class="text-[10px] text-gray-400 font-mono">({{ $indent->branch_code }})</div>
                        </td>
                        <td class="px-8 py-4 overflow-hidden">
                            <div class="text-[10px] text-gray-500 line-clamp-1 truncate w-48">
                                @php $itemNames = $indent->items->take(2)->pluck('product_name')->toArray(); @endphp
                                {{ implode(', ', $itemNames) }} {{ $indent->items->count() > 2 ? '... (+' . ($indent->items->count() - 2) . ' more)' : '' }}
                            </div>
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="text-[8px] font-black uppercase {{ $indent->status == 'completed' ? 'bg-green-100 text-green-600' : ($indent->status == 'pending' ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600') }} px-1.5 py-0.5 rounded">
                                    {{ $indent->status }}
                                </span>
                            </div>
                        </td>
                        <td class="px-8 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick="planFromIndent({{ $indent->id }}, 'full')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-black py-2 px-4 rounded-lg text-[10px] uppercase tracking-widest shadow-lg shadow-indigo-100 transition transform active:scale-95">
                                    Plan Full
                                </button>
                                <button onclick="planFromIndent({{ $indent->id }}, 'shortfall')" class="bg-amber-600 hover:bg-amber-700 text-white font-black py-2 px-4 rounded-lg text-[10px] uppercase tracking-widest shadow-lg shadow-amber-100 transition transform active:scale-95">
                                    Plan Shortfall
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-8 py-10 text-center text-gray-400 italic">No indents available for planning.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="p-6 bg-gray-50 border-t text-center">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Tip: "Plan Shortfall" only adds items where the completed quantity is less than the asked quantity.</p>
        </div>
    </div>
</div>

<!-- Bulk Add Modal -->
<div id="bulkModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-indigo-600 text-white">
            <h3 class="font-bold flex items-center"><i class="fas fa-layer-group mr-2"></i> Select Products In Bulk</h3>
            <button onclick="toggleBulkModal()" class="text-white hover:text-gray-200 transition"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-0">
            <div class="p-4 bg-gray-50 border-b">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" id="productSearch" onkeyup="filterProducts()" class="pl-10 w-full border rounded-lg py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Search product name or code...">
                </div>
            </div>
            
            <div class="h-96 overflow-y-auto" id="productListContainer">
                @foreach($finishedGoods as $product)
                <label class="flex items-center px-6 py-3 hover:bg-indigo-50 border-b border-gray-100 cursor-pointer product-list-item transition duration-150" 
                       data-name="{{ strtolower($product->name) }}" 
                       data-code="{{ strtolower($product->item_code) }}"
                       data-type-id="{{ $product->product_type_id }}">
                    <input type="checkbox" value="{{ $product->id }}" class="product-checkbox w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <div class="ml-4 flex-grow">
                        <div class="font-bold text-gray-800">{{ $product->name }}</div>
                        <div class="flex gap-2">
                             <span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 rounded font-mono">{{ $product->item_code ?: 'N/A' }}</span>
                             <span class="text-[10px] text-gray-400">{{ $product->pack_name }}</span>
                        </div>
                    </div>
                </label>
                @endforeach
            </div>
            
            <div class="p-4 bg-gray-50 border-t flex justify-between items-center">
                <span class="text-sm text-gray-500" id="selectedCount">0 products selected</span>
                <div class="flex gap-3">
                    <button onclick="toggleBulkModal()" class="bg-white border hover:bg-gray-100 text-gray-700 font-bold py-2 px-6 rounded-lg transition">Cancel</button>
                    <button onclick="addSelectedProducts()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-8 rounded-lg shadow-md transition transform active:scale-95">Add Selected</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Update selected count when checkboxes change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('product-checkbox')) {
            const count = document.querySelectorAll('.product-checkbox:checked').length;
            document.getElementById('selectedCount').innerText = `${count} products selected`;
        }
    });

    function toggleBulkModal() {
        const modal = document.getElementById('bulkModal');
        modal.classList.toggle('hidden');
        if (!modal.classList.contains('hidden')) {
            document.getElementById('productSearch').value = '';
            filterProducts(); // Reset filter
            document.getElementById('productSearch').focus();
        }
    }

    function openIndentPlanModal() {
        document.getElementById('indentPlanModal').classList.remove('hidden');
    }

    function closeIndentPlanModal() {
        document.getElementById('indentPlanModal').classList.add('hidden');
    }

    function filterIndents() {
        const branch = document.getElementById('f_branch').value;
        const status = document.getElementById('f_status').value;
        const rows = document.querySelectorAll('.indent-row');
        
        rows.forEach(row => {
            const matchesBranch = !branch || row.dataset.branch == branch;
            const matchesStatus = !status || row.dataset.status == status;
            
            if (matchesBranch && matchesStatus) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        });
    }

    async function planFromIndent(indentId, mode) {
        try {
            const baseUrl = "{{ url('indent/show') }}";
            const res = await fetch(`${baseUrl}/${indentId}`);
            
            if (!res.ok) {
                const errorText = await res.text();
                throw new Error(`Server returned ${res.status}: ${errorText.substring(0, 100)}`);
            }
            
            const data = await res.json();
            
            if (data.success) {
                const indent = data.indent;
                
                // Set branch code if it matches
                const branchSelect = document.getElementById('branch_code');
                if (branchSelect && indent.branch_code) {
                    branchSelect.value = indent.branch_code;
                }

                // Clear existing calculator rows (except the first one if we want to reset it)
                const container = document.getElementById('productInputList');
                const firstRow = container.querySelector('.product-row');
                const template = firstRow ? firstRow.cloneNode(true) : null;
                
                // Clear EXCEPT the template row logic
                container.innerHTML = '';
                if (template) {
                    // Reset template for use
                    template.querySelector('.product-select').value = '';
                    template.querySelector('.demand-qty').value = '';
                    template.querySelector('.remove-row').style.display = 'none';
                    container.appendChild(template);
                }

                let itemsAdded = 0;
                if (indent.items && indent.items.length > 0) {
                    indent.items.forEach(item => {
                        const asked = parseFloat(item.final_qty_box || 0);
                        const completed = parseFloat(item.completed_qty || 0);
                        let qtyToPlan = 0;

                        if (mode === 'full') {
                            qtyToPlan = asked;
                        } else if (mode === 'shortfall') {
                            qtyToPlan = asked - completed;
                        }

                        if (qtyToPlan > 0 && item.product_id) {
                            addProductRow(item.product_id, qtyToPlan.toFixed(3));
                            itemsAdded++;
                        }
                    });
                }

                if (itemsAdded === 0) {
                    alert('No items with positive planning quantity found in this indent for the selected mode.');
                    // Restore one empty row if none added and container is empty
                    if (container.children.length === 0) addProductRow();
                } else {
                    closeIndentPlanModal();
                    // Scroll to calculator
                    document.getElementById('productInputList').scrollIntoView({ behavior: 'smooth' });
                }

            } else {
                throw new Error(data.message || 'Failed to fetch indent details.');
            }
        } catch (e) {
            console.error('Indent Planning Error:', e);
            alert('Error: ' + e.message);
        }
    }

    function applyGlobalTypeFilter() {
        const typeId = document.getElementById('global_type_filter').value;
        const rows = document.querySelectorAll('.product-row');
        
        // Filter options in all selection dropdowns
        document.querySelectorAll('.product-select').forEach(select => {
            const options = select.querySelectorAll('option');
            options.forEach(option => {
                const optionTypeId = option.getAttribute('data-type-id');
                if (!typeId || !optionTypeId || optionTypeId === typeId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // If the currently selected option is now hidden, reset the select
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption && selectedOption.style.display === 'none') {
                select.value = '';
            }
        });

        // Also filter the bulk modal if it's open or about to be
        filterProducts();
    }

    function filterProducts() {
        const query = document.getElementById('productSearch').value.toLowerCase();
        const typeId = document.getElementById('global_type_filter').value;
        const items = document.querySelectorAll('.product-list-item');
        
        items.forEach(item => {
            const name = item.getAttribute('data-name');
            const code = item.getAttribute('data-code');
            const itemTypeId = item.getAttribute('data-type-id');
            
            const matchesSearch = name.includes(query) || code.includes(query);
            const matchesType = !typeId || itemTypeId === typeId;

            if (matchesSearch && matchesType) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function addSelectedProducts() {
        const checkboxes = document.querySelectorAll('.product-checkbox:checked');
        if (checkboxes.length === 0) {
            alert('Please select at least one product.');
            return;
        }

        checkboxes.forEach(cb => {
            addProductRow(cb.value);
            cb.checked = false; // Reset for next time
        });

        document.getElementById('selectedCount').innerText = '0 products selected';
        toggleBulkModal();
    }

    function clearAllRows() {
        if (confirm('Are you sure you want to remove all products from the list?')) {
            const container = document.getElementById('productInputList');
            const rows = container.querySelectorAll('.product-row');
            
            // Keep only the first row and reset it
            const firstRow = rows[0];
            firstRow.querySelector('.product-select').value = '';
            firstRow.querySelector('.demand-qty').value = '';
            firstRow.querySelector('.remove-row').style.display = 'none';
            
            // Remove other rows
            for (let i = 1; i < rows.length; i++) {
                rows[i].remove();
            }
        }
    }

    function addProductRow(productId = '', qty = '') {
        const container = document.getElementById('productInputList');
        const rows = container.querySelectorAll('.product-row');
        
        // Find an empty row or create a new one
        let emptyRow = null;
        rows.forEach(r => {
            if (!r.querySelector('.product-select').value && !emptyRow) {
                emptyRow = r;
            }
        });

        let row;
        if (emptyRow) {
            row = emptyRow;
        } else {
            const firstRow = rows[0];
            row = firstRow.cloneNode(true);
            row.querySelector('.remove-row').style.display = 'inline-block';
            row.querySelector('.remove-row').onclick = function() { this.parentElement.remove(); };
            container.appendChild(row);
        }
        
        if (productId) row.querySelector('.product-select').value = productId;
        if (qty) row.querySelector('.demand-qty').value = qty;
        
        // Apply current filter to the new row
        const typeId = document.getElementById('global_type_filter').value;
        if (typeId) {
            row.querySelectorAll('.product-select option').forEach(option => {
                const optionTypeId = option.getAttribute('data-type-id');
                if (!typeId || !optionTypeId || optionTypeId === typeId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        }

        return row;
    }

    async function calculateIndent() {
        const rows = document.querySelectorAll('.product-row');
        const products = [];

        rows.forEach(row => {
            const id = row.querySelector('.product-select').value;
            const qty = row.querySelector('.demand-qty').value;
            if (id && qty) {
                products.push({ id: id, demand_qty: qty });
            }
        });

        if (products.length === 0) {
            alert('Please select at least one product and quantity.');
            return;
        }

        try {
            const response = await fetch("{{ route('planning.calculate') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ 
                    products: products,
                    branch_code: document.getElementById('branch_code').value
                })
            });

            const result = await response.json();
            
            if (result.success) {
                // Populate Production Summary
                const summaryDiv = document.getElementById('productionSummary');
                summaryDiv.innerHTML = '';
                result.summary.forEach(item => {
                    const card = document.createElement('div');
                    card.className = "bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col hover:border-indigo-300 transition group";
                    card.innerHTML = `
                        <span class="inline-flex w-fit px-2 py-0.5 rounded text-[9px] font-black bg-indigo-50 text-indigo-600 uppercase tracking-widest mb-1.5 border border-indigo-100">${item.item_code || 'No Code'}</span>
                        <div class="font-bold text-slate-800 text-sm truncate group-hover:text-indigo-700 transition-colors" title="${item.name}">${item.name}</div>
                        <div class="flex justify-between items-end mt-2">
                            <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">${item.pack_name || ''}</span>
                            <span class="text-sm font-black text-indigo-600 bg-indigo-50/50 px-2 py-1 rounded-lg border border-indigo-100/50">${parseFloat(item.quantity).toFixed(3)}</span>
                        </div>
                    `;
                    summaryDiv.appendChild(card);
                });

                const tbody = document.getElementById('resultBody');
                tbody.innerHTML = '';
                
                result.data.forEach(item => {
                    const row = document.createElement('tr');
                     row.className = "hover:bg-slate-50/70 transition-colors group";
                    row.innerHTML = `
                        <td class="py-3 px-6 text-left">
                            <div class="font-bold text-slate-800 text-sm">${item.name}</div>
                            <div class="flex items-center gap-2 mt-1">
                                ${item.item_code ? `<span class="font-mono text-[9px] font-bold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100">${item.item_code}</span>` : ''}
                                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">${item.pack_name || ''}</span>
                            </div>
                        </td>
                        <td class="py-3 px-6 text-right">
                            <span class="font-bold text-slate-700">${parseFloat(item.required_qty).toFixed(3)}</span>
                            <span class="text-[10px] text-slate-400 font-bold ml-1">${item.uom}</span>
                        </td>
                        <td class="py-3 px-6 text-right text-slate-500 font-semibold">${parseFloat(item.current_stock).toFixed(3)}</td>
                        <td class="py-3 px-6 text-right">
                            <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg text-xs font-black ${item.shortfall > 0 ? 'text-red-700 bg-red-50 border border-red-200' : 'text-emerald-700 bg-emerald-50 border border-emerald-200'}">
                                ${parseFloat(item.shortfall).toFixed(3)}
                            </span>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                document.getElementById('resultSection').style.display = 'block';
                document.getElementById('resultSection').scrollIntoView({ behavior: 'smooth' });
            } else {
                alert(result.message);
            }

        } catch (error) {
            console.error('Error:', error);
            alert('Calculation failed.');
        }
    }

    function exportIndent() {
        const rows = document.querySelectorAll('.product-row');
        const products = [];

        rows.forEach(row => {
            const id = row.querySelector('.product-select').value;
            const qty = row.querySelector('.demand-qty').value;
            if (id && qty) {
                products.push({ id: id, demand_qty: qty });
            }
        });

        if (products.length === 0) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = "{{ route('planning.export') }}";
        form.style.display = 'none';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = "{{ csrf_token() }}";
        form.appendChild(csrfInput);

        const dataInput = document.createElement('input');
        dataInput.type = 'hidden';
        dataInput.name = 'products_json';
        dataInput.value = JSON.stringify(products);
        form.appendChild(dataInput);

        const branchInput = document.createElement('input');
        branchInput.type = 'hidden';
        branchInput.name = 'branch_code';
        branchInput.value = document.getElementById('branch_code').value;
        form.appendChild(branchInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
</script>
@endsection
