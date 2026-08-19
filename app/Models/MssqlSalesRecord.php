<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MssqlSalesRecord extends Model
{
    protected $table = 'mssql_sales_records';

    protected $guarded = ['id'];

    protected $casts = [
        'vouch_date'     => 'date',
        'tot_qty'        => 'float',
        'calc_net_amt_n' => 'float',
        'free_qty'       => 'float',
        'rate'           => 'float',
        'calc_tax_1'     => 'float',
        'calc_tax_2'     => 'float',
        'calc_tax_3'     => 'float',
        'discount_rs'    => 'float',
        'calc_scheme_rs' => 'float',
        'calc_gross_amt' => 'float',
        'calc_net_amt'   => 'float',
        'weight_per_unit'=> 'float',
        'cf_1'           => 'float',
        'pur_rate'       => 'float',
        'basic_rate'     => 'float',
    ];
}
