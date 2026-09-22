@extends('layouts.app')

@section('header', 'Recipe Master')

@section('content')
<div class="bg-white rounded-3xl shadow-sm border border-slate-100/80">
    {{-- Header Section --}}
    <div id="pageHeader" class="bg-white px-7 py-5 border-b border-slate-100 flex flex-col md:flex-row md:justify-between md:items-center gap-4 transition-all">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-200/50">
                <i class="fas fa-flask text-white"></i>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight">Recipe Master</h3>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Manage production recipes</p>
            </div>
        </div>

        <div class="flex gap-2 flex-wrap items-center">
            @if(Auth::user()->hasPermission('recipes', 'excel'))
            <a href="{{ route('recipes.export', request()->query()) }}" class="bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-700 text-xs font-bold py-2 px-4 rounded-xl transition-colors flex items-center gap-2">
                <i class="fas fa-file-export"></i> Export
            </a>
            @endif
            @if(Auth::user()->hasPermission('recipes', 'create'))
            <button onclick="document.getElementById('importModal').classList.remove('hidden')" class="bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 text-xs font-bold py-2 px-4 rounded-xl transition-colors flex items-center gap-2">
                <i class="fas fa-file-import"></i> Import
            </button>
            <button onclick="openAddModal()" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white shadow-md shadow-emerald-200/50 text-sm font-bold py-2 px-5 rounded-xl transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> Add Recipe
            </button>
            @endif
        </div>
    </div>

    {{-- Search & Filter Form --}}
    <div class="px-7 py-4 bg-slate-50/50 border-b border-slate-100">
        <form action="{{ route('recipes.index') }}" method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-grow min-w-[200px]">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Search</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="By finished product name..." class="w-full bg-white border border-slate-200 rounded-xl py-2 pl-10 pr-4 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all shadow-sm">
                </div>
            </div>
            
            <div class="w-full sm:w-44">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Product Type</label>
                <select name="type_id" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all shadow-sm appearance-none">
                    <option value="">All Types</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" {{ request('type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->type_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-24">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Per Page</label>
                <select name="per_page" onchange="this.form.submit()" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all shadow-sm appearance-none">
                    <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-5 rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2">
                    <i class="fas fa-filter"></i> Apply
                </button>
                @if(request()->anyFilled(['search', 'type_id', 'per_page']))
                <a href="{{ route('recipes.index') }}" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-bold py-2.5 px-4 rounded-xl shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-times"></i> Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    @if(Auth::user()->hasPermission('recipes', 'delete'))
    <div id="bulkActions" class="bg-white px-7 py-3 border-b border-slate-100 flex items-center hidden">
        <button onclick="bulkDelete()" class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold py-1.5 px-4 rounded-lg transition-colors flex items-center gap-2">
            <i class="fas fa-trash-alt"></i> Delete Selected (<span id="selectedCount">0</span>)
        </button>
    </div>
    @endif

    <div>
        <table class="w-full text-left border-collapse">
            <thead id="tableHead" class="sticky top-0 z-10 bg-slate-50 shadow-sm before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-200">
                <tr>
                    <th class="py-3 px-6 border-b border-slate-200 w-10 text-center"><input type="checkbox" id="selectAll" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"></th>
                    <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Finished Product</th>
                    <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Yield</th>
                    <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Raw Materials</th>
                    <th class="py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($products as $product)
                @php
                    $recipe = $product->recipe;
                    $pmCount = 0;
                    if ($recipe) {
                        $pmItems = $recipe->items->filter(function($i) {
                            $typeName = $i->rawMaterial->type->type_name ?? '';
                            return str_contains(strtoupper($typeName), 'PACKING') || in_array(strtoupper($i->rawMaterial->rm_type ?? ''), ['DRUM', 'BAG', 'BOTTLE', 'CAP', 'CARTON', 'LABEL', 'TAPE', 'BOX']);
                        });
                        $pmCount = $pmItems->count();
                    }
                    
                    $typeName = $product->type->type_name ?? 'N/A';
                    $badgeClass = 'bg-slate-100 text-slate-600 border border-slate-200';
                    if (str_contains(strtolower($typeName), 'finished good') && !str_contains(strtolower($typeName), 'semi')) {
                        $badgeClass = 'bg-emerald-100 text-emerald-700 border border-emerald-200';
                    } elseif (str_contains(strtolower($typeName), 'semi')) {
                        $badgeClass = 'bg-amber-100 text-amber-700 border border-amber-200';
                    }
                @endphp
                <tr class="hover:bg-slate-50/70 transition-colors group">
                    <td class="py-3 px-6 text-center">
                        <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" class="recipe-checkbox rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    </td>
                    <td class="py-3 px-4">
                        <div class="font-bold text-slate-800 text-sm">{{ $product->name }}</div>
                        <div class="flex items-center gap-2 mt-1 mb-1">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $badgeClass }}">
                                {{ $typeName }}
                            </span>
                            @if($product->item_code)
                            <span class="font-mono text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">{{ $product->item_code }}</span>
                            @endif
                        </div>
                        <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">
                            Packing: {{ $product->pack_name ?? '-' }}
                        </div>
                    </td>
                    <td class="py-3 px-4">
                        @if($recipe)
                            <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg text-xs font-black bg-blue-50 text-blue-700 border border-blue-100">
                                {{ $recipe->yield_quantity }} {{ $recipe->yield_uom }}
                            </span>
                        @else
                            <span class="text-[11px] font-semibold text-slate-400 italic">Not Defined</span>
                        @endif
                    </td>
                    <td class="py-3 px-4">
                        @php
                            $user = Auth::user();
                            $canEditRecipe = $user ? ($user->hasPermission('recipes', 'edit') || $user->hasPermission('recipes', 'create')) : false;
                            $canPacking = $canEditRecipe && ($user ? $user->hasFeature('recipes', 'packing_config') : true);
                        @endphp

                        @if($canPacking)
                            @if($pmCount > 0)
                                <button type="button" 
                                    onclick="openPackingModal({{ $product->id }})" 
                                    class="text-xs font-bold text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-xl border border-amber-200 transition inline-flex items-center gap-2 shadow-2xs active:scale-95">
                                    <i class="fas fa-boxes-packing text-amber-600"></i> {{ $pmCount }} Packing Items (Edit BOM)
                                </button>
                            @else
                                <button type="button" 
                                    onclick="openPackingModal({{ $product->id }})" 
                                    class="text-xs font-bold text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-xl border border-blue-200 transition inline-flex items-center gap-2 shadow-2xs active:scale-95">
                                    <i class="fas fa-boxes-packing text-blue-500"></i> Define Formulation / BOM
                                </button>
                            @endif
                        @else
                            @if($pmCount > 0)
                                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-xl border border-slate-200 inline-flex items-center gap-2">
                                    <i class="fas fa-boxes-packing text-slate-400"></i> {{ $pmCount }} Packing Items
                                </span>
                            @else
                                <span class="text-xs font-semibold text-slate-400 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200 inline-flex items-center gap-2">
                                    <i class="fas fa-lock text-slate-400"></i> Not Configured
                                </span>
                            @endif
                        @endif

                        @if($recipe && $recipe->items->count() > 0)
                            <ul class="space-y-1 mt-2">
                                @foreach($recipe->items as $item)
                                    @php
                                        $rmType = $item->rawMaterial->type->type_name ?? '';
                                        $isPm = str_contains(strtoupper($rmType), 'PACKING') || in_array(strtoupper($item->rawMaterial->rm_type ?? ''), ['DRUM', 'BAG', 'BOTTLE', 'CAP', 'CARTON', 'LABEL', 'TAPE', 'BOX']);
                                    @endphp
                                    @if(!$isPm)
                                    <li class="text-sm text-slate-600 flex items-center">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300 mr-2"></span>
                                        <span class="font-semibold text-slate-700 mr-1">{{ $item->rawMaterial->name }}</span> 
                                        <span class="text-[11px] text-slate-400 font-medium mr-2">({{ $item->rawMaterial->pack_name }})</span>
                                        <span class="text-xs font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">{{ $item->quantity }}</span>
                                    </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="py-3 px-6 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-2">
                            @if($recipe && Auth::user()->hasPermission('recipes', 'delete'))
                            <form action="{{ route('recipes.destroy', $recipe->id) }}" method="POST" class="inline m-0" onsubmit="return confirm('Delete this recipe?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-300 hover:bg-red-50 flex items-center justify-center transition-all shadow-sm" title="Delete Recipe">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="py-12 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 mb-4">
                                <i class="fas fa-boxes-stacked text-2xl"></i>
                            </div>
                            <p class="text-slate-500 font-medium">No products found matching your criteria.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-7 py-4 border-t border-slate-100 bg-slate-50/50">
        {{ $products->links() }}
    </div>
</div>

{{-- Recipe Modal --}}
<div id="recipeModal" class="fixed inset-0 z-[100] bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
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
                        <select name="finished_product_id" id="finished_product_id" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-bold text-slate-700 bg-white shadow-sm hover:border-slate-300 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all cursor-pointer" required>
                            <option value="">Select Finished Good</option>
                            @foreach($finishedGoods as $index => $product)
                                <option value="{{ $product->id }}" data-type="{{ $product->product_type_id }}">{{ $index + 1 }}. {{ $product->name }} ({{ $product->pack_name }}) ({{ $product->uom }})</option>
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
<div id="importModal" class="fixed inset-0 z-[100] bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
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
        text: opt.text.replace(/^\d+\.\s*/, ''),
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
        
        let counter = 1;
        allFinishedProducts.forEach(prod => {
            if (prod.id === "") return;
            if (!typeId || prod.type == typeId) {
                const opt = document.createElement('option');
                opt.value = prod.id;
                opt.text = `${counter}. ${prod.text}`;
                opt.dataset.type = prod.type;
                if (prod.id == currentValue) opt.selected = true;
                select.appendChild(opt);
                counter++;
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
        let counter = 1;
        allRawMaterials.forEach(rm => {
            if (!typeId || rm.type == typeId) {
                const opt = document.createElement('option');
                opt.value = rm.id;
                opt.text = `${counter}. ${rm.name} (${rm.pack}) (${rm.uom})`;
                if (rm.id == currentValue) opt.selected = true;
                select.appendChild(opt);
                counter++;
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
                <select name="items[${itemIndex}][raw_material_id]" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm font-bold text-slate-700 bg-white shadow-sm hover:border-slate-300 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all cursor-pointer" required>
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

    function openAddModalForProduct(productId, name, packName, uom) {
        openAddModal();
        const select = document.getElementById('finished_product_id');
        select.value = productId;
        if (document.getElementById('yield_uom')) {
            document.getElementById('yield_uom').value = uom || 'BOX';
        }
        if (document.getElementById('yield_quantity')) {
            document.getElementById('yield_quantity').value = 1;
        }
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

{{-- ============================================================
     PACKING MATERIAL CONFIGURATION MODAL (STEP 4 FROM COSTING BOM)
     ============================================================ --}}
<div id="packingModal" class="fixed inset-0 z-[120] bg-slate-900/60 backdrop-blur-xs overflow-y-auto h-full w-full hidden flex items-center justify-center p-4">
    <div class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-150 flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 duration-150">
        
        {{-- Modal Header --}}
        <div class="bg-gradient-to-r from-amber-500 via-amber-600 to-orange-500 px-6 py-4 flex justify-between items-center text-white shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shadow-inner">
                    <i class="fas fa-boxes-packing text-lg text-white"></i>
                </div>
                <div>
                    <h3 class="text-base font-black tracking-tight">Packaging Material Configuration</h3>
                    <div class="flex items-center gap-2 mt-0.5 text-xs">
                        <span id="pmModalProductName" class="font-extrabold text-amber-100"></span>
                        <span id="pmModalProductCode" class="font-mono text-[11px] bg-white/20 px-2 py-0.5 rounded font-bold"></span>
                        <span id="pmModalProductPack" class="text-[11px] font-black text-white bg-amber-700/60 px-2 py-0.5 rounded border border-white/20"></span>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <div class="bg-white text-slate-800 px-4 py-2 rounded-xl text-xs font-bold shadow-sm flex items-center gap-2">
                    <span class="text-slate-500">PM Total:</span>
                    <span id="pmModalGrandTotal" class="text-amber-600 font-black text-sm">₹0.00</span>
                </div>
                <button type="button" onclick="closePackingModal()" class="w-9 h-9 rounded-xl bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        {{-- Modal Body --}}
        <div class="p-6 overflow-y-auto flex-1 space-y-4">
            
            {{-- Info Banner --}}
            <div class="bg-amber-50/70 border border-amber-200/80 rounded-2xl p-3.5 flex items-start gap-3">
                <i class="fas fa-circle-info text-amber-600 mt-0.5 text-sm shrink-0"></i>
                <div class="text-xs text-amber-900 leading-relaxed">
                    <strong>Product Master Mappings:</strong> Is finished product size ke liye required packing materials (Bottles, Cans, Cartons, Caps, Bags, Duplex) configure karein. Quantity aur rates enter karein, PM Total auto-calculate hoga aur Costing BOM ke sath sync rahega.
                </div>
            </div>

            {{-- Table / Rows Container --}}
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[10px] font-black tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Packing Material (Product Master)</th>
                            <th class="py-3 px-3 w-28 text-center">Quantity</th>
                            <th class="py-3 px-2 w-16 text-center">UOM</th>
                            <th class="py-3 px-3 w-28 text-center">Rate (₹)</th>
                            <th class="py-3 px-3 w-28 text-center">Subtotal</th>
                            <th class="py-3 px-2 w-20 text-center" title="Primary container (e.g. bottle/can)">Container?</th>
                            <th class="py-3 px-3 w-12 text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="packingRowsBody" class="divide-y divide-slate-100">
                        <!-- Injected via JavaScript -->
                    </tbody>
                </table>
                <div id="packingEmptyState" class="hidden py-10 text-center text-slate-400">
                    <i class="fas fa-box-open text-3xl mb-2 text-slate-300"></i>
                    <p class="font-bold text-xs">No packing materials configured yet.</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Click "+ Add Packing Material" below to start.</p>
                </div>
            </div>

            {{-- Add Material Button --}}
            <div>
                <button type="button" onclick="addPackingMaterialRow()" class="px-4 py-2.5 bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-800 rounded-xl text-xs font-black flex items-center gap-2 transition-all shadow-xs active:scale-95">
                    <i class="fas fa-plus-circle text-amber-600"></i> Add Packing Material
                </button>
            </div>
        </div>

        {{-- Modal Footer --}}
        <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-between items-center shrink-0">
            <span id="pmModalItemCount" class="text-xs font-bold text-slate-500">0 items configured</span>
            <div class="flex items-center gap-3">
                <button type="button" onclick="closePackingModal()" class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl transition">
                    Cancel
                </button>
                <button type="button" id="btnSavePacking" onclick="savePackingConfig()" class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-black text-xs rounded-xl shadow-md transition-all flex items-center gap-2 active:scale-95">
                    <i class="fas fa-save"></i> Save Packing Config
                </button>
            </div>
        </div>

    </div>
</div>

<script>
let currentPackingRecipeId = null;
let allPackingMaterialsList = [];
let priceMapObj = {};

async function openPackingModal(recipeId) {
    currentPackingRecipeId = recipeId;
    const modal = document.getElementById('packingModal');
    const tbody = document.getElementById('packingRowsBody');
    const emptyState = document.getElementById('packingEmptyState');
    
    tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400"><i class="fas fa-spinner fa-spin text-xl text-amber-500 mb-2"></i><div class="text-xs font-bold">Loading packing configuration...</div></td></tr>';
    emptyState.classList.add('hidden');
    modal.classList.remove('hidden');

    try {
        const response = await fetch(`{{ url('recipes') }}/${recipeId}/packing`, {
            headers: {
                'Accept': 'application/json'
            }
        });
        const data = await response.json();

        if (!data.success) {
            alert('Failed to load packing configuration.');
            closePackingModal();
            return;
        }

        // Set Header details
        document.getElementById('pmModalProductName').innerText = data.product.name;
        document.getElementById('pmModalProductCode').innerText = data.product.item_code || '-';
        document.getElementById('pmModalProductPack').innerText = data.product.pack_name ? 'Pack: ' + data.product.pack_name : 'No Pack Size';

        allPackingMaterialsList = data.all_packing_materials || [];
        priceMapObj = data.price_map || {};

        tbody.innerHTML = '';

        if (data.current_materials && data.current_materials.length > 0) {
            data.current_materials.forEach(mat => {
                addPackingMaterialRow(mat.raw_material_id, mat.quantity, mat.rate, mat.is_container);
            });
        } else {
            emptyState.classList.remove('hidden');
        }

        calculatePmTotals();

    } catch (err) {
        console.error('Error fetching packing config:', err);
        alert('Error connecting to server.');
        closePackingModal();
    }
}

function closePackingModal() {
    document.getElementById('packingModal').classList.add('hidden');
    currentPackingRecipeId = null;
}

function addPackingMaterialRow(rawMaterialId = '', quantity = 1, rate = null, isContainer = false) {
    const tbody = document.getElementById('packingRowsBody');
    const emptyState = document.getElementById('packingEmptyState');
    emptyState.classList.add('hidden');

    const tr = document.createElement('tr');
    tr.className = 'hover:bg-amber-50/30 transition-colors packing-material-row';

    // Build options
    let optionsHtml = '<option value="">-- Select Packing Material --</option>';
    allPackingMaterialsList.forEach(pm => {
        const isSelected = (pm.id == rawMaterialId) ? 'selected' : '';
        const pack = pm.pack_name ? ` [${pm.pack_name}]` : '';
        const code = pm.item_code ? ` (${pm.item_code})` : '';
        optionsHtml += `<option value="${pm.id}" data-uom="${pm.uom || 'NOS'}" data-code="${pm.item_code || ''}" ${isSelected}>${pm.name}${pack}${code}</option>`;
    });

    tr.innerHTML = `
        <td class="py-2.5 px-4">
            <select class="w-full border border-slate-200 rounded-xl py-1.5 px-2.5 text-xs font-semibold text-slate-700 bg-white focus:ring-2 focus:ring-amber-400 outline-none pm-select" onchange="onPackingMaterialChange(this)">
                ${optionsHtml}
            </select>
        </td>
        <td class="py-2.5 px-3 text-center">
            <input type="number" step="0.0001" min="0.0001" value="${quantity || 1}" class="w-full border border-slate-200 rounded-xl py-1.5 px-2 text-xs font-bold text-slate-800 text-center focus:ring-2 focus:ring-amber-400 outline-none pm-qty" oninput="calculatePmTotals()">
        </td>
        <td class="py-2.5 px-2 text-center">
            <span class="text-[11px] font-bold text-slate-500 pm-uom">NOS</span>
        </td>
        <td class="py-2.5 px-3 text-center">
            <input type="number" step="0.01" min="0" value="${rate !== null ? rate : ''}" placeholder="0.00" class="w-full border border-slate-200 rounded-xl py-1.5 px-2 text-xs font-bold text-slate-800 text-center focus:ring-2 focus:ring-amber-400 outline-none pm-rate" oninput="calculatePmTotals()">
        </td>
        <td class="py-2.5 px-3 text-center">
            <span class="text-xs font-extrabold text-slate-800 pm-subtotal">₹0.00</span>
        </td>
        <td class="py-2.5 px-2 text-center">
            <input type="checkbox" ${isContainer ? 'checked' : ''} class="w-4 h-4 text-amber-600 rounded border-slate-300 focus:ring-amber-400 pm-container" title="Primary Container (e.g. Can, Bottle)">
        </td>
        <td class="py-2.5 px-3 text-center">
            <button type="button" onclick="removePackingRow(this)" class="w-7 h-7 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center transition">
                <i class="fas fa-trash-alt text-xs"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);

    // Trigger onchange to set UOM and default rate if needed
    const select = tr.querySelector('.pm-select');
    if (rawMaterialId) {
        const selectedOpt = select.options[select.selectedIndex];
        if (selectedOpt) {
            tr.querySelector('.pm-uom').innerText = selectedOpt.getAttribute('data-uom') || 'NOS';
        }
    }

    calculatePmTotals();
}

function onPackingMaterialChange(select) {
    const tr = select.closest('tr');
    const selectedOpt = select.options[select.selectedIndex];
    if (!selectedOpt || !selectedOpt.value) return;

    const uom = selectedOpt.getAttribute('data-uom') || 'NOS';
    const code = selectedOpt.getAttribute('data-code') || '';
    tr.querySelector('.pm-uom').innerText = uom;

    const rateInput = tr.querySelector('.pm-rate');
    // If rate is empty, auto-fill from priceMapObj
    if (!rateInput.value && code && priceMapObj[code]) {
        rateInput.value = parseFloat(priceMapObj[code]).toFixed(2);
    }

    calculatePmTotals();
}

function removePackingRow(btn) {
    const tr = btn.closest('tr');
    tr.remove();
    const tbody = document.getElementById('packingRowsBody');
    if (tbody.querySelectorAll('tr').length === 0) {
        document.getElementById('packingEmptyState').classList.remove('hidden');
    }
    calculatePmTotals();
}

function calculatePmTotals() {
    const rows = document.querySelectorAll('.packing-material-row');
    let grandTotal = 0;
    let count = 0;

    rows.forEach(tr => {
        const qty = parseFloat(tr.querySelector('.pm-qty').value) || 0;
        const rate = parseFloat(tr.querySelector('.pm-rate').value) || 0;
        const subtotal = qty * rate;
        tr.querySelector('.pm-subtotal').innerText = '₹' + subtotal.toFixed(2);
        grandTotal += subtotal;
        if (tr.querySelector('.pm-select').value) {
            count++;
        }
    });

    document.getElementById('pmModalGrandTotal').innerText = '₹' + grandTotal.toFixed(2);
    document.getElementById('pmModalItemCount').innerText = `${count} items configured`;
}

async function savePackingConfig() {
    if (!currentPackingRecipeId) return;

    const rows = document.querySelectorAll('.packing-material-row');
    const materials = [];

    for (let tr of rows) {
        const select = tr.querySelector('.pm-select');
        const rmId = select.value;
        if (!rmId) continue;

        const qty = parseFloat(tr.querySelector('.pm-qty').value) || 0;
        if (qty <= 0) {
            alert('Please enter a valid quantity for all materials.');
            tr.querySelector('.pm-qty').focus();
            return;
        }

        const rate = tr.querySelector('.pm-rate').value !== '' ? parseFloat(tr.querySelector('.pm-rate').value) : null;
        const isContainer = tr.querySelector('.pm-container').checked;

        materials.push({
            raw_material_id: rmId,
            quantity: qty,
            rate: rate,
            is_container: isContainer
        });
    }

    const btn = document.getElementById('btnSavePacking');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
        const response = await fetch(`{{ url('recipes') }}/${currentPackingRecipeId}/packing`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ materials: materials })
        });

        const result = await response.json();

        if (result.success) {
            closePackingModal();
            window.location.reload();
        } else {
            alert(result.message || 'Failed to save packing configuration.');
        }
    } catch (err) {
        console.error('Error saving packing config:', err);
        alert('Server error while saving.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Packing Config';
    }
}
</script>
@endsection
