<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\QueryJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BridgeApiController extends Controller
{
    /**
     * Verify Bridge Secret Token
     */
    private function authenticateToken(Request $request): bool
    {
        $headerToken = $request->header('X-Bridge-Token') 
            ?? $request->header('Authorization') 
            ?? $request->query('token')
            ?? $request->input('token');

        if (str_starts_with((string)$headerToken, 'Bearer ')) {
            $headerToken = substr($headerToken, 7);
        }

        $validToken = AppSetting::get('bridge_secret_token', 'invoflow_bridge_key_2026');

        return !empty($headerToken) && hash_equals(trim($validToken), trim($headerToken));
    }

    /**
     * Poll for pending query jobs (Called by Python Bridge every 2-3 seconds)
     */
    public function poll(Request $request)
    {
        if (!$this->authenticateToken($request)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: Invalid Bridge Secret Token.',
            ], 401);
        }

        // Update heartbeat info
        AppSetting::set('bridge_agent_last_seen', now()->toDateTimeString());
        if ($request->filled('agent_version')) {
            AppSetting::set('bridge_agent_version', $request->input('agent_version'));
        }
        if ($request->filled('db_name')) {
            AppSetting::set('bridge_agent_db_name', $request->input('db_name'));
        }

        // Expire stuck jobs older than 5 minutes
        QueryJob::where('status', 'running')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->update([
                'status'        => 'failed',
                'error_message' => 'Query execution timed out on Bridge Agent.',
            ]);

        // Find oldest pending job
        $job = QueryJob::where('status', 'pending')
            ->orderBy('id', 'asc')
            ->first();

        if ($job) {
            $job->update([
                'status' => 'running',
            ]);

            return response()->json([
                'status'  => 'success',
                'has_job' => true,
                'job'     => [
                    'id'        => $job->id,
                    'job_token' => $job->job_token,
                    'query_sql' => $job->query_sql,
                    'db_type'   => $job->db_type,
                ],
            ]);
        }

        return response()->json([
            'status'      => 'success',
            'has_job'     => false,
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Submit query result from Python Bridge Agent
     */
    public function submit(Request $request)
    {
        if (!$this->authenticateToken($request)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: Invalid Bridge Secret Token.',
            ], 401);
        }

        $request->validate([
            'job_token' => 'required|string',
            'status'    => 'required|string|in:completed,failed',
        ]);

        $job = QueryJob::where('job_token', $request->job_token)->first();
        if (!$job) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Query job not found.',
            ], 404);
        }

        $isSuccess = $request->status === 'completed';
        $rows = $request->input('rows', []);
        $columns = $request->input('columns', []);

        // Encode rows to string
        $encodedRows = is_array($rows) ? json_encode($rows, JSON_UNESCAPED_UNICODE) : (string)$rows;

        $job->update([
            'status'            => $isSuccess ? 'completed' : 'failed',
            'result_columns'    => $columns ?: [],
            'result_rows'       => $encodedRows,
            'row_count'         => is_array($rows) ? count($rows) : (int)$request->input('row_count', 0),
            'execution_seconds' => (float)$request->input('execution_seconds', 0),
            'error_message'     => $request->input('error_message'),
            'completed_at'      => now(),
        ]);

        // Keep last-seen fresh
        AppSetting::set('bridge_agent_last_seen', now()->toDateTimeString());

        return response()->json([
            'status'  => 'success',
            'message' => 'Query job updated successfully.',
            'job_id'  => $job->id,
        ]);
    }

    /**
     * Bridge Agent Heartbeat Ping
     */
    public function heartbeat(Request $request)
    {
        if (!$this->authenticateToken($request)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        AppSetting::set('bridge_agent_last_seen', now()->toDateTimeString());
        if ($request->filled('agent_version')) AppSetting::set('bridge_agent_version', $request->input('agent_version'));
        if ($request->filled('db_name')) AppSetting::set('bridge_agent_db_name', $request->input('db_name'));

        return response()->json([
            'status'      => 'online',
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Direct Automated Push Sync from Python Agent (Cron / Scheduled Auto-Sync)
     */
    public function pushSync(Request $request)
    {
        if (!$this->authenticateToken($request)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $targetTable = $request->input('target_table', 'mssql_sales_records');
        $rows = $request->input('rows', []);
        $truncateOld = $request->boolean('truncate_old', false);
        $chunkIndex = (int)$request->input('chunk_index', 0);

        if (!\Illuminate\Support\Facades\Schema::hasTable($targetTable)) {
            return response()->json(['status' => 'error', 'message' => "Table '{$targetTable}' not found"], 404);
        }

        $tableColumns = \Illuminate\Support\Facades\Schema::getColumnListing($targetTable);
        $insertedCount = 0;
        $batch = [];

        try {
            if ($truncateOld && $chunkIndex === 0) {
                \Illuminate\Support\Facades\DB::table($targetTable)->delete();
            }

            \Illuminate\Support\Facades\DB::beginTransaction();

            foreach ($rows as $row) {
                $record = [];
                $lowerRowKeys = [];
                foreach ($row as $k => $v) {
                    $lowerRowKeys[strtolower(str_replace([' ', '_', '-'], '', $k))] = $v;
                }

                foreach ($tableColumns as $col) {
                    if ($col === 'id') continue;
                    $normCol = strtolower(str_replace([' ', '_', '-'], '', $col));
                    if (array_key_exists($normCol, $lowerRowKeys)) {
                        $val = $lowerRowKeys[$normCol];
                        if (str_contains($col, 'date') && $val) {
                            try { $val = \Carbon\Carbon::parse(trim($val))->format('Y-m-d'); } catch (\Exception $e) { $val = null; }
                        }
                        $record[$col] = $val;
                    }
                }

                if (in_array('created_at', $tableColumns) && !isset($record['created_at'])) $record['created_at'] = now();
                if (in_array('updated_at', $tableColumns) && !isset($record['updated_at'])) $record['updated_at'] = now();

                if (!empty($record)) $batch[] = $record;

                if (count($batch) >= 500) {
                    \Illuminate\Support\Facades\DB::table($targetTable)->insert($batch);
                    $insertedCount += count($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                \Illuminate\Support\Facades\DB::table($targetTable)->insert($batch);
                $insertedCount += count($batch);
            }

            if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
                \Illuminate\Support\Facades\DB::commit();
            }

            AppSetting::set('last_mssql_sales_sync', now()->format('d M Y, h:i A'));
            AppSetting::set('bridge_agent_last_seen', now()->toDateTimeString());

            return response()->json([
                'status'  => 'success',
                'count'   => $insertedCount,
                'message' => "Auto-Sync: {$insertedCount} records inserted into '{$targetTable}'.",
            ]);
        } catch (\Throwable $e) {
            if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
                \Illuminate\Support\Facades\DB::rollBack();
            }
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
