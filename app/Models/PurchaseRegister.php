<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRegister extends Model
{
    protected $fillable = [
        'item_code',
        'item_name',
        'supplier_name',
        'vouch_no',
        'vouch_date',
        'qty',
        'case_rate',
        'purity',
        'group_name4',
        'group_name5',
    ];
}
