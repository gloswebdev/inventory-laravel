<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricelistPushLog extends Model
{
    protected $fillable = [
        'total_items',
        'total_success',
        'total_failed',
        'price_list',
        'status',
        'error_message',
        'request_payload',
        'response_body',
        'pushed_by',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PricelistPushLogItem::class);
    }
}
