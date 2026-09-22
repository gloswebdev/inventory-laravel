<?php

namespace App\Http\Controllers;

use App\Models\Adjustment;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Branch;
use App\Models\StockLedger;
use App\Library\ErpStockPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermission('adjustments', 'view')) {
            abort(403, 'Unauthorized access to Stock Adjustments.');
        }

        $query = Adjustment::with(['product.type', 'user'])->orderByDesc('created_at');

        // Apply product type filters for non-admin users
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

        // Search and Filters
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('branch_name', 'like', "%{$search}%")
                  ->orWhere('erp_doc_no', 'like', "%{$search}%")
                  ->orWhereHas('product', function($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                         ->orWhere('item_code', 'like', "%{$search}%")
                         ->orWhere('alias_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('type') && in_array($request->type, ['add', 'deduct'])) {
            $query->where('adjustment_type', $request->type);
        }

        if ($request->filled('branch_code')) {
            $query->where('branch_code', $request->branch_code);
        }

        if ($request->filled('product_type_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('product_type_id', $request->product_type_id);
            });
        }

        // Product Types & Branches
        $productTypes = ProductType::orderBy('type_name')->get();
        
        $isBranchLocked = ($user->role !== 'admin') && ($user->hasFeature('adjustments', 'branch_lock') || !$user->hasFeature('adjustments', 'branch_select'));
        $userBranches = $user->branches;
        $lockedBranch = $isBranchLocked ? $userBranches->first() : null;

        if ($isBranchLocked && $lockedBranch) {
            $branches = $userBranches->count() > 0 ? $userBranches : Branch::where('code', $lockedBranch->code)->get();
            $query->where('branch_code', $lockedBranch->code);
        } else {
            $branches = Branch::orderBy('name')->get();
        }

        $adjustments = $query->limit(100)->get();

        // Products for adjustment form
        $productsQuery = Product::with('type')->orderBy('name');
        $this->applyTypeFilters($productsQuery);
        $products = $productsQuery->get();

        // Metrics / KPI Counters
        $totalAdjustments = Adjustment::count();
        $totalReceipts = Adjustment::where('adjustment_type', 'add')->count();
        $totalIssues = Adjustment::where('adjustment_type', 'deduct')->count();
        $unsyncedCount = Adjustment::whereIn('erp_push_status', ['pending', 'failed'])->count();

        return view('adjustments.index', compact(
            'adjustments',
            'products',
            'productTypes',
            'branches',
            'isBranchLocked',
            'lockedBranch',
            'totalAdjustments',
            'totalReceipts',
            'totalIssues',
            'unsyncedCount'
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        // Permissions check
        if ($request->adjustment_type === 'add' && !$user->hasFeature('adjustments', 'create_receipt')) {
            return $this->errorResponse($request, 'You do not have permission to record Stock Receipts (+).', 403);
        }
        if ($request->adjustment_type === 'deduct' && !$user->hasFeature('adjustments', 'create_issue')) {
            return $this->errorResponse($request, 'You do not have permission to record Stock Issues (-).', 403);
        }

        $validated = $request->validate([
            'product_id'      => 'required|exists:products,id',
            'adjustment_type' => 'required|in:add,deduct',
            'quantity'        => 'required|numeric|min:0.0001',
            'branch_code'     => 'nullable|string|max:50',
            'reason'          => 'nullable|string|max:1000',
            'sync_erp'        => 'nullable|boolean',
        ]);

        $isBranchLocked = ($user->role !== 'admin') && ($user->hasFeature('adjustments', 'branch_lock') || !$user->hasFeature('adjustments', 'branch_select'));
        $lockedBranch = $isBranchLocked ? $user->branches->first() : null;

        if ($isBranchLocked && $lockedBranch) {
            $validated['branch_code'] = $lockedBranch->code;
            $branchName = $lockedBranch->name;
        } else {
            $branchName = null;
            if (!empty($validated['branch_code'])) {
                $branch = Branch::where('code', $validated['branch_code'])->first();
                $branchName = $branch ? $branch->name : "Branch {$validated['branch_code']}";
            }
        }

        try {
            $adjustment = DB::transaction(function () use ($validated, $user, $branchName) {
                $product = Product::lockForUpdate()->find($validated['product_id']);

                if ($validated['adjustment_type'] === 'deduct' && $product->current_stock < $validated['quantity']) {
                    throw new \Exception("Insufficient stock. Available: {$product->current_stock}, Requested: {$validated['quantity']}");
                }

                $adj = Adjustment::create([
                    'product_id'      => $product->id,
                    'user_id'         => $user->id,
                    'branch_code'     => $validated['branch_code'] ?? null,
                    'branch_name'     => $branchName,
                    'adjustment_type' => $validated['adjustment_type'],
                    'quantity'        => $validated['quantity'],
                    'reason'          => $validated['reason'] ?? null,
                    'erp_push_status' => 'pending',
                ]);

                $changeQty = $validated['adjustment_type'] === 'add' ? $validated['quantity'] : -$validated['quantity'];
                
                if ($validated['adjustment_type'] === 'add') {
                    $product->increment('current_stock', $validated['quantity']);
                } else {
                    $product->decrement('current_stock', $validated['quantity']);
                }

                StockLedger::create([
                    'product_id'       => $product->id,
                    'transaction_type' => 'adjustment_' . $validated['adjustment_type'],
                    'transaction_id'   => $adj->id,
                    'change_quantity'  => $changeQty,
                    'new_stock'        => $product->current_stock,
                ]);

                return $adj;
            });

            // ERP Push
            $shouldPushErp = $request->boolean('sync_erp', true) && $user->hasFeature('adjustments', 'erp_push');
            if ($shouldPushErp) {
                try {
                    $erpService = new ErpStockPushService();
                    $erpResult = $erpService->pushAdjustment($adjustment);

                    if ($erpResult['success']) {
                        $adjustment->update([
                            'erp_push_status' => 'success',
                            'erp_doc_no'      => $erpResult['LastSavedDoc'] ?? ($erpResult['response']['LastSavedDocNo'] ?? null),
                            'erp_response'    => json_encode($erpResult['response'] ?? []),
                        ]);
                    } else {
                        $adjustment->update([
                            'erp_push_status' => 'failed',
                            'erp_response'    => json_encode($erpResult['response'] ?? ['message' => $erpResult['message']]),
                        ]);
                    }
                } catch (\Exception $erpEx) {
                    Log::warning("Adjustment ERP Push Exception: " . $erpEx->getMessage());
                    $adjustment->update([
                        'erp_push_status' => 'failed',
                        'erp_response'    => json_encode(['error' => $erpEx->getMessage()]),
                    ]);
                }
            } else {
                $adjustment->update(['erp_push_status' => 'skipped']);
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Stock adjustment recorded successfully!',
                    'adjustment' => $adjustment->load('product'),
                ]);
            }

            return redirect()->route('adjustments.index')->with('success', 'Stock adjustment recorded successfully.');

        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    public function destroy(Adjustment $adjustment, Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && !$user->hasFeature('adjustments', 'delete')) {
            return $this->errorResponse($request, 'You do not have permission to revert adjustments.', 403);
        }

        try {
            DB::transaction(function () use ($adjustment) {
                $product = Product::lockForUpdate()->find($adjustment->product_id);

                if ($product) {
                    // Reverse the effect
                    if ($adjustment->adjustment_type === 'add') {
                        $product->decrement('current_stock', $adjustment->quantity);
                        $changeQty = -$adjustment->quantity;
                    } else {
                        $product->increment('current_stock', $adjustment->quantity);
                        $changeQty = $adjustment->quantity;
                    }

                    StockLedger::create([
                        'product_id'       => $product->id,
                        'transaction_type' => 'adjustment_revert',
                        'transaction_id'   => $adjustment->id,
                        'change_quantity'  => $changeQty,
                        'new_stock'        => $product->current_stock,
                    ]);
                }

                $adjustment->delete();
            });

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Adjustment reverted successfully.']);
            }

            return redirect()->route('adjustments.index')->with('success', 'Adjustment reverted successfully.');

        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    public function retryErp(Adjustment $adjustment, Request $request)
    {
        $user = Auth::user();
        if (!$user->hasFeature('adjustments', 'erp_push')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        try {
            $erpService = new ErpStockPushService();
            $result = $erpService->pushAdjustment($adjustment);

            if ($result['success']) {
                $adjustment->update([
                    'erp_push_status' => 'success',
                    'erp_doc_no'      => $result['LastSavedDoc'] ?? ($result['response']['LastSavedDocNo'] ?? null),
                    'erp_response'    => json_encode($result['response'] ?? []),
                ]);
                return response()->json([
                    'success'  => true,
                    'message'  => 'ERP sync succeeded!',
                    'doc_no'   => $adjustment->erp_doc_no,
                    'status'   => 'success',
                ]);
            } else {
                $adjustment->update([
                    'erp_push_status' => 'failed',
                    'erp_response'    => json_encode($result['response'] ?? ['message' => $result['message']]),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'ERP sync failed.',
                    'status'  => 'failed',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkRetryErp(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasFeature('adjustments', 'erp_bulk_retry')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $unsynced = Adjustment::whereIn('erp_push_status', ['pending', 'failed'])
            ->orderBy('created_at')
            ->limit(30)
            ->get();

        if ($unsynced->isEmpty()) {
            return response()->json(['success' => true, 'message' => 'No pending adjustments to sync.']);
        }

        $erpService = new ErpStockPushService();
        $successCount = 0;
        $failedCount = 0;

        foreach ($unsynced as $adj) {
            try {
                $res = $erpService->pushAdjustment($adj);
                if ($res['success']) {
                    $adj->update([
                        'erp_push_status' => 'success',
                        'erp_doc_no'      => $res['LastSavedDoc'] ?? ($res['response']['LastSavedDocNo'] ?? null),
                        'erp_response'    => json_encode($res['response'] ?? []),
                    ]);
                    $successCount++;
                } else {
                    $adj->update([
                        'erp_push_status' => 'failed',
                        'erp_response'    => json_encode($res['response'] ?? ['message' => $res['message']]),
                    ]);
                    $failedCount++;
                }
            } catch (\Exception $e) {
                $adj->update([
                    'erp_push_status' => 'failed',
                    'erp_response'    => json_encode(['error' => $e->getMessage()]),
                ]);
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk sync complete: {$successCount} succeeded, {$failedCount} failed.",
            'success_count' => $successCount,
            'failed_count'  => $failedCount,
        ]);
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

    private function errorResponse(Request $request, string $message, int $status = 422)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        return redirect()->back()->withErrors(['error' => $message])->withInput();
    }
}
