<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Indent;
use App\Models\StockLedger;
use Illuminate\Http\Request;
use App\Models\Branch;
use App\Models\Recipe;
use App\Models\CostingBom;
use App\Models\CostingBomItem;
use App\Models\Production;
use App\Models\ProductionItem;
use App\Models\Adjustment;
use App\Models\ProductType;
use App\Models\ProductGroup;
use App\Models\ProductSyncLog;
use App\Models\ProductPrice;
use App\Models\PurchaseRegister;
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
                    'mobile.stock'                  => 'mobile_stock',
                    'mobile.production'             => 'mobile_production',
                    'mobile.production.store'       => 'mobile_production',
                    'mobile.planning'               => 'mobile_planning',
                    'mobile.planning.calculate'     => 'mobile_planning',
                    'mobile.planning.pdf'           => 'mobile_planning',
                    'mobile.indents'                => 'mobile_indents',
                    'mobile.indents.store'          => 'mobile_indents',
                    'mobile.indent.show'            => 'mobile_indents',
                    'mobile.indents.print'          => 'mobile_indents',
                    'mobile.indents.process'        => 'mobile_indents',
                    'mobile.indents.completion'     => 'mobile_indents',
                    'mobile.indents.excel'          => 'mobile_indents',
                    'mobile.indents.pdf'            => 'mobile_indents',
                    'mobile.indents.process.excel'  => 'mobile_indents',
                    'mobile.indents.process.pdf'    => 'mobile_indents',
                    'mobile.fg-stock'               => 'mobile_indents',
                    'mobile.stock.excel'            => 'mobile_stock',
                    'mobile.stock.pdf'              => 'mobile_stock',
                    'mobile.recipes'                => 'mobile_recipes',
                    'mobile.recipes.show'           => 'mobile_recipes',
                    'mobile.recipes.store'          => 'mobile_recipes',
                    'mobile.recipes.update'         => 'mobile_recipes',
                    'mobile.recipes.destroy'        => 'mobile_recipes',
                    'mobile.adjustments'            => 'mobile_adjustments',
                    'mobile.adjustments.store'      => 'mobile_adjustments',
                    'mobile.ledger'                 => 'mobile_ledger',
                    'mobile.products'               => 'mobile_products',
                    'mobile.products.store'         => 'mobile_products',
                    'mobile.products.update'        => 'mobile_products',
                    'mobile.products.sync'          => 'mobile_products',
                    'mobile.users'                  => 'mobile_users',
                    'mobile.users.store'            => 'mobile_users',
                    'mobile.users.permissions'      => 'mobile_users',
                    'mobile.settings'               => 'mobile_settings',
                    'mobile.settings.branch.store'        => 'mobile_settings',
                    'mobile.settings.branch.delete'       => 'mobile_settings',
                    'mobile.settings.product-types'       => 'mobile_settings',
                    'mobile.settings.product-types.store' => 'mobile_settings',
                    'mobile.settings.product-groups'      => 'mobile_settings',
                    'mobile.settings.product-groups.store'=> 'mobile_settings',
                    'mobile.costing'                => 'mobile_costing',
                    'mobile.costing.calculate'      => 'mobile_costing',
                    'mobile.costing.export'         => 'mobile_costing',
                    'mobile.costing.boms'           => 'mobile_costing_bom',
                    'mobile.costing.boms.store'     => 'mobile_costing_bom',
                    'mobile.costing.boms.duplicate' => 'mobile_costing_bom',
                    'mobile.costing.boms.destroy'   => 'mobile_costing_bom',
                    'mobile.costing.boms.export'    => 'mobile_costing_bom',
                    'mobile.costing.pro'            => 'mobile_costing_pro',
                    'mobile.costing.purchase'       => 'mobile_costing_purchase',
                    'mobile.costing.purchase.sync'  => 'mobile_costing_purchase',
                    'mobile.costing.pricelist'      => 'mobile_costing_pricelist',
                    'mobile.costing.pricelist.update' => 'mobile_costing_pricelist',
                    'mobile.costing.pricelist.sync' => 'mobile_costing_pricelist',
                    'mobile.costing.pricelist-update'      => 'mobile_costing_pricelist_update',
                    'mobile.costing.pricelist-update.items' => 'mobile_costing_pricelist_update',
                    'mobile.costing.pricelist-update.push' => 'mobile_costing_pricelist_update',
                    'mobile.costing.pricelist-update.history' => 'mobile_costing_pricelist_update',
                    'mobile.purchase-report'        => 'mobile_purchase_report',
                    
                    // Collection Report & Targets
                    'mobile.collection-report'                => 'mobile_collection',
                    'mobile.teams.setup'                      => 'mobile_teams_setup',
                    'mobile.reports.collection.teams.store'   => 'mobile_teams_setup',
                    'mobile.reports.collection.teams.update'  => 'mobile_teams_setup',
                    'mobile.reports.collection.teams.destroy' => 'mobile_teams_setup',
                    'mobile.agent-targets.index'              => 'mobile_agent_targets',
                    'mobile.agent-targets.store'              => 'mobile_agent_targets',
                    'mobile.team-targets.store'               => 'mobile_agent_targets',
                    'mobile.teams.setup'                      => 'mobile_teams_setup',
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
                'name'       => 'Settings',
                'icon'       => 'fas fa-gears',
                'route'      => 'mobile.settings',
                'color'      => 'bg-cyan-600',
                'permission' => 'mobile_settings'
            ],
            [
                'name'       => 'Costing BOMs',
                'icon'       => 'fas fa-layer-group',
                'route'      => 'mobile.costing.boms',
                'color'      => 'bg-amber-600',
                'permission' => 'mobile_costing_bom'
            ],
            [
                'name'       => 'Costing Dashboard',
                'icon'       => 'fas fa-chart-pie',
                'route'      => 'mobile.costing.pro',
                'color'      => 'bg-yellow-500',
                'permission' => 'mobile_costing_pro'
            ],
            [
                'name'       => 'Purchase Register',
                'icon'       => 'fas fa-receipt',
                'route'      => 'mobile.costing.purchase',
                'color'      => 'bg-orange-500',
                'permission' => 'mobile_costing_purchase'
            ],
            [
                'name'       => 'Pricelist',
                'icon'       => 'fas fa-tags',
                'route'      => 'mobile.costing.pricelist',
                'color'      => 'bg-rose-500',
                'permission' => 'mobile_costing_pricelist'
            ],
            [
                'name'       => 'Pricelist Update',
                'icon'       => 'fas fa-cloud-arrow-up',
                'route'      => 'mobile.costing.pricelist-update',
                'color'      => 'bg-sky-500',
                'permission' => 'mobile_costing_pricelist_update'
            ],
            [
                'name'       => 'Costing',
                'icon'       => 'fas fa-coins',
                'route'      => 'mobile.costing',
                'color'      => 'bg-yellow-500',
                'permission' => 'mobile_costing'
            ],
            [
                'name'       => 'Purchase Report',
                'icon'       => 'fas fa-shopping-cart',
                'route'      => 'mobile.purchase-report',
                'color'      => 'bg-orange-500',
                'permission' => 'mobile_purchase_report'
            ],
            [
                'name'       => 'Collection Report',
                'icon'       => 'fas fa-wallet',
                'route'      => 'mobile.collection-report',
                'color'      => 'bg-emerald-600',
                'permission' => 'mobile_collection'
            ],
            [
                'name'       => 'Set Targets',
                'icon'       => 'fas fa-bullseye',
                'route'      => 'mobile.agent-targets.index',
                'color'      => 'bg-indigo-600',
                'permission' => 'mobile_agent_targets'
            ],
            [
                'name'       => 'Teams Setup',
                'icon'       => 'fas fa-network-wired',
                'route'      => 'mobile.teams.setup',
                'color'      => 'bg-violet-600',
                'permission' => 'mobile_teams_setup'
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

    public function stock(Request $request)
    {
        if ($request->has('refresh')) {
            Cache::forget('external_stock_data_grouped');
            return redirect()->route('mobile.stock', $request->except('refresh'))
                             ->with('success', 'Live stock data synced successfully from Algebra ERP!');
        }

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
        
        // Selective Products Filter
        $productIds = $request->input('product_ids', []);
        if (!empty($productIds)) {
            $productsQuery->whereIn('id', $productIds);
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

        // All products for the selective picker
        $allProductsQuery = Product::orderBy('name')->select('id', 'name', 'item_code', 'pack_name', 'product_type_id');
        if ($user->role !== 'admin') {
            $allProductsQuery->whereIn('product_type_id', $permittedTypeIds)
                ->where(function($q) use ($permittedRMTypes) {
                    $q->whereIn('rm_type', $permittedRMTypes)
                      ->orWhereNull('rm_type')
                      ->orWhere('rm_type', '');
                });
        }
        $allProducts = $allProductsQuery->get();

        return view('mobile.stock', [
            'products'       => $filteredProducts,
            'branches'       => $branches,
            'selectedBranch' => $selectedBranch,
            'stocks'         => $stocks,
            'displayUnit'    => $displayUnit,
            'stockFilter'    => $stockFilter,
            'hasMore'        => $paginatedProducts->hasMorePages(),
            'productTypes'   => $productTypes,
            'rmTypes'        => $rmTypes,
            'allProducts'    => $allProducts,
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

        $productIds = $request->input('product_ids', []);
        if (!empty($productIds)) {
            $query->whereIn('id', $productIds);
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

        $productIds = $request->input('product_ids', []);
        if (!empty($productIds)) {
            $query->whereIn('id', $productIds);
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
        $productTypes = \App\Models\ProductType::orderBy('type_name')->get();
        
        $history = \App\Models\Production::whereIn('branch_code', $permittedCodes)
            ->with(['items.product', 'user'])
            ->orderByDesc('production_date')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('mobile.production', compact('products', 'branches', 'history', 'productTypes'));
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
        
        $productsQuery = Product::orderBy('name');
        
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
        $productTypes = \App\Models\ProductType::orderBy('type_name')->get();
        $defaultType = \App\Models\ProductType::where('type_name', 'Finished Good')->first();
        $defaultTypeId = $defaultType ? $defaultType->id : 6;

        return view('mobile.indents', compact('indents', 'products', 'branches', 'users', 'productTypes', 'defaultTypeId'));
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
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.batch_number' => 'required|string|max:255',
            'items.*.mfg_date' => 'required|date',
            'items.*.exp_date' => 'required|date|after_or_equal:items.*.mfg_date',
        ]);

        $branch = Branch::where('code', $request->branch_code)->first();

        try {
            DB::transaction(function () use ($request, $branch) {
                $production = Production::create([
                    'production_date' => $request->production_date,
                    'branch_code' => $request->branch_code,
                    'branch_name' => $branch ? $branch->name : $request->branch_code,
                    'user_id' => auth()->id(),
                ]);

                foreach ($request->items as $itemData) {
                    $product = Product::find($itemData['product_id']);
                    if (auth()->user()->role !== 'admin') {
                        $permittedTypeIds = auth()->user()->getPermittedProductTypeIds();
                        if (!in_array($product->product_type_id, $permittedTypeIds)) {
                            throw new \Exception('Unauthorized product type for this user.');
                        }
                    }

                    $recipe = Recipe::where('finished_product_id', $product->id)->with('items')->first();

                    $unitPerBox = (float)($product->unit_box ?: 1);
                    $totalUnits = $itemData['quantity'] * $unitPerBox;
                    $totalProducedInBaseUnit = $totalUnits * $product->weight_multiplier;

                    ProductionItem::create([
                        'production_id' => $production->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'pack_size' => $product->pack_name,
                        'quantity_box' => $itemData['quantity'],
                        'batch_number' => isset($itemData['batch_number']) ? strtoupper($itemData['batch_number']) : null,
                        'mfg_date' => $itemData['mfg_date'] ?? null,
                        'exp_date' => $itemData['exp_date'] ?? null,
                    ]);

                    $product->increment('current_stock', $totalUnits);
                    StockLedger::create([
                        'product_id' => $product->id,
                        'transaction_type' => 'production_add',
                        'transaction_id' => $production->id,
                        'change_quantity' => $totalUnits,
                        'new_stock' => $product->current_stock,
                    ]);

                    if ($recipe) {
                        foreach ($recipe->items as $recipeItem) {
                            $deductQty = ($recipeItem->quantity / $recipe->yield_quantity) * $totalProducedInBaseUnit;
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
                }

                // ── ERP PUSH (non-blocking) ────────────────────────────────────────
                if (\App\Models\AppSetting::get('erp_push_enabled', '0') === '1') {
                    $production->load('items.product');
                    $issueItems   = [];
                    $receiptItems = [];

                    foreach ($production->items as $prodItem) {
                        $product = $prodItem->product;
                        if (!$product) continue;

                        $unitPerBox  = (float) ($product->unit_box ?: 1);
                        $totalUnits  = $prodItem->quantity_box * $unitPerBox;
                        $receiptItems[] = [
                            'item_code' => $product->item_code,
                            'quantity'  => $totalUnits,
                            'lot_no'    => $prodItem->batch_number,
                            'mfg_date'  => $prodItem->mfg_date,
                            'exp_date'  => $prodItem->exp_date,
                            'rate'      => (float) ($product->price ?? 0),
                        ];

                        $recipe = Recipe::where('finished_product_id', $product->id)->with('items.rawMaterial')->first();
                        if ($recipe) {
                            $totalBase = $totalUnits * $product->weight_multiplier;
                            foreach ($recipe->items as $recipeItem) {
                                $rm  = $recipeItem->rawMaterial;
                                if (!$rm || !$rm->item_code) continue;
                                $qty = ($recipeItem->quantity / $recipe->yield_quantity) * $totalBase;
                                $issueItems[] = [
                                    'item_code' => $rm->item_code,
                                    'quantity'  => $qty,
                                ];
                            }
                        }
                    }

                    $erp = new \App\Library\ErpStockPushService();
                    $issueResult   = ['success' => true, 'response' => []];
                    $receiptResult = ['success' => true, 'response' => []];

                    if (!empty($issueItems)) {
                        $issueResult = $erp->pushIssueStock($production, $issueItems);
                    }
                    if (!empty($receiptItems)) {
                        $receiptResult = $erp->pushReceiptStock($production, $receiptItems);
                    }

                    $erpSuccess = $issueResult['success'] && $receiptResult['success'];
                    $production->update([
                        'erp_push_status'      => $erpSuccess ? 'success' : 'failed',
                        'erp_issue_response'   => json_encode($issueResult['response'] ?? []),
                        'erp_receipt_response' => json_encode($receiptResult['response'] ?? []),
                    ]);
                } else {
                    $production->update(['erp_push_status' => 'skipped']);
                }
            });

            return response()->json(['success' => true, 'message' => 'Production recorded successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete Production Entry from Mobile
     */
    public function destroyProduction($id)
    {
        if (auth()->user()->role !== 'admin' && !auth()->user()->hasPermission('mobile_production', 'delete')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        try {
            $production = Production::findOrFail($id);

            DB::transaction(function () use ($production) {
                foreach ($production->items as $item) {
                    $product = Product::find($item->product_id);
                    if (!$product) continue;
                    $recipe = Recipe::where('finished_product_id', $product->id)->with('items')->first();

                    $unitPerBox = (float)($product->unit_box ?: 1);
                    $totalUnits = $item->quantity_box * $unitPerBox;
                    $totalProducedInBaseUnit = $totalUnits * $product->weight_multiplier;

                    $product->decrement('current_stock', $totalUnits);
                    StockLedger::create([
                        'product_id' => $product->id,
                        'transaction_type' => 'production_delete_deduct',
                        'transaction_id' => $production->id,
                        'change_quantity' => -$totalUnits,
                        'new_stock' => $product->current_stock,
                    ]);

                    if ($recipe) {
                        foreach ($recipe->items as $recipeItem) {
                            $reverseQty = ($recipeItem->quantity / $recipe->yield_quantity) * $totalProducedInBaseUnit;
                            $rawMaterial = Product::find($recipeItem->raw_material_id);
                            if ($rawMaterial) {
                                $rawMaterial->increment('current_stock', $reverseQty);

                                StockLedger::create([
                                    'product_id' => $rawMaterial->id,
                                    'transaction_type' => 'production_delete_add',
                                    'transaction_id' => $production->id,
                                    'change_quantity' => $reverseQty,
                                    'new_stock' => $rawMaterial->current_stock,
                                ]);
                            }
                        }
                    }
                }
                $production->delete();
            });

            return response()->json(['success' => true, 'message' => 'Production entry deleted and stock reverted.']);
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
            $user = \App\Models\User::create([
                'name'           => $request->name,
                'username'       => $request->username,
                'role'           => $request->role,
                'interface_type' => $request->interface_type,
                'password'       => \Illuminate\Support\Facades\Hash::make($request->password),
            ]);

            // Assign location/branch access if provided at creation
            if ($request->has('branches') && is_array($request->branches) && count($request->branches) > 0) {
                $user->branches()->sync($request->branches);
            }

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
                    foreach ($request->features as $pageKey => $features) {
                        $hasAnyEnabled = false;
                        $featuresData = [];
                        foreach ($features as $fKey => $allowed) {
                            $featuresData[$fKey] = (bool)$allowed;
                            if ($allowed) {
                                $hasAnyEnabled = true;
                            }
                        }

                        if ($hasAnyEnabled) {
                            \App\Models\UserPermission::create([
                                'user_id' => $user->id,
                                'page_key' => $pageKey,
                                'can_view' => $featuresData['view'] ?? true,
                                'can_create' => $featuresData['create'] ?? $featuresData['management'] ?? false,
                                'can_edit' => $featuresData['edit'] ?? false,
                                'can_delete' => $featuresData['delete'] ?? false,
                                'features' => $featuresData,
                            ]);
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

    // ─────────────────────────────────────────────
    //  COSTING MODULE
    // ─────────────────────────────────────────────

    /**
     * Mobile Costing — product cost calculator page
     */
    public function costing()
    {
        if (!Auth::user()->hasPermission('mobile_costing', 'view') && !Auth::user()->hasPermission('mobile_costing_pro', 'view') && !Auth::user()->hasPermission('costing_pro', 'view')) {
            abort(403, 'Unauthorized access to Costing module.');
        }

        $user = Auth::user();
        $query = \App\Models\Product::with(['costingBoms.items.rawMaterial', 'type'])
                    ->whereHas('costingBoms')
                    ->orderBy('name');

        if ($user->role !== 'admin') {
            $query->whereIn('product_type_id', $user->getPermittedProductTypeIds());
        }

        $products  = $query->get();
        $priceMap  = \App\Models\ProductPrice::allAsMap();
        $types     = \App\Models\ProductType::orderBy('type_name')->get();

        $boms = CostingBom::with(['finishedProduct.type', 'items.rawMaterial', 'packingMaterials.rawMaterial', 'packingMaterials.pricelist'])->get();
        $boms = $boms->sortBy(function ($bom) {
            return strtolower($bom->finishedProduct->name ?? '');
        })->values();

        $localPurities = \App\Models\ProductPrice::allPuritiesAsMap();

        $processedBoms = $boms->map(function ($bom) use ($localPurities, $priceMap) {
            $product = $bom->finishedProduct;
            $purity = '—';
            $rmName = '—';

            $techRm = null;
            foreach ($bom->items as $item) {
                if ($item->rawMaterial && strtoupper(trim($item->rawMaterial->rm_type)) === 'TECHNICAL') {
                    $techRm = $item->rawMaterial;
                    break;
                }
            }
            if (!$techRm && $bom->items->isNotEmpty()) {
                $techRm = $bom->items->first()->rawMaterial;
            }

            if ($techRm && $techRm->item_code) {
                $code = trim($techRm->item_code);
                $rmName = $techRm->name;
                if (isset($localPurities[$code]) && $localPurities[$code] > 0) {
                    $purity = $localPurities[$code] . '%';
                }
            }

            $yieldQty = max((float)$bom->yield_quantity, 0.001);
            $density  = (float)($bom->density > 0 ? $bom->density : 1.0);
            
            preg_match('/(\d+(?:\.\d+)?)\s*%/', $product->name ?? '', $matches);
            $formulation = ($bom->formulation !== null) ? (float)$bom->formulation : (isset($matches[1]) ? (float)$matches[1] : 100.0);

            $grandTotal = 0;
            foreach ($bom->items as $item) {
                $rm = $item->rawMaterial;
                if (!$rm) continue;

                $pricePerUnit = (float)($priceMap[$rm->item_code] ?? 0);
                $tc = (float)($item->transportation_cost ?? 5.0);
                $requiredQty = (float)$item->quantity;

                if (strtoupper(trim($rm->rm_type ?? '')) === 'TECHNICAL') {
                    $rmPurity = (float) \App\Models\ProductPrice::where('item_code', $rm->item_code)->value('purity');
                    if ($rmPurity <= 0 && $item->purity > 0) {
                        $rmPurity = (float) $item->purity;
                    }
                    if ($rmPurity <= 0) $rmPurity = 100.0;

                    $recipePurity = (float)($item->purity > 0 ? $item->purity : 100.0);
                    $itemFormulation = ($item->quantity * $recipePurity) / $yieldQty;
                    $requiredQty = ($yieldQty * $itemFormulation) / $rmPurity;
                }

                $subCost = $requiredQty * ($pricePerUnit + $tc);
                $grandTotal += $subCost;
            }

            $woDensityRate   = $grandTotal / $yieldQty;
            $withDensityRate = $woDensityRate * $density;

            $packingCosts = [];
            $grouped = $bom->packingMaterials->groupBy('pricelist_id');
            foreach ($grouped as $pricelistId => $pmItems) {
                $pricelist = $pmItems->first()->pricelist;
                if (!$pricelist) continue;

                $cf1 = (float)($pricelist->cf_1 ?? 1);
                if ($cf1 <= 0) $cf1 = 1;

                $singlePackPmCost = 0;
                foreach ($pmItems as $pmItem) {
                    $rm = $pmItem->rawMaterial;
                    if (!$rm) continue;

                    $pmPrice = (float)($priceMap[$rm->item_code] ?? 0);
                    if ($pmPrice <= 0 && $pmItem->rate) {
                        $pmPrice = (float)$pmItem->rate;
                    }

                    if ($pmItem->is_container) {
                        $pmPrice = $cf1 > 0 ? ($pmPrice / $cf1) : $pmPrice;
                    }

                    $singlePackPmCost += round($pmPrice, 2);
                }
                $singlePackPmCost = round($singlePackPmCost, 2);

                $sizeStr = strtolower(trim($pricelist->size ?? ''));
                preg_match('/(\d+(?:\.\d+)?)/', $sizeStr, $matches);
                $sizeNum = !empty($matches[1]) ? (float)$matches[1] : 1000.0;
                $sizeInMl = $sizeNum;
                if (str_contains($sizeStr, 'ml') || str_contains($sizeStr, 'gm') || str_contains($sizeStr, 'g') || str_contains($sizeStr, 'gram')) {
                    $sizeInMl = $sizeNum;
                } elseif (str_contains($sizeStr, 'ltr') || str_contains($sizeStr, 'liter') || str_contains($sizeStr, 'litre') || str_contains($sizeStr, 'kg') || preg_match('/\b\d+\s*l\b/i', $sizeStr)) {
                    $sizeInMl = $sizeNum * 1000.0;
                }
                $packVolumeLtr = $sizeInMl > 0 ? ($sizeInMl / 1000.0) : 1.0;

                $unitBulkWo   = $woDensityRate * $packVolumeLtr;
                $unitBulkWith = $withDensityRate * $packVolumeLtr;

                $unitTotalWo   = $unitBulkWo + $singlePackPmCost;
                $unitTotalWith = $unitBulkWith + $singlePackPmCost;

                $packingCosts[] = [
                    'pricelist_id'     => $pricelistId,
                    'size'             => $pricelist->size ?: 'Unknown',
                    'fg_name'          => $pricelist->item_hd_name ?: ($pricelist->item_short_name ?: '—'),
                    'cf1'              => $cf1,
                    'size_in_ml'       => round($sizeInMl, 2),
                    'pack_volume_ltr'  => round($packVolumeLtr, 4),
                    'pm_cost'          => round($singlePackPmCost, 2),
                    'unit_bulk_wo'     => round($unitBulkWo, 2),
                    'unit_bulk_with'   => round($unitBulkWith, 2),
                    'unit_total_wo'    => round($unitTotalWo, 2),
                    'unit_total_with'  => round($unitTotalWith, 2),
                ];
            }

            return [
                'id'                 => $bom->id,
                'product_name'       => $product->name ?? '—',
                'badge'              => $bom->badge,
                'item_code'          => $product->item_code ?? '—',
                'pack_name'          => $product->pack_name ?? '—',
                'yield_qty'          => $bom->yield_quantity,
                'yield_uom'          => $bom->yield_uom,
                'density'            => $density,
                'formulation'        => $formulation,
                'grand_total'        => round($grandTotal, 2),
                'wo_density_rate'    => round($woDensityRate, 2),
                'with_density_rate'  => round($withDensityRate, 2),
                'packing_costs'      => $packingCosts,
                'purity'             => $purity,
                'rm_name'            => $rmName,
                'raw_bom_data'       => $bom->load(['items.rawMaterial', 'packingMaterials.rawMaterial', 'packingMaterials.pricelist']),
            ];
        });

        return view('mobile.costing', compact('products', 'priceMap', 'types', 'processedBoms'));
    }

    /**
     * Mobile Costing — AJAX calculate cost
     */
    public function calculateCosting(\Illuminate\Http\Request $request)
    {
        if (!Auth::user()->hasPermission('mobile_costing', 'view')) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $request->validate([
            'products'            => 'required|array|min:1',
            'products.*.id'       => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0.001',
            'products.*.density'  => 'nullable|numeric|min:0.1|max:3',
        ]);

        $priceMap   = \App\Models\ProductPrice::allAsMap();
        $results    = [];
        $grandTotal = 0;

        foreach ($request->products as $input) {
            $product = \App\Models\Product::with('costingBoms.items.rawMaterial')->find($input['id']);
            if (!$product) continue;

            $qty         = (float) $input['quantity'];
            $density     = (float) ($input['density'] ?? 1);

            $recipe = $product->costingBoms->first();

            if (!$recipe) {
                $results[] = [
                    'product_id'    => $product->id,
                    'product_name'  => $product->name,
                    'quantity'      => $qty,
                    'purity'        => 100,
                    'formulation'   => 100,
                    'density'       => $density,
                    'cost_per_unit' => 0,
                    'total_cost'    => 0,
                    'has_recipe'    => false,
                    'breakdown'     => [],
                ];
                continue;
            }

            preg_match('/(\d+(?:\.\d+)?)\s*%/', $product->name, $matches);
            $formulation = isset($matches[1]) ? (float)$matches[1] : 100.0;

            // Find purity of TECHNICAL raw material in BOM
            $rmPurity = 100.0;
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

            $weightMultiplier = (float)($product->weight_multiplier ?? 1);
            $baseQty          = $qty * $weightMultiplier * $density;
            $breakdown        = [];
            $totalCost        = 0;

            foreach ($recipe->items as $item) {
                $rm = $item->rawMaterial;
                if (!$rm) continue;

                $requiredQty  = ($item->quantity / max($recipe->yield_quantity, 0.001)) * $baseQty;
                
                if (strtoupper(trim($rm->rm_type)) === 'TECHNICAL') {
                    $itemPurity = (float) \App\Models\ProductPrice::where('item_code', $rm->item_code)->value('purity');
                    if ($itemPurity <= 0 && $item->purity > 0) {
                        $itemPurity = (float) $item->purity;
                    }
                    if ($itemPurity <= 0) {
                        $itemPurity = 100.0;
                    }
                    $recipePurity = (float)($item->purity > 0 ? $item->purity : 100.0);
                    $itemFormulation = ($item->quantity * $recipePurity) / max($recipe->yield_quantity, 0.001);
                    $requiredQty = ($baseQty * $itemFormulation) / $itemPurity;
                }

                $pricePerUnit = (float)($priceMap[$rm->item_code] ?? 0);
                $subCost      = $requiredQty * $pricePerUnit;
                $totalCost   += $subCost;

                $breakdown[] = [
                    'rm_name'      => $rm->name,
                    'item_code'    => $rm->item_code,
                    'uom'          => $rm->uom,
                    'required_qty' => round($requiredQty, 3),
                    'price'        => $pricePerUnit,
                    'sub_cost'     => round($subCost, 2),
                    'has_price'    => $pricePerUnit > 0,
                ];
            }

            $costPerUnit  = $qty > 0 ? $totalCost / $qty : 0;
            $grandTotal  += $totalCost;

            $results[] = [
                'product_id'    => $product->id,
                'product_name'  => $product->name,
                'pack_name'     => $product->pack_name,
                'quantity'      => $qty,
                'purity'        => $rmPurity,
                'formulation'   => $formulation,
                'density'       => $density,
                'cost_per_unit' => round($costPerUnit, 2),
                'total_cost'    => round($totalCost, 2),
                'has_recipe'    => true,
                'breakdown'     => $breakdown,
            ];
        }

        return response()->json([
            'success'     => true,
            'results'     => $results,
            'grand_total' => round($grandTotal, 2),
        ]);
    }

    /**
     * Mobile Costing — Export PDF
     */
    public function exportCosting(\Illuminate\Http\Request $request)
    {
        if (!Auth::user()->hasPermission('mobile_costing', 'view')) {
            abort(403);
        }

        $productIds   = $request->input('product_ids', []);
        $quantities   = $request->input('quantities', []);
        $densities    = $request->input('densities', []);
        $priceMap     = \App\Models\ProductPrice::allAsMap();
        $results      = [];
        $grandTotal   = 0;

        $query = \App\Models\Product::with('costingBoms.items.rawMaterial')->whereHas('costingBoms');
        if (!empty($productIds)) {
            $query->whereIn('id', $productIds);
        }
        $products = $query->orderBy('name')->get();

        foreach ($products as $product) {
            $qty         = (float)($quantities[$product->id] ?? 1);
            $density     = (float)($densities[$product->id] ?? 1);
            
            $recipe = $product->costingBoms->first();
            if (!$recipe) continue;

            preg_match('/(\d+(?:\.\d+)?)\s*%/', $product->name, $matches);
            $formulation = isset($matches[1]) ? (float)$matches[1] : 100.0;

            // Find purity of TECHNICAL raw material in BOM
            $rmPurity = 100.0;
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

            $baseQty   = $qty * (float)($product->weight_multiplier ?? 1) * $density;
            $breakdown = [];
            $totalCost = 0;

            foreach ($recipe->items as $item) {
                $rm = $item->rawMaterial;
                if (!$rm) continue;
                $requiredQty  = ($item->quantity / max($recipe->yield_quantity, 0.001)) * $baseQty;
                
                if (strtoupper(trim($rm->rm_type)) === 'TECHNICAL') {
                    $itemPurity = (float) \App\Models\ProductPrice::where('item_code', $rm->item_code)->value('purity');
                    if ($itemPurity <= 0 && $item->purity > 0) {
                        $itemPurity = (float) $item->purity;
                    }
                    if ($itemPurity <= 0) {
                        $itemPurity = 100.0;
                    }
                    $recipePurity = (float)($item->purity > 0 ? $item->purity : 100.0);
                    $itemFormulation = ($item->quantity * $recipePurity) / max($recipe->yield_quantity, 0.001);
                    $requiredQty = ($baseQty * $itemFormulation) / $itemPurity;
                }

                $pricePerUnit = (float)($priceMap[$rm->item_code] ?? 0);
                $subCost      = $requiredQty * $pricePerUnit;
                $totalCost   += $subCost;
                $breakdown[] = [
                    'rm_name'      => $rm->name,
                    'uom'          => $rm->uom,
                    'required_qty' => round($requiredQty, 3),
                    'price'        => $pricePerUnit,
                    'sub_cost'     => round($subCost, 2),
                ];
            }

            $grandTotal += $totalCost;
            $results[]   = [
                'product'      => $product,
                'quantity'     => $qty,
                'purity'       => $rmPurity,
                'formulation'  => $formulation,
                'density'      => $density,
                'total_cost'   => round($totalCost, 2),
                'cost_per_unit'=> $qty > 0 ? round($totalCost / $qty, 2) : 0,
                'breakdown'    => $breakdown,
                'has_recipe'   => true,
            ];
        }

        $pdf = Pdf::loadView('costing.pdf', compact('results', 'grandTotal'))
                  ->setPaper('a4', 'portrait');

        return $pdf->download('Mobile_Cost_Report_' . now()->format('Y-m-d_His') . '.pdf');
    }

    /**
     * Mobile: Purchase Report
     */
    public function purchaseReport(Request $request)
    {
        $baseUrl = rtrim(\App\Models\AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
        $apiKey  = \App\Models\AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');

        $now     = now();
        $fyStart = $now->month >= 4 ? $now->year . '-04-01' : ($now->year - 1) . '-04-01';
        $fyEnd   = $now->month >= 4 ? ($now->year + 1) . '-03-31' : $now->year . '-03-31';

        $defaults = [
            'from_date' => \App\Models\AppSetting::get('costing_api_from_date', $fyStart) ?: $fyStart,
            'to_date'   => \App\Models\AppSetting::get('costing_api_to_date',   $fyEnd)   ?: $fyEnd,
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

        // Initialize option arrays
        $rmTypeOptions  = [];
        $typesOptions   = [];
        $accountOptions = [];
        $itemOptions    = [];

        // Show form on first load — only call API when form submitted
        if (!$request->hasAny(['from_date', 'to_date', 'account', 'item', 'branch', 'rm_type', 'types'])) {
            return view('mobile.purchase_report', compact(
                'defaults', 'fromDate', 'toDate', 'account', 'item', 'branch',
                'rmType', 'types', 'rmTypeOptions', 'typesOptions', 'accountOptions', 'itemOptions'
            ));
        }

        // NOTE: Account & Item filtered PHP-side; API only accepts 'all' reliably
        $payload = [
            'apikey'   => $apiKey,
            'FromDate' => $fromDate,
            'ToDate'   => $toDate,
            'Account'  => 'all',
            'Item'     => 'all',
            'Branch'   => $branch,
        ];

        $reportData = [];
        $error      = null;

        try {
            $response = Http::withoutVerifying()->timeout(60)->connectTimeout(15)
                ->post("{$baseUrl}/LogicPurchaseRegisterDetail", $payload);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['response']) && $data['response'] === 'success' && isset($data['resultdata'])) {
                    $reportData = $data['resultdata'];
                } elseif (is_array($data) && !isset($data['response'])) {
                    $reportData = $data;
                } else {
                    $error = 'API: ' . ($data['message'] ?? $data['response'] ?? 'No data');
                }

                // Extract dropdown options from FULL data (before filters)
                $rmTypeOptions  = collect($reportData)->pluck('GroupName4')->map(fn($v) => trim($v))->filter()->unique()->sort()->values()->toArray();
                $typesOptions   = collect($reportData)->pluck('GroupName5')->map(fn($v) => trim($v))->filter()->unique()->sort()->values()->toArray();
                $accountOptions = collect($reportData)->pluck('SupplierName')->map(fn($v) => trim($v))->filter()->unique()->sort()->values()->toArray();
                $itemOptions    = collect($reportData)
                    ->map(fn($r) => ['code' => trim($r['User_Code'] ?? ''), 'name' => trim($r['Item_Hd_Name'] ?? '')])
                    ->filter(fn($r) => $r['name'] !== '')
                    ->unique('name')->sortBy('name')->values()->toArray();

                // Server-side filters
                if (!empty($rmType) && $rmType !== 'all') {
                    $reportData = array_values(array_filter($reportData, fn($r) => trim($r['GroupName4'] ?? '') === trim($rmType)));
                }
                if (!empty($types) && $types !== 'all') {
                    $reportData = array_values(array_filter($reportData, fn($r) => trim($r['GroupName5'] ?? '') === trim($types)));
                }
                if (!empty($account) && $account !== 'all') {
                    $reportData = array_values(array_filter($reportData, fn($r) => trim($r['SupplierName'] ?? '') === trim($account)));
                }
                if (!empty($item) && $item !== 'all') {
                    $reportData = array_values(array_filter($reportData, fn($r) => trim($r['Item_Hd_Name'] ?? '') === trim($item)));
                }
            } else {
                $error = 'HTTP ' . $response->status();
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return view('mobile.purchase_report', compact(
            'reportData', 'defaults', 'error',
            'fromDate', 'toDate', 'account', 'item', 'branch',
            'rmType', 'types', 'rmTypeOptions', 'typesOptions', 'accountOptions', 'itemOptions'
        ));
    }

    /**
     * Mobile: Costing BOMs
     */
    public function costingBoms(\Illuminate\Http\Request $request)
    {
        $query = CostingBom::with(['finishedProduct.type', 'items.rawMaterial']);
        $user = Auth::user();

        if ($user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $permittedRMTypes = $user->getPermittedRMTypes();
            
            $query->whereHas('finishedProduct', function($q) use ($permittedTypeIds, $permittedRMTypes) {
                $q->whereIn('product_type_id', $permittedTypeIds)
                  ->where(function($sq) use ($permittedRMTypes) {
                      $sq->whereIn('rm_type', $permittedRMTypes)
                        ->orWhereNull('rm_type')
                        ->orWhere('rm_type', '');
                  });
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('finishedProduct', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%");
            });
        }

        $boms = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $fgQuery = Product::whereHas('type', function($q) {
            $q->whereIn('type_name', ['Semi Finished Good', 'Semi Finished Goods', 'SEMI FINISHED GOOD', 'SEMI FINISHED GOODS', 'Finished Good', 'Finished Goods']);
        })->orderBy('name');

        $rmQuery = Product::whereHas('type', function($q) {
            $q->whereIn('type_name', ['RAW MATERIAL', 'PACKING MATERIAL', 'Raw Material', 'Packing Material']);
        })->orderBy('name');

        if ($user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $fgQuery->whereIn('product_type_id', $permittedTypeIds);
            $rmQuery->whereIn('product_type_id', $permittedTypeIds);
        }

        $finishedGoods = $fgQuery->get(['id', 'name', 'pack_name', 'uom', 'item_code', 'product_type_id']);
        $rawMaterials  = $rmQuery->get(['id', 'name', 'pack_name', 'uom', 'item_code', 'product_type_id', 'rm_type']);

        return view('mobile.costing_boms', compact('boms', 'finishedGoods', 'rawMaterials'));
    }

    public function storeCostingBom(\Illuminate\Http\Request $request)
    {
        if (!Auth::user()->hasPermission('mobile_costing_bom', 'create') && !Auth::user()->hasPermission('costing_bom', 'create')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'finished_product_id' => 'required|exists:products,id',
            'badge'               => 'nullable|string|in:small,big,bulk',
            'yield_quantity'      => 'required|numeric|min:0.001',
            'yield_uom'           => 'required|string|max:50',
            'items'               => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:products,id',
            'items.*.quantity'    => 'required|numeric|min:0.001',
            'items.*.purity'      => 'nullable|numeric|min:0.1|max:100',
        ]);

        DB::transaction(function () use ($validated) {
            $bom = CostingBom::create([
                'finished_product_id' => $validated['finished_product_id'],
                'yield_quantity'      => $validated['yield_quantity'],
                'yield_uom'           => $validated['yield_uom'],
                'badge'               => $validated['badge'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                CostingBomItem::create([
                    'costing_bom_id'  => $bom->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity'        => $item['quantity'],
                    'purity'          => $item['purity'] ?? null,
                    'transportation_cost' => 5.0,
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Costing BOM created successfully.']);
    }

    public function duplicateCostingBom(\Illuminate\Http\Request $request, $id)
    {
        if (!Auth::user()->hasPermission('mobile_costing_bom', 'create') && !Auth::user()->hasPermission('costing_bom', 'create')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $costingBom = CostingBom::findOrFail($id);
        $badge = $request->input('badge', 'small');

        DB::transaction(function () use ($costingBom, $badge) {
            $newBom = CostingBom::create([
                'finished_product_id' => $costingBom->finished_product_id,
                'yield_quantity'      => $costingBom->yield_quantity,
                'yield_uom'           => $costingBom->yield_uom,
                'badge'               => $badge,
                'formulation'         => $costingBom->formulation,
                'density'             => $costingBom->density,
            ]);

            foreach ($costingBom->items as $item) {
                CostingBomItem::create([
                    'costing_bom_id'  => $newBom->id,
                    'raw_material_id' => $item->raw_material_id,
                    'quantity'        => $item->quantity,
                    'purity'          => $item->purity,
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Costing BOM duplicated successfully.']);
    }

    public function deleteCostingBom(\Illuminate\Http\Request $request, $id)
    {
        if (!Auth::user()->hasPermission('mobile_costing_bom', 'delete') && !Auth::user()->hasPermission('costing_bom', 'delete')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $costingBom = CostingBom::findOrFail($id);
        DB::transaction(function () use ($costingBom) {
            $costingBom->items()->delete();
            $costingBom->delete();
        });

        return response()->json(['success' => true, 'message' => 'Costing BOM deleted successfully.']);
    }

    public function exportCostingBoms(\Illuminate\Http\Request $request)
    {
        return (new \App\Exports\CostingBomsExport($request->search))->download('mobile_costing_boms.xlsx');
    }

    /**
     * Mobile: Costing Dashboard / Pro
     */
    public function costingPro(\Illuminate\Http\Request $request)
    {
        return $this->costing($request);
    }

    /**
     * Mobile: Costing Purchase Register
     */
    public function costingPurchaseRegister(\Illuminate\Http\Request $request)
    {
        $query = PurchaseRegister::orderByDesc('vouch_date')->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('supplier_name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%")
                  ->orWhere('vouch_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('vouch_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('vouch_date', '<=', $request->to_date);
        }

        $purchases = $query->paginate(20)->withQueryString();

        $totalBills  = PurchaseRegister::distinct('vouch_no')->count('vouch_no');
        $totalItems  = PurchaseRegister::count();
        $totalAmount = PurchaseRegister::sum(DB::raw('qty * case_rate'));

        return view('mobile.purchase_register', compact('purchases', 'totalBills', 'totalItems', 'totalAmount'));
    }

    public function syncCostingPurchaseRegister(\Illuminate\Http\Request $request)
    {
        try {
            $controller = new CostingController();
            $count = $controller->syncPurchaseRegisterRaw();
            return response()->json([
                'success' => true,
                'message' => "Synced {$count} purchase entries from ERP.",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mobile: Costing Pricelist (Master Finished Goods Rates)
     */
    public function costingPricelist(\Illuminate\Http\Request $request)
    {
        $query = \App\Models\Pricelist::where('group5', 'FINISHED GOODS');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('item_hd_name', 'like', "%{$search}%")
                  ->orWhere('item_short_name', 'like', "%{$search}%")
                  ->orWhere('user_code', 'like', "%{$search}%")
                  ->orWhere('group3', 'like', "%{$search}%");
            });
        }

        if ($request->filled('group1')) {
            $query->where('group1', $request->group1);
        }

        $sortOrder = $request->input('sort', 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy('item_hd_name', $sortOrder);

        $pricelists = $query->paginate(20)->withQueryString();
        $group1List = \App\Models\Pricelist::where('group5', 'FINISHED GOODS')->whereNotNull('group1')->where('group1', '!=', '')->distinct()->pluck('group1')->sort()->values();

        if ($request->ajax()) {
            return response()->json([
                'html' => view('mobile.partials.pricelist-items', compact('pricelists'))->render(),
                'has_more' => $pricelists->hasMorePages(),
                'next_page' => $pricelists->currentPage() + 1
            ]);
        }

        return view('mobile.pricelist', compact('pricelists', 'group1List'));
    }

    public function updateCostingPricelist(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'item_code'      => 'required|string',
            'price_per_unit' => 'required|numeric|min:0',
            'purity'         => 'nullable|numeric|min:0|max:100',
        ]);

        ProductPrice::updateOrCreate(
            ['item_code' => $request->item_code],
            [
                'price_per_unit' => $request->price_per_unit,
                'purity'         => $request->purity ?? 100.0,
                'price_source'   => 'manual',
                'fetched_at'     => now(),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Price & purity updated successfully.']);
    }

    public function syncCostingPricelist(\Illuminate\Http\Request $request)
    {
        try {
            $controller = new CostingController();
            $count = $controller->syncPricelistRaw();
            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$count} items from Product Master ERP API into database.",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Shared filter/sort query for the mobile Pricelist Update list & its infinite-scroll feed.
     */
    private function pricelistUpdateQuery(\Illuminate\Http\Request $request)
    {
        $query = \App\Models\Pricelist::where('group5', 'FINISHED GOODS');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('item_hd_name', 'like', "%{$search}%")
                  ->orWhere('user_code', 'like', "%{$search}%")
                  ->orWhere('group3', 'like', "%{$search}%");
            });
        }

        if ($request->filled('group1')) {
            $query->where('group1', $request->group1);
        }

        $sortOrder = $request->input('sort', 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy('item_hd_name', $sortOrder);

        return $query;
    }

    /**
     * Mobile: Pricelist Update (push rates to ERP)
     */
    public function costingPricelistUpdate(\Illuminate\Http\Request $request)
    {
        $pricelists = $this->pricelistUpdateQuery($request)->paginate(20)->withQueryString();
        $group1List = \App\Models\Pricelist::where('group5', 'FINISHED GOODS')
            ->whereNotNull('group1')->where('group1', '!=', '')
            ->distinct()->pluck('group1')->sort()->values();

        $priceLists = CostingController::PRICE_LIST_MAP;
        $recentPushes = \App\Models\PricelistPushLog::latest()->limit(10)->get();
        $rateMatrix = CostingController::buildRateMatrix($pricelists);

        return view('mobile.pricelist-update', compact('pricelists', 'group1List', 'priceLists', 'recentPushes', 'rateMatrix'));
    }

    /**
     * Mobile: Pricelist Update — infinite-scroll feed (returns rendered cards + rate data as JSON)
     */
    public function costingPricelistUpdateItems(\Illuminate\Http\Request $request)
    {
        $pricelists = $this->pricelistUpdateQuery($request)->paginate(20)->withQueryString();
        $rateMatrix = CostingController::buildRateMatrix($pricelists);

        return response()->json([
            'html'     => view('mobile.partials.pricelist-update-items', compact('pricelists'))->render(),
            'has_more' => $pricelists->hasMorePages(),
            'next_page' => $pricelists->currentPage() + 1,
            'rates'    => $rateMatrix,
        ]);
    }

    public function pushCostingPricelist(\Illuminate\Http\Request $request)
    {
        $controller = new CostingController();
        return $controller->pushPricelist($request);
    }

    public function costingPricelistPushHistory($id)
    {
        $controller = new CostingController();
        return $controller->pricelistPushHistory($id);
    }

    /**
     * Mobile: Collection Report
     */
    /**
     * Mobile: Teams Hierarchy & Setup Manager
     */
    public function teamsSetup(Request $request)
    {
        $reportController = new ReportController();
        $response = $reportController->teamsSetup();
        $data = $response->getData();
        return view('mobile.teams_setup', $data);
    }

    public function collectionReport(Request $request)
    {
        // Re-use core ReportController's collection report calculations but render to a mobile blade view.
        $reportController = new ReportController();
        $response = $reportController->collectionReport($request);
        
        if ($response instanceof \Illuminate\Http\RedirectResponse) {
            return $response;
        }

        // Extract view parameters from the view object
        $data = $response->getData();
        return view('mobile.collection_report', $data);
    }

    /**
     * Mobile: Target management index
     */
    public function agentTargetsIndex(Request $request)
    {
        $reportController = new ReportController();
        $response = $reportController->agentTargetsIndex($request);
        $data = $response->getData();
        return view('mobile.agent_targets', $data);
    }

    /**
     * Mobile: Target store for agents
     */
    public function agentTargetsStore(Request $request)
    {
        $reportController = new ReportController();
        return $reportController->agentTargetsStore($request);
    }

    /**
     * Mobile: Target store for teams
     */
    public function teamTargetsStore(Request $request)
    {
        $reportController = new ReportController();
        return $reportController->teamTargetsStore($request);
    }

    /**
     * Mobile: Store a custom team
     */
    public function storeTeam(Request $request)
    {
        $reportController = new ReportController();
        return $reportController->storeTeam($request);
    }

    /**
     * Mobile: Update a custom team
     */
    public function updateTeam(Request $request, \App\Models\Team $team)
    {
        $reportController = new ReportController();
        return $reportController->updateTeam($request, $team);
    }

    /**
     * Mobile: Delete a custom team
     */
    public function deleteTeam(\App\Models\Team $team)
    {
        $reportController = new ReportController();
        return $reportController->deleteTeam($team);
    }
}
