<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Indent;
use App\Models\StockLedger;
use Illuminate\Http\Request;
use App\Models\Branch;
use App\Models\Recipe;
use App\Models\Production;
use App\Models\ProductionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class MobileController extends Controller implements HasMiddleware
{
    /**
     * Enforce mobile access permission.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware(function ($request, $next) {
                $user = Auth::user();
                $route = $request->route()->getName();
                
                $permissionMap = [
                    'mobile.stock' => 'mobile_stock',
                    'mobile.production' => 'mobile_production',
                    'mobile.production.store' => 'mobile_production',
                    'mobile.planning' => 'mobile_planning',
                    'mobile.planning.calculate' => 'mobile_planning',
                    'mobile.indents' => 'mobile_indents',
                    'mobile.indents.store' => 'mobile_indents',
                    'mobile.indents.process' => 'mobile_indents',
                    'mobile.indents.completion' => 'mobile_indents',
                    'mobile.indents.excel' => 'mobile_indents',
                    'mobile.indents.pdf' => 'mobile_indents',
                    'mobile.indents.process.excel' => 'mobile_indents',
                    'mobile.indents.process.pdf' => 'mobile_indents',
                    'mobile.stock.excel' => 'mobile_stock',
                    'mobile.stock.pdf' => 'mobile_stock',
                ];

                if (isset($permissionMap[$route])) {
                    if (!$user->hasPermission($permissionMap[$route], 'view')) {
                        abort(403, 'Unauthorized access to mobile module.');
                    }
                }

                return $next($request);
            }),
        ];
    }

    /**
     * Display the mobile dashboard with permitted modules.
     */
    public function index()
    {
        $user = Auth::user();
        
        // Define all available mobile-friendly modules and their corresponding permission keys
        $allModules = [
            [
                'name' => 'Live Stock',
                'icon' => 'fas fa-boxes',
                'route' => 'mobile.stock',
                'color' => 'bg-indigo-500',
                'permission' => 'mobile_stock'
            ],
            [
                'name' => 'Production',
                'icon' => 'fas fa-industry',
                'route' => 'mobile.production',
                'color' => 'bg-green-500',
                'permission' => 'mobile_production'
            ],
            [
                'name' => 'Planning',
                'icon' => 'fas fa-calendar-alt',
                'route' => 'mobile.planning',
                'color' => 'bg-amber-500',
                'permission' => 'mobile_planning'
            ],
            [
                'name' => 'Indents',
                'icon' => 'fas fa-list-check',
                'route' => 'mobile.indents',
                'color' => 'bg-blue-500',
                'permission' => 'mobile_indents'
            ],
        ];

        // Filter modules based on permissions
        $modules = array_values(array_filter($allModules, function($m) use ($user) {
            return $user->hasPermission($m['permission'], 'view');
        }));

        $stats = [
            'products' => Product::count(),
            'today_indents' => Indent::whereDate('created_at', today())->count(),
            'pending_indents' => Indent::where('status', 'pending')->count(),
            'total_stock' => (float)Product::sum('current_stock'),
            'permitted_branches' => $user->getPermittedBranchCodes(),
            'finished_goods' => Product::where('product_type_id', 1)->count(), // Assuming 1 is FG
            'raw_materials' => Product::where('product_type_id', 2)->count(),  // Assuming 2 is RM
            'last_production' => \App\Models\StockLedger::where('transaction_type', 'production_add')->latest()->first()?->created_at?->diffForHumans() ?? 'No records',
        ];

        return view('mobile.dashboard', compact('modules', 'stats'));
    }

    /**
     * Mobile Stock Viewer
     */
    public function stock(Request $request)
    {
        $user = Auth::user();
        $permittedCodes = $user->getPermittedBranchCodes();
        
        $permittedTypeIds = $user->getPermittedProductTypeIds();
        $permittedRMTypes = $user->getPermittedRMTypes();
        
        $productsQuery = Product::orderBy('name');
        
        // Base Permissions
        if ($user->role !== 'admin') {
            $productsQuery->whereIn('product_type_id', $permittedTypeIds)
                ->where(function($q) use ($permittedRMTypes) {
                    $q->whereIn('rm_type', $permittedRMTypes)
                      ->orWhereNull('rm_type')
                      ->orWhere('rm_type', '');
                });
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->get('search');
            $productsQuery->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('item_code', 'like', "%$search%")
                  ->orWhere('alias_name', 'like', "%$search%");
            });
        }

        // Category Filters
        if ($request->filled('type_id')) {
            $productsQuery->where('product_type_id', $request->type_id);
        }
        if ($request->filled('rm_type')) {
            $productsQuery->where('rm_type', $request->rm_type);
        }
        
        // Pagination (20 items)
        $paginatedProducts = $productsQuery->paginate(20);
        $products = $paginatedProducts->items();
        
        $branches = Branch::whereIn('code', $permittedCodes)->orderBy('code')->get();
        $selectedBranch = $request->get('branch');
        
        // If selected branch is not in permitted list, reset it
        if ($selectedBranch && !in_array($selectedBranch, $permittedCodes)) {
            $selectedBranch = null;
        }

        // Auto-select if only one branch is permitted and none selected
        if (!$selectedBranch && count($permittedCodes) === 1) {
            $selectedBranch = $permittedCodes[0];
        }
        
        $externalStock = $this->getExternalStock();
        $displayUnit = $request->get('display_unit', 'unit');
        $stockFilter = $request->get('stock_filter', 'all');
        
        $stocks = [];
        $filteredProducts = [];

        foreach ($products as $product) {
            $qty = 0;
            if ($selectedBranch && isset($externalStock[$selectedBranch][$product->item_code])) {
                $qty = $externalStock[$selectedBranch][$product->item_code];
            } elseif (!$selectedBranch) {
                foreach ($externalStock as $bCode => $items) {
                    if (in_array($bCode, $permittedCodes)) {
                        $qty += ($items[$product->item_code] ?? 0);
                    }
                }
            }
            
            // Filter out zero stock if requested
            if ($stockFilter === 'ignore_zero' && $qty <= 0) {
                continue;
            }

            // Convert to display unit
            $unitPerBox = (float)($product->unit_box ?: 1);
            $weightPerUnit = (float)($product->weight_unit ?: 1);
            
            if ($displayUnit === 'kg') {
                $stocks[$product->id] = $qty * $product->weight_multiplier;
            } else {
                $stocks[$product->id] = $qty / $unitPerBox;
            }
            
            $filteredProducts[] = $product;
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'products' => $filteredProducts,
                'stocks' => $stocks,
                'hasMore' => $paginatedProducts->hasMorePages(),
                'nextPage' => $paginatedProducts->currentPage() + 1
            ]);
        }

        $productTypes = \App\Models\ProductType::orderBy('type_name')->get();
        $rmTypes = \App\Models\ProductAttribute::where('type', 'rm_type')->orderBy('value')->get();

        return view('mobile.stock', [
            'products' => $filteredProducts,
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'stocks' => $stocks,
            'displayUnit' => $displayUnit,
            'stockFilter' => $stockFilter,
            'hasMore' => $paginatedProducts->hasMorePages(),
            'productTypes' => $productTypes,
            'rmTypes' => $rmTypes
        ]);
    }

    /**
     * Export Mobile Stock to Excel
     */
    public function exportStockExcel(Request $request)
    {
        if (!auth()->user()->hasPermission('mobile_stock', 'excel')) {
            abort(403, 'Unauthorized action.');
        }

        $displayUnit = $request->get('display_unit', 'unit');
        $externalStock = $this->getExternalStock();

        $query = Product::with('recipes')->orderBy('name');
        
        $user = Auth::user();
        $permittedTypeIds = $user->getPermittedProductTypeIds();
        $permittedRMTypes = $user->getPermittedRMTypes();

        if ($user->role !== 'admin') {
            $query->whereIn('product_type_id', $permittedTypeIds)
                  ->where(function($q) use ($permittedRMTypes) {
                      $q->whereIn('rm_type', $permittedRMTypes)
                        ->orWhereNull('rm_type')
                        ->orWhere('rm_type', '');
                  });
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('item_code', 'like', "%$search%");
            });
        }

        if ($request->filled('type_id')) {
            $query->where('product_type_id', $request->type_id);
        }

        if ($request->filled('rm_type')) {
            $query->where('rm_type', $request->rm_type);
        }

        $products = $query->get();
        $selectedBranch = $request->get('branch');

        if ($request->get('stock_filter') === 'ignore_zero') {
            $products = $products->filter(function($product) use ($externalStock, $selectedBranch) {
                $qty = 0;
                if ($selectedBranch && isset($externalStock[$selectedBranch][$product->item_code])) {
                    $qty = $externalStock[$selectedBranch][$product->item_code];
                } elseif (!$selectedBranch) {
                    foreach ($externalStock as $items) {
                        $qty += ($items[$product->item_code] ?? 0);
                    }
                }
                return $qty > 0;
            });
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\MobileStockExport($products, $externalStock, $displayUnit, $selectedBranch), 
            "Mobile_Stock_Report_" . now()->format('Y-m-d_His') . ".xlsx"
        );
    }

    /**
     * Export Mobile Stock to PDF
     */
    public function exportStockPdf(Request $request)
    {
        if (!auth()->user()->hasPermission('mobile_stock', 'pdf')) {
            abort(403, 'Unauthorized action.');
        }

        $displayUnit = $request->get('display_unit', 'unit');
        $externalStock = $this->getExternalStock();
        $query = Product::orderBy('name');
        
        $user = Auth::user();
        if ($user->role !== 'admin') {
            $query->whereIn('product_type_id', $user->getPermittedProductTypeIds())
                  ->where(function($q) use ($user) {
                      $permittedRMTypes = $user->getPermittedRMTypes();
                      $q->whereIn('rm_type', $permittedRMTypes)
                        ->orWhereNull('rm_type')
                        ->orWhere('rm_type', '');
                  });
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('item_code', 'like', "%$search%");
            });
        }

        if ($request->filled('type_id')) {
            $query->where('product_type_id', $request->type_id);
        }

        if ($request->filled('rm_type')) {
            $query->where('rm_type', $request->rm_type);
        }

        $products = $query->get();
        $selectedBranch = $request->get('branch');

        $reportData = [];
        foreach ($products as $product) {
            $qty = 0;
            if ($selectedBranch && isset($externalStock[$selectedBranch][$product->item_code])) {
                $qty = $externalStock[$selectedBranch][$product->item_code];
            } elseif (!$selectedBranch) {
                foreach ($externalStock as $items) {
                    $qty += ($items[$product->item_code] ?? 0);
                }
            }

            if ($request->get('stock_filter') === 'ignore_zero' && $qty <= 0) {
                continue;
            }

            $unitPerBox = (float)($product->unit_box ?: 1);
            $displayQty = ($displayUnit === 'kg') ? ($qty * $product->weight_multiplier) : ($qty / $unitPerBox);

            $reportData[] = [
                'product' => $product,
                'stock' => $displayQty,
                'unit' => $displayUnit === 'kg' ? ($product->uom ?: 'KG/LT') : 'BOXES'
            ];
        }

        $pdf = Pdf::loadView('mobile.stock_pdf', compact('reportData', 'displayUnit', 'selectedBranch'))
                  ->setPaper('a4', 'portrait');
        
        return $pdf->download("Mobile_Stock_Report_" . now()->format('Y-m-d_His') . ".pdf");
    }

    /**
     * Mobile Production Entry
     */
    public function production()
    {
        $user = Auth::user();
        $permittedCodes = $user->getPermittedBranchCodes();
        $permittedTypeIds = $user->getPermittedProductTypeIds();

        $productsQuery = Product::whereHas('recipes')->orderBy('name');

        if ($user->role !== 'admin') {
            $productsQuery->whereIn('product_type_id', $permittedTypeIds);
        }

        $products = $productsQuery->get();
        $branches = Branch::whereIn('code', $permittedCodes)->orderBy('code')->get();
        return view('mobile.production', compact('products', 'branches'));
    }

    /**
     * Mobile Planning (Placeholder for MRP)
     */
    public function planning()
    {
        $user = Auth::user();
        $permittedCodes = $user->getPermittedBranchCodes();
        
        $permittedTypeIds = $user->getPermittedProductTypeIds();
        $permittedRMTypes = $user->getPermittedRMTypes();
        
        // Use Actual IDs for Finished Good (6) and Semi Finished Good (7)
        $productsQuery = Product::whereHas('recipes')->whereIn('product_type_id', [6, 7])->orderBy('name');
        
        if ($user->role !== 'admin') {
            $productsQuery->whereIn('product_type_id', $permittedTypeIds)
                ->where(function($q) use ($permittedRMTypes) {
                    $q->whereIn('rm_type', $permittedRMTypes)
                      ->orWhereNull('rm_type')
                      ->orWhere('rm_type', '');
                });
        }
        
        $products = $productsQuery->get();
        $branches = Branch::whereIn('code', $permittedCodes)->orderBy('code')->get();
        $types = \App\Models\ProductType::orderBy('type_name')->get();

        // Fetch recent indents for "Plan by Indent" feature
        $indents = Indent::whereIn('branch_code', $permittedCodes)
            ->with(['items.product'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('mobile.planning', compact('products', 'branches', 'types', 'indents'));
    }

    /**
     * Mobile MRP Calculation (Explode Recipe)
     */
    public function calculateMRP(Request $request)
    {
        $productsInput = $request->input('products', []);
        $branchCode = $request->input('branch_code');
        
        if (empty($productsInput)) {
            return response()->json(['success' => false, 'message' => 'No products provided']);
        }

        $results = $this->getConsolidatedRequirementsMRP($productsInput, $branchCode);

        if (is_string($results)) {
            return response()->json(['success' => false, 'message' => $results]);
        }

        $summary = $this->getProductionSummaryMRP($productsInput);

        return response()->json([
            'success' => true, 
            'data' => $results,
            'summary' => $summary
        ]);
    }

    /**
     * Consolidated Requirements for Mobile MRP
     */
    private function getConsolidatedRequirementsMRP($productsInput, $branchCode = null)
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
                $demandInBaseUnit = $demandQty * $product->weight_multiplier;
                $requiredForThis = $requiredForThisPerYield * $demandInBaseUnit;

                if (isset($totalRequirements[$rm->id])) {
                    $totalRequirements[$rm->id]['required_qty'] += $requiredForThis;
                } else {
                    $currentStock = 0;
                    if ($branchCode && isset($externalStock[$branchCode][$rm->item_code])) {
                        $currentStock = $externalStock[$branchCode][$rm->item_code];
                    } else if (!$branchCode) {
                        $permittedCodes = Auth::user()->getPermittedBranchCodes();
                        foreach ($externalStock as $bCode => $items) {
                            if (in_array($bCode, $permittedCodes)) {
                                $currentStock += ($items[$rm->item_code] ?? 0);
                            }
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

    /**
     * Production Summary for Mobile MRP
     */
    private function getProductionSummaryMRP($productsInput)
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

    /**
     * Mobile Indent List
     */
    public function indents(Request $request)
    {
        $user = Auth::user();
        $permittedCodes = $user->getPermittedBranchCodes();

        $query = Indent::whereIn('branch_code', $permittedCodes)->with('user')->withCount('items');

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

        $indents = $query->orderByDesc('created_at')->paginate(20);
        
        $permittedTypeIds = $user->getPermittedProductTypeIds();
        $permittedRMTypes = $user->getPermittedRMTypes();
        
        // Use Actual IDs for Finished Good (6) and Semi Finished Good (7)
        $productsQuery = Product::whereIn('product_type_id', [6, 7])->orderBy('name');
        
        if ($user->role !== 'admin') {
            $productsQuery->whereIn('product_type_id', $permittedTypeIds)
                ->where(function($q) use ($permittedRMTypes) {
                    $q->whereIn('rm_type', $permittedRMTypes)
                      ->orWhereNull('rm_type')
                      ->orWhere('rm_type', '');
                });
        }
        
        $products = $productsQuery->get();
        $branches = Branch::whereIn('code', $permittedCodes)->orderBy('code')->get();
        $users = \App\Models\User::orderBy('name')->get(); // For filtering

        return view('mobile.indents', compact('indents', 'products', 'branches', 'users'));
    }

    /**
     * Fetch Live Stock for Finished Goods (Batch)
     */
    public function getFGStock(Request $request)
    {
        $productIds = $request->input('product_ids', []);
        $branchCode = $request->input('branch_code');
        
        $user = Auth::user();
        $permittedTypeIds = $user->getPermittedProductTypeIds();
        $permittedRMTypes = $user->getPermittedRMTypes();

        $query = Product::whereIn('id', $productIds);

        if ($user->role !== 'admin') {
            $query->whereIn('product_type_id', $permittedTypeIds)
                  ->where(function($q) use ($permittedRMTypes) {
                      $q->whereIn('rm_type', $permittedRMTypes)
                        ->orWhereNull('rm_type')
                        ->orWhere('rm_type', '');
                  });
        }

        $products = $query->get();
        $externalStock = $this->getExternalStock();

        $stocks = [];
        foreach ($products as $product) {
            $stock = 0;
            $permittedCodes = $user->getPermittedBranchCodes();
            
            if ((int)$branchCode && in_array((int)$branchCode, $permittedCodes) && isset($externalStock[(int)$branchCode][$product->item_code])) {
                $stock = $externalStock[(int)$branchCode][$product->item_code];
            } else if (!(int)$branchCode) {
                // Sum only for permitted branches
                foreach ($externalStock as $bCode => $items) {
                    if (in_array($bCode, $permittedCodes)) {
                        $stock += ($items[$product->item_code] ?? 0);
                    }
                }
            }

            $unitPerBox = (float)($product->unit_box ?: 1);
            $weightPerUnit = (float)($product->weight_unit ?: 1);

            $stocks[] = [
                'id' => $product->id,
                'stock_kg' => number_format($stock * $product->weight_multiplier, 2, '.', ''),
                'stock_box' => number_format($stock / $unitPerBox, 2, '.', '')
            ];
        }

        return response()->json(['success' => true, 'stocks' => $stocks]);
    }

    /**
     * Store Mobile Indent Submission
     */
    public function storeIndent(Request $request)
    {
        $request->validate([
            'branch_code' => 'required',
            'indent_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.demand_qty' => 'required|numeric|min:0.001',
        ]);

        $branch = Branch::where('code', $request->branch_code)->first();

        try {
            return DB::transaction(function () use ($request, $branch) {
                $indent = Indent::create([
                    'indent_date' => $request->indent_date,
                    'branch_code' => $request->branch_code,
                    'branch_name' => $branch ? $branch->name : 'Consolidated',
                    'user_id' => auth()->id(),
                    'total_boxes' => 0,
                    'status' => 'pending'
                ]);

                $totalBoxes = 0;
                foreach ($request->products as $item) {
                    $product = Product::find($item['id']);
                    if (!$product) continue;

                    \App\Models\IndentItem::create([
                        'indent_id' => $indent->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'demand_qty' => $item['demand_qty'],
                        'demand_unit' => $item['unit'] ?? 'box',
                        'final_qty_box' => $item['final_qty_box'] ?? $item['demand_qty'],
                        'stock_box' => $item['stock_box'] ?? 0,
                        'stock_kg' => $item['stock_kg'] ?? 0,
                    ]);
                    $totalBoxes += ($item['final_qty_box'] ?? $item['demand_qty']);
                }

                $indent->update(['total_boxes' => $totalBoxes]);

                return response()->json(['success' => true, 'message' => 'Indent created successfully!', 'redirect' => route('mobile.indents')]);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mobile Indent Process (Comparison View)
     */
    public function process(Indent $indent)
    {
        $indent->load('items.product', 'user');
        $branches = Branch::orderBy('code')->get();
        $branchStocks = $this->getBranchStocksForIndent($indent, $branches);

        return view('mobile.process', compact('indent', 'branches', 'branchStocks'));
    }

    /**
     * Export Indent to Excel (Mobile)
     */
    public function exportExcel(Indent $indent)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\IndentExport($indent), 
            "Indent_{$indent->id}_{$indent->indent_date}.xlsx"
        );
    }

    /**
     * Export Indent to PDF (Mobile)
     */
    public function exportPdf(Indent $indent)
    {
        $indent->load('items.product', 'user');
        $pdf = Pdf::loadView('planning.indent_pdf', compact('indent'));
        return $pdf->download("Indent_{$indent->id}_{$indent->indent_date}.pdf");
    }

    /**
     * Export Process Matrix to Excel (Mobile)
     */
    public function exportProcessExcel(Indent $indent)
    {
        $indent->load('items.product', 'user');
        $branches = Branch::orderBy('code')->get();
        $branchStocks = $this->getBranchStocksForIndent($indent, $branches);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\IndentProcessExport($indent, $branches, $branchStocks), 
            "Process_Matrix_{$indent->id}_{$indent->indent_date}.xlsx"
        );
    }

    /**
     * Export Process Matrix to PDF (Mobile)
     */
    public function exportProcessPdf(Indent $indent)
    {
        $indent->load('items.product', 'user');
        $branches = Branch::orderBy('code')->get();
        $branchStocks = $this->getBranchStocksForIndent($indent, $branches);

        $pdf = Pdf::loadView('planning.process_pdf', compact('indent', 'branches', 'branchStocks'))
                  ->setPaper('a4', 'landscape');
        
        return $pdf->download("Process_Matrix_{$indent->id}_{$indent->indent_date}.pdf");
    }

    /**
     * Mobile Indent Completion Update
     */
    public function updateCompletion(Request $request, Indent $indent)
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

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Status updated to ' . strtoupper($status)]);
        }

        return redirect()->back()->with('success', 'Indent status updated to ' . strtoupper($status));
    }

    /**
     * Helper: Get Cross-Branch Stock for Indent Items
     */
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
                $branchStocks[$p->id][$branch->code] = $rawStock / $unitPerBox;
            }
        }
        return $branchStocks;
    }

    /**
     * Helper: Fetch Global Stock Data from Algebra ERP
     */
    private function getExternalStock()
    {
        return Cache::remember('external_stock_data_grouped', 300, function () {
            try {
                $response = Http::timeout(30)->post('https://logicapi.algebraerp.com/API/SYNWOOD/ProductWiseInventory', [
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
                Log::error('External Stock API Error (Mobile): ' . $e->getMessage());
            }
            return [];
        });
    }

    /**
     * Submit Production from Mobile
     */
    public function submitProduction(Request $request)
    {
        $request->validate([
            'branch_code' => 'required',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:1',
        ]);

        $branch = Branch::where('code', $request->branch_code)->first();

        try {
            DB::transaction(function () use ($request, $branch) {
                $production = Production::create([
                    'production_date' => now(),
                    'branch_code' => $request->branch_code,
                    'branch_name' => $branch ? $branch->name : $request->branch_code,
                    'user_id' => auth()->id(),
                ]);

                $product = Product::find($request->product_id);
                
                // Server-side permission check
                if (auth()->user()->role !== 'admin') {
                    $permittedTypeIds = auth()->user()->getPermittedProductTypeIds();
                    if (!in_array($product->product_type_id, $permittedTypeIds)) {
                        throw new \Exception('Unauthorized product type for this user.');
                    }
                }

                $recipe = Recipe::where('finished_product_id', $product->id)->with('items')->first();

                ProductionItem::create([
                    'production_id' => $production->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'pack_size' => $product->pack_name,
                    'quantity_box' => $request->quantity,
                ]);

                $product->increment('current_stock', $request->quantity);
                StockLedger::create([
                    'product_id' => $product->id,
                    'transaction_type' => 'production_add',
                    'transaction_id' => $production->id,
                    'change_quantity' => $request->quantity,
                    'new_stock' => $product->current_stock,
                ]);

                if ($recipe) {
                    foreach ($recipe->items as $recipeItem) {
                        $deductQty = ($recipeItem->quantity / $recipe->yield_quantity) * $request->quantity;
                        $rawMaterial = Product::find($recipeItem->raw_material_id);
                        $rawMaterial->decrement('current_stock', $deductQty);

                        StockLedger::create([
                            'product_id' => $rawMaterial->id,
                            'transaction_type' => 'production_deduct',
                            'transaction_id' => $production->id,
                            'change_quantity' => -$deductQty,
                            'new_stock' => $rawMaterial->current_stock,
                        ]);
                    }
                }
            });

            return response()->json(['success' => true, 'message' => 'Production recorded successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
