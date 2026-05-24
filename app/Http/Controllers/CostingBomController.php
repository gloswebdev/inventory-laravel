<?php

namespace App\Http\Controllers;

use App\Models\CostingBom;
use App\Models\CostingBomItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CostingBomController extends Controller
{
    public function index(Request $request)
    {
        if (!Auth::user()->hasPermission('costing', 'view')) {
            abort(403, 'Access denied to Costing BOM module.');
        }

        $query = CostingBom::with(['finishedProduct.type', 'items.rawMaterial']);
        $user = Auth::user();

        // Apply access control (similar to Recipe Master)
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

        if ($request->filled('type_id')) {
            $query->whereHas('finishedProduct', function($q) use ($request) {
                $q->where('product_type_id', $request->type_id);
            });
        }

        $perPage = $request->get('per_page', 20);
        if ($perPage === 'all') {
            $boms = $query->orderByDesc('created_at')->get();
        } else {
            $boms = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();
        }

        $fgQuery = Product::orderBy('name');
        $rmQuery = Product::orderBy('name');
        $typesQuery = \App\Models\ProductType::orderBy('type_name');

        if ($user->role !== 'admin') {
            $this->applyTypeFilters($fgQuery);
            $this->applyTypeFilters($rmQuery);
            
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $typesQuery->whereIn('id', $permittedTypeIds);
        }

        $finishedGoods = $fgQuery->get(['id', 'name', 'pack_name', 'uom', 'item_code', 'product_type_id']);
        $rawMaterials = $rmQuery->get(['id', 'name', 'pack_name', 'uom', 'item_code', 'product_type_id', 'rm_type']);
        $types = $typesQuery->get();

        return view('costing.bom.index', compact('boms', 'finishedGoods', 'rawMaterials', 'types'));
    }

    public function store(Request $request)
    {
        if (!Auth::user()->hasPermission('costing', 'create')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'finished_product_id' => 'required|exists:products,id|unique:costing_boms,finished_product_id',
            'yield_quantity' => 'required|numeric|min:0.001',
            'yield_uom' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($validated) {
            $bom = CostingBom::create([
                'finished_product_id' => $validated['finished_product_id'],
                'yield_quantity'      => $validated['yield_quantity'],
                'yield_uom'           => $validated['yield_uom'],
            ]);

            foreach ($validated['items'] as $item) {
                CostingBomItem::create([
                    'costing_bom_id'  => $bom->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity'        => $item['quantity'],
                ]);
            }
        });

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Costing BOM created successfully.']);
        }
        return redirect()->route('costing.boms.index')->with('success', 'Costing BOM created successfully.');
    }

    public function update(Request $request, CostingBom $costingBom)
    {
        if (!Auth::user()->hasPermission('costing', 'edit')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'finished_product_id' => 'required|exists:products,id|unique:costing_boms,finished_product_id,' . $costingBom->id,
            'yield_quantity' => 'required|numeric|min:0.001',
            'yield_uom' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($costingBom, $validated) {
            $costingBom->update([
                'finished_product_id' => $validated['finished_product_id'],
                'yield_quantity'      => $validated['yield_quantity'],
                'yield_uom'           => $validated['yield_uom'],
            ]);

            $costingBom->items()->delete();

            foreach ($validated['items'] as $item) {
                CostingBomItem::create([
                    'costing_bom_id'  => $costingBom->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity'        => $item['quantity'],
                ]);
            }
        });

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Costing BOM updated successfully.']);
        }
        return redirect()->route('costing.boms.index')->with('success', 'Costing BOM updated successfully.');
    }

    public function destroy(CostingBom $costingBom)
    {
        if (!Auth::user()->hasPermission('costing', 'delete')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $costingBom->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Costing BOM deleted successfully.']);
        }
        return redirect()->route('costing.boms.index')->with('success', 'Costing BOM deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        if (!Auth::user()->hasPermission('costing', 'delete')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:costing_boms,id'
        ]);

        DB::transaction(function () use ($request) {
            CostingBom::whereIn('id', $request->ids)->delete();
        });

        return response()->json(['success' => true, 'message' => 'Selected Costing BOMs deleted.']);
    }

    public function export(Request $request)
    {
        if (!Auth::user()->hasPermission('costing', 'view')) {
            abort(403);
        }

        return (new \App\Exports\CostingBomsExport($request->search))->download('costing_boms.xlsx');
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
