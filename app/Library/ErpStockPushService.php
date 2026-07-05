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
        $this->baseUrl  = rtrim(AppSetting::get('erp_push_base_url', 'http://demo.logicerp.com/api'), '/');
        $this->username = AppSetting::get('erp_push_username', '');
        $this->password = AppSetting::get('erp_push_password', '');
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
            'ListItems'    => array_map(fn($item) => [
                'EANCode'              => '',
                'ItemCode'             => $item['item_code'],
                'LotNo'                => $item['lot_no'] ?? null,
                'Quantity'             => round((float) $item['quantity'], 4),
                'Rate'                 => round((float) ($item['rate'] ?? 0), 4),
                'Mrp'                  => 0.0,
                'Manufacturing_Date'   => $item['mfg_date'] ?? null,
                'Expiry_Date'          => $item['exp_date'] ?? null,
                'LotProductionUnit'    => '',
            ], $receiptItems),
        ];

        return $this->callApi('SaveReceiptStock', $payload);
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
