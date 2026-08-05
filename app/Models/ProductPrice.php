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
     */
    public static function getPrice(string $itemCode): float
    {
        return (float) static::where('item_code', $itemCode)->value('price_per_unit') ?? 0.0;
    }

    public static function allAsMap(): array
    {
        $localPrices = static::where('price_per_unit', '>', 0)->pluck('price_per_unit', 'item_code')->toArray();
        $prPrices = \App\Models\PurchaseRegister::orderByDesc('vouch_date')
            ->orderByDesc('id')
            ->get()
            ->unique('item_code')
            ->pluck('case_rate', 'item_code')
            ->toArray();
        return array_merge($localPrices, $prPrices);
    }

    /**
     * Bulk-load purities as an associative array [item_code => purity]
     */
    public static function allPuritiesAsMap(): array
    {
        return static::pluck('purity', 'item_code')->toArray();
    }
}
