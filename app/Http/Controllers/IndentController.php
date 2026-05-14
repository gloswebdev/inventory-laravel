<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\Branch;
use App\Models\Indent;
use App\Models\IndentItem;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IndentController extends Controller
{
    /**
     * Indent Manager Dashboard (Bulk Entry & History)
     */
    public function index(Request $request)
    {
        $productsQuery = Product::whereIn('product_type_id', [6, 7])->orderBy('name');
        $this->applyTypeFilters($productsQuery);
        $finishedGoods = $productsQuery->get();
        
        $branches = Branch::orderBy('code')->get();
        
        $query = Indent::with(['items.product', 'user']);

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

        $history = $query->orderByDesc('created_at')->limit(50)->get();
        $users = \App\Models\User::orderBy('name')->get();
        $productTypes = \App\Models\ProductType::orderBy('type_name')->get();

        return view('indent.index', compact('finishedGoods', 'branches', 'history', 'users', 'productTypes'));
    }

    /**
     * Store New Indent (Bulk)
     */
    public function store(Request $request)
    {
        $request->validate([
            'branch_code' => 'required',
            'indent_date' => 'required|date',
            'products' => 'required|array',
        ]);

        $branch = Branch::where('code', $request->branch_code)->first();
        $branchName = $branch ? $branch->name : "Branch " . $request->branch_code;

        $indent = Indent::create([
            'branch_code' => $request->branch_code,
            'branch_name' => $branchName,
            'indent_date' => $request->indent_date,
            'user_id' => auth()->id(),
            'total_boxes' => 0,
        ]);

        $totalBoxes = 0;
        foreach ($request->products as $item) {
            if ($item['demand_qty'] > 0) {
                IndentItem::create([
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

    /**
     * Get Live Stock for a single product
     */
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
        $stock = 0;
        
        if ($branchCode && isset($externalStock[$branchCode][$product->item_code])) {
            $stock = $externalStock[$branchCode][$product->item_code];
        } else {
            foreach ($externalStock as $items) {
                $stock += ($items[$product->item_code] ?? 0);
            }
        }

        $unitPerBox = (float)($product->unit_box ?: 1);
        $stockBoxes = $stock / $unitPerBox;

        return response()->json([
            'success' => true, 
            'stock' => $stock,
            'stock_boxes' => $stockBoxes,
            'uom' => $product->uom,
            'unit_box' => $product->unit_box,
            'weight_unit' => $product->weight_unit,
            'stock_kg' => $stock * $product->weight_multiplier
        ]);
    }

    /**
     * Get Bulk Stock for all products in view
     */
    public function getBulkStock(Request $request)
    {
        $branchCode = $request->get('branch_code');
        
        $productsQuery = Product::whereIn('product_type_id', [6, 7])->orderBy('name');
        $this->applyTypeFilters($productsQuery);
        $products = $productsQuery->get();
        $externalStock = $this->getExternalStock();
        
        $results = [];
        foreach ($products as $product) {
            $stock = 0;
            if ((int)$branchCode && isset($externalStock[(int)$branchCode][$product->item_code])) {
                $stock = $externalStock[(int)$branchCode][$product->item_code];
            } else {
                foreach ($externalStock as $items) {
                    $stock += ($items[$product->item_code] ?? 0);
                }
            }

            $unitPerBox = (float)($product->unit_box ?: 1);
            $stockBoxes = $stock / $unitPerBox;
            $stockKg = $stock * $product->weight_multiplier;
            
            $results[$product->id] = [
                'stock' => $stockKg,
                'stock_boxes' => $stockBoxes
            ];
        }

        return response()->json(['success' => true, 'stocks' => $results]);
    }

    public function show(Indent $indent)
    {
        return response()->json([
            'success' => true,
            'indent' => $indent->load('items.product', 'user')
        ]);
    }

    public function print(Indent $indent)
    {
        $indent->load('items.product', 'user');
        return view('indent.print', compact('indent'));
    }

    public function exportExcel(Indent $indent)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\IndentExport($indent), 
            "Indent_{$indent->branch_code}_{$indent->indent_date}.xlsx"
        );
    }

    public function exportPdf(Indent $indent)
    {
        $indent->load('items.product', 'user');
        $pdf = Pdf::loadView('indent.indent_pdf', compact('indent'));
        return $pdf->download("Indent_{$indent->branch_code}_{$indent->indent_date}.pdf");
    }

    public function process(Indent $indent)
    {
        $indent->load('items.product', 'user');
        $branches = Branch::orderBy('sort_order')->orderBy('code')->get();
        $branchStocks = $this->getBranchStocksForIndent($indent, $branches);

        return view('indent.process', compact('indent', 'branches', 'branchStocks'));
    }

    public function processList(Request $request)
    {
        $query = Indent::with(['user'])->withCount('items');

        if ($request->filled('from_date')) $query->whereDate('indent_date', '>=', $request->from_date);
        if ($request->filled('to_date')) $query->whereDate('indent_date', '<=', $request->to_date);
        if ($request->filled('branch_code')) $query->where('branch_code', $request->branch_code);
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('status')) $query->where('status', $request->status);

        $history = $query->orderByDesc('created_at')->get();
        $branches = Branch::orderBy('sort_order')->orderBy('code')->get();
        $users = \App\Models\User::orderBy('name')->get();

        return view('indent.list', compact('history', 'branches', 'users'));
    }

    public function exportProcessExcel(Indent $indent)
    {
        $indent->load('items.product', 'user');
        $branches = Branch::orderBy('sort_order')->orderBy('code')->get();
        $branchStocks = $this->getBranchStocksForIndent($indent, $branches);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\IndentProcessExport($indent, $branches, $branchStocks), 
            "Process_Matrix_{$indent->branch_code}_{$indent->indent_date}.xlsx"
        );
    }
    public function updateCompletion(Request $request, Indent $indent)
    {
        $quantities = $request->input('completed_qty', []);
        $totalAsked = 0; $totalCompleted = 0; $anyCompleted = false;

        foreach ($indent->items as $item) {
            $compQty = $quantities[$item->id] ?? 0;
            $item->update(['completed_qty' => $compQty]);
            $totalAsked += $item->final_qty_box;
            $totalCompleted += $compQty;
            if ($compQty > 0) $anyCompleted = true;
        }

        $status = ($totalCompleted >= $totalAsked && $totalAsked > 0) ? 'completed' : ($anyCompleted ? 'partly completed' : 'pending');
        $indent->update(['status' => $status]);

        return redirect()->back()->with('success', 'Status updated to ' . strtoupper($status));
    }

    public function destroy(Indent $indent)
    {
        $indent->items()->delete();
        $indent->delete();
        return response()->json(['success' => true, 'message' => 'Indent deleted successfully!']);
    }

    public function clone(Indent $indent)
    {
        try {
            return DB::transaction(function () use ($indent) {
                $newIndent = $indent->replicate();
                $newIndent->status = 'pending';
                $newIndent->created_at = now();
                $newIndent->save();

                foreach ($indent->items as $item) {
                    $newItem = $item->replicate();
                    $newItem->indent_id = $newIndent->id;
                    $newItem->completed_qty = 0;
                    $newItem->save();
                }

                return response()->json(['success' => true, 'message' => 'Indent cloned successfully!', 'id' => $newIndent->id]);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error cloning: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, Indent $indent)
    {
        $request->validate([
            'branch_code' => 'sometimes|string',
            'indent_date' => 'sometimes|date',
            'products' => 'required|array|min:1',
        ]);

        try {
            return DB::transaction(function () use ($request, $indent) {
                if ($request->has('indent_date')) $indent->indent_date = $request->indent_date;
                if ($request->has('branch_code')) {
                    $branch = Branch::where('code', $request->branch_code)->first();
                    $indent->branch_code = $request->branch_code;
                    $indent->branch_name = $branch ? $branch->name : 'Consolidated';
                }
                
                $indent->items()->delete();
                $totalBoxes = 0;

                foreach ($request->products as $pData) {
                    $product = Product::find($pData['id']);
                    if (!$product) continue;

                    \App\Models\IndentItem::create([
                        'indent_id' => $indent->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'demand_qty' => $pData['demand_qty'],
                        'demand_unit' => $pData['unit'] ?? 'box',
                        'final_qty_box' => $pData['final_qty_box'] ?? $pData['demand_qty'],
                        'stock_box' => $pData['stock_box'] ?? 0,
                        'stock_kg' => $pData['stock_kg'] ?? 0,
                    ]);
                    $totalBoxes += ($pData['final_qty_box'] ?? $pData['demand_qty']);
                }

                $indent->total_boxes = $totalBoxes;
                $indent->save();

                return response()->json(['success' => true, 'message' => 'Indent updated successfully!']);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating: ' . $e->getMessage()]);
        }
    }

    private function getBranchStocksForIndent($indent, $branches)
    {
        $externalStock = $this->getExternalStock();
        $branchStocks = [];
        foreach ($indent->items as $item) {
            $p = $item->product;
            if (!$p) continue;
            foreach ($branches as $branch) {
                $rawStock = $externalStock[$branch->code][$p->item_code] ?? 0;
                $branchStocks[$p->id][$branch->code] = $rawStock / ($p->unit_box ?: 1);
            }
        }
        return $branchStocks;
    }

    private function getExternalStock()
    {
        return \Illuminate\Support\Facades\Cache::remember('external_stock_data_grouped', 3600, function () {
            try {
                $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
                $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');
                $branch  = AppSetting::get('inventory_api_branch', 'ALL');
                $item    = AppSetting::get('inventory_api_item', 'ALL');

                $response = \Illuminate\Support\Facades\Http::timeout(30)->post("{$baseUrl}/ProductWiseInventory", [
                    "apikey" => $apiKey, "Branch" => $branch, "Item" => $item
                ]);
                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['response']) && $data['response'] === 'success' && isset($data['resultdata'])) {
                        $stockMap = [];
                        foreach ($data['resultdata'] as $item) {
                            $stockMap[(int)$item['Branch_Code']][$item['User_Code']] = (float)$item['ClosingQty'];
                        }
                        return $stockMap;
                    }
                }
            } catch (\Exception $e) { \Illuminate\Support\Facades\Log::error('Stock API Error: ' . $e->getMessage()); }
            return [];
        });
    }

    protected function applyTypeFilters($query)
    {
        $user = Auth::user();
        if (!$user || $user->role === 'admin') return $query;
        $permittedTypeIds = $user->getPermittedProductTypeIds();
        $permittedRMTypes = $user->getPermittedRMTypes();
        return $query->whereIn('product_type_id', $permittedTypeIds)
            ->where(function ($q) use ($permittedRMTypes) {
                $q->whereIn('rm_type', $permittedRMTypes)->orWhereNull('rm_type')->orWhere('rm_type', '');
            });
    }
}
