<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostingBomPackingMaterial extends Model
{
    use HasFactory;

    protected $table = 'costing_bom_packing_materials';

    protected $fillable = [
        'costing_bom_id',
        'pricelist_id',
        'raw_material_id',
        'quantity',
        'is_container',
    ];

    protected $casts = [
        'is_container' => 'boolean',
    ];

    public function costingBom(): BelongsTo
    {
        return $this->belongsTo(CostingBom::class, 'costing_bom_id');
    }

    public function pricelist(): BelongsTo
    {
        return $this->belongsTo(Pricelist::class, 'pricelist_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'raw_material_id');
    }
}
