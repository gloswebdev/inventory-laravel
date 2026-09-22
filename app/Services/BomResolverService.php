<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\CostingBom;
use App\Models\CostingBomPackingMaterial;
use App\Models\Pricelist;

class BomResolverService
{
    /**
     * Resolve all BOM requirements for a product.
     *
     * @param Product $product
     * @param float $quantityBoxes Number of boxes (or units if not boxed)
     * @param bool $includeFormulation Whether to include chemical formulation raw materials
     * @param bool $includePackaging Whether to include packaging materials
     * @return array
     */
    public function resolve(Product $product, float $quantityBoxes, bool $includeFormulation = false, bool $includePackaging = true): array
    {
        $unitPerBox = (float)($product->unit_box ?: 1);
        $totalUnits = $quantityBoxes * $unitPerBox;
        $totalBaseUnit = $totalUnits * $product->weight_multiplier;

        $packingMaterials = [];
        $formulationIngredients = [];

        $recipe = Recipe::where('finished_product_id', $product->id)
            ->with(['items.rawMaterial.type'])
            ->first();

        // ── 1. Resolve Packaging Materials (if enabled) ──────────────────────
        if ($includePackaging) {
            if ($recipe && $recipe->items->isNotEmpty()) {
            foreach ($recipe->items as $item) {
                $rm = $item->rawMaterial;
                if (!$rm) continue;

                $typeName = $rm->type->type_name ?? '';
                $isPacking = str_contains(strtoupper($typeName), 'PACKING') ||
                    in_array(strtoupper($rm->rm_type ?? ''), ['DRUM', 'BAG', 'BOTTLE', 'CAP', 'CARTON', 'LABEL', 'TAPE', 'BOX']);

                if ($isPacking) {
                    $yieldQty = (float)($recipe->yield_quantity ?: 1);
                    $requiredQty = ($item->quantity / $yieldQty) * $quantityBoxes;

                    $packingMaterials[$rm->id] = [
                        'raw_material_id' => $rm->id,
                        'name'            => $rm->name,
                        'item_code'       => $rm->item_code,
                        'pack_name'       => $rm->pack_name,
                        'uom'             => $rm->uom ?: 'NOS',
                        'required_qty'    => (float)$requiredQty,
                        'is_container'    => in_array(strtoupper($rm->rm_type ?? ''), ['DRUM', 'BOTTLE', 'CAN', 'CONTAINER']),
                        'type'            => 'packing',
                    ];
                }
            }
        }

        // Fallback to CostingBomPackingMaterial if no packing items found in recipe
        if (empty($packingMaterials) && !empty($product->item_code)) {
            $pricelist = Pricelist::where('user_code', $product->item_code)->first();
            if ($pricelist) {
                $cbpm = CostingBomPackingMaterial::with('rawMaterial.type')
                    ->where('pricelist_id', $pricelist->id)
                    ->get();

                foreach ($cbpm as $pm) {
                    $rm = $pm->rawMaterial;
                    if (!$rm) continue;

                    $requiredQty = (float)$pm->quantity * $quantityBoxes;
                    $packingMaterials[$rm->id] = [
                        'raw_material_id' => $rm->id,
                        'name'            => $rm->name,
                        'item_code'       => $rm->item_code,
                        'pack_name'       => $rm->pack_name,
                        'uom'             => $rm->uom ?: 'NOS',
                        'required_qty'    => (float)$requiredQty,
                        'is_container'    => (bool)$pm->is_container,
                        'type'            => 'packing',
                    ];
                }
            }
        }
        }

        // ── 2. Resolve Chemical Formulation Ingredients (if enabled) ────────
        if ($includeFormulation) {
            $costingBom = $this->findMatchingCostingBom($product);

            if ($costingBom && $costingBom->items->isNotEmpty()) {
                $yield = (float)($costingBom->yield_quantity ?: 1);
                foreach ($costingBom->items as $cbItem) {
                    $rm = $cbItem->rawMaterial;
                    if (!$rm) continue;

                    $requiredQty = ((float)$cbItem->quantity / $yield) * $totalBaseUnit;

                    if (isset($formulationIngredients[$rm->id])) {
                        $formulationIngredients[$rm->id]['required_qty'] += $requiredQty;
                    } else {
                        $formulationIngredients[$rm->id] = [
                            'raw_material_id' => $rm->id,
                            'name'            => $rm->name,
                            'item_code'       => $rm->item_code,
                            'pack_name'       => $rm->pack_name,
                            'uom'             => $rm->uom ?: 'KG',
                            'required_qty'    => (float)$requiredQty,
                            'purity'          => $cbItem->purity,
                            'type'            => 'formulation',
                        ];
                    }
                }
            } elseif ($recipe && $recipe->items->isNotEmpty()) {
                // Fallback to non-packing items in Recipe
                $yield = (float)($recipe->yield_quantity ?: 1);
                foreach ($recipe->items as $item) {
                    $rm = $item->rawMaterial;
                    if (!$rm) continue;

                    $typeName = $rm->type->type_name ?? '';
                    $isPacking = str_contains(strtoupper($typeName), 'PACKING') ||
                        in_array(strtoupper($rm->rm_type ?? ''), ['DRUM', 'BAG', 'BOTTLE', 'CAP', 'CARTON', 'LABEL', 'TAPE', 'BOX']);

                    if (!$isPacking) {
                        $requiredQty = ((float)$item->quantity / $yield) * $totalBaseUnit;
                        $formulationIngredients[$rm->id] = [
                            'raw_material_id' => $rm->id,
                            'name'            => $rm->name,
                            'item_code'       => $rm->item_code,
                            'pack_name'       => $rm->pack_name,
                            'uom'             => $rm->uom ?: 'KG',
                            'required_qty'    => (float)$requiredQty,
                            'purity'          => null,
                            'type'            => 'formulation',
                        ];
                    }
                }
            }
        }

        $hasRecipe = !empty($recipe) && $recipe->items->isNotEmpty();
        $hasCostingBom = !empty($costingBom) || !empty($cbpm);

        // Normalize all item arrays to have both key conventions
        $normalizeItems = function(array $items) {
            return array_map(function($item) {
                $item['id'] = $item['raw_material_id'];
                $item['required_quantity'] = $item['required_qty'];
                return $item;
            }, array_values($items));
        };

        $normalizedPacking = $normalizeItems($packingMaterials);
        $normalizedFormulation = $normalizeItems($formulationIngredients);
        $normalizedAll = array_merge($normalizedPacking, $normalizedFormulation);

        return [
            'product'                 => [
                'id'                => $product->id,
                'name'              => $product->name,
                'item_code'         => $product->item_code,
                'pack_name'         => $product->pack_name,
                'unit_box'          => $unitPerBox,
                'weight_multiplier' => $product->weight_multiplier,
            ],
            'quantity_boxes'          => $quantityBoxes,
            'total_units'             => $totalUnits,
            'total_base_qty'          => $totalBaseUnit,
            'include_packaging'       => $includePackaging,
            'include_formulation'     => $includeFormulation,
            'has_recipe'              => $hasRecipe,
            'has_costing_bom'         => $hasCostingBom,
            'packing_materials'       => $normalizedPacking,
            'formulation_ingredients' => $normalizedFormulation,
            'formulation_materials'   => $normalizedFormulation,
            'all_materials'           => $normalizedAll,
        ];
    }

    /**
     * Find matching Costing BOM for a product.
     */
    public function findMatchingCostingBom(Product $product): ?CostingBom
    {
        // 1. Direct match
        $direct = CostingBom::where('finished_product_id', $product->id)
            ->with('items.rawMaterial')
            ->first();
        if ($direct) {
            return $direct;
        }

        // 2. Via Pricelist and formulation/group3 matching
        if (!empty($product->item_code)) {
            $pricelist = Pricelist::where('user_code', $product->item_code)->first();
            if ($pricelist) {
                $normalize = function ($str) {
                    $str = preg_replace('/\[[^\]]*\]|\([^\)]*\)/', '', $str ?? '');
                    return trim(preg_replace('/[^a-zA-Z0-9\+]/', '', strtolower($str)));
                };

                $plNormGrp3 = $normalize($pricelist->group3);
                $plNormName = $normalize($pricelist->item_hd_name);
                $prodNormName = $normalize($product->name);

                $boms = CostingBom::with(['finishedProduct', 'items.rawMaterial'])->get();
                foreach ($boms as $bom) {
                    $fpName = $bom->finishedProduct->name ?? '';
                    $fpNorm = $normalize($fpName);

                    if (!empty($fpNorm)) {
                        if (!empty($plNormGrp3) && $plNormGrp3 !== 'nil' && (str_contains($fpNorm, $plNormGrp3) || str_contains($plNormGrp3, $fpNorm))) {
                            return $bom;
                        }
                        if (!empty($plNormName) && (str_contains($fpNorm, $plNormName) || str_contains($plNormName, $fpNorm))) {
                            return $bom;
                        }
                        if (!empty($prodNormName) && (str_contains($fpNorm, $prodNormName) || str_contains($prodNormName, $fpNorm))) {
                            return $bom;
                        }
                    }
                }
            }
        }

        return null;
    }
}
