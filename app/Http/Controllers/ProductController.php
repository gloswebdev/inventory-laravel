<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        Product::whereIn('id', $request->product_ids)->delete();

        return redirect()->back()->with('success', 'Selected products deleted successfully.');
    }

    public function index(Request $request)
    {
        $query = Product::with(['group', 'type'])->orderBy('name');

        // Apply Access Control
        $this->applyTypeFilters($query);

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%")
                  ->orWhere('technical_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('group_id') && $request->group_id != '') {
            $query->where('group_id', $request->group_id);
        }

        if ($request->has('product_type_id') && $request->product_type_id != '') {
            $query->where('product_type_id', $request->product_type_id);
        }

        if ($request->has('rm_type') && $request->rm_type != '') {
            $query->where('rm_type', $request->rm_type);
        }

        $perPage = $request->input('per_page', 50);
        if ($perPage === 'all') {
            $perPage = 1000000; // Large number to show all
        }

        $products = $query->paginate($perPage)->withQueryString();
        $groups = ProductGroup::orderBy('group_name')->get();
        
        $typesQuery = ProductType::orderBy('type_name');
        $rmTypesQuery = \App\Models\ProductAttribute::where('type', 'rm_type')->orderBy('value');
        
        $user = Auth::user();
        if ($user->role !== 'admin') {
            $permittedTypeIds = $user->getPermittedProductTypeIds();
            $permittedRMTypes = $user->getPermittedRMTypes();
            
            $typesQuery->whereIn('id', $permittedTypeIds);
            $rmTypesQuery->whereIn('value', $permittedRMTypes);
        }
        
        $types = $typesQuery->get();

        // Fetch distinct values for dropdowns/datalists from ProductAttribute model
        $categories = \App\Models\ProductAttribute::where('type', 'category')->orderBy('value')->get(['id', 'value']);
        $forms = \App\Models\ProductAttribute::where('type', 'form')->orderBy('value')->get(['id', 'value']);
        $rmTypes = $rmTypesQuery->get(['id', 'value']);
        $packNames = \App\Models\ProductAttribute::where('type', 'pack_name')->orderBy('value')->get(['id', 'value']);

        // Fetch Factory (Branch 2) stock from external API
        $externalStock = $this->getFactoryStock();

        return view('products.index', compact('products', 'groups', 'types', 'categories', 'forms', 'rmTypes', 'packNames', 'externalStock'));
    }

    /**
     * Fetch stock for Branch 2 (Factory) from the external API.
     * Cached for 5 minutes to avoid hammering the API on every page load.
     */
    private function getFactoryStock(): array
    {
        return Cache::remember('external_stock_branch2', 300, function () {
            try {
                $response = Http::timeout(30)->post('https://logicapi.algebraerp.com/API/SYNWOOD/ProductWiseInventory', [
                    'apikey' => 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14',
                    'Branch' => '2',
                    'Item'   => 'ALL',
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['response']) && $data['response'] === 'success' && isset($data['resultdata'])) {
                        $stockMap = [];
                        foreach ($data['resultdata'] as $item) {
                            $iCode = $item['User_Code'];
                            $stockMap[$iCode] = (float) $item['ClosingQty'];
                        }
                        return $stockMap;
                    }
                }
            } catch (\Exception $e) {
                Log::error('Product Master Stock API Error: ' . $e->getMessage());
            }
            return [];
        });
    }

    public function create()
    {
        $groups = ProductGroup::orderBy('group_name')->get();
        $types = ProductType::orderBy('type_name')->get();
        // return view('products.create', compact('groups', 'types'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'uom' => 'required|string|max:50',
            'price' => 'numeric|min:0',
            'group_id' => 'nullable|exists:product_groups,id',
            'product_type_id' => 'required|exists:product_types,id',
            'low_alert_quantity' => 'numeric|min:0',
            'item_code' => 'nullable|string|max:255|unique:products,item_code',
            'category' => 'nullable|string|max:255',
            'form' => 'nullable|string|max:255',
            'technical_name' => 'nullable|string|max:255',
            'rm_type' => 'nullable|string|max:255',
            'pack_name' => 'nullable|string|max:255',
            'unit_box' => 'nullable|string|max:255',
            'weight_unit' => 'nullable|string|max:255',
            'weight_in' => 'nullable|string|max:255',
        ]);

        Product::create($validated);

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $groups = ProductGroup::orderBy('group_name')->get();
        $types = ProductType::orderBy('type_name')->get();
        // return view('products.edit', compact('product', 'groups', 'types'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'uom' => 'required|string|max:50',
            'price' => 'numeric|min:0',
            'group_id' => 'nullable|exists:product_groups,id',
            'product_type_id' => 'required|exists:product_types,id',
            'low_alert_quantity' => 'numeric|min:0',
            'item_code' => ['nullable', 'string', 'max:255', Rule::unique('products', 'item_code')->ignore($product->id)],
            'category' => 'nullable|string|max:255',
            'form' => 'nullable|string|max:255',
            'technical_name' => 'nullable|string|max:255',
            'rm_type' => 'nullable|string|max:255',
            'pack_name' => 'nullable|string|max:255',
            'unit_box' => 'nullable|string|max:255',
            'weight_unit' => 'nullable|string|max:255',
            'weight_in' => 'nullable|string|max:255',
        ]);

        $product->update($validated);

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }


    public function export(Request $request)
    {
        $filters = $request->only(['search', 'group_id', 'product_type_id', 'rm_type']);
        return (new \App\Exports\ProductsExport($filters))->download('products_master.xlsx');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

    /**
     * Sync products from the external Algebra ERP ProductMaster API.
     * Fetches all products and upserts them into the local DB.
     * Same item_code => update, new item_code => create.
     */
    public function syncFromApi()
    {
        try {
            $response = Http::timeout(60)->post('https://logicapi.algebraerp.com/API/SYNWOOD/ProductMaster', [
                'apikey'       => 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14',
                'Itemdetcode'  => '0',
                'Usercode'     => '0',
                'Branchcode'   => '0',
                'PageNumber'   => '1',
                'RowsOfPage'   => '10000',
                'modifieddate' => '',
                'TxnType'      => 'Old',
            ]);

            if (!$response->successful()) {
                \App\Models\ProductSyncLog::create([
                    'status' => 'failed',
                    'error_message' => 'HTTP ' . $response->status(),
                    'synced_by' => Auth::user()->name ?? 'Unknown',
                ]);
                return redirect()->back()->with('error', 'API Sync failed: HTTP ' . $response->status());
            }

            $data = $response->json();

            if (!isset($data['response']) || $data['response'] !== 'success' || !isset($data['resultdata'])) {
                \App\Models\ProductSyncLog::create([
                    'status' => 'failed',
                    'error_message' => 'Invalid response from API',
                    'synced_by' => Auth::user()->name ?? 'Unknown',
                ]);
                return redirect()->back()->with('error', 'API Sync failed: Invalid response from API.');
            }

            $items = $data['resultdata'];
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $createdItems = [];
            $updatedItems = [];

            DB::beginTransaction();

            // Type mapping from API Group5 to local ProductType
            $typeMap = [
                'FINISHED GOODS'      => 'Finished Good',
                'RAW MATERIAL'        => 'Raw Material',
                'SEMI FINISHED GOODS' => 'Semi Finished Good',
                'PACKING MATERIAL'    => 'Packing Material',
            ];

            foreach ($items as $item) {
                $itemCode = trim($item['User_Code'] ?? '');
                $itemName = trim($item['Item_hd_name'] ?? '');

                if (!$itemCode || !$itemName) {
                    $skipped++;
                    continue;
                }

                // Resolve Type
                $group5 = trim($item['Group5'] ?? '');
                $typeName = $typeMap[$group5] ?? 'General';
                $productType = ProductType::firstOrCreate(['type_name' => $typeName]);

                // Resolve Category (Group1) as group
                $categoryRaw = trim($item['Group1'] ?? '');
                $groupId = null;
                if ($categoryRaw && $categoryRaw !== '(NIL)') {
                    $group = ProductGroup::firstOrCreate(['group_name' => $categoryRaw]);
                    $groupId = $group->id;
                }

                // Extract Size for weight_unit parsing
                $size = trim($item['Size'] ?? '');
                $weightUnit = $size;
                $weightIn = '';
                if (preg_match('/\b(KG|KGS|GM|LTR|LTRS|ML|MG)\b/i', $size, $unitMatch)) {
                    $weightIn = strtoupper($unitMatch[1]);
                    if (in_array($weightIn, ['KG', 'KGS'])) $weightIn = 'KGS';
                    if (in_array($weightIn, ['LTR', 'LTRS'])) $weightIn = 'LTRS';
                }

                $existingProduct = Product::where('item_code', $itemCode)->first();

                if ($existingProduct) {
                    $existingProduct->update([
                        'name'            => $itemName,
                        'category'        => ($categoryRaw && $categoryRaw !== '(NIL)') ? $categoryRaw : $existingProduct->category,
                        'form'            => (trim($item['Group2'] ?? '') && trim($item['Group2'] ?? '') !== '(NIL)') ? trim($item['Group2'] ?? '') : $existingProduct->form,
                        'technical_name'  => (trim($item['Group3'] ?? '') && trim($item['Group3'] ?? '') !== '(NIL)') ? trim($item['Group3'] ?? '') : $existingProduct->technical_name,
                        'rm_type'         => (trim($item['Group4'] ?? '') && trim($item['Group4'] ?? '') !== '(NIL)') ? trim($item['Group4'] ?? '') : $existingProduct->rm_type,
                        'group_id'        => $groupId ?? $existingProduct->group_id,
                        'product_type_id' => $productType->id,
                        'pack_name'       => $size ?: $existingProduct->pack_name,
                        'unit_box'        => trim($item['cf_1'] ?? '') ?: $existingProduct->unit_box,
                        'weight_unit'     => $weightUnit ?: $existingProduct->weight_unit,
                        'weight_in'       => $weightIn ?: $existingProduct->weight_in,
                        'uom'             => $weightIn ?: $existingProduct->uom,
                        'price'           => (float)($item['MRP'] ?? 0) ?: $existingProduct->price,
                    ]);
                    $updatedItems[] = ['item_code' => $itemCode, 'name' => $itemName, 'type' => $typeName];
                    $updated++;
                } else {
                    Product::create([
                        'item_code'       => $itemCode,
                        'name'            => $itemName,
                        'category'        => ($categoryRaw && $categoryRaw !== '(NIL)') ? $categoryRaw : null,
                        'form'            => (trim($item['Group2'] ?? '') && trim($item['Group2'] ?? '') !== '(NIL)') ? trim($item['Group2'] ?? '') : null,
                        'technical_name'  => (trim($item['Group3'] ?? '') && trim($item['Group3'] ?? '') !== '(NIL)') ? trim($item['Group3'] ?? '') : null,
                        'rm_type'         => (trim($item['Group4'] ?? '') && trim($item['Group4'] ?? '') !== '(NIL)') ? trim($item['Group4'] ?? '') : null,
                        'group_id'        => $groupId,
                        'product_type_id' => $productType->id,
                        'pack_name'       => $size ?: null,
                        'unit_box'        => trim($item['cf_1'] ?? '') ?: null,
                        'weight_unit'     => $weightUnit ?: null,
                        'weight_in'       => $weightIn ?: null,
                        'uom'             => $weightIn ?: 'N/A',
                        'price'           => (float)($item['MRP'] ?? 0),
                        'low_alert_quantity' => 0,
                    ]);
                    $createdItems[] = ['item_code' => $itemCode, 'name' => $itemName, 'type' => $typeName];
                    $created++;
                }
            }

            DB::commit();

            // Save sync log
            $syncLog = \App\Models\ProductSyncLog::create([
                'total_created'  => $created,
                'total_updated'  => $updated,
                'total_skipped'  => $skipped,
                'created_items'  => $createdItems,
                'updated_items'  => $updatedItems,
                'status'         => 'success',
                'synced_by'      => Auth::user()->name ?? 'Unknown',
            ]);

            return redirect()->back()->with('success', "API Sync Complete! Created: {$created}, Updated: {$updated}, Skipped: {$skipped}")
                                      ->with('sync_log_id', $syncLog->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product API Sync Error: ' . $e->getMessage());
            \App\Models\ProductSyncLog::create([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'synced_by' => Auth::user()->name ?? 'Unknown',
            ]);
            return redirect()->back()->with('error', 'API Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Get sync report data as JSON (for AJAX modal).
     */
    public function syncReport($id)
    {
        $log = \App\Models\ProductSyncLog::findOrFail($id);
        return response()->json($log);
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
