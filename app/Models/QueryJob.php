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
     * Get decoded rows as array (Warning: Use getPreviewRows for large datasets to avoid memory limit)
     */
    public function getRowsAttribute(): array
    {
        if (empty($this->result_rows)) {
            return [];
        }
        $decoded = json_decode($this->result_rows, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Safely extract first N preview rows without loading entire multi-megabyte JSON into memory
     */
    public function getPreviewRows(int $limit = 50): array
    {
        return self::extractPreviewRows($this->result_rows, $limit);
    }

    public static function extractPreviewRows(?string $rawJson, int $limit = 50): array
    {
        if (empty($rawJson)) return [];
        
        if (strlen($rawJson) < 200000) {
            $decoded = json_decode($rawJson, true);
            return is_array($decoded) ? array_slice($decoded, 0, $limit) : [];
        }

        $preview = [];
        $raw = trim($rawJson);
        if (str_starts_with($raw, '[')) {
            $raw = substr($raw, 1);
        }
        
        $depth = 0;
        $inString = false;
        $escape = false;
        $start = -1;
        $len = strlen($raw);
        
        for ($i = 0; $i < $len && count($preview) < $limit; $i++) {
            $char = $raw[$i];
            
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($char === '\\') {
                $escape = true;
                continue;
            }
            if ($char === '"') {
                $inString = !$inString;
                continue;
            }
            
            if (!$inString) {
                if ($char === '{') {
                    if ($depth === 0) $start = $i;
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;
                    if ($depth === 0 && $start !== -1) {
                        $objStr = substr($raw, $start, $i - $start + 1);
                        $obj = json_decode($objStr, true);
                        if ($obj) $preview[] = $obj;
                        $start = -1;
                    }
                }
            }
        }
        
        return $preview;
    }
}
