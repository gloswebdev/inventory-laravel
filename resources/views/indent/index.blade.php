@extends('layouts.app')

@section('header', 'Production Planning')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded shadow-md p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <h3 class="text-lg font-bold text-gray-700">Calculate Requirements (MRP)</h3>
            <div class="flex items-center gap-2">
                @if(Auth::user()->hasFeature('indent', 'type_filter'))
                <div class="flex items-center gap-2 bg-gray-50 border border-gray-100 rounded-lg px-3 py-1.5">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Filter Type:</label>
                    <select id="global_type_filter" onchange="applyGlobalTypeFilter()" class="bg-transparent border-none text-xs font-bold text-indigo-600 focus:ring-0 outline-none pr-8">
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
                <button type="button" onclick="toggleBulkModal()" class="bg-indigo-100 text-indigo-700 hover:bg-indigo-200 font-bold py-1.5 px-3 rounded-lg text-xs transition duration-200 flex items-center gap-2">
                    <i class="fas fa-layer-group"></i> Bulk Add
                </button>
                @endif
            </div>
        </div>

        <div id="productInputList">
            <div class="flex gap-2 mb-2 product-row items-center">
                <div class="flex-grow">
                    <select class="shadow border rounded w-full py-2 px-3 text-gray-700 product-select">
                        <option value="" data-type-id="">Select Finished Good</option>
                        @foreach($finishedGoods as $product)
                            <option value="{{ $product->id }}" data-type-id="{{ $product->product_type_id }}">{{ $product->name }} ({{ $product->pack_name }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-32">
                    <input type="number" step="0.001" placeholder="Qty" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 demand-qty">
                </div>
                <button type="button" class="text-red-500 hover:text-red-700 remove-row p-2" style="display:none;"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        
        <div class="mt-4 p-4 bg-indigo-50/50 rounded-xl border border-indigo-100 flex items-center justify-between gap-4">
            @if(Auth::user()->hasFeature('indent', 'branch_select'))
            <div class="flex-grow">
                <label class="block text-gray-600 text-[9px] font-black uppercase tracking-widest mb-1.5 ml-1">Check Stock At</label>
                <select id="branch_code" class="w-full border border-gray-200 rounded-xl py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white text-xs font-bold">
                    <option value="">Consolidated View (All Branches)</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->code }}">{{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="flex items-center gap-2 pt-5">
                <button type="button" onclick="addProductRow()" class="h-9 px-4 bg-white border border-blue-100 text-blue-600 hover:bg-blue-50 text-[10px] font-black uppercase tracking-widest rounded-xl transition flex items-center gap-2 shadow-sm">
                    <i class="fas fa-plus"></i> Add Another
                </button>
                <button type="button" onclick="clearAllRows()" class="h-9 px-4 bg-white border border-red-100 text-red-500 hover:bg-red-50 text-[10px] font-black uppercase tracking-widest rounded-xl transition flex items-center gap-2 shadow-sm">
                    <i class="fas fa-trash-can"></i> Clear
                </button>
            </div>
        </div>

        <div class="mt-8 border-t pt-6">
            @if(Auth::user()->hasPermission('indent', 'create'))
            <button type="button" onclick="calculateIndent()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded w-full shadow-lg transition duration-200 flex justify-center items-center">
                <i class="fas fa-microchip mr-2"></i> Explode Recipe & Generate Report
            </button>
            @else
            <div class="bg-amber-50 border-2 border-dashed border-amber-200 rounded-xl p-4 text-center">
                <p class="text-amber-700 text-xs font-bold italic uppercase tracking-tighter">Access Restricted: You don't have permission to generate plans.</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Results -->
    <div class="bg-white rounded shadow-md p-6" id="resultSection" style="display:none;">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-extrabold text-indigo-900 flex items-center">
                <i class="fas fa-clipboard-check mr-2"></i> Planning Results
            </h3>
            @if(Auth::user()->hasPermission('indent', 'excel'))
            <button onclick="exportIndent()" class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold py-2 px-4 rounded-lg flex items-center shadow-md transition">
                <i class="fas fa-file-excel mr-2"></i> Export To Excel
            </button>
            @endif
        </div>

        <!-- Production Summary -->
        <div class="mb-8 bg-indigo-50 rounded-xl p-4 border border-indigo-100">
            <h4 class="text-sm font-bold text-indigo-700 uppercase tracking-wider mb-3 flex items-center">
                <i class="fas fa-industry mr-2"></i> Planning Summary
            </h4>
            <div id="productionSummary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <!-- Will be populated by JS -->
            </div>
        </div>

        <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wider mb-3">Consolidated Material Requirements</h4>
        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="min-w-full bg-white">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 uppercase text-[10px] font-bold tracking-widest border-b">
                        <th class="py-3 px-4 text-left">Raw Material</th>
                        <th class="py-3 px-4 text-right">Required</th>
                        <th class="py-3 px-4 text-right">Stock</th>
                        <th class="py-3 px-4 text-right">Shortfall</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm" id="resultBody"></tbody>
            </table>
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
                    card.className = "bg-white p-3 rounded-lg border border-indigo-100 shadow-sm flex flex-col";
                    card.innerHTML = `
                        <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-tighter">${item.item_code || 'No Code'}</span>
                        <div class="font-bold text-gray-800 text-sm truncate" title="${item.name}">${item.name}</div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-xs text-gray-400 italic">${item.pack_name || ''}</span>
                            <span class="text-xs font-black text-indigo-600">${parseFloat(item.quantity).toFixed(3)}</span>
                        </div>
                    `;
                    summaryDiv.appendChild(card);
                });

                const tbody = document.getElementById('resultBody');
                tbody.innerHTML = '';
                
                result.data.forEach(item => {
                    const row = document.createElement('tr');
                     row.className = "border-b border-gray-100 hover:bg-gray-50 transition duration-150";
                    row.innerHTML = `
                        <td class="py-4 px-4 text-left">
                            <div class="font-bold text-gray-800">${item.name}</div>
                            <div class="flex gap-2 mt-1">
                                <span class="text-[9px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded font-mono font-bold">${item.item_code || 'N/A'}</span>
                                <span class="text-[9px] bg-gray-50 text-gray-500 px-1.5 py-0.5 rounded italic">${item.pack_name || ''}</span>
                            </div>
                        </td>
                        <td class="py-4 px-4 text-right font-bold text-gray-700">${parseFloat(item.required_qty).toFixed(3)} <span class="text-[10px] text-gray-400 font-normal ml-1">${item.uom}</span></td>
                        <td class="py-4 px-4 text-right text-gray-500 font-medium">${parseFloat(item.current_stock).toFixed(3)}</td>
                        <td class="py-4 px-4 text-right">
                            <span class="inline-block px-3 py-1 rounded-full font-black text-xs ${item.shortfall > 0 ? 'text-red-700 bg-red-100' : 'text-green-700 bg-green-100'}">
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
