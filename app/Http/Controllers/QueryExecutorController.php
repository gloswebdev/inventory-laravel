<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\QueryJob;
use App\Models\SavedQuery;
use App\Models\SalesRegister;
use App\Models\MssqlSalesRecord;
use App\Models\Product;
use App\Models\PurchaseRegister;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class QueryExecutorController extends Controller
{
    /**
     * Ensure only admin can access
     */
    private function adminOnly()
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Admin access required for Query Executor.');
        }
    }

    /**
     * Main Query Executor Dashboard View
     */
    public function index(Request $request)
    {
        $this->adminOnly();

        // Auto-seed standard presets if empty
        if (SavedQuery::count() === 0) {
            SavedQuery::create([
                'title'        => '⚡ Current Year Sales (FY 2026-2027) [Exact ERP Match]',
                'description'  => 'Pure sales & returns (excludes stock transfers) matching Logic ERP exact figures',
                'target_table' => 'mssql_sales_records',
                'is_favorite'  => true,
                'query_sql'    => "SELECT \n" .
                    "    BM.branch_name,\n" .
                    "    HD.vouch_date,\n" .
                    "    HD.Vouch_Time,\n" .
                    "    HD.vouch_num,\n" .
                    "    BS.series,\n" .
                    "    ACT.act_name,\n" .
                    "    TXN.item_det_code,\n" .
                    "    CASE WHEN TXN.sale_or_sr = 'SR' OR BS.type = 'SR' THEN TXN.Tot_Qty * -1 ELSE TXN.Tot_Qty END AS tot_qty,\n" .
                    "    CASE WHEN TXN.sale_or_sr = 'SR' OR BS.type = 'SR' THEN TXN.Calc_Net_Amt * -1 ELSE TXN.Calc_Net_Amt END AS calc_net_amt_n,\n" .
                    "    TXN.Free_Qty,\n" .
                    "    TXN.rate,\n" .
                    "    TXN.Calc_Tax_1,\n" .
                    "    TXN.calc_commission AS discount_rs,\n" .
                    "    IMD.User_Code,\n" .
                    "    IMH.item_hd_name,\n" .
                    "    GM1.group_name,\n" .
                    "    BM.branch_code\n" .
                    "FROM Sl_Txn20262027 AS TXN\n" .
                    "INNER JOIN Sl_Head20262027 AS HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0\n" .
                    "INNER JOIN Bill_Ser AS BS ON HD.Series_Code = BS.Series_Code\n" .
                    "LEFT JOIN It_Mst_Det AS IMD ON TXN.Item_Det_Code = IMD.Item_Det_Code\n" .
                    "LEFT JOIN It_Mst_Hd AS IMH ON IMD.Item_Hd_Code = IMH.Item_Hd_Code\n" .
                    "LEFT JOIN Group_Mst AS GM1 ON IMH.Group_Code = GM1.Group_Code\n" .
                    "LEFT JOIN Accounts AS ACT ON HD.cust_code = ACT.act_code\n" .
                    "LEFT JOIN Branch_Mst AS BM ON HD.Branch_Code = BM.Branch_Code\n" .
                    "WHERE BS.Stock_Trans = 0\n" .
                    "  AND BS.type IN ('SL', 'SR')\n" .
                    "  AND BS.series IN ('AMSR', 'AKSL', 'AKCS', 'AKLF', 'PNSL', 'PNCS', 'PNF', 'SPSR', 'MPSL', 'MPCS', 'SMSR', 'UPSL', 'UPCS', 'SWSR', 'MHSL', 'LKS', 'LKR', 'SWAK', 'SWPN', 'SWMP', 'SWUP')\n" .
                    "ORDER BY HD.vouch_date DESC;"
            ]);

            SavedQuery::create([
                'title'        => '📅 Previous Year Sales (FY 2025-2026)',
                'description'  => 'FY 2025-2026 sales & returns history',
                'target_table' => 'mssql_sales_records',
                'is_favorite'  => false,
                'query_sql'    => "SELECT \n" .
                    "    BM.branch_name,\n" .
                    "    HD.vouch_date,\n" .
                    "    HD.Vouch_Time,\n" .
                    "    HD.vouch_num,\n" .
                    "    BS.series,\n" .
                    "    ACT.act_name,\n" .
                    "    TXN.item_det_code,\n" .
                    "    CASE WHEN TXN.sale_or_sr = 'SR' OR BS.type = 'SR' THEN TXN.Tot_Qty * -1 ELSE TXN.Tot_Qty END AS tot_qty,\n" .
                    "    CASE WHEN TXN.sale_or_sr = 'SR' OR BS.type = 'SR' THEN TXN.Calc_Net_Amt * -1 ELSE TXN.Calc_Net_Amt END AS calc_net_amt_n,\n" .
                    "    TXN.Free_Qty,\n" .
                    "    TXN.rate,\n" .
                    "    TXN.Calc_Tax_1,\n" .
                    "    TXN.calc_commission AS discount_rs,\n" .
                    "    IMD.User_Code,\n" .
                    "    IMH.item_hd_name,\n" .
                    "    GM1.group_name,\n" .
                    "    BM.branch_code\n" .
                    "FROM Sl_Txn20252026 AS TXN\n" .
                    "INNER JOIN Sl_Head20252026 AS HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0\n" .
                    "INNER JOIN Bill_Ser AS BS ON HD.Series_Code = BS.Series_Code\n" .
                    "LEFT JOIN It_Mst_Det AS IMD ON TXN.Item_Det_Code = IMD.Item_Det_Code\n" .
                    "LEFT JOIN It_Mst_Hd AS IMH ON IMD.Item_Hd_Code = IMH.Item_Hd_Code\n" .
                    "LEFT JOIN Group_Mst AS GM1 ON IMH.Group_Code = GM1.Group_Code\n" .
                    "LEFT JOIN Accounts AS ACT ON HD.cust_code = ACT.act_code\n" .
                    "LEFT JOIN Branch_Mst AS BM ON HD.Branch_Code = BM.Branch_Code\n" .
                    "WHERE BS.Stock_Trans = 0\n" .
                    "  AND BS.type IN ('SL', 'SR')\n" .
                    "ORDER BY HD.vouch_date DESC;"
            ]);
        }

        $savedQueries = SavedQuery::orderByDesc('is_favorite')
            ->orderBy('title')
            ->get();

        $recentJobs = QueryJob::orderByDesc('id')
            ->limit(10)
            ->get();

        // Bridge agent heartbeat
        $lastSeenStr = AppSetting::get('bridge_agent_last_seen');
        $agentVersion = AppSetting::get('bridge_agent_version', '1.0');
        $agentDbName = AppSetting::get('bridge_agent_db_name', 'MSSQL Database');
        $bridgeToken = AppSetting::get('bridge_secret_token', 'invoflow_bridge_key_2026');

        $isAgentOnline = false;
        $lastSeenDiff = 'Never connected';
        if ($lastSeenStr) {
            $lastSeenTime = Carbon::parse($lastSeenStr);
            $secondsAgo = $lastSeenTime->diffInSeconds(now());
            $isAgentOnline = $secondsAgo <= 30; // Online if pinged within last 30 seconds
            $lastSeenDiff = $lastSeenTime->diffForHumans();
        }

        // Active job if requested
        $activeJob = null;
        if ($request->filled('job_token')) {
            $activeJob = QueryJob::where('job_token', $request->job_token)->first();
        } elseif ($request->filled('job_id')) {
            $activeJob = QueryJob::find($request->job_id);
        }

        // Available target tables for smart import
        $targetTables = [
            'mssql_sales_records' => [
                'label'   => 'MSSQL Sales Records (mssql_sales_records)',
                'columns' => Schema::getColumnListing('mssql_sales_records'),
            ],
            'sales_registers' => [
                'label'   => 'Sales Register (sales_registers)',
                'columns' => Schema::getColumnListing('sales_registers'),
            ],
            'products' => [
                'label'   => 'Products Master (products)',
                'columns' => Schema::getColumnListing('products'),
            ],
            'purchase_registers' => [
                'label'   => 'Purchase Register (purchase_registers)',
                'columns' => Schema::getColumnListing('purchase_registers'),
            ],
        ];

        return view('reports.query_executor', compact(
            'savedQueries',
            'recentJobs',
            'isAgentOnline',
            'lastSeenStr',
            'lastSeenDiff',
            'agentVersion',
            'agentDbName',
            'bridgeToken',
            'activeJob',
            'targetTables'
        ));
    }

    /**
     * Dispatch a new SQL Query Job to Bridge Queue
     */
    public function dispatchJob(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'query_sql' => 'required|string|min:4',
            'db_type'   => 'nullable|string|in:mssql,mysql',
        ]);

        $querySql = html_entity_decode(trim($request->query_sql), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Security check: Only allow SELECT, EXEC, WITH queries for safety
        $trimmedUpper = strtoupper(ltrim($querySql));
        $isSafe = str_starts_with($trimmedUpper, 'SELECT') 
               || str_starts_with($trimmedUpper, 'EXEC') 
               || str_starts_with($trimmedUpper, 'WITH')
               || str_starts_with($trimmedUpper, 'SET');

        if (!$isSafe) {
            return response()->json([
                'success' => false,
                'message' => 'Safety Warning: Only SELECT, WITH, or EXEC queries are permitted in Query Executor.',
            ], 422);
        }

        $jobToken = Str::random(32);

        $job = QueryJob::create([
            'job_token'         => $jobToken,
            'query_sql'         => $querySql,
            'db_type'           => $request->input('db_type', 'mssql'),
            'status'            => 'pending',
            'requested_by'      => Auth::id(),
            'requested_by_name' => Auth::user()->name ?? Auth::user()->username,
            'dispatched_at'     => now(),
        ]);

        return response()->json([
            'success'   => true,
            'job_id'    => $job->id,
            'job_token' => $jobToken,
            'message'   => 'Query dispatched to Local MSSQL Bridge queue.',
        ]);
    }

    /**
     * Poll status of a dispatched query job
     */
    public function checkStatus($token)
    {
        $this->adminOnly();

        $job = QueryJob::where('job_token', $token)->first();
        if (!$job) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }

        if ($job->status === 'completed') {
            return response()->json([
                'success'           => true,
                'status'            => 'completed',
                'job_id'            => $job->id,
                'job_token'         => $job->job_token,
                'columns'           => $job->result_columns ?: [],
                'rows'              => $job->rows,
                'row_count'         => $job->row_count,
                'execution_seconds' => $job->execution_seconds,
                'completed_at'      => $job->completed_at ? $job->completed_at->format('d M Y, h:i:s A') : '',
            ]);
        }

        if ($job->status === 'failed') {
            return response()->json([
                'success'       => false,
                'status'        => 'failed',
                'job_id'        => $job->id,
                'error_message' => $job->error_message ?: 'Unknown error during query execution on local MSSQL.',
            ]);
        }

        return response()->json([
            'success' => true,
            'status'  => $job->status, // pending or running
            'job_id'  => $job->id,
        ]);
    }

    /**
     * Import Selected Rows into InvoFlow Target Table
     */
    public function importSelected(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'target_table' => 'required|string',
            'rows'         => 'required|array|min:1',
            'mapping'      => 'nullable|array',
            'truncate_old' => 'nullable|boolean',
        ]);

        $targetTable = $request->target_table;
        $rows = $request->rows;
        $mapping = $request->mapping ?: [];
        $truncateOld = (bool)$request->truncate_old;

        if (!Schema::hasTable($targetTable)) {
            return response()->json([
                'success' => false,
                'message' => "Target table '{$targetTable}' does not exist in database.",
            ], 422);
        }

        $tableColumns = Schema::getColumnListing($targetTable);
        $insertedCount = 0;
        $batch = [];

        try {
            if ($truncateOld) {
                DB::table($targetTable)->delete();
            }

            DB::beginTransaction();

            foreach ($rows as $row) {
                $record = [];

                // 1. If explicit mapping provided
                if (!empty($mapping)) {
                    foreach ($mapping as $targetCol => $sourceCol) {
                        if (empty($targetCol) || empty($sourceCol)) continue;
                        if (in_array($targetCol, $tableColumns) && array_key_exists($sourceCol, $row)) {
                            $record[$targetCol] = $this->formatColumnValue($targetCol, $row[$sourceCol]);
                        }
                    }
                } else {
                    // 2. Auto-match matching column names (case-insensitive)
                    $lowerRowKeys = [];
                    foreach ($row as $k => $v) {
                        $lowerRowKeys[strtolower(str_replace([' ', '_', '-'], '', $k))] = $v;
                    }

                    foreach ($tableColumns as $col) {
                        if ($col === 'id') continue;
                        $normCol = strtolower(str_replace([' ', '_', '-'], '', $col));
                        if (array_key_exists($normCol, $lowerRowKeys)) {
                            $record[$col] = $this->formatColumnValue($col, $lowerRowKeys[$normCol]);
                        } elseif (array_key_exists($col, $row)) {
                            $record[$col] = $this->formatColumnValue($col, $row[$col]);
                        }
                    }
                }

                // If target table has raw_data column, store full original row
                if (in_array('raw_data', $tableColumns) && !isset($record['raw_data'])) {
                    $record['raw_data'] = json_encode($row, JSON_UNESCAPED_UNICODE);
                }

                if (in_array('created_at', $tableColumns) && !isset($record['created_at'])) {
                    $record['created_at'] = now();
                }
                if (in_array('updated_at', $tableColumns) && !isset($record['updated_at'])) {
                    $record['updated_at'] = now();
                }

                if (!empty($record)) {
                    $batch[] = $record;
                }

                if (count($batch) >= 500) {
                    DB::table($targetTable)->insert($batch);
                    $insertedCount += count($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table($targetTable)->insert($batch);
                $insertedCount += count($batch);
            }

            if (DB::transactionLevel() > 0) {
                DB::commit();
            }

            // Update last sync time setting
            if (in_array($targetTable, ['mssql_sales_records', 'sales_registers'])) {
                AppSetting::set('last_mssql_sales_sync', now()->format('d M Y, h:i A'));
            }

            return response()->json([
                'success' => true,
                'count'   => $insertedCount,
                'message' => "Successfully imported {$insertedCount} rows into '{$targetTable}'!",
            ]);

        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format values for database types (dates, decimals, strings)
     */
    private function formatColumnValue(string $column, $val)
    {
        if ($val === null || $val === '') return null;

        if (str_contains($column, 'date')) {
            try {
                $cleaned = trim((string)$val);
                if (str_contains($cleaned, 'T')) $cleaned = explode('T', $cleaned)[0];
                if (str_contains($cleaned, ' ')) $cleaned = explode(' ', $cleaned)[0];
                if (str_contains($cleaned, '/')) {
                    $parts = explode('/', $cleaned);
                    if (count($parts) === 3) {
                        if (strlen($parts[0]) === 4) {
                            return "{$parts[0]}-" . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . "-" . str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                        } else {
                            return "{$parts[2]}-" . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . "-" . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                        }
                    }
                } elseif (str_contains($cleaned, '-')) {
                    $parts = explode('-', $cleaned);
                    if (count($parts) === 3 && strlen($parts[0]) !== 4) {
                        return "{$parts[2]}-" . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . "-" . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                    } else {
                        return $cleaned;
                    }
                }
                return Carbon::parse($cleaned)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (is_array($val) || is_object($val)) {
            return json_encode($val, JSON_UNESCAPED_UNICODE);
        }

        return $val;
    }

    /**
     * Save a Query Template Preset
     */
    public function saveTemplate(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'title'        => 'required|string|max:150',
            'query_sql'    => 'required|string',
            'target_table' => 'nullable|string',
            'description'  => 'nullable|string|max:255',
        ]);

        $saved = SavedQuery::create([
            'title'        => trim($request->title),
            'description'  => trim($request->description ?? ''),
            'query_sql'    => trim($request->query_sql),
            'target_table' => $request->target_table,
            'is_favorite'  => $request->has('is_favorite'),
            'created_by'   => Auth::id(),
        ]);

        return back()->with('system_success', "✅ Saved query '{$saved->title}' created successfully!");
    }

    /**
     * Delete a Saved Query
     */
    public function deleteTemplate($id)
    {
        $this->adminOnly();

        $saved = SavedQuery::findOrFail($id);
        $title = $saved->title;
        $saved->delete();

        return back()->with('system_success', "Deleted saved query '{$title}'.");
    }

    /**
     * Update Bridge Secret Token
     */
    public function updateBridgeSettings(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'bridge_secret_token' => 'required|string|min:8',
        ]);

        AppSetting::set('bridge_secret_token', trim($request->bridge_secret_token));

        return back()->with('system_success', '✅ Bridge Secret Token updated successfully!');
    }

    /**
     * Export Job Results as CSV
     */
    public function exportCsv($token)
    {
        $this->adminOnly();

        $job = QueryJob::where('job_token', $token)->firstOrFail();
        $rows = $job->rows;
        $columns = $job->result_columns ?: (count($rows) > 0 ? array_keys($rows[0]) : []);

        $filename = 'query_result_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($columns, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $col) {
                    $val = $row[$col] ?? '';
                    if (is_array($val) || is_object($val)) {
                        $val = json_encode($val);
                    }
                    $line[] = $val;
                }
                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
