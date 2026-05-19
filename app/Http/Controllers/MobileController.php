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
use App\Models\Adjustment;
use App\Models\ProductType;
use App\Models\ProductGroup;
use App\Models\ProductSyncLog;
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
            [
                'name' => 'Recipes',
                'icon' => 'fas fa-flask',
                'route' => 'mobile.recipes',
                'color' => 'bg-indigo-600',
                'permission' => 'mobile_recipes'
            ],
            [
                'name' => 'Adjustments',
                'icon' => 'fas fa-sliders',
                'route' => 'mobile.adjustments',
                'color' => 'bg-emerald-600',
                'permission' => 'mobile_adjustments'
            ],
            [
                'name' => 'Ledger',
                'icon' => 'fas fa-book',
                'route' => 'mobile.ledger',
                'color' => 'bg-rose-600',
                'permission' => 'mobile_ledger'
            ],
            [
                'name' => 'Products',
                'icon' => 'fas fa-boxes-stacked',
                'route' => 'mobile.products',
                'color' => 'bg-slate-700',
                'permission' => 'mobile_products'
            ],
            [
                'name' => 'Users',
                'icon' => 'fas fa-users-gear',
                'route' => 'mobile.users',
                'color' => 'bg-violet-600',
                'permission' => 'mobile_users'
            ],
            [
                'name' => 'Settings',
                'icon' => 'fas fa-gears',
                'route' => 'mobile.settings',
                'color' => 'bg-cyan-600',
                'permission' => 'mobile_settings'
            ],
        ];

        // Filter modules based on permissions
        $modules = array_values(array_filter($allModules, function($m) use ($user) {
            return $user->hasPermission($m['permission'], 'view');
        }));

        $permittedBranches = $user->getPermittedBranchCodes();

        $stats = Cache::remember('mobile_dashboard_stats_' . $user->id, 600, function() use ($permittedBranches) {
            return [
                'products' => Product::count(),
                'today_indents' => Indent::whereDate('created_at', today())->count(),
                'pending_indents' => Indent::where('status', 'pending')->count(),
                'total_stock' => (float)Product::sum('current_stock'),
                'finished_goods' => Product::where('product_type_id', 1)->count(), // Assuming 1 is FG
                'raw_materials' => Product::where('product_type_id', 2)->count(),  // Assuming 2 is RM
                'last_production' => \App\Models\StockLedger::where('transaction_type', 'production_add')->latest()->first()?->created_at?->diffForHumans() ?? 'No records',
                'low_stock_count' => Product::whereColumn('current_stock', '<=', 'low_alert_quantity')->count(),
                'today_production_boxes' => \App\Models\ProductionItem::whereHas('production', function($q) use ($permittedBranches) {
                    $q->whereIn('branch_code', $permittedBranches)
                      ->whereDate('production_date', today());
                })->sum('quantity_box'),
            ];
        });
        $stats['permitted_branches'] = $permittedBranches;

        // Fetch Recent Combined Activity (Indents + Production)
        $recentIndents = Indent::whereIn('branch_code', $permittedBranches)
            ->with('user')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function($i) {
                return [
                    'type' => 'Indent',
                    'title' => "New Indent: #IND-" . str_pad($i->id, 4, '0', STR_PAD_LEFT),
                    'subtitle' => $i->branch_name,
                    'time' => $i->created_at->diffForHumans(),
                    'icon' => 'fas fa-file-invoice',
                    'color' => 'bg-blue-500',
                    'raw_time' => $i->created_at
                ];
            });

        $recentProduction = \App\Models\Production::whereIn('branch_code', $permittedBranches)
            ->with(['items.product', 'user'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function($p) {
                $firstItem = $p->items->first();
                $count = $p->items->count();
                $title = "Produced: " . ($firstItem->product->name ?? 'Mixed Items');
                if($count > 1) $title .= " +" . ($count - 1) . " more";

                return [
                    'type' => 'Production',
                    'title' => $title,
                    'subtitle' => $p->branch_name ?? $p->branch_code,
                    'time' => $p->created_at->diffForHumans(),
                    'icon' => 'fas fa-industry',
                    'color' => 'bg-green-500',
                    'raw_time' => $p->created_at
                ];
            });

        $activities = $recentIndents->concat($recentProduction)->sortByDesc('raw_time')->take(5);

        return view('mobile.dashboard', compact('modules', 'stats', 'activities'));
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
        
        $branches = Branch::whereIn('code', $permittedCodes)->orderBy('sort_order')->orderBy('code')->get();
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
            
            // Convert to display unit
            $unitPerBox = (float)($product->unit_box ?: 1);
            
            $branchBreakdown = [];
            foreach ($branches as $branch) {
                $bQty = $externalStock[$branch->code][$product->item_code] ?? 0;
                if ($displayUnit === 'kg') {
                    $branchBreakdown[$branch->code] = $bQty * $product->weight_multiplier;
                } else {
                    $branchBreakdown[$branch->code] = $bQty / $unitPerBox;
                }
            }
            
            if ($displayUnit === 'kg') {
                $stocks[$product->id] = $qty * $product->weight_multiplier;
            } else {
                $stocks[$product->id] = $qty / $unitPerBox;
            }
            
            $product->branch_stocks = $branchBreakdown;
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
        $branches = Branch::whereIn('code', $permittedCodes)->orderBy('sort_order')->orderBy('code')->get();
        
        $history = \App\Models\ProductionItem::whereHas('production', function($q) use ($permittedCodes) {
                $q->whereIn('branch_code', $permittedCodes);
            })
            ->with(['product', 'production'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('mobile.production', compact('products', 'branches', 'history'));
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
        $branches = Branch::whereIn('code', $permittedCodes)->orderBy('sort_order')->orderBy('code')->get();
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
        $branches = Branch::whereIn('code', $permittedCodes)->orderBy('sort_order')->orderBy('code')->get();
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
        $user = Auth::user();
        $permittedCodes = $user->getPermittedBranchCodes();
        
        $indent->load('items.product', 'user');
        $branches = Branch::whereIn('code', $permittedCodes)->orderBy('sort_order')->orderBy('code')->get();
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
        $pdf = Pdf::loadView('indent.indent_pdf', compact('indent'));
        return $pdf->download("Indent_{$indent->id}_{$indent->indent_date}.pdf");
    }

    /**
     * Export Process Matrix to Excel (Mobile)
     */
    public function exportProcessExcel(Indent $indent)
    {
        $user = Auth::user();
        $permittedCodes = $user->getPermittedBranchCodes();
        
        $indent->load('items.product', 'user');
        $branches = Branch::whereIn('code', $permittedCodes)->orderBy('sort_order')->orderBy('code')->get();
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
        $user = Auth::user();
        $permittedCodes = $user->getPermittedBranchCodes();
        
        $indent->load('items.product', 'user');
        $branches = Branch::whereIn('code', $permittedCodes)->orderBy('sort_order')->orderBy('code')->get();
        $branchStocks = $this->getBranchStocksForIndent($indent, $branches);

        $pdf = Pdf::loadView('indent.process_pdf', compact('indent', 'branches', 'branchStocks'))
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
        return Cache::remember('external_stock_data_grouped', 3600, function () {
            try {
                $baseUrl = rtrim(\App\Models\AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
                $apiKey  = \App\Models\AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');
                $branch  = \App\Models\AppSetting::get('inventory_api_branch', 'ALL');
                $item    = \App\Models\AppSetting::get('inventory_api_item', 'ALL');

                $response = Http::withoutVerifying()
                    ->timeout(60)
                    ->connectTimeout(15)
                    ->post("{$baseUrl}/ProductWiseInventory", [
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
                Log::warning('Mobile External Stock API bad/empty response', ['status' => $response->status()]);
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

    /**
     * Mobile Stock Ledger
     */
    public function ledger(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasFeature('mobile_ledger', 'view')) abort(403);

        $query = StockLedger::with('product')->orderByDesc('created_at');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

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
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $productsQuery->whereIn('product_type_id', $permittedTypeIds);
        }
        $products = $productsQuery->get();
        
        return view('mobile.ledger', compact('ledger', 'products'));
    }

    /**
     * Mobile Stock Adjustments
     */
    public function adjustments()
    {
        $user = Auth::user();
        if (!$user->hasFeature('mobile_adjustments', 'view')) abort(403);

        $query = Adjustment::with('product')->orderByDesc('created_at');

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

        $adjustments = $query->limit(50)->get();
        
        $productsQuery = Product::orderBy('name');
        if ($user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $productsQuery->whereIn('product_type_id', $permittedTypeIds);
        }
        $products = $productsQuery->get();
        
        return view('mobile.adjustments', compact('adjustments', 'products'));
    }

    /**
     * Store Mobile Stock Adjustment
     */
    public function storeAdjustment(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasFeature('mobile_adjustments', 'create')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'adjustment_type' => 'required|in:add,deduct',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $product = Product::lockForUpdate()->find($validated['product_id']);

                if ($validated['adjustment_type'] === 'deduct' && $product->current_stock < $validated['quantity']) {
                    throw new \Exception("Insufficient current stock. Available: {$product->current_stock}");
                }

                $adjustment = Adjustment::create($validated);
                $changeQty = $validated['adjustment_type'] === 'add' ? $validated['quantity'] : -$validated['quantity'];
                
                if ($validated['adjustment_type'] === 'add') {
                    $product->increment('current_stock', $validated['quantity']);
                } else {
                    $product->decrement('current_stock', $validated['quantity']);
                }

                StockLedger::create([
                    'product_id' => $product->id,
                    'transaction_type' => 'adjustment_' . $validated['adjustment_type'],
                    'transaction_id' => $adjustment->id,
                    'change_quantity' => $changeQty,
                    'new_stock' => $product->current_stock,
                ]);
            });

            return response()->json(['success' => true, 'message' => 'Adjustment saved successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mobile Recipe Master
     */
    public function recipes(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasFeature('mobile_recipes', 'view')) abort(403);

        $query = Recipe::with(['finishedProduct.type', 'items.rawMaterial']);

        if ($user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $query->whereHas('finishedProduct', function($q) use ($permittedTypeIds) {
                $q->whereIn('product_type_id', $permittedTypeIds);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('finishedProduct', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%");
            });
        }

        $recipes = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $types = ProductType::orderBy('type_name')->get();

        $fgQuery = Product::orderBy('name');
        $rmQuery = Product::orderBy('name');
        if ($user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $fgQuery->whereIn('product_type_id', $permittedTypeIds);
            $rmQuery->whereIn('product_type_id', $permittedTypeIds);
        }
        $finishedGoods = $fgQuery->get();
        $rawMaterials = $rmQuery->get();

        return view('mobile.recipes', compact('recipes', 'types', 'finishedGoods', 'rawMaterials'));
    }

    /**
     * Mobile Product Master List
     */
    public function products(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasFeature('mobile_products', 'view')) abort(403);

        $query = Product::with('type')->orderBy('name');

        if ($user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $query->whereIn('product_type_id', $permittedTypeIds);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type_id')) {
            $query->where('product_type_id', $request->type_id);
        }
        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('form')) {
            $query->where('form', $request->form);
        }
        if ($request->filled('rm_type')) {
            $query->where('rm_type', $request->rm_type);
        }
        if ($request->filled('pack_name')) {
            $query->where('pack_name', $request->pack_name);
        }

        $products = $query->paginate(30)->withQueryString();
        $types = ProductType::orderBy('type_name')->get();
        $groups = \App\Models\ProductGroup::orderBy('group_name')->get();
        $categories = Product::select('category')->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category');
        $forms = Product::select('form')->whereNotNull('form')->where('form', '!=', '')->distinct()->orderBy('form')->pluck('form');
        $rmTypes = Product::select('rm_type')->whereNotNull('rm_type')->where('rm_type', '!=', '')->distinct()->orderBy('rm_type')->pluck('rm_type');
        $packs = Product::select('pack_name')->whereNotNull('pack_name')->where('pack_name', '!=', '')->distinct()->orderBy('pack_name')->pluck('pack_name');

        return view('mobile.products', compact('products', 'types', 'groups', 'categories', 'forms', 'rmTypes', 'packs'));
    }

    /**
     * Update Product Basics from Mobile
     */
    public function updateProduct(Request $request, Product $product)
    {
        $user = Auth::user();
        if (!$user->hasFeature('mobile_products', 'edit')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'low_alert_quantity' => 'numeric|min:0',
            'product_type_id' => 'required|exists:product_types,id',
        ]);

        $product->update($validated);

        return response()->json(['success' => true, 'message' => 'Product updated!']);
    }

    /**
     * Trigger Product Sync from Mobile
     */
    public function syncProducts()
    {
        $user = Auth::user();
        if (!$user->hasFeature('mobile_products', 'sync')) abort(403);

        // We can redirect to the desktop sync route if we want to share logic, 
        // or just call the sync logic here. I'll use redirect for simplicity and logic reuse.
        return (new ProductController())->syncFromApi();
    }

    /**
     * Mobile User Manager
     */
    public function users()
    {
        if (!Auth::user()->hasFeature('mobile_users', 'view')) abort(403);

        $users = \App\Models\User::with(['permissions', 'branches', 'productTypes'])->orderBy('name')->get();
        $branches = \App\Models\Branch::orderBy('name')->get();
        $productTypes = \App\Models\ProductType::orderBy('type_name')->get();
        $rmTypes = \App\Models\ProductAttribute::where('type', 'rm_type')->orderBy('value')->get();

        // Pass module definition from UserController for permission toggles
        $userController = new UserController();
        $modules = (new \ReflectionClass($userController))->getProperty('modules')->getValue($userController);
        $moduleFeatures = (new \ReflectionClass($userController))->getProperty('moduleFeatures')->getValue($userController);

        return view('mobile.users', compact('users', 'branches', 'productTypes', 'rmTypes', 'modules', 'moduleFeatures'));
    }

    /**
     * Store Mobile User
     */
    public function storeUser(Request $request)
    {
        if (!Auth::user()->hasFeature('mobile_users', 'create')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'role' => 'required|in:user,admin',
            'interface_type' => 'required|in:desktop,mobile',
            'password' => 'required|min:4',
        ]);

        try {
            \App\Models\User::create([
                'name' => $request->name,
                'username' => $request->username,
                'role' => $request->role,
                'interface_type' => $request->interface_type,
                'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            ]);

            return response()->json(['success' => true, 'message' => 'User created successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update User Permissions from Mobile
     */
    public function updateUserPermissions(Request $request, \App\Models\User $user)
    {
        if (!Auth::user()->hasFeature('mobile_users', 'edit')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        try {
            DB::transaction(function () use ($request, $user) {
                // Sync Branches
                if ($request->has('branches')) {
                    $user->branches()->sync($request->branches);
                }

                // Sync Product Types
                if ($request->has('product_types')) {
                    $user->productTypes()->sync($request->product_types);
                }

                // Sync Features (via UserPermission model)
                if ($request->has('features')) {
                    \App\Models\UserPermission::where('user_id', $user->id)->delete();
                    foreach ($request->features as $module => $features) {
                        foreach ($features as $feature => $allowed) {
                            if ($allowed) {
                                \App\Models\UserPermission::create([
                                    'user_id' => $user->id,
                                    'module' => $module,
                                    'feature' => $feature,
                                    'is_allowed' => true
                                ]);
                            }
                        }
                    }
                }
            });

            return response()->json(['success' => true, 'message' => 'Permissions updated!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mobile Settings
     */
    public function settings()
    {
        if (!Auth::user()->hasFeature('mobile_settings', 'management')) abort(403);

        $branches = Branch::orderBy('code')->get();
        return view('mobile.settings', compact('branches'));
    }

    /**
     * Store Branch from Mobile Settings
     */
    public function storeBranch(Request $request)
    {
        if (!Auth::user()->hasFeature('mobile_settings', 'management')) abort(403);

        $request->validate([
            'code' => 'required|string|unique:branches,code',
            'name' => 'required|string',
        ]);

        Branch::create($request->all());

        return response()->json(['success' => true, 'message' => 'Branch added successfully!']);
    }

    /**
     * Delete Branch
     */
    public function deleteBranch(Branch $branch)
    {
        if (!Auth::user()->hasFeature('mobile_settings', 'management')) abort(403);

        $branch->delete();
        return response()->json(['success' => true, 'message' => 'Branch deleted!']);
    }

    /**
     * Mobile Product Types
     */
    public function productTypes()
    {
        if (!Auth::user()->hasFeature('mobile_settings', 'management')) abort(403);
        $types = \App\Models\ProductType::orderBy('type_name')->get();
        return response()->json(['success' => true, 'types' => $types]);
    }

    /**
     * Mobile Store Product Type
     */
    public function storeProductType(Request $request)
    {
        if (!Auth::user()->hasFeature('mobile_settings', 'management')) abort(403);
        $request->validate(['type_name' => 'required|string|unique:product_types,type_name']);
        $type = \App\Models\ProductType::create($request->all());
        return response()->json(['success' => true, 'message' => 'Type added!', 'type' => $type]);
    }

    /**
     * Mobile Product Groups
     */
    public function productGroups()
    {
        if (!Auth::user()->hasFeature('mobile_settings', 'management')) abort(403);
        $groups = \App\Models\ProductGroup::orderBy('group_name')->get();
        return response()->json(['success' => true, 'groups' => $groups]);
    }

    /**
     * Mobile Store Product Group
     */
    public function storeProductGroup(Request $request)
    {
        if (!Auth::user()->hasFeature('mobile_settings', 'management')) abort(403);
        $request->validate(['group_name' => 'required|string|unique:product_groups,group_name']);
        $group = \App\Models\ProductGroup::create($request->all());
        return response()->json(['success' => true, 'message' => 'Group added!', 'group' => $group]);
    }

    /**
     * Mobile Store Product
     */
    public function storeProduct(Request $request)
    {
        if (!Auth::user()->hasFeature('mobile_products', 'edit')) abort(403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'item_code' => 'required|string|unique:products,item_code',
            'product_type_id' => 'required|exists:product_types,id',
            'uom' => 'nullable|string',
            'unit_box' => 'nullable|numeric|min:1',
            'weight_unit' => 'nullable|numeric|min:0.001',
        ]);

        try {
            $product = Product::create($validated);
            return response()->json(['success' => true, 'message' => 'Product created successfully!', 'product' => $product]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mobile Export Planning
     */
    public function exportPlanning(Request $request)
    {
        if (!Auth::user()->hasFeature('mobile_planning', 'view')) abort(403);
        
        $productsInput = json_decode($request->input('data'), true);
        if (empty($productsInput)) return redirect()->back();

        $results = $this->getConsolidatedRequirementsMRP($productsInput);
        $summary = $this->getProductionSummaryMRP($productsInput);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('planning.mrp_pdf', [
            'results' => $results,
            'summary' => $summary,
            'branch' => 'All Branches'
        ]);

        return $pdf->download('MRP_Report_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Mobile Show Recipe Details
     */
    public function showRecipe(Recipe $recipe)
    {
        if (!Auth::user()->hasFeature('mobile_recipes', 'view')) abort(403);
        return response()->json([
            'success' => true,
            'recipe' => $recipe->load('items.rawMaterial')
        ]);
    }

    /**
     * Mobile Store Recipe
     */
    public function storeRecipe(Request $request)
    {
        if (!Auth::user()->hasFeature('mobile_recipes', 'edit')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'finished_product_id' => 'required|exists:products,id|unique:recipes,finished_product_id',
            'yield_quantity' => 'required|numeric|min:0.001',
            'yield_uom' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $recipe = Recipe::create([
                    'finished_product_id' => $validated['finished_product_id'],
                    'yield_quantity' => $validated['yield_quantity'],
                    'yield_uom' => $validated['yield_uom'],
                ]);

                foreach ($validated['items'] as $item) {
                    \App\Models\RecipeItem::create([
                        'recipe_id' => $recipe->id,
                        'raw_material_id' => $item['raw_material_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }
            });

            return response()->json(['success' => true, 'message' => 'Recipe created successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mobile Update Recipe
     */
    public function updateRecipe(Request $request, Recipe $recipe)
    {
        if (!Auth::user()->hasFeature('mobile_recipes', 'edit')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'finished_product_id' => 'required|exists:products,id|unique:recipes,finished_product_id,' . $recipe->id,
            'yield_quantity' => 'required|numeric|min:0.001',
            'yield_uom' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        try {
            DB::transaction(function () use ($recipe, $validated) {
                $recipe->update([
                    'finished_product_id' => $validated['finished_product_id'],
                    'yield_quantity' => $validated['yield_quantity'],
                    'yield_uom' => $validated['yield_uom'],
                ]);

                $recipe->items()->delete();

                foreach ($validated['items'] as $item) {
                    \App\Models\RecipeItem::create([
                        'recipe_id' => $recipe->id,
                        'raw_material_id' => $item['raw_material_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }
            });

            return response()->json(['success' => true, 'message' => 'Recipe updated successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mobile Delete Recipe
     */
    public function deleteRecipe(Recipe $recipe)
    {
        if (!Auth::user()->hasFeature('mobile_recipes', 'delete')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        try {
            $recipe->delete();
            return response()->json(['success' => true, 'message' => 'Recipe deleted successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
