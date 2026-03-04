<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionItem;
use App\Models\Branch;
use App\Models\Recipe;
use App\Models\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProductionController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Production::with(['items.product', 'user'])->orderByDesc('production_date')->orderByDesc('created_at');

        if ($user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $permittedRMTypes = $user->getPermittedRMTypes();
            
            $query->whereHas('items.product', function($q) use ($permittedTypeIds, $permittedRMTypes) {
                $q->whereIn('product_type_id', $permittedTypeIds)
                  ->where(function($sq) use ($permittedRMTypes) {
                      $sq->whereIn('rm_type', $permittedRMTypes)
                        ->orWhereNull('rm_type')
                        ->orWhere('rm_type', '');
                  });
            });
        }

        $history = $query->limit(50)->get();
        
        $fgQuery = Product::whereHas('recipes')->orderBy('name');
        $this->applyTypeFilters($fgQuery);
        $finishedGoods = $fgQuery->get();
        
        $branches = Branch::orderBy('name')->get();
        $productTypes = \App\Models\ProductType::orderBy('type_name')->get();
        return view('production.index', compact('history', 'finishedGoods', 'branches', 'productTypes'));
    }

    public function show(Production $production)
    {
        return response()->json([
            'success' => true,
            'production' => $production->load('items')
        ]);
    }

    public function checkStock(Request $request)
    {
        $productId = $request->get('product_id');
        $quantity = $request->get('quantity');

        $recipe = Recipe::where('finished_product_id', $productId)->with('items.rawMaterial')->firstOrFail();
        
        $requirements = [];
        $possible = true;

        foreach ($recipe->items as $item) {
            $requiredQty = ($item->quantity / $recipe->yield_quantity) * $quantity;
            $currentStock = $item->rawMaterial->current_stock;
            $shortfall = $currentStock - $requiredQty;

            if ($shortfall < 0) $possible = false;

            $requirements[] = [
                'name' => $item->rawMaterial->name,
                'required_qty' => $requiredQty,
                'current_stock' => $currentStock,
                'shortfall' => $shortfall,
            ];
        }

        return response()->json([
            'possible' => $possible,
            'requirements' => $requirements,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'production_date' => 'required|date',
            'branch_code' => 'required',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
        ]);

        $branch = Branch::where('code', $request->branch_code)->first();

        DB::transaction(function () use ($request, $branch) {
            $production = Production::create([
                'production_date' => $request->production_date,
                'branch_code' => $request->branch_code,
                'branch_name' => $branch ? $branch->name : $request->branch_code,
                'user_id' => auth()->id(),
            ]);

            foreach ($request->items as $itemData) {
                $product = Product::find($itemData['product_id']);
                $recipe = Recipe::where('finished_product_id', $product->id)->with('items')->first();

                ProductionItem::create([
                    'production_id' => $production->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'pack_size' => $product->pack_name,
                    'quantity_box' => $itemData['quantity'],
                    'batch_number' => $itemData['batch_number'] ?? null,
                    'mfg_date' => $itemData['mfg_date'] ?? null,
                    'exp_date' => $itemData['exp_date'] ?? null,
                ]);

                $product->increment('current_stock', $itemData['quantity']);
                StockLedger::create([
                    'product_id' => $product->id,
                    'transaction_type' => 'production_add',
                    'transaction_id' => $production->id,
                    'change_quantity' => $itemData['quantity'],
                    'new_stock' => $product->current_stock,
                ]);

                if ($recipe) {
                    foreach ($recipe->items as $recipeItem) {
                        $deductQty = ($recipeItem->quantity / $recipe->yield_quantity) * $itemData['quantity'];
                        $rawMaterial = Product::find($recipeItem->raw_material_id);
                        $rawMaterial->decrement('current_stock', $deductQty);

                        StockLedger::create([
                            'product_id' => $rawMaterial->id,
                            'transaction_type' => 'production_deduct',
                            'transaction_id' => $production->id,
                            'change_quantity' => -$deductQty,
                            'new_stock' => $rawMaterial->current_stock,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('production.index')->with('success', 'Production entry saved successfully.');
    }

    public function update(Request $request, Production $production)
    {
        $request->validate([
            'production_date' => 'required|date',
            'branch_code' => 'required',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
        ]);

        $branch = Branch::where('code', $request->branch_code)->first();

        DB::transaction(function () use ($request, $production, $branch) {
            foreach ($production->items as $oldItem) {
                $oldProduct = Product::find($oldItem->product_id);
                $oldRecipe = Recipe::where('finished_product_id', $oldProduct->id)->with('items')->first();

                $oldProduct->decrement('current_stock', $oldItem->quantity_box);
                StockLedger::create([
                    'product_id' => $oldProduct->id,
                    'transaction_type' => 'production_reversal_deduct',
                    'transaction_id' => $production->id,
                    'change_quantity' => -$oldItem->quantity_box,
                    'new_stock' => $oldProduct->current_stock,
                ]);

                if ($oldRecipe) {
                    foreach ($oldRecipe->items as $recipeItem) {
                        $reverseQty = ($recipeItem->quantity / $oldRecipe->yield_quantity) * $oldItem->quantity_box;
                        $rawMaterial = Product::find($recipeItem->raw_material_id);
                        $rawMaterial->increment('current_stock', $reverseQty);

                        StockLedger::create([
                            'product_id' => $rawMaterial->id,
                            'transaction_type' => 'production_reversal_add',
                            'transaction_id' => $production->id,
                            'change_quantity' => $reverseQty,
                            'new_stock' => $rawMaterial->current_stock,
                        ]);
                    }
                }
            }

            $production->items()->delete();

            $production->update([
                'production_date' => $request->production_date,
                'branch_code' => $request->branch_code,
                'branch_name' => $branch ? $branch->name : $request->branch_code,
            ]);

            foreach ($request->items as $itemData) {
                $product = Product::find($itemData['product_id']);
                $recipe = Recipe::where('finished_product_id', $product->id)->with('items')->first();

                ProductionItem::create([
                    'production_id' => $production->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'pack_size' => $product->pack_name,
                    'quantity_box' => $itemData['quantity'],
                    'batch_number' => $itemData['batch_number'] ?? null,
                    'mfg_date' => $itemData['mfg_date'] ?? null,
                    'exp_date' => $itemData['exp_date'] ?? null,
                ]);

                $product->increment('current_stock', $itemData['quantity']);
                StockLedger::create([
                    'product_id' => $product->id,
                    'transaction_type' => 'production_add',
                    'transaction_id' => $production->id,
                    'change_quantity' => $itemData['quantity'],
                    'new_stock' => $product->current_stock,
                ]);

                if ($recipe) {
                    foreach ($recipe->items as $recipeItem) {
                        $deductQty = ($recipeItem->quantity / $recipe->yield_quantity) * $itemData['quantity'];
                        $rawMaterial = Product::find($recipeItem->raw_material_id);
                        $rawMaterial->decrement('current_stock', $deductQty);

                        StockLedger::create([
                            'product_id' => $rawMaterial->id,
                            'transaction_type' => 'production_deduct',
                            'transaction_id' => $production->id,
                            'change_quantity' => -$deductQty,
                            'new_stock' => $rawMaterial->current_stock,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('production.index')->with('success', 'Production entry updated successfully.');
    }

    public function destroy(Production $production)
    {
        DB::transaction(function () use ($production) {
            foreach ($production->items as $item) {
                $product = Product::find($item->product_id);
                $recipe = Recipe::where('finished_product_id', $product->id)->with('items')->first();

                $product->decrement('current_stock', $item->quantity_box);
                StockLedger::create([
                    'product_id' => $product->id,
                    'transaction_type' => 'production_delete_deduct',
                    'transaction_id' => $production->id,
                    'change_quantity' => -$item->quantity_box,
                    'new_stock' => $product->current_stock,
                ]);

                if ($recipe) {
                    foreach ($recipe->items as $recipeItem) {
                        $reverseQty = ($recipeItem->quantity / $recipe->yield_quantity) * $item->quantity_box;
                        $rawMaterial = Product::find($recipeItem->raw_material_id);
                        $rawMaterial->increment('current_stock', $reverseQty);

                        StockLedger::create([
                            'product_id' => $rawMaterial->id,
                            'transaction_type' => 'production_delete_add',
                            'transaction_id' => $production->id,
                            'change_quantity' => $reverseQty,
                            'new_stock' => $rawMaterial->current_stock,
                        ]);
                    }
                }
            }
            $production->delete();
        });

        return redirect()->route('production.index')->with('success', 'Production entry deleted and stock reverted.');
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
