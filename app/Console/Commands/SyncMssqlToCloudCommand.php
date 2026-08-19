<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncMssqlToCloudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mssql:sync-to-cloud 
                            {--year=all : Financial Year suffix (e.g. 20262027, 20252026, or "all")}
                            {--url= : Cloud App URL (default: https://invoflow2.sagarkhandar.in)} 
                            {--token= : Sync Token (default from settings)} 
                            {--truncate : Truncate and perform fresh sync}
                            {--batch=1000 : Number of rows per HTTP request}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch sales records for 2026-2027 and 2025-2026 from local MS SQL Server (via Tailscale) and push to Cloud MySQL database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        @set_time_limit(900);
        @ini_set('memory_limit', '1024M');

        $cloudUrl = rtrim($this->option('url') ?: AppSetting::get('cloud_app_url', env('CLOUD_APP_URL', 'https://invoflow2.sagarkhandar.in')), '/');
        $token = $this->option('token') ?: AppSetting::get('mssql_sync_token', 'invoflow_mssql_sync_secret_2026');
        $batchSize = (int)$this->option('batch') ?: 1000;
        $shouldTruncate = $this->option('truncate') || true; // fresh sync
        $yearParam = $this->option('year') ?: 'all';

        $yearsToSync = [];
        if ($yearParam === 'all') {
            $yearsToSync = ['20262027', '20252026'];
        } else {
            $yearsToSync = explode(',', $yearParam);
        }

        $this->info("=================================================");
        $this->info("🚀 MS SQL TO CLOUD SALES SYNC AGENT");
        $this->info("• Source: MS SQL @ " . config('database.connections.sqlsrv.host', '100.108.74.58'));
        $this->info("• Target Cloud: {$cloudUrl}");
        $this->info("• Financial Years: " . implode(', ', $yearsToSync));
        $this->info("• Batch Size: {$batchSize}");
        $this->info("=================================================\n");

        $totalPushedAllYears = 0;
        $isFirstBatchOverall = true;

        foreach ($yearsToSync as $yearSuffix) {
            $yearSuffix = trim($yearSuffix);
            $formattedYear = strlen($yearSuffix) === 8 
                ? substr($yearSuffix, 0, 4) . '-' . substr($yearSuffix, 4, 4) 
                : $yearSuffix;

            $this->info("-------------------------------------------------");
            $this->info("🔄 Processing Financial Year: {$formattedYear} (Tables: Sl_Txn{$yearSuffix})");
            $this->info("-------------------------------------------------");

            // 1. Check MS SQL Table
            try {
                $countRes = DB::connection('sqlsrv')->select("SELECT COUNT(*) as total FROM sl_txn{$yearSuffix} TXN INNER JOIN sl_head{$yearSuffix} HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0");
                $rowCount = $countRes[0]->total ?? 0;
                $this->info("✅ Found {$rowCount} records in MS SQL for FY {$formattedYear}");
            } catch (\Exception $e) {
                $this->warn("⚠️ Could not find or query table for FY {$yearSuffix}: " . $e->getMessage());
                continue;
            }

            if ($rowCount === 0) {
                $this->warn("No records found for FY {$formattedYear}.");
                continue;
            }

            // 2. Fetch MS SQL Data
            $query = "SELECT 
                BM.branch_name,     
                HD.vouch_date,     
                HD.Vouch_Time as vouch_time,     
                HD.vouch_num,     
                ACT.act_name,     
                TXN.item_det_code,     
                CASE WHEN TXN.sale_or_sr = 'SR' THEN TXN.Tot_Qty * -1 ELSE TXN.Tot_Qty END AS tot_qty, 	 
                CASE WHEN TXN.sale_or_sr = 'SR' THEN txn.Calc_Net_Amt * -1 ELSE TXN.Calc_Net_Amt END AS calc_net_amt_n,     
                TXN.Free_Qty as free_qty,     
                TXN.rate,     
                TXN.Calc_Tax_1 as calc_tax_1,     
                TXN.Calc_Tax_2 as calc_tax_2,     
                TXN.Calc_Tax_3 as calc_tax_3,     
                TXN.calc_commission AS discount_rs,     
                TXN.Calc_Scheme_Rs as calc_scheme_rs,     
                TXN.Calc_Gross_Amt as calc_gross_amt,     
                TXN.Calc_Net_Amt as calc_net_amt,     
                TXN.sale_or_sr,     
                IMD.User_Code as user_code,   
                IMD.Weight_Per_Unit as weight_per_unit,  
                IMD.Item_Det_Code as item_det_code_orig,     
                IMD.cf_1,     
                IMD.Item_Hd_Code as item_hd_code,     
                IMH.item_hd_name,     
                LM.lot_number,     
                LM.lot_code,     
                LM.pur_rate,     
                LM.basic_rate,     
                CMH.Mobile_no as mobile_no,     
                CMD.cust_hd_code,     
                CMD.First_name AS customer_name,     
                ACT.act_code,     
                BM.branch_code,     
                CM.Cashier_name as cashier_name,     
                GM1.group_name,     
                PM.Pack_Name as pack_name,     
                BS.series 
            FROM      
                sl_txn{$yearSuffix} AS TXN 
            INNER JOIN      
                sl_head{$yearSuffix} AS HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0 
            LEFT JOIN      
                It_Mst_Det AS IMD ON TXN.Item_Det_Code = IMD.Item_Det_Code 
            LEFT JOIN      
                It_Mst_Hd AS IMH ON IMD.Item_Hd_Code = IMH.Item_Hd_Code 
            LEFT JOIN      
                Pack_Mst AS PM ON IMD.Pack_Code = PM.Pack_Code   
            LEFT JOIN      
                Lot_Mst AS LM ON TXN.Lot_Code = LM.Lot_Code  
            LEFT JOIN      
                Group_Mst AS GM1 ON IMH.Group_Code = GM1.Group_Code   
            LEFT JOIN      
                Cust_Mst_Hd AS CMH ON HD.Member_Code = CMH.Cust_Hd_Code 
            LEFT JOIN      
                Cust_Mst_Det AS CMD ON CMH.Cust_Hd_Code = CMD.Cust_Hd_Code  
            LEFT JOIN      
                Accounts AS ACT ON HD.cust_code = ACT.act_code  
            LEFT JOIN      
                Branch_Mst AS BM ON HD.Branch_Code = BM.Branch_Code  
            LEFT JOIN      
                SL_Cashier_Mst AS CM ON HD.Cashier_Code = CM.Code 
            LEFT JOIN      
                Bill_Ser AS BS ON HD.series_code = BS.series_code 
            LEFT JOIN     
                Agents_Brokers AS AG ON HD.agent_code=AG.Code 
            LEFT JOIN 	
                AccountGroups AS ACG ON ACT.Grp_Code1=ACG.grp_code";

            $startTime = microtime(true);
            $this->comment("Fetching rows from MS SQL for FY {$formattedYear}...");
            $rows = DB::connection('sqlsrv')->select($query);
            $fetchTime = round(microtime(true) - $startTime, 2);
            $this->info("Fetched " . count($rows) . " rows in {$fetchTime}s.");

            // 3. Push to Cloud API in Chunks
            $chunks = array_chunk($rows, $batchSize);
            $progressBar = $this->output->createProgressBar(count($rows));
            $progressBar->start();

            $endpoint = "{$cloudUrl}/api/sync/mssql-sales";

            foreach ($chunks as $index => $chunk) {
                $truncateOnThisBatch = ($isFirstBatchOverall && $index === 0);
                $payload = [
                    'token'          => $token,
                    'truncate'       => $truncateOnThisBatch,
                    'financial_year' => $formattedYear,
                    'records'        => array_map(function($item) use ($formattedYear) {
                        $arr = (array)$item;
                        $arr['financial_year'] = $formattedYear;
                        if (isset($arr['vouch_date']) && $arr['vouch_date'] instanceof \DateTimeInterface) {
                            $arr['vouch_date'] = $arr['vouch_date']->format('Y-m-d');
                        }
                        return $arr;
                    }, $chunk),
                ];

                $success = false;
                $attempts = 0;
                while (!$success && $attempts < 3) {
                    $attempts++;
                    try {
                        $response = Http::withoutVerifying()
                            ->timeout(60)
                            ->post($endpoint, $payload);

                        if ($response->successful()) {
                            $success = true;
                        } else {
                            Log::warning("Batch {$index} failed (HTTP {$response->status()}), attempt {$attempts}: " . $response->body());
                            sleep(1);
                        }
                    } catch (\Exception $e) {
                        Log::warning("Batch {$index} exception, attempt {$attempts}: " . $e->getMessage());
                        sleep(1);
                    }
                }

                if (!$success) {
                    $errBody = isset($response) ? substr($response->body(), 0, 300) : 'No response';
                    $statusCode = isset($response) ? $response->status() : 'Error';
                    $this->error("\n❌ Failed to push batch " . ($index + 1) . " (HTTP {$statusCode}). Response: {$errBody}");
                    return 1;
                }

                $totalPushedAllYears += count($chunk);
                $progressBar->advance(count($chunk));
            }

            $progressBar->finish();
            $this->line("\n");
            $isFirstBatchOverall = false;
        }

        $this->info("=================================================");
        $this->info("🎉 ALL FINANCIAL YEARS SYNCED SUCCESSFULLY!");
        $this->info("• Total Records Pushed: {$totalPushedAllYears}");
        $this->info("• Financial Years     : " . implode(', ', $yearsToSync));
        $this->info("• Target Website      : {$cloudUrl}");
        $this->info("=================================================\n");

        return 0;
    }
}
