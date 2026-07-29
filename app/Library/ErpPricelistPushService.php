<?php

namespace App\Library;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pushes selling rates to the Algebra ERP Logic_Item_Master_Update_Multiple endpoint.
 *
 * Auth is an apikey inside the POST body (same scheme as the ProductMaster pull),
 * NOT the Basic Auth used by ErpStockPushService against LogicERP.
 */
class ErpPricelistPushService
{
    /** Items per API call - a single 5000-item XML payload times out on shared hosting. */
    private const CHUNK_SIZE = 200;

    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(AppSetting::get('erp_api_base_url', 'https://logicapi.algebraerp.com/API/SYNWOOD'), '/');
        $this->apiKey  = AppSetting::get('erp_api_key', 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14');
    }

    /**
     * Push rates to the ERP in chunks.
     *
     * @param  array  $items  [['user_code' => 'SWA001', 'new_value' => 300.0], ...]
     * @return array{success: bool, pushed: int, failed: int, message: string, payload: string, response: string}
     */
    public function push(array $items, string $priceList): array
    {
        $chunks   = array_chunk(array_values($items), self::CHUNK_SIZE);
        $pushed   = 0;
        $failed   = 0;
        $payloads = [];
        $bodies   = [];
        $messages = [];

        foreach ($chunks as $chunk) {
            $result = $this->callApi($chunk, $priceList);

            $payloads[] = $result['payload'];
            $bodies[]   = $result['raw'];
            $messages[] = $result['message'];

            if ($result['success']) {
                $pushed += count($chunk);
            } else {
                $failed += count($chunk);
            }
        }

        return [
            'success'  => $failed === 0,
            'pushed'   => $pushed,
            'failed'   => $failed,
            'message'  => implode(' | ', array_unique($messages)),
            'payload'  => implode("\n", $payloads),
            'response' => implode("\n", $bodies),
        ];
    }

    /**
     * Build the PriceData XML string that the ERP expects inside the JSON body.
     */
    public function buildPriceDataXml(array $items, string $priceList): string
    {
        $xml = '<Items>';
        foreach ($items as $item) {
            $userCode = htmlspecialchars((string) $item['user_code'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $value    = round((float) $item['new_value'], 4);
            $xml .= '<Item>'
                . '<User_Code>' . $userCode . '</User_Code>'
                . '<PriceList>' . htmlspecialchars($priceList, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</PriceList>'
                . '<PriceValue>' . $value . '</PriceValue>'
                . '</Item>';
        }

        return $xml . '</Items>';
    }

    /**
     * @return array{success: bool, message: string, payload: string, raw: string}
     */
    private function callApi(array $items, string $priceList): array
    {
        $xml = $this->buildPriceDataXml($items, $priceList);
        $url = "{$this->baseUrl}/Logic_Item_Master_Update_Multiple";

        try {
            Log::info("ERP Pricelist Push → {$url}", ['count' => count($items), 'price_list' => $priceList, 'payload' => $xml]);

            $response = Http::withoutVerifying()
                ->timeout(90)
                ->post($url, [
                    'apikey'    => $this->apiKey,
                    'PriceData' => $xml,
                ]);

            $raw  = $response->body();
            $body = $response->json() ?? [];

            // The endpoint's envelope is not documented - accept the pull-side
            // {response: "success"} and the push-side {Status: true}, else fall
            // back to the HTTP status.
            $success = ($body['response'] ?? null) === 'success'
                || ($body['Status'] ?? null) === true
                || (empty($body) && $response->successful());

            $message = $body['Message']
                ?? $body['message']
                ?? ($response->successful() ? 'OK' : 'HTTP ' . $response->status());

            Log::info("ERP Pricelist Push ← Status={$response->status()}", ['success' => $success, 'message' => $message, 'body' => $raw]);

            return ['success' => $success, 'message' => $message, 'payload' => $xml, 'raw' => $raw];

        } catch (\Exception $e) {
            Log::error('ERP Pricelist Push EXCEPTION: ' . $e->getMessage());

            return ['success' => false, 'message' => $e->getMessage(), 'payload' => $xml, 'raw' => ''];
        }
    }
}
