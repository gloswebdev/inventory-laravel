<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Branch;
use Illuminate\Http\Request;

class IndentController extends Controller
{
    public function calculate(Request $request)
    {
        $productsInput = $request->input('products', []);
        $branchCode = $request->input('branch_code');
        $results = $this->getConsolidatedRequirements($productsInput, $branchCode);

        if (is_string($results)) {
            return response()->json(['success' => false, 'message' => $results]);
        }

        $productionSummary = $this->getProductionSummary($productsInput);

        return response()->json([
            'success' => true, 
            'data' => $results,
            'summary' => $productionSummary
        ]);
    }

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

    public function index()
    {
        $finishedGoods = Product::whereHas('recipes')->orderBy('name')->get();
        $branches = Branch::orderBy('code')->get();
        $productTypes = \App\Models\ProductType::orderBy('type_name')->get();
        return view('indent.index', compact('finishedGoods', 'branches', 'productTypes'));
    }

    private function getProductionSummary($productsInput)
    {
        $summary = [];
        foreach ($productsInput as $input) {
            $product = \App\Models\Product::find($input['id']);
            if ($product) {
                $summary[] = [
                    'name' => $product->name,
                    'item_code' => $product->item_code,
                    'pack_name' => $product->pack_name,
                    'quantity' => (float)$input['demand_qty']
                ];
            }
        }
        return $summary;
    }

    private function getConsolidatedRequirements($productsInput, $branchCode = null)
    {
        if (empty($productsInput)) {
            return 'No products provided';
        }

        $totalRequirements = [];
        
        // Fetch external stock from API
        $externalStock = $this->getExternalStock();

        foreach ($productsInput as $input) {
            $productId = $input['id'];
            $demandQty = (float)$input['demand_qty'];

            $recipe = \App\Models\Recipe::where('finished_product_id', $productId)
                ->with('items.rawMaterial')
                ->first();

            if (!$recipe) continue;

            foreach ($recipe->items as $item) {
                $rm = $item->rawMaterial;
                if (!$rm) continue;

                $requiredForThisFG = ($item->quantity / $recipe->yield_quantity) * $demandQty;

                if (isset($totalRequirements[$rm->id])) {
                    $totalRequirements[$rm->id]['required_qty'] += $requiredForThisFG;
                } else {
                    // Correct Stock aggregation logic
                    $currentStock = 0;
                    if ((int)$branchCode && isset($externalStock[(int)$branchCode][$rm->item_code])) {
                        $currentStock = $externalStock[(int)$branchCode][$rm->item_code];
                    } else {
                        // Sum across all branches for consolidated view
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
                        'required_qty' => $requiredForThisFG,
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

    private function getExternalStock()
    {
        return \Illuminate\Support\Facades\Cache::remember('external_stock_data_grouped', 300, function () {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(30)->post('https://logicapi.algebraerp.com/API/SYNWOOD/ProductWiseInventory', [
                    "apikey" => "e2a4fuye2a4fuy9swssw122sbkn0m82y83g14",
                    "Branch" => "ALL",
                    "Item" => "ALL"
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
                \Illuminate\Support\Facades\Log::error('External Stock API Error (Indent): ' . $e->getMessage());
            }
            return [];
        });
    }
}
