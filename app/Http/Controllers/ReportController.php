<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\StockLedger;
use App\Models\Product;
use App\Models\Branch;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function stockLedger(Request $request)
    {
        $query = StockLedger::with('product')->orderByDesc('created_at');

        if ($request->has('product_id') && $request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        // Filter the ledger by permitted products
        $user = Auth::user();
        if ($user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $permittedRMTypes = $user->getPermittedRMTypes();
            
            $query->whereHas('product', function($q) use ($permittedTypeIds, $permittedRMTypes) {
                $q->whereIn('product_type_id', $permittedTypeIds)
                  ->where(function($sq) use ($permittedRMTypes) {
                      $sq->whereIn('rm_type', $permittedRMTypes)
                        ->orWhereNull('rm_type')
                        ->orWhere('rm_type', '');
                  });
            });
        }

        $ledger = $query->limit(100)->get();
        
        $productsQuery = Product::orderBy('name');
        if ($user->role !== 'admin') {
            $this->applyTypeFilters($productsQuery);
        }
        $products = $productsQuery->get();
        
        return view('reports.stock_ledger', compact('ledger', 'products'));
    }

    public function liveStock(Request $request)
    {
        if ($request->has('refresh')) {
            Cache::forget('external_stock_data_grouped');
            return redirect()->route('reports.live-stock', $request->except('refresh'))
                             ->with('success', 'Live stock data synced successfully from Algebra ERP!');
        }

        $branches = Branch::orderBy('sort_order')->orderBy('code')->get();
        $types = ProductType::orderBy('type_name')->get();
        $rmTypes = Cache::remember('distinct_rm_types', 3600, function() {
            return Product::whereNotNull('rm_type')->where('rm_type', '!=', '')->distinct()->pluck('rm_type');
        });
        $displayUnit = $request->get('display_unit', 'unit'); // 'unit' or 'kg'
        $perPage = $request->get('per_page', 20);

        $query = Product::with('type')->orderBy('name');

        // Apply Access Control
        $this->applyTypeFilters($query);

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('item_code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('type_id')) {
            $query->where('product_type_id', $request->type_id);
        }

        if ($request->filled('rm_type')) {
            $query->where('rm_type', $request->rm_type);
        }

        // Selective Products Filter
        if ($request->filled('product_ids')) {
            $query->whereIn('id', $request->input('product_ids'));
        }

        if ($perPage === 'all') {
            $products = $query->get();
        } else {
            $products = $query->paginate($perPage)->withQueryString();
        }

        $externalStock = $this->getExternalStock();

        $reportData = [];
        foreach ($products as $product) {
            $branchStocks = [];
            $totalQty = 0;

            foreach ($branches as $branch) {
                $qty = $externalStock[$branch->code][$product->item_code] ?? 0;
                $unitPerBox = (float)($product->unit_box ?: 1);
                $weightPerUnit = (float)($product->weight_unit ?: 1);
                
                $displayQty = ($displayUnit === 'kg') ? ($qty * $product->weight_multiplier) : $qty;

                $branchStocks[$branch->code] = [
                    'qty' => $displayQty,
                    'boxes' => $qty / $unitPerBox
                ];
                $totalQty += $qty;
            }

            // Apply Stock Filter: Ignore Zero Stock
            if ($request->get('stock_filter') === 'ignore_zero' && $totalQty <= 0) {
                continue;
            }

            $unitPerBox = (float)($product->unit_box ?: 1);
            
            $totalDisplayQty = ($displayUnit === 'kg') ? ($totalQty * $product->weight_multiplier) : $totalQty;

            $reportData[] = [
                'product' => $product,
                'branch_stocks' => $branchStocks,
                'total_qty' => $totalDisplayQty,
                'total_boxes' => $totalQty / $unitPerBox
            ];
        }

        // All products for the multi-select picker (unfiltered, just access-controlled)
        $allProductsQuery = Product::orderBy('name')->select('id', 'name', 'item_code', 'pack_name', 'product_type_id');
        $this->applyTypeFilters($allProductsQuery);
        $allProducts = $allProductsQuery->get();

        return view('reports.live_stock', compact('reportData', 'products', 'branches', 'types', 'rmTypes', 'displayUnit', 'allProducts'));
    }

    public function exportLiveStockExcel(Request $request)
    {
        if (!auth()->user()->hasPermission('reports', 'excel')) {
            abort(403, 'Unauthorized action.');
        }

        $displayUnit = $request->get('display_unit', 'unit');
        $externalStock = $this->getExternalStock();
        $branches = Branch::orderBy('sort_order')->orderBy('code')->get();

        $query = Product::with('type')->orderBy('name');
        
        // Apply Access Control
        $this->applyTypeFilters($query);

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('item_code', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->filled('type_id')) {
            $query->where('product_type_id', $request->type_id);
        }
        if ($request->filled('rm_type')) {
            $query->where('rm_type', $request->rm_type);
        }
        if ($request->filled('product_ids')) {
            $query->whereIn('id', $request->input('product_ids'));
        }

        $products = $query->get();

        if ($request->get('stock_filter') === 'ignore_zero') {
            $products = $products->filter(function($product) use ($externalStock) {
                $total = 0;
                foreach ($externalStock as $branchStock) {
                    $total += ($branchStock[$product->item_code] ?? 0);
                }
                return $total > 0;
            });
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\LiveStockExport($products, $branches, $externalStock, $displayUnit), 
            "Live_Stock_Report_" . now()->format('Y-m-d_His') . ".xlsx"
        );
    }

    public function exportLiveStockPdf(Request $request)
    {
        if (!auth()->user()->hasPermission('reports', 'pdf')) {
            abort(403, 'Unauthorized action.');
        }

        $displayUnit = $request->get('display_unit', 'unit');
        $externalStock = $this->getExternalStock();
        $branches = Branch::orderBy('sort_order')->orderBy('code')->get();

        $query = Product::with('type')->orderBy('name');

        // Apply Access Control
        $this->applyTypeFilters($query);

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('item_code', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->filled('type_id')) {
            $query->where('product_type_id', $request->type_id);
        }
        if ($request->filled('rm_type')) {
            $query->where('rm_type', $request->rm_type);
        }
        if ($request->filled('product_ids')) {
            $query->whereIn('id', $request->input('product_ids'));
        }

        $products = $query->get();
        
        $reportData = [];
        foreach ($products as $product) {
            $branchStocks = [];
            $totalQty = 0;
            foreach ($branches as $branch) {
                $qty = $externalStock[$branch->code][$product->item_code] ?? 0;
                $unitPerBox = (float)($product->unit_box ?: 1);
                $weightPerUnit = (float)($product->weight_unit ?: 1);
                $displayQty = ($displayUnit === 'kg') ? ($qty * $product->weight_multiplier) : $qty;
                $branchStocks[$branch->code] = ['qty' => $displayQty, 'boxes' => $qty / $unitPerBox];
                $totalQty += $qty;
            }

            // Apply Stock Filter: Ignore Zero Stock
            if ($request->get('stock_filter') === 'ignore_zero' && $totalQty <= 0) {
                continue;
            }

            $weightPerUnit = (float)($product->weight_unit ?: 1);
            $totalDisplayQty = ($displayUnit === 'kg') ? ($totalQty * $product->weight_multiplier) : $totalQty;
            $reportData[] = [
                'product' => $product,
                'branch_stocks' => $branchStocks,
                'total_qty' => $totalDisplayQty,
                'total_boxes' => $totalQty / ($product->unit_box ?: 1)
            ];
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.live_stock_pdf', compact('reportData', 'branches', 'displayUnit'))
                  ->setPaper('a4', 'landscape');
        
        return $pdf->download("Live_Stock_Report_" . now()->format('Y-m-d_His') . ".pdf");
    }

    private function getExternalStock()
    {
        return Cache::remember('external_stock_data_grouped', 3600, function () {
            try {
                $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
                $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');
                $branch  = AppSetting::get('inventory_api_branch', 'ALL');
                $item    = AppSetting::get('inventory_api_item', 'ALL');

                Log::info('External Stock API Call', ['url' => $baseUrl, 'branch' => $branch]);

                $response = Http::withoutVerifying()
                    ->timeout(60)
                    ->connectTimeout(15)
                    ->post("{$baseUrl}/ProductWiseInventory", [
                        "apikey" => $apiKey,
                        "Branch" => $branch,
                        "Item"   => $item,
                    ]);

                Log::info('External Stock API Response', ['status' => $response->status()]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['response']) && $data['response'] === 'success' && isset($data['resultdata'])) {
                        $stockMap = [];
                        foreach ($data['resultdata'] as $item) {
                            $bCode = $item['Branch_Code'];
                            $iCode = $item['User_Code'];
                            $stockMap[$bCode][$iCode] = (float)$item['ClosingQty'];
                        }
                        Log::info('External Stock loaded', ['items' => count($stockMap)]);
                        return $stockMap;
                    }
                    Log::warning('External Stock API bad response', ['body' => $response->body()]);
                } else {
                    Log::error('External Stock API HTTP Error', ['status' => $response->status(), 'body' => $response->body()]);
                }
            } catch (\Exception $e) {
                Log::error('External Stock API Exception: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
            return [];
        });
    }

    protected function applyTypeFilters($query)
    {
        $user = Auth::user();
        if ($user->role === 'admin') {
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

    public function purchaseReport(Request $request)
    {
        // --- Default values from settings (same as Costing API section) ---
        $baseUrl  = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
        $apiKey   = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');

        // FY auto-dates
        $now      = now();
        $fyStart  = $now->month >= 4
            ? $now->year . '-04-01'
            : ($now->year - 1) . '-04-01';
        $fyEnd    = $now->month >= 4
            ? ($now->year + 1) . '-03-31'
            : $now->year . '-03-31';

        $defaults = [
            'from_date' => AppSetting::get('costing_api_from_date', $fyStart) ?: $fyStart,
            'to_date'   => AppSetting::get('costing_api_to_date',   $fyEnd)   ?: $fyEnd,
            'account'   => AppSetting::get('costing_api_account', 'all'),
            'item'      => AppSetting::get('costing_api_item', 'all'),
            'branch'    => AppSetting::get('costing_api_branch', 'all'),
        ];

        // If no filters submitted, just show empty page with defaults
        if (!$request->anyFilled(['from_date', 'to_date', 'account', 'item', 'branch'])) {
            return view('reports.purchase_report', compact('defaults'));
        }

        // --- Build request payload ---
        $payload = [
            'apikey'   => $apiKey,
            'FromDate' => $request->input('from_date', $defaults['from_date']),
            'ToDate'   => $request->input('to_date',   $defaults['to_date']),
            'Account'  => $request->input('account',   $defaults['account']),
            'Item'     => $request->input('item',      $defaults['item']),
            'Branch'   => $request->input('branch',    $defaults['branch']),
        ];

        $reportData = [];
        $error      = null;

        try {
            Log::info('Purchase Report API Call', ['url' => $baseUrl . '/LogicPurchaseRegisterDetail', 'payload' => $payload]);

            $response = Http::withoutVerifying()
                ->timeout(60)
                ->connectTimeout(15)
                ->post("{$baseUrl}/LogicPurchaseRegisterDetail", $payload);

            Log::info('Purchase Report API Response', ['status' => $response->status()]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['response']) && $data['response'] === 'success' && isset($data['resultdata'])) {
                    $reportData = $data['resultdata'];
                } else {
                    $error = 'API returned: ' . ($data['message'] ?? $data['response'] ?? 'Unexpected response');
                    Log::warning('Purchase Report API bad response', ['body' => $response->body()]);
                }
            } else {
                $error = 'HTTP ' . $response->status() . ': ' . $response->body();
                Log::error('Purchase Report API HTTP Error', ['status' => $response->status()]);
            }
        } catch (\Exception $e) {
            $error = 'Connection error: ' . $e->getMessage();
            Log::error('Purchase Report API Exception: ' . $e->getMessage());
        }

        return view('reports.purchase_report', compact('reportData', 'defaults', 'error'));
    }
}

