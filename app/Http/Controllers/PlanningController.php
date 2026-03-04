<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class PlanningController extends Controller
{
    public function calculate(Request $request)
    {
        $productsInput = $request->input('products', []);
        $branchCode = $request->input('branch_code'); // Get branch from request
        
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

    public function index(Request $request)
    {
        $productsQuery = Product::where('product_type_id', 1)->orderBy('name');
        $this->applyTypeFilters($productsQuery);
        $finishedGoods = $productsQuery->get();
        $branches = \App\Models\Branch::orderBy('code')->get();
        
        $query = \App\Models\Indent::with(['items.product', 'user']);

        if ($request->filled('from_date')) {
            $query->whereDate('indent_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('indent_date', '<=', $request->to_date);
        }

        if ($request->filled('branch_code')) {
            $query->where('branch_code', $request->branch_code);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $history = $query->orderByDesc('created_at')->get();
        $users = \App\Models\User::orderBy('name')->get();
        
        return view('planning.index', compact('finishedGoods', 'branches', 'history', 'users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'branch_code' => 'required',
            'indent_date' => 'required|date',
            'products' => 'required|array',
        ]);

        $branch = \App\Models\Branch::where('code', $request->branch_code)->first();
        $branchName = $branch ? $branch->name : "Branch " . $request->branch_code;

        $indent = \App\Models\Indent::create([
            'branch_code' => $request->branch_code,
            'branch_name' => $branchName,
            'indent_date' => $request->indent_date,
            'user_id' => auth()->id(),
            'total_boxes' => 0, // Will update after adding items
        ]);

        $totalBoxes = 0;
        foreach ($request->products as $item) {
            if ($item['demand_qty'] > 0) {
                \App\Models\IndentItem::create([
                    'indent_id' => $indent->id,
                    'product_id' => $item['id'],
                    'product_name' => $item['name'] ?? null,
                    'demand_qty' => $item['demand_qty'],
                    'demand_unit' => $item['unit'],
                    'stock_box' => $item['stock_box'] ?? 0,
                    'stock_kg' => $item['stock_kg'] ?? 0,
                    'final_qty_box' => $item['final_qty_box'] ?? 0,
                ]);
                $totalBoxes += (float)($item['final_qty_box'] ?? 0);
            }
        }

        $indent->update(['total_boxes' => $totalBoxes]);

        return response()->json(['success' => true, 'message' => 'Indent saved successfully!']);
    }

    public function getStock(Request $request)
    {
        $productId = $request->get('product_id');
        $branchCode = $request->get('branch_code');

        if (!$productId) return response()->json(['success' => false, 'stock' => 0]);

        $query = Product::where('id', $productId);
        $this->applyTypeFilters($query);
        $product = $query->first();

        if (!$product) return response()->json(['success' => false, 'stock' => 0]);

        $externalStock = $this->getExternalStock();
        
        // If branch is specified, get branch-specific stock, else get sum
        $stock = 0;
        if ($branchCode && isset($externalStock[$branchCode][$product->item_code])) {
            $stock = $externalStock[$branchCode][$product->item_code];
        } elseif (!$branchCode) {
            // Sum across all branches if no branch selected
            foreach ($externalStock as $branchId => $items) {
                $stock += ($items[$product->item_code] ?? 0);
            }
        }

        // Conversion calculations (API returns Pcs/Units)
        $unitPerBox = (float)($product->unit_box ?: 1);
        $stockBoxes = $stock / $unitPerBox;

        return response()->json([
            'success' => true, 
            'stock' => $stock,
            'stock_boxes' => $stockBoxes,
            'uom' => $product->uom,
            'unit_box' => $product->unit_box,
            'weight_unit' => $product->weight_unit
        ]);
    }

    public function getBulkStock(Request $request)
    {
        $branchCode = $request->get('branch_code');
        
        $productsQuery = Product::where('product_type_id', 1)->orderBy('name');
        $this->applyTypeFilters($productsQuery);
        $products = $productsQuery->get();
        $externalStock = $this->getExternalStock();
        
        $results = [];
        foreach ($products as $product) {
            $stock = 0;
            if ((int)$branchCode && isset($externalStock[(int)$branchCode][$product->item_code])) {
                $stock = $externalStock[(int)$branchCode][$product->item_code];
            } elseif (!(int)$branchCode) {
                foreach ($externalStock as $bCode => $items) {
                    $stock += ($items[$product->item_code] ?? 0);
                }
            }

            $unitPerBox = (float)($product->unit_box ?: 1);
            $weightPerUnit = (float)($product->weight_unit ?: 1);
            
            $stockBoxes = $stock / $unitPerBox;
            $stockKg = $stock * $weightPerUnit;
            
            $results[$product->id] = [
                'stock' => $stockKg, // Total KG/LTR
                'stock_boxes' => $stockBoxes // Total Boxes
            ];
        }

        return response()->json([
            'success' => true,
            'stocks' => $results
        ]);
    }

    public function show(\App\Models\Indent $indent)
    {
        return response()->json([
            'success' => true,
            'indent' => $indent->load('items.product', 'user')
        ]);
    }

    public function print(\App\Models\Indent $indent)
    {
        $indent->load('items.product', 'user');
        return view('planning.print', compact('indent'));
    }

    public function exportExcel(\App\Models\Indent $indent)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\IndentExport($indent), 
            "Indent_{$indent->branch_code}_{$indent->indent_date}.xlsx"
        );
    }

    public function exportPdf(\App\Models\Indent $indent)
    {
        $indent->load('items.product', 'user');
        $pdf = Pdf::loadView('planning.indent_pdf', compact('indent'));
        return $pdf->download("Indent_{$indent->branch_code}_{$indent->indent_date}.pdf");
    }

    public function exportProcessPdf(\App\Models\Indent $indent)
    {
        $indent->load('items.product', 'user');
        $branches = \App\Models\Branch::orderBy('code')->get();
        $branchStocks = $this->getBranchStocksForIndent($indent, $branches);

        $pdf = Pdf::loadView('planning.process_pdf', compact('indent', 'branches', 'branchStocks'))
                  ->setPaper('a4', 'landscape');
        
        return $pdf->download("Process_Matrix_{$indent->branch_code}_{$indent->indent_date}.pdf");
    }

    public function updateCompletion(Request $request, \App\Models\Indent $indent)
    {
        $quantities = $request->input('completed_qty', []);
        $totalAsked = 0;
        $totalCompleted = 0;
        $anyCompleted = false;

        foreach ($indent->items as $item) {
            $compQty = $quantities[$item->id] ?? 0;
            $item->update(['completed_qty' => $compQty]);
            
            $totalAsked += $item->final_qty_box;
            $totalCompleted += $compQty;
            if ($compQty > 0) $anyCompleted = true;
        }

        $status = 'pending';
        if ($totalCompleted >= $totalAsked && $totalAsked > 0) {
            $status = 'completed';
        } elseif ($anyCompleted) {
            $status = 'partly completed';
        }

        $indent->update(['status' => $status]);

        return redirect()->back()->with('success', 'Indent completion status updated to ' . strtoupper($status));
    }

    public function process(\App\Models\Indent $indent)
    {
        $indent->load('items.product', 'user');
        $branches = \App\Models\Branch::orderBy('code')->get();
        $branchStocks = $this->getBranchStocksForIndent($indent, $branches);

        return view('planning.process', compact('indent', 'branches', 'branchStocks'));
    }

    public function exportProcessExcel(\App\Models\Indent $indent)
    {
        $indent->load('items.product', 'user');
        $branches = \App\Models\Branch::orderBy('code')->get();
        $branchStocks = $this->getBranchStocksForIndent($indent, $branches);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\IndentProcessExport($indent, $branches, $branchStocks), 
            "Process_Matrix_{$indent->branch_code}_{$indent->indent_date}.xlsx"
        );
    }

    public function processList(Request $request)
    {
        $query = \App\Models\Indent::with(['user'])->withCount('items');

        if ($request->filled('from_date')) {
            $query->whereDate('indent_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('indent_date', '<=', $request->to_date);
        }

        if ($request->filled('branch_code')) {
            $query->where('branch_code', $request->branch_code);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $history = $query->orderByDesc('created_at')->get();
        
        $branches = \App\Models\Branch::orderBy('name')->get();
        $users = \App\Models\User::orderBy('name')->get();

        return view('planning.list', compact('history', 'branches', 'users'));
    }

    private function getBranchStocksForIndent($indent, $branches)
    {
        $externalStock = $this->getExternalStock(); // [branch_code][item_code] = qty
        $branchStocks = [];
        foreach ($indent->items as $item) {
            $p = $item->product;
            if (!$p) continue;
            
            foreach ($branches as $branch) {
                $rawStock = $externalStock[$branch->code][$p->item_code] ?? 0;
                
                // Convert to Boxes (API returns Pcs/Units)
                $unitPerBox = (float)($p->unit_box ?: 1);
                $boxStock = $rawStock / $unitPerBox;
                
                $branchStocks[$p->id][$branch->code] = $boxStock;
            }
        }
        return $branchStocks;
    }

    private function getConsolidatedRequirements($productsInput, $branchCode = null)
    {
        $totalRequirements = [];
        $externalStock = $this->getExternalStock();

        foreach ($productsInput as $input) {
            $productId = $input['id'];
            $demandQty = (float)$input['demand_qty'];

            $recipe = Recipe::where('finished_product_id', $productId)->with('items.rawMaterial')->first();
            if (!$recipe) continue;

            foreach ($recipe->items as $item) {
                $rm = $item->rawMaterial;
                if (!$rm) continue;

                $requiredForThis = ($item->quantity / $recipe->yield_quantity) * $demandQty;

                if (isset($totalRequirements[$rm->id])) {
                    $totalRequirements[$rm->id]['required_qty'] += $requiredForThis;
                } else {
                    // Get stock for selected branch or sum
                    $currentStock = 0;
                    if ($branchCode && isset($externalStock[$branchCode][$rm->item_code])) {
                        $currentStock = $externalStock[$branchCode][$rm->item_code];
                    } else {
                        foreach ($externalStock as $bCode => $items) {
                            $currentStock += ($items[$rm->item_code] ?? 0);
                        }
                    }

                    $totalRequirements[$rm->id] = [
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
                    'pack_name' => $product->pack_name,
                    'quantity' => (float)$input['demand_qty']
                ];
            }
        }
        return $summary;
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
