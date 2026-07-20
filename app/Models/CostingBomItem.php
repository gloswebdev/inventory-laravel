<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostingBomItem extends Model
{
    use HasFactory;

    protected $table = 'costing_bom_items';

    protected $fillable = [
        'costing_bom_id',
        'raw_material_id',
        'quantity',
        'purity',
        'transportation_cost',
    ];

    public function costingBom(): BelongsTo
    {
        return $this->belongsTo(CostingBom::class, 'costing_bom_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'raw_material_id');
    }
}
