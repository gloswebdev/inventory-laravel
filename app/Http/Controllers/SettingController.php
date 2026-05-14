<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
        ]);

        $keys = [
            'erp_api_base_url', 'erp_api_key',
            'inventory_api_branch', 'inventory_api_item', 'factory_stock_branch',
            'product_master_itemdetcode', 'product_master_usercode', 'product_master_branchcode',
            'product_master_page_number', 'product_master_rows', 'product_master_txn_type',
        ];

        foreach ($keys as $key) {
            AppSetting::set($key, $request->input($key));
        }

        // Bust the stock cache so new settings take effect immediately
        Cache::forget('external_stock_data_grouped');
        Cache::forget('external_stock_branch2');

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
