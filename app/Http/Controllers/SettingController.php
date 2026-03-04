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
        
        return view('settings.branches', compact('branches', 'apiStatus', 'apiUrl', 'erpApiStatus', 'erpApiUrl'));
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

    public function deleteBranch(Branch $branch)
    {
        $branch->delete();
        return redirect()->back()->with('success', 'Branch mapping deleted.');
    }
}
