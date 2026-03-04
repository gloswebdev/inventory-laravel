<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 
        'page_key', 
        'can_view', 
        'can_create', 
        'can_edit', 
        'can_delete',
        'can_print',
        'can_export_excel',
        'can_export_pdf',
        'features',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
        'can_print' => 'boolean',
        'can_export_excel' => 'boolean',
        'can_export_pdf' => 'boolean',
        'features' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
