<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = ['name', 'agents', 'branches'];

    protected $casts = [
        'agents'   => 'array',
        'branches' => 'array',
    ];
}
