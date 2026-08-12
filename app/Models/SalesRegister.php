<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesRegister extends Model
{
    protected $fillable = [
        'vouch_date',
        'act_code',
        'act_name',
        'agent_code',
        'agent_name',
        'item_code',
        'item_name',
        'qty',
        'amount',
        'branch',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'qty' => 'float',
        'amount' => 'float',
        'vouch_date' => 'date',
    ];
}
