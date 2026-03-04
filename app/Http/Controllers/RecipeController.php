<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $query = Recipe::with(['finishedProduct.type', 'items.rawMaterial']);

        // Apply Access Control
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

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->whereHas('finishedProduct', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($request->has('type_id') && $request->type_id != '') {
            $query->whereHas('finishedProduct', function($q) use ($request) {
                $q->where('product_type_id', $request->type_id);
            });
        }

        $perPage = $request->get('per_page', 20);
        if ($perPage === 'all') {
            $recipes = $query->orderByDesc('created_at')->get();
        } else {
            $recipes = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();
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

        $finishedGoods = $fgQuery->get();
        $rawMaterials = $rmQuery->get();
        $types = $typesQuery->get();

        return view('recipes.index', compact('recipes', 'finishedGoods', 'rawMaterials', 'types'));
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:recipes,id'
        ]);

        DB::transaction(function () use ($request) {
            Recipe::whereIn('id', $request->ids)->delete();
        });

        return response()->json(['success' => true]);
    }

    public function update(Request $request, Recipe $recipe)
    {
        $validated = $request->validate([
            'finished_product_id' => 'required|exists:products,id|unique:recipes,finished_product_id,' . $recipe->id,
            'yield_quantity' => 'required|numeric|min:0.001',
            'yield_uom' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($recipe, $validated) {
            $recipe->update([
                'finished_product_id' => $validated['finished_product_id'],
                'yield_quantity' => $validated['yield_quantity'],
                'yield_uom' => $validated['yield_uom'],
            ]);

            // Sync items: Delete existing and create new (simplest for recipes)
            $recipe->items()->delete();

            foreach ($validated['items'] as $item) {
                RecipeItem::create([
                    'recipe_id' => $recipe->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        });

        return redirect()->route('recipes.index')->with('success', 'Recipe updated successfully.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'finished_product_id' => 'required|exists:products,id|unique:recipes,finished_product_id',
            'yield_quantity' => 'required|numeric|min:0.001',
            'yield_uom' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($validated) {
            $recipe = Recipe::create([
                'finished_product_id' => $validated['finished_product_id'],
                'yield_quantity' => $validated['yield_quantity'],
                'yield_uom' => $validated['yield_uom'],
            ]);

            foreach ($validated['items'] as $item) {
                RecipeItem::create([
                    'recipe_id' => $recipe->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        });

        return redirect()->route('recipes.index')->with('success', 'Recipe created successfully.');
    }


    public function export(Request $request)
    {
        return (new \App\Exports\RecipesExport($request->search))->download('recipes_master.xlsx');
    }


    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\RecipeTemplateExport, 'recipe_import_template.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx,csv,xls',
        ]);

        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\RecipesImport, $request->file('excel_file'));

        return redirect()->route('recipes.index')->with('success', 'Recipes imported successfully.');
    }

    public function destroy(Recipe $recipe)
    {
        $recipe->delete();
        return redirect()->route('recipes.index')->with('success', 'Recipe deleted successfully.');
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
