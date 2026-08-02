@extends('layouts.app')

@section('header', 'Product Master')

@section('content')
<div class="pt-8">



<div class="bg-white rounded-3xl shadow-sm border border-slate-100/80">
    {{-- Header Section --}}
    <div id="pageHeader" class="bg-white px-7 py-5 border-b border-slate-100 flex flex-col md:flex-row md:justify-between md:items-center gap-4 transition-all">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-200/50">
                <i class="fas fa-box-open text-white"></i>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight">Product Master</h3>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Manage your inventory catalog</p>
            </div>
        </div>

        <div class="flex gap-2 flex-wrap items-center">
            @if(Auth::user()->hasPermission('products', 'edit'))
            <div class="flex flex-wrap bg-slate-50 p-1 rounded-xl border border-slate-100 gap-1 mr-2">
                <button onclick="document.getElementById('typeModal').classList.remove('hidden')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-slate-600 hover:bg-white hover:text-purple-600 hover:shadow-sm transition-all" title="Types">
                    <i class="fas fa-tags text-[10px]"></i> Types
                </button>
                <button onclick="document.getElementById('groupModal').classList.remove('hidden')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-slate-600 hover:bg-white hover:text-gray-700 hover:shadow-sm transition-all" title="Groups">
                    <i class="fas fa-layer-group text-[10px]"></i> Groups
                </button>
                 <button onclick="document.getElementById('categoryModal').classList.remove('hidden')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-slate-600 hover:bg-white hover:text-indigo-600 hover:shadow-sm transition-all" title="Categories">
                    <i class="fas fa-list text-[10px]"></i> Categories
                </button>
                 <button onclick="document.getElementById('formModal').classList.remove('hidden')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-slate-600 hover:bg-white hover:text-pink-600 hover:shadow-sm transition-all" title="Forms">
                    <i class="fas fa-shapes text-[10px]"></i> Forms
                </button>
                 <button onclick="document.getElementById('rmTypeModal').classList.remove('hidden')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-slate-600 hover:bg-white hover:text-teal-600 hover:shadow-sm transition-all" title="RM Types">
                    <i class="fas fa-flask text-[10px]"></i> RM Types
                </button>
                 <button onclick="document.getElementById('packNameModal').classList.remove('hidden')" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-slate-600 hover:bg-white hover:text-orange-600 hover:shadow-sm transition-all" title="Pack Names">
                    <i class="fas fa-box text-[10px]"></i> Packs
                </button>
            </div>
            @endif

            <a href="{{ route('products.export', request()->query()) }}" class="bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-700 text-xs font-bold py-2 px-4 rounded-xl transition-colors flex items-center gap-2">
                <i class="fas fa-file-export"></i> Export
            </a>
            
            @if(Auth::user()->hasPermission('products', 'create'))
            <button onclick="document.getElementById('importModal').classList.remove('hidden')" class="bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 text-xs font-bold py-2 px-4 rounded-xl transition-colors flex items-center gap-2">
                <i class="fas fa-file-import"></i> Import
            </button>
            <form id="syncApiForm" action="{{ route('products.sync-api') }}" method="POST" class="inline m-0">
                @csrf
                <button type="submit" onclick="return confirm('Sync all products from Algebra ERP API? This will create new products and update existing ones.')" class="bg-cyan-50 hover:bg-cyan-100 border border-cyan-200 text-cyan-700 text-xs font-bold py-2 px-4 rounded-xl transition-colors flex items-center gap-2">
                    <i class="fas fa-rotate"></i> Sync ERP
                </button>
            </form>
            <button onclick="document.getElementById('productModal').classList.remove('hidden')" class="bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white shadow-md shadow-blue-200/50 text-sm font-bold py-2 px-5 rounded-xl transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> Add Product
            </button>
            @endif
        </div>
    </div>
    
    {{-- Search & Filter Form --}}
    <div class="px-7 py-4 bg-slate-50/50 border-b border-slate-100">
        <form action="{{ route('products.index') }}" method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-grow min-w-[200px]">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Search</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="By Name, Code, Tech Name..." class="w-full bg-white border border-slate-200 rounded-xl py-2 pl-10 pr-4 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm">
                </div>
            </div>
            
            <div class="w-full sm:w-44">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Group</label>
                <select name="group_id" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none">
                    <option value="">All Groups</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>
                            {{ $group->group_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-44">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Type</label>
                <select name="product_type_id" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none">
                    <option value="">All Types</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" {{ request('product_type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->type_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-44">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">RM Type</label>
                <select name="rm_type" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none">
                    <option value="">All RM Types</option>
                    @foreach($rmTypes as $rm)
                        <option value="{{ $rm->value }}" {{ request('rm_type') == $rm->value ? 'selected' : '' }}>
                            {{ $rm->value }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-24">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Per Page</label>
                <select name="per_page" onchange="this.form.submit()" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none">
                    <option value="50" {{ request('per_page', 50) == '50' ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == '100' ? 'selected' : '' }}>100</option>
                    <option value="all" {{ request('per_page') == 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-2.5 px-5 rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2">
                    <i class="fas fa-filter"></i> Apply
                </button>
                @if(request()->hasAny(['search', 'group_id', 'product_type_id', 'rm_type']))
                <a href="{{ route('products.index') }}" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-bold py-2.5 px-4 rounded-xl shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-times"></i> Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    <form id="bulkDeleteForm" action="{{ route('products.bulk_delete') }}" method="POST" onsubmit="return confirm('Are you sure you want to delete selected products?');">
        @csrf
        
        @if(Auth::user()->hasPermission('products', 'delete'))
        <div class="bg-white px-7 py-3 border-b border-slate-100 flex items-center">
            <button type="button" onclick="submitBulkDelete()" class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold py-1.5 px-4 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2" id="bulkDeleteBtn" disabled>
                <i class="fas fa-trash-alt"></i> Delete Selected
            </button>
        </div>
        @endif

        <div>
            <table class="w-full text-left border-collapse">
                <thead id="tableHead" class="sticky top-0 z-10 bg-slate-50 shadow-sm before:content-[''] before:absolute before:bottom-0 before:left-0 before:w-full before:h-px before:bg-slate-200">
                    <tr>
                        <th class="py-3 px-6 border-b border-slate-200"><input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"></th>
                        <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Item Code</th>
                        <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Product</th>
                        <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Details</th>
                        <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200 text-right">Packaging</th>
                        <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200 text-right">Price</th>
                        <th class="py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200 text-center">API Stock</th>
                        <th class="py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($products as $product)
                    <tr class="hover:bg-slate-50/70 transition-colors group">
                        <td class="py-3 px-6"><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" class="product-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500" onclick="updateBulkBtn()"></td>
                        <td class="py-3 px-4">
                            <span class="font-mono text-xs font-bold {{ $product->item_code ? 'text-indigo-600 bg-indigo-50 px-2 py-1 rounded-md' : 'text-slate-400' }}">{{ $product->item_code ?? '-' }}</span>
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-bold text-slate-800 text-sm">{{ $product->name }}</div>
                            <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider mt-0.5">{{ $product->technical_name ?? 'No Tech Name' }}</div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ optional($product->type)->type_name == 'Finished Good' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ optional($product->type)->type_name ?? 'N/A' }}
                                </span>
                                @if($product->rm_type)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                    {{ $product->rm_type }}
                                </span>
                                @endif
                            </div>
                            <div class="text-[11px] text-slate-500">
                                {{ $product->group->group_name ?? 'No Group' }} <span class="text-slate-300 mx-1">•</span> {{ $product->form ?? '-' }}
                            </div>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <div class="text-[12px] font-semibold text-slate-700">{{ $product->pack_name ?? '-' }}</div>
                            <div class="text-[10px] text-slate-400">
                                {{ (float)$product->unit_box }} {{ $product->uom }}/Box <span class="text-slate-300 mx-1">|</span> {{ (float)$product->weight_unit }} Wt/Unit
                            </div>
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-slate-700 text-sm">
                            ₹{{ number_format($product->price, 2) }}
                        </td>
                        <td class="py-3 px-4 text-center">
                            @php
                                $apiStock = $externalStock[$product->item_code] ?? null;
                            @endphp
                            @if($apiStock !== null)
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg text-xs font-black {{ $apiStock > 0 ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-slate-100 text-slate-500 border border-slate-200' }}">
                                    {{ number_format($apiStock, 0) }}
                                </span>
                            @else
                                <span class="text-slate-300 text-xs font-semibold italic">N/A</span>
                            @endif
                        </td>
                        <td class="py-3 px-6 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                @if(Auth::user()->hasPermission('products', 'edit'))
                                <button type="button" 
                                    data-product='@json($product)'
                                    onclick="editProduct(this)" 
                                    class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-blue-600 hover:border-blue-300 hover:bg-blue-50 flex items-center justify-center transition-all shadow-sm">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                @endif
                                
                                @if(Auth::user()->hasPermission('products', 'delete'))
                                <button type="button" onclick="if(confirm('Delete {{ addslashes($product->name) }}?')) { document.getElementById('delete-form-{{ $product->id }}').submit(); }" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-300 hover:bg-red-50 flex items-center justify-center transition-all shadow-sm">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 mb-4">
                                    <i class="fas fa-box-open text-2xl"></i>
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
    </form>
    
    {{-- Individual Delete Forms (Hidden) --}}
    @foreach($products as $product)
        <form id="delete-form-{{ $product->id }}" action="{{ route('products.destroy', $product->id) }}" method="POST" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</div>

{{-- Product Modal --}}
<div id="productModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative mx-auto mt-10 mb-10 p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">

        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Add Product</h3>
            <form id="productForm" method="POST" action="{{ route('products.store') }}" class="mt-2 text-left">
                @csrf
                <input type="hidden" name="_method" id="methodField" value="POST">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                    <input type="text" name="name" id="name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>

                {{-- Group & Type Selects would need data passed to view or fetched via AJAX if strictly keeping single view. 
                     For simplicity, we should pass $groups and $types from controller to index.
                --}}
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Group</label>
                    <select name="group_id" id="group_id" class="shadow border rounded w-full py-2 px-3 text-gray-700">
                        <option value="">Select Group</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->group_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Type</label>
                    <select name="product_type_id" id="product_type_id" class="shadow border rounded w-full py-2 px-3 text-gray-700" required>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                        @endforeach
                    </select>
                </div>

                 <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">UOM</label>
                    <input type="text" name="uom" id="uom" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>

                 <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Price</label>
                    <input type="number" step="0.01" name="price" id="price" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Item Code</label>
                        <input type="text" name="item_code" id="item_code" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                     <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Category</label>
                        <input type="text" list="categoryList" name="category" id="category" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" autocomplete="off">
                        <datalist id="categoryList">
                            @foreach($categories as $cat)
                                <option value="{{ $cat->value }}">
                            @endforeach
                        </datalist>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Form</label>
                        <input type="text" list="formList" name="form" id="form" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" autocomplete="off">
                        <datalist id="formList">
                            @foreach($forms as $f)
                                <option value="{{ $f->value }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Technical Name</label>
                        <input type="text" name="technical_name" id="technical_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                     <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">RM Type</label>
                        <input type="text" list="rmTypeList" name="rm_type" id="rm_type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" autocomplete="off">
                        <datalist id="rmTypeList">
                            @foreach($rmTypes as $rm)
                                <option value="{{ $rm->value }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Pack Name</label>
                        <input type="text" list="packNameList" name="pack_name" id="pack_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" autocomplete="off">
                        <datalist id="packNameList">
                            @foreach($packNames as $pack)
                                <option value="{{ $pack->value }}">
                            @endforeach
                        </datalist>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Unit/Box</label>
                        <input type="text" name="unit_box" id="unit_box" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Weight/Unit</label>
                        <input type="text" name="weight_unit" id="weight_unit" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-4">
                         <label class="block text-gray-700 text-sm font-bold mb-2">Weight (In)</label>
                        <input type="text" name="weight_in" id="weight_in" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Low Stock Alert</label>
                    <input type="number" step="0.001" name="low_alert_quantity" id="low_alert_quantity" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>

                <div class="flex items-center justify-between mt-4">
                    <button type="button" onclick="document.getElementById('productModal').classList.add('hidden'); resetForm();" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Group Modal --}}
<div id="groupModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Manage Groups</h3>
        <form action="{{ route('product-groups.store') }}" method="POST" class="mb-4">
            @csrf
            <div class="flex gap-2">
                <input type="text" name="group_name" placeholder="New Group Name" class="border rounded px-2 py-1 flex-grow" required>
                <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded">Add</button>
            </div>
        </form>
        <ul class="max-h-40 overflow-y-auto">
            @foreach($groups as $group)
            <li class="flex justify-between py-1 border-b">
                <span>{{ $group->group_name }}</span>
                <form action="{{ route('product-groups.destroy', $group->id) }}" method="POST" onsubmit="return confirm('Delete group? Products will be ungrouped.');">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-500"><i class="fas fa-trash"></i></button>
                </form>
            </li>
            @endforeach
        </ul>
        <button onclick="document.getElementById('groupModal').classList.add('hidden')" class="mt-4 text-gray-500">Close</button>
    </div>
</div>

{{-- Type Modal --}}
<div id="typeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Manage Types</h3>
        <form action="{{ route('product-types.store') }}" method="POST" class="mb-4">
            @csrf
            <div class="flex gap-2">
                <input type="text" name="type_name" placeholder="New Type Name" class="border rounded px-2 py-1 flex-grow" required>
                <button type="submit" class="bg-purple-500 text-white px-3 py-1 rounded">Add</button>
            </div>
        </form>
        <ul class="max-h-40 overflow-y-auto">
            @foreach($types as $type)
            <li class="flex justify-between py-1 border-b">
                <span>{{ $type->type_name }}</span>
                <form action="{{ route('product-types.destroy', $type->id) }}" method="POST" onsubmit="return confirm('Delete type?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-500"><i class="fas fa-trash"></i></button>
                </form>
            </li>
            @endforeach
        </ul>
        <button onclick="document.getElementById('typeModal').classList.add('hidden')" class="mt-4 text-gray-500">Close</button>
    </div>
</div>

{{-- Import Modal --}}
<div id="importModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Import Products</h3>
        <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-bold">Excel/CSV File</label>
                    <a href="{{ route('products.import.template') }}" class="text-blue-500 text-sm hover:underline">
                        <i class="fas fa-download mr-1"></i> Download Template
                    </a>
                </div>
                <input type="file" name="excel_file" accept=".xlsx,.csv" required class="w-full">
                <p class="text-xs text-gray-500 mt-1">Format: SNO, ITEM CODE, CATEGORY, FORM, TECHNICAL NAME, RM TYPE, TYPE, ITEM NAME, PACK NAME, UNIT/BOX, WEIGHT/UNIT, WEIGHT(IN)</p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')" class="bg-gray-300 px-4 py-2 rounded">Cancel</button>
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Import</button>
            </div>
        </form>
    </div>
</div>


{{-- Category Modal --}}
<div id="categoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Manage Categories</h3>
        <form action="{{ route('product-attributes.store') }}" method="POST" class="mb-4">
            @csrf
            <input type="hidden" name="type" value="category">
            <div class="flex gap-2">
                <input type="text" name="value" placeholder="New Category" class="border rounded px-2 py-1 flex-grow" required>
                <button type="submit" class="bg-indigo-500 text-white px-3 py-1 rounded">Add</button>
            </div>
        </form>
        <ul class="max-h-40 overflow-y-auto">
            @foreach($categories as $cat)
            <li class="flex justify-between py-1 border-b">
                <span>{{ $cat->value }}</span>
                <form action="{{ route('product-attributes.destroy', $cat->id) }}" method="POST" onsubmit="return confirm('Delete? Products with this category will be set to null.');">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-500"><i class="fas fa-trash"></i></button>
                </form>
            </li>
            @endforeach
        </ul>
        <button onclick="document.getElementById('categoryModal').classList.add('hidden')" class="mt-4 text-gray-500">Close</button>
    </div>
</div>

{{-- Form Modal --}}
<div id="formModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Manage Forms</h3>
        <form action="{{ route('product-attributes.store') }}" method="POST" class="mb-4">
            @csrf
            <input type="hidden" name="type" value="form">
            <div class="flex gap-2">
                <input type="text" name="value" placeholder="New Form" class="border rounded px-2 py-1 flex-grow" required>
                <button type="submit" class="bg-pink-500 text-white px-3 py-1 rounded">Add</button>
            </div>
        </form>
        <ul class="max-h-40 overflow-y-auto">
            @foreach($forms as $f)
            <li class="flex justify-between py-1 border-b">
                <span>{{ $f->value }}</span>
                <form action="{{ route('product-attributes.destroy', $f->id) }}" method="POST" onsubmit="return confirm('Delete?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-500"><i class="fas fa-trash"></i></button>
                </form>
            </li>
            @endforeach
        </ul>
        <button onclick="document.getElementById('formModal').classList.add('hidden')" class="mt-4 text-gray-500">Close</button>
    </div>
</div>

{{-- RM Type Modal --}}
<div id="rmTypeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Manage RM Types</h3>
        <form action="{{ route('product-attributes.store') }}" method="POST" class="mb-4">
            @csrf
            <input type="hidden" name="type" value="rm_type">
            <div class="flex gap-2">
                <input type="text" name="value" placeholder="New RM Type" class="border rounded px-2 py-1 flex-grow" required>
                <button type="submit" class="bg-teal-500 text-white px-3 py-1 rounded">Add</button>
            </div>
        </form>
        <ul class="max-h-40 overflow-y-auto">
            @foreach($rmTypes as $rm)
            <li class="flex justify-between py-1 border-b">
                <span>{{ $rm->value }}</span>
                <form action="{{ route('product-attributes.destroy', $rm->id) }}" method="POST" onsubmit="return confirm('Delete?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-500"><i class="fas fa-trash"></i></button>
                </form>
            </li>
            @endforeach
        </ul>
        <button onclick="document.getElementById('rmTypeModal').classList.add('hidden')" class="mt-4 text-gray-500">Close</button>
    </div>
</div>

{{-- Pack Name Modal --}}
<div id="packNameModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-bold mb-4">Manage Pack Names</h3>
        <form action="{{ route('product-attributes.store') }}" method="POST" class="mb-4">
            @csrf
            <input type="hidden" name="type" value="pack_name">
            <div class="flex gap-2">
                <input type="text" name="value" placeholder="New Pack Name" class="border rounded px-2 py-1 flex-grow" required>
                <button type="submit" class="bg-orange-500 text-white px-3 py-1 rounded">Add</button>
            </div>
        </form>
        <ul class="max-h-40 overflow-y-auto">
            @foreach($packNames as $pack)
            <li class="flex justify-between py-1 border-b">
                <span>{{ $pack->value }}</span>
                <form action="{{ route('product-attributes.destroy', $pack->id) }}" method="POST" onsubmit="return confirm('Delete?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-500"><i class="fas fa-trash"></i></button>
                </form>
            </li>
            @endforeach
        </ul>
        <button onclick="document.getElementById('packNameModal').classList.add('hidden')" class="mt-4 text-gray-500">Close</button>
    </div>
</div>

<script>
    function toggleSelectAll() {
        const checkboxes = document.querySelectorAll('.product-checkbox');
        const selectAll = document.getElementById('selectAll');
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateBulkBtn();
    }

    function updateBulkBtn() {
        const checked = document.querySelectorAll('.product-checkbox:checked').length;
        const btn = document.getElementById('bulkDeleteBtn');
        btn.disabled = checked === 0;
    }

    function submitBulkDelete() {
        document.getElementById('bulkDeleteForm').submit();
    }

    function editProduct(btn) {
        const product = JSON.parse(btn.getAttribute('data-product'));
        
        document.getElementById('productModal').classList.remove('hidden');
        document.getElementById('modalTitle').innerText = 'Edit Product';
        
        // Update Form Action
        let form = document.getElementById('productForm');
        form.action = `{{ url('products') }}/${product.id}`;
        document.getElementById('methodField').value = 'PUT';

        // Populate fields
        document.getElementById('name').value = product.name;
        document.getElementById('group_id').value = product.group_id || '';
        document.getElementById('product_type_id').value = product.product_type_id;
        document.getElementById('uom').value = product.uom;
        document.getElementById('price').value = product.price;
        document.getElementById('low_alert_quantity').value = product.low_alert_quantity;
        
        // New Fields
        document.getElementById('item_code').value = product.item_code || '';
        document.getElementById('category').value = product.category || '';
        document.getElementById('form').value = product.form || '';
        document.getElementById('technical_name').value = product.technical_name || '';
        document.getElementById('rm_type').value = product.rm_type || '';
        document.getElementById('pack_name').value = product.pack_name || '';
        document.getElementById('unit_box').value = product.unit_box || '';
        document.getElementById('weight_unit').value = product.weight_unit || '';
        document.getElementById('weight_in').value = product.weight_in || '';
    }

    function resetForm() {
        let form = document.getElementById('productForm');
        form.action = "{{ route('products.store') }}";
        document.getElementById('methodField').value = 'POST';
        form.reset();
        document.getElementById('modalTitle').innerText = 'Add Product';
    }

    // ===== Sync Report Modal =====
    function openSyncReport(logId) {
        document.getElementById('syncReportModal').classList.remove('hidden');
        document.getElementById('syncReportLoading').classList.remove('hidden');
        document.getElementById('syncReportContent').classList.add('hidden');

        fetch(`{{ url('products/sync-report') }}/${logId}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('syncReportLoading').classList.add('hidden');
                document.getElementById('syncReportContent').classList.remove('hidden');

                // Summary
                document.getElementById('syncSummary').innerHTML = `
                    <div class="flex gap-4 flex-wrap">
                        <div class="bg-green-100 text-green-800 px-4 py-2 rounded-lg font-bold">
                            <i class="fas fa-plus-circle mr-1"></i> Created: ${data.total_created}
                        </div>
                        <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg font-bold">
                            <i class="fas fa-edit mr-1"></i> Updated: ${data.total_updated}
                        </div>
                        <div class="bg-gray-100 text-gray-800 px-4 py-2 rounded-lg font-bold">
                            <i class="fas fa-forward mr-1"></i> Skipped: ${data.total_skipped}
                        </div>
                        <div class="bg-purple-100 text-purple-800 px-4 py-2 rounded-lg font-bold">
                            <i class="fas fa-user mr-1"></i> By: ${data.synced_by}
                        </div>
                        <div class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded-lg font-bold">
                            <i class="fas fa-clock mr-1"></i> ${new Date(data.created_at).toLocaleString('en-IN')}
                        </div>
                    </div>
                `;

                // Store data globally for tab switching
                window.syncReportData = data;
                showSyncTab('created');
            })
            .catch(err => {
                document.getElementById('syncReportLoading').innerHTML = '<p class="text-red-500">Failed to load report.</p>';
            });
    }

    function showSyncTab(tab) {
        const data = window.syncReportData;
        const items = tab === 'created' ? (data.created_items || []) : (data.updated_items || []);
        
        // Update tab buttons
        document.getElementById('tabCreated').className = tab === 'created' 
            ? 'px-4 py-2 rounded-t-lg font-bold bg-white text-green-700 border border-b-0 border-gray-300' 
            : 'px-4 py-2 rounded-t-lg font-bold bg-gray-200 text-gray-600 hover:bg-gray-300 cursor-pointer';
        document.getElementById('tabUpdated').className = tab === 'updated' 
            ? 'px-4 py-2 rounded-t-lg font-bold bg-white text-blue-700 border border-b-0 border-gray-300' 
            : 'px-4 py-2 rounded-t-lg font-bold bg-gray-200 text-gray-600 hover:bg-gray-300 cursor-pointer';

        window.currentSyncTab = tab;
        window.currentSyncItems = items;
        renderSyncTable(items);
    }

    function filterSyncTable() {
        const search = document.getElementById('syncSearch').value.toLowerCase();
        const filtered = window.currentSyncItems.filter(item => 
            item.item_code.toLowerCase().includes(search) || 
            item.name.toLowerCase().includes(search) ||
            (item.type && item.type.toLowerCase().includes(search))
        );
        renderSyncTable(filtered);
    }

    function renderSyncTable(items) {
        const label = window.currentSyncTab === 'created' ? 'Created' : 'Updated';
        let html = `<p class="text-sm text-gray-500 mb-2">Showing ${items.length} ${label.toLowerCase()} products</p>`;
        if (items.length === 0) {
            html += `<p class="text-center text-gray-400 py-8"><i class="fas fa-inbox text-3xl mb-2"></i><br>No ${label.toLowerCase()} products in this sync.</p>`;
        } else {
            html += `<div class="overflow-y-auto max-h-96 border rounded">`;
            html += `<table class="min-w-full text-sm">`;
            html += `<thead class="bg-gray-50 sticky top-0"><tr>
                <th class="px-3 py-2 text-left font-bold text-gray-600">#</th>
                <th class="px-3 py-2 text-left font-bold text-gray-600">Item Code</th>
                <th class="px-3 py-2 text-left font-bold text-gray-600">Product Name</th>
                <th class="px-3 py-2 text-left font-bold text-gray-600">Type</th>
            </tr></thead><tbody>`;
            items.forEach((item, i) => {
                const rowClass = i % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                html += `<tr class="${rowClass} border-b">
                    <td class="px-3 py-2 text-gray-400">${i + 1}</td>
                    <td class="px-3 py-2 font-mono text-xs font-bold text-indigo-600">${item.item_code}</td>
                    <td class="px-3 py-2">${item.name}</td>
                    <td class="px-3 py-2"><span class="px-2 py-1 rounded-full text-xs ${item.type === 'Finished Good' ? 'bg-green-100 text-green-700' : item.type === 'Raw Material' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'}">${item.type || 'N/A'}</span></td>
                </tr>`;
            });
            html += `</tbody></table></div>`;
        }
        document.getElementById('syncTableBody').innerHTML = html;
    }

    function closeSyncReport() {
        document.getElementById('syncReportModal').classList.add('hidden');
    }
</script>

{{-- Sync Report Modal --}}
<div id="syncReportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-6 border w-full max-w-4xl shadow-2xl rounded-lg bg-white mb-10">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-sync-alt text-cyan-500 mr-2"></i>API Sync Report</h3>
            <button onclick="closeSyncReport()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>

        {{-- Loading Spinner --}}
        <div id="syncReportLoading" class="text-center py-10 hidden">
            <i class="fas fa-spinner fa-spin text-4xl text-cyan-500"></i>
            <p class="mt-2 text-gray-500">Loading sync report...</p>
        </div>

        {{-- Report Content --}}
        <div id="syncReportContent" class="hidden">
            {{-- Summary Cards --}}
            <div id="syncSummary" class="mb-4"></div>

            {{-- Tabs --}}
            <div class="flex gap-1 mt-4">
                <button id="tabCreated" onclick="showSyncTab('created')" class="px-4 py-2 rounded-t-lg font-bold bg-white text-green-700 border border-b-0 border-gray-300">
                    <i class="fas fa-plus-circle mr-1"></i> Created
                </button>
                <button id="tabUpdated" onclick="showSyncTab('updated')" class="px-4 py-2 rounded-t-lg font-bold bg-gray-200 text-gray-600 hover:bg-gray-300 cursor-pointer">
                    <i class="fas fa-edit mr-1"></i> Updated
                </button>
            </div>

            {{-- Search --}}
            <div class="border border-gray-300 rounded-b-lg rounded-tr-lg p-4 bg-white">
                <input type="text" id="syncSearch" oninput="filterSyncTable()" placeholder="Search by Item Code or Name..." class="w-full md:w-1/2 rounded border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 py-2 px-3 border mb-3">
                <div id="syncTableBody"></div>
            </div>
        </div>

        <div class="mt-4 text-right">
            <button onclick="closeSyncReport()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded">Close</button>
        </div>
    </div>
</div>

</div>
@endsection
