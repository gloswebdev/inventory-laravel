<?php

namespace App\Http\Controllers;

use App\Models\CostingBom;
use App\Models\CostingBomItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CostingBomController extends Controller
{
    public function index(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_bom', 'view')) {
            abort(403, 'Access denied to Costing BOM module.');
        }

        $query = CostingBom::with(['finishedProduct.type', 'items.rawMaterial']);
        $user = Auth::user();

        // Apply access control (similar to Recipe Master)
        if ($user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $permittedRMTypes = $user->getPermittedRMTypes();
            
            $query->whereHas('finishedProduct', function($q) use ($permittedTypeIds, $permittedRMTypes) {
                $q->whereIn('product_type_id', $permittedTypeIds)
                  ->where(function($sq) use ($permittedRMTypes) {
                      $sq->whereIn('rm_type', $permittedRMTypes)
                        ->orWhereNull('rm_type')
                        ->orWhere('rm_type', '');
                  });
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('finishedProduct', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type_id')) {
            $query->whereHas('finishedProduct', function($q) use ($request) {
                $q->where('product_type_id', $request->type_id);
            });
        }

        if ($request->filled('badge')) {
            if ($request->badge === 'standard') {
                $query->where(function($q) {
                    $q->whereNull('badge')->orWhere('badge', '');
                });
            } else {
                $query->where('badge', $request->badge);
            }
        }

        $perPage = $request->get('per_page', 20);
        if ($perPage === 'all') {
            $boms = $query->orderByDesc('created_at')->get();
        } else {
            $boms = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();
        }

        $fgQuery = Product::whereHas('type', function($q) {
            $q->whereIn('type_name', ['Semi Finished Good', 'Semi Finished Goods', 'SEMI FINISHED GOOD', 'SEMI FINISHED GOODS']);
        })->orderBy('name');

        $rmQuery = Product::whereHas('type', function($q) {
            $q->whereIn('type_name', ['RAW MATERIAL', 'PACKING MATERIAL', 'Raw Material', 'Packing Material']);
        })->orderBy('name');

        $typesQuery = \App\Models\ProductType::orderBy('type_name');

        if ($user->role !== 'admin') {
            $this->applyTypeFilters($fgQuery);
            $this->applyTypeFilters($rmQuery);
            
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $typesQuery->whereIn('id', $permittedTypeIds);
        }

        $finishedGoods = $fgQuery->get(['id', 'name', 'pack_name', 'uom', 'item_code', 'product_type_id']);
        $rawMaterials  = $rmQuery->get(['id', 'name', 'pack_name', 'uom', 'item_code', 'product_type_id', 'rm_type']);
        $types         = $typesQuery->get();
        $bomPurities = \App\Models\CostingBomItem::whereNotNull('purity')
            ->where('purity', '>', 0)
            ->with('rawMaterial')
            ->orderByDesc('id')
            ->get()
            ->filter(fn($item) => $item->rawMaterial !== null)
            ->unique('rawMaterial.item_code')
            ->pluck('purity', 'rawMaterial.item_code')
            ->toArray();

        $pricePurities = \App\Models\ProductPrice::whereNotNull('purity')
            ->where('purity', '>', 0)
            ->pluck('purity', 'item_code')
            ->toArray();

        $prPurities = \App\Models\PurchaseRegister::whereNotNull('purity')
            ->where('purity', '>', 0)
            ->orderByDesc('vouch_date')
            ->orderByDesc('id')
            ->get()
            ->unique('item_code')
            ->pluck('purity', 'item_code')
            ->toArray();

        $purities = array_merge($bomPurities, $pricePurities, $prPurities);

        $pricelists = \App\Models\Pricelist::where('group5', 'FINISHED GOODS')
            ->get(['id', 'item_hd_name', 'user_code', 'size', 'cf_1', 'group3']);

        $localPrices = \App\Models\ProductPrice::pluck('price_per_unit', 'item_code')->toArray();
        $prPrices = \App\Models\PurchaseRegister::orderByDesc('vouch_date')
            ->orderByDesc('id')
            ->get()
            ->unique('item_code')
            ->pluck('case_rate', 'item_code')
            ->toArray();
        $pmRates = array_merge($localPrices, $prPrices);

        return view('costing.bom.index', compact('boms', 'finishedGoods', 'rawMaterials', 'types', 'purities', 'pricelists', 'pmRates'));
    }

    public function store(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_bom', 'create')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'finished_product_id' => [
                'required',
                'exists:products,id',
                Rule::unique('costing_boms')->where(function ($query) use ($request) {
                    return $query->where('finished_product_id', $request->finished_product_id)
                                 ->where('badge', $request->badge);
                }),
            ],
            'badge' => 'nullable|string|in:small,big,bulk',
            'formulation' => 'nullable|numeric|min:0.001|max:100',
            'density' => 'nullable|numeric|min:0.001|max:10',
            'yield_quantity' => 'required|numeric|min:0.001',
            'yield_uom'           => 'required|string|max:50',
            'items'               => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:products,id',
            'items.*.quantity'    => 'required|numeric|min:0.001',
            'items.*.purity'      => 'nullable|numeric|min:0.1|max:100',
            'items.*.rate'        => 'nullable|numeric|min:0.01',
            'packing_materials'   => 'nullable|array',
            'packing_materials.*.pricelist_id'    => 'required|exists:pricelists,id',
            'packing_materials.*.raw_material_id' => 'required|exists:products,id',
            'packing_materials.*.quantity'        => 'required|numeric|min:0.001',
            'packing_materials.*.is_container'    => 'nullable|boolean',
        ], [
            'finished_product_id.unique' => 'A Costing BOM for this product with this badge already exists.',
        ]);

        $rawMaterialIds = array_column($validated['items'], 'raw_material_id');
        if (count($rawMaterialIds) !== count(array_unique($rawMaterialIds))) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Duplicate raw materials are not allowed.'], 422);
            }
            return back()->withErrors(['items' => 'Duplicate raw materials are not allowed.'])->withInput();
        }

        $totalRmQty = array_sum(array_column($validated['items'], 'quantity'));
        if ($totalRmQty > $validated['yield_quantity']) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => "Validation Error: Total raw material quantity ({$totalRmQty}) cannot exceed the Batch Quantity ({$validated['yield_quantity']})."], 422);
            }
            return back()->withErrors(['items' => "Total raw material quantity ({$totalRmQty}) cannot exceed the Batch Quantity ({$validated['yield_quantity']})."])->withInput();
        }

        DB::transaction(function () use ($validated) {
            $bom = CostingBom::create([
                'finished_product_id' => $validated['finished_product_id'],
                'yield_quantity'      => $validated['yield_quantity'],
                'yield_uom'           => $validated['yield_uom'],
                'badge'               => $validated['badge'] ?? null,
                'formulation'         => $validated['formulation'] ?? null,
                'density'             => $validated['density'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                CostingBomItem::create([
                    'costing_bom_id'  => $bom->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity'        => $item['quantity'],
                    'purity'          => $item['purity'] ?? null,
                ]);

                // Save manually entered purity to product_prices if provided
                if (!empty($item['purity'])) {
                    $product = Product::find($item['raw_material_id']);
                    if ($product && !empty($product->item_code)) {
                        \App\Models\ProductPrice::updateOrCreate(
                            ['item_code' => $product->item_code],
                            ['purity' => $item['purity']]
                        );
                    }
                }

                // Save manually entered rate to product_prices if provided
                if (!empty($item['rate'])) {
                    $product = Product::find($item['raw_material_id']);
                    if ($product && !empty($product->item_code)) {
                        \App\Models\ProductPrice::updateOrCreate(
                            ['item_code' => $product->item_code],
                            ['price_per_unit' => $item['rate']]
                        );
                    }
                }
            }

            if (!empty($validated['packing_materials'])) {
                foreach ($validated['packing_materials'] as $pm) {
                    \App\Models\CostingBomPackingMaterial::create([
                        'costing_bom_id'  => $bom->id,
                        'pricelist_id'    => $pm['pricelist_id'],
                        'raw_material_id' => $pm['raw_material_id'],
                        'quantity'        => $pm['quantity'],
                        'is_container'    => !empty($pm['is_container']),
                    ]);
                }
            }
        });

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Costing BOM created successfully.']);
        }
        return redirect()->route('costing.boms.index')->with('success', 'Costing BOM created successfully.');
    }

    public function update(Request $request, CostingBom $costingBom)
    {
        if (!Auth::user()->hasPermission('costing_bom', 'edit')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'finished_product_id' => [
                'required',
                'exists:products,id',
                Rule::unique('costing_boms')->where(function ($query) use ($request, $costingBom) {
                    return $query->where('finished_product_id', $request->finished_product_id)
                                 ->where('badge', $request->badge)
                                 ->where('id', '!=', $costingBom->id);
                }),
            ],
            'badge' => 'nullable|string|in:small,big,bulk',
            'formulation' => 'nullable|numeric|min:0.001|max:100',
            'density' => 'nullable|numeric|min:0.001|max:10',
            'yield_quantity' => 'required|numeric|min:0.001',
            'yield_uom'           => 'required|string|max:50',
            'items'               => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:products,id',
            'items.*.quantity'    => 'required|numeric|min:0.001',
            'items.*.purity'      => 'nullable|numeric|min:0.1|max:100',
            'items.*.rate'        => 'nullable|numeric|min:0.01',
            'packing_materials'   => 'nullable|array',
            'packing_materials.*.pricelist_id'    => 'required|exists:pricelists,id',
            'packing_materials.*.raw_material_id' => 'required|exists:products,id',
            'packing_materials.*.quantity'        => 'required|numeric|min:0.001',
            'packing_materials.*.is_container'    => 'nullable|boolean',
        ], [
            'finished_product_id.unique' => 'A Costing BOM for this product with this badge already exists.',
        ]);

        $rawMaterialIds = array_column($validated['items'], 'raw_material_id');
        if (count($rawMaterialIds) !== count(array_unique($rawMaterialIds))) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Duplicate raw materials are not allowed.'], 422);
            }
            return back()->withErrors(['items' => 'Duplicate raw materials are not allowed.'])->withInput();
        }

        $totalRmQty = array_sum(array_column($validated['items'], 'quantity'));
        if ($totalRmQty > $validated['yield_quantity']) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => "Validation Error: Total raw material quantity ({$totalRmQty}) cannot exceed the Batch Quantity ({$validated['yield_quantity']})."], 422);
            }
            return back()->withErrors(['items' => "Total raw material quantity ({$totalRmQty}) cannot exceed the Batch Quantity ({$validated['yield_quantity']})."])->withInput();
        }

        DB::transaction(function () use ($costingBom, $validated) {
            $costingBom->update([
                'finished_product_id' => $validated['finished_product_id'],
                'yield_quantity'      => $validated['yield_quantity'],
                'yield_uom'           => $validated['yield_uom'],
                'badge'               => $validated['badge'] ?? null,
                'formulation'         => $validated['formulation'] ?? null,
                'density'             => $validated['density'] ?? null,
            ]);

            $costingBom->items()->delete();

            foreach ($validated['items'] as $item) {
                CostingBomItem::create([
                    'costing_bom_id'  => $costingBom->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity'        => $item['quantity'],
                    'purity'          => $item['purity'] ?? null,
                ]);

                // Save manually entered purity to product_prices if provided
                if (!empty($item['purity'])) {
                    $product = Product::find($item['raw_material_id']);
                    if ($product && !empty($product->item_code)) {
                        \App\Models\ProductPrice::updateOrCreate(
                            ['item_code' => $product->item_code],
                            ['purity' => $item['purity']]
                        );
                    }
                }

                // Save manually entered rate to product_prices if provided
                if (!empty($item['rate'])) {
                    $product = Product::find($item['raw_material_id']);
                    if ($product && !empty($product->item_code)) {
                        \App\Models\ProductPrice::updateOrCreate(
                            ['item_code' => $product->item_code],
                            ['price_per_unit' => $item['rate']]
                        );
                    }
                }
            }

            $costingBom->packingMaterials()->delete();
            if (!empty($validated['packing_materials'])) {
                foreach ($validated['packing_materials'] as $pm) {
                    \App\Models\CostingBomPackingMaterial::create([
                        'costing_bom_id'  => $costingBom->id,
                        'pricelist_id'    => $pm['pricelist_id'],
                        'raw_material_id' => $pm['raw_material_id'],
                        'quantity'        => $pm['quantity'],
                        'is_container'    => !empty($pm['is_container']),
                    ]);
                }
            }
        });

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Costing BOM updated successfully.']);
        }
        return redirect()->route('costing.boms.index')->with('success', 'Costing BOM updated successfully.');
    }

    public function destroy(CostingBom $costingBom)
    {
        if (!Auth::user()->hasPermission('costing_bom', 'delete')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        DB::transaction(function () use ($costingBom) {
            $costingBom->items()->delete();
            $costingBom->delete();
        });

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Costing BOM deleted successfully.']);
        }
        return redirect()->route('costing.boms.index')->with('success', 'Costing BOM deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_bom', 'delete')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:costing_boms,id'
        ]);

        DB::transaction(function () use ($request) {
            CostingBomItem::whereIn('costing_bom_id', $request->ids)->delete();
            CostingBom::whereIn('id', $request->ids)->delete();
        });

        return response()->json(['success' => true, 'message' => 'Selected Costing BOMs deleted.']);
    }

    public function duplicate(Request $request, CostingBom $costingBom)
    {
        if (!Auth::user()->hasPermission('costing_bom', 'create')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $request->validate([
            'badge' => 'required|string|in:small,big,bulk',
        ]);

        // Check if a BOM with this finished_product_id and this badge already exists
        $exists = CostingBom::where('finished_product_id', $costingBom->finished_product_id)
            ->where('badge', $request->badge)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'A Costing BOM for this product with this badge already exists.'], 422);
        }

        DB::transaction(function () use ($costingBom, $request) {
            $newBom = CostingBom::create([
                'finished_product_id' => $costingBom->finished_product_id,
                'yield_quantity'      => $costingBom->yield_quantity,
                'yield_uom'           => $costingBom->yield_uom,
                'badge'               => $request->badge,
                'formulation'         => $costingBom->formulation,
                'density'             => $costingBom->density,
            ]);

            foreach ($costingBom->items as $item) {
                CostingBomItem::create([
                    'costing_bom_id'  => $newBom->id,
                    'raw_material_id' => $item->raw_material_id,
                    'quantity'        => $item->quantity,
                    'purity'          => $item->purity,
                ]);
            }

            foreach ($costingBom->packingMaterials as $pm) {
                \App\Models\CostingBomPackingMaterial::create([
                    'costing_bom_id'  => $newBom->id,
                    'pricelist_id'    => $pm->pricelist_id,
                    'raw_material_id' => $pm->raw_material_id,
                    'quantity'        => $pm->quantity,
                    'is_container'    => $pm->is_container,
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Costing BOM duplicated successfully.']);
    }

    public function export(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_bom', 'view')) {
            abort(403);
        }

        return (new \App\Exports\CostingBomsExport($request->search))->download('costing_boms.xlsx');
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
