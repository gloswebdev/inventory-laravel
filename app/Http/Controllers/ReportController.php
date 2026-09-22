<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\StockLedger;
use App\Models\Product;
use App\Models\Branch;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        // --- Default values ---
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
            'account'   => 'all',
            'item'      => 'all',
            'branch'    => 'all',
        ];

        $fromDate = $request->input('from_date', $defaults['from_date']);
        $toDate   = $request->input('to_date',   $defaults['to_date']);
        $account  = $request->input('account',   $defaults['account']);
        $item     = $request->input('item',      $defaults['item']);
        $branch   = $request->input('branch',    $defaults['branch']);
        $rmType   = $request->input('rm_type',   '');
        $types    = $request->input('types',     '');

        // Only call API when form is submitted (has at least one query param)
        if (!$request->hasAny(['from_date', 'to_date', 'account', 'item', 'branch', 'rm_type', 'types'])) {
            return view('reports.purchase_report', compact('defaults', 'fromDate', 'toDate', 'account', 'item', 'branch', 'rmType', 'types'));
        }

        // --- Build request payload ---
        // NOTE: Account & Item are filtered PHP-side after fetch (API only accepts 'all' reliably)
        $payload = [
            'apikey'   => $apiKey,
            'FromDate' => $fromDate,
            'ToDate'   => $toDate,
            'Account'  => 'all',
            'Item'     => 'all',
            'Branch'   => $branch,
        ];

        Log::info('Purchase Report API Call', ['url' => $baseUrl . '/LogicPurchaseRegisterDetail', 'payload' => $payload]);

        $reportData     = [];
        $error          = null;
        $rmTypeOptions  = [];
        $typesOptions   = [];
        $accountOptions = [];
        $itemOptions    = [];

        try {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->connectTimeout(15)
                ->post("{$baseUrl}/LogicPurchaseRegisterDetail", $payload);

            Log::info('Purchase Report API Response', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                // Handle both success formats
                if (isset($data['response']) && $data['response'] === 'success' && isset($data['resultdata'])) {
                    $reportData = $data['resultdata'];
                } elseif (is_array($data) && !isset($data['response'])) {
                    // Some APIs return array directly
                    $reportData = $data;
                } else {
                    $msg   = $data['message'] ?? $data['response'] ?? 'Unknown';
                    $error = "API returned: {$msg}";
                    Log::warning('Purchase Report API non-success', ['data' => $data]);
                }

                // Extract dropdown options from FULL data (before filters)
                $rmTypeOptions = collect($reportData)
                    ->pluck('GroupName4')
                    ->map(fn($v) => trim($v))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();

                $typesOptions = collect($reportData)
                    ->pluck('GroupName5')
                    ->map(fn($v) => trim($v))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();

                $accountOptions = collect($reportData)
                    ->pluck('SupplierName')
                    ->map(fn($v) => trim($v))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();

                $itemOptions = collect($reportData)
                    ->map(fn($r) => ['code' => trim($r['User_Code'] ?? ''), 'name' => trim($r['Item_Hd_Name'] ?? '')])
                    ->filter(fn($r) => $r['name'] !== '')
                    ->unique('name')
                    ->sortBy('name')
                    ->values()
                    ->toArray();

                // Apply server-side GroupName4 (Rm Type) filter
                if (!empty($rmType) && $rmType !== 'all') {
                    $reportData = array_values(array_filter($reportData, function($row) use ($rmType) {
                        return trim($row['GroupName4'] ?? '') === trim($rmType);
                    }));
                }

                // Apply server-side GroupName5 (Types) filter
                if (!empty($types) && $types !== 'all') {
                    $reportData = array_values(array_filter($reportData, function($row) use ($types) {
                        return trim($row['GroupName5'] ?? '') === trim($types);
                    }));
                }

                // Apply server-side Account (SupplierName) filter
                if (!empty($account) && $account !== 'all') {
                    $reportData = array_values(array_filter($reportData, function($row) use ($account) {
                        return trim($row['SupplierName'] ?? '') === trim($account);
                    }));
                }

                // Apply server-side Item (Item_Hd_Name) filter
                if (!empty($item) && $item !== 'all') {
                    $reportData = array_values(array_filter($reportData, function($row) use ($item) {
                        return trim($row['Item_Hd_Name'] ?? '') === trim($item);
                    }));
                }
            } else {
                $error = 'HTTP ' . $response->status();
                Log::error('Purchase Report API HTTP Error', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Exception $e) {
            $error = 'Connection error: ' . $e->getMessage();
            Log::error('Purchase Report API Exception: ' . $e->getMessage());
        }

        return view('reports.purchase_report', compact(
            'reportData', 'defaults', 'error',
            'fromDate', 'toDate', 'account', 'item', 'branch',
            'rmType', 'types', 'rmTypeOptions', 'typesOptions',
            'accountOptions', 'itemOptions'
        ));
    }

    public function collectionReport(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && !$user->hasPermission('collection_report', 'view') && !$user->hasPermission('mobile_collection', 'view')) {
            abort(403, 'Unauthorized access to Collection Report.');
        }

        $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
        $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');

        // Fetch PartyMaster (cached 2 hours) first — needed for filter dropdowns too
        if ($request->has('refresh_party_master')) {
            Cache::forget('party_master_map');
        }
        $partyMasterMap = $this->getPartyMasterMap($baseUrl, $apiKey);

        // Build branch & agent options from PartyMaster
        $branchOptions = collect($partyMasterMap)
            ->pluck('BranchName')->filter(fn($v) => $v && $v !== '—')
            ->unique()->sort()->values()->toArray();
        $agentOptions = collect($partyMasterMap)
            ->pluck('AgentName')->filter(fn($v) => $v && $v !== '—')
            ->unique()->sort()->values()->toArray();

        // --- Month Filter options (Past 12 months + Next 2 months) ---
        $monthOptions = [
            'this_week' => 'This Week',
            'last_week' => 'Last Week',
        ];
        $currentYm = date('Y-m');
        for ($i = -12; $i <= 2; $i++) {
            $time = strtotime("$i month");
            $ym = date('Y-m', $time);
            $label = date('F Y', $time);
            if ($ym === $currentYm) {
                $label .= ' (Current Month)';
            }
            $monthOptions[$ym] = $label;
        }

        // --- Filters ---
        $monthFilter = $request->input('month_filter', $currentYm);

        // Date calculations for current and comparison periods
        $prevFromDate = null;
        $prevToDate = null;
        $comparisonLabel = 'Previous Period';

        if ($monthFilter === 'this_week') {
            $fromDate = date('Y-m-d', strtotime('monday this week'));
            $toDate   = date('Y-m-d', strtotime('sunday this week'));
            $prevFromDate = date('Y-m-d', strtotime('monday last week'));
            $prevToDate   = date('Y-m-d', strtotime('sunday last week'));
            $comparisonLabel = 'vs Last Week';
        } elseif ($monthFilter === 'last_week') {
            $fromDate = date('Y-m-d', strtotime('monday last week'));
            $toDate   = date('Y-m-d', strtotime('sunday last week'));
            $prevFromDate = date('Y-m-d', strtotime('monday -2 weeks'));
            $prevToDate   = date('Y-m-d', strtotime('sunday -2 weeks'));
            $comparisonLabel = 'vs Week Before';
        } elseif ($monthFilter && $monthFilter !== 'custom') {
            if (preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
                $fromDate = $monthFilter . '-01';
                $toDate   = date('Y-m-t', strtotime($fromDate));
                $prevFromDate = date('Y-m-d', strtotime("$fromDate -1 month"));
                $prevToDate   = date('Y-m-t', strtotime($prevFromDate));
                $comparisonLabel = 'vs Last Month';
            } else {
                $fromDate = date('Y-m-01');
                $toDate   = date('Y-m-t');
                $prevFromDate = date('Y-m-d', strtotime("$fromDate -1 month"));
                $prevToDate   = date('Y-m-t', strtotime($prevFromDate));
                $comparisonLabel = 'vs Last Month';
            }
        } else {
            $fromDate = $request->input('from_date', date('Y-m-01'));
            $toDate   = $request->input('to_date',   date('Y-m-t'));
            
            $diff = abs(strtotime($toDate) - strtotime($fromDate));
            $prevFromDate = date('Y-m-d', strtotime($fromDate) - $diff - 86400);
            $prevToDate   = date('Y-m-d', strtotime($fromDate) - 86400);
            $comparisonLabel = 'vs Prior Period';
        }

        $finYearDefault = AppSetting::get('collection_api_fin_year', '2627') ?: '2627';
        $partyCodeDefault = AppSetting::get('collection_api_party_code', 'ALL') ?: 'ALL';

        $finYear = $request->input('fin_year', $finYearDefault);

        // Support array (multiple select) or string for branch_filter
        $branchFilter = $request->input('branch_filter', []);
        if (is_string($branchFilter)) {
            $branchFilter = $branchFilter ? [$branchFilter] : [];
        }
        
        $agentFilter   = $request->input('agent_filter', '');
        $selectedTeams = $request->input('teams', []); // Selected team IDs

        // Load Teams from Database
        $dbTeams = \App\Models\Team::all();

        // If a team is clicked/active, accumulate agents and branches from the selected teams (and their child teams)
        $teamAgents = [];
        $teamBranches = [];
        if (!empty($selectedTeams)) {
            $activeTeamsData = $dbTeams->whereIn('id', $selectedTeams);
            foreach ($activeTeamsData as $team) {
                $teamAgents = array_merge($teamAgents, $team->getEffectiveAgents($dbTeams));
                $teamBranches = array_merge($teamBranches, $team->getEffectiveBranches($dbTeams));
            }
            $teamAgents   = array_unique(array_filter($teamAgents));
            $teamBranches = array_unique(array_filter($teamBranches));
        }

        $defaults = [
            'fin_year'     => $finYearDefault,
            'month_filter' => $currentYm,
            'from_date'    => date('Y-m-01'),
            'to_date'      => date('Y-m-t'),
        ];

        $payload = [
            'apikey'    => $apiKey,
            'FinYear'   => $finYear,
            'PartyCode' => $partyCodeDefault,
            'FromDate'  => $fromDate,
            'ToDate'    => $toDate,
        ];

        Log::info('Collection Report API Call', ['url' => $baseUrl . '/LogicPartyCollection', 'payload' => $payload]);

        $reportData = [];
        $prevReportData = [];
        $error      = null;

        try {
            // Fetch current period data
            $response = Http::withoutVerifying()
                ->timeout(90)
                ->connectTimeout(20)
                ->post("{$baseUrl}/LogicPartyCollection", $payload);

            Log::info('Collection Report API Response', ['status' => $response->status(), 'body' => substr($response->body(), 0, 300)]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['response']) && $data['response'] === 'success' && isset($data['resultdata'])) {
                    $reportData = $data['resultdata'];
                } elseif (is_array($data) && !isset($data['response'])) {
                    $reportData = $data;
                } else {
                    $error = "API: " . ($data['message'] ?? $data['response'] ?? 'Unknown error');
                }
            } else {
                $error = 'HTTP ' . $response->status();
            }

            // Fetch previous period data
            if ($prevFromDate && $prevToDate) {
                $prevPayload = $payload;
                $prevPayload['FromDate'] = $prevFromDate;
                $prevPayload['ToDate']   = $prevToDate;
                $prevResponse = Http::withoutVerifying()
                    ->timeout(90)
                    ->connectTimeout(20)
                    ->post("{$baseUrl}/LogicPartyCollection", $prevPayload);

                if ($prevResponse->successful()) {
                    $prevData = $prevResponse->json();
                    if (isset($prevData['response']) && $prevData['response'] === 'success' && isset($prevData['resultdata'])) {
                        $prevReportData = $prevData['resultdata'];
                    } elseif (is_array($prevData) && !isset($prevData['response'])) {
                        $prevReportData = $prevData;
                    }
                }
            }
        } catch (\Exception $e) {
            $error = 'Connection error: ' . $e->getMessage();
            Log::error('Collection Report API Exception: ' . $e->getMessage());
        }

        // ── Remove DEPOSIT rows ──
        $reportData = array_values(array_filter($reportData, function ($row) {
            foreach ($row as $v) {
                if (is_string($v) && stripos($v, 'DEPOSIT') !== false) return false;
            }
            return true;
        }));

        $prevReportData = array_values(array_filter($prevReportData, function ($row) {
            foreach ($row as $v) {
                if (is_string($v) && stripos($v, 'DEPOSIT') !== false) return false;
            }
            return true;
        }));

        // ── Merge PartyMaster and Apply Filters (Branch, Team, Agent) ──
        $processReportRows = function($rawData) use ($partyMasterMap, $branchFilter, $teamAgents, $agentFilter) {
            $mapped = array_map(function ($row) use ($partyMasterMap) {
                // Strictly align the Collection API's "act_code" and "act_name"
                $colPartyCode = trim(
                    $row['act_code']   ?? $row['Act_Code']   ?? $row['ActCode']   ??
                    $row['AC_Code']    ?? $row['Ac_Code']    ??
                    $row['PartyCode']  ?? $row['Party_Code']  ?? ''
                );
                
                $masterInfo = null;
                if ($colPartyCode && isset($partyMasterMap[$colPartyCode])) {
                    $masterInfo = $partyMasterMap[$colPartyCode];
                }

                // Fallback to name match (check act_name)
                if (!$masterInfo) {
                    $rowName = strtolower(trim(
                        $row['act_name']   ?? $row['AC_Name']    ?? $row['AcName']    ?? 
                        $row['PartyName']  ?? $row['Party_Name']  ?? ''
                    ));
                    foreach ($partyMasterMap as $info) {
                        if (strtolower(trim($info['PartyName'] ?? '')) === $rowName) {
                            $masterInfo = $info; 
                            break;
                        }
                    }
                }

                $row['_AgentName']  = $masterInfo['AgentName']  ?? '—';
                $row['_AgentCode']  = $masterInfo['AgentCode']  ?? '—';
                $row['_BranchName'] = $masterInfo['BranchName'] ?? '—';
                $row['_TownName']   = $masterInfo['TownName']   ?? '—';
                
                // Override raw names/codes for rendering consistency
                $row['PartyName']   = $row['act_name'] ?? $row['PartyName'] ?? ($masterInfo['PartyName'] ?? '—');
                $row['ActCode']     = $colPartyCode;
                
                return $row;
            }, $rawData);

            // ── Apply Branch filter (handles array of selected branches) ──
            if (!empty($branchFilter)) {
                $mapped = array_values(array_filter($mapped, function($r) use ($branchFilter) {
                    return in_array(trim($r['_BranchName'] ?? ''), $branchFilter);
                }));
            }

            // ── Apply Team / Agent Filter ──
            if (!empty($teamAgents)) {
                $mapped = array_values(array_filter($mapped, function($r) use ($teamAgents) {
                    return in_array(trim($r['_AgentName'] ?? ''), $teamAgents);
                }));
            }

            // ── Apply Agent filter ──
            if (!empty($agentFilter)) {
                $mapped = array_values(array_filter($mapped,
                    fn($r) => trim($r['_AgentName'] ?? '') === trim($agentFilter)
                ));
            }

            return $mapped;
        };

        $reportData = $processReportRows($reportData);
        $prevReportData = $processReportRows($prevReportData);

        // ── Detect amount fields (smart auto-detection) ──
        $firstRow  = $reportData[0] ?? [];

        // Log actual keys so we can see what the API returns
        Log::info('Collection API first row keys', ['keys' => array_keys($firstRow), 'sample' => $firstRow]);

        // All possible collection/credit field names
        $amtFields = [
            'Collection_Amount','SL_Amount','Collection_Amt','CollectionAmt',
            'BL_Amount','Bl_Amount','BLAmount','bl_amount',
            'Credit_Amount','CreditAmt','Credit_Amt','Credit','Cr','CrAmt',
            'Amount','Amt','Net_Amount','NetAmt','Net_Amt',
            'TotalAmt','Total_Amount','Total',
        ];
        $crField = collect($amtFields)->first(fn($k) => array_key_exists($k, $firstRow));

        // If still not found → auto-detect: pick first numeric field that looks like an amount
        if (!$crField) {
            $skipKeys = ['_AgentName','_BranchName','_TownName','ActCode','AC_Code','Ac_Code',
                         'Act_Code','PartyCode','Fin_Year','VouchNo','Vouch_No'];
            foreach ($firstRow as $key => $val) {
                if (str_starts_with($key, '_')) continue;
                if (in_array($key, $skipKeys)) continue;
                $clean = str_replace([',',' '], '', (string)$val);
                if (is_numeric($clean) && (float)$clean >= 0) {
                    // Prefer fields whose name contains 'col','amt','amount','credit','cr','total','bl'
                    $keyLower = strtolower($key);
                    if (str_contains($keyLower,'col') || str_contains($keyLower,'amt') ||
                        str_contains($keyLower,'amount') || str_contains($keyLower,'credit') ||
                        str_contains($keyLower,'cr') || str_contains($keyLower,'total') ||
                        str_contains($keyLower,'bl')) {
                        $crField = $key;
                        break;
                    }
                }
            }
            // Last resort: any numeric field with value > 0
            if (!$crField) {
                foreach ($firstRow as $key => $val) {
                    if (str_starts_with($key, '_')) continue;
                    $clean = str_replace([',',' '], '', (string)$val);
                    if (is_numeric($clean) && (float)$clean > 0) {
                        $crField = $key;
                        break;
                    }
                }
            }
        }

        $drField  = collect(['Debit','Dr','DrAmt','Debit_Amt','Debit_Amount'])->first(fn($k) => array_key_exists($k, $firstRow));
        Log::info('Collection amount fields detected', ['crField' => $crField, 'drField' => $drField]);

        // Helper to parse amount
        $parseAmt = fn($v) => is_numeric(str_replace([',',' '], '', (string)$v)) ? (float)str_replace(',', '', (string)$v) : 0;

        // ── Apply Zero Collection Filter if requested ──
        if ($request->boolean('hide_zero_collection')) {
            $reportData = array_values(array_filter($reportData, function($r) use ($crField, $parseAmt) {
                return $crField ? ($parseAmt($r[$crField] ?? 0) > 0) : true;
            }));
        }

        // ── Build grouped structure: Team Name -> Agent Name -> [rows] ──
        $grouped = [];
        
        foreach ($reportData as $row) {
            $agentName = $row['_AgentName'] ?: '(No Agent)';
            
            // Find which team this agent belongs to (including effective agents in child teams)
            $matchedTeams = [];
            foreach ($dbTeams as $team) {
                if (in_array($agentName, $team->getEffectiveAgents($dbTeams))) {
                    $matchedTeams[] = $team->name;
                }
            }
            
            // If agent doesn't belong to any team, assign to "Unassigned Agents"
            if (empty($matchedTeams)) {
                $matchedTeams = ['Unassigned Agents'];
            }
            
            foreach ($matchedTeams as $tName) {
                $grouped[$tName][$agentName][] = $row;
            }
        }
        
        ksort($grouped);
        foreach ($grouped as $teamName => &$agents) {
            ksort($agents);
        }
        unset($agents);

        // ── Team-level summaries ──
        $branchSummary = []; // reusing variable name to avoid changing view variables too much, but acts as Team Summary
        foreach ($grouped as $teamName => $agents) {
            $tTotal = 0; $tParties = 0; $tAgents = count($agents);
            foreach ($agents as $agent => $rows) {
                $aTotal = array_sum(array_map(fn($r) => $parseAmt($r[$crField] ?? 0), $rows));
                $tTotal += $aTotal;
                $tParties += count($rows);
            }
            $branchSummary[$teamName] = ['total' => $tTotal, 'parties' => $tParties, 'agents' => $tAgents];
        }

        $grandTotal   = array_sum(array_map(fn($r) => $parseAmt($r[$crField] ?? 0), $reportData));
        $totalParties = count($reportData);
        $totalAgents  = collect($reportData)->pluck('_AgentName')->filter(fn($v)=>$v&&$v!=='—')->unique()->count();

        // ── Compute Previous Period Grand Total & Growth ──
        $prevGrandTotal = 0;
        if (!empty($prevReportData)) {
            $prevGrandTotal = array_sum(array_map(fn($r) => $parseAmt($r[$crField] ?? 0), $prevReportData));
        }

        $momGrowthPercent = 0;
        if ($prevGrandTotal > 0) {
            $momGrowthPercent = round((($grandTotal - $prevGrandTotal) / $prevGrandTotal) * 100, 1);
        } elseif ($grandTotal > 0) {
            $momGrowthPercent = 100.0;
        }

        // Party name key
        $partyNameKey = collect(['AC_Name','AcName','PartyName','Party_Name'])
                          ->first(fn($k) => array_key_exists($k, $firstRow));

        // Load Agent & Team Targets for selected filter date's month
        // We extract YYYY-MM from $fromDate
        $targetMonth = substr($fromDate, 0, 7);
        $agentTargets = \App\Models\AgentTarget::where('target_month', $targetMonth)
            ->get()
            ->pluck('target_amount', 'agent_name')
            ->toArray();

        $teamTargets = \App\Models\TeamTarget::where('target_month', $targetMonth)
            ->get()
            ->pluck('target_amount', 'team_id')
            ->toArray();

        // Compute grand target (sum of all team targets for visible teams)
        $grandTarget = array_sum($teamTargets);

        return view('reports.collection_report', compact(
            'reportData', 'grouped', 'branchSummary',
            'grandTotal', 'grandTarget', 'totalParties', 'totalAgents',
            'error', 'defaults', 'monthFilter', 'monthOptions',
            'finYear', 'fromDate', 'toDate', 'selectedTeams', 'dbTeams',
            'branchFilter', 'agentFilter', 'branchOptions', 'agentOptions',
            'crField', 'drField', 'partyNameKey', 'agentTargets', 'teamTargets',
            'prevGrandTotal', 'momGrowthPercent', 'comparisonLabel', 'prevReportData'
        ));
    }

    /**
     * Fetch & cache PartyMaster from Algebra ERP API.
     * Returns a map keyed by ActCode => [PartyName, AgentName, AgentCode, TownName, BranchName, GroupName]
     */
    private function getPartyMasterMap(string $baseUrl, string $apiKey): array
    {
        return Cache::remember('party_master_map', 7200, function () use ($baseUrl, $apiKey) {
            try {
                $pmBranch    = AppSetting::get('partymaster_api_branch', 'ALL');
                $pmActCode   = AppSetting::get('partymaster_api_actcode', 'ALL');
                $pmAgentCode = AppSetting::get('partymaster_api_agentcode', 'ALL');
                $pmTxnType   = AppSetting::get('partymaster_api_txntype', 'New');

                Log::info('PartyMaster API Call', [
                    'url'       => $baseUrl . '/PartyMaster',
                    'Branch'    => $pmBranch,
                    'ActCode'   => $pmActCode,
                    'AgentCode' => $pmAgentCode,
                    'TxnType'   => $pmTxnType,
                ]);

                $response = Http::withoutVerifying()
                    ->timeout(120)
                    ->connectTimeout(20)
                    ->post("{$baseUrl}/PartyMaster", [
                        'apikey'       => $apiKey,
                        'Branch'       => $pmBranch,
                        'ActCode'      => $pmActCode,
                        'AgentCode'    => $pmAgentCode,
                        'modifieddate' => 'ALL',
                        'TxnType'      => $pmTxnType,
                    ]);

                Log::info('PartyMaster API Response', ['status' => $response->status()]);

                if ($response->successful()) {
                    $data = $response->json();
                    $rows = [];

                    if (isset($data['response']) && $data['response'] === 'success' && isset($data['resultdata'])) {
                        $rows = $data['resultdata'];
                    } elseif (is_array($data) && !isset($data['response'])) {
                        $rows = $data;
                    }

                    $map = [];
                    foreach ($rows as $row) {
                        // Try various key names for act code
                        $actCode = trim(
                            $row['ActCode']    ?? $row['Act_Code']   ??
                            $row['AC_Code']    ?? $row['Ac_Code']    ??
                            $row['PartyCode']  ?? $row['Party_Code'] ?? ''
                        );
                        if (!$actCode) continue;

                        // Auto detect AgentCode by looking for keys containing 'agent' or 'salesman' or 'sm' (case-insensitive)
                        $agentCodeVal = '';
                        foreach ($row as $k => $v) {
                            $kLower = strtolower($k);
                            if ($kLower !== 'agentname' && $kLower !== 'salesman' && $kLower !== 'salesmanname' && $kLower !== 'agent_name' &&
                                (str_contains($kLower, 'agentcode') || str_contains($kLower, 'agent_code') || 
                                 str_contains($kLower, 'salesmancode') || str_contains($kLower, 'salesman_code') ||
                                 str_contains($kLower, 'agentid') || str_contains($kLower, 'agent_id') ||
                                 $kLower === 'actcode' || $kLower === 'act_code' || $kLower === 'agent_code' ||
                                 $kLower === 'smcode' || $kLower === 'sm_code')) {
                                // Skip if it's the main party code (we already have $actCode)
                                if (trim($v) === $actCode && ($kLower === 'actcode' || $kLower === 'act_code' || $kLower === 'ac_code')) {
                                    continue;
                                }
                                $agentCodeVal = trim($v);
                                if ($agentCodeVal) break;
                            }
                        }
                        
                        // Fallback check on common names
                        if (!$agentCodeVal) {
                            $agentCodeVal = trim($row['AgentCode'] ?? $row['Agent_Code'] ?? $row['SalesmanCode'] ?? $row['SalesManCode'] ?? $row['Agent_Id'] ?? $row['AgentID'] ?? '');
                        }

                        $map[$actCode] = [
                            'PartyName'  => trim($row['ActName']     ?? $row['PartyName']   ?? $row['Party_Name']  ?? $row['AC_Name']    ?? $row['AcName']    ?? $row['AccountName'] ?? $row['Account_Name'] ?? $row['party_name'] ?? ''),
                            'AgentName'  => trim($row['AgentName']   ?? $row['Agent_Name']  ?? $row['SalesMan']   ?? $row['Salesman']  ?? ''),
                            'AgentCode'  => $agentCodeVal,
                            'TownName'   => trim($row['TownName']    ?? $row['Town_Name']   ?? $row['Town']       ?? $row['City']      ?? ''),
                            'BranchName' => trim($row['BranchName']  ?? $row['Branch_Name'] ?? $row['Branch']     ?? ''),
                            'GroupName'  => trim($row['GroupName']   ?? $row['Group_Name']  ?? $row['GroupName1'] ?? ''),
                        ];
                    }

                    Log::info('PartyMaster loaded', ['count' => count($map)]);
                    return $map;
                }

                Log::error('PartyMaster API failed', ['status' => $response->status()]);
            } catch (\Exception $e) {
                Log::error('PartyMaster API Exception: ' . $e->getMessage());
            }

            return [];
        });
    }

    /**
     * Display a report of all parties from PartyMaster API
     */
    public function partyMasterReport(Request $request)
    {
        $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
        $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');

        if ($request->has('refresh')) {
            Cache::forget('party_master_map');
        }

        $partyMasterMap = $this->getPartyMasterMap($baseUrl, $apiKey);
        $parties = collect($partyMasterMap);

        // Filters
        $branchFilter = $request->input('branch_filter', '');
        $agentFilter  = $request->input('agent_filter', '');

        $branchOptions = $parties->pluck('BranchName')->filter()->unique()->sort()->values()->toArray();
        $agentOptions  = $parties->pluck('AgentName')->filter()->unique()->sort()->values()->toArray();

        if ($branchFilter) {
            $parties = $parties->filter(fn($p) => ($p['BranchName'] ?? '') === $branchFilter);
        }
        if ($agentFilter) {
            $parties = $parties->filter(fn($p) => ($p['AgentName'] ?? '') === $agentFilter);
        }

        $reportData = $parties->values()->toArray();

        return view('reports.party_master', compact(
            'reportData', 'branchFilter', 'agentFilter', 'branchOptions', 'agentOptions'
        ));
    }

    /**
     * Store new Team in DB
     */
    public function storeTeam(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|unique:teams,name',
            'agents'      => 'nullable|array',
            'branches'    => 'nullable|array',
            'child_teams' => 'nullable|array',
            'parent_id'   => 'nullable|integer|exists:teams,id',
        ]);

        \App\Models\Team::create([
            'name'        => $request->input('name'),
            'agents'      => $request->input('agents', []),
            'branches'    => $request->input('branches', []),
            'child_teams' => $request->input('child_teams', []),
            'parent_id'   => $request->input('parent_id'),
        ]);

        return redirect()->back()->with('success', 'Team created successfully!');
    }

    /**
     * Update Team in DB
     */
    public function updateTeam(Request $request, \App\Models\Team $team)
    {
        $request->validate([
            'name'        => 'required|string|unique:teams,name,' . $team->id,
            'agents'      => 'nullable|array',
            'branches'    => 'nullable|array',
            'child_teams' => 'nullable|array',
            'parent_id'   => 'nullable|integer|exists:teams,id|different:id',
        ]);

        $team->update([
            'name'        => $request->input('name'),
            'agents'      => $request->input('agents', []),
            'branches'    => $request->input('branches', []),
            'child_teams' => $request->input('child_teams', []),
            'parent_id'   => $request->input('parent_id'),
        ]);

        return redirect()->back()->with('success', 'Team updated successfully!');
    }

    /**
     * Delete Team from DB
     */
    public function deleteTeam(\App\Models\Team $team)
    {
        $team->delete();
        return redirect()->back()->with('success', 'Team deleted successfully!');
    }

    public function agentTargetsIndex(Request $request)
    {
        $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
        $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');
        
        $partyMasterMap = $this->getPartyMasterMap($baseUrl, $apiKey);
        $agentOptions = collect($partyMasterMap)
            ->pluck('AgentName')->filter(fn($v) => $v && $v !== '—')
            ->unique()->sort()->values()->toArray();

        // Month filter
        $targetMonth = $request->input('month', date('Y-m'));

        // The party master is the collection side's source of agents. Anyone who actually
        // billed this financial year belongs here too, or they could never be given a sales
        // target -- so both lists are merged.
        if (Schema::hasTable('mssql_sales_records') && Schema::hasColumn('mssql_sales_records', 'agent_name')) {
            $fyStart = (now()->month >= 4 ? now()->year : now()->year - 1) . '-04-01';

            $sellingAgents = DB::table('mssql_sales_records')
                ->selectRaw('DISTINCT TRIM(agent_name) as agent_name')
                ->whereNotNull('agent_name')
                ->whereRaw("TRIM(agent_name) <> ''")
                ->where('vouch_date', '>=', $fyStart)
                ->pluck('agent_name')
                ->toArray();

            $agentOptions = collect($agentOptions)->merge($sellingAgents)
                ->map(fn ($a) => trim($a))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->toArray();
        }

        // Collection targets (the original purpose of this screen)
        $targets = \App\Models\AgentTarget::where('target_month', $targetMonth)
            ->get()
            ->pluck('target_amount', 'agent_name')
            ->toArray();

        // Sales targets — a separate table, because an agent's sell number and collect
        // number are different figures.
        $salesTargets = Schema::hasTable('agent_sales_targets')
            ? \App\Models\AgentSalesTarget::where('target_month', $targetMonth)
                ->get()
                ->pluck('target_amount', 'agent_name')
                ->toArray()
            : [];

        $fyMonths = self::financialYearMonths();

        // Load custom teams and their targets
        $dbTeams = \App\Models\Team::all();
        $teamTargets = \App\Models\TeamTarget::where('target_month', $targetMonth)
            ->get()
            ->pluck('target_amount', 'team_id')
            ->toArray();

        // Fetch months that already have targets configured
        $configuredAgentMonths = \App\Models\AgentTarget::select('target_month')
            ->distinct()
            ->pluck('target_month')
            ->toArray();

        $configuredTeamMonths = \App\Models\TeamTarget::select('target_month')
            ->distinct()
            ->pluck('target_month')
            ->toArray();

        $configuredMonths = array_unique(array_merge($configuredAgentMonths, $configuredTeamMonths));
        sort($configuredMonths);

        return view('reports.agent_targets', compact(
            'agentOptions', 'targetMonth', 'targets', 'salesTargets', 'fyMonths',
            'dbTeams', 'teamTargets', 'configuredMonths'
        ));
    }

    /**
     * Batch store agent targets
     */
    public function agentTargetsStore(Request $request)
    {
        $request->validate([
            'month'         => 'required|string',
            'targets'       => 'nullable|array',   // collection targets
            'sales_targets' => 'nullable|array',   // sales targets
        ]);

        $month = $request->input('month');

        // Optionally repeat these figures across the rest of the financial year, so a year's
        // worth of targets does not mean visiting this screen twelve times.
        $months = [$month];
        if ($request->boolean('apply_rest_of_fy')) {
            $months = array_values(array_filter(
                array_column(self::financialYearMonths(), 'key'),
                fn ($m) => $m >= $month
            )) ?: [$month];
        }

        $write = function (string $model, ?array $values) use ($months) {
            foreach ($values ?? [] as $agentName => $amount) {
                $agentName = trim((string)$agentName);
                if ($agentName === '') {
                    continue;
                }

                foreach ($months as $m) {
                    // Blank means "no target", which is not the same as a target of zero --
                    // so clear the row instead of storing 0.
                    if ($amount === null || trim((string)$amount) === '') {
                        $model::where('agent_name', $agentName)->where('target_month', $m)->delete();
                        continue;
                    }

                    $model::updateOrCreate(
                        ['agent_name' => $agentName, 'target_month' => $m],
                        ['target_amount' => (float)$amount]
                    );
                }
            }
        };

        $write(\App\Models\AgentTarget::class, $request->input('targets'));

        if (Schema::hasTable('agent_sales_targets')) {
            $write(\App\Models\AgentSalesTarget::class, $request->input('sales_targets'));
        }

        $spread = count($months) > 1 ? " (applied to {$months[0]} .. " . end($months) . ')' : '';

        return redirect()->back()->with('success', "Agent targets updated successfully!{$spread}");
    }

    public function teamTargetsStore(Request $request)
    {
        $request->validate([
            'month'               => 'required|string',
            'targets'             => 'required|array',   // team-level collection goals
            'agent_targets'       => 'nullable|array',   // member collection targets
            'agent_sales_targets' => 'nullable|array',   // member sales targets
        ]);

        $month = $request->input('month');

        // Save Team Targets
        foreach ($request->input('targets') as $teamId => $amount) {
            if ($amount === null || $amount === '') {
                \App\Models\TeamTarget::where('team_id', $teamId)
                    ->where('target_month', $month)
                    ->delete();
                continue;
            }

            \App\Models\TeamTarget::updateOrCreate(
                ['team_id' => $teamId, 'target_month' => $month],
                ['target_amount' => (float)$amount]
            );
        }

        // Member targets. Both kinds land in the same tables the Agent Targets tab writes to,
        // so a member edited here and the same agent edited there are one value, not two.
        $months = [$month];
        if ($request->boolean('apply_rest_of_fy')) {
            $months = array_values(array_filter(
                array_column(self::financialYearMonths(), 'key'),
                fn ($m) => $m >= $month
            )) ?: [$month];
        }

        $write = function (string $model, ?array $values) use ($months) {
            foreach ($values ?? [] as $agentName => $amount) {
                $agentName = trim((string)$agentName);
                if ($agentName === '') {
                    continue;
                }

                foreach ($months as $m) {
                    if ($amount === null || trim((string)$amount) === '') {
                        $model::where('agent_name', $agentName)->where('target_month', $m)->delete();
                        continue;
                    }

                    $model::updateOrCreate(
                        ['agent_name' => $agentName, 'target_month' => $m],
                        ['target_amount' => (float)$amount]
                    );
                }
            }
        };

        $write(\App\Models\AgentTarget::class, $request->input('agent_targets'));

        if (Schema::hasTable('agent_sales_targets')) {
            $write(\App\Models\AgentSalesTarget::class, $request->input('agent_sales_targets'));
        }

        $spread = count($months) > 1 ? " (applied to {$months[0]} .. " . end($months) . ')' : '';

        return redirect()->back()->with('success', "Team and Member targets updated successfully!{$spread}");
    }

    public function teamsSetup()
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && !$user->hasPermission('teams_setup', 'view') && !$user->hasPermission('mobile_teams_setup', 'view') && !$user->hasPermission('collection_report', 'view')) {
            abort(403, 'Unauthorized access to Teams Setup.');
        }

        $dbTeams = \App\Models\Team::all();

        $baseUrl = rtrim(\App\Models\AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
        $apiKey  = \App\Models\AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');
        
        $partyMasterMap = $this->getPartyMasterMap($baseUrl, $apiKey);

        // All agents and branches/group names from Party Master
        $allAgents = collect($partyMasterMap)
            ->pluck('AgentName')->filter(fn($v) => $v && $v !== '—')
            ->unique()->sort()->values()->toArray();
        $allBranches = collect($partyMasterMap)
            ->pluck('BranchName')->filter(fn($v) => $v && $v !== '—')
            ->unique()->sort()->values()->toArray();

        $agentToTeamMap = [];
        $branchToTeamMap = [];
        foreach ($dbTeams as $team) {
            foreach ($team->agents ?: [] as $a) {
                $agentToTeamMap[$a] = $team->name;
            }
            foreach ($team->branches ?: [] as $b) {
                $branchToTeamMap[$b] = $team->name;
            }
        }

        return view('reports.teams_setup', compact('dbTeams', 'allAgents', 'allBranches', 'agentToTeamMap', 'branchToTeamMap'));
    }

    public function saveTeamsSetup(Request $request)
    {
        $payload = $request->input('structure', []);
        $deletedTeamIds = $request->input('deleted_team_ids', []);
        
        if (!empty($deletedTeamIds)) {
            \App\Models\Team::whereIn('id', $deletedTeamIds)->delete();
        }
        
        $teamIdMapping = [];
        
        // First pass: create/update team base info
        foreach ($payload as $item) {
            $id = $item['id'] ?? null;
            $name = $item['name'] ?? '';
            if (!$name) continue;
            
            if (str_starts_with((string)$id, 'new_')) {
                $teamObj = \App\Models\Team::create([
                    'name' => $name,
                    'agents' => [],
                    'branches' => [],
                ]);
                $teamIdMapping[$id] = $teamObj->id;
            } else {
                $teamObj = \App\Models\Team::find($id);
                if ($teamObj) {
                    $teamObj->update([
                        'name' => $name,
                    ]);
                }
            }
        }
        
        // Second pass: update hierarchy & assignment arrays
        foreach ($payload as $item) {
            $id = $item['id'] ?? null;
            $dbId = $teamIdMapping[$id] ?? $id;
            
            $teamObj = \App\Models\Team::find($dbId);
            if ($teamObj) {
                $parentId = $item['parent_id'] ?? null;
                if ($parentId && isset($teamIdMapping[$parentId])) {
                    $parentId = $teamIdMapping[$parentId];
                }
                
                $teamObj->update([
                    'parent_id' => $parentId ?: null,
                    'agents' => $item['agents'] ?? [],
                    'branches' => $item['branches'] ?? [],
                ]);
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Team hierarchy structure saved successfully!'
        ]);
    }

    /**
     * Transaction types the sales report can be sliced by.
     *
     * Sale vs Sale Return and Stock Transfer come straight from the ERP's own series master
     * (Bill_Ser.type and Bill_Ser.Stock_Trans, synced as series_type / is_stock_transfer), so
     * there is no guessing from series names any more.
     */
    public const TXN_TYPES = [
        'sale'           => ['label' => 'Sale',           'icon' => '🧾', 'hint' => 'Cash + credit sales'],
        'sale_return'    => ['label' => 'Sale Return',    'icon' => '↩️', 'hint' => 'Goods returned by customers'],
        'credit_note'    => ['label' => 'Credit Note',    'icon' => '📝', 'hint' => 'Rate / scheme adjustments'],
        'stock_transfer' => ['label' => 'Stock Transfer', 'icon' => '🚚', 'hint' => 'Branch to branch movement'],
    ];

    /** A sales report shows trade, not internal movement, so transfers are off by default. */
    public const DEFAULT_TXN_TYPES = ['sale', 'sale_return', 'credit_note'];

    /**
     * The ERP marks these as returns (Bill_Ser.type = 'SR') but they are credit notes, not
     * goods coming back. Nothing in Bill_Ser separates the two, so the split is by series.
     */
    public const CREDIT_NOTE_SERIES = ['ISCR', 'MDS', 'SPCN', 'CNSW', 'SWCN', 'LKCN'];

    /**
     * Series lists used only for rows the sync agent has not classified yet (series_type is
     * NULL). Once a row has been through the agent, series_type / is_stock_transfer decide.
     */
    public const LEGACY_RETURN_SERIES = ['AMSR', 'SPSR', 'MSR', 'SWSR', 'LKR', 'LKSR'];
    public const LEGACY_TRANSFER_SERIES = ['AKST', 'MPST', 'UPST', 'MHST', 'SPST', 'SWPN'];

    /**
     * Read the requested transaction types, falling back to the default set.
     */
    public static function resolveTxnTypes(Request $request): array
    {
        $requested = $request->get('txn_types');

        if ($requested === null) {
            return self::DEFAULT_TXN_TYPES;
        }

        $types = array_values(array_intersect(
            array_map('strval', (array)$requested),
            array_keys(self::TXN_TYPES)
        ));

        // Everything unticked would produce an empty report, which reads as "no data" rather
        // than "no filter". Fall back to the default set instead.
        return $types ?: self::DEFAULT_TXN_TYPES;
    }

    /**
     * Narrow a query to the chosen transaction types. No-op when every type is selected, or
     * when the table predates the series_type / is_stock_transfer columns.
     */
    public static function applyTxnTypeFilter($query, array $types, string $tableName)
    {
        if (count($types) === count(self::TXN_TYPES)) {
            return $query;
        }

        $hasClassification = Schema::hasColumn($tableName, 'series_type')
            && Schema::hasColumn($tableName, 'is_stock_transfer');

        if (!$hasClassification || !Schema::hasColumn($tableName, 'series')) {
            return $query;
        }

        // Series codes come out of the ERP space-padded ("MDS "). MariaDB ignores trailing
        // spaces on comparison but MySQL 8's default collation does not, so trim explicitly.
        $series = fn () => DB::raw('TRIM(series)');

        $creditNotes = self::CREDIT_NOTE_SERIES;
        $returns = self::LEGACY_RETURN_SERIES;
        $transfers = self::LEGACY_TRANSFER_SERIES;
        $nonSale = array_merge($creditNotes, $returns, $transfers);

        // Rows the agent has classified are matched on series_type / is_stock_transfer.
        // Rows it has not reached yet (series_type IS NULL) fall back to the series lists,
        // so a half-synced table still reports sensibly instead of coming back empty.
        $classified = [
            'sale'           => fn ($q) => $q->where('series_type', 'SL')->where('is_stock_transfer', 0),
            'stock_transfer' => fn ($q) => $q->where('is_stock_transfer', 1),
            'sale_return'    => fn ($q) => $q->where('series_type', 'SR')->where('is_stock_transfer', 0)
                                             ->whereNotIn($series(), $creditNotes),
            'credit_note'    => fn ($q) => $q->where('series_type', 'SR')->where('is_stock_transfer', 0)
                                             ->whereIn($series(), $creditNotes),
        ];

        $legacy = [
            'sale'           => fn ($q) => $q->whereNotIn($series(), $nonSale),
            'stock_transfer' => fn ($q) => $q->whereIn($series(), $transfers),
            'sale_return'    => fn ($q) => $q->whereIn($series(), $returns),
            'credit_note'    => fn ($q) => $q->whereIn($series(), $creditNotes),
        ];

        return $query->where(function ($outer) use ($types, $classified, $legacy) {
            foreach ($types as $type) {
                if (!isset($classified[$type])) {
                    continue;
                }

                $outer->orWhere(function ($q) use ($type, $classified) {
                    $q->whereNotNull('series_type');
                    $classified[$type]($q);
                });

                $outer->orWhere(function ($q) use ($type, $legacy) {
                    $q->whereNull('series_type');
                    $legacy[$type]($q);
                });
            }
        });
    }

    /**
     * Resolve the branch filter into the list of raw values to match on. The table stores
     * either a numeric branch code or a name depending on which sync wrote the row, so both
     * forms of every selected branch go into the list.
     */
    public static function branchMatchList(array $selectedBranches, array $branchMap): array
    {
        $matches = [];

        foreach ($selectedBranches as $selected) {
            $target = strtoupper(trim((string)$selected));
            if ($target === '') {
                continue;
            }

            $matches[] = $selected;
            $matches[] = $target;

            foreach ($branchMap as $code => $name) {
                if (strtoupper($name) === $target || strtoupper((string)$code) === $target) {
                    $matches[] = (string)$code;
                    $matches[] = (string)$name;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    public function salesReport(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->role !== 'admin') {
            if (!$user->hasPermission('sales_report', 'view') && !$user->hasPermission('reports', 'view') && !$user->hasPermission('mobile_sales_report', 'view')) {
                abort(403, 'Unauthorized access to Sales Report.');
            }
        }

        // 1. Determine active data table
        $useMssqlTable = Schema::hasTable('mssql_sales_records') && DB::table('mssql_sales_records')->count() > 0;
        $useSalesRegTable = Schema::hasTable('sales_registers') && DB::table('sales_registers')->count() > 0;

        $tableName = $useMssqlTable ? 'mssql_sales_records' : ($useSalesRegTable ? 'sales_registers' : null);
        $totalSyncedRecords = $tableName ? DB::table($tableName)->count() : 0;
        $lastSyncTime = \App\Models\AppSetting::get('last_mssql_sales_sync', 'Live Synced');

        // Date Range logic
        $datePreset = $request->get('date_range');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $now = now();
        $currentYear = $now->year;
        $fyStartYear = $now->month >= 4 ? $currentYear : $currentYear - 1;

        if (empty($datePreset)) {
            if (!empty($fromDate) || !empty($toDate)) {
                $datePreset = 'custom';
            } else {
                $datePreset = 'this_fy';
            }
        }

        if ($datePreset === 'today') {
            $fromDate = $now->toDateString();
            $toDate = $now->toDateString();
        } elseif ($datePreset === 'this_month') {
            $fromDate = $now->copy()->startOfMonth()->toDateString();
            $toDate = $now->copy()->endOfMonth()->toDateString();
        } elseif ($datePreset === 'last_month') {
            $fromDate = $now->copy()->subMonth()->startOfMonth()->toDateString();
            $toDate = $now->copy()->subMonth()->endOfMonth()->toDateString();
        } elseif ($datePreset === 'this_fy') {
            $fromDate = "{$fyStartYear}-04-01";
            $toDate = ($fyStartYear + 1) . "-03-31";
        } elseif ($datePreset === 'prev_fy') {
            $fromDate = ($fyStartYear - 1) . "-04-01";
            $toDate = "{$fyStartYear}-03-31";
        } elseif ($datePreset === 'fy_24_25') {
            $fromDate = "2024-04-01";
            $toDate = "2025-03-31";
        } elseif ($datePreset === 'all_time') {
            $fromDate = null;
            $toDate = null;
        } elseif ($datePreset === 'custom') {
            // Keep provided $fromDate and $toDate
        }

        // branches[] is the current form field; `branch` is kept so old bookmarks and the
        // drill-down links still work.
        $selectedBranches = array_values(array_filter(
            array_map('trim', (array)($request->get('branches') ?? $request->get('branch') ?? [])),
            fn ($b) => $b !== ''
        ));
        $selectedBranch = $selectedBranches[0] ?? null;

        // Categories Filter: categories[] or category (supports single click & multi-select)
        $selectedCategories = array_values(array_filter(
            array_map('trim', (array)($request->get('categories') ?? $request->get('category') ?? [])),
            fn ($c) => $c !== '' && strtolower($c) !== 'all'
        ));
        $selectedCategory = count($selectedCategories) === 1 ? $selectedCategories[0] : null;

        $selectedTypes = self::resolveTxnTypes($request);
        $txnTypeOptions = self::TXN_TYPES;

        $searchQuery = trim($request->get('search', ''));

        $branchSummary = [];
        $grandTotalSales = 0;
        $grandTotalQty = 0;
        $grandTotalInvoices = 0;
        $allBranchNames = [];
        $allCategories = [];
        $monthlyTrend = [];
        $topProducts = [];
        $topParties = [];

        if ($tableName) {
            // Branch Name Mapping Dictionary (maps numeric codes to human readable branch names)
            $branchMap = [
                '2'  => 'FACTORY (HO)',
                '3'  => 'AKOLA',
                '6'  => 'PUNE',
                '7'  => 'INDORE',
                '13' => 'GHAZIABAD',
                '14' => 'LUCKNOW',
            ];
            if (Schema::hasTable('branches')) {
                foreach (\App\Models\Branch::all() as $br) {
                    if (!empty($br->code)) {
                        $branchMap[(string)$br->code] = strtoupper($br->name);
                    }
                }
            }

            // Distinct branch names for dropdown
            $branchCol = $useMssqlTable ? 'branch_name' : 'branch';
            $allBranchNames = DB::table($tableName)
                ->whereNotNull($branchCol)
                ->where($branchCol, '!=', '')
                ->distinct()
                ->pluck($branchCol)
                ->map(function($bVal) use ($branchMap) {
                    $clean = trim((string)$bVal);
                    return $branchMap[$clean] ?? $clean;
                })
                ->unique()
                ->sort()
                ->values();

            // Distinct product categories for filter bar
            $categoryCol = Schema::hasColumn($tableName, 'group_name') ? 'group_name' : (Schema::hasColumn($tableName, 'category') ? 'category' : null);
            if ($categoryCol) {
                $allCategories = DB::table($tableName)
                    ->whereNotNull($categoryCol)
                    ->where($categoryCol, '!=', '')
                    ->where($categoryCol, '!=', '(NIL)')
                    ->distinct()
                    ->orderBy($categoryCol)
                    ->pluck($categoryCol)
                    ->map(fn($c) => trim((string)$c))
                    ->unique()
                    ->values()
                    ->all();
            }

            // Build base query
            $amtField = $useMssqlTable ? 'COALESCE(calc_net_amt_n, calc_net_amt, 0)' : 'COALESCE(amount, 0)';
            $qtyField = $useMssqlTable ? 'COALESCE(tot_qty, 0)' : 'COALESCE(qty, 0)';
            $vouchField = $useMssqlTable ? 'COALESCE(vouch_num, id)' : 'id';
            $itemField = $useMssqlTable ? "COALESCE(item_hd_name, user_code, 'Unknown Item')" : "COALESCE(item_name, 'Unknown Item')";
            $actField = "COALESCE(act_name, 'Direct Customer')";

            $query = DB::table($tableName);

            if (!empty($fromDate)) {
                $query->where('vouch_date', '>=', $fromDate);
            }
            if (!empty($toDate)) {
                $query->where('vouch_date', '<=', $toDate);
            }
            if (!empty($selectedBranches)) {
                $query->whereIn($branchCol, self::branchMatchList($selectedBranches, $branchMap));
            }
            if (!empty($selectedCategories) && $categoryCol) {
                $query->whereIn($categoryCol, $selectedCategories);
            }
            if (!empty($searchQuery)) {
                // Only search columns that exist on the table actually being queried.
                $searchable = array_values(array_filter(
                    [$branchCol, 'item_hd_name', 'item_name', 'act_name', 'agent_name', 'vouch_num', 'user_code', $categoryCol],
                    fn ($col) => !empty($col) && Schema::hasColumn($tableName, $col)
                ));

                $query->where(function ($q) use ($searchQuery, $searchable) {
                    foreach ($searchable as $col) {
                        $q->orWhere($col, 'like', "%{$searchQuery}%");
                    }
                });
            }

            // Transaction type filter
            self::applyTxnTypeFilter($query, $selectedTypes, $tableName);

            // 1. Consolidated Branch Grouping
            $rawBranches = (clone $query)
                ->select(
                    DB::raw("COALESCE({$branchCol}, 'HEAD OFFICE') as branch_name"),
                    DB::raw("SUM({$amtField}) as total_sales"),
                    DB::raw("SUM({$qtyField}) as total_qty"),
                    DB::raw("COUNT(DISTINCT {$vouchField}) as total_invoices"),
                    DB::raw("COUNT(*) as total_lines"),
                    DB::raw("MIN(vouch_date) as min_date"),
                    DB::raw("MAX(vouch_date) as max_date")
                )
                ->groupBy(DB::raw("COALESCE({$branchCol}, 'HEAD OFFICE')"))
                ->get();

            // Merge / resolve numeric codes to human-readable names
            $groupedByResolvedName = [];
            foreach ($rawBranches as $b) {
                $rawName = trim((string)$b->branch_name);
                $resolvedName = $branchMap[$rawName] ?? $rawName;

                if (!isset($groupedByResolvedName[$resolvedName])) {
                    $groupedByResolvedName[$resolvedName] = [
                        'branch_name'    => $resolvedName,
                        'total_sales'    => 0,
                        'total_qty'      => 0,
                        'total_invoices' => 0,
                        'total_lines'    => 0,
                        'min_date'       => $b->min_date,
                        'max_date'       => $b->max_date,
                    ];
                }
                $groupedByResolvedName[$resolvedName]['total_sales'] += (float)$b->total_sales;
                $groupedByResolvedName[$resolvedName]['total_qty'] += (float)$b->total_qty;
                $groupedByResolvedName[$resolvedName]['total_invoices'] += (int)$b->total_invoices;
                $groupedByResolvedName[$resolvedName]['total_lines'] += (int)$b->total_lines;
                if ($b->min_date && (!$groupedByResolvedName[$resolvedName]['min_date'] || $b->min_date < $groupedByResolvedName[$resolvedName]['min_date'])) {
                    $groupedByResolvedName[$resolvedName]['min_date'] = $b->min_date;
                }
                if ($b->max_date && (!$groupedByResolvedName[$resolvedName]['max_date'] || $b->max_date > $groupedByResolvedName[$resolvedName]['max_date'])) {
                    $groupedByResolvedName[$resolvedName]['max_date'] = $b->max_date;
                }
            }

            // Sort by sales descending, then by qty descending
            usort($groupedByResolvedName, function($a, $b) {
                if ($b['total_sales'] != $a['total_sales']) {
                    return $b['total_sales'] <=> $a['total_sales'];
                }
                return $b['total_qty'] <=> $a['total_qty'];
            });

            $grandTotalSales = array_sum(array_column($groupedByResolvedName, 'total_sales'));
            $grandTotalQty = array_sum(array_column($groupedByResolvedName, 'total_qty'));
            $grandTotalInvoices = array_sum(array_column($groupedByResolvedName, 'total_invoices'));

            $rank = 1;
            foreach ($groupedByResolvedName as $b) {
                $sales = (float)$b['total_sales'];
                $invoices = (int)$b['total_invoices'];
                $share = $grandTotalSales > 0 ? ($sales / $grandTotalSales) * 100 : 0;
                $aov = $invoices > 0 ? $sales / $invoices : 0;

                $branchSummary[] = [
                    'rank'               => $rank++,
                    'branch_name'        => $b['branch_name'],
                    'total_sales'        => $sales,
                    'total_qty'          => (float)$b['total_qty'],
                    'total_invoices'     => $invoices,
                    'total_lines'        => (int)$b['total_lines'],
                    'share_percent'      => round($share, 1),
                    'avg_order_value'    => round($aov, 2),
                    'formatted_sales'    => self::formatIndianCurrency($sales),
                    'formatted_aov'      => self::formatIndianCurrency($aov),
                    'min_date'           => $b['min_date'],
                    'max_date'           => $b['max_date'],
                ];
            }

            // 2. Top 8 Selling Items overall
            $topProducts = (clone $query)
                ->select(
                    DB::raw("{$itemField} as item_name"),
                    DB::raw("SUM({$amtField}) as total_sales"),
                    DB::raw("SUM({$qtyField}) as total_qty")
                )
                ->groupBy(DB::raw("{$itemField}"))
                ->orderByDesc('total_sales')
                ->limit(8)
                ->get()
                ->map(fn($item) => [
                    'item_name'       => $item->item_name,
                    'total_sales'     => (float)$item->total_sales,
                    'total_qty'       => (float)$item->total_qty,
                    'formatted_sales' => self::formatIndianCurrency($item->total_sales),
                ]);

            // 3. Top 8 Customers / Parties overall
            $topParties = (clone $query)
                ->select(
                    DB::raw("{$actField} as party_name"),
                    DB::raw("COALESCE({$branchCol}, '-') as branch_name"),
                    DB::raw("SUM({$amtField}) as total_sales"),
                    DB::raw("COUNT(DISTINCT {$vouchField}) as invoice_count")
                )
                ->groupBy(DB::raw("{$actField}"), DB::raw("COALESCE({$branchCol}, '-')"))
                ->orderByDesc('total_sales')
                ->limit(8)
                ->get()
                ->map(function($party) use ($branchMap) {
                    $rawB = trim((string)$party->branch_name);
                    $mappedB = $branchMap[$rawB] ?? $rawB;
                    return [
                        'party_name'      => $party->party_name,
                        'branch_name'     => $mappedB,
                        'total_sales'     => (float)$party->total_sales,
                        'invoice_count'   => (int)$party->invoice_count,
                        'formatted_sales' => self::formatIndianCurrency($party->total_sales),
                    ];
                });
            // 4. Multi-Year YoY Comparison (Same period / till date across Current FY, Last FY, 2 Years Ago)
            $yoyComparison = self::computeYoYComparison(
                $tableName,
                $useMssqlTable,
                $fromDate,
                $toDate,
                $datePreset,
                $selectedBranches,
                $selectedCategories,
                $selectedTypes,
                $searchQuery,
                $branchMap
            );
        } else {
            $yoyComparison = [];
        }

        $formattedGrandSales = self::formatIndianCurrency($grandTotalSales);
        $topBranch = count($branchSummary) > 0 ? $branchSummary[0] : null;

        return view('reports.sales_report', compact(
            'branchSummary',
            'grandTotalSales',
            'formattedGrandSales',
            'grandTotalQty',
            'grandTotalInvoices',
            'topBranch',
            'allBranchNames',
            'allCategories',
            'selectedCategory',
            'selectedCategories',
            'yoyComparison',
            'totalSyncedRecords',
            'lastSyncTime',
            'datePreset',
            'fromDate',
            'toDate',
            'selectedBranch',
            'selectedBranches',
            'selectedTypes',
            'txnTypeOptions',
            'searchQuery',
            'topProducts',
            'topParties'
        ));
    }

    /**
     * Compute Year-over-Year (YoY) Multi-Year Sales Comparison for the same date window / till date,
     * respecting all user filters (branches, category, transaction types, search).
     */
    public static function computeYoYComparison(
        string $tableName,
        bool $useMssqlTable,
        ?string $fromDate,
        ?string $toDate,
        string $datePreset,
        array $selectedBranches,
        array $selectedCategories,
        array $selectedTypes,
        string $searchQuery,
        array $branchMap
    ): array {
        $amtField   = $useMssqlTable ? 'COALESCE(calc_net_amt_n, calc_net_amt, 0)' : 'COALESCE(amount, 0)';
        $qtyField   = $useMssqlTable ? 'COALESCE(tot_qty, 0)' : 'COALESCE(qty, 0)';
        $vouchField = $useMssqlTable ? 'COALESCE(vouch_num, id)' : 'id';
        $branchCol  = $useMssqlTable ? 'branch_name' : 'branch';
        $categoryCol = Schema::hasColumn($tableName, 'group_name') ? 'group_name' : (Schema::hasColumn($tableName, 'category') ? 'category' : null);

        $today = now()->format('Y-m-d');
        $maxDbDate = DB::table($tableName)->max('vouch_date') ?? $today;

        // Base dates for Current Period
        if (!empty($fromDate) && !empty($toDate) && $datePreset === 'custom') {
            $curFrom = $fromDate;
            $curTo   = $toDate;
        } elseif ($datePreset === 'prev_fy') {
            $curFrom = '2025-04-01';
            $curTo   = '2026-03-31';
        } elseif ($datePreset === 'fy_24_25') {
            $curFrom = '2024-04-01';
            $curTo   = '2025-03-31';
        } else {
            // Default (this_fy / all_time / today / this_month / etc.):
            if (!empty($fromDate)) {
                $curFrom = $fromDate;
                $curTo   = !empty($toDate) ? $toDate : $maxDbDate;
            } else {
                $curFrom = '2026-04-01';
                $curTo   = min($maxDbDate, $today);
            }
        }

        // Calculate equivalent date windows in 1 Year Ago (LY) and 2 Years Ago (LLY)
        try {
            $cFromObj = \Carbon\Carbon::parse($curFrom);
            $cToObj   = \Carbon\Carbon::parse($curTo);

            $lyFrom   = $cFromObj->copy()->subYear()->format('Y-m-d');
            $lyTo     = $cToObj->copy()->subYear()->format('Y-m-d');

            $llyFrom  = $cFromObj->copy()->subYears(2)->format('Y-m-d');
            $llyTo    = $cToObj->copy()->subYears(2)->format('Y-m-d');
        } catch (\Exception $e) {
            $curFrom = '2026-04-01';
            $curTo   = $maxDbDate;
            $lyFrom  = '2025-04-01';
            $lyTo    = '2025-08-24';
            $llyFrom = '2024-04-01';
            $llyTo   = '2024-08-24';
        }

        // Helper query builder that applies all filters
        $buildFilteredQuery = function ($startDt, $endDt) use (
            $tableName, $branchCol, $categoryCol, $selectedBranches, $selectedCategories,
            $selectedTypes, $searchQuery, $branchMap
        ) {
            $q = DB::table($tableName)->whereBetween('vouch_date', [$startDt, $endDt]);

            if (!empty($selectedBranches)) {
                $q->whereIn($branchCol, self::branchMatchList($selectedBranches, $branchMap));
            }
            if (!empty($selectedCategories) && $categoryCol) {
                $q->whereIn($categoryCol, $selectedCategories);
            }
            if (!empty($searchQuery)) {
                $searchable = array_values(array_filter(
                    [$branchCol, 'item_hd_name', 'item_name', 'act_name', 'agent_name', 'vouch_num', 'user_code', $categoryCol],
                    fn ($col) => !empty($col) && Schema::hasColumn($tableName, $col)
                ));
                $q->where(function ($sub) use ($searchQuery, $searchable) {
                    foreach ($searchable as $col) {
                        $sub->orWhere($col, 'like', "%{$searchQuery}%");
                    }
                });
            }
            self::applyTxnTypeFilter($q, $selectedTypes, $tableName);
            return $q;
        };

        // Query Aggregations for the 3 Periods
        $curData = $buildFilteredQuery($curFrom, $curTo)->select(
            DB::raw("COALESCE(SUM({$amtField}), 0) as total_sales"),
            DB::raw("COALESCE(SUM({$qtyField}), 0) as total_qty"),
            DB::raw("COUNT(DISTINCT {$vouchField}) as total_invoices")
        )->first();

        $lyData = $buildFilteredQuery($lyFrom, $lyTo)->select(
            DB::raw("COALESCE(SUM({$amtField}), 0) as total_sales"),
            DB::raw("COALESCE(SUM({$qtyField}), 0) as total_qty"),
            DB::raw("COUNT(DISTINCT {$vouchField}) as total_invoices")
        )->first();

        $llyData = $buildFilteredQuery($llyFrom, $llyTo)->select(
            DB::raw("COALESCE(SUM({$amtField}), 0) as total_sales"),
            DB::raw("COALESCE(SUM({$qtyField}), 0) as total_qty"),
            DB::raw("COUNT(DISTINCT {$vouchField}) as total_invoices")
        )->first();

        $curSales = (float)($curData->total_sales ?? 0);
        $lySales  = (float)($lyData->total_sales ?? 0);
        $llySales = (float)($llyData->total_sales ?? 0);

        // Growth vs Last Year
        $lyDiff = $curSales - $lySales;
        $lyGrowthPct = $lySales > 0 ? round(($lyDiff / $lySales) * 100, 1) : 0;

        // Growth vs 2 Years Ago
        $llyDiff = $curSales - $llySales;
        $llyGrowthPct = $llySales > 0 ? round(($llyDiff / $llySales) * 100, 1) : 0;

        $maxVal = max(1.0, $curSales, $lySales, $llySales);

        // Helper function for branch breakdown for any date window
        $getBranchBreakdown = function ($startDt, $endDt) use ($buildFilteredQuery, $branchCol, $amtField, $qtyField, $vouchField, $branchMap) {
            $rows = $buildFilteredQuery($startDt, $endDt)
                ->select(
                    DB::raw("COALESCE({$branchCol}, 'HEAD OFFICE') as branch_name"),
                    DB::raw("COALESCE(SUM({$amtField}), 0) as total_sales"),
                    DB::raw("COALESCE(SUM({$qtyField}), 0) as total_qty"),
                    DB::raw("COUNT(DISTINCT {$vouchField}) as total_invoices")
                )
                ->groupBy(DB::raw("COALESCE({$branchCol}, 'HEAD OFFICE')"))
                ->get();

            $branches = [];
            foreach ($rows as $r) {
                $raw = trim((string)$r->branch_name);
                $mapped = $branchMap[$raw] ?? $raw;
                if (!isset($branches[$mapped])) {
                    $branches[$mapped] = [
                        'name'            => $mapped,
                        'total_sales'     => 0,
                        'total_qty'       => 0,
                        'total_invoices'  => 0,
                        'formatted_sales' => '₹ 0.00',
                    ];
                }
                $branches[$mapped]['total_sales'] += (float)$r->total_sales;
                $branches[$mapped]['total_qty'] += (float)$r->total_qty;
                $branches[$mapped]['total_invoices'] += (int)$r->total_invoices;
            }

            usort($branches, fn($a, $b) => $b['total_sales'] <=> $a['total_sales']);

            foreach ($branches as &$b) {
                $b['formatted_sales'] = self::formatIndianCurrency($b['total_sales']);
            }
            unset($b);

            return array_values($branches);
        };

        $curBranches = $getBranchBreakdown($curFrom, $curTo);
        $lyBranches  = $getBranchBreakdown($lyFrom, $lyTo);
        $llyBranches = $getBranchBreakdown($llyFrom, $llyTo);

        // Map branches for direct key lookup
        $curBranchMap = array_column($curBranches, null, 'name');
        $lyBranchMap  = array_column($lyBranches, null, 'name');
        $llyBranchMap = array_column($llyBranches, null, 'name');

        // All distinct branch names across the 3 periods
        $allBranchNamesList = array_values(array_unique(array_merge(
            array_keys($curBranchMap),
            array_keys($lyBranchMap),
            array_keys($llyBranchMap)
        )));

        $branchComparison = [];
        foreach ($allBranchNamesList as $bName) {
            $cSales = (float)($curBranchMap[$bName]['total_sales'] ?? 0);
            $lSales = (float)($lyBranchMap[$bName]['total_sales'] ?? 0);
            $llSales = (float)($llyBranchMap[$bName]['total_sales'] ?? 0);

            $diff = $cSales - $lSales;
            $growth = $lSales > 0 ? round(($diff / $lSales) * 100, 1) : ($cSales > 0 ? 100.0 : 0.0);

            $branchComparison[] = [
                'name'            => $bName,
                'cur_sales'       => $cSales,
                'formatted_cur'   => self::formatIndianCurrency($cSales),
                'ly_sales'        => $lSales,
                'formatted_ly'    => self::formatIndianCurrency($lSales),
                'lly_sales'       => $llSales,
                'formatted_lly'   => self::formatIndianCurrency($llSales),
                'diff'            => $diff,
                'growth_percent'  => $growth,
                'is_growth'       => $diff >= 0,
            ];
        }

        usort($branchComparison, fn($a, $b) => $b['cur_sales'] <=> $a['cur_sales']);

        return [
            'current' => [
                'fy_label'        => 'FY 26-27 (Current)',
                'badge'           => '🟢 Current FY',
                'from_date'       => $curFrom,
                'to_date'         => $curTo,
                'display_period'  => \Carbon\Carbon::parse($curFrom)->format('d M Y') . ' → ' . \Carbon\Carbon::parse($curTo)->format('d M Y'),
                'total_sales'     => $curSales,
                'formatted_sales' => self::formatIndianCurrency($curSales),
                'exact_sales'     => '₹ ' . number_format($curSales, 2),
                'total_qty'       => (float)($curData->total_qty ?? 0),
                'total_invoices'  => (int)($curData->total_invoices ?? 0),
                'bar_percent'     => round(($curSales / $maxVal) * 100, 1),
                'branches'        => $curBranches,
            ],
            'last_year' => [
                'fy_label'        => 'FY 25-26 (Last Year)',
                'badge'           => '📅 1 Year Ago (Same Period)',
                'from_date'       => $lyFrom,
                'to_date'         => $lyTo,
                'display_period'  => \Carbon\Carbon::parse($lyFrom)->format('d M Y') . ' → ' . \Carbon\Carbon::parse($lyTo)->format('d M Y'),
                'total_sales'     => $lySales,
                'formatted_sales' => self::formatIndianCurrency($lySales),
                'exact_sales'     => '₹ ' . number_format($lySales, 2),
                'total_qty'       => (float)($lyData->total_qty ?? 0),
                'total_invoices'  => (int)($lyData->total_invoices ?? 0),
                'diff_sales'      => $lyDiff,
                'growth_percent'  => $lyGrowthPct,
                'is_growth'       => $lyDiff >= 0,
                'formatted_diff'  => ($lyDiff >= 0 ? '+' : '') . self::formatIndianCurrency($lyDiff) . ' (' . ($lyGrowthPct >= 0 ? '+' : '') . $lyGrowthPct . '%)',
                'bar_percent'     => round(($lySales / $maxVal) * 100, 1),
                'branches'        => $lyBranches,
            ],
            'two_years_ago' => [
                'fy_label'        => 'FY 24-25 (2 Years Ago)',
                'badge'           => '📜 2 Years Ago (Same Period)',
                'from_date'       => $llyFrom,
                'to_date'         => $llyTo,
                'display_period'  => \Carbon\Carbon::parse($llyFrom)->format('d M Y') . ' → ' . \Carbon\Carbon::parse($llyTo)->format('d M Y'),
                'total_sales'     => $llySales,
                'formatted_sales' => self::formatIndianCurrency($llySales),
                'exact_sales'     => '₹ ' . number_format($llySales, 2),
                'total_qty'       => (float)($llyData->total_qty ?? 0),
                'total_invoices'  => (int)($llyData->total_invoices ?? 0),
                'diff_sales'      => $llyDiff,
                'growth_percent'  => $llyGrowthPct,
                'is_growth'       => $llyDiff >= 0,
                'formatted_diff'  => ($llyDiff >= 0 ? '+' : '') . self::formatIndianCurrency($llyDiff) . ' (' . ($llyGrowthPct >= 0 ? '+' : '') . $llyGrowthPct . '%)',
                'bar_percent'     => round(($llySales / $maxVal) * 100, 1),
                'branches'        => $llyBranches,
            ],
            'branch_comparison' => $branchComparison,
        ];
    }

    /**
     * Category styling metadata (emoji icon, human-readable label, CSS classes)
     */
    public static function categoryMeta(string $catName): array
    {
        $upper = strtoupper(trim($catName));
        return match($upper) {
            'HERBICIDE' => [
                'icon'         => '🌿',
                'label'        => 'Herbicide',
                'badge_class'  => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'active_class' => 'bg-emerald-600 text-white shadow-emerald-600/30 border-emerald-600',
                'color'        => 'emerald',
            ],
            'FUNGICIDE' => [
                'icon'         => '🍄',
                'label'        => 'Fungicide',
                'badge_class'  => 'bg-teal-50 text-teal-700 border border-teal-200',
                'active_class' => 'bg-teal-600 text-white shadow-teal-600/30 border-teal-600',
                'color'        => 'teal',
            ],
            'INSECTICIDE' => [
                'icon'         => '🐛',
                'label'        => 'Insecticide',
                'badge_class'  => 'bg-amber-50 text-amber-700 border border-amber-200',
                'active_class' => 'bg-amber-600 text-white shadow-amber-600/30 border-amber-600',
                'color'        => 'amber',
            ],
            'PLANT GROWTH REGULATOR', 'PGR' => [
                'icon'         => '🌱',
                'label'        => 'PGR / Growth Reg.',
                'badge_class'  => 'bg-lime-50 text-lime-700 border border-lime-200',
                'active_class' => 'bg-lime-600 text-white shadow-lime-600/30 border-lime-600',
                'color'        => 'lime',
            ],
            'ORGANIC MANURE' => [
                'icon'         => '🍂',
                'label'        => 'Organic Manure',
                'badge_class'  => 'bg-orange-50 text-orange-700 border border-orange-200',
                'active_class' => 'bg-orange-600 text-white shadow-orange-600/30 border-orange-600',
                'color'        => 'orange',
            ],
            'ANTIBIOTIC' => [
                'icon'         => '💊',
                'label'        => 'Antibiotic',
                'badge_class'  => 'bg-sky-50 text-sky-700 border border-sky-200',
                'active_class' => 'bg-sky-600 text-white shadow-sky-600/30 border-sky-600',
                'color'        => 'sky',
            ],
            '100% SOLUBLE IN WATER', 'WATER SOLUBLE FERTILIZERS' => [
                'icon'         => '💧',
                'label'        => '100% Soluble',
                'badge_class'  => 'bg-blue-50 text-blue-700 border border-blue-200',
                'active_class' => 'bg-blue-600 text-white shadow-blue-600/30 border-blue-600',
                'color'        => 'blue',
            ],
            default => [
                'icon'         => '🧪',
                'label'        => ucwords(strtolower($catName)),
                'badge_class'  => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                'active_class' => 'bg-indigo-600 text-white shadow-indigo-600/30 border-indigo-600',
                'color'        => 'indigo',
            ],
        };
    }

    /**
     * Numeric branch code -> display name. The table stores whichever form the sync that
     * wrote the row happened to use, so both are needed when filtering.
     */
    public static function branchCodeMap(): array
    {
        $map = [
            '2'  => 'FACTORY (HO)',
            '3'  => 'AKOLA',
            '6'  => 'PUNE',
            '7'  => 'INDORE',
            '13' => 'GHAZIABAD',
            '14' => 'LUCKNOW',
        ];

        if (Schema::hasTable('branches')) {
            foreach (\App\Models\Branch::all() as $branch) {
                if (!empty($branch->code)) {
                    $map[(string)$branch->code] = strtoupper($branch->name);
                }
            }
        }

        return $map;
    }

    /**
     * Series code -> the description used in the ERP's own reports.
     */
    public static function seriesLabels(): array
    {
        return [
            'AMSR' => 'Akola Sales Return',
            'ISCR' => 'Akola Credit Note',
            'AKST' => 'Akola Stock Transfer',
            'SPSR' => 'Pune Sale Return',
            'MSR'  => 'Indore Sale Return',
            'MDS'  => 'Indore Credit Note',
            'SWSR' => 'Ghaziabad Sale Retun',
            'MPST' => 'Indore Stock Transfer',
            'UPST' => 'Ghaziabad Stock Transfer',
            'AKSL' => 'Akola Credit Sale',
            'AKCS' => 'Akola Cash Sale',
            'PNCS' => 'Pune Cash Sale',
            'PNSL' => 'Pune Credit Sale',
            'MPSL' => 'Indore Credit Sale',
            'MPCS' => 'Indore Cash Sale',
            'UPSL' => 'Ghaziabad Credit Sale',
            'UPCS' => 'Ghaziabad Cash Sale',
            'MHST' => 'Factory Stock Transfer',
            'MHSL' => 'Factory Sale',
            'AKLF' => 'Akola Fertilizer Sale',
            'LKN'  => 'Lucknow Credit Sale',
            'LKR'  => 'Lucknow Sale Return',
            'SPCN' => 'Pune Return Credit Note',
            'SPST' => 'Pune Stock Transfer',
            'CNSW' => 'Ghaziabad Credit Note',
            'PNF'  => 'Pune Local / Factory',
            'LKS'  => 'Lucknow Credit Sale',
            'LKSL' => 'Lucknow Credit Sale',
            'LKCS' => 'Lucknow Cash Sale',
            'LKLF' => 'Lucknow Local Sales',
            'LKSR' => 'Lucknow Sale Return',
        ];
    }

    /**
     * Turn the date_range preset (or an explicit from/to) into [$fromDate, $toDate].
     * Both can come back null, which means "all time".
     */
    public static function resolveDateWindow(Request $request): array
    {
        $preset = $request->get('date_range');
        $from = $request->get('from_date');
        $to = $request->get('to_date');

        $now = now();
        $fyStart = $now->month >= 4 ? $now->year : $now->year - 1;

        if (empty($preset)) {
            $preset = (!empty($from) || !empty($to)) ? 'custom' : 'this_fy';
        }

        return match ($preset) {
            'today'      => [$now->toDateString(), $now->toDateString()],
            'this_month' => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth()->toDateString(),
                $now->copy()->subMonth()->endOfMonth()->toDateString(),
            ],
            'this_fy'    => ["{$fyStart}-04-01", ($fyStart + 1) . '-03-31'],
            'prev_fy'    => [($fyStart - 1) . '-04-01', "{$fyStart}-03-31"],
            'fy_24_25'   => ['2024-04-01', '2025-03-31'],
            'all_time'   => [null, null],
            default      => [$from, $to],
        };
    }

    /**
     * The twelve 'YYYY-MM' keys of a financial year, April to March.
     */
    public static function financialYearMonths(?int $startYear = null): array
    {
        $now = now();
        $startYear ??= $now->month >= 4 ? $now->year : $now->year - 1;

        $cursor = \Carbon\Carbon::create($startYear, 4, 1);
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $months[] = ['key' => $cursor->format('Y-m'), 'label' => $cursor->format('M y')];
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Save a whole financial year of agent targets in one go.
     *
     * Payload: targets[<agent name>][<YYYY-MM>] = amount. A blank cell deletes that month's
     * target rather than storing zero, so "no target set" and "target of zero" stay distinct.
     */
    public function salesAgentTargetsStore(Request $request)
    {
        $request->validate([
            'targets' => 'required|array',
        ]);

        $saved = 0;
        $cleared = 0;

        foreach ($request->input('targets') as $agentName => $months) {
            $agentName = trim((string)$agentName);

            if ($agentName === '' || !is_array($months)) {
                continue;
            }

            foreach ($months as $month => $amount) {
                if (!preg_match('/^\d{4}-\d{2}$/', (string)$month)) {
                    continue;
                }

                if ($amount === null || trim((string)$amount) === '') {
                    $cleared += \App\Models\AgentSalesTarget::where('agent_name', $agentName)
                        ->where('target_month', $month)
                        ->delete();
                    continue;
                }

                \App\Models\AgentSalesTarget::updateOrCreate(
                    ['agent_name' => $agentName, 'target_month' => $month],
                    ['target_amount' => (float)$amount]
                );
                $saved++;
            }
        }

        return redirect()->back()->with(
            'success',
            "Agent targets saved — {$saved} month value(s) updated" . ($cleared ? ", {$cleared} cleared." : '.')
        );
    }

    /**
     * Levels of the sales drill-down, in order.
     *
     * A branch card is the entry point; each level below drills one step further and carries
     * every level above it as a filter. Reordering the chain or adding a level (godown, city,
     * ...) is a change to this array only -- the query builder and both views are generic.
     */
    public const DRILL_LEVELS = [
        ['key' => 'agent',    'label' => 'Agent',            'column' => 'agent_name',   'icon' => '👤'],
        ['key' => 'category', 'label' => 'Product Category', 'column' => 'group_name',   'icon' => '🧪'],
        ['key' => 'series',   'label' => 'Sale Type',        'column' => 'series',       'icon' => '🧾'],
        ['key' => 'party',    'label' => 'Party',            'column' => 'act_name',     'icon' => '🏪'],
        ['key' => 'bill',     'label' => 'Bill No',          'column' => 'vouch_num',    'icon' => '📄'],
        ['key' => 'item',     'label' => 'Product',          'column' => 'item_hd_name', 'icon' => '📦'],
    ];

    /** URL value meaning "the rows where this level is blank". */
    public const DRILL_BLANK = '__blank__';

    /**
     * The 'YYYY-MM' keys a date range covers, which is how agent_targets stores a month.
     * A range that touches only part of a month still counts that month whole -- the UI
     * shows the month count alongside the figure so the basis is never hidden.
     */
    public static function monthsInRange(?string $from, ?string $to): array
    {
        if (empty($from) || empty($to)) {
            return [];
        }

        $cursor = \Carbon\Carbon::parse($from)->startOfMonth();
        $last = \Carbon\Carbon::parse($to)->startOfMonth();
        $months = [];

        // Guard against a reversed range, and against an absurd span from a bad URL.
        while ($cursor->lte($last) && count($months) < 120) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Total agent target for a date range: ['AGENT NAME' => ['target' => float, 'months' => int]].
     * Returns an empty array for an open-ended range (All Time), where no target applies.
     */
    public static function agentTargetsForPeriod(?string $from, ?string $to): array
    {
        $months = self::monthsInRange($from, $to);

        if (empty($months) || !Schema::hasTable('agent_sales_targets')) {
            return [];
        }

        $rows = \App\Models\AgentSalesTarget::whereIn('target_month', $months)
            ->selectRaw('TRIM(agent_name) as agent_name')
            ->selectRaw('SUM(target_amount) as total_target')
            ->selectRaw('COUNT(DISTINCT target_month) as month_count')
            ->groupBy(DB::raw('TRIM(agent_name)'))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[strtoupper(trim($row->agent_name))] = [
                'target' => (float)$row->total_target,
                'months' => (int)$row->month_count,
            ];
        }

        return $out;
    }

    /** Rows sent per level. Anything beyond is reported, never silently dropped. */
    public const DRILL_PAGE_SIZE = 150;

    /**
     * The levels usable against a given table. A column the sync agent has not delivered yet
     * (agent_name on a cloud DB that has not been bootstrapped) is dropped rather than
     * producing an empty level.
     */
    public static function drillLevels(string $tableName): array
    {
        return array_values(array_filter(
            self::DRILL_LEVELS,
            fn ($level) => Schema::hasColumn($tableName, $level['column'])
        ));
    }

    /**
     * SQL for a level's grouping key: trimmed, with blanks collapsed to NULL so that '',
     * '   ' and NULL all land in one bucket instead of three.
     */
    private static function drillKeyExpr(array $level): string
    {
        return "NULLIF(TRIM(COALESCE({$level['column']}, '')), '')";
    }

    /**
     * AJAX/JSON API for the interactive sales drill-down.
     *
     * Branch is required; every level parameter present narrows the query one step further:
     *   ?branch=AKOLA
     *   ?branch=AKOLA&category=HERBICIDE
     *   ?branch=AKOLA&category=HERBICIDE&series=AKSL
     *   ?branch=AKOLA&category=HERBICIDE&series=AKSL&agent=TUSHAR+PISE ... and so on
     *
     * The response always names the level it just returned and the one below it, so the
     * views can render the chain without knowing it.
     */
    public function salesDrilldown(Request $request)
    {
        if ($request->has('check_jobs')) {
            $jobs = DB::table('query_jobs')->orderBy('id', 'desc')->limit(10)->get();
            $output = [];
            foreach ($jobs as $j) {
                $output[] = "ID: {$j->id} | Status: {$j->status} | Rows: {$j->result_count} | Token: {$j->job_token} | Error: {$j->error_message} | Exec Time: {$j->execution_seconds}s | Created: {$j->created_at} | Updated: {$j->updated_at}";
            }
            return response(implode("\n", $output))->header('Content-Type', 'text/plain');
        }

        $branch = trim($request->get('branch', ''));

        if ($branch === '') {
            return response()->json(['status' => 'error', 'message' => 'Branch is required'], 400);
        }

        $useMssqlTable = Schema::hasTable('mssql_sales_records') && DB::table('mssql_sales_records')->count() > 0;
        $useSalesRegTable = Schema::hasTable('sales_registers') && DB::table('sales_registers')->count() > 0;
        $tableName = $useMssqlTable ? 'mssql_sales_records' : ($useSalesRegTable ? 'sales_registers' : null);

        if (!$tableName) {
            return response()->json([
                'status'                => 'success',
                'branch'                => $branch,
                'levels'                => [],
                'path'                  => [],
                'level'                 => null,
                'is_leaf'               => true,
                'items'                 => [],
                'total_sales'           => 0,
                'formatted_total_sales' => '₹ 0.00',
            ]);
        }

        $levels = self::drillLevels($tableName);
        $branchMap = self::branchCodeMap();
        $seriesNames = self::seriesLabels();

        [$fromDate, $toDate] = self::resolveDateWindow($request);

        $amtField   = $useMssqlTable ? 'COALESCE(calc_net_amt_n, calc_net_amt, 0)' : 'COALESCE(amount, 0)';
        $qtyField   = $useMssqlTable ? 'COALESCE(tot_qty, 0)' : 'COALESCE(qty, 0)';
        $vouchField = $useMssqlTable ? 'COALESCE(vouch_num, id)' : 'id';
        $branchCol  = $useMssqlTable ? 'branch_name' : 'branch';

        $query = DB::table($tableName)
            ->whereIn($branchCol, self::branchMatchList([$branch], $branchMap));

        if (!empty($fromDate)) {
            $query->where('vouch_date', '>=', $fromDate);
        }
        if (!empty($toDate)) {
            $query->where('vouch_date', '<=', $toDate);
        }

        self::applyTxnTypeFilter($query, self::resolveTxnTypes($request), $tableName);

        // Walk down the chain for as long as the request supplies values. A gap stops the
        // walk, so a stale link with a level missing degrades to the level above it instead
        // of silently applying a filter from further down.
        $path = [];
        $depth = 0;

        foreach ($levels as $index => $level) {
            $value = $request->get($level['key']);

            if ($value === null || $value === '') {
                break;
            }

            $expr = self::drillKeyExpr($level);

            if ($value === self::DRILL_BLANK) {
                $query->whereNull(DB::raw($expr));
            } else {
                $query->where(DB::raw($expr), trim((string)$value));
            }

            $path[] = [
                'key'     => $level['key'],
                'label'   => $level['label'],
                'icon'    => $level['icon'],
                'value'   => $value,
                'display' => self::drillDisplayLabel($level['key'], $value, $seriesNames),
            ];

            $depth = $index + 1;
        }

        // Totals for whatever the current filters describe -- the header of the panel.
        $totals = (clone $query)
            ->select(
                DB::raw("SUM({$amtField}) as total_sales"),
                DB::raw("SUM({$qtyField}) as total_qty"),
                DB::raw("COUNT(DISTINCT {$vouchField}) as total_invoices"),
                DB::raw("COUNT(*) as total_lines")
            )
            ->first();

        $totalSales = (float)($totals->total_sales ?? 0);

        $response = [
            'status'                => 'success',
            'branch'                => $branch,
            'levels'                => array_map(
                fn ($l) => ['key' => $l['key'], 'label' => $l['label'], 'icon' => $l['icon']],
                $levels
            ),
            'path'                  => $path,
            'depth'                 => $depth,
            'total_sales'           => $totalSales,
            'formatted_total_sales' => self::formatIndianCurrency($totalSales),
            'total_qty'             => (float)($totals->total_qty ?? 0),
            'total_invoices'        => (int)($totals->total_invoices ?? 0),
            'total_lines'           => (int)($totals->total_lines ?? 0),
        ];

        // Bottom of the chain: nothing left to group by.
        if ($depth >= count($levels)) {
            return response()->json($response + [
                'level'      => null,
                'next_level' => null,
                'is_leaf'    => true,
                'items'      => [],
            ]);
        }

        $next = $levels[$depth];
        $keyExpr = self::drillKeyExpr($next);

        $select = [
            DB::raw("{$keyExpr} as group_key"),
            DB::raw("SUM({$amtField}) as total_sales"),
            DB::raw("SUM({$qtyField}) as total_qty"),
            DB::raw("COUNT(DISTINCT {$vouchField}) as total_invoices"),
            DB::raw("COUNT(*) as total_lines"),
        ];

        // A bill is one document, so its date and customer are worth showing inline rather
        // than making the user drill another step just to identify it.
        $wantsBillMeta = $next['key'] === 'bill';
        if ($wantsBillMeta) {
            $select[] = DB::raw('MIN(vouch_date) as bill_date');
            if (Schema::hasColumn($tableName, 'act_name')) {
                $select[] = DB::raw('MIN(act_name) as bill_party');
            }
        }

        // Monthly targets only make sense against agents, and only for a bounded period.
        $agentTargets = $next['key'] === 'agent'
            ? self::agentTargetsForPeriod($fromDate, $toDate)
            : [];

        // How many months the selected period spans. Compared against each agent's
        // target_months this tells the UI when a target only covers part of the period --
        // otherwise a year of sales against one month of target reads as 1400% achieved.
        $periodMonths = $next['key'] === 'agent'
            ? count(self::monthsInRange($fromDate, $toDate))
            : 0;

        // Group on the select alias rather than repeating the expression -- under
        // ONLY_FULL_GROUP_BY, MariaDB does not treat two identical expressions as the same
        // grouping key, but it does resolve an alias.
        $rows = (clone $query)
            ->select($select)
            ->groupBy('group_key')
            ->orderByDesc('total_sales')
            ->get();

        $distinctCount = $rows->count();

        $items = $rows->take(self::DRILL_PAGE_SIZE)
            ->map(function ($row) use ($next, $seriesNames, $totalSales, $wantsBillMeta, $agentTargets) {
                $raw = $row->group_key;
                $sales = (float)$row->total_sales;

                $item = [
                    'key'             => $raw === null ? self::DRILL_BLANK : (string)$raw,
                    'label'           => self::drillDisplayLabel($next['key'], $raw, $seriesNames),
                    'code'            => $next['key'] === 'series' ? trim((string)$raw) : null,
                    'total_sales'     => $sales,
                    'formatted_sales' => self::formatIndianCurrency($sales),
                    'total_qty'       => (float)$row->total_qty,
                    'total_invoices'  => (int)$row->total_invoices,
                    'total_lines'     => (int)$row->total_lines,
                    'share_percent'   => $totalSales != 0 ? round(($sales / $totalSales) * 100, 1) : 0,
                    'is_return'       => $sales < 0,
                ];

                if ($wantsBillMeta) {
                    $item['bill_date'] = $row->bill_date
                        ? \Carbon\Carbon::parse($row->bill_date)->format('d-m-Y')
                        : null;
                    $item['bill_party'] = $row->bill_party ?? null;
                }

                if ($next['key'] === 'agent') {
                    $target = $agentTargets[strtoupper(trim((string)$raw))] ?? null;

                    $item['target'] = $target['target'] ?? null;
                    $item['target_months'] = $target['months'] ?? 0;
                    $item['formatted_target'] = $target ? self::formatIndianCurrency($target['target']) : null;

                    // Achievement is meaningless without a target, and returns can push an
                    // agent negative -- both cases stay null rather than showing a fake 0%.
                    $item['achievement_percent'] = ($target && $target['target'] > 0)
                        ? round(($sales / $target['target']) * 100, 1)
                        : null;

                    $item['shortfall'] = ($target && $target['target'] > 0)
                        ? round($target['target'] - $sales, 2)
                        : null;
                }

                return $item;
            })->values();

        return response()->json($response + [
            'level'         => ['key' => $next['key'], 'label' => $next['label'], 'icon' => $next['icon']],
            'period_months' => $periodMonths,
            'next_level' => $levels[$depth + 1]['label'] ?? null,
            'is_leaf'    => !isset($levels[$depth + 1]),
            'items'      => $items,
            'shown'      => $items->count(),
            'available'  => $distinctCount,
            'truncated'  => $distinctCount > $items->count(),
        ]);
    }

    /**
     * Human label for a drill-down value. Series get their ERP description, and the ERP's
     * placeholder agent (code 0, named "NIL") reads better as "No Agent".
     */
    private static function drillDisplayLabel(string $levelKey, $raw, array $seriesNames): string
    {
        $value = trim((string)($raw ?? ''));

        if ($value === '' || $value === self::DRILL_BLANK) {
            return match ($levelKey) {
                'category' => '(No Category)',
                'agent'    => '(No Agent)',
                'party'    => '(Direct Customer)',
                'item'     => '(Unknown Item)',
                default    => '(Blank)',
            };
        }

        if ($levelKey === 'series') {
            return $seriesNames[$value] ?? "Series {$value}";
        }

        if ($levelKey === 'agent' && strcasecmp($value, 'NIL') === 0) {
            return '(No Agent)';
        }

        return $value;
    }

    /**
     * Format number as Indian Currency (Crores, Lakhs, Thousands)
     */
    public static function formatIndianCurrency($num): string
    {
        $num = (float)$num;
        $abs = abs($num);
        $sign = $num < 0 ? '-' : '';

        if ($abs >= 10000000) { // >= 1 Crore (100 Lakhs)
            return $sign . '₹ ' . number_format($abs / 10000000, 2) . ' Cr';
        } elseif ($abs >= 100000) { // >= 1 Lakh
            return $sign . '₹ ' . number_format($abs / 100000, 2) . ' L';
        } elseif ($abs >= 1000) {
            return $sign . '₹ ' . number_format($abs / 1000, 1) . ' K';
        }
        return $sign . '₹ ' . number_format($abs, 2);
    }

    /**
     * ------------------------------------------------------------------------------------
     * 360-Degree Sales Explorer (mobile-only)
     * ------------------------------------------------------------------------------------
     * Unlike salesDrilldown()'s fixed agent -> category -> series -> party -> bill -> item
     * chain (each level a strict prefix of the one before it), every dimension here --
     * branch, category, agent, product -- is an independent, freely combinable AND filter
     * over one query, so any combination can be applied at once. Built as a single engine
     * (buildSales360Payload) shared by the page action and the AJAX action so they can never
     * drift apart. Nothing here touches salesReport()/salesDrilldown()/DRILL_LEVELS.
     */

    /**
     * Page entry point: permission guard + small preloaded filter-option lists (branches,
     * categories, agents -- all bounded) + the initial breakdown payload for default filters,
     * so the mobile view isn't blank-then-fetch on first paint. Products are deliberately NOT
     * preloaded here -- see sales360Products().
     */
    public function sales360(Request $request): array
    {
        $user = Auth::user();
        if ($user && $user->role !== 'admin') {
            if (!$user->hasPermission('mobile_sales_360', 'view')) {
                abort(403, 'Unauthorized access to 360 Sales Report.');
            }
        }

        $useMssqlTable = Schema::hasTable('mssql_sales_records') && DB::table('mssql_sales_records')->count() > 0;
        $useSalesRegTable = Schema::hasTable('sales_registers') && DB::table('sales_registers')->count() > 0;
        $tableName = $useMssqlTable ? 'mssql_sales_records' : ($useSalesRegTable ? 'sales_registers' : null);

        $branchMap = self::branchCodeMap();
        $allBranchNames = [];
        $allCategories = [];
        $allAgents = [];

        if ($tableName) {
            $branchCol = $useMssqlTable ? 'branch_name' : 'branch';
            $allBranchNames = DB::table($tableName)
                ->whereNotNull($branchCol)
                ->where($branchCol, '!=', '')
                ->distinct()
                ->pluck($branchCol)
                ->map(fn ($b) => $branchMap[strtoupper(trim((string)$b))] ?? trim((string)$b))
                ->unique()
                ->sort()
                ->values()
                ->all();

            $categoryCol = Schema::hasColumn($tableName, 'group_name') ? 'group_name' : (Schema::hasColumn($tableName, 'category') ? 'category' : null);
            if ($categoryCol) {
                $allCategories = DB::table($tableName)
                    ->whereNotNull($categoryCol)
                    ->where($categoryCol, '!=', '')
                    ->where($categoryCol, '!=', '(NIL)')
                    ->distinct()
                    ->orderBy($categoryCol)
                    ->pluck($categoryCol)
                    ->map(fn ($c) => trim((string)$c))
                    ->unique()
                    ->values()
                    ->all();
            }

            if (Schema::hasColumn($tableName, 'agent_name')) {
                $allAgents = DB::table($tableName)
                    ->whereNotNull('agent_name')
                    ->where('agent_name', '!=', '')
                    ->distinct()
                    ->orderBy('agent_name')
                    ->pluck('agent_name')
                    ->map(fn ($a) => trim((string)$a))
                    ->filter(fn ($a) => $a !== '')
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return self::buildSales360Payload($request) + [
            'filter_options' => [
                'branches'   => $allBranchNames,
                'categories' => $allCategories,
                'agents'     => $allAgents,
            ],
            'txn_type_options' => self::TXN_TYPES,
            'default_txn_types' => self::DEFAULT_TXN_TYPES,
        ];
    }

    /**
     * AJAX action: same filters as sales360(), returns just the breakdown payload. Called on
     * every filter change from the mobile page.
     */
    public function sales360Data(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->role !== 'admin' && !$user->hasPermission('mobile_sales_360', 'view')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json(self::buildSales360Payload($request));
    }

    /**
     * Product typeahead: products are far too numerous to preload like branches/categories/
     * agents, so this is a debounced search-as-you-type sub-endpoint instead (capped at 20).
     */
    public function sales360Products(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->role !== 'admin' && !$user->hasPermission('mobile_sales_360', 'view')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $q = trim((string)$request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $useMssqlTable = Schema::hasTable('mssql_sales_records') && DB::table('mssql_sales_records')->count() > 0;
        $useSalesRegTable = Schema::hasTable('sales_registers') && DB::table('sales_registers')->count() > 0;
        $tableName = $useMssqlTable ? 'mssql_sales_records' : ($useSalesRegTable ? 'sales_registers' : null);

        $itemCol = $tableName
            ? (Schema::hasColumn($tableName, 'item_hd_name') ? 'item_hd_name' : (Schema::hasColumn($tableName, 'item_name') ? 'item_name' : null))
            : null;

        if (!$itemCol) {
            return response()->json(['results' => []]);
        }

        $results = DB::table($tableName)
            ->whereNotNull($itemCol)
            ->where($itemCol, 'like', '%' . $q . '%')
            ->distinct()
            ->orderBy($itemCol)
            ->limit(20)
            ->pluck($itemCol)
            ->map(fn ($v) => trim((string)$v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Party (customer) typeahead: just as numerous as products, same debounced search pattern.
     */
    public function sales360Parties(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->role !== 'admin' && !$user->hasPermission('mobile_sales_360', 'view')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $q = trim((string)$request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $useMssqlTable = Schema::hasTable('mssql_sales_records') && DB::table('mssql_sales_records')->count() > 0;
        $useSalesRegTable = Schema::hasTable('sales_registers') && DB::table('sales_registers')->count() > 0;
        $tableName = $useMssqlTable ? 'mssql_sales_records' : ($useSalesRegTable ? 'sales_registers' : null);

        $actCol = $tableName && Schema::hasColumn($tableName, 'act_name') ? 'act_name' : null;

        if (!$actCol) {
            return response()->json(['results' => []]);
        }

        $results = DB::table($tableName)
            ->whereNotNull($actCol)
            ->where($actCol, 'like', '%' . $q . '%')
            ->distinct()
            ->orderBy($actCol)
            ->limit(20)
            ->pluck($actCol)
            ->map(fn ($v) => trim((string)$v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values();

        return response()->json(['results' => $results]);
    }

    /**
     * The 360 engine: one filtered query (every dimension applied simultaneously, unlike the
     * fixed-chain drilldown), grand totals, and five independent top-8+Others breakdowns
     * (branch/category/agent/product/party) computed off clones of that same query.
     */
    private static function buildSales360Payload(Request $request): array
    {
        $emptyBreakdowns = [
            'branch'   => ['available' => false, 'rows' => [], 'others' => null],
            'category' => ['available' => false, 'rows' => [], 'others' => null],
            'agent'    => ['available' => false, 'rows' => [], 'others' => null],
            'product'  => ['available' => false, 'rows' => [], 'others' => null],
            'party'    => ['available' => false, 'rows' => [], 'others' => null],
        ];

        $useMssqlTable = Schema::hasTable('mssql_sales_records') && DB::table('mssql_sales_records')->count() > 0;
        $useSalesRegTable = Schema::hasTable('sales_registers') && DB::table('sales_registers')->count() > 0;
        $tableName = $useMssqlTable ? 'mssql_sales_records' : ($useSalesRegTable ? 'sales_registers' : null);

        if (!$tableName) {
            return [
                'totals' => [
                    'total_sales' => 0, 'total_qty' => 0, 'total_qty_kg' => 0, 'qty_kg_available' => false,
                    'total_invoices' => 0, 'avg_order_value' => 0,
                    'formatted_sales' => self::formatIndianCurrency(0),
                    'formatted_aov' => self::formatIndianCurrency(0),
                ],
                'filters_echo' => [],
                'breakdowns' => $emptyBreakdowns,
                'meta' => ['table_used' => null],
            ];
        }

        [$fromDate, $toDate] = self::resolveDateWindow($request);
        $branchMap = self::branchCodeMap();
        $selectedTypes = self::resolveTxnTypes($request);

        $trimFilter = fn ($key) => array_values(array_filter(
            array_map('trim', (array)$request->get($key, [])),
            fn ($v) => $v !== ''
        ));

        $selectedBranches   = $trimFilter('branches');
        $selectedCategories = $trimFilter('categories');
        $selectedAgents     = $trimFilter('agents');
        $selectedProducts   = $trimFilter('products');
        $selectedParties    = $trimFilter('parties');

        $branchCol   = $useMssqlTable ? 'branch_name' : 'branch';
        $categoryCol = Schema::hasColumn($tableName, 'group_name') ? 'group_name' : (Schema::hasColumn($tableName, 'category') ? 'category' : null);
        $agentCol    = Schema::hasColumn($tableName, 'agent_name') ? 'agent_name' : null;
        $itemCol     = Schema::hasColumn($tableName, 'item_hd_name') ? 'item_hd_name' : (Schema::hasColumn($tableName, 'item_name') ? 'item_name' : null);
        $actCol      = Schema::hasColumn($tableName, 'act_name') ? 'act_name' : null;

        $amtField   = $useMssqlTable ? 'COALESCE(calc_net_amt_n, calc_net_amt, 0)' : 'COALESCE(amount, 0)';
        $qtyField   = $useMssqlTable ? 'COALESCE(tot_qty, 0)' : 'COALESCE(qty, 0)';
        $vouchField = $useMssqlTable ? 'COALESCE(vouch_num, id)' : 'id';

        // Busy's own Item Master already carries weight_per_unit normalized to KG/LTR per pack
        // (e.g. a "500 ML" pack -> weight_per_unit 0.5, a "250 GM" pack -> 0.25) -- verified
        // against live data on 2026-09-15. So SUM(tot_qty * weight_per_unit) is the sale
        // quantity in KG/LTR directly, no separate product-master lookup/conversion needed.
        $qtyKgAvailable = $useMssqlTable && Schema::hasColumn($tableName, 'weight_per_unit');
        $qtyKgField = $qtyKgAvailable ? 'SUM(COALESCE(tot_qty, 0) * COALESCE(weight_per_unit, 0))' : null;

        $query = DB::table($tableName);

        if (!empty($fromDate)) {
            $query->where('vouch_date', '>=', $fromDate);
        }
        if (!empty($toDate)) {
            $query->where('vouch_date', '<=', $toDate);
        }
        if (!empty($selectedBranches)) {
            $query->whereIn($branchCol, self::branchMatchList($selectedBranches, $branchMap));
        }
        if (!empty($selectedCategories) && $categoryCol) {
            $query->whereIn($categoryCol, $selectedCategories);
        }
        if (!empty($selectedAgents) && $agentCol) {
            $query->whereIn($agentCol, $selectedAgents);
        }
        if (!empty($selectedProducts) && $itemCol) {
            $query->whereIn($itemCol, $selectedProducts);
        }
        if (!empty($selectedParties) && $actCol) {
            $query->whereIn($actCol, $selectedParties);
        }

        self::applyTxnTypeFilter($query, $selectedTypes, $tableName);

        // Grand totals -- the single source every breakdown's share_percent divides into.
        // Never derive this by summing a breakdown's rows.
        $totalsSelect = "SUM({$amtField}) as total_sales, SUM({$qtyField}) as total_qty, COUNT(DISTINCT {$vouchField}) as total_invoices";
        if ($qtyKgField) {
            $totalsSelect .= ", {$qtyKgField} as total_qty_kg";
        }
        $totalsRow = (clone $query)->selectRaw($totalsSelect)->first();

        $grandSales = (float)($totalsRow->total_sales ?? 0);
        $grandQty = (float)($totalsRow->total_qty ?? 0);
        $grandQtyKg = $qtyKgField ? (float)($totalsRow->total_qty_kg ?? 0) : 0;
        $grandInvoices = (int)($totalsRow->total_invoices ?? 0);
        $aov = $grandInvoices > 0 ? $grandSales / $grandInvoices : 0;

        $dimensions = [
            'branch' => [
                'col' => $branchCol,
                'expr' => "COALESCE({$branchCol}, 'HEAD OFFICE')",
                'available' => true,
                // Numeric-code and name rows for the same branch must merge into one group,
                // exactly like salesReport()'s branch summary does.
                'resolve' => fn ($raw) => $branchMap[strtoupper(trim((string)$raw))] ?? trim((string)$raw),
            ],
            'category' => [
                'col' => $categoryCol,
                'expr' => $categoryCol ? "COALESCE({$categoryCol}, 'Uncategorized')" : null,
                'available' => (bool)$categoryCol,
                'resolve' => null,
            ],
            'agent' => [
                'col' => $agentCol,
                // The sync agent already NULLIFs blank/whitespace agent names at source (see
                // docs/05-sales-sync-bridge.md), so a plain COALESCE is enough here -- keeping
                // this a single simple function, like every other dimension's expr, avoids a
                // MySQL ONLY_FULL_GROUP_BY mismatch some nested TRIM/NULLIF combinations trip.
                'expr' => $agentCol ? "COALESCE({$agentCol}, 'Unassigned')" : null,
                'available' => (bool)$agentCol,
                'resolve' => null,
            ],
            'product' => [
                'col' => $itemCol,
                'expr' => $itemCol
                    ? ($useMssqlTable ? "COALESCE({$itemCol}, user_code, 'Unknown Item')" : "COALESCE({$itemCol}, 'Unknown Item')")
                    : null,
                'available' => (bool)$itemCol,
                'resolve' => null,
            ],
            'party' => [
                'col' => $actCol,
                'expr' => $actCol ? "COALESCE({$actCol}, 'Direct Customer')" : null,
                'available' => (bool)$actCol,
                'resolve' => null,
            ],
        ];

        $breakdowns = [];

        foreach ($dimensions as $key => $dim) {
            if (!$dim['available']) {
                $breakdowns[$key] = ['available' => false, 'rows' => [], 'others' => null];
                continue;
            }

            $select = [
                DB::raw("{$dim['expr']} as dim_value"),
                DB::raw("SUM({$amtField}) as total_sales"),
                DB::raw("SUM({$qtyField}) as total_qty"),
                DB::raw("COUNT(DISTINCT {$vouchField}) as total_invoices"),
            ];
            if ($qtyKgField) {
                $select[] = DB::raw("{$qtyKgField} as total_qty_kg");
            }

            $rawRows = (clone $query)
                ->select($select)
                ->groupBy(DB::raw($dim['expr']))
                ->orderByDesc('total_sales')
                ->get();

            // Uniform group shape: [label, total_sales, total_qty, total_qty_kg, total_invoices,
            // raw_values[]]. Branch merges multiple raw group values (code + name) into one
            // label; every other dimension's SQL GROUP BY already produces one row per distinct
            // label, so raw_values is a one-item list there -- kept the same shape either way so
            // the "Others" exclusion below doesn't need to special-case branch.
            if ($dim['resolve']) {
                $groups = [];
                foreach ($rawRows as $row) {
                    $label = $dim['resolve']($row->dim_value);
                    $groups[$label] ??= ['label' => $label, 'total_sales' => 0.0, 'total_qty' => 0.0, 'total_qty_kg' => 0.0, 'total_invoices' => 0, 'raw_values' => []];
                    $groups[$label]['total_sales'] += (float)$row->total_sales;
                    $groups[$label]['total_qty'] += (float)$row->total_qty;
                    $groups[$label]['total_qty_kg'] += $qtyKgField ? (float)$row->total_qty_kg : 0.0;
                    $groups[$label]['total_invoices'] += (int)$row->total_invoices;
                    $groups[$label]['raw_values'][] = $row->dim_value;
                }
                $groups = collect($groups)->sortByDesc('total_sales')->values();
            } else {
                $groups = $rawRows->map(fn ($row) => [
                    'label' => $row->dim_value,
                    'total_sales' => (float)$row->total_sales,
                    'total_qty' => (float)$row->total_qty,
                    'total_qty_kg' => $qtyKgField ? (float)$row->total_qty_kg : 0.0,
                    'total_invoices' => (int)$row->total_invoices,
                    'raw_values' => [$row->dim_value],
                ]);
            }

            $top = $groups->take(8)->values();
            $remaining = $groups->slice(8)->values();

            $rows = $top->map(function ($g, $idx) use ($grandSales) {
                $sales = (float)$g['total_sales'];
                return [
                    'rank'            => $idx + 1,
                    'label'           => $g['label'],
                    'total_sales'     => $sales,
                    'total_qty'       => (float)$g['total_qty'],
                    'total_qty_kg'    => round((float)$g['total_qty_kg'], 2),
                    'total_invoices'  => (int)$g['total_invoices'],
                    'share_percent'   => $grandSales > 0 ? round(($sales / $grandSales) * 100, 1) : 0,
                    'formatted_sales' => self::formatIndianCurrency($sales),
                ];
            })->values()->all();

            $others = null;
            if ($remaining->isNotEmpty()) {
                $othersSales = (float)$remaining->sum('total_sales');
                $othersQty = (float)$remaining->sum('total_qty');
                $othersQtyKg = (float)$remaining->sum('total_qty_kg');

                // Invoice counts are NOT additive/subtractive across a dimension's groups --
                // one bill can span several products/categories -- so Others' invoice count
                // needs its own distinct-count query, never derived from the rows above.
                $topRawValues = $top->flatMap(fn ($g) => $g['raw_values'])->all();
                $othersInvoices = (int)((clone $query)
                    ->whereNotIn(DB::raw($dim['expr']), $topRawValues)
                    ->selectRaw("COUNT(DISTINCT {$vouchField}) as c")
                    ->value('c') ?? 0);

                $others = [
                    'label'           => 'Others',
                    'total_sales'     => $othersSales,
                    'total_qty'       => $othersQty,
                    'total_qty_kg'    => round($othersQtyKg, 2),
                    'total_invoices'  => $othersInvoices,
                    'share_percent'   => $grandSales > 0 ? round(($othersSales / $grandSales) * 100, 1) : 0,
                    'formatted_sales' => self::formatIndianCurrency($othersSales),
                    'count'           => $remaining->count(),
                ];
            }

            $breakdowns[$key] = ['available' => true, 'rows' => $rows, 'others' => $others];
        }

        return [
            'totals' => [
                'total_sales'      => $grandSales,
                'total_qty'        => $grandQty,
                'total_qty_kg'     => round($grandQtyKg, 2),
                'qty_kg_available' => $qtyKgAvailable,
                'total_invoices'   => $grandInvoices,
                'avg_order_value'  => round($aov, 2),
                'formatted_sales'  => self::formatIndianCurrency($grandSales),
                'formatted_aov'    => self::formatIndianCurrency($aov),
            ],
            'filters_echo' => [
                'date_range'  => $request->get('date_range'),
                'from_date'   => $fromDate,
                'to_date'     => $toDate,
                'branches'    => $selectedBranches,
                'categories'  => $selectedCategories,
                'agents'      => $selectedAgents,
                'products'    => $selectedProducts,
                'parties'     => $selectedParties,
                'txn_types'   => $selectedTypes,
            ],
            'breakdowns' => $breakdowns,
            'meta' => ['table_used' => $tableName],
        ];
    }

    public function executeSalesQuery(Request $request)
    {
        @set_time_limit(180);
        $query = trim($request->input('query', ''));

        if (empty($query)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid SQL query to execute.'
            ], 422);
        }

        // Security check: Only allow SELECT or WITH queries (Read-Only)
        $cleanQuery = ltrim($query);
        $firstWord = strtoupper(strtok($cleanQuery, " \t\n\r"));
        if (!in_array($firstWord, ['SELECT', 'WITH', 'EXEC', 'EXECUTE', 'SET', 'SHOW', 'DESCRIBE', 'EXPLAIN'])) {
            return response()->json([
                'success' => false,
                'message' => 'Security Error: Only SELECT queries are permitted in this module.'
            ], 403);
        }

        // Check for forbidden keywords (prevent destructive SQL)
        if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM|UPDATE\s+\w+\s+SET|ALTER\s+TABLE|INSERT\s+INTO|CREATE\s+TABLE)\b/i', $query)) {
            return response()->json([
                'success' => false,
                'message' => 'Security Error: Modifying queries (DROP/DELETE/UPDATE/INSERT/ALTER) are strictly blocked.'
            ], 403);
        }

        $startTime = microtime(true);

        try {
            $isMssqlDirect = preg_match('/\b(Sl_Txn|Sl_Head|It_Mst|Accounts|Cust_Mst|Branch_Mst|Lot_Mst|Pack_Mst|Bill_Ser)\b/i', $query);

            if (!$isMssqlDirect || stripos($query, 'mssql_sales_records') !== false) {
                // Execute directly on MySQL (Synced Records)
                $results = DB::select($query);
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                $rows = [];
                $columns = [];

                if (!empty($results)) {
                    $firstObj = (array)$results[0];
                    $columns = array_keys($firstObj);

                    foreach ($results as $res) {
                        $row = (array)$res;
                        foreach ($row as $k => $v) {
                            if ($v instanceof \DateTimeInterface) {
                                $row[$k] = $v->format('Y-m-d H:i:s');
                            } elseif (is_null($v)) {
                                $row[$k] = null;
                            }
                        }
                        $rows[] = $row;
                    }
                }

                return response()->json([
                    'success' => true,
                    'columns' => $columns,
                    'rows' => $rows,
                    'count' => count($rows),
                    'execution_time_ms' => $executionTime,
                    'source' => 'MySQL Synced Data',
                ]);
            }

            // Direct MS SQL Query execution (when on Local or Tunnel)
            $pdo = $this->getMssqlConnection();
            $stmt = $pdo->query($query);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $rows = [];
            $columns = [];

            if (!empty($results)) {
                $columns = array_keys($results[0]);

                foreach ($results as $res) {
                    $row = $res;
                    foreach ($row as $k => $v) {
                        if ($v instanceof \DateTimeInterface) {
                            $row[$k] = $v->format('Y-m-d H:i:s');
                        } elseif (is_null($v)) {
                            $row[$k] = null;
                        }
                    }
                    $rows[] = $row;
                }
            }

            return response()->json([
                'success' => true,
                'columns' => $columns,
                'rows' => $rows,
                'count' => count($rows),
                'execution_time_ms' => $executionTime,
                'source' => 'MS SQL Direct',
            ]);
        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::error('Sales Query Error: ' . $e->getMessage(), ['query' => $query]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
            ], 500);
        }
    }

    /**
     * Adaptive MS SQL connection supporting sqlsrv, dblib (Linux FreeTDS), and odbc
     */
    private function getMssqlConnection(): \PDO
    {
        $host = config('database.connections.sqlsrv.host', env('DB_SQLSRV_HOST', '100.108.74.58'));
        $port = config('database.connections.sqlsrv.port', env('DB_SQLSRV_PORT', '1433'));
        $db   = config('database.connections.sqlsrv.database', env('DB_SQLSRV_DATABASE', 'LOGICDBSY'));
        $user = config('database.connections.sqlsrv.username', env('DB_SQLSRV_USERNAME', 'sa'));
        $pass = config('database.connections.sqlsrv.password', env('DB_SQLSRV_PASSWORD', 'Logic@1234'));

        // 1. Socket Connectivity Check (Provides instant friendly error if remote IP is unreachable)
        $timeout = 3;
        $socket = @fsockopen($host, (int)$port, $errno, $errstr, $timeout);
        if (!$socket) {
            throw new \Exception("Cannot connect to MS SQL Server at {$host}:{$port} ({$errstr} [{$errno}]). Note: If {$host} is a local/Tailscale VPN IP, Hostinger cloud server cannot reach it directly. Please configure a public IP / port forward or tunnel for port 1433.");
        }
        fclose($socket);

        $driverErrors = [];

        // 2. Try pdo_sqlsrv (Windows & Linux with Microsoft ODBC)
        if (extension_loaded('pdo_sqlsrv')) {
            try {
                $dsn = "sqlsrv:Server={$host},{$port};Database={$db};TrustServerCertificate=true";
                return new \PDO($dsn, $user, $pass, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::SQLSRV_ATTR_DIRECT_QUERY => true,
                ]);
            } catch (\Exception $e) {
                $driverErrors[] = "pdo_sqlsrv: " . $e->getMessage();
            }
        }

        // 3. Try pdo_dblib (FreeTDS on Linux - Standard on Hostinger/cPanel)
        if (extension_loaded('pdo_dblib')) {
            try {
                $dsn = "dblib:host={$host}:{$port};dbname={$db};charset=utf8";
                return new \PDO($dsn, $user, $pass, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
            } catch (\Exception $e) {
                $driverErrors[] = "pdo_dblib: " . $e->getMessage();
            }
        }

        // 4. Try pdo_odbc
        if (extension_loaded('pdo_odbc')) {
            try {
                $dsn = "odbc:Driver={FreeTDS};Server={$host};Port={$port};Database={$db};UID={$user};PWD={$pass};TDS_Version=7.4";
                return new \PDO($dsn, $user, $pass, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
            } catch (\Exception $e) {
                $driverErrors[] = "pdo_odbc: " . $e->getMessage();
            }
        }

        $allErrors = implode(' | ', $driverErrors);
        throw new \Exception("MS SQL Driver Error: " . ($allErrors ?: "No compatible MS SQL PDO extension enabled on this server. Please enable 'pdo_dblib' or 'pdo_sqlsrv' in Hostinger PHP Extensions."));
    }

    public function exportSalesQuery(Request $request)
    {
        @set_time_limit(300);
        $query = trim($request->input('query', ''));

        if (empty($query)) {
            return back()->with('error', 'No query provided to export.');
        }

        // Security check
        $cleanQuery = ltrim($query);
        $firstWord = strtoupper(strtok($cleanQuery, " \t\n\r"));
        if (!in_array($firstWord, ['SELECT', 'WITH'])) {
            return back()->with('error', 'Only SELECT queries can be exported.');
        }

        try {
            $results = \Illuminate\Support\Facades\DB::connection('sqlsrv')->select($query);
            if (empty($results)) {
                return back()->with('error', 'Query returned 0 rows to export.');
            }

            $firstObj = (array)$results[0];
            $columns = array_keys($firstObj);

            $filename = 'Sales_Report_' . now()->format('Y-m-d_His') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            $callback = function () use ($results, $columns) {
                $file = fopen('php://output', 'w');
                // UTF-8 BOM for Excel compatibility
                fputs($file, "\xEF\xBB\xBF");
                fputcsv($file, $columns);

                foreach ($results as $item) {
                    $row = (array)$item;
                    $line = [];
                    foreach ($columns as $col) {
                        $val = $row[$col] ?? '';
                        if ($val instanceof \DateTimeInterface) {
                            $val = $val->format('Y-m-d H:i:s');
                        }
                        $line[] = $val;
                    }
                    fputcsv($file, $line);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return back()->with('error', 'Query Export Error: ' . $e->getMessage());
        }
    }

    public function syncSalesReport(Request $request)
    {
        try {
            $count = $this->syncSalesReportRaw();
            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$count} sales records from ERP API into database.",
            ]);
        } catch (\Exception $e) {
            Log::error('Sales Register Sync Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function syncSalesReportRaw()
    {
        @set_time_limit(300);

        $baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
        $apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');

        // FY auto-dates
        $now     = now();
        $fyStart = $now->month >= 4 ? $now->year . '-04-01' : ($now->year - 1) . '-04-01';
        $fyEnd   = $now->format('Y-m-d'); // Fetch only up to today to minimize API processing time

        $actCode   = AppSetting::get('sales_api_actcode', 'ALL') ?: 'ALL';
        $agentCode = AppSetting::get('sales_api_agentcode', 'ALL') ?: 'ALL';
        $item      = AppSetting::get('sales_api_item', 'ALL') ?: 'ALL';
        $usercode  = AppSetting::get('sales_api_usercode', 'ALL') ?: 'ALL';
        $branch    = AppSetting::get('sales_api_branch', 'ALL') ?: 'ALL';

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->post("{$baseUrl}/PartyWiseProductWiseSales", [
                'apikey'    => $apiKey,
                'FromDate'  => $fyStart,
                'ToDate'    => $fyEnd,
                'ActCode'   => $actCode,
                'AgentCode' => $agentCode,
                'Item'      => $item,
                'Usercode'  => $usercode,
                'Branch'    => $branch,
            ]);

        if (!$response->successful()) {
            throw new \Exception('ERP API request failed.');
        }

        $data = $response->json();
        $resultdata = null;
        if (isset($data['response']) && $data['response'] === 'success' && isset($data['resultdata'])) {
            $resultdata = $data['resultdata'];
        } elseif (is_array($data) && !isset($data['response'])) {
            $resultdata = $data;
        }

        if (empty($resultdata)) {
            throw new \Exception('No data returned from ERP API.');
        }

        $insertData = [];
        foreach ($resultdata as $row) {
            $vouchDateStr = $row['Vouch_Date'] ?? $row['VouchDate'] ?? $row['Date'] ?? $row['BillDate'] ?? null;
            $formattedDate = null;
            if ($vouchDateStr) {
                try {
                    if (str_contains($vouchDateStr, '/')) {
                        $formattedDate = \Carbon\Carbon::createFromFormat('d/m/Y', trim($vouchDateStr))->format('Y-m-d');
                    } else {
                        $formattedDate = \Carbon\Carbon::parse(trim($vouchDateStr))->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    $formattedDate = null;
                }
            }

            $actCodeVal   = trim($row['ActCode'] ?? $row['PartyCode'] ?? $row['AC_Code'] ?? $row['AcCode'] ?? '');
            $actNameVal   = trim($row['ActName'] ?? $row['PartyName'] ?? $row['AC_Name'] ?? $row['AcName'] ?? '');
            $agentCodeVal = trim($row['AgentCode'] ?? $row['SalesmanCode'] ?? '');
            $agentNameVal = trim($row['AgentName'] ?? $row['SalesmanName'] ?? $row['Agent_Name'] ?? '');
            $itemCodeVal  = trim($row['User_Code'] ?? $row['ItemCode'] ?? $row['ProductCode'] ?? '');
            $itemNameVal  = trim($row['Item_Hd_Name'] ?? $row['ItemName'] ?? $row['ProductName'] ?? '');
            $qtyVal       = (float)($row['Qty'] ?? $row['Quantity'] ?? 0);
            $amtVal       = (float)($row['Amount'] ?? $row['NetAmt'] ?? $row['SalesValue'] ?? 0);
            $branchVal    = trim($row['Branch'] ?? $row['BranchName'] ?? $row['Branch_Code'] ?? '');

            if (empty($actCodeVal) && empty($itemCodeVal) && empty($itemNameVal)) {
                continue;
            }

            $insertData[] = [
                'vouch_date'  => $formattedDate,
                'act_code'    => $actCodeVal,
                'act_name'    => $actNameVal,
                'agent_code'  => $agentCodeVal,
                'agent_name'  => $agentNameVal,
                'item_code'   => $itemCodeVal,
                'item_name'   => $itemNameVal,
                'qty'         => $qtyVal,
                'amount'      => $amtVal,
                'branch'      => $branchVal,
                'raw_data'    => json_encode($row),
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        $count = count($insertData);
        if ($count > 0) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($insertData) {
                \App\Models\SalesRegister::truncate();
                foreach (array_chunk($insertData, 500) as $chunk) {
                    \App\Models\SalesRegister::insert($chunk);
                }
            });
        }

        return $count;
    }

    /**
     * Clean / Delete Specific FY or All Synced Sales Records
     */
    public function cleanSalesData(Request $request)
    {
        $yearChoice = $request->input('year_choice', '20262027');
        $deletedCount = 0;

        if (!Schema::hasTable('mssql_sales_records')) {
            return response()->json(['status' => 'error', 'message' => "Table 'mssql_sales_records' does not exist."], 404);
        }

        try {
            if ($yearChoice === '20262027') {
                $deletedCount = DB::table('mssql_sales_records')->whereBetween('vouch_date', ['2026-04-01', '2027-03-31'])->delete();
                $label = 'FY 2026-2027 (Current Year)';
            } elseif ($yearChoice === '20252026') {
                $deletedCount = DB::table('mssql_sales_records')->whereBetween('vouch_date', ['2025-04-01', '2026-03-31'])->delete();
                $label = 'FY 2025-2026 (Previous Year)';
            } elseif ($yearChoice === '20242025') {
                $deletedCount = DB::table('mssql_sales_records')->whereBetween('vouch_date', ['2024-04-01', '2025-03-31'])->delete();
                $label = 'FY 2024-2025 (Historical)';
            } elseif ($yearChoice === 'all') {
                $deletedCount = DB::table('mssql_sales_records')->count();
                DB::table('mssql_sales_records')->truncate();
                $label = 'All Financial Years (Complete Reset)';
            } else {
                return response()->json(['status' => 'error', 'message' => 'Invalid year selection.'], 422);
            }

            return response()->json([
                'status'        => 'success',
                'deleted_count' => $deletedCount,
                'message'       => "🎉 Successfully cleaned {$deletedCount} records for {$label}."
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}


