<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $fillable = [
        'item_code',
        'price_per_unit',
        'purity',
        'price_source',
        'fetched_at',
    ];

    protected $casts = [
        'price_per_unit' => 'float',
        'purity'         => 'float',
        'fetched_at'     => 'datetime',
    ];

    /**
     * Get the price for a given item_code. Returns 0 if not found.
     * Priority: Latest PurchaseRegister rate > ProductPrice manual rate > 0
     */
    public static function getPrice(string $itemCode): float
    {
        $code = trim($itemCode);
        if (empty($code)) return 0.0;

        $prRate = \App\Models\PurchaseRegister::where('item_code', $code)
            ->where('case_rate', '>', 0)
            ->orderByDesc('vouch_date')
            ->orderByDesc('id')
            ->value('case_rate');

        if ($prRate && (float)$prRate > 0) {
            return (float)$prRate;
        }

        return (float) (static::where('item_code', $code)->value('price_per_unit') ?? 0.0);
    }

    /**
     * Bulk-load prices as an associative array [item_code => price_per_unit]
     * Priority: Latest PurchaseRegister rate overrides local/manual price.
     * Uses array_replace to avoid PHP numeric key re-indexing.
     */
    public static function allAsMap(): array
    {
        $localPrices = static::where('price_per_unit', '>', 0)->pluck('price_per_unit', 'item_code')->toArray();
        $prPrices = \App\Models\PurchaseRegister::where('case_rate', '>', 0)
            ->orderByDesc('vouch_date')
            ->orderByDesc('id')
            ->get()
            ->unique('item_code')
            ->pluck('case_rate', 'item_code')
            ->toArray();

        return array_replace($localPrices, $prPrices);
    }

    /**
     * Bulk-load purities as an associative array [item_code => purity]
     * Priority: Latest PurchaseRegister non-zero purity > ProductPrice non-zero purity > CostingBomItem purity
     * Uses array_replace to preserve all keys.
     */
    public static function allPuritiesAsMap(): array
    {
        $bomPurities = \App\Models\CostingBomItem::whereNotNull('purity')
            ->where('purity', '>', 0)
            ->with('rawMaterial')
            ->orderByDesc('id')
            ->get()
            ->filter(fn($item) => $item->rawMaterial !== null && !empty($item->rawMaterial->item_code))
            ->unique('rawMaterial.item_code')
            ->pluck('purity', 'rawMaterial.item_code')
            ->toArray();

        $pricePurities = static::whereNotNull('purity')
            ->where('purity', '>', 0)
            ->pluck('purity', 'item_code')
            ->toArray();

        $prPurities = \App\Models\PurchaseRegister::whereNotNull('purity')
            ->where('purity', '>', 0)
            ->orderByDesc('vouch_date')
            ->orderByDesc('id')
            ->get()
            ->unique('item_code')
            ->pluck('purity', 'item_code')
            ->toArray();

        return array_replace($bomPurities, $pricePurities, $prPurities);
    }

    /**
     * Resolve the active purity for an item code.
     * Priority:
     * 1. Latest PurchaseRegister non-zero purity
     * 2. ProductPrice cached/manual non-zero purity
     * 3. $bomFallbackPurity (if provided)
     * 4. 100.0%
     */
    public static function resolvePurity(?string $itemCode, ?float $bomFallbackPurity = null): float
    {
        $code = trim($itemCode ?? '');
        if (!empty($code)) {
            // 1. Latest PR Purity
            $prPurity = \App\Models\PurchaseRegister::where('item_code', $code)
                ->whereNotNull('purity')
                ->where('purity', '>', 0)
                ->orderByDesc('vouch_date')
                ->orderByDesc('id')
                ->value('purity');

            if ($prPurity && (float)$prPurity > 0) {
                return (float)$prPurity;
            }

            // 2. ProductPrice cached/manual purity
            $ppPurity = static::where('item_code', $code)
                ->whereNotNull('purity')
                ->where('purity', '>', 0)
                ->value('purity');

            if ($ppPurity && (float)$ppPurity > 0) {
                return (float)$ppPurity;
            }
        }

        // 3. Fallback to BOM Item purity
        if ($bomFallbackPurity !== null && (float)$bomFallbackPurity > 0) {
            return (float)$bomFallbackPurity;
        }

        return 100.0;
    }
}
