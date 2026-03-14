<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $branches = Branch::orderBy('code')->get();
        $apiStatus = 'Active'; 
        $apiUrl = config('app.url') . '/api';
        
        // Live Stock API (Algebra ERP)
        $erpApiStatus = 'Active';
        $erpApiUrl = 'https://logicapi.algebraerp.com/API/SYNWOOD/ProductWiseInventory';

        // Product Master API (Algebra ERP)
        $productMasterApiStatus = 'Active';
        $productMasterApiUrl = 'https://logicapi.algebraerp.com/API/SYNWOOD/ProductMaster';
        
        return view('settings.branches', compact(
            'branches', 
            'apiStatus', 
            'apiUrl', 
            'erpApiStatus', 
            'erpApiUrl',
            'productMasterApiStatus',
            'productMasterApiUrl'
        ));
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
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer', // IDs of branches in order
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
