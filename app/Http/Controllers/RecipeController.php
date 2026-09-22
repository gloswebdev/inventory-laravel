<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user && !$user->hasPermission('recipes', 'view')) {
            abort(403, 'Unauthorized access to Recipe Master.');
        }

        $query = Product::with(['type', 'recipe.items.rawMaterial.type', 'costingBoms']);

        // Apply Access Control
        if ($user && $user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $permittedRMTypes = $user->getPermittedRMTypes();
            
            $query->whereIn('product_type_id', $permittedTypeIds)
                  ->where(function($sq) use ($permittedRMTypes) {
                      $sq->whereIn('rm_type', $permittedRMTypes)
                        ->orWhereNull('rm_type')
                        ->orWhere('rm_type', '');
                  });
        }

        // Filter by Product Type: default to Finished Good (6) and Semi Finished Good (7)
        if ($request->filled('type_id')) {
            $query->where('product_type_id', $request->type_id);
        } else {
            $query->whereIn('product_type_id', [6, 7]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%")
                  ->orWhere('pack_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        if ($perPage === 'all') {
            $products = $query->orderBy('name')->get();
        } else {
            $products = $query->orderBy('name')->paginate($perPage)->withQueryString();
        }
        
        $fgQuery = Product::whereIn('product_type_id', [6, 7])->orderBy('name');
        $rmQuery = Product::whereIn('product_type_id', [4, 5])->orderBy('name');
        $typesQuery = \App\Models\ProductType::orderBy('type_name');
        
        if ($user && $user->role !== 'admin') {
            $this->applyTypeFilters($fgQuery);
            $this->applyTypeFilters($rmQuery);
            
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $typesQuery->whereIn('id', $permittedTypeIds);
        }

        $finishedGoods = $fgQuery->get();
        $rawMaterials = $rmQuery->get();
        $types = $typesQuery->get();

        return view('recipes.index', compact('products', 'finishedGoods', 'rawMaterials', 'types'));
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            Recipe::whereIn('id', $request->ids)
                ->orWhereIn('finished_product_id', $request->ids)
                ->delete();
        });

        return response()->json(['success' => true]);
    }

    public function update(Request $request, Recipe $recipe)
    {
        $validated = $request->validate([
            'finished_product_id' => 'required|exists:products,id|unique:recipes,finished_product_id,' . $recipe->id,
            'yield_quantity' => 'required|numeric|min:0.001',
            'yield_uom' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($recipe, $validated) {
            $recipe->update([
                'finished_product_id' => $validated['finished_product_id'],
                'yield_quantity'      => $validated['yield_quantity'],
                'yield_uom'           => $validated['yield_uom'],
            ]);
            $recipe->items()->delete();
            foreach ($validated['items'] as $item) {
                RecipeItem::create([
                    'recipe_id'       => $recipe->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity'        => $item['quantity'],
                ]);
            }
        });

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Recipe updated successfully.']);
        }
        return redirect()->route('recipes.index')->with('success', 'Recipe updated successfully.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'finished_product_id' => 'required|exists:products,id|unique:recipes,finished_product_id',
            'yield_quantity' => 'required|numeric|min:0.001',
            'yield_uom' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($validated) {
            $recipe = Recipe::create([
                'finished_product_id' => $validated['finished_product_id'],
                'yield_quantity'      => $validated['yield_quantity'],
                'yield_uom'           => $validated['yield_uom'],
            ]);

            foreach ($validated['items'] as $item) {
                RecipeItem::create([
                    'recipe_id'       => $recipe->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity'        => $item['quantity'],
                ]);
            }
        });

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Recipe created successfully.']);
        }
        return redirect()->route('recipes.index')->with('success', 'Recipe created successfully.');
    }


    public function export(Request $request)
    {
        return (new \App\Exports\RecipesExport($request->search))->download('recipes_master.xlsx');
    }


    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\RecipeTemplateExport, 'recipe_import_template.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx,csv,xls',
        ]);

        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\RecipesImport, $request->file('excel_file'));

        return redirect()->route('recipes.index')->with('success', 'Recipes imported successfully.');
    }

    public function destroy(Recipe $recipe)
    {
        $recipe->delete();
        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Recipe deleted.']);
        }
        return redirect()->route('recipes.index')->with('success', 'Recipe deleted successfully.');
    }

    public function getPackingConfig($id)
    {
        $user = Auth::user();
        if ($user && !$user->hasPermission('recipes', 'view')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $product = Product::with('type')->find($id);
        if ($product) {
            $recipe = Recipe::firstOrCreate(
                ['finished_product_id' => $product->id],
                [
                    'yield_quantity' => 1,
                    'yield_uom'      => $product->uom ?: 'BOX',
                ]
            );
        } else {
            $recipe = Recipe::findOrFail($id);
            $product = $recipe->finishedProduct;
        }

        $recipe->load(['finishedProduct.type', 'items.rawMaterial.type']);
        $product = $recipe->finishedProduct;

        // Fetch all products from Product Master that are PACKING MATERIAL
        $allPackingMaterials = Product::whereHas('type', function ($q) {
            $q->whereIn('type_name', ['PACKING MATERIAL', 'Packing Material']);
        })->orderBy('name')->get(['id', 'name', 'item_code', 'pack_name', 'uom', 'rm_type']);

        // Rates map from ProductPrice
        $priceMap = \App\Models\ProductPrice::allAsMap();

        // Current packing materials from RecipeItems (where type is PACKING MATERIAL)
        $currentMaterials = [];
        foreach ($recipe->items as $item) {
            $rm = $item->rawMaterial;
            if (!$rm) continue;

            $typeName = $rm->type->type_name ?? '';
            $isPacking = str_contains(strtoupper($typeName), 'PACKING') || in_array(strtoupper($rm->rm_type ?? ''), ['DRUM', 'BAG', 'BOTTLE', 'CAP', 'CARTON', 'LABEL', 'TAPE', 'BOX']);

            if ($isPacking) {
                $rate = $priceMap[$rm->item_code] ?? null;
                $currentMaterials[] = [
                    'raw_material_id' => $rm->id,
                    'name'            => $rm->name,
                    'item_code'       => $rm->item_code,
                    'pack_name'       => $rm->pack_name,
                    'uom'             => $rm->uom ?? 'NOS',
                    'quantity'        => (float) $item->quantity,
                    'rate'            => $rate !== null ? (float) $rate : null,
                    'is_container'    => in_array(strtoupper($rm->rm_type ?? ''), ['DRUM', 'BOTTLE', 'CAN', 'CONTAINER']),
                ];
            }
        }

        // Also check if CostingBomPackingMaterial has records for this product's pricelist entry
        $pricelist = \App\Models\Pricelist::where('user_code', $product->item_code)->first();
        if (empty($currentMaterials) && $pricelist) {
            $cbpm = \App\Models\CostingBomPackingMaterial::with('rawMaterial.type')
                ->where('pricelist_id', $pricelist->id)
                ->get();

            foreach ($cbpm as $pm) {
                $rm = $pm->rawMaterial;
                if (!$rm) continue;
                $rate = $priceMap[$rm->item_code] ?? null;
                $currentMaterials[] = [
                    'raw_material_id' => $rm->id,
                    'name'            => $rm->name,
                    'item_code'       => $rm->item_code,
                    'pack_name'       => $rm->pack_name,
                    'uom'             => $rm->uom ?? 'NOS',
                    'quantity'        => (float) $pm->quantity,
                    'rate'            => $rate !== null ? (float) $rate : null,
                    'is_container'    => (bool) $pm->is_container,
                ];
            }
        }

        return response()->json([
            'success'               => true,
            'recipe_id'             => $recipe->id,
            'product'               => [
                'id'        => $product->id,
                'name'      => $product->name,
                'item_code' => $product->item_code,
                'pack_name' => $product->pack_name,
                'unit_box'  => $product->unit_box,
                'uom'       => $product->uom,
            ],
            'current_materials'     => $currentMaterials,
            'all_packing_materials' => $allPackingMaterials,
            'price_map'             => $priceMap,
        ]);
    }

    public function savePackingConfig(Request $request, $id)
    {
        $user = Auth::user();
        if ($user && !$user->hasPermission('recipes', 'edit') && !$user->hasPermission('recipes', 'create')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: You do not have permission to edit recipes.'], 403);
        }
        if ($user && !$user->hasFeature('recipes', 'packing_config')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Packaging Configuration (Step 4) feature is disabled for your user account.'], 403);
        }

        $product = Product::find($id);
        if ($product) {
            $recipe = Recipe::firstOrCreate(
                ['finished_product_id' => $product->id],
                [
                    'yield_quantity' => 1,
                    'yield_uom'      => $product->uom ?: 'BOX',
                ]
            );
        } else {
            $recipe = Recipe::findOrFail($id);
            $product = $recipe->finishedProduct;
        }

        $validated = $request->validate([
            'materials'                      => 'nullable|array',
            'materials.*.raw_material_id'    => 'required|exists:products,id',
            'materials.*.quantity'           => 'required|numeric|min:0.0001',
            'materials.*.rate'               => 'nullable|numeric|min:0',
            'materials.*.is_container'       => 'nullable|boolean',
        ]);

        $materials = $validated['materials'] ?? [];
        $product   = $recipe->finishedProduct;

        DB::transaction(function () use ($recipe, $product, $materials) {
            // 1. In recipe_items: Remove existing PACKING MATERIAL items and add new ones
            $existingPackingIds = [];
            foreach ($recipe->items()->with('rawMaterial.type')->get() as $item) {
                $rm = $item->rawMaterial;
                if ($rm) {
                    $typeName = $rm->type->type_name ?? '';
                    if (str_contains(strtoupper($typeName), 'PACKING') || in_array(strtoupper($rm->rm_type ?? ''), ['DRUM', 'BAG', 'BOTTLE', 'CAP', 'CARTON', 'LABEL', 'TAPE', 'BOX'])) {
                        $existingPackingIds[] = $item->id;
                    }
                }
            }

            if (!empty($existingPackingIds)) {
                RecipeItem::whereIn('id', $existingPackingIds)->delete();
            }

            foreach ($materials as $mat) {
                RecipeItem::create([
                    'recipe_id'       => $recipe->id,
                    'raw_material_id' => $mat['raw_material_id'],
                    'quantity'        => $mat['quantity'],
                ]);

                // Update ProductPrice if manual rate is given
                if (!empty($mat['rate'])) {
                    $rm = Product::find($mat['raw_material_id']);
                    if ($rm && !empty($rm->item_code)) {
                        \App\Models\ProductPrice::updateOrCreate(
                            ['item_code' => $rm->item_code],
                            [
                                'price_per_unit' => $mat['rate'],
                                'price_source'   => 'manual',
                                'fetched_at'     => now(),
                            ]
                        );
                    }
                }
            }

            // 2. Sync with CostingBomPackingMaterial if matching Pricelist & CostingBom exist
            $pricelist = \App\Models\Pricelist::where('user_code', $product->item_code)->first();
            if ($pricelist) {
                $normalize = function ($str) {
                    $str = preg_replace('/\[[^\]]*\]|\([^\)]*\)/', '', $str ?? '');
                    return trim(preg_replace('/[^a-zA-Z0-9\+]/', '', strtolower($str)));
                };

                $plNormGrp3 = $normalize($pricelist->group3);
                $plNormName = $normalize($pricelist->item_hd_name);
                $prodNormName = $normalize($product->name);

                // Find all matching Costing BOMs (supports formulation, bulk, and direct links)
                $allBoms = \App\Models\CostingBom::with('finishedProduct')->get();
                $matchedBomIds = [];

                foreach ($allBoms as $bom) {
                    if ($bom->finished_product_id == $product->id) {
                        $matchedBomIds[] = $bom->id;
                        continue;
                    }

                    // Check if already linked
                    $alreadyLinked = \App\Models\CostingBomPackingMaterial::where('costing_bom_id', $bom->id)
                        ->where('pricelist_id', $pricelist->id)
                        ->exists();
                    if ($alreadyLinked) {
                        $matchedBomIds[] = $bom->id;
                        continue;
                    }

                    $fpName = $bom->finishedProduct->name ?? '';
                    $fpNorm = $normalize($fpName);

                    if (!empty($fpNorm)) {
                        if (!empty($plNormGrp3) && $plNormGrp3 !== 'nil' && (str_contains($fpNorm, $plNormGrp3) || str_contains($plNormGrp3, $fpNorm))) {
                            $matchedBomIds[] = $bom->id;
                        } elseif (!empty($plNormName) && (str_contains($fpNorm, $plNormName) || str_contains($plNormName, $fpNorm))) {
                            $matchedBomIds[] = $bom->id;
                        } elseif (!empty($prodNormName) && (str_contains($fpNorm, $prodNormName) || str_contains($prodNormName, $fpNorm))) {
                            $matchedBomIds[] = $bom->id;
                        }
                    }
                }

                $matchedBomIds = array_unique($matchedBomIds);

                foreach ($matchedBomIds as $bomId) {
                    // Remove existing packing materials for this costing_bom and pricelist
                    \App\Models\CostingBomPackingMaterial::where('costing_bom_id', $bomId)
                        ->where('pricelist_id', $pricelist->id)
                        ->delete();

                    // Insert new ones
                    foreach ($materials as $mat) {
                        \App\Models\CostingBomPackingMaterial::create([
                            'costing_bom_id'  => $bomId,
                            'pricelist_id'    => $pricelist->id,
                            'raw_material_id' => $mat['raw_material_id'],
                            'quantity'        => $mat['quantity'],
                            'is_container'    => !empty($mat['is_container']),
                        ]);
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Packing materials configuration saved successfully!',
        ]);
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
