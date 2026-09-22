<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;

class MssqlSyncController extends Controller
{
    /**
     * Columns the agent may write. Anything else in a payload is ignored.
     */
    private const FIELDS = [
        'financial_year', 'txn_code', 'branch_name', 'branch_code', 'vouch_date', 'vouch_time',
        'entry_datetime', 'vouch_num', 'vouch_code', 'act_name', 'act_code',
        'agent_code', 'agent_name', 'item_det_code',
        'tot_qty', 'calc_net_amt_n', 'free_qty', 'rate', 'calc_tax_1', 'calc_tax_2', 'calc_tax_3',
        'discount_rs', 'calc_scheme_rs', 'calc_gross_amt', 'calc_net_amt', 'calc_freight',
        'sale_or_sr', 'user_code', 'weight_per_unit', 'cf_1', 'item_hd_code', 'item_hd_name',
        'lot_number', 'lot_code', 'pur_rate', 'basic_rate', 'mobile_no', 'cust_hd_code',
        'customer_name', 'cashier_name', 'group_name', 'pack_name', 'series', 'series_type',
        'is_stock_transfer',
    ];

    private const NUMERIC_FIELDS = [
        'tot_qty', 'calc_net_amt_n', 'free_qty', 'rate', 'calc_tax_1', 'calc_tax_2', 'calc_tax_3',
        'discount_rs', 'calc_scheme_rs', 'calc_gross_amt', 'calc_net_amt', 'calc_freight',
        'weight_per_unit', 'cf_1', 'pur_rate', 'basic_rate',
    ];

    private const INT_FIELDS = [
        'txn_code', 'branch_code', 'vouch_code', 'act_code', 'agent_code', 'item_det_code',
        'item_hd_code', 'lot_code', 'cust_hd_code',
    ];

    /**
     * Ingest a batch of MS SQL sales records from the local sync agent.
     *
     * mode=upsert  -> idempotent write keyed on (financial_year, txn_code). Preferred.
     * mode=replace -> legacy behaviour: wipe a date range / financial year, then insert.
     */
    public function ingestSales(Request $request)
    {
        @set_time_limit(300);

        if (!$this->authorised($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized sync token'], 403);
        }

        $records = $request->input('records', []);
        if (!is_array($records)) {
            return response()->json(['success' => false, 'message' => 'Records must be an array.'], 422);
        }

        $mode = $request->input('mode', 'replace');
        $financialYear = $request->input('financial_year');

        try {
            // The unique key can only go on once every row carries a txn_code, so a first-time
            // upgrade has to drop the legacy unkeyed rows. That is destructive, so the agent
            // must ask for it explicitly (it only does so during a full bootstrap push).
            $this->ensureSchema($request->boolean('allow_reset', false));

            if ($mode === 'upsert') {
                $blocked = !$this->indexExists('mssql_sales_uniq_line')
                    || DB::table('mssql_sales_records')->whereNull('txn_code')->exists();

                if ($blocked) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Legacy rows without txn_code are still present, so an upsert would '
                            . 'double-count. Run the agent once with `bootstrap` (sends allow_reset) to '
                            . 'rebuild this table before using upsert mode.',
                    ], 409);
                }
            }

            if ($mode === 'upsert') {
                $written = $this->upsertRecords($records);
            } else {
                $written = $this->replaceRecords($request, $records, $financialYear);
            }

            AppSetting::set('last_mssql_sales_sync', now()->format('Y-m-d H:i:s'));

            return response()->json([
                'success'      => true,
                'mode'         => $mode,
                'batch_size'   => count($records),
                'written'      => $written,
                'total_synced' => DB::table('mssql_sales_records')->count(),
                'message'      => 'Batch processed successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('MS SQL Ingest Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Watermark + row counts, per financial year, so the agent knows where to resume.
     */
    public function getSyncStatus(Request $request)
    {
        if (!$this->authorised($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized sync token'], 403);
        }

        $this->ensureSchema();

        $years = DB::table('mssql_sales_records')
            ->select('financial_year')
            ->selectRaw('COUNT(*) as rows_count')
            ->selectRaw('MAX(entry_datetime) as max_entry')
            ->selectRaw('MAX(vouch_date) as max_vouch_date')
            ->selectRaw('SUM(CASE WHEN txn_code IS NULL THEN 1 ELSE 0 END) as unkeyed_rows')
            ->groupBy('financial_year')
            ->get()
            ->keyBy(fn ($r) => $r->financial_year ?? 'unknown');

        return response()->json([
            'success'      => true,
            'latest_date'  => DB::table('mssql_sales_records')->max('vouch_date'),
            'total_rows'   => DB::table('mssql_sales_records')->count(),
            'last_sync_at' => AppSetting::get('last_mssql_sales_sync'),
            'years'        => $years,
        ]);
    }

    /**
     * Day-wise fingerprint of a date window, used by the agent's nightly reconcile pass to
     * spot bills that were edited or deleted in the ERP after they were first synced.
     */
    public function checksum(Request $request)
    {
        if (!$this->authorised($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized sync token'], 403);
        }

        $this->ensureSchema();

        $rows = DB::table('mssql_sales_records')
            ->when($request->filled('financial_year'), fn ($q) => $q->where('financial_year', $request->input('financial_year')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('vouch_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('vouch_date', '<=', $request->input('date_to')))
            ->select('vouch_date')
            ->selectRaw('COUNT(*) as rows_count')
            ->selectRaw('ROUND(SUM(calc_net_amt), 2) as net_sum')
            ->groupBy('vouch_date')
            ->orderBy('vouch_date')
            ->get();

        return response()->json(['success' => true, 'days' => $rows]);
    }

    /**
     * Delete rows in a window whose ERP line no longer exists (bill deleted or a line removed).
     * The agent sends every txn_code the ERP still holds for that window; anything else goes.
     */
    public function prune(Request $request)
    {
        if (!$this->authorised($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized sync token'], 403);
        }

        $financialYear = $request->input('financial_year');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $validCodes = $request->input('valid_txn_codes', []);

        if (empty($financialYear) || empty($dateFrom) || empty($dateTo)) {
            return response()->json([
                'success' => false,
                'message' => 'financial_year, date_from and date_to are required.',
            ], 422);
        }

        if (!is_array($validCodes)) {
            return response()->json(['success' => false, 'message' => 'valid_txn_codes must be an array.'], 422);
        }

        try {
            $this->ensureSchema();

            $deleted = DB::table('mssql_sales_records')
                ->where('financial_year', $financialYear)
                ->whereBetween('vouch_date', [$dateFrom, $dateTo])
                ->when(!empty($validCodes), fn ($q) => $q->whereNotIn('txn_code', $validCodes))
                ->delete();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'window'  => "{$dateFrom} .. {$dateTo}",
            ]);
        } catch (\Throwable $e) {
            Log::error('MS SQL Prune Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Agent heartbeat -> lets the web UI show whether the local PC is still reporting in.
     */
    public function agentHealth(Request $request)
    {
        if (!$this->authorised($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized sync token'], 403);
        }

        if ($request->isMethod('post')) {
            AppSetting::set('sync_agent_health', json_encode([
                'reported_at' => now()->format('Y-m-d H:i:s'),
                'agent'       => $request->input('agent'),
                'version'     => $request->input('version'),
                'status'      => $request->input('status'),
                'last_run_at' => $request->input('last_run_at'),
                'next_run_at' => $request->input('next_run_at'),
                'rows_pushed' => $request->input('rows_pushed'),
                'error'       => $request->input('error'),
            ]));
        }

        $health = json_decode(AppSetting::get('sync_agent_health', '{}'), true) ?: [];

        if (!empty($health['reported_at'])) {
            $health['minutes_since_report'] = now()->diffInMinutes(\Carbon\Carbon::parse($health['reported_at']));
        }

        return response()->json(['success' => true, 'health' => $health]);
    }

    // ------------------------------------------------------------------ writers

    private function upsertRecords(array $records): int
    {
        $rows = [];
        $now = now();

        foreach ($records as $r) {
            $row = $this->normalise($r);

            // Without the ERP line id there is nothing to key on, so refuse rather than
            // silently create a duplicate that no later run can ever clean up.
            if ($row['txn_code'] === null || empty($row['financial_year'])) {
                continue;
            }

            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $rows[] = $row;
        }

        if (empty($rows)) {
            return 0;
        }

        $update = array_values(array_diff(array_keys($rows[0]), ['financial_year', 'txn_code', 'created_at']));

        $written = 0;
        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('mssql_sales_records')->upsert($chunk, ['financial_year', 'txn_code'], $update);
            $written += count($chunk);
        }

        return $written;
    }

    private function replaceRecords(Request $request, array $records, ?string $financialYear): int
    {
        $shouldTruncate = $request->boolean('truncate', false);
        $now = now();

        if ($request->boolean('truncate_all', false)) {
            DB::table('mssql_sales_records')->truncate();
        } elseif ($shouldTruncate) {
            if (!empty($financialYear)) {
                DB::table('mssql_sales_records')->where('financial_year', $financialYear)->delete();
            } else {
                DB::table('mssql_sales_records')->truncate();
            }
        } else {
            $delFrom = $request->input('delete_range_from');
            $delTo = $request->input('delete_range_to');
            if (!empty($delFrom) && !empty($delTo)) {
                DB::table('mssql_sales_records')
                    ->when(!empty($financialYear), fn ($q) => $q->where('financial_year', $financialYear))
                    ->whereBetween('vouch_date', [$delFrom, $delTo])
                    ->delete();
            }
        }

        if (empty($records)) {
            return 0;
        }

        $rows = [];
        foreach ($records as $r) {
            $row = $this->normalise($r);
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $rows[] = $row;
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('mssql_sales_records')->insert($chunk);
        }

        return count($rows);
    }

    // ------------------------------------------------------------------ helpers

    private function authorised(Request $request): bool
    {
        $token = $request->input('token') ?: $request->bearerToken();
        $valid = AppSetting::get('mssql_sync_token', 'invoflow_mssql_sync_secret_2026');

        return !empty($token) && hash_equals((string)$valid, (string)$token);
    }

    /**
     * Map one incoming record onto the table's columns, tolerating the mixed casing the
     * older agents send (Calc_Tax_1 vs calc_tax_1 vs CalcTax1).
     */
    private function normalise(array $r): array
    {
        $lookup = [];
        foreach ($r as $k => $v) {
            $lookup[strtolower(str_replace([' ', '_', '-'], '', (string)$k))] = $v;
        }

        $row = [];
        foreach (self::FIELDS as $field) {
            $key = strtolower(str_replace('_', '', $field));
            $val = $lookup[$key] ?? null;

            if (in_array($field, self::NUMERIC_FIELDS, true)) {
                $row[$field] = (float)($val ?? 0);
            } elseif (in_array($field, self::INT_FIELDS, true)) {
                $row[$field] = ($val === null || $val === '') ? null : (int)$val;
            } elseif ($field === 'is_stock_transfer') {
                $row[$field] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
            } elseif ($field === 'vouch_date') {
                $row[$field] = $this->toDate($val);
            } elseif ($field === 'entry_datetime') {
                $row[$field] = $this->toDateTime($val);
            } elseif ($field === 'vouch_time') {
                $row[$field] = $val === null ? null : substr((string)$val, 0, 30);
            } else {
                $row[$field] = ($val === null || $val === '') ? null : (string)$val;
            }
        }

        if (empty($row['financial_year']) && !empty($row['vouch_date'])) {
            $dt = \Carbon\Carbon::parse($row['vouch_date']);
            $start = $dt->month >= 4 ? $dt->year : $dt->year - 1;
            $row['financial_year'] = $start . '-' . ($start + 1);
        }

        return $row;
    }

    private function toDate($val): ?string
    {
        if (empty($val)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string)$val)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function toDateTime($val): ?string
    {
        if (empty($val)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string)$val)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create the table if it is missing and back-fill any column or index this version of
     * the agent expects. Lets the cloud heal itself without an SSH session for `migrate`.
     */
    private function ensureSchema(bool $allowReset = false): void
    {
        if (!Schema::hasTable('mssql_sales_records')) {
            Schema::create('mssql_sales_records', function (Blueprint $table) {
                $table->id();
                $table->string('financial_year', 15)->nullable()->index();
                $table->integer('txn_code')->nullable();
                $table->string('branch_name')->nullable()->index();
                $table->integer('branch_code')->nullable()->index();
                $table->date('vouch_date')->nullable()->index();
                $table->string('vouch_time')->nullable();
                $table->dateTime('entry_datetime')->nullable();
                $table->string('vouch_num')->nullable()->index();
                $table->integer('vouch_code')->nullable()->index();
                $table->string('act_name')->nullable()->index();
                $table->integer('act_code')->nullable()->index();
                $table->integer('item_det_code')->nullable()->index();
                $table->decimal('tot_qty', 15, 4)->default(0);
                $table->decimal('calc_net_amt_n', 15, 4)->default(0);
                $table->decimal('free_qty', 15, 4)->default(0);
                $table->decimal('rate', 15, 4)->default(0);
                $table->decimal('calc_tax_1', 15, 4)->default(0);
                $table->decimal('calc_tax_2', 15, 4)->default(0);
                $table->decimal('calc_tax_3', 15, 4)->default(0);
                $table->decimal('discount_rs', 15, 4)->default(0);
                $table->decimal('calc_scheme_rs', 15, 4)->default(0);
                $table->decimal('calc_gross_amt', 15, 4)->default(0);
                $table->decimal('calc_net_amt', 15, 4)->default(0);
                $table->decimal('calc_freight', 15, 4)->default(0);
                $table->string('sale_or_sr', 10)->nullable();
                $table->string('user_code', 50)->nullable()->index();
                $table->decimal('weight_per_unit', 15, 4)->default(0);
                $table->decimal('cf_1', 15, 4)->default(0);
                $table->integer('item_hd_code')->nullable()->index();
                $table->string('item_hd_name')->nullable()->index();
                $table->string('lot_number')->nullable();
                $table->integer('lot_code')->nullable();
                $table->decimal('pur_rate', 15, 4)->default(0);
                $table->decimal('basic_rate', 15, 4)->default(0);
                $table->string('mobile_no')->nullable();
                $table->integer('cust_hd_code')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('cashier_name')->nullable();
                $table->string('group_name')->nullable()->index();
                $table->string('pack_name')->nullable();
                $table->string('series', 30)->nullable();
                $table->string('series_type', 5)->nullable();
                $table->boolean('is_stock_transfer')->default(false);
                $table->timestamps();
                $table->unique(['financial_year', 'txn_code'], 'mssql_sales_uniq_line');
                $table->index(['financial_year', 'entry_datetime'], 'mssql_sales_entry_idx');
            });

            return;
        }

        $additions = [
            'financial_year'    => fn (Blueprint $t) => $t->string('financial_year', 15)->nullable()->index(),
            'txn_code'          => fn (Blueprint $t) => $t->integer('txn_code')->nullable(),
            'vouch_code'        => fn (Blueprint $t) => $t->integer('vouch_code')->nullable()->index(),
            'entry_datetime'    => fn (Blueprint $t) => $t->dateTime('entry_datetime')->nullable(),
            'series_type'       => fn (Blueprint $t) => $t->string('series_type', 5)->nullable(),
            'is_stock_transfer' => fn (Blueprint $t) => $t->boolean('is_stock_transfer')->default(false),
            'calc_freight'      => fn (Blueprint $t) => $t->decimal('calc_freight', 15, 4)->default(0),
            'calc_tax_2'        => fn (Blueprint $t) => $t->decimal('calc_tax_2', 15, 4)->default(0),
            'calc_tax_3'        => fn (Blueprint $t) => $t->decimal('calc_tax_3', 15, 4)->default(0),
            'calc_gross_amt'    => fn (Blueprint $t) => $t->decimal('calc_gross_amt', 15, 4)->default(0),
            'calc_net_amt'      => fn (Blueprint $t) => $t->decimal('calc_net_amt', 15, 4)->default(0),
            'calc_scheme_rs'    => fn (Blueprint $t) => $t->decimal('calc_scheme_rs', 15, 4)->default(0),
            'agent_code'        => fn (Blueprint $t) => $t->integer('agent_code')->nullable(),
            'agent_name'        => fn (Blueprint $t) => $t->string('agent_name')->nullable()->index(),
        ];

        $missing = array_filter(
            $additions,
            fn ($_, $col) => !Schema::hasColumn('mssql_sales_records', $col),
            ARRAY_FILTER_USE_BOTH
        );

        if ($missing) {
            Schema::table('mssql_sales_records', function (Blueprint $table) use ($missing) {
                foreach ($missing as $definition) {
                    $definition($table);
                }
            });
        }

        // Rows written by the pre-agent syncs have no txn_code. MySQL never treats two NULLs
        // as duplicates, so those rows would survive every upsert and double the totals --
        // having the unique key in place is not enough on its own.
        $unkeyed = DB::table('mssql_sales_records')->whereNull('txn_code')->count();

        if ($unkeyed > 0 && $allowReset) {
            Log::warning("MS SQL sync: dropping {$unkeyed} legacy rows with no txn_code (bootstrap).");
            DB::table('mssql_sales_records')->whereNull('txn_code')->delete();
            $unkeyed = 0;
        }

        if ($unkeyed === 0 && !$this->indexExists('mssql_sales_uniq_line')) {
            Schema::table('mssql_sales_records', function (Blueprint $table) {
                $table->unique(['financial_year', 'txn_code'], 'mssql_sales_uniq_line');
            });
        }

        if (!$this->indexExists('mssql_sales_entry_idx')) {
            Schema::table('mssql_sales_records', function (Blueprint $table) {
                $table->index(['financial_year', 'entry_datetime'], 'mssql_sales_entry_idx');
            });
        }
    }

    private function indexExists(string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'mssql_sales_records')
            ->where('index_name', $name)
            ->exists();
    }
}
