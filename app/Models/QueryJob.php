<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueryJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_token',
        'query_sql',
        'db_type',
        'status',
        'result_columns',
        'result_rows',
        'row_count',
        'execution_seconds',
        'error_message',
        'requested_by',
        'requested_by_name',
        'dispatched_at',
        'completed_at',
    ];

    protected $casts = [
        'result_columns'    => 'array',
        'row_count'         => 'integer',
        'execution_seconds' => 'float',
        'dispatched_at'     => 'datetime',
        'completed_at'      => 'datetime',
    ];

    /**
     * Get decoded rows as array
     */
    public function getRowsAttribute(): array
    {
        if (empty($this->result_rows)) {
            return [];
        }
        $decoded = json_decode($this->result_rows, true);
        return is_array($decoded) ? $decoded : [];
    }
}
