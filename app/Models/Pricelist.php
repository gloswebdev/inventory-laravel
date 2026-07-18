<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pricelist extends Model
{
    protected $fillable = [
        'item_det_code',
        'user_code',
        'item_hd_name',
        'item_short_name',
        'size',
        'size_desc',
        'group1',
        'group2',
        'group3',
        'group4',
        'group5',
        'group6',
        'mrp',
        'sp_rate1',
        'prev_sp_rate1',
        'sp_rate2',
        'prev_sp_rate2',
        'sp_rate3',
        'prev_sp_rate3',
        'sp_rate4',
        'prev_sp_rate4',
        'sp_rate5',
        'prev_sp_rate5',
        'sale_rate',
        'barcode',
        'item_nature',
        'cf_1',
        'cf_2',
        'cf_3',
        'modify_date',
        'gst_tax',
    ];
}
