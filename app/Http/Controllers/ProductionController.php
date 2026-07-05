<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionItem;
use App\Models\Branch;
use App\Models\Recipe;
use App\Models\StockLedger;
use App\Models\RecipeItem;
use App\Library\ErpStockPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
        $branchCode = $request->get('branch_code', '2'); // Default to Factory (2)

        $product = Product::findOrFail($productId);
        $recipe = Recipe::where('finished_product_id', $productId)->with('items.rawMaterial')->first();
        
        if (!$recipe) {
            return response()->json([
                'success' => false,
                'message' => 'No recipe defined for this product.'
            ]);
        }
        
        $externalStock = $this->getExternalStock();
        $requirements = [];
        $possible = true;

        foreach ($recipe->items as $item) {
            $rm = $item->rawMaterial;
            if (!$rm) continue;

            // Required Qty = (Recipe Qty / Yield) * (Produced Qty * FG Weight Multiplier)
            $requiredQty = ($item->quantity / $recipe->yield_quantity) * ($quantity * $product->weight_multiplier);
            
            // Get live stock for target branch from ERP data
            $liveStock = 0;
            if (isset($externalStock[$branchCode][$rm->item_code])) {
                $liveStock = (float)$externalStock[$branchCode][$rm->item_code];
            } else {
                // If not in ERP, fallback to local stock? (User asked for live stock, so usually ERP)
                $liveStock = (float)$rm->current_stock; 
            }

            $shortfall = $liveStock - $requiredQty;
            if ($shortfall < 0) $possible = false;

            $requirements[] = [
                'name' => $rm->name,
                'item_code' => $rm->item_code,
                'uom' => $rm->uom,
                'required_qty' => round($requiredQty, 3),
                'live_stock' => round($liveStock, 3),
                'shortfall' => round(max(0, $requiredQty - $liveStock), 3),
            ];
        }

        return response()->json([
            'success' => true,
            'possible' => $possible,
            'requirements' => $requirements,
        ]);
    }

    private function getExternalStock()
    {
        return Cache::remember('external_stock_data_grouped', 3600, function () {
            try {
                $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
                $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');
                $branch  = AppSetting::get('inventory_api_branch', 'ALL');
                $item    = AppSetting::get('inventory_api_item', 'ALL');

                $response = Http::withoutVerifying()
                    ->timeout(60)
                    ->connectTimeout(15)
                    ->post("{$baseUrl}/ProductWiseInventory", [
                        "apikey" => $apiKey,
                        "Branch" => $branch,
                        "Item"   => $item,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['response']) && $data['response'] === 'success' && isset($data['resultdata'])) {
                        $stockMap = [];
                        foreach ($data['resultdata'] as $item) {
                            $bCode = (int)$item['Branch_Code'];
                            $iCode = $item['User_Code'];
                            $stockMap[$bCode][$iCode] = (float)$item['ClosingQty'];
                        }
                        return $stockMap;
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('External Stock API Error (Production): ' . $e->getMessage());
            }
            return [];
        });
    }

    public function store(Request $request)
    {
        $request->validate([
            'production_date' => 'required|date',
            'branch_code' => 'required',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.batch_number' => 'required|string|max:255',
            'items.*.mfg_date' => 'required|date',
            'items.*.exp_date' => 'required|date|after_or_equal:items.*.mfg_date',
        ]);

        $branch = Branch::where('code', $request->branch_code)->first();

        $production = DB::transaction(function () use ($request, $branch) {
            $production = Production::create([
                'production_date' => $request->production_date,
                'branch_code' => $request->branch_code,
                'branch_name' => $branch ? $branch->name : $request->branch_code,
                'user_id' => auth()->id(),
            ]);

            foreach ($request->items as $itemData) {
                $product = Product::find($itemData['product_id']);
                $recipe = Recipe::where('finished_product_id', $product->id)->with('items')->first();

                $unitPerBox = (float)($product->unit_box ?: 1);
                $totalUnits = $itemData['quantity'] * $unitPerBox;
                $totalProducedInBaseUnit = $totalUnits * $product->weight_multiplier;

                ProductionItem::create([
                    'production_id' => $production->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'pack_size' => $product->pack_name,
                    'quantity_box' => $itemData['quantity'],
                    'batch_number' => isset($itemData['batch_number']) ? strtoupper($itemData['batch_number']) : null,
                    'mfg_date' => $itemData['mfg_date'] ?? null,
                    'exp_date' => $itemData['exp_date'] ?? null,
                ]);

                $product->increment('current_stock', $totalUnits);
                StockLedger::create([
                    'product_id' => $product->id,
                    'transaction_type' => 'production_add',
                    'transaction_id' => $production->id,
                    'change_quantity' => $totalUnits,
                    'new_stock' => $product->current_stock,
                ]);

                if ($recipe) {
                    foreach ($recipe->items as $recipeItem) {
                        $deductQty = ($recipeItem->quantity / $recipe->yield_quantity) * $totalProducedInBaseUnit;
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
            return $production;
        });

        // ── ERP PUSH (non-blocking) ────────────────────────────────────────
        // DB transaction already committed above. ERP failure will NOT rollback
        // local stock — production is saved regardless.
        if (AppSetting::get('erp_push_enabled', '0') === '1') {

            // We need the production record + items freshly loaded for building payloads
            $production->load('items.product');

            // ── Build Issue payload (raw materials consumed) ──────────────
            $issueItems   = [];
            $receiptItems = [];

            foreach ($production->items as $prodItem) {
                $product = $prodItem->product;
                if (!$product) continue;

                // Receipt: every FG item
                $unitPerBox  = (float) ($product->unit_box ?: 1);
                $totalUnits  = $prodItem->quantity_box * $unitPerBox;
                $receiptItems[] = [
                    'item_code' => $product->item_code,
                    'quantity'  => $totalUnits,
                    'lot_no'    => $prodItem->batch_number,
                    'mfg_date'  => $prodItem->mfg_date,
                    'exp_date'  => $prodItem->exp_date,
                    'rate'      => (float) ($product->price ?? 0),
                ];

                // Issue: raw materials from recipe
                $recipe = Recipe::where('finished_product_id', $product->id)->with('items.rawMaterial')->first();
                if ($recipe) {
                    $totalBase = $totalUnits * $product->weight_multiplier;
                    foreach ($recipe->items as $recipeItem) {
                        $rm  = $recipeItem->rawMaterial;
                        if (!$rm || !$rm->item_code) continue;
                        $qty = ($recipeItem->quantity / $recipe->yield_quantity) * $totalBase;
                        $issueItems[] = [
                            'item_code' => $rm->item_code,
                            'quantity'  => $qty,
                        ];
                    }
                }
            }

            $erp    = new ErpStockPushService();
            $issueResult   = ['success' => true, 'message' => 'No raw materials', 'response' => []];
            $receiptResult = ['success' => true, 'message' => 'No FG items',      'response' => []];

            if (!empty($issueItems)) {
                $issueResult = $erp->pushIssueStock($production, $issueItems);
            }
            if (!empty($receiptItems)) {
                $receiptResult = $erp->pushReceiptStock($production, $receiptItems);
            }

            $erpSuccess = $issueResult['success'] && $receiptResult['success'];
            $production->update([
                'erp_push_status'      => $erpSuccess ? 'success' : 'failed',
                'erp_issue_response'   => json_encode($issueResult['response']),
                'erp_receipt_response' => json_encode($receiptResult['response']),
            ]);

            $erpMsg = $erpSuccess
                ? ' ERP stock updated ✓'
                : ' ERP push failed: Issue=' . $issueResult['message'] . ' | Receipt=' . $receiptResult['message'];

            return redirect()->route('production.index')
                ->with('success', 'Production entry saved successfully.' . $erpMsg);
        }

        // ERP push disabled
        $production->update(['erp_push_status' => 'skipped']);
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
            'items.*.batch_number' => 'required|string|max:255',
            'items.*.mfg_date' => 'required|date',
            'items.*.exp_date' => 'required|date|after_or_equal:items.*.mfg_date',
        ]);

        $branch = Branch::where('code', $request->branch_code)->first();

        DB::transaction(function () use ($request, $production, $branch) {
            foreach ($production->items as $oldItem) {
                $oldProduct = Product::find($oldItem->product_id);
                $oldRecipe = Recipe::where('finished_product_id', $oldProduct->id)->with('items')->first();

                $unitPerBox = (float)($oldProduct->unit_box ?: 1);
                $totalUnits = $oldItem->quantity_box * $unitPerBox;
                $totalProducedInBaseUnit = $totalUnits * $oldProduct->weight_multiplier;

                $oldProduct->decrement('current_stock', $totalUnits);
                StockLedger::create([
                    'product_id' => $oldProduct->id,
                    'transaction_type' => 'production_reversal_deduct',
                    'transaction_id' => $production->id,
                    'change_quantity' => -$totalUnits,
                    'new_stock' => $oldProduct->current_stock,
                ]);

                if ($oldRecipe) {
                    foreach ($oldRecipe->items as $recipeItem) {
                        $reverseQty = ($recipeItem->quantity / $oldRecipe->yield_quantity) * $totalProducedInBaseUnit;
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

                $unitPerBox = (float)($product->unit_box ?: 1);
                $totalUnits = $itemData['quantity'] * $unitPerBox;
                $totalProducedInBaseUnit = $totalUnits * $product->weight_multiplier;

                ProductionItem::create([
                    'production_id' => $production->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'pack_size' => $product->pack_name,
                    'quantity_box' => $itemData['quantity'],
                    'batch_number' => isset($itemData['batch_number']) ? strtoupper($itemData['batch_number']) : null,
                    'mfg_date' => $itemData['mfg_date'] ?? null,
                    'exp_date' => $itemData['exp_date'] ?? null,
                ]);

                $product->increment('current_stock', $totalUnits);
                StockLedger::create([
                    'product_id' => $product->id,
                    'transaction_type' => 'production_add',
                    'transaction_id' => $production->id,
                    'change_quantity' => $totalUnits,
                    'new_stock' => $product->current_stock,
                ]);

                if ($recipe) {
                    foreach ($recipe->items as $recipeItem) {
                        $deductQty = ($recipeItem->quantity / $recipe->yield_quantity) * $totalProducedInBaseUnit;
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

                $unitPerBox = (float)($product->unit_box ?: 1);
                $totalUnits = $item->quantity_box * $unitPerBox;
                $totalProducedInBaseUnit = $totalUnits * $product->weight_multiplier;

                $product->decrement('current_stock', $totalUnits);
                StockLedger::create([
                    'product_id' => $product->id,
                    'transaction_type' => 'production_delete_deduct',
                    'transaction_id' => $production->id,
                    'change_quantity' => -$totalUnits,
                    'new_stock' => $product->current_stock,
                ]);

                if ($recipe) {
                    foreach ($recipe->items as $recipeItem) {
                        $reverseQty = ($recipeItem->quantity / $recipe->yield_quantity) * $totalProducedInBaseUnit;
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
