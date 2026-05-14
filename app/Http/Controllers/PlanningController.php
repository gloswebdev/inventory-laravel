<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\Branch;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PlanningController extends Controller
{
    /**
     * Production Planning Dashboard
     */
    public function index(Request $request)
    {
        $productsQuery = Product::whereHas('recipes')->whereIn('product_type_id', [6, 7])->orderBy('name');
        $this->applyTypeFilters($productsQuery);
        $finishedGoods = $productsQuery->get();
        
        $branches = Branch::orderBy('sort_order')->orderBy('code')->get();
        $productTypes = \App\Models\ProductType::orderBy('type_name')->get();

        // Fetch Indents for "Plan by Indent" feature
        $indents = \App\Models\Indent::with(['items.product', 'user'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
            
        $users = \App\Models\User::orderBy('name')->get();
        $history = $indents;

        return view('planning.index', compact('finishedGoods', 'branches', 'productTypes', 'indents', 'users', 'history'));
    }

    /**
     * Calculate MRP Requirements
     */
    public function calculate(Request $request)
    {
        $productsInput = $request->input('products', []);
        $branchCode = $request->input('branch_code');
        
        if (empty($productsInput)) {
            return response()->json(['success' => false, 'message' => 'No products provided']);
        }

        $results = $this->getConsolidatedRequirements($productsInput, $branchCode);

        if (is_string($results)) {
            return response()->json(['success' => false, 'message' => $results]);
        }

        $summary = $this->getProductionSummary($productsInput);

        return response()->json([
            'success' => true, 
            'data' => $results,
            'summary' => $summary
        ]);
    }

    /**
     * Export MRP Report to Excel
     */
    public function export(Request $request)
    {
        $productsInput = json_decode($request->input('products_json', '[]'), true);
        $branchCode = $request->input('branch_code');
        
        $results = $this->getConsolidatedRequirements($productsInput, $branchCode);

        if (is_string($results)) {
            return redirect()->back()->with('error', $results);
        }

        $summary = $this->getProductionSummary($productsInput);

        return (new \App\Exports\MRPExport($results, $summary, $branchCode))->download('mrp_planning_report.xlsx');
    }

    private function getConsolidatedRequirements($productsInput, $branchCode = null)
    {
        $totalRequirements = [];
        $externalStock = $this->getExternalStock();

        foreach ($productsInput as $input) {
            $productId = $input['id'];
            $demandQty = (float)$input['demand_qty'];

            $product = Product::find($productId);
            $recipe = Recipe::where('finished_product_id', $productId)->with('items.rawMaterial')->first();
            if (!$product || !$recipe) continue;

            foreach ($recipe->items as $item) {
                $rm = $item->rawMaterial;
                if (!$rm) continue;

                $requiredForThisPerYield = ($item->quantity / $recipe->yield_quantity);
                // Convert demand items to KG/LTR to match recipe yield unit
                $demandInBaseUnit = $demandQty * $product->weight_multiplier;
                $requiredForThis = $requiredForThisPerYield * $demandInBaseUnit;

                if (isset($totalRequirements[$rm->id])) {
                    $totalRequirements[$rm->id]['required_qty'] += $requiredForThis;
                } else {
                    $currentStock = 0;
                    if ($branchCode && isset($externalStock[$branchCode][$rm->item_code])) {
                        $currentStock = $externalStock[$branchCode][$rm->item_code];
                    } else {
                        foreach ($externalStock as $bCode => $items) {
                            $currentStock += ($items[$rm->item_code] ?? 0);
                        }
                    }

                    $totalRequirements[$rm->id] = [
                        'id' => $rm->id,
                        'name' => $rm->name,
                        'item_code' => $rm->item_code,
                        'uom' => $rm->uom,
                        'pack_name' => $rm->pack_name,
                        'required_qty' => $requiredForThis,
                        'current_stock' => $currentStock,
                    ];
                }
            }
        }

        return array_values(array_map(function($item) {
            $shortfall = $item['required_qty'] - $item['current_stock'];
            $item['shortfall'] = $shortfall > 0 ? $shortfall : 0;
            return $item;
        }, $totalRequirements));
    }

    private function getProductionSummary($productsInput)
    {
        $summary = [];
        foreach ($productsInput as $input) {
            $product = Product::find($input['id']);
            if ($product) {
                $summary[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'item_code' => $product->item_code,
                    'pack_name' => $product->pack_name,
                    'quantity' => (float)$input['demand_qty']
                ];
            }
        }
        return $summary;
    }

    private function getExternalStock()
    {
        return Cache::remember('external_stock_data_grouped', 3600, function () {
            try {
                $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
                $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');
                $branch  = AppSetting::get('inventory_api_branch', 'ALL');
                $item    = AppSetting::get('inventory_api_item', 'ALL');

                $response = \Illuminate\Support\Facades\Http::timeout(30)->post("{$baseUrl}/ProductWiseInventory", [
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
                \Illuminate\Support\Facades\Log::error('External Stock API Error (Planning): ' . $e->getMessage());
            }
            return [];
        });
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
