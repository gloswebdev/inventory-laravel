<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductType;
use App\Models\CostingBom;
use App\Models\CostingBomItem;
use App\Models\PurchaseRegister;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class CostingController extends Controller
{
    /**
     * Desktop: Costing index — product list with cost overview
     */
    public function index(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_pro', 'view')) {
            abort(403, 'Access denied to Costing module.');
        }

        return redirect()->route('costing.pro');
    }

    /**
     * Desktop: Show detailed cost breakdown for one product
     */
    public function show(Product $product)
    {
        if (!Auth::user()->hasPermission('costing_pro', 'view')) {
            abort(403);
        }

        $product->load('costingBoms.items.rawMaterial.type');
        $priceMap = ProductPrice::allAsMap();
        $recipe   = $product->costingBoms->first();

        if (!$recipe) {
            return redirect()->route('costing.index')
                ->with('error', 'No costing BOM found for this product.');
        }

        $breakdown = $this->buildBreakdown($recipe, $priceMap);
        $totalPerUnit = collect($breakdown)->sum('sub_cost');

        return view('costing.show', compact('product', 'recipe', 'breakdown', 'totalPerUnit', 'priceMap'));
    }

    /**
     * AJAX: Calculate cost for multiple products with given quantities, purities, and densities
     */
    public function calculate(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_pro', 'view')) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $request->validate([
            'products'              => 'required|array|min:1',
            'products.*.id'         => 'required|exists:products,id',
            'products.*.quantity'   => 'required|numeric|min:0.001',
            'products.*.density'    => 'nullable|numeric|min:0.1|max:3',
        ]);

        $priceMap   = ProductPrice::allAsMap();
        $results    = [];
        $grandTotal = 0;

        foreach ($request->products as $input) {
            $product = Product::with('costingBoms.items.rawMaterial')->find($input['id']);
            if (!$product) continue;

            $qty         = (float) $input['quantity'];
            $density     = (float) ($input['density'] ?? 1);

            $costData = $this->computeCost($product, $priceMap, $qty, $density);
            $grandTotal += $costData['total_cost'];

            // Find purity of TECHNICAL raw material in BOM
            $recipe = $product->costingBoms->first();
            $rmPurity = 100.0;
            if ($recipe) {
                foreach ($recipe->items as $item) {
                    if ($item->rawMaterial && strtoupper(trim($item->rawMaterial->rm_type)) === 'TECHNICAL') {
                        $rmPurity = (float) \App\Models\ProductPrice::where('item_code', $item->rawMaterial->item_code)->value('purity');
                        if ($rmPurity <= 0 && $item->purity > 0) {
                            $rmPurity = (float) $item->purity;
                        }
                        if ($rmPurity <= 0) $rmPurity = 100.0;
                        break;
                    }
                }
            }

            $formulation = $this->parseFormulation($product->name);

            $results[] = [
                'product_id'    => $product->id,
                'product_name'  => $product->name,
                'pack_name'     => $product->pack_name,
                'quantity'      => $qty,
                'purity'        => $rmPurity,
                'formulation'   => $formulation,
                'density'       => $density,
                'cost_per_unit' => $costData['cost_per_unit'],
                'total_cost'    => $costData['total_cost'],
                'breakdown'     => $costData['breakdown'],
                'packing_costs' => $costData['packing_costs'] ?? [],
                'has_recipe'    => $costData['has_recipe'],
            ];
        }

        return response()->json([
            'success'     => true,
            'results'     => $results,
            'grand_total' => $grandTotal,
        ]);
    }

    /**
     * Sync prices from ERP — LogicPurchaseRegisterDetail API
     * Fetches purchase register for current FY and takes the LATEST CaseRate per item.
     */
    public function fetchPrices(Request $request)
    {
        if (!Auth::user()->hasFeature('costing', 'fetch_prices')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        try {
            $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
            $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');

            // Use settings if set, else auto-calculate current Financial Year (Apr–Mar)
            $now     = now();
            $fyStart = $now->month >= 4
                        ? $now->year . '-04-01'
                        : ($now->year - 1) . '-04-01';
            $fyEnd   = $now->month >= 4
                        ? ($now->year + 1) . '-03-31'
                        : $now->year . '-03-31';

            $fromDate = AppSetting::get('costing_api_from_date') ?: $fyStart;
            $toDate   = AppSetting::get('costing_api_to_date')   ?: $fyEnd;
            $account  = AppSetting::get('costing_api_account', 'all') ?: 'all';
            $item     = AppSetting::get('costing_api_item',    'all') ?: 'all';
            $branch   = AppSetting::get('costing_api_branch',  'all') ?: 'all';

            $response = Http::withoutVerifying()
                ->timeout(60)
                ->connectTimeout(15)
                ->post("{$baseUrl}/LogicPurchaseRegisterDetail", [
                    'apikey'   => $apiKey,
                    'FromDate' => $fromDate,
                    'ToDate'   => $toDate,
                    'Account'  => $account,
                    'Item'     => $item,
                    'Branch'   => $branch,
                ]);

            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'ERP API request failed.'], 500);
            }

            $data = $response->json();

            if (($data['response'] ?? '') !== 'success' || empty($data['resultdata'])) {
                return response()->json(['success' => false, 'message' => 'No data returned from ERP API.'], 422);
            }

            // Build a map: User_Code => latest CaseRate & Purity
            $latestByCode = [];

            foreach ($data['resultdata'] as $row) {
                $itemCode  = trim($row['User_Code'] ?? '');
                $caseRate  = (float)($row['CaseRate'] ?? 0);
                $purity    = isset($row['Purity']) ? (float)$row['Purity'] : null;
                $vouchDate = \Carbon\Carbon::createFromFormat('d/m/Y', $row['Vouch_Date'] ?? '01/01/2000')->timestamp ?? 0;

                if (empty($itemCode) || $caseRate <= 0) continue;

                // Keep the row with the most recent voucher date
                if (!isset($latestByCode[$itemCode]) || $vouchDate > $latestByCode[$itemCode]['date']) {
                    $latestByCode[$itemCode] = [
                        'price'  => $caseRate,
                        'purity' => $purity,
                        'date'   => $vouchDate,
                    ];
                }
            }

            if (empty($latestByCode)) {
                return response()->json(['success' => false, 'message' => 'No valid price records found in ERP data.'], 422);
            }

            $count = 0;
            foreach ($latestByCode as $itemCode => $entry) {
                ProductPrice::updateOrCreate(
                    ['item_code' => $itemCode],
                    [
                        'price_per_unit' => $entry['price'],
                        'purity'         => $entry['purity'],
                        'price_source'   => 'erp',
                        'fetched_at'     => now(),
                    ]
                );
                $count++;
            }

            Cache::forget('costing_price_map');

            return response()->json([
                'success'      => true,
                'message'      => "✅ {$count} item prices synced from ERP ({$fromDate} → {$toDate}).",
                'synced_at'    => now()->format('d M Y, h:i A'),
                'total_rows'   => count($data['resultdata']),
                'unique_items' => $count,
            ]);

        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
            Log::error('Costing price sync - date parse error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Date format error in ERP data.'], 500);
        } catch (\Exception $e) {
            Log::error('Costing price sync error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save/update a single price manually
     */
    public function updatePrice(Request $request)
    {
        if (!Auth::user()->hasFeature('costing', 'fetch_prices')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $request->validate([
            'item_code'      => 'required|string',
            'price_per_unit' => 'required|numeric|min:0',
        ]);

        ProductPrice::updateOrCreate(
            ['item_code' => $request->item_code],
            [
                'price_per_unit' => $request->price_per_unit,
                'price_source'   => 'manual',
                'fetched_at'     => now(),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Price updated.']);
    }

    /**
     * Export cost report as PDF
     */
    public function export(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_pro', 'view')) {
            abort(403);
        }

        $productIds   = $request->input('product_ids', []);
        $quantities   = $request->input('quantities', []);
        $densities    = $request->input('densities', []);
        $priceMap     = ProductPrice::allAsMap();

        $results    = [];
        $grandTotal = 0;

        $query = Product::with('costingBoms.items.rawMaterial')->whereHas('costingBoms');
        if (!empty($productIds)) {
            $query->whereIn('id', $productIds);
        }
        $products = $query->orderBy('name')->get();

        foreach ($products as $product) {
            $qty         = (float)($quantities[$product->id] ?? 1);
            $density     = (float)($densities[$product->id] ?? 1);

            $costData = $this->computeCost($product, $priceMap, $qty, $density);
            $grandTotal += $costData['total_cost'];
            
            // Find purity of TECHNICAL raw material in BOM
            $recipe = $product->costingBoms->first();
            $rmPurity = 100.0;
            if ($recipe) {
                foreach ($recipe->items as $item) {
                    if ($item->rawMaterial && strtoupper(trim($item->rawMaterial->rm_type)) === 'TECHNICAL') {
                        $rmPurity = (float) \App\Models\ProductPrice::where('item_code', $item->rawMaterial->item_code)->value('purity');
                        if ($rmPurity <= 0 && $item->purity > 0) {
                            $rmPurity = (float) $item->purity;
                        }
                        if ($rmPurity <= 0) $rmPurity = 100.0;
                        break;
                    }
                }
            }

            $formulation = $this->parseFormulation($product->name);

            $results[] = array_merge($costData, [
                'product'     => $product,
                'quantity'    => $qty,
                'purity'      => $rmPurity,
                'formulation' => $formulation,
                'density'     => $density,
            ]);
        }

        $pdf = Pdf::loadView('costing.pdf', compact('results', 'grandTotal'))
                  ->setPaper('a4', 'portrait');

        return $pdf->download('Cost_Report_' . now()->format('Y-m-d_His') . '.pdf');
    }

    /**
     * Desktop: Costing Pro
     */
    public function pro(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_pro', 'view')) {
            abort(403, 'Access denied to Costing Dashboard.');
        }

        $boms = CostingBom::with(['finishedProduct.type', 'items.rawMaterial', 'packingMaterials.rawMaterial', 'packingMaterials.pricelist'])->get();

        // Sort BOMs A to Z by finished product name
        $boms = $boms->sortBy(function ($bom) {
            return strtolower($bom->finishedProduct->name ?? '');
        })->values();

        $priceMap = ProductPrice::allAsMap();

        $latestPurityMap = [];
        $apiSuccess = false;
        try {
            $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
            $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');

            $now     = now();
            $fyStart = $now->month >= 4 ? $now->year . '-04-01' : ($now->year - 1) . '-04-01';
            $fyEnd   = $now->month >= 4 ? ($now->year + 1) . '-03-31' : $now->year . '-03-31';

            $fromDate = AppSetting::get('costing_api_from_date') ?: $fyStart;
            $toDate   = AppSetting::get('costing_api_to_date')   ?: $fyEnd;

            $response = Http::withoutVerifying()
                ->timeout(10)
                ->post("{$baseUrl}/LogicPurchaseRegisterDetail", [
                    'apikey'   => $apiKey,
                    'FromDate' => $fromDate,
                    'ToDate'   => $toDate,
                    'Account'  => 'all',
                    'Item'     => 'all',
                    'Branch'   => 'all',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['response'] ?? '') === 'success' && !empty($data['resultdata'])) {
                    $latestByCode = [];
                    foreach ($data['resultdata'] as $row) {
                        $itemCode  = trim($row['User_Code'] ?? '');
                        $purity    = isset($row['Purity']) ? (float)$row['Purity'] : null;
                        $vouchDateStr = $row['Vouch_Date'] ?? '01/01/2000';
                        $dateObj = \Carbon\Carbon::createFromFormat('d/m/Y', $vouchDateStr);
                        $timestamp = $dateObj ? $dateObj->timestamp : 0;

                        if (empty($itemCode) || $purity === null) continue;

                        if (!isset($latestByCode[$itemCode]) || $timestamp > $latestByCode[$itemCode]['timestamp']) {
                            $latestByCode[$itemCode] = [
                                'purity'     => $purity,
                                'timestamp'  => $timestamp,
                                'vouch_date' => $vouchDateStr,
                            ];
                        }
                    }
                    $latestPurityMap = $latestByCode;
                    $apiSuccess = true;
                }
            }
        } catch (\Exception $e) {
            Log::error('Costing Dashboard ERP API error: ' . $e->getMessage());
        }

        $localPurities = ProductPrice::allPuritiesAsMap();

        // Process BOMs with detailed batch grand total, density rates, and packing materials PM costs
        $processedBoms = $boms->map(function ($bom) use ($latestPurityMap, $localPurities, $priceMap) {
            $product = $bom->finishedProduct;
            $purity = '—';
            $vouchDate = '—';
            $source = 'No Data';
            $rmName = '—';

            // Find the TECHNICAL raw material in this BOM
            $techRm = null;
            foreach ($bom->items as $item) {
                if ($item->rawMaterial && strtoupper(trim($item->rawMaterial->rm_type)) === 'TECHNICAL') {
                    $techRm = $item->rawMaterial;
                    break;
                }
            }
            if (!$techRm && $bom->items->isNotEmpty()) {
                $techRm = $bom->items->first()->rawMaterial;
            }

            if ($techRm && $techRm->item_code) {
                $code = trim($techRm->item_code);
                $rmName = $techRm->name;
                if (isset($latestPurityMap[$code])) {
                    $purity = $latestPurityMap[$code]['purity'] . '%';
                    $vouchDate = $latestPurityMap[$code]['vouch_date'];
                    $source = 'Purchase API';
                } elseif (isset($localPurities[$code]) && $localPurities[$code] > 0) {
                    $purity = $localPurities[$code] . '%';
                    $source = 'Local Cache';
                }
            }

            // Calculate Batch Grand Total RM Cost
            $yieldQty = max((float)$bom->yield_quantity, 0.001);
            $density  = (float)($bom->density > 0 ? $bom->density : 1.0);
            $formulation = ($bom->formulation !== null) ? (float)$bom->formulation : $this->parseFormulation($product->name ?? '');

            $grandTotal = 0;
            foreach ($bom->items as $item) {
                $rm = $item->rawMaterial;
                if (!$rm) continue;

                $pricePerUnit = (float)($priceMap[$rm->item_code] ?? 0);
                $tc = (float)($item->transportation_cost ?? 5.0);
                $requiredQty = (float)$item->quantity;

                if (strtoupper(trim($rm->rm_type ?? '')) === 'TECHNICAL') {
                    $rmPurity = (float) ProductPrice::where('item_code', $rm->item_code)->value('purity');
                    if ($rmPurity <= 0 && $item->purity > 0) {
                        $rmPurity = (float) $item->purity;
                    }
                    if ($rmPurity <= 0) $rmPurity = 100.0;

                    $requiredQty = ($yieldQty * $formulation) / $rmPurity;
                }

                $subCost = $requiredQty * ($pricePerUnit + $tc);
                $grandTotal += $subCost;
            }

            $woDensityRate   = $grandTotal / $yieldQty;
            $withDensityRate = $woDensityRate * $density;

            // Packing materials cost computation
            $packingCosts = [];
            $grouped = $bom->packingMaterials->groupBy('pricelist_id');
            foreach ($grouped as $pricelistId => $pmItems) {
                $pricelist = $pmItems->first()->pricelist;
                if (!$pricelist) continue;

                $cf1 = (float)($pricelist->cf_1 ?? 1);
                if ($cf1 <= 0) $cf1 = 1;

                $singlePackPmCost = 0;
                foreach ($pmItems as $pmItem) {
                    $rm = $pmItem->rawMaterial;
                    if (!$rm) continue;

                    $pmPrice = (float)($priceMap[$rm->item_code] ?? 0);
                    if ($pmPrice <= 0 && $pmItem->rate) {
                        $pmPrice = (float)$pmItem->rate;
                    }

                    if ($pmItem->is_container) {
                        $pmPrice = $cf1 > 0 ? ($pmPrice / $cf1) : $pmPrice;
                    }

                    $singlePackPmCost += round($pmPrice, 2);
                }
                $singlePackPmCost = round($singlePackPmCost, 2);

                $sizeStr = strtolower(trim($pricelist->size ?? ''));
                preg_match('/(\d+(?:\.\d+)?)/', $sizeStr, $matches);
                $sizeNum = !empty($matches[1]) ? (float)$matches[1] : 1000.0;
                $sizeInMl = $sizeNum;
                if (str_contains($sizeStr, 'ml') || str_contains($sizeStr, 'gm') || str_contains($sizeStr, 'g') || str_contains($sizeStr, 'gram')) {
                    $sizeInMl = $sizeNum;
                } elseif (str_contains($sizeStr, 'ltr') || str_contains($sizeStr, 'liter') || str_contains($sizeStr, 'litre') || str_contains($sizeStr, 'kg') || preg_match('/\b\d+\s*l\b/i', $sizeStr)) {
                    $sizeInMl = $sizeNum * 1000.0;
                }
                $packVolumeLtr = $sizeInMl > 0 ? ($sizeInMl / 1000.0) : 1.0;

                $unitBulkWo   = $woDensityRate * $packVolumeLtr;
                $unitBulkWith = $withDensityRate * $packVolumeLtr;

                $unitTotalWo   = $unitBulkWo + $singlePackPmCost;
                $unitTotalWith = $unitBulkWith + $singlePackPmCost;

                $bulkCostForPackWo   = $woDensityRate * $cf1;
                $bulkCostForPackWith = $withDensityRate * $cf1;

                $packingCosts[] = [
                    'pricelist_id'     => $pricelistId,
                    'size'             => $pricelist->size ?: 'Unknown',
                    'fg_name'          => $pricelist->item_hd_name ?: ($pricelist->item_short_name ?: '—'),
                    'cf1'              => $cf1,
                    'size_in_ml'       => round($sizeInMl, 2),
                    'pack_volume_ltr'  => round($packVolumeLtr, 4),
                    'pm_cost'          => round($singlePackPmCost, 2),
                    'unit_bulk_wo'     => round($unitBulkWo, 2),
                    'unit_bulk_with'   => round($unitBulkWith, 2),
                    'unit_total_wo'    => round($unitTotalWo, 2),
                    'unit_total_with'  => round($unitTotalWith, 2),
                    'bulk_cost_wo'     => round($bulkCostForPackWo, 2),
                    'bulk_cost_with'   => round($bulkCostForPackWith, 2),
                    'total_cost_wo'    => round($bulkCostForPackWo + $singlePackPmCost, 2),
                    'total_cost_with'  => round($bulkCostForPackWith + $singlePackPmCost, 2),
                ];
            }

            return [
                'id'                 => $bom->id,
                'product_name'       => $product->name ?? '—',
                'badge'              => $bom->badge,
                'item_code'          => $product->item_code ?? '—',
                'pack_name'          => $product->pack_name ?? '—',
                'yield_qty'          => $bom->yield_quantity,
                'yield_uom'          => $bom->yield_uom,
                'density'            => $density,
                'formulation'        => $formulation,
                'grand_total'        => round($grandTotal, 2),
                'wo_density_rate'    => round($woDensityRate, 2),
                'with_density_rate'  => round($withDensityRate, 2),
                'packing_costs'      => $packingCosts,
                'purity'             => $purity,
                'purchase_date'      => $vouchDate,
                'source'             => $source,
                'rm_name'            => $rmName,
                'raw_bom_data'       => $bom->load(['items', 'packingMaterials']),
            ];
        });

        $user = Auth::user();
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
        $purities = $localPurities;

        return view('costing.pro', compact(
            'processedBoms', 
            'apiSuccess', 
            'finishedGoods', 
            'rawMaterials', 
            'types', 
            'pricelists', 
            'pmRates', 
            'purities'
        ));
    }

    public function purchaseRegister(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_purchase', 'view')) {
            abort(403, 'Access denied to Purchase Register module.');
        }

        $query = PurchaseRegister::orderByDesc('vouch_date')->orderByDesc('id');

        if ($request->filled('supplier_name')) {
            $query->where('supplier_name', $request->supplier_name);
        }

        if ($request->filled('item_name')) {
            $query->where('item_name', $request->item_name);
        }

        if ($request->filled('rm_type')) {
            $query->where('group_name4', $request->rm_type);
        }

        if ($request->filled('group_name')) {
            $query->where('group_name5', $request->group_name);
        }

        if ($request->filled('vouch_no')) {
            $query->where('vouch_no', 'like', "%" . $request->vouch_no . "%");
        }

        if ($request->filled('from_date')) {
            $query->whereDate('vouch_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('vouch_date', '<=', $request->to_date);
        }

        $purchases = $query->paginate(30)->withQueryString();

        // Base query for dependent dropdowns (shares general filters)
        $baseDropdownQuery = PurchaseRegister::query();
        if ($request->filled('vouch_no')) {
            $baseDropdownQuery->where('vouch_no', 'like', "%" . $request->vouch_no . "%");
        }
        if ($request->filled('from_date')) {
            $baseDropdownQuery->whereDate('vouch_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $baseDropdownQuery->whereDate('vouch_date', '<=', $request->to_date);
        }

        // 1. Supplier List (filtered by item, rm_type, and group)
        $supQuery = clone $baseDropdownQuery;
        if ($request->filled('item_name')) {
            $supQuery->where('item_name', $request->item_name);
        }
        if ($request->filled('rm_type')) {
            $supQuery->where('group_name4', $request->rm_type);
        }
        if ($request->filled('group_name')) {
            $supQuery->where('group_name5', $request->group_name);
        }
        $supplierList = $supQuery->whereNotNull('supplier_name')->where('supplier_name', '!=', '')->distinct()->pluck('supplier_name')->sort()->values();

        // 2. Product List (filtered by supplier, rm_type, and group)
        $prodQuery = clone $baseDropdownQuery;
        if ($request->filled('supplier_name')) {
            $prodQuery->where('supplier_name', $request->supplier_name);
        }
        if ($request->filled('rm_type')) {
            $prodQuery->where('group_name4', $request->rm_type);
        }
        if ($request->filled('group_name')) {
            $prodQuery->where('group_name5', $request->group_name);
        }
        $productList = $prodQuery->whereNotNull('item_name')->where('item_name', '!=', '')->distinct()->pluck('item_name')->sort()->values();
        
        // 3. RM Type List (filtered by supplier, item, and group)
        $rmTypeQuery = clone $baseDropdownQuery;
        if ($request->filled('supplier_name')) {
            $rmTypeQuery->where('supplier_name', $request->supplier_name);
        }
        if ($request->filled('item_name')) {
            $rmTypeQuery->where('item_name', $request->item_name);
        }
        if ($request->filled('group_name')) {
            $rmTypeQuery->where('group_name5', $request->group_name);
        }
        $rmTypeList = $rmTypeQuery->whereNotNull('group_name4')->where('group_name4', '!=', '')->distinct()->pluck('group_name4')->sort()->values();

        // 4. Group List (filtered by supplier, item, and rm_type)
        $grpQuery = clone $baseDropdownQuery;
        if ($request->filled('supplier_name')) {
            $grpQuery->where('supplier_name', $request->supplier_name);
        }
        if ($request->filled('item_name')) {
            $grpQuery->where('item_name', $request->item_name);
        }
        if ($request->filled('rm_type')) {
            $grpQuery->where('group_name4', $request->rm_type);
        }
        $groupList = $grpQuery->whereNotNull('group_name5')->where('group_name5', '!=', '')->distinct()->pluck('group_name5')->sort()->values();

        $settings = [
            'purchase_sync_auto'      => AppSetting::get('purchase_sync_auto', 'disabled'),
            'purchase_sync_frequency' => AppSetting::get('purchase_sync_frequency', 'daily'),
            'purchase_sync_time'      => AppSetting::get('purchase_sync_time', '02:00'),
            'purchase_sync_day'       => AppSetting::get('purchase_sync_day', 'Sunday'),
        ];

        return view('costing.purchase_register', compact('purchases', 'supplierList', 'productList', 'rmTypeList', 'groupList', 'settings'));
    }

    /**
     * POST: Sync purchase register from ERP for current year and save to DB
     */
    public function syncPurchaseRegister(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_purchase', 'view')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        try {
            $count = $this->syncPurchaseRegisterRaw();
            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$count} purchases from ERP API into database.",
            ]);
        } catch (\Exception $e) {
            Log::error('Purchase Register Sync Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Common core method to sync purchase registers from ERP API
     */
    public function syncPurchaseRegisterRaw()
    {
        $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
        $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');

        // FY auto-dates (current year)
        $now     = now();
        $fyStart = $now->month >= 4 ? $now->year . '-04-01' : ($now->year - 1) . '-04-01';
        $fyEnd   = $now->month >= 4 ? ($now->year + 1) . '-03-31' : $now->year . '-03-31';

        $fromDate = AppSetting::get('costing_api_from_date') ?: $fyStart;
        $toDate   = AppSetting::get('costing_api_to_date')   ?: $fyEnd;

        $response = Http::withoutVerifying()
            ->timeout(90)
            ->post("{$baseUrl}/LogicPurchaseRegisterDetail", [
                'apikey'   => $apiKey,
                'FromDate' => $fromDate,
                'ToDate'   => $toDate,
                'Account'  => 'all',
                'Item'     => 'all',
                'Branch'   => 'all',
            ]);

        if (!$response->successful()) {
            throw new \Exception('ERP API request failed.');
        }

        $data = $response->json();
        if (($data['response'] ?? '') !== 'success' || empty($data['resultdata'])) {
            throw new \Exception('No data returned from ERP API.');
        }

        $count = 0;
        DB::transaction(function () use ($data, &$count) {
            foreach ($data['resultdata'] as $row) {
                $itemCode  = trim($row['User_Code'] ?? '');
                if (empty($itemCode)) continue;

                $vouchDateStr = $row['Vouch_Date'] ?? null;
                $formattedDate = null;
                if ($vouchDateStr) {
                    try {
                        $formattedDate = \Carbon\Carbon::createFromFormat('d/m/Y', $vouchDateStr)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $formattedDate = null;
                    }
                }

                $vouchNo = trim($row['Bill_No'] ?? $row['Vouch_No'] ?? '');

                PurchaseRegister::updateOrCreate(
                    [
                        'item_code'     => $itemCode,
                        'vouch_no'      => $vouchNo,
                        'vouch_date'    => $formattedDate,
                        'supplier_name' => trim($row['SupplierName'] ?? ''),
                    ],
                    [
                        'item_name'   => trim($row['Item_Hd_Name'] ?? $row['ItemName'] ?? ''),
                        'qty'         => (float)($row['Qty'] ?? 0),
                        'case_rate'   => (float)($row['CaseRate'] ?? 0),
                        'purity'      => isset($row['Purity']) ? (float)$row['Purity'] : null,
                        'group_name4' => trim($row['GroupName4'] ?? ''),
                        'group_name5' => trim($row['GroupName5'] ?? ''),
                    ]
                );
                $count++;
            }
        });

        return $count;
    }

    /**
     * POST: Save sync scheduler settings to database
     */
    public function saveSyncSettings(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_purchase', 'view')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $request->validate([
            'purchase_sync_auto'      => 'required|in:enabled,disabled',
            'purchase_sync_frequency' => 'required|in:daily,weekly',
            'purchase_sync_time'      => 'required',
            'purchase_sync_day'       => 'required|in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
        ]);

        AppSetting::set('purchase_sync_auto', $request->purchase_sync_auto);
        AppSetting::set('purchase_sync_frequency', $request->purchase_sync_frequency);
        AppSetting::set('purchase_sync_time', $request->purchase_sync_time);
        AppSetting::set('purchase_sync_day', $request->purchase_sync_day);

        return response()->json([
            'success' => true,
            'message' => 'Sync settings updated successfully.',
        ]);
    }

    /**
     * Desktop: Pricelist view - synced from Product Master ERP API
     */
    public function pricelist(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_pricelist', 'view')) {
            abort(403, 'Access denied to Pricelist module.');
        }

        $query = \App\Models\Pricelist::where('group5', 'FINISHED GOODS');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('item_hd_name', 'like', "%{$search}%")
                  ->orWhere('user_code', 'like', "%{$search}%")
                  ->orWhere('group3', 'like', "%{$search}%");
            });
        }

        if ($request->filled('group1')) {
            $query->where('group1', $request->group1);
        }

        $sortOrder = $request->input('sort', 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy('item_hd_name', $sortOrder);

        $pricelists = $query->paginate(30)->withQueryString();

        $group1List = \App\Models\Pricelist::where('group5', 'FINISHED GOODS')->whereNotNull('group1')->where('group1', '!=', '')->distinct()->pluck('group1')->sort()->values();

        $settings = [
            'pricelist_sync_auto'      => AppSetting::get('pricelist_sync_auto', 'disabled'),
            'pricelist_sync_frequency' => AppSetting::get('pricelist_sync_frequency', 'daily'),
            'pricelist_sync_time'      => AppSetting::get('pricelist_sync_time', '02:00'),
            'pricelist_sync_day'       => AppSetting::get('pricelist_sync_day', 'Sunday'),
        ];

        return view('costing.pricelist', compact('pricelists', 'group1List', 'settings'));
    }

    /**
     * POST: Sync pricelist manually from Product Master API
     */
    public function syncPricelist(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_pricelist', 'view')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        try {
            $count = $this->syncPricelistRaw();
            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$count} items from Product Master ERP API into database.",
            ]);
        } catch (\Exception $e) {
            Log::error('Pricelist Sync Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Core logic to sync from Product Master API
     */
    public function syncPricelistRaw()
    {
        $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
        $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');

        $response = Http::withoutVerifying()
            ->timeout(90)
            ->post("{$baseUrl}/ProductMaster", [
                'apikey'       => $apiKey,
                'Itemdetcode'  => AppSetting::get('product_master_itemdetcode', '0'),
                'Usercode'     => AppSetting::get('product_master_usercode', '0'),
                'Branchcode'   => AppSetting::get('product_master_branchcode', '0'),
                'PageNumber'   => AppSetting::get('product_master_page_number', '1'),
                'RowsOfPage'   => AppSetting::get('product_master_rows', '10000'),
                'modifieddate' => '',
                'TxnType'      => AppSetting::get('product_master_txn_type', 'Old'),
            ]);

        if (!$response->successful()) {
            throw new \Exception('ERP ProductMaster API request failed.');
        }

        $data = $response->json();
        if (($data['response'] ?? '') !== 'success' || empty($data['resultdata'])) {
            throw new \Exception('No data returned from ERP API.');
        }

        $count = 0;
        $items = $data['resultdata'];
        DB::transaction(function () use ($items, &$count) {
            foreach ($items as $item) {
                $userCode = trim($item['User_Code'] ?? '');
                if (empty($userCode)) continue;

                $updateData = [
                    'item_det_code'   => trim($item['Item_det_code'] ?? ''),
                    'item_hd_name'    => trim($item['Item_hd_name'] ?? ''),
                    'item_short_name' => trim($item['Item_Short_Name'] ?? ''),
                    'size'            => trim($item['Size'] ?? ''),
                    'size_desc'       => trim($item['Size_Desc'] ?? ''),
                    'group1'          => trim($item['Group1'] ?? ''),
                    'group2'          => trim($item['Group2'] ?? ''),
                    'group3'          => trim($item['Group3'] ?? ''),
                    'group4'          => trim($item['Group4'] ?? ''),
                    'group5'          => trim($item['Group5'] ?? ''),
                    'group6'          => trim($item['Group6'] ?? ''),
                    'mrp'             => isset($item['MRP']) ? (float)$item['MRP'] : null,
                    'sp_rate1'        => isset($item['Sp_Rate1']) ? (float)$item['Sp_Rate1'] : null,
                    'sp_rate2'        => isset($item['Sp_Rate2']) ? (float)$item['Sp_Rate2'] : null,
                    'sp_rate3'        => isset($item['Sp_Rate3']) ? (float)$item['Sp_Rate3'] : null,
                    'sp_rate4'        => isset($item['Sp_Rate4']) ? (float)$item['Sp_Rate4'] : null,
                    'sp_rate5'        => isset($item['Sp_Rate5']) ? (float)$item['Sp_Rate5'] : null,
                    'sale_rate'       => isset($item['Sale_rate']) ? (float)$item['Sale_rate'] : null,
                    'barcode'         => trim($item['Barcode'] ?? ''),
                    'item_nature'     => trim($item['Item_Nature'] ?? ''),
                    'cf_1'            => isset($item['cf_1']) ? (float)$item['cf_1'] : null,
                    'cf_2'            => isset($item['cf_2']) ? (float)$item['cf_2'] : null,
                    'cf_3'            => isset($item['cf_3']) ? (float)$item['cf_3'] : null,
                    'modify_date'     => trim($item['Modify_Date'] ?? ''),
                    'gst_tax'         => trim($item['GSTTax'] ?? ''),
                ];

                $existing = \App\Models\Pricelist::where('user_code', $userCode)->first();
                if ($existing) {
                    for ($i = 1; $i <= 5; $i++) {
                        $rateCol = "sp_rate{$i}";
                        $prevCol = "prev_sp_rate{$i}";
                        $newVal = $updateData[$rateCol];
                        $oldVal = $existing->$rateCol !== null ? (float)$existing->$rateCol : null;

                        if ($newVal !== null && $oldVal !== null && abs($newVal - $oldVal) > 0.001) {
                            $updateData[$prevCol] = $oldVal;
                        }
                    }
                    $existing->update($updateData);
                } else {
                    \App\Models\Pricelist::create(array_merge(['user_code' => $userCode], $updateData));
                }
                $count++;
            }
        });

        return $count;
    }

    /**
     * POST: Save Pricelist sync scheduler settings
     */
    public function savePricelistSyncSettings(Request $request)
    {
        if (!Auth::user()->hasPermission('costing_pricelist', 'view')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $request->validate([
            'pricelist_sync_auto'      => 'required|in:enabled,disabled',
            'pricelist_sync_frequency' => 'required|in:daily,weekly',
            'pricelist_sync_time'      => 'required',
            'pricelist_sync_day'       => 'required|in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
        ]);

        AppSetting::set('pricelist_sync_auto', $request->pricelist_sync_auto);
        AppSetting::set('pricelist_sync_frequency', $request->pricelist_sync_frequency);
        AppSetting::set('pricelist_sync_time', $request->pricelist_sync_time);
        AppSetting::set('pricelist_sync_day', $request->pricelist_sync_day);

        return response()->json([
            'success' => true,
            'message' => 'Pricelist sync settings updated successfully.',
        ]);
    }

    // ─────────────────────────────────────────────
    //  Private Helpers
    // ─────────────────────────────────────────────

    /**
     * Compute the manufacturing cost for a product given a quantity.
     */
    private function computeCost(Product $product, array $priceMap, float $quantity, float $density = 1): array
    {
        $recipe = $product->costingBoms->first();

        if (!$recipe || $recipe->items->isEmpty()) {
            return [
                'has_recipe'    => false,
                'cost_per_unit' => 0,
                'total_cost'    => 0,
                'breakdown'     => [],
                'packing_costs' => [],
            ];
        }

        $formulation = ($recipe->formulation !== null) ? (float)$recipe->formulation : $this->parseFormulation($product->name);

        $breakdown    = $this->buildBreakdown($recipe, $priceMap, $quantity, $product, $formulation, $density);
        $totalCost    = collect($breakdown)->sum('sub_cost');
        $costPerUnit  = $quantity > 0 ? $totalCost / $quantity : 0;

        // Size-wise packing materials cost computation
        $packingCosts = [];
        $pms = \App\Models\CostingBomPackingMaterial::with(['rawMaterial', 'pricelist'])
            ->where('costing_bom_id', $recipe->id)
            ->get();
        
        $grouped = $pms->groupBy('pricelist_id');
        foreach ($grouped as $pricelistId => $items) {
            $pricelist = $items->first()->pricelist;
            if (!$pricelist) continue;
            
            $cf1 = (float)($pricelist->cf_1 ?? 1);
            if ($cf1 <= 0) $cf1 = 1;
            
            $singlePackPmCost = 0;
            $pmBreakdown = [];
            foreach ($items as $item) {
                $rm = $item->rawMaterial;
                if (!$rm) continue;
                $pmPrice = (float)($priceMap[$rm->item_code] ?? 0);
                if ($pmPrice <= 0 && $item->rate) {
                    $pmPrice = (float)$item->rate;
                }
                
                if ($item->is_container) {
                    $pmPrice = $cf1 > 0 ? ($pmPrice / $cf1) : $pmPrice;
                }
                
                $itemCost = round($pmPrice, 2);
                $singlePackPmCost += $itemCost;
                
                $pmBreakdown[] = [
                    'name' => $rm->name,
                    'qty' => (float)$item->quantity,
                    'price' => round($pmPrice, 2),
                    'cost' => round($itemCost, 2),
                ];
            }
            $singlePackPmCost = round($singlePackPmCost, 2);

            $bulkCostForPack = $costPerUnit * $cf1;
            $totalPackCost = $bulkCostForPack + $singlePackPmCost;
            
            $packingCosts[] = [
                'pricelist_id' => $pricelistId,
                'size' => $pricelist->size ?? 'Unknown',
                'fg_name' => $pricelist->item_hd_name,
                'cf1' => $cf1,
                'bulk_cost' => round($bulkCostForPack, 2),
                'pm_cost' => round($singlePackPmCost, 2),
                'total_cost' => round($totalPackCost, 2),
                'pm_breakdown' => $pmBreakdown,
            ];
        }

        return [
            'has_recipe'    => true,
            'cost_per_unit' => round($costPerUnit, 4),
            'total_cost'    => round($totalCost, 4),
            'breakdown'     => $breakdown,
            'packing_costs' => $packingCosts,
        ];
    }

    /**
     * Build the per-item cost breakdown array.
     */
    private function buildBreakdown(CostingBom $recipe, array $priceMap, float $quantity = 1, ?Product $finishedProduct = null, float $formulation = 100, float $density = 1): array
    {
        $breakdown = [];
        $fp        = $finishedProduct ?? $recipe->finishedProduct;
        
        // Weight multiplier for converting boxes → base unit
        $weightMultiplier = $fp ? (float)($fp->weight_multiplier ?? 1) : 1;
        $baseQty = $quantity * $weightMultiplier * $density;

        foreach ($recipe->items as $item) {
            $rm = $item->rawMaterial;
            if (!$rm) continue;

            // Qty of RM needed for this batch
            $requiredQty  = ($item->quantity / max($recipe->yield_quantity, 0.001)) * $baseQty;
            
            // Adjust required technical raw material quantity by formulation and purity percentage
            if (strtoupper(trim($rm->rm_type)) === 'TECHNICAL') {
                $rmPurity = (float) \App\Models\ProductPrice::where('item_code', $rm->item_code)->value('purity');
                if ($rmPurity <= 0 && $item->purity > 0) {
                    $rmPurity = (float) $item->purity;
                }
                if ($rmPurity <= 0) {
                    $rmPurity = 100.0;
                }
                $requiredQty = ($baseQty * $formulation) / $rmPurity;
            }

            $pricePerUnit = (float)($priceMap[$rm->item_code] ?? 0);
            $tc = (float)($item->transportation_cost ?? 5.0);
            $subCost      = $requiredQty * ($pricePerUnit + $tc);

            $breakdown[] = [
                'rm_id'        => $rm->id,
                'rm_name'      => $rm->name,
                'item_code'    => $rm->item_code,
                'uom'          => $rm->uom,
                'recipe_qty'   => round($item->quantity, 4),
                'required_qty' => round($requiredQty, 4),
                'price'        => $pricePerUnit,
                'transportation_cost' => $tc,
                'sub_cost'     => round($subCost, 4),
                'has_price'    => $pricePerUnit > 0,
            ];
        }

        return $breakdown;
    }

    /**
     * Parse and sum all formulation percentages in the product name.
     */
    private function parseFormulation(string $name): float
    {
        preg_match_all('/(\d+(?:\.\d+)?)\s*%/', $name, $matches);
        if (!empty($matches[1])) {
            return (float) array_sum(array_map('floatval', $matches[1]));
        }
        return 100.0;
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
