<?php

namespace App\Http\Controllers;

use App\Models\ProductGroup;
use Illuminate\Http\Request;

class ProductGroupController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['group_name' => 'required|string|unique:product_groups,group_name']);
        
        ProductGroup::create(['group_name' => $request->group_name]);
        
        return redirect()->back()->with('success', 'Product Group added successfully.');
    }

    public function destroy(ProductGroup $productGroup)
    {
        // Check if group is used
        if ($productGroup->products()->exists()) {
             // Or allow delete and set products to null? Legacy used to warn user.
            // Let's prevent deletion for safety or replicate legacy "ungroup" behavior.
            // Legacy said: "Is group ko delete karne se iske sabhi products ungroup ho jayenge."
             $productGroup->products()->update(['group_id' => null]);
        }

        $productGroup->delete();
        
        return redirect()->back()->with('success', 'Product Group deleted successfully.');
    }
}
