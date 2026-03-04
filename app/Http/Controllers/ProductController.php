<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        Product::whereIn('id', $request->product_ids)->delete();

        return redirect()->back()->with('success', 'Selected products deleted successfully.');
    }

    public function index(Request $request)
    {
        $query = Product::with(['group', 'type'])->orderBy('name');

        // Apply Access Control
        $this->applyTypeFilters($query);

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%")
                  ->orWhere('technical_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('group_id') && $request->group_id != '') {
            $query->where('group_id', $request->group_id);
        }

        if ($request->has('product_type_id') && $request->product_type_id != '') {
            $query->where('product_type_id', $request->product_type_id);
        }

        if ($request->has('rm_type') && $request->rm_type != '') {
            $query->where('rm_type', $request->rm_type);
        }

        $perPage = $request->input('per_page', 50);
        if ($perPage === 'all') {
            $perPage = 1000000; // Large number to show all
        }

        $products = $query->paginate($perPage)->withQueryString();
        $groups = ProductGroup::orderBy('group_name')->get();
        
        $typesQuery = ProductType::orderBy('type_name');
        $rmTypesQuery = \App\Models\ProductAttribute::where('type', 'rm_type')->orderBy('value');
        
        $user = Auth::user();
        if ($user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $permittedRMTypes = $user->getPermittedRMTypes();
            
            $typesQuery->whereIn('id', $permittedTypeIds);
            $rmTypesQuery->whereIn('value', $permittedRMTypes);
        }
        
        $types = $typesQuery->get();

        // Fetch distinct values for dropdowns/datalists from ProductAttribute model
        $categories = \App\Models\ProductAttribute::where('type', 'category')->orderBy('value')->get(['id', 'value']);
        $forms = \App\Models\ProductAttribute::where('type', 'form')->orderBy('value')->get(['id', 'value']);
        $rmTypes = $rmTypesQuery->get(['id', 'value']);
        $packNames = \App\Models\ProductAttribute::where('type', 'pack_name')->orderBy('value')->get(['id', 'value']);

        return view('products.index', compact('products', 'groups', 'types', 'categories', 'forms', 'rmTypes', 'packNames'));
    }

    public function create()
    {
        $groups = ProductGroup::orderBy('group_name')->get();
        $types = ProductType::orderBy('type_name')->get();
        // return view('products.create', compact('groups', 'types'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'uom' => 'required|string|max:50',
            'price' => 'numeric|min:0',
            'group_id' => 'nullable|exists:product_groups,id',
            'product_type_id' => 'required|exists:product_types,id',
            'low_alert_quantity' => 'numeric|min:0',
            'item_code' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'form' => 'nullable|string|max:255',
            'technical_name' => 'nullable|string|max:255',
            'rm_type' => 'nullable|string|max:255',
            'pack_name' => 'nullable|string|max:255',
            'unit_box' => 'nullable|string|max:255',
            'weight_unit' => 'nullable|string|max:255',
            'weight_in' => 'nullable|string|max:255',
        ]);

        Product::create($validated);

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $groups = ProductGroup::orderBy('group_name')->get();
        $types = ProductType::orderBy('type_name')->get();
        // return view('products.edit', compact('product', 'groups', 'types'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'uom' => 'required|string|max:50',
            'price' => 'numeric|min:0',
            'group_id' => 'nullable|exists:product_groups,id',
            'product_type_id' => 'required|exists:product_types,id',
            'low_alert_quantity' => 'numeric|min:0',
            'item_code' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'form' => 'nullable|string|max:255',
            'technical_name' => 'nullable|string|max:255',
            'rm_type' => 'nullable|string|max:255',
            'pack_name' => 'nullable|string|max:255',
            'unit_box' => 'nullable|string|max:255',
            'weight_unit' => 'nullable|string|max:255',
            'weight_in' => 'nullable|string|max:255',
        ]);

        $product->update($validated);

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }


    public function export(Request $request)
    {
        $filters = $request->only(['search', 'group_id', 'product_type_id', 'rm_type']);
        return (new \App\Exports\ProductsExport($filters))->download('products_master.xlsx');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

    protected function applyTypeFilters($query)
    {
        $user = Auth::user();
        if (!$user || $user->role === 'admin') {
            return $query;
        }

        $permittedTypeIds = $user->getPermittedProductTypeIds();
        $permittedRMTypes = $user->getPermittedRMTypes();

        return $query->whereIn('product_type_id', $permittedTypeIds)
            ->where(function ($q) use ($permittedRMTypes) {
                $q->whereIn('rm_type', $permittedRMTypes)
                    ->orWhereNull('rm_type')
                    ->orWhere('rm_type', '');
            });
    }
}
