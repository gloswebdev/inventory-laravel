<?php

namespace App\Http\Controllers;

use App\Models\Adjustment;
use App\Models\Product;
use App\Models\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AdjustmentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
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
        $this->applyTypeFilters($productsQuery);
        $products = $productsQuery->get();
        
        return view('adjustments.index', compact('adjustments', 'products'));
    }

    public function create()
    {
        $products = Product::orderBy('name')->get();
        // return view('adjustments.create', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'adjustment_type' => 'required|in:add,deduct',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($validated) {
            $product = Product::lockForUpdate()->find($validated['product_id']);

            if ($validated['adjustment_type'] === 'deduct' && $product->current_stock < $validated['quantity']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => "Insufficient stock. Available: {$product->current_stock}",
                ]);
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

        return redirect()->route('adjustments.index')->with('success', 'Adjustment saved successfully.');
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
}
