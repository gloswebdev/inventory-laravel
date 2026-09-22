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
use App\Services\BomResolverService;
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
        
        $fgQuery = Product::orderBy('name');
        $this->applyTypeFilters($fgQuery);
        $finishedGoods = $fgQuery->get();
        
        $branches = Branch::orderBy('name')->get();
        $productTypes = \App\Models\ProductType::orderBy('type_name')->get();
        return view('production.index', compact('history', 'finishedGoods', 'branches', 'productTypes'));
    }

    public function show(Production $production)
    {
        $production->load(['items.product', 'user']);

        // Find all stock ledger deductions for this production to show the exact Issue voucher
        $deductions = StockLedger::where('transaction_id', $production->id)
            ->where('transaction_type', 'production_deduct')
            ->with('product.type')
            ->get();

        $issueItems = $deductions->map(function($ledger) {
            $p = $ledger->product;
            $typeName = $p->type->type_name ?? '';
            $isPacking = str_contains(strtoupper($typeName), 'PACKING') ||
                in_array(strtoupper($p->rm_type ?? ''), ['DRUM', 'BAG', 'BOTTLE', 'CAP', 'CARTON', 'LABEL', 'TAPE', 'BOX']);

            return [
                'id' => $p->id ?? null,
                'name' => $p->name ?? 'Unknown',
                'item_code' => $p->item_code ?? '',
                'uom' => $p->uom ?? '',
                'quantity' => abs($ledger->change_quantity),
                'type' => $isPacking ? 'packing' : 'formulation',
            ];
        });

        // Extract ERP Doc numbers if available
        $receiptDoc = null;
        $issueDoc = null;
        if (!empty($production->erp_receipt_response)) {
            $r = json_decode($production->erp_receipt_response, true);
            $receiptDoc = $r['LastSavedDocNo'] ?? null;
        }
        if (!empty($production->erp_issue_response)) {
            $i = json_decode($production->erp_issue_response, true);
            $issueDoc = $i['LastSavedDocNo'] ?? null;
        }

        return response()->json([
            'success' => true,
            'production' => $production,
            'issue_items' => $issueItems,
            'receipt_doc_no' => $receiptDoc,
            'issue_doc_no' => $issueDoc,
        ]);
    }

    public function checkStock(Request $request)
    {
        $branchCode = $request->get('branch_code', '2'); // Branch 2 (Factory)
        $includePackaging = filter_var($request->get('include_packaging', true), FILTER_VALIDATE_BOOLEAN);
        $includeFormulation = filter_var($request->get('include_formulation', false), FILTER_VALIDATE_BOOLEAN);
        $resolver = app(BomResolverService::class);
        $externalStock = $this->getExternalStock();

        // Support both multi-item array and single product_id/quantity
        $rawItems = $request->get('items');
        if (!is_array($rawItems)) {
            $productId = $request->get('product_id');
            $quantity = (float)$request->get('quantity');
            if ($productId && $quantity > 0) {
                $rawItems = [['product_id' => $productId, 'quantity' => $quantity]];
            } else {
                $rawItems = [];
            }
        }

        if (empty($rawItems)) {
            return response()->json([
                'success' => true,
                'possible' => true,
                'requirements' => [],
                'receipt_items' => [],
            ]);
        }

        $consolidatedMaterials = [];
        $receiptItems = [];
        $possible = true;

        foreach ($rawItems as $item) {
            $pId = $item['product_id'] ?? null;
            $qty = (float)($item['quantity'] ?? 0);
            if (!$pId || $qty <= 0) continue;

            $product = Product::find($pId);
            if (!$product) continue;

            $unitPerBox = (float)($product->unit_box ?: 1);
            $totalUnits = $qty * $unitPerBox;

            $receiptItems[] = [
                'product_id'   => $product->id,
                'name'         => $product->name,
                'item_code'    => $product->item_code ?? '',
                'pack_name'    => $product->pack_name ?? 'N/A',
                'quantity_box' => $qty,
                'total_units'  => $totalUnits,
                'unit_per_box' => $unitPerBox,
            ];

            $bom = $resolver->resolve($product, $qty, $includeFormulation, $includePackaging);

            foreach ($bom['all_materials'] as $mat) {
                $key = $mat['id'] ?: ($mat['item_code'] ?: $mat['name']);
                if (isset($consolidatedMaterials[$key])) {
                    $consolidatedMaterials[$key]['required_quantity'] += $mat['required_quantity'];
                } else {
                    $consolidatedMaterials[$key] = $mat;
                }
            }
        }

        $requirements = [];
        $packingCount = 0;
        $formulationCount = 0;

        foreach ($consolidatedMaterials as $item) {
            $liveStock = 0;
            if (!empty($item['item_code']) && isset($externalStock[$branchCode][$item['item_code']])) {
                $liveStock = (float)$externalStock[$branchCode][$item['item_code']];
            } else {
                $liveStock = (float)($item['current_stock'] ?? 0);
            }

            $shortfall = $liveStock - $item['required_quantity'];
            if ($shortfall < -0.0001) $possible = false;

            $type = $item['type'] ?? 'packing';
            if ($type === 'formulation') {
                $formulationCount++;
            } else {
                $packingCount++;
            }

            $requirements[] = [
                'id'           => $item['id'] ?? null,
                'name'         => $item['name'],
                'item_code'    => $item['item_code'] ?? '',
                'uom'          => $item['uom'] ?? 'PCS',
                'type'         => $type, // 'packing' or 'formulation'
                'required_qty' => round($item['required_quantity'], 3),
                'live_stock'   => round($liveStock, 3),
                'shortfall'    => round(max(0, $item['required_quantity'] - $liveStock), 3),
                'is_available' => $shortfall >= -0.0001,
            ];
        }

        return response()->json([
            'success'             => true,
            'possible'            => $possible,
            'include_packaging'   => $includePackaging,
            'include_formulation' => $includeFormulation,
            'branch_code'         => $branchCode,
            'packing_count'       => $packingCount,
            'formulation_count'   => $formulationCount,
            'receipt_items'       => $receiptItems,
            'requirements'        => $requirements,
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
        $includePackagingBatch = filter_var($request->input('include_packaging', true), FILTER_VALIDATE_BOOLEAN);
        $includeFormulationBatch = filter_var($request->input('include_formulation', false), FILTER_VALIDATE_BOOLEAN);
        $resolver = app(BomResolverService::class);

        $production = DB::transaction(function () use ($request, $branch, $resolver, $includePackagingBatch, $includeFormulationBatch) {
            $production = Production::create([
                'production_date' => $request->production_date,
                'branch_code' => $request->branch_code,
                'branch_name' => $branch ? $branch->name : $request->branch_code,
                'user_id' => auth()->id(),
            ]);

            foreach ($request->items as $itemData) {
                $product = Product::find($itemData['product_id']);
                $quantityBoxes = (float)$itemData['quantity'];
                $includePackaging = isset($itemData['include_packaging'])
                    ? filter_var($itemData['include_packaging'], FILTER_VALIDATE_BOOLEAN)
                    : $includePackagingBatch;
                $includeFormulation = isset($itemData['include_formulation'])
                    ? filter_var($itemData['include_formulation'], FILTER_VALIDATE_BOOLEAN)
                    : $includeFormulationBatch;

                $unitPerBox = (float)($product->unit_box ?: 1);
                $totalUnits = $quantityBoxes * $unitPerBox;

                ProductionItem::create([
                    'production_id' => $production->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'pack_size' => $product->pack_name,
                    'quantity_box' => $quantityBoxes,
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

                // Deduct BOM materials (Packaging if toggled ON; Formulation if toggled ON)
                $bom = $resolver->resolve($product, $quantityBoxes, $includeFormulation, $includePackaging);
                foreach ($bom['all_materials'] as $mat) {
                    if (!empty($mat['id'])) {
                        $rawMaterial = Product::find($mat['id']);
                        if ($rawMaterial) {
                            $rawMaterial->decrement('current_stock', $mat['required_quantity']);

                            StockLedger::create([
                                'product_id' => $rawMaterial->id,
                                'transaction_type' => 'production_deduct',
                                'transaction_id' => $production->id,
                                'change_quantity' => -$mat['required_quantity'],
                                'new_stock' => $rawMaterial->current_stock,
                            ]);
                        }
                    }
                }
            }
            return $production;
        });

        // ── ERP PUSH (non-blocking) ────────────────────────────────────────
        // DB transaction already committed above. ERP failure will NOT rollback
        // local stock — production is saved regardless.
        if (AppSetting::get('erp_push_enabled', '0') === '1') {
            $result = $this->pushProductionToErp($production);

            $receiptDoc = $result['receipt_doc'];
            $issueDoc   = $result['issue_doc'];

            $erpMsg = $result['success']
                ? " ERP Synced ✓ [Receipt Doc: {$receiptDoc} | Issue Doc: {$issueDoc}]"
                : ' ERP Push Failed: Issue=' . ($result['issue_result']['message'] ?? 'Error') . ' | Receipt=' . ($result['receipt_result']['message'] ?? 'Error');

            return redirect()->route('production.index')
                ->with('success', 'Production entry saved successfully in Branch 2.' . $erpMsg);
        }

        // ERP push disabled
        $production->update(['erp_push_status' => 'skipped']);
        return redirect()->route('production.index')->with('success', 'Production entry saved successfully.');
    }

    /**
     * Push a production batch (Finished Goods Receipt + Materials Issue) to ERP
     */
    public function pushProductionToErp(Production $production): array
    {
        $production->load('items.product');

        // 1. Build Receipt items (Finished Goods)
        $receiptItems = [];
        foreach ($production->items as $prodItem) {
            $product = $prodItem->product;
            if (!$product) continue;

            $unitPerBox = (float)($product->unit_box ?: 1);
            $totalUnits = (float)$prodItem->quantity_box * $unitPerBox;

            $receiptItems[] = [
                'item_code' => $product->item_code,
                'quantity'  => $totalUnits,
                'lot_no'    => $prodItem->batch_number,
                'mfg_date'  => $prodItem->mfg_date,
                'exp_date'  => $prodItem->exp_date,
                'rate'      => (float)($product->price ?? 0),
            ];
        }

        // 2. Build Issue items (Materials Consumed) from exact local StockLedger records
        $deductions = StockLedger::where('transaction_id', $production->id)
            ->where('transaction_type', 'production_deduct')
            ->with('product')
            ->get();

        $issueItems = [];
        if ($deductions->isNotEmpty()) {
            foreach ($deductions as $ledger) {
                $p = $ledger->product;
                if (!$p || empty($p->item_code)) continue;

                $code = $p->item_code;
                $qty = abs((float)$ledger->change_quantity);

                if (isset($issueItems[$code])) {
                    $issueItems[$code]['quantity'] += $qty;
                } else {
                    $issueItems[$code] = [
                        'item_code' => $code,
                        'quantity'  => $qty,
                    ];
                }
            }
        } else {
            // Fallback: resolve from BOM ONLY if this production has no local ledger records at all (legacy data).
            // If production_add exists in StockLedger and deductions is empty, material issue was intentionally skipped.
            $hasLedgerEntries = StockLedger::where('transaction_id', $production->id)
                ->where('transaction_type', 'production_add')
                ->exists();

            if (!$hasLedgerEntries) {
                $resolver = app(BomResolverService::class);
                foreach ($production->items as $prodItem) {
                    $product = $prodItem->product;
                    if (!$product) continue;
                    $bom = $resolver->resolve($product, (float)$prodItem->quantity_box, false, true);
                    foreach ($bom['all_materials'] as $mat) {
                        if (!empty($mat['item_code'])) {
                            $code = $mat['item_code'];
                            if (isset($issueItems[$code])) {
                                $issueItems[$code]['quantity'] += $mat['required_quantity'];
                            } else {
                                $issueItems[$code] = [
                                    'item_code' => $code,
                                    'quantity'  => $mat['required_quantity'],
                                ];
                            }
                        }
                    }
                }
            }
        }

        $erp = new ErpStockPushService();
        $issueResult   = ['success' => true, 'message' => 'No raw materials', 'response' => []];
        $receiptResult = ['success' => true, 'message' => 'No FG items',      'response' => []];

        if (!empty($receiptItems)) {
            $receiptResult = $erp->pushReceiptStock($production, $receiptItems);
        }
        if (!empty($issueItems)) {
            $issueResult = $erp->pushIssueStock($production, array_values($issueItems));
        }

        $erpSuccess = $issueResult['success'] && $receiptResult['success'];
        $production->update([
            'erp_push_status'      => $erpSuccess ? 'success' : 'failed',
            'erp_issue_response'   => json_encode($issueResult['response']),
            'erp_receipt_response' => json_encode($receiptResult['response']),
        ]);

        return [
            'success'        => $erpSuccess,
            'receipt_result' => $receiptResult,
            'issue_result'   => $issueResult,
            'receipt_doc'    => $receiptResult['response']['LastSavedDocNo'] ?? null,
            'issue_doc'      => $issueResult['response']['LastSavedDocNo'] ?? null,
        ];
    }

    /**
     * Retry pushing a single failed production batch to ERP
     */
    public function retryErpPush(Request $request, Production $production)
    {
        if (AppSetting::get('erp_push_enabled', '0') !== '1') {
            $msg = 'ERP Push is currently disabled in Settings.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg]);
            }
            return back()->with('error', $msg);
        }

        $result = $this->pushProductionToErp($production);

        $msg = $result['success']
            ? "Production #BATCH-{$production->id} synced to ERP successfully! [Receipt Doc: {$result['receipt_doc']} | Issue Doc: {$result['issue_doc']}]"
            : "Retry failed for #BATCH-{$production->id}: Issue=" . ($result['issue_result']['message'] ?? 'Error') . " | Receipt=" . ($result['receipt_result']['message'] ?? 'Error');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'     => $result['success'],
                'message'     => $msg,
                'status'      => $production->fresh()->erp_push_status,
                'receipt_doc' => $result['receipt_doc'],
                'issue_doc'   => $result['issue_doc'],
            ]);
        }

        return back()->with($result['success'] ? 'success' : 'error', $msg);
    }

    /**
     * Bulk retry all failed and pending production batches to ERP
     */
    public function bulkRetryErpPush(Request $request)
    {
        if (AppSetting::get('erp_push_enabled', '0') !== '1') {
            $msg = 'ERP Push is currently disabled in Settings.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg]);
            }
            return back()->with('error', $msg);
        }

        $unsyncedProductions = Production::whereIn('erp_push_status', ['failed', 'pending', 'skipped'])
            ->orWhereNull('erp_push_status')
            ->orderBy('id', 'asc')
            ->get();

        if ($unsyncedProductions->isEmpty()) {
            $msg = 'No pending or failed production batches found to sync.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $msg, 'synced_count' => 0]);
            }
            return back()->with('info', $msg);
        }

        $syncedCount = 0;
        $failCount   = 0;

        foreach ($unsyncedProductions as $prod) {
            $res = $this->pushProductionToErp($prod);
            if ($res['success']) {
                $syncedCount++;
            } else {
                $failCount++;
            }
        }

        $msg = "Bulk Sync Complete: {$syncedCount} synced successfully" . ($failCount > 0 ? ", {$failCount} failed." : ".");
        $isAllSuccess = $failCount === 0;

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'      => $isAllSuccess,
                'message'      => $msg,
                'synced_count' => $syncedCount,
                'fail_count'   => $failCount,
            ]);
        }

        return back()->with($isAllSuccess ? 'success' : 'warning', $msg);
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
        $resolver = app(BomResolverService::class);

        DB::transaction(function () use ($request, $production, $branch, $resolver) {
            // Revert previous deductions via StockLedger (exact record of what was deducted)
            $deductLedgers = StockLedger::where('transaction_id', $production->id)
                ->where('transaction_type', 'production_deduct')
                ->get();
            $addLedgers = StockLedger::where('transaction_id', $production->id)
                ->where('transaction_type', 'production_add')
                ->get();

            if ($deductLedgers->isNotEmpty() || $addLedgers->isNotEmpty()) {
                foreach ($addLedgers as $ledger) {
                    $p = Product::find($ledger->product_id);
                    if ($p) {
                        $p->decrement('current_stock', $ledger->change_quantity);
                        StockLedger::create([
                            'product_id' => $p->id,
                            'transaction_type' => 'production_reversal_deduct',
                            'transaction_id' => $production->id,
                            'change_quantity' => -$ledger->change_quantity,
                            'new_stock' => $p->current_stock,
                        ]);
                    }
                }

                foreach ($deductLedgers as $ledger) {
                    $rm = Product::find($ledger->product_id);
                    if ($rm) {
                        $revertQty = abs($ledger->change_quantity);
                        $rm->increment('current_stock', $revertQty);
                        StockLedger::create([
                            'product_id' => $rm->id,
                            'transaction_type' => 'production_reversal_add',
                            'transaction_id' => $production->id,
                            'change_quantity' => $revertQty,
                            'new_stock' => $rm->current_stock,
                        ]);
                    }
                }
            } else {
                // Fallback for legacy records
                foreach ($production->items as $oldItem) {
                    $oldProduct = Product::find($oldItem->product_id);
                    if (!$oldProduct) continue;
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
                            if ($rawMaterial) {
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
                $quantityBoxes = (float)$itemData['quantity'];
                $includeFormulation = !empty($itemData['include_formulation']) && filter_var($itemData['include_formulation'], FILTER_VALIDATE_BOOLEAN);

                $unitPerBox = (float)($product->unit_box ?: 1);
                $totalUnits = $quantityBoxes * $unitPerBox;

                ProductionItem::create([
                    'production_id' => $production->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'pack_size' => $product->pack_name,
                    'quantity_box' => $quantityBoxes,
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

                $bom = $resolver->resolve($product, $quantityBoxes, $includeFormulation);
                foreach ($bom['all_materials'] as $mat) {
                    if (!empty($mat['id'])) {
                        $rawMaterial = Product::find($mat['id']);
                        if ($rawMaterial) {
                            $rawMaterial->decrement('current_stock', $mat['required_quantity']);

                            StockLedger::create([
                                'product_id' => $rawMaterial->id,
                                'transaction_type' => 'production_deduct',
                                'transaction_id' => $production->id,
                                'change_quantity' => -$mat['required_quantity'],
                                'new_stock' => $rawMaterial->current_stock,
                            ]);
                        }
                    }
                }
            }
        });

        return redirect()->route('production.index')->with('success', 'Production entry updated successfully.');
    }

    public function destroy(Production $production)
    {
        DB::transaction(function () use ($production) {
            $deductLedgers = StockLedger::where('transaction_id', $production->id)
                ->where('transaction_type', 'production_deduct')
                ->get();
            $addLedgers = StockLedger::where('transaction_id', $production->id)
                ->where('transaction_type', 'production_add')
                ->get();

            if ($deductLedgers->isNotEmpty() || $addLedgers->isNotEmpty()) {
                foreach ($addLedgers as $ledger) {
                    $p = Product::find($ledger->product_id);
                    if ($p) {
                        $p->decrement('current_stock', $ledger->change_quantity);
                        StockLedger::create([
                            'product_id' => $p->id,
                            'transaction_type' => 'production_delete_deduct',
                            'transaction_id' => $production->id,
                            'change_quantity' => -$ledger->change_quantity,
                            'new_stock' => $p->current_stock,
                        ]);
                    }
                }

                foreach ($deductLedgers as $ledger) {
                    $rm = Product::find($ledger->product_id);
                    if ($rm) {
                        $revertQty = abs($ledger->change_quantity);
                        $rm->increment('current_stock', $revertQty);
                        StockLedger::create([
                            'product_id' => $rm->id,
                            'transaction_type' => 'production_delete_add',
                            'transaction_id' => $production->id,
                            'change_quantity' => $revertQty,
                            'new_stock' => $rm->current_stock,
                        ]);
                    }
                }
            } else {
                foreach ($production->items as $item) {
                    $product = Product::find($item->product_id);
                    if (!$product) continue;
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
                            if ($rawMaterial) {
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
