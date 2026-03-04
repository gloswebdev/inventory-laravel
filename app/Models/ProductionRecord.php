<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'finished_product_id',
        'quantity_produced',
        // 'production_date' is created_at
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'finished_product_id');
    }
}
