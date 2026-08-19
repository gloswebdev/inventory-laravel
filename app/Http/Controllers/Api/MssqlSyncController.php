<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\MssqlSalesRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;

class MssqlSyncController extends Controller
{
    /**
     * Ingest batch of MS SQL sales records from local sync agent
     */
    public function ingestSales(Request $request)
    {
        @set_time_limit(300);

        $token = $request->input('token') ?: $request->bearerToken();
        $validToken = AppSetting::get('mssql_sync_token', 'invoflow_mssql_sync_secret_2026');

        if (empty($token) || $token !== $validToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized sync token: ' . ($token ?: 'None'),
            ], 403);
        }

        $records = $request->input('records', []);
        $shouldTruncate = $request->boolean('truncate', false);

        if (!is_array($records)) {
            return response()->json(['success' => false, 'message' => 'Records must be an array.'], 422);
        }

        try {
            $this->ensureTableExists();

            $financialYearParam = $request->input('financial_year');
            $truncateAll = $request->boolean('truncate_all', false);

            if ($truncateAll) {
                DB::table('mssql_sales_records')->truncate();
            } elseif ($shouldTruncate) {
                if (!empty($financialYearParam)) {
                    DB::table('mssql_sales_records')->where('financial_year', $financialYearParam)->delete();
                } else {
                    DB::table('mssql_sales_records')->truncate();
                }
            }

            if (!empty($records)) {
                $now = now();
                $insertData = [];

                foreach ($records as $r) {
                    $vouchDate = null;
                    if (!empty($r['vouch_date'])) {
                        try {
                            $vouchDate = \Carbon\Carbon::parse($r['vouch_date'])->format('Y-m-d');
                        } catch (\Exception $e) {
                            $vouchDate = null;
                        }
                    }

                    $financialYear = $r['financial_year'] ?? null;
                    if (!$financialYear && $vouchDate) {
                        $dt = \Carbon\Carbon::parse($vouchDate);
                        $startYear = $dt->month >= 4 ? $dt->year : $dt->year - 1;
                        $financialYear = $startYear . '-' . ($startYear + 1);
                    }

                    $insertData[] = [
                        'financial_year'  => $financialYear,
                        'branch_name'     => $r['branch_name'] ?? null,
                        'branch_code'     => isset($r['branch_code']) ? (int)$r['branch_code'] : null,
                        'vouch_date'      => $vouchDate,
                        'vouch_time'      => isset($r['vouch_time']) ? substr((string)$r['vouch_time'], 0, 30) : null,
                        'vouch_num'       => $r['vouch_num'] ?? null,
                        'act_name'        => $r['act_name'] ?? null,
                        'act_code'        => isset($r['act_code']) ? (int)$r['act_code'] : null,
                        'item_det_code'   => isset($r['item_det_code']) ? (int)$r['item_det_code'] : null,
                        'tot_qty'         => (float)($r['tot_qty'] ?? $r['Tot_qty'] ?? 0),
                        'calc_net_amt_n'  => (float)($r['calc_net_amt_n'] ?? 0),
                        'free_qty'        => (float)($r['free_qty'] ?? $r['Free_Qty'] ?? 0),
                        'rate'            => (float)($r['rate'] ?? 0),
                        'calc_tax_1'      => (float)($r['calc_tax_1'] ?? $r['Calc_Tax_1'] ?? 0),
                        'calc_tax_2'      => (float)($r['calc_tax_2'] ?? $r['Calc_Tax_2'] ?? 0),
                        'calc_tax_3'      => (float)($r['calc_tax_3'] ?? $r['Calc_Tax_3'] ?? 0),
                        'discount_rs'     => (float)($r['discount_rs'] ?? $r['Discount_Rs'] ?? 0),
                        'calc_scheme_rs'  => (float)($r['calc_scheme_rs'] ?? $r['Calc_Scheme_Rs'] ?? 0),
                        'calc_gross_amt'  => (float)($r['calc_gross_amt'] ?? $r['Calc_Gross_Amt'] ?? 0),
                        'calc_net_amt'    => (float)($r['calc_net_amt'] ?? $r['Calc_Net_Amt'] ?? 0),
                        'sale_or_sr'      => $r['sale_or_sr'] ?? null,
                        'user_code'       => $r['user_code'] ?? $r['User_Code'] ?? null,
                        'weight_per_unit' => (float)($r['weight_per_unit'] ?? $r['Weight_Per_Unit'] ?? 0),
                        'cf_1'            => (float)($r['cf_1'] ?? 0),
                        'item_hd_code'    => isset($r['item_hd_code']) ? (int)$r['item_hd_code'] : null,
                        'item_hd_name'    => $r['item_hd_name'] ?? null,
                        'lot_number'      => $r['lot_number'] ?? null,
                        'lot_code'        => isset($r['lot_code']) ? (int)$r['lot_code'] : null,
                        'pur_rate'        => (float)($r['pur_rate'] ?? 0),
                        'basic_rate'      => (float)($r['basic_rate'] ?? 0),
                        'mobile_no'       => isset($r['mobile_no']) ? (string)$r['mobile_no'] : null,
                        'cust_hd_code'    => isset($r['cust_hd_code']) ? (int)$r['cust_hd_code'] : null,
                        'customer_name'   => $r['customer_name'] ?? $r['CustomerName'] ?? null,
                        'cashier_name'    => $r['cashier_name'] ?? $r['Cashier_name'] ?? null,
                        'group_name'      => $r['group_name'] ?? null,
                        'pack_name'       => $r['pack_name'] ?? $r['Pack_Name'] ?? null,
                        'series'          => $r['series'] ?? null,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }

                foreach (array_chunk($insertData, 250) as $chunk) {
                    DB::table('mssql_sales_records')->insert($chunk);
                }
            }

            $totalCount = DB::table('mssql_sales_records')->count();
            AppSetting::set('last_mssql_sales_sync', now()->format('Y-m-d H:i:s'));
            AppSetting::set('total_mssql_sales_records', (string)$totalCount);

            return response()->json([
                'success'      => true,
                'batch_size'   => count($records),
                'total_synced' => $totalCount,
                'message'      => 'Batch processed successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('MS SQL Ingest Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Auto create table if it does not exist on target database
     */
    private function ensureTableExists(): void
    {
        if (!Schema::hasTable('mssql_sales_records')) {
            Schema::create('mssql_sales_records', function (Blueprint $table) {
                $table->id();
                $table->string('financial_year', 15)->nullable()->index();
                $table->string('branch_name')->nullable()->index();
                $table->integer('branch_code')->nullable()->index();
                $table->date('vouch_date')->nullable()->index();
                $table->string('vouch_time')->nullable();
                $table->string('vouch_num')->nullable()->index();
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
                $table->timestamps();
            });
        } elseif (!Schema::hasColumn('mssql_sales_records', 'financial_year')) {
            Schema::table('mssql_sales_records', function (Blueprint $table) {
                $table->string('financial_year', 15)->nullable()->index()->after('id');
            });
        }
    }
}
