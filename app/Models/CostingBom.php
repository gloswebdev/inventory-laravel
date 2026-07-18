<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostingBom extends Model
{
    use HasFactory;

    protected $table = 'costing_boms';

    protected $fillable = [
        'finished_product_id',
        'yield_quantity',
        'yield_uom',
        'badge',
        'formulation',
        'density',
    ];

    protected $casts = [
        'yield_quantity' => 'float',
        'formulation' => 'float',
        'density' => 'float',
    ];

    public function finishedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'finished_product_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CostingBomItem::class, 'costing_bom_id');
    }
}
