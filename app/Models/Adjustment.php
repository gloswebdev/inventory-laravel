<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'adjustment_type',
        'quantity',
        'reason',
        // 'date' is created_at
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
