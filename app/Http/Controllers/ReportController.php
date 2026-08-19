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

        // Load existing targets for this month
        $targets = \App\Models\AgentTarget::where('target_month', $targetMonth)
            ->get()
            ->pluck('target_amount', 'agent_name')
            ->toArray();

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

        return view('reports.agent_targets', compact('agentOptions', 'targetMonth', 'targets', 'dbTeams', 'teamTargets', 'configuredMonths'));
    }

    /**
     * Batch store agent targets
     */
    public function agentTargetsStore(Request $request)
    {
        $request->validate([
            'month'   => 'required|string',
            'targets' => 'required|array',
        ]);

        $month = $request->input('month');

        foreach ($request->input('targets') as $agentName => $amount) {
            if ($amount === null || $amount === '') {
                \App\Models\AgentTarget::where('agent_name', $agentName)
                    ->where('target_month', $month)
                    ->delete();
                continue;
            }

            \App\Models\AgentTarget::updateOrCreate(
                ['agent_name' => $agentName, 'target_month' => $month],
                ['target_amount' => (float)$amount]
            );
        }

        return redirect()->back()->with('success', 'Agent targets updated successfully!');
    }

    public function teamTargetsStore(Request $request)
    {
        $request->validate([
            'month'         => 'required|string',
            'targets'       => 'required|array',
            'agent_targets' => 'nullable|array',
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

        // Save Team Members (Agents) targets if provided
        if ($request->has('agent_targets')) {
            foreach ($request->input('agent_targets') as $agentName => $amount) {
                if ($amount === null || $amount === '') {
                    \App\Models\AgentTarget::where('agent_name', $agentName)
                        ->where('target_month', $month)
                        ->delete();
                    continue;
                }

                \App\Models\AgentTarget::updateOrCreate(
                    ['agent_name' => $agentName, 'target_month' => $month],
                    ['target_amount' => (float)$amount]
                );
            }
        }

        return redirect()->back()->with('success', 'Team and Member targets updated successfully!');
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

    public function salesReport(Request $request)
    {
        $defaultQuery = "SELECT TOP 50\n" .
            "    BM.branch_name,\n" .
            "    HD.vouch_date,\n" .
            "    HD.Vouch_Time,\n" .
            "    HD.vouch_num,\n" .
            "    ACT.act_name,\n" .
            "    TXN.item_det_code,\n" .
            "    CASE\n" .
            "        WHEN TXN.sale_or_sr = 'SR' THEN TXN.Tot_Qty * -1\n" .
            "        ELSE TXN.Tot_Qty\n" .
            "    END AS Tot_qty,\n" .
            "    CASE\n" .
            "        WHEN TXN.sale_or_sr = 'SR' THEN txn.Calc_Net_Amt * -1\n" .
            "        ELSE TXN.Calc_Net_Amt\n" .
            "    END AS calc_net_amt_n,\n" .
            "    TXN.Free_Qty,\n" .
            "    TXN.rate,\n" .
            "    TXN.Calc_Tax_1,\n" .
            "    TXN.Calc_Tax_2,\n" .
            "    TXN.Calc_Tax_3,\n" .
            "    TXN.calc_commission AS Discount_Rs,\n" .
            "    TXN.Calc_Scheme_Rs,\n" .
            "    TXN.Calc_Gross_Amt,\n" .
            "    TXN.Calc_Net_Amt,\n" .
            "    TXN.sale_or_sr,\n" .
            "    IMD.User_Code,\n" .
            "    IMD.Weight_Per_Unit,\n" .
            "    IMD.Item_Det_Code,\n" .
            "    IMD.cf_1,\n" .
            "    IMD.Item_Hd_Code,\n" .
            "    IMH.item_hd_name,\n" .
            "    LM.lot_number,\n" .
            "    LM.lot_code,\n" .
            "    LM.pur_rate,\n" .
            "    LM.basic_rate,\n" .
            "    CMH.Mobile_no,\n" .
            "    CMD.cust_hd_code,\n" .
            "    CMD.First_name AS CustomerName,\n" .
            "    ACT.act_code,\n" .
            "    BM.branch_code,\n" .
            "    CM.Cashier_name,\n" .
            "    GM1.group_name,\n" .
            "    PM.Pack_Name,\n" .
            "    BS.series\n" .
            "FROM\n" .
            "    Sl_Txn20252026 AS TXN\n" .
            "INNER JOIN\n" .
            "    Sl_Head20252026 AS HD\n" .
            "    ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0\n" .
            "LEFT JOIN\n" .
            "    It_Mst_Det AS IMD\n" .
            "    ON TXN.Item_Det_Code = IMD.Item_Det_Code\n" .
            "LEFT JOIN\n" .
            "    It_Mst_Hd AS IMH\n" .
            "    ON IMD.Item_Hd_Code = IMH.Item_Hd_Code\n" .
            "LEFT JOIN\n" .
            "    Pack_Mst AS PM\n" .
            "    ON IMD.Pack_Code = PM.Pack_Code\n" .
            "LEFT JOIN\n" .
            "    Lot_Mst AS LM\n" .
            "    ON TXN.Lot_Code = LM.Lot_Code\n" .
            "LEFT JOIN\n" .
            "    Group_Mst AS GM1\n" .
            "    ON IMH.Group_Code = GM1.Group_Code\n" .
            "LEFT JOIN\n" .
            "    Cust_Mst_Hd AS CMH\n" .
            "    ON HD.Member_Code = CMH.Cust_Hd_Code\n" .
            "LEFT JOIN\n" .
            "    Cust_Mst_Det AS CMD\n" .
            "    ON CMH.Cust_Hd_Code = CMD.Cust_Hd_Code\n" .
            "LEFT JOIN\n" .
            "    Accounts AS ACT\n" .
            "    ON HD.cust_code = ACT.act_code\n" .
            "LEFT JOIN\n" .
            "    Branch_Mst AS BM\n" .
            "    ON HD.Branch_Code = BM.Branch_Code\n" .
            "LEFT JOIN\n" .
            "    SL_Cashier_Mst AS CM\n" .
            "    ON HD.Cashier_Code = CM.Code\n" .
            "LEFT JOIN\n" .
            "    Bill_Ser AS BS\n" .
            "    ON HD.series_code = BS.series_code\n" .
            "LEFT JOIN\n" .
            "    Agents_Brokers AS AG\n" .
            "    ON HD.agent_code=AG.Code\n" .
            "LEFT JOIN\n" .
            "    AccountGroups AS ACG\n" .
            "    ON ACT.Grp_Code1=ACG.grp_code;";

        $dbHost = config('database.connections.sqlsrv.host', '100.108.74.58');
        $dbName = config('database.connections.sqlsrv.database', 'LOGICDBSY');

        return view('reports.sales_report', compact('defaultQuery', 'dbHost', 'dbName'));
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
        if (!in_array($firstWord, ['SELECT', 'WITH', 'EXEC', 'EXECUTE', 'SET'])) {
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
            ]);
        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::error('MS SQL Sales Query Error: ' . $e->getMessage(), ['query' => $query]);

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
}

