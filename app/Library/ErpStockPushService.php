<?php

namespace App\Library;

use App\Models\AppSetting;
use App\Models\Production;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErpStockPushService
{
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->baseUrl  = rtrim(AppSetting::get('erp_push_base_url', 'http://logic.gloswebdev.in'), '/');
        $this->username = AppSetting::get('erp_push_username', 'SALapi');
        $this->password = AppSetting::get('erp_push_password', 'SAL@api@123');
    }

    /**
     * Push raw-material consumption to ERP as an Issue Register entry.
     *
     * @param  Production  $production
     * @param  array       $issueItems  Each: ['item_code'=>, 'quantity'=>, 'lot_no'=>]
     * @return array  ['success'=>bool, 'response'=>array, 'message'=>string]
     */
    public function pushIssueStock(Production $production, array $issueItems): array
    {
        $payload = [
            'Branch_Code'  => (int) $production->branch_code,
            'Doc_Prefix'   => AppSetting::get('erp_issue_doc_prefix', 'IS'),
            'IssueTo'      => AppSetting::get('erp_issue_issue_to', 'DAMAGE'),
            'GodownName'   => AppSetting::get('erp_issue_godown_name', ''),
            'ReceivedFrom' => '',
            'Remarks'      => 'Production Issue - ' . $production->production_date,
            'ListItems'    => array_map(fn($item) => [
                'EANCode'              => '',
                'ItemCode'             => $item['item_code'],
                'LotNo'                => null,
                'Quantity'             => round((float) $item['quantity'], 4),
                'Rate'                 => 0.0,
                'Mrp'                  => 0.0,
                'Manufacturing_Date'   => null,
                'Expiry_Date'          => null,
                'LotProductionUnit'    => '',
            ], $issueItems),
        ];

        return $this->callApi('SaveIssueStock', $payload);
    }

    /**
     * Push finished-good production to ERP as a Receipt Register entry.
     *
     * @param  Production  $production
     * @param  array       $receiptItems  Each: ['item_code'=>, 'quantity'=>, 'lot_no'=>, 'mfg_date'=>, 'exp_date'=>, 'rate'=>]
     * @return array  ['success'=>bool, 'response'=>array, 'message'=>string]
     */
    public function pushReceiptStock(Production $production, array $receiptItems): array
    {
        $payload = [
            'Branch_Code'  => (int) $production->branch_code,
            'Doc_Prefix'   => AppSetting::get('erp_receipt_doc_prefix', 'REC'),
            'IssueTo'      => AppSetting::get('erp_receipt_issue_to', ''),
            'GodownName'   => AppSetting::get('erp_receipt_godown_name', 'MAIN'),
            'ReceivedFrom' => AppSetting::get('erp_receipt_received_from', ''),
            'Remarks'      => 'Production Receipt - ' . $production->production_date,
            'ListItems'    => array_map(function($item) use ($production) {
                // Auto-correct MFG Date (fallback to production_date or today)
                $mfgDate = $this->sanitizeDate($item['mfg_date'] ?? null) 
                    ?: ($production->production_date ?: date('Y-m-d'));

                // Auto-correct EXP Date (fallback to MFG Date + 2 years)
                $expDate = $this->sanitizeDate($item['exp_date'] ?? null);
                if (empty($expDate) && !empty($mfgDate)) {
                    $expDate = date('Y-m-d', strtotime('+2 years', strtotime($mfgDate)));
                }

                return [
                    'EANCode'              => '',
                    'ItemCode'             => $item['item_code'],
                    'LotNo'                => $item['lot_no'] ?? null,
                    'Quantity'             => round((float) $item['quantity'], 4),
                    'Rate'                 => round((float) ($item['rate'] ?? 0), 4),
                    'Mrp'                  => 0.0,
                    'Manufacturing_Date'   => $mfgDate,
                    'Expiry_Date'          => $expDate,
                    'LotProductionUnit'    => '',
                ];
            }, $receiptItems),
        ];

        return $this->callApi('SaveReceiptStock', $payload);
    }

    /**
     * Ensure dates sent to Logic ERP are valid Y-m-d and have a 4-digit year >= 1753
     * to avoid SQL Server SqlDateTime overflow exceptions.
     */
    public function sanitizeDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }
        $date = trim($date);
        // Correct 2-digit years or typos like 0026-09-25 -> 2026-09-25
        if (preg_match('/^00(\d{2})-(.*)$/', $date, $matches)) {
            $date = '20' . $matches[1] . '-' . $matches[2];
        }

        try {
            $parsed = \Carbon\Carbon::parse($date);
            if ($parsed->year < 100) {
                $parsed->year += 2000;
            } elseif ($parsed->year < 1753) {
                $parsed->year = (int) date('Y');
            }
            return $parsed->format('Y-m-d');
        } catch (\Throwable $e) {
            return $date;
        }
    }

    /**
     * Push a stock adjustment to ERP (Receipt if 'add', Issue if 'deduct').
     *
     * @param  \App\Models\Adjustment  $adjustment
     * @return array  ['success'=>bool, 'response'=>array, 'message'=>string]
     */
    public function pushAdjustment(\App\Models\Adjustment $adjustment): array
    {
        $adjustment->loadMissing('product');
        $product = $adjustment->product;

        if (!$product || empty($product->item_code)) {
            return [
                'success'  => false,
                'response' => [],
                'message'  => 'Product has no valid item code.',
            ];
        }

        $branchCode = (int) ($adjustment->branch_code ?: AppSetting::get('product_master_branchcode', '2'));

        if ($adjustment->adjustment_type === 'add') {
            $payload = [
                'Branch_Code'  => $branchCode,
                'Doc_Prefix'   => AppSetting::get('erp_receipt_doc_prefix', 'REC'),
                'IssueTo'      => AppSetting::get('erp_receipt_issue_to', ''),
                'GodownName'   => AppSetting::get('erp_receipt_godown_name', 'MAIN'),
                'ReceivedFrom' => '',
                'Remarks'      => 'Stock Adjustment (Receipt) #' . $adjustment->id . ($adjustment->reason ? ' - ' . $adjustment->reason : ''),
                'ListItems'    => [
                    [
                        'EANCode'              => '',
                        'ItemCode'             => $product->item_code,
                        'LotNo'                => null,
                        'Quantity'             => round((float) $adjustment->quantity, 4),
                        'Rate'                 => 0.0,
                        'Mrp'                  => 0.0,
                        'Manufacturing_Date'   => null,
                        'Expiry_Date'          => null,
                        'LotProductionUnit'    => '',
                    ]
                ],
            ];
            return $this->callApi('SaveReceiptStock', $payload);
        } else {
            $payload = [
                'Branch_Code'  => $branchCode,
                'Doc_Prefix'   => AppSetting::get('erp_issue_doc_prefix', 'IS'),
                'IssueTo'      => AppSetting::get('erp_issue_issue_to', 'DAMAGE'),
                'GodownName'   => AppSetting::get('erp_issue_godown_name', ''),
                'ReceivedFrom' => '',
                'Remarks'      => 'Stock Adjustment (Issue) #' . $adjustment->id . ($adjustment->reason ? ' - ' . $adjustment->reason : ''),
                'ListItems'    => [
                    [
                        'EANCode'              => '',
                        'ItemCode'             => $product->item_code,
                        'LotNo'                => null,
                        'Quantity'             => round((float) $adjustment->quantity, 4),
                        'Rate'                 => 0.0,
                        'Mrp'                  => 0.0,
                        'Manufacturing_Date'   => null,
                        'Expiry_Date'          => null,
                        'LotProductionUnit'    => '',
                    ]
                ],
            ];
            return $this->callApi('SaveIssueStock', $payload);
        }
    }

    /**
     * Make a POST request to the Logic ERP API with Basic Auth.
     */
    private function callApi(string $endpoint, array $payload): array
    {
        $url = "{$this->baseUrl}/{$endpoint}";

        try {
            Log::info("ERP Push [{$endpoint}] → {$url}", ['payload' => $payload]);

            $response = Http::withoutVerifying()
                ->withBasicAuth($this->username, $this->password)
                ->timeout(30)
                ->connectTimeout(10)
                ->post($url, $payload);

            $body = $response->json() ?? [];

            $success = isset($body['Status']) && $body['Status'] === true;
            $message = $body['Message'] ?? ($response->successful() ? 'OK' : 'HTTP ' . $response->status());

            if (str_contains($message, 'SqlDateTime overflow') || str_contains($message, 'Must be between 1/1/1753') || str_contains($message, 'SqlTypeException')) {
                $message = 'Invalid Date: Please enter a valid date between 2000 and 2099.';
            }

            Log::info("ERP Push [{$endpoint}] ← Status={$response->status()}", [
                'success'      => $success,
                'message'      => $message,
                'LastSavedDoc' => $body['LastSavedDocNo'] ?? null,
            ]);

            return [
                'success'  => $success,
                'response' => $body,
                'message'  => $message,
            ];

        } catch (\Exception $e) {
            Log::error("ERP Push [{$endpoint}] EXCEPTION: " . $e->getMessage());

            return [
                'success'  => false,
                'response' => [],
                'message'  => $e->getMessage(),
            ];
        }
    }
}
