<?php

namespace App\Http\Controllers;

use App\Models\ProductType;
use Illuminate\Http\Request;

class ProductTypeController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['type_name' => 'required|string|unique:product_types,type_name']);
        
        ProductType::create(['type_name' => $request->type_name]);
        
        return redirect()->back()->with('success', 'Product Type added successfully.');
    }

    public function destroy(ProductType $productType)
    {
        if ($productType->products()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete type because it is associated with products.');
        }

        $productType->delete();
        
        return redirect()->back()->with('success', 'Product Type deleted successfully.');
    }
}
