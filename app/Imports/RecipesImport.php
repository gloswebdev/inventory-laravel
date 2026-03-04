<?php

namespace App\Imports;

use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;

class RecipesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        // Group by Item Code (primary) or Product Name (secondary)
        $grouped = $rows->groupBy(function($row) {
            return $row['fg_item_code'] ?? $row['finished_product'];
        });

        foreach ($grouped as $key => $items) {
            if (empty($key)) continue;

            // Find Finished Product by Item Code or Name
            $finishedProduct = Product::where('item_code', $key)
                ->orWhere('name', $key)
                ->first();

            if (!$finishedProduct) continue;

            $firstItem = $items->first();
            $yieldQty = $firstItem['yield_qty'];
            $yieldUom = $firstItem['yield_uom'] ?? $finishedProduct->uom;

            DB::transaction(function () use ($finishedProduct, $yieldQty, $yieldUom, $items) {
                $recipe = Recipe::updateOrCreate(
                    ['finished_product_id' => $finishedProduct->id],
                    [
                        'yield_quantity' => $yieldQty,
                        'yield_uom' => $yieldUom,
                    ]
                );

                // Sync items
                $recipe->items()->delete();

                foreach ($items as $item) {
                    $rmKey = $item['rm_item_code'] ?? $item['raw_material'];
                    if (empty($rmKey)) continue;

                    $rawMaterial = Product::where('item_code', $rmKey)
                        ->orWhere('name', $rmKey)
                        ->first();

                    if ($rawMaterial) {
                        RecipeItem::create([
                            'recipe_id' => $recipe->id,
                            'raw_material_id' => $rawMaterial->id,
                            'quantity' => $item['rm_qty'],
                        ]);
                    }
                }
            });
        }
    }
}
