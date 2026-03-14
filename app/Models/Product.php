<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'alias_name',
        'uom', // Mapped from WEIGHT(IN)
        'price',
        'group_id',
        'product_type_id',
        'low_alert_quantity',
        'current_stock',
        // New Excel Columns
        'item_code',
        'category',
        'form',
        'technical_name',
        'rm_type',
        'pack_name',
        'unit_box',
        'weight_unit',
        'weight_in',
    ];

    protected $appends = ['weight_multiplier'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class, 'finished_product_id');
    }

    /**
     * Get the multiplier to convert unit quantity to KG/LTR
     */
    public function getWeightMultiplierAttribute()
    {
        $value = (float)($this->weight_unit ?: 1);
        $unit = strtoupper($this->weight_in ?: $this->uom ?: '');

        // If unit is grams or ml, divide by 1000 to get KG/LTR
        if (str_contains($unit, 'GM') || str_contains($unit, 'ML') || str_contains($unit, 'GRAM')) {
            return $value / 1000;
        }

        // Check if value itself looks like it's in grams/ml but labeled as KG/LTR
        // (e.g. 1000 LTR instead of 1000 ML)
        if ($value >= 100 && (str_contains($unit, 'KG') || str_contains($unit, 'LTR') || str_contains($unit, 'LIT'))) {
            // Heuristic: If it's 100+, it's likely grams/ml mislabeled
            return $value / 1000;
        }

        return $value;
    }
}
