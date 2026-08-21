<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedQuery extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'query_sql',
        'target_table',
        'column_mapping',
        'is_favorite',
        'created_by',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'is_favorite'    => 'boolean',
    ];
}
