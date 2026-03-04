<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
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

    // Calculators
    Route::prefix('planning')->name('planning.')->group(function() {
        Route::get('/', [App\Http\Controllers\IndentController::class, 'index'])->name('index');
        Route::post('/calculate', [App\Http\Controllers\IndentController::class, 'calculate'])->name('calculate');
        Route::post('/export', [App\Http\Controllers\IndentController::class, 'export'])->name('export');
    });

    Route::prefix('indent')->name('indent.')->group(function() {
        Route::get('/', [App\Http\Controllers\PlanningController::class, 'index'])->name('index');
        Route::post('/calculate', [App\Http\Controllers\PlanningController::class, 'calculate'])->name('calculate_inner');
        Route::post('/store', [App\Http\Controllers\PlanningController::class, 'store'])->name('store');
        Route::get('/get-stock', [App\Http\Controllers\PlanningController::class, 'getStock'])->name('get-stock');
        Route::get('/bulk-stock', [App\Http\Controllers\PlanningController::class, 'getBulkStock'])->name('bulk-stock');
        Route::get('/show/{indent}', [App\Http\Controllers\PlanningController::class, 'show'])->name('show');
        Route::get('/show/{indent}/print', [App\Http\Controllers\PlanningController::class, 'print'])->name('print');
        Route::get('/show/{indent}/excel', [App\Http\Controllers\PlanningController::class, 'exportExcel'])->name('excel');
        Route::get('/show/{indent}/pdf', [App\Http\Controllers\PlanningController::class, 'exportPdf'])->name('pdf');
        Route::get('/show/{indent}/process', [App\Http\Controllers\PlanningController::class, 'process'])->name('process');
        Route::get('/process-list', [App\Http\Controllers\PlanningController::class, 'processList'])->name('process.list');
        Route::get('/show/{indent}/process/excel', [App\Http\Controllers\PlanningController::class, 'exportProcessExcel'])->name('process.excel');
        Route::get('/show/{indent}/process/pdf', [App\Http\Controllers\PlanningController::class, 'exportProcessPdf'])->name('process.pdf');
        Route::post('/show/{indent}/complete', [App\Http\Controllers\PlanningController::class, 'updateCompletion'])->name('complete');
    });

    // Reports
    Route::get('reports/stock-ledger', [ReportController::class, 'stockLedger'])->name('reports.stock-ledger');
    Route::get('reports/live-stock', [ReportController::class, 'liveStock'])->name('reports.live-stock');
    Route::get('reports/live-stock/excel', [ReportController::class, 'exportLiveStockExcel'])->name('reports.live-stock.excel');
    Route::get('reports/live-stock/pdf', [ReportController::class, 'exportLiveStockPdf'])->name('reports.live-stock.pdf');

    // Settings
    Route::get('settings/branches', [App\Http\Controllers\SettingController::class, 'index'])->name('settings.branches.index');
    Route::post('settings/branches/store', [App\Http\Controllers\SettingController::class, 'storeBranch'])->name('settings.branches.store');
    Route::post('settings/branches/update', [App\Http\Controllers\SettingController::class, 'updateBranches'])->name('settings.branches.update');
    Route::delete('settings/branches/{branch}', [App\Http\Controllers\SettingController::class, 'deleteBranch'])->name('settings.branches.destroy');
});

// Mobile PWA Interface
Route::prefix('mobile')->middleware(['auth', 'interface:mobile'])->group(function () {
    Route::get('/', [App\Http\Controllers\MobileController::class, 'index'])->name('mobile.dashboard');
    Route::get('/stock', [App\Http\Controllers\MobileController::class, 'stock'])->name('mobile.stock');
    Route::get('/production', [App\Http\Controllers\MobileController::class, 'production'])->name('mobile.production');
    Route::post('/production-store', [App\Http\Controllers\MobileController::class, 'submitProduction'])->name('mobile.production.store');
    Route::get('/planning', [App\Http\Controllers\MobileController::class, 'planning'])->name('mobile.planning');
    Route::post('/planning/calculate', [App\Http\Controllers\MobileController::class, 'calculateMRP'])->name('mobile.planning.calculate');
    Route::get('/indents', [App\Http\Controllers\MobileController::class, 'indents'])->name('mobile.indents');
    Route::post('/indents-store', [App\Http\Controllers\MobileController::class, 'storeIndent'])->name('mobile.indents.store');
    Route::post('/fg-stock', [App\Http\Controllers\MobileController::class, 'getFGStock'])->name('mobile.fg-stock');
    Route::get('/indents/{indent}/process', [App\Http\Controllers\MobileController::class, 'process'])->name('mobile.indents.process');
    Route::post('/indents/{indent}/completion', [App\Http\Controllers\MobileController::class, 'updateCompletion'])->name('mobile.indents.completion');
    Route::get('/indents/{indent}/excel', [App\Http\Controllers\MobileController::class, 'exportExcel'])->name('mobile.indents.excel');
    Route::get('/indents/{indent}/pdf', [App\Http\Controllers\MobileController::class, 'exportPdf'])->name('mobile.indents.pdf');
    Route::get('/indents/{indent}/process/excel', [App\Http\Controllers\MobileController::class, 'exportProcessExcel'])->name('mobile.indents.process.excel');
    Route::get('/indents/{indent}/process/pdf', [App\Http\Controllers\MobileController::class, 'exportProcessPdf'])->name('mobile.indents.process.pdf');
    Route::get('/stock/excel', [App\Http\Controllers\MobileController::class, 'exportStockExcel'])->name('mobile.stock.excel');
    Route::get('/stock/pdf', [App\Http\Controllers\MobileController::class, 'exportStockPdf'])->name('mobile.stock.pdf');
});

require __DIR__.'/auth.php';
