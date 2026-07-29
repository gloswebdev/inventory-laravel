<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricelistPushLogItem extends Model
{
    protected $fillable = [
        'pricelist_push_log_id',
        'user_code',
        'item_name',
        'price_list',
        'old_value',
        'new_value',
    ];

    protected $casts = [
        'old_value' => 'float',
        'new_value' => 'float',
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(PricelistPushLog::class, 'pricelist_push_log_id');
    }
}
