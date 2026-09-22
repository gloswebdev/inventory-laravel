<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SettingController extends Controller
{
    public function index()
    {
        $branches = Branch::orderBy('code')->get();

        // Load all API settings from DB
        $settings = AppSetting::all()->keyBy('key');

        return view('settings.branches', compact('branches', 'settings'));
    }

    /**
     * Test ERP Push connection with provided or saved credentials.
     */
    public function testErpPushConnection(Request $request)
    {
        $baseUrl  = rtrim($request->input('erp_push_base_url') ?: AppSetting::get('erp_push_base_url', 'http://logic.gloswebdev.in'), '/');
        $username = $request->input('erp_push_username') ?: AppSetting::get('erp_push_username', 'SALapi');
        $password = $request->input('erp_push_password') ?: AppSetting::get('erp_push_password', 'SAL@api@123');

        $url = "{$baseUrl}/SaveIssueStock";

        // Minimal test payload to verify authorization and endpoint reachability
        $payload = [
            'Branch_Code'  => 2,
            'Doc_Prefix'   => 'IS',
            'IssueTo'      => 'DAMAGE',
            'GodownName'   => 'MAIN',
            'ReceivedFrom' => '',
            'Remarks'      => 'Connection Test from Inventory Suite',
            'ListItems'    => [
                [
                    'EANCode'            => '',
                    'ItemCode'           => '8900001',
                    'LotNo'              => null,
                    'Quantity'           => 0.0,
                    'Rate'               => 0.0,
                    'Mrp'                => 0.0,
                    'Manufacturing_Date' => null,
                    'Expiry_Date'        => null,
                    'LotProductionUnit'  => '',
                ]
            ],
        ];

        try {
            $response = Http::withoutVerifying()
                ->withBasicAuth($username, $password)
                ->timeout(12)
                ->connectTimeout(6)
                ->post($url, $payload);

            $status = $response->status();
            $body = $response->json();

            if ($status === 401 || $status === 403) {
                return response()->json([
                    'success' => false,
                    'message' => "Authentication Failed (HTTP {$status})! Please check your Username and Password.",
                    'details' => $body ?? $response->body(),
                ]);
            }

            if ($status === 200 || (is_array($body) && isset($body['Status']))) {
                $docNo = $body['LastSavedDocNo'] ?? null;
                $msg   = $body['Message'] ?? 'Connected successfully';
                return response()->json([
                    'success' => true,
                    'message' => "Logic ERP API Connected Successfully! [Response: {$msg}]",
                    'details' => $body,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Server responded with HTTP {$status}: " . ($body['Message'] ?? $response->body()),
                'details' => $body,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Connection error: " . $e->getMessage(),
            ]);
        }
    }

    /**
     * Save API settings from the settings form.
     */
    public function updateApiSettings(Request $request)
    {
        $request->validate([
            'erp_api_base_url'          => 'required|url',
            'erp_api_key'               => 'required|string',
            'inventory_api_branch'      => 'required|string',
            'inventory_api_item'        => 'required|string',
            'factory_stock_branch'      => 'required|string',
            'product_master_itemdetcode'=> 'required|string',
            'product_master_usercode'   => 'required|string',
            'product_master_branchcode' => 'required|string',
            'product_master_page_number'=> 'required|string',
            'product_master_rows'       => 'required|string',
            'product_master_txn_type'   => 'required|string',
            // Costing API (optional — blank = auto FY)
            'costing_api_from_date'     => 'nullable|string',
            'costing_api_to_date'       => 'nullable|string',
            'costing_api_account'       => 'nullable|string',
            'costing_api_item'          => 'nullable|string',
            'costing_api_branch'        => 'nullable|string',
            // Party Master API
            'partymaster_api_branch'    => 'nullable|string',
            'partymaster_api_actcode'   => 'nullable|string',
            'partymaster_api_agentcode' => 'nullable|string',
            'partymaster_api_txntype'   => 'nullable|string',
            // ERP Push (optional)
            'erp_push_enabled'          => 'nullable|string',
            'erp_push_base_url'         => 'nullable|string',
            'erp_push_username'         => 'nullable|string',
            'erp_push_password'         => 'nullable|string',
            'erp_receipt_doc_prefix'    => 'nullable|string',
            'erp_receipt_godown_name'   => 'nullable|string',
            'erp_receipt_received_from' => 'nullable|string',
            'erp_receipt_issue_to'      => 'nullable|string',
            'erp_issue_doc_prefix'      => 'nullable|string',
            'erp_issue_godown_name'     => 'nullable|string',
            'erp_issue_issue_to'        => 'nullable|string',
            // Sales Report API & Sync Config
            'sales_api_actcode'         => 'nullable|string',
            'sales_api_agentcode'       => 'nullable|string',
            'sales_api_item'            => 'nullable|string',
            'sales_api_usercode'        => 'nullable|string',
            'sales_api_branch'          => 'nullable|string',
            'sales_sync_frequency'      => 'nullable|string',
            'sales_sync_time'           => 'nullable|string',
            'sales_sync_day'            => 'nullable|string',
            // Collection API Settings
            'collection_api_fin_year'   => 'nullable|string',
            'collection_api_party_code' => 'nullable|string',
        ]);

        $keys = [
            'erp_api_base_url', 'erp_api_key',
            'inventory_api_branch', 'inventory_api_item', 'factory_stock_branch',
            'product_master_itemdetcode', 'product_master_usercode', 'product_master_branchcode',
            'product_master_page_number', 'product_master_rows', 'product_master_txn_type',
            // Costing API settings
            'costing_api_from_date', 'costing_api_to_date',
            'costing_api_account', 'costing_api_item', 'costing_api_branch',
            // Party Master API settings
            'partymaster_api_branch', 'partymaster_api_actcode', 'partymaster_api_agentcode', 'partymaster_api_txntype',
            // ERP Push settings
            'erp_push_base_url', 'erp_push_username', 'erp_push_password',
            'erp_receipt_doc_prefix', 'erp_receipt_godown_name', 'erp_receipt_received_from', 'erp_receipt_issue_to',
            'erp_issue_doc_prefix', 'erp_issue_godown_name', 'erp_issue_issue_to',
            // Sales Report settings
            'sales_api_actcode', 'sales_api_agentcode', 'sales_api_item', 'sales_api_usercode', 'sales_api_branch',
            'sales_sync_frequency', 'sales_sync_time', 'sales_sync_day',
            // Collection API settings
            'collection_api_fin_year', 'collection_api_party_code',
        ];

        foreach ($keys as $key) {
            AppSetting::set($key, (string) ($request->input($key) ?? ''));
        }

        // erp_push_enabled is a checkbox — only present in request when checked
        AppSetting::set('erp_push_enabled', $request->has('erp_push_enabled') ? '1' : '0');
        
        // sales_sync_auto is a checkbox — default disabled if not present
        AppSetting::set('sales_sync_auto', $request->has('sales_sync_auto') ? 'enabled' : 'disabled');

        // Bust the stock cache so new settings take effect immediately
        Cache::forget('external_stock_data_grouped');
        Cache::forget('external_stock_branch2');
        Cache::forget('party_master_map'); // Also clear party master cache on save

        return redirect()->back()->with('success', 'API settings saved successfully! Stock cache cleared.');
    }

    public function updateBranches(Request $request)
    {
        $request->validate([
            'branches.*.code' => 'required|string',
            'branches.*.name' => 'required|string',
        ]);

        foreach ($request->branches as $branchData) {
            Branch::updateOrCreate(
                ['code' => $branchData['code']],
                ['name' => $branchData['name']]
            );
        }

        return redirect()->back()->with('success', 'Branch settings updated successfully!');
    }

    public function storeBranch(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:branches,code',
            'name' => 'required|string',
        ]);

        Branch::create($request->all());

        return redirect()->back()->with('success', 'Branch added successfully!');
    }

    public function reorder(Request $request)
    {
        if (auth()->user()->role !== 'admin' &&
            !auth()->user()->hasFeature('reports', 'branch_reorder') &&
            !auth()->user()->hasFeature('indent', 'branch_reorder') &&
            !auth()->user()->hasFeature('mobile_indents', 'branch_reorder')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'required|integer',
        ]);

        foreach ($request->order as $index => $branchId) {
            Branch::where('id', $branchId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    public function deleteBranch(Branch $branch)
    {
        $branch->delete();
        return redirect()->back()->with('success', 'Branch mapping deleted.');
    }
}
