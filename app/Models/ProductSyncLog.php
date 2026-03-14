<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSyncLog extends Model
{
    protected $fillable = [
        'total_created',
        'total_updated',
        'total_skipped',
        'created_items',
        'updated_items',
        'status',
        'error_message',
        'synced_by',
    ];

    protected $casts = [
        'created_items' => 'array',
        'updated_items' => 'array',
    ];
}
