<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductType;
use App\Models\CostingBom;
use App\Models\CostingBomItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class CostingController extends Controller
{
    /**
     * Desktop: Costing index — product list with cost overview
     */
    public function index(Request $request)
    {
        if (!Auth::user()->hasPermission('costing', 'view')) {
            abort(403, 'Access denied to Costing module.');
        }

        $user = Auth::user();

        $query = Product::with(['type', 'costingBoms.items.rawMaterial'])
                        ->whereHas('costingBoms')
                        ->orderBy('name');

        // Permission-based product type filter
        if ($user->role !== 'admin') {
            $query->whereIn('product_type_id', $user->getPermittedProductTypeIds());
        }

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%$s%")
                  ->orWhere('item_code', 'like', "%$s%");
            });
        }

        // Type filter
        if ($request->filled('type_id')) {
            $query->where('product_type_id', $request->type_id);
        }

        $products     = $query->get();
        $priceMap     = ProductPrice::allAsMap();
        $productTypes = ProductType::orderBy('type_name')->get();
        $lastSync     = ProductPrice::where('price_source', 'erp')
                        ->orderByDesc('fetched_at')->first()?->fetched_at
                        ?? ProductPrice::orderByDesc('updated_at')->first()?->updated_at;

        // Pre-compute per-unit cost for each product using costing BOMs
        $costData = [];
        foreach ($products as $product) {
            $costData[$product->id] = $this->computeCost($product, $priceMap, 1, 1);
        }

        $activeTab = 'calculator';

        return view('costing.index', compact(
            'products', 'priceMap', 'productTypes', 'lastSync', 'costData', 'activeTab'
        ));
    }

    /**
     * Desktop: Show detailed cost breakdown for one product
     */
    public function show(Product $product)
    {
        if (!Auth::user()->hasPermission('costing', 'view')) {
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
        if (!Auth::user()->hasPermission('costing', 'view')) {
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

            preg_match('/(\d+(?:\.\d+)?)\s*%/', $product->name, $matches);
            $formulation = isset($matches[1]) ? (float)$matches[1] : 100.0;

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
        if (!Auth::user()->hasPermission('costing', 'view')) {
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

            preg_match('/(\d+(?:\.\d+)?)\s*%/', $product->name, $matches);
            $formulation = isset($matches[1]) ? (float)$matches[1] : 100.0;

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
            ];
        }

        preg_match('/(\d+(?:\.\d+)?)\s*%/', $product->name, $matches);
        $formulation = isset($matches[1]) ? (float)$matches[1] : 100.0;

        $breakdown    = $this->buildBreakdown($recipe, $priceMap, $quantity, $product, $formulation, $density);
        $totalCost    = collect($breakdown)->sum('sub_cost');
        $costPerUnit  = $quantity > 0 ? $totalCost / $quantity : 0;

        return [
            'has_recipe'    => true,
            'cost_per_unit' => round($costPerUnit, 4),
            'total_cost'    => round($totalCost, 4),
            'breakdown'     => $breakdown,
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
            $subCost      = $requiredQty * $pricePerUnit;

            $breakdown[] = [
                'rm_id'        => $rm->id,
                'rm_name'      => $rm->name,
                'item_code'    => $rm->item_code,
                'uom'          => $rm->uom,
                'recipe_qty'   => round($item->quantity, 4),
                'required_qty' => round($requiredQty, 4),
                'price'        => $pricePerUnit,
                'sub_cost'     => round($subCost, 4),
                'has_price'    => $pricePerUnit > 0,
            ];
        }

        return $breakdown;
    }
}
