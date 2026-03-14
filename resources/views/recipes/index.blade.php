@extends('layouts.app')

@section('header', 'Recipe Master')

@section('content')
<div class="bg-white rounded shadow-md p-6">
    <div class="flex flex-wrap justify-between items-center mb-6 gap-2">
        <h3 class="text-lg font-bold text-gray-700">Recipe List</h3>
        <div class="flex gap-2">
            <a href="{{ route('recipes.export', request()->query()) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded shadow">
                <i class="fas fa-file-download mr-2"></i> Download
            </a>
            @if(Auth::user()->hasPermission('recipes', 'create'))
            <button onclick="document.getElementById('importModal').classList.remove('hidden')" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded shadow">
                <i class="fas fa-file-excel mr-2"></i> Import
            </button>
            <button onclick="openAddModal()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded shadow">
                <i class="fas fa-plus mr-2"></i> Add Recipe
            </button>
            @endif
        </div>
    </div>

    {{-- Search Bar --}}
    <div class="mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
        <form action="{{ route('recipes.index') }}" method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-grow min-w-[300px]">
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by finished product name..." class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 px-3 border">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Product Type</label>
                <select name="type_id" class="rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 px-3 border min-w-[150px]">
                    <option value="">All Types</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" {{ request('type_id') == $type->id ? 'selected' : '' }}>{{ $type->type_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Per Page</label>
                <select name="per_page" class="rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 px-3 border">
                    <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
                @if(request()->anyFilled(['search', 'type_id', 'per_page']))
                    <a href="{{ route('recipes.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded shadow">Clear</a>
                @endif
            </div>
        </form>
    </div>

    @if(Auth::user()->hasPermission('recipes', 'delete'))
    <div id="bulkActions" class="mb-4 hidden">
        <button onclick="bulkDelete()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded shadow flex items-center">
            <i class="fas fa-trash-alt mr-2"></i> Delete Selected (<span id="selectedCount">0</span>)
        </button>
    </div>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-300">
            <thead>
                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-center w-10">
                        <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </th>
                    <th class="py-3 px-6 text-left">Finished Product</th>
                    <th class="py-3 px-6 text-left">Yield</th>
                    <th class="py-3 px-6 text-left">Raw Materials</th>
                    <th class="py-3 px-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm font-light">
                @foreach($recipes as $recipe)
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="py-3 px-6 text-center">
                        <input type="checkbox" name="recipe_ids[]" value="{{ $recipe->id }}" class="recipe-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </td>
                    <td class="py-3 px-6 text-left font-medium">
                        <div class="font-bold">{{ $recipe->finishedProduct->name }}</div>
                        <div class="mt-1">
                            @php
                                $typeName = $recipe->finishedProduct->type->type_name ?? 'N/A';
                                $badgeClass = 'bg-slate-100 text-slate-600'; // Default
                                
                                if (str_contains(strtolower($typeName), 'finished good') && !str_contains(strtolower($typeName), 'semi')) {
                                    $badgeClass = 'bg-green-100 text-green-700 border border-green-200';
                                } elseif (str_contains(strtolower($typeName), 'semi')) {
                                    $badgeClass = 'bg-amber-100 text-amber-700 border border-amber-200';
                                } elseif (str_contains(strtolower($typeName), 'raw')) {
                                    $badgeClass = 'bg-gray-100 text-gray-700 border border-gray-200';
                                }
                            @endphp
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $badgeClass }}">
                                {{ $typeName }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 italic">Packing: {{ $recipe->finishedProduct->pack_name }}</div>
                    </td>
                    <td class="py-3 px-6 text-left">{{ $recipe->yield_quantity }} {{ $recipe->yield_uom }}</td>
                    <td class="py-3 px-6 text-left">
                        <ul class="list-disc list-inside">
                            @foreach($recipe->items as $item)
                                <li>
                                    {{ $item->rawMaterial->name }} 
                                    <span class="text-xs text-gray-500">({{ $item->rawMaterial->pack_name }})</span>
                                    ({{ $item->quantity }})
                                </li>
                            @endforeach
                        </ul>
                    </td>
                        <td class="py-3 px-6 text-center">
                            <div class="flex item-center justify-center">
                                @if(Auth::user()->hasPermission('recipes', 'edit'))
                                <button type="button" 
                                    data-recipe='@json($recipe)'
                                    onclick="editRecipe(this)" 
                                    class="w-4 mr-2 transform hover:text-blue-500 hover:scale-110">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @endif
                                @if(Auth::user()->hasPermission('recipes', 'delete'))
                                <form action="{{ route('recipes.destroy', $recipe->id) }}" method="POST" onsubmit="return confirm('Are you sure?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-4 transform hover:text-red-500 hover:scale-110">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $recipes->links() }}
    </div>
</div>

{{-- Recipe Modal --}}
<div id="recipeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 id="modalTitle" class="text-xl leading-6 font-bold text-gray-900">Add New Recipe</h3>
            <form id="recipeForm" method="POST" action="{{ route('recipes.store') }}" class="mt-4 text-left">
                @csrf
                <input type="hidden" name="_method" id="methodField" value="POST">
                
                <div class="grid grid-cols-2 gap-4 mb-4 bg-gray-50 p-3 rounded-lg border">
                    <div>
                        <label class="block text-gray-700 text-[10px] font-black uppercase tracking-widest mb-1">Filter Finished Product Type</label>
                        <select onchange="filterFinishedProducts(this.value)" class="w-full border border-gray-200 rounded-lg py-1.5 px-3 text-xs focus:ring-2 focus:ring-blue-500 outline-none transition uppercase font-bold">
                            <option value="">All Types</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-[10px] font-black uppercase tracking-widest mb-1">Finished Product</label>
                        <select name="finished_product_id" id="finished_product_id" class="w-full border border-gray-200 rounded-lg py-1.5 px-3 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none transition" required>
                            <option value="">Select Finished Good</option>
                            @foreach($finishedGoods as $product)
                                <option value="{{ $product->id }}" data-type="{{ $product->product_type_id }}">{{ $product->name }} ({{ $product->pack_name }}) ({{ $product->uom }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                     <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Yield Quantity</label>
                        <input type="number" step="0.001" name="yield_quantity" id="yield_quantity" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Yield UOM</label>
                        <input type="text" name="yield_uom" id="yield_uom" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                </div>

                <div class="mb-4 bg-blue-50/30 p-4 rounded-xl border border-blue-100/50">
                    <div class="flex justify-between items-end mb-4 border-b pb-3">
                        <label class="block text-blue-800 text-xs font-black uppercase tracking-widest">
                            <i class="fas fa-flask mr-2"></i> Raw Materials
                        </label>
                        <div class="w-48">
                            <label class="block text-[8px] font-black text-blue-400 uppercase tracking-widest mb-1">Type Filter (All Rows)</label>
                            <select onchange="filterAllRawMaterials(this.value)" id="rmGlobalFilter" class="w-full border border-blue-100 rounded-lg py-1.5 px-2 text-[10px] font-bold uppercase focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                                <option value="">Show All Items</option>
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div id="rawMaterialsList" class="space-y-3">
                        <!-- Rows will be injected here -->
                    </div>
                    <button type="button" onclick="addRawMaterialRow()" class="mt-4 bg-white border-2 border-dashed border-blue-200 text-blue-600 hover:border-blue-400 hover:text-blue-700 w-full rounded-xl py-3 text-xs font-black uppercase tracking-widest transition flex items-center justify-center gap-2">
                        <i class="fas fa-plus-circle"></i> Add Raw Material
                    </button>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6 border-t pt-4">
                    <button type="button" onclick="closeModal()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded shadow focus:outline-none">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow focus:outline-none">
                        Save Recipe
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Import Modal --}}
<div id="importModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Import Recipes</h3>
        <form action="{{ route('recipes.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2 text-gray-700">Excel/CSV File</label>
                <input type="file" name="excel_file" accept=".xlsx,.csv" required class="w-full text-sm">
                <div class="mt-2 flex justify-between items-center">
                    <p class="text-xs text-gray-500">Headers: FG ITEM CODE, FINISHED PRODUCT...</p>
                    <a href="{{ route('recipes.import-template') }}" class="text-xs text-blue-600 hover:text-blue-800 font-bold flex items-center">
                        <i class="fas fa-download mr-1"></i> Download Template
                    </a>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t">
                <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')" class="bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded text-sm font-bold">Cancel</button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-bold shadow">Import</button>
            </div>
        </form>
    </div>
</div>

<script>
    let itemIndex = 0;
    
    // Store all options as arrays for filtering
    const allFinishedProducts = Array.from(document.querySelectorAll('#finished_product_id option')).map(opt => ({
        id: opt.value,
        text: opt.text,
        type: opt.dataset.type
    }));

    const allRawMaterials = [
        @foreach($rawMaterials as $material)
            { id: "{{ $material->id }}", name: "{{ $material->name }}", pack: "{{ $material->pack_name }}", uom: "{{ $material->uom }}", type: "{{ $material->product_type_id }}" },
        @endforeach
    ];

    function filterFinishedProducts(typeId) {
        const select = document.getElementById('finished_product_id');
        const currentValue = select.value;
        select.innerHTML = '<option value="">Select Finished Good</option>';
        
        allFinishedProducts.forEach(prod => {
            if (prod.id === "") return;
            if (!typeId || prod.type == typeId) {
                const opt = document.createElement('option');
                opt.value = prod.id;
                opt.text = prod.text;
                opt.dataset.type = prod.type;
                if (prod.id == currentValue) opt.selected = true;
                select.appendChild(opt);
            }
        });
    }

    function filterAllRawMaterials(typeId) {
        // Update all existing selects
        const selects = document.querySelectorAll('#rawMaterialsList select');
        selects.forEach(select => {
            const currentValue = select.value;
            populateRMSelect(select, typeId, currentValue);
        });
    }

    function populateRMSelect(select, typeId, currentValue = '') {
        select.innerHTML = '<option value="">Select Raw Material</option>';
        allRawMaterials.forEach(rm => {
            if (!typeId || rm.type == typeId) {
                const opt = document.createElement('option');
                opt.value = rm.id;
                opt.text = `${rm.name} (${rm.pack}) (${rm.uom})`;
                if (rm.id == currentValue) opt.selected = true;
                select.appendChild(opt);
            }
        });
    }

    function addRawMaterialRow(materialId = '', quantity = '') {
        const typeFilter = document.getElementById('rmGlobalFilter').value;
        const container = document.getElementById('rawMaterialsList');
        const div = document.createElement('div');
        div.className = 'grid grid-cols-12 gap-3 p-3 bg-white border border-gray-100 rounded-xl shadow-sm items-center transition hover:border-blue-200';
        div.innerHTML = `
            <div class="col-span-8">
                <select name="items[${itemIndex}][raw_material_id]" class="w-full border border-gray-200 rounded-lg py-2 px-3 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none transition" required>
                </select>
            </div>
            <div class="col-span-3">
                <input type="number" step="0.001" name="items[${itemIndex}][quantity]" value="${quantity}" placeholder="Qty" class="w-full border border-gray-200 rounded-lg py-2 px-3 text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none transition" required>
            </div>
            <div class="col-span-1 text-right">
                <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-gray-300 hover:text-red-500 transition-colors p-2 h-10 w-10 flex items-center justify-center rounded-lg hover:bg-red-50">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;
        container.appendChild(div);
        
        const select = div.querySelector('select');
        populateRMSelect(select, typeFilter, materialId);
        itemIndex++;
    }

    function openAddModal() {
        itemIndex = 0;
        document.getElementById('modalTitle').innerText = 'Add New Recipe';
        document.getElementById('recipeForm').action = "{{ route('recipes.store') }}";
        document.getElementById('methodField').value = 'POST';
        document.getElementById('recipeForm').reset();
        document.getElementById('rawMaterialsList').innerHTML = '';
        addRawMaterialRow();
        document.getElementById('recipeModal').classList.remove('hidden');
    }

    function editRecipe(btn) {
        itemIndex = 0;
        const recipe = JSON.parse(btn.getAttribute('data-recipe'));
        
        document.getElementById('modalTitle').innerText = 'Edit Recipe';
        document.getElementById('recipeForm').action = `{{ url('recipes') }}/${recipe.id}`;
        document.getElementById('methodField').value = 'PUT';
        
        document.getElementById('finished_product_id').value = recipe.finished_product_id;
        document.getElementById('yield_quantity').value = recipe.yield_quantity;
        document.getElementById('yield_uom').value = recipe.yield_uom;
        
        document.getElementById('rawMaterialsList').innerHTML = '';
        recipe.items.forEach(item => {
            addRawMaterialRow(item.raw_material_id, item.quantity);
        });
        
        document.getElementById('recipeModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('recipeModal').classList.add('hidden');
    }

    // Initialize with one row if showing Add by default (though we use openAddModal now)
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('rawMaterialsList').innerHTML.trim() === '') {
            // addRawMaterialRow(); // This might interfere with modal logic, better to call in openAddModal
        }
    });
    // Bulk Selection Logic
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.recipe-checkbox');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');

    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkActions();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkActions);
    });

    function updateBulkActions() {
        const checkedCount = document.querySelectorAll('.recipe-checkbox:checked').length;
        selectedCount.innerText = checkedCount;
        if (checkedCount > 0) {
            bulkActions.classList.remove('hidden');
        } else {
            bulkActions.classList.add('hidden');
        }
    }

    async function bulkDelete() {
        const checkedIds = Array.from(document.querySelectorAll('.recipe-checkbox:checked')).map(cb => cb.value);
        if (checkedIds.length === 0) return;

        if (confirm(`Are you sure you want to delete ${checkedIds.length} recipes?`)) {
            try {
                const response = await fetch("{{ route('recipes.bulk-delete') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ ids: checkedIds })
                });

                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Something went wrong. Please try again.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Connection error.');
            }
        }
    }
</script>
@endsection
