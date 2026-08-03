<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CostingController;
use App\Http\Controllers\CostingBomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        if (auth()->user()->interface_type === 'mobile') {
            return redirect()->route('mobile.dashboard');
        }
    }
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    $totalProducts = \App\Models\Product::count();
    $lowStockItems = \App\Models\Product::whereColumn('current_stock', '<=', 'low_alert_quantity')->get();
    $lowStockCount = $lowStockItems->count();
    $productionCount = \App\Models\StockLedger::where('transaction_type', 'production_add')
                        ->whereDate('created_at', \Carbon\Carbon::today())
                        ->count();
    $recipeCount = \App\Models\Recipe::count();

    // Chart Data Preparation
    $chartProducts = \App\Models\Product::orderByDesc('current_stock')->limit(10)->get();
    $stockChartLabels = $chartProducts->pluck('name');
    $stockChartData = $chartProducts->pluck('current_stock');

    return view('dashboard', compact('totalProducts', 'lowStockCount', 'lowStockItems', 'productionCount', 'recipeCount', 'stockChartLabels', 'stockChartData'));
})->middleware(['auth', 'verified', 'interface:desktop'])->name('dashboard');

Route::middleware(['auth', 'interface:desktop'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('products/bulk-delete', [ProductController::class, 'bulkDestroy'])->name('products.bulk_delete');
    Route::post('products/import', [App\Http\Controllers\ProductImportController::class, 'import'])->name('products.import');
    Route::get('products/import/template', [App\Http\Controllers\ProductImportController::class, 'downloadTemplate'])->name('products.import.template');
    Route::resource('product-types', App\Http\Controllers\ProductTypeController::class)->only(['store', 'destroy']);
    Route::resource('product-groups', App\Http\Controllers\ProductGroupController::class)->only(['store', 'destroy']);
    Route::resource('product-attributes', App\Http\Controllers\ProductAttributeController::class)->only(['store', 'destroy']);
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::post('products/sync-api', [ProductController::class, 'syncFromApi'])->name('products.sync-api');
    Route::get('products/sync-report/{id}', [ProductController::class, 'syncReport'])->name('products.sync-report');
    Route::resource('products', ProductController::class);
    Route::get('recipes/export', [RecipeController::class, 'export'])->name('recipes.export');
    Route::get('recipes/import-template', [RecipeController::class, 'importTemplate'])->name('recipes.import-template');
    Route::post('recipes/import', [RecipeController::class, 'import'])->name('recipes.import');
    Route::post('recipes/bulk-delete', [RecipeController::class, 'bulkDelete'])->name('recipes.bulk-delete');
    Route::resource('recipes', RecipeController::class);
    Route::resource('production', ProductionController::class);
    Route::post('production/check-stock', [ProductionController::class, 'checkStock'])->name('production.check-stock');
    Route::resource('adjustments', AdjustmentController::class);

    // User Management
    Route::resource('users', UserController::class);
    Route::post('users/{user}/toggle-permission', [UserController::class, 'togglePermission'])->name('users.toggle-permission');

    // Production Planning (MRP Calculator)
    Route::prefix('planning')->name('planning.')->group(function() {
        Route::get('/', [App\Http\Controllers\PlanningController::class, 'index'])->name('index');
        Route::post('/calculate', [App\Http\Controllers\PlanningController::class, 'calculate'])->name('calculate');
        Route::post('/export', [App\Http\Controllers\PlanningController::class, 'export'])->name('export');
    });

    // Indent Manager (Bulk Entry, History, Processing)
    Route::prefix('indent')->name('indent.')->group(function() {
        Route::get('/', [App\Http\Controllers\IndentController::class, 'index'])->name('index');
        Route::post('/calculate', [App\Http\Controllers\IndentController::class, 'calculate'])->name('calculate_inner'); // For draft preview
        Route::post('/store', [App\Http\Controllers\IndentController::class, 'store'])->name('store');
        Route::get('/get-stock', [App\Http\Controllers\IndentController::class, 'getStock'])->name('get-stock');
        Route::get('/bulk-stock', [App\Http\Controllers\IndentController::class, 'getBulkStock'])->name('bulk-stock');
        Route::get('/show/{indent}', [App\Http\Controllers\IndentController::class, 'show'])->name('show');
        Route::get('/show/{indent}/print', [App\Http\Controllers\IndentController::class, 'print'])->name('print');
        Route::get('/show/{indent}/excel', [App\Http\Controllers\IndentController::class, 'exportExcel'])->name('excel');
        Route::get('/show/{indent}/pdf', [App\Http\Controllers\IndentController::class, 'exportPdf'])->name('pdf');
        Route::get('/show/{indent}/process', [App\Http\Controllers\IndentController::class, 'process'])->name('process');
        Route::get('/process-list', [App\Http\Controllers\IndentController::class, 'processList'])->name('process.list');
        Route::get('/show/{indent}/process/excel', [App\Http\Controllers\IndentController::class, 'exportProcessExcel'])->name('process.excel');
        Route::get('/show/{indent}/process/pdf', [App\Http\Controllers\IndentController::class, 'exportProcessPdf'])->name('process.pdf');
        Route::post('/show/{indent}/complete', [App\Http\Controllers\IndentController::class, 'updateCompletion'])->name('complete');
    });


    // Costing Module
    Route::prefix('costing')->name('costing.')->group(function () {
        Route::get('/',                   [CostingController::class, 'index'])->name('index');
        Route::get('/pro',                [CostingController::class, 'pro'])->name('pro');
        Route::get('/purchase-register',  [CostingController::class, 'purchaseRegister'])->name('purchase-register');
        Route::post('/sync-purchase-register', [CostingController::class, 'syncPurchaseRegister'])->name('sync-purchase-register');
        Route::post('/save-sync-settings', [CostingController::class, 'saveSyncSettings'])->name('save-sync-settings');
        Route::get('/pricelist',           [CostingController::class, 'pricelist'])->name('pricelist');
        Route::post('/sync-pricelist',     [CostingController::class, 'syncPricelist'])->name('sync-pricelist');
        Route::post('/save-pricelist-sync-settings', [CostingController::class, 'savePricelistSyncSettings'])->name('save-pricelist-sync-settings');
        Route::get('/pricelist-update',    [CostingController::class, 'pricelistUpdate'])->name('pricelist-update');
        Route::post('/push-pricelist',     [CostingController::class, 'pushPricelist'])->name('push-pricelist');
        Route::get('/pricelist-push-history/{id}', [CostingController::class, 'pricelistPushHistory'])->name('pricelist-push-history');
        Route::get('/product/{product}',  [CostingController::class, 'show'])->name('show');
        Route::post('/calculate',         [CostingController::class, 'calculate'])->name('calculate');
        Route::post('/fetch-prices',      [CostingController::class, 'fetchPrices'])->name('fetch-prices');
        Route::post('/update-price',      [CostingController::class, 'updatePrice'])->name('update-price');
        Route::get('/export',             [CostingController::class, 'export'])->name('export');
    });

    // Costing BOM Master
    Route::post('costing-boms/bulk-delete', [CostingBomController::class, 'bulkDelete'])->name('costing.boms.bulk-delete');
    Route::get('costing-boms/export', [CostingBomController::class, 'export'])->name('costing.boms.export');
    Route::post('costing-boms/{costing_bom}/duplicate', [CostingBomController::class, 'duplicate'])->name('costing.boms.duplicate');
    Route::resource('costing-boms', CostingBomController::class)->names([
        'index'   => 'costing.boms.index',
        'store'   => 'costing.boms.store',
        'update'  => 'costing.boms.update',
        'destroy' => 'costing.boms.destroy',
    ]);

    // Reports
    Route::get('reports/stock-ledger', [ReportController::class, 'stockLedger'])->name('reports.stock-ledger');
    Route::get('reports/live-stock', [ReportController::class, 'liveStock'])->name('reports.live-stock');
    Route::get('reports/live-stock/excel', [ReportController::class, 'exportLiveStockExcel'])->name('reports.live-stock.excel');
    Route::get('reports/live-stock/pdf', [ReportController::class, 'exportLiveStockPdf'])->name('reports.live-stock.pdf');
    Route::get('reports/purchase', [ReportController::class, 'purchaseReport'])->name('reports.purchase');
    Route::get('reports/collection', [ReportController::class, 'collectionReport'])->name('reports.collection');
    Route::get('reports/teams/setup', [ReportController::class, 'teamsSetup'])->name('reports.teams.setup');
    Route::post('reports/teams/setup/save', [ReportController::class, 'saveTeamsSetup'])->name('reports.teams.setup.save');
    Route::get('reports/party-master', [ReportController::class, 'partyMasterReport'])->name('reports.party-master');
    Route::post('reports/collection/teams', [ReportController::class, 'storeTeam'])->name('reports.collection.teams.store');
    Route::put('reports/collection/teams/{team}', [ReportController::class, 'updateTeam'])->name('reports.collection.teams.update');
    Route::delete('reports/collection/teams/{team}', [ReportController::class, 'deleteTeam'])->name('reports.collection.teams.destroy');
    
    // Agent & Team Targets Routes
    Route::get('reports/agent-targets', [ReportController::class, 'agentTargetsIndex'])->name('reports.agent-targets.index');
    Route::post('reports/agent-targets', [ReportController::class, 'agentTargetsStore'])->name('reports.agent-targets.store');
    Route::post('reports/team-targets', [ReportController::class, 'teamTargetsStore'])->name('reports.team-targets.store');

    // Settings
    Route::get('settings/branches', [App\Http\Controllers\SettingController::class, 'index'])->name('settings.branches.index');
    Route::post('settings/api', [App\Http\Controllers\SettingController::class, 'updateApiSettings'])->name('settings.api.update');
    Route::post('settings/branches/store', [App\Http\Controllers\SettingController::class, 'storeBranch'])->name('settings.branches.store');
    Route::post('settings/branches/update', [App\Http\Controllers\SettingController::class, 'updateBranches'])->name('settings.branches.update');
    Route::delete('settings/branches/{branch}', [App\Http\Controllers\SettingController::class, 'deleteBranch'])->name('settings.branches.destroy');
});

// Shared Settings (Accessible by both interfaces)
Route::middleware(['auth'])->group(function() {
    Route::post('settings/branches/reorder', [App\Http\Controllers\SettingController::class, 'reorder'])->name('settings.branches.reorder');
});

// Mobile PWA Interface
// Indent Shared API (Accessible by both Mobile & Desktop)
Route::prefix('indent-api')->middleware(['auth'])->group(function() {
    Route::get('/show/{indent}', [App\Http\Controllers\IndentController::class, 'show'])->name('indent.api.show');
    Route::delete('/show/{indent}', [App\Http\Controllers\IndentController::class, 'destroy'])->name('indent.api.destroy');
    Route::post('/show/{indent}/clone', [App\Http\Controllers\IndentController::class, 'clone'])->name('indent.api.clone');
    Route::post('/show/{indent}/update', [App\Http\Controllers\IndentController::class, 'update'])->name('indent.api.update');
});

Route::prefix('mobile')->middleware(['auth', 'interface:mobile'])->group(function () {
    Route::get('/', [App\Http\Controllers\MobileController::class, 'index'])->name('mobile.dashboard');
    Route::get('/stock', [App\Http\Controllers\MobileController::class, 'stock'])->name('mobile.stock');
    Route::get('/production', [App\Http\Controllers\MobileController::class, 'production'])->name('mobile.production');
    Route::post('/production-store', [App\Http\Controllers\MobileController::class, 'submitProduction'])->name('mobile.production.store');
    Route::delete('/production/{id}', [App\Http\Controllers\MobileController::class, 'destroyProduction'])->name('mobile.production.destroy');
    Route::post('/production/check-stock', [ProductionController::class, 'checkStock'])->name('mobile.production.check-stock');
    Route::get('/production/{production}', [ProductionController::class, 'show'])->name('mobile.production.show');
    Route::get('/planning', [App\Http\Controllers\MobileController::class, 'planning'])->name('mobile.planning');
    Route::post('/planning/calculate', [App\Http\Controllers\MobileController::class, 'calculateMRP'])->name('mobile.planning.calculate');
    Route::get('/indents', [App\Http\Controllers\MobileController::class, 'indents'])->name('mobile.indents');
    Route::post('/indents-store', [App\Http\Controllers\MobileController::class, 'storeIndent'])->name('mobile.indents.store');
    Route::post('/fg-stock', [App\Http\Controllers\MobileController::class, 'getFGStock'])->name('mobile.fg-stock');
    Route::get('/indent/show/{indent}', [App\Http\Controllers\IndentController::class, 'show'])->name('mobile.indent.show');
    Route::get('/indents/{indent}/print', [App\Http\Controllers\IndentController::class, 'print'])->name('mobile.indents.print');
    Route::get('/indents/{indent}/process', [App\Http\Controllers\MobileController::class, 'process'])->name('mobile.indents.process');
    Route::post('/indents/{indent}/completion', [App\Http\Controllers\MobileController::class, 'updateCompletion'])->name('mobile.indents.completion');
    Route::get('/indents/{indent}/excel', [App\Http\Controllers\MobileController::class, 'exportExcel'])->name('mobile.indents.excel');
    Route::get('/indents/{indent}/pdf', [App\Http\Controllers\MobileController::class, 'exportPdf'])->name('mobile.indents.pdf');
    Route::get('/indents/{indent}/process/excel', [App\Http\Controllers\MobileController::class, 'exportProcessExcel'])->name('mobile.indents.process.excel');
    Route::get('/indents/{indent}/process/pdf', [App\Http\Controllers\MobileController::class, 'exportProcessPdf'])->name('mobile.indents.process.pdf');
    Route::get('/stock/excel', [App\Http\Controllers\MobileController::class, 'exportStockExcel'])->name('mobile.stock.excel');
    Route::get('/stock/pdf', [App\Http\Controllers\MobileController::class, 'exportStockPdf'])->name('mobile.stock.pdf');
    // Costing Sub-Modules (Mobile)
    Route::get('/costing',                     [App\Http\Controllers\MobileController::class, 'costing'])->name('mobile.costing');
    Route::post('/costing/calculate',          [App\Http\Controllers\MobileController::class, 'calculateCosting'])->name('mobile.costing.calculate');
    Route::post('/costing/export',             [App\Http\Controllers\MobileController::class, 'exportCosting'])->name('mobile.costing.export');

    Route::get('/costing-boms',                [App\Http\Controllers\MobileController::class, 'costingBoms'])->name('mobile.costing.boms');
    Route::post('/costing-boms/store',         [App\Http\Controllers\MobileController::class, 'storeCostingBom'])->name('mobile.costing.boms.store');
    Route::post('/costing-boms/{id}/duplicate', [App\Http\Controllers\MobileController::class, 'duplicateCostingBom'])->name('mobile.costing.boms.duplicate');
    Route::delete('/costing-boms/{id}',        [App\Http\Controllers\MobileController::class, 'deleteCostingBom'])->name('mobile.costing.boms.destroy');
    Route::get('/costing-boms-export',         [App\Http\Controllers\MobileController::class, 'exportCostingBoms'])->name('mobile.costing.boms.export');

    Route::get('/costing-dashboard',           [App\Http\Controllers\MobileController::class, 'costingPro'])->name('mobile.costing.pro');

    Route::get('/costing-purchase-register',   [App\Http\Controllers\MobileController::class, 'costingPurchaseRegister'])->name('mobile.costing.purchase');
    Route::post('/costing-purchase-register/sync', [App\Http\Controllers\MobileController::class, 'syncCostingPurchaseRegister'])->name('mobile.costing.purchase.sync');

    Route::get('/costing-pricelist',           [App\Http\Controllers\MobileController::class, 'costingPricelist'])->name('mobile.costing.pricelist');
    Route::post('/costing-pricelist/update',   [App\Http\Controllers\MobileController::class, 'updateCostingPricelist'])->name('mobile.costing.pricelist.update');
    Route::post('/costing-pricelist/sync',     [App\Http\Controllers\MobileController::class, 'syncCostingPricelist'])->name('mobile.costing.pricelist.sync');

    Route::get('/costing-pricelist-update',       [App\Http\Controllers\MobileController::class, 'costingPricelistUpdate'])->name('mobile.costing.pricelist-update');
    Route::get('/costing-pricelist-update/items', [App\Http\Controllers\MobileController::class, 'costingPricelistUpdateItems'])->name('mobile.costing.pricelist-update.items');
    Route::post('/costing-pricelist-update/push', [App\Http\Controllers\MobileController::class, 'pushCostingPricelist'])->name('mobile.costing.pricelist-update.push');
    Route::get('/costing-pricelist-update/history/{id}', [App\Http\Controllers\MobileController::class, 'costingPricelistPushHistory'])->name('mobile.costing.pricelist-update.history');

    // Purchase Report
    Route::get('/purchase-report', [App\Http\Controllers\MobileController::class, 'purchaseReport'])->name('mobile.purchase-report');

    // Recipes
    Route::get('/recipes', [App\Http\Controllers\MobileController::class, 'recipes'])->name('mobile.recipes');
    Route::get('/recipes/{recipe}', [App\Http\Controllers\MobileController::class, 'showRecipe'])->name('mobile.recipes.show');
    Route::post('/recipes-store', [App\Http\Controllers\MobileController::class, 'storeRecipe'])->name('mobile.recipes.store');
    Route::post('/recipes/{recipe}/update', [App\Http\Controllers\MobileController::class, 'updateRecipe'])->name('mobile.recipes.update');
    Route::delete('/recipes/{recipe}', [App\Http\Controllers\MobileController::class, 'deleteRecipe'])->name('mobile.recipes.destroy');
    // Adjustments
    Route::get('/adjustments', [App\Http\Controllers\MobileController::class, 'adjustments'])->name('mobile.adjustments');
    Route::post('/adjustments', [App\Http\Controllers\MobileController::class, 'storeAdjustment'])->name('mobile.adjustments.store');
    // Ledger
    Route::get('/ledger', [App\Http\Controllers\MobileController::class, 'ledger'])->name('mobile.ledger');
    // Product Master
    Route::get('/products', [App\Http\Controllers\MobileController::class, 'products'])->name('mobile.products');
    Route::post('/products', [App\Http\Controllers\MobileController::class, 'storeProduct'])->name('mobile.products.store');
    Route::post('/products/{product}/update', [App\Http\Controllers\MobileController::class, 'updateProduct'])->name('mobile.products.update');
    Route::get('/products-sync', [App\Http\Controllers\MobileController::class, 'syncProducts'])->name('mobile.products.sync');
    // User Management
    Route::get('/users', [App\Http\Controllers\MobileController::class, 'users'])->name('mobile.users');
    Route::post('/users', [App\Http\Controllers\MobileController::class, 'storeUser'])->name('mobile.users.store');
    Route::post('/users/{user}/permissions', [App\Http\Controllers\MobileController::class, 'updateUserPermissions'])->name('mobile.users.permissions');
    // Settings & Master
    Route::get('/settings', [App\Http\Controllers\MobileController::class, 'settings'])->name('mobile.settings');
    Route::post('/settings/branch', [App\Http\Controllers\MobileController::class, 'storeBranch'])->name('mobile.settings.branch.store');
    Route::delete('/settings/branch/{branch}', [App\Http\Controllers\MobileController::class, 'deleteBranch'])->name('mobile.settings.branch.delete');
    Route::get('/settings/product-types', [App\Http\Controllers\MobileController::class, 'productTypes'])->name('mobile.settings.product-types');
    Route::post('/settings/product-types', [App\Http\Controllers\MobileController::class, 'storeProductType'])->name('mobile.settings.product-types.store');
    Route::get('/settings/product-groups', [App\Http\Controllers\MobileController::class, 'productGroups'])->name('mobile.settings.product-groups');
    Route::post('/settings/product-groups', [App\Http\Controllers\MobileController::class, 'storeProductGroup'])->name('mobile.settings.product-groups.store');
    // Export Planning (MRP)
    // Collection Report & Targets (Mobile)
    Route::get('/collection-report', [App\Http\Controllers\MobileController::class, 'collectionReport'])->name('mobile.collection-report');
    Route::get('/agent-targets', [App\Http\Controllers\MobileController::class, 'agentTargetsIndex'])->name('mobile.agent-targets.index');
    Route::post('/agent-targets', [App\Http\Controllers\MobileController::class, 'agentTargetsStore'])->name('mobile.agent-targets.store');
    Route::post('/team-targets', [App\Http\Controllers\MobileController::class, 'teamTargetsStore'])->name('mobile.team-targets.store');
    Route::get('/teams-setup', [App\Http\Controllers\MobileController::class, 'teamsSetup'])->name('mobile.teams.setup');
    Route::post('/collection/teams', [App\Http\Controllers\MobileController::class, 'storeTeam'])->name('mobile.reports.collection.teams.store');
    Route::put('/collection/teams/{team}', [App\Http\Controllers\MobileController::class, 'updateTeam'])->name('mobile.reports.collection.teams.update');
    Route::delete('/collection/teams/{team}', [App\Http\Controllers\MobileController::class, 'deleteTeam'])->name('mobile.reports.collection.teams.destroy');
});

// System Management (Admin Only)
use App\Http\Controllers\SystemController;
Route::middleware(['auth'])->group(function () {
    Route::get('/system',                [SystemController::class, 'index'])->name('system.index');
    Route::get('/system/backup/download',[SystemController::class, 'backupDownload'])->name('system.backup.download');
    Route::post('/system/restore',       [SystemController::class, 'restoreUpload'])->name('system.restore.upload');
    Route::post('/system/update',        [SystemController::class, 'applyUpdate'])->name('system.update.apply');
    Route::post('/system/cache/clear',   [SystemController::class, 'clearCache'])->name('system.cache.clear');
});

require __DIR__.'/auth.php';

