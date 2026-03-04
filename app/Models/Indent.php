<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Indent extends Model
{
    protected $fillable = ['branch_code', 'branch_name', 'indent_date', 'total_boxes', 'user_id', 'status'];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function items()
    {
        return $this->hasMany(IndentItem::class);
    }
}
