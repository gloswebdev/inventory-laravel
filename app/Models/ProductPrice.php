<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $fillable = [
        'item_code',
        'price_per_unit',
        'price_source',
        'fetched_at',
    ];

    protected $casts = [
        'price_per_unit' => 'float',
        'fetched_at'     => 'datetime',
    ];

    /**
     * Get the price for a given item_code. Returns 0 if not found.
     */
    public static function getPrice(string $itemCode): float
    {
        return (float) static::where('item_code', $itemCode)->value('price_per_unit') ?? 0.0;
    }

    /**
     * Bulk-load prices as an associative array [item_code => price_per_unit]
     */
    public static function allAsMap(): array
    {
        return static::pluck('price_per_unit', 'item_code')->toArray();
    }
}
