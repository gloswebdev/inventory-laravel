<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndentItem extends Model
{
    protected $fillable = [
        'indent_id', 'product_id', 'product_name', 
        'demand_qty', 'demand_unit', 'stock_box', 
        'stock_kg', 'final_qty_box', 'completed_qty'
    ];

    public function indent()
    {
        return $this->belongsTo(Indent::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
