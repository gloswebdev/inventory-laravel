<?php

namespace App\Http\Controllers;

use App\Models\ProductAttribute;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductAttributeController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:category,form,rm_type,pack_name',
            'value' => 'required|string|max:255',
        ]);

        // Check for existence to prevent duplicates
        $exists = ProductAttribute::where('type', $request->type)
            ->where('value', $request->value)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'This attribute already exists.');
        }

        ProductAttribute::create([
            'type' => $request->type,
            'value' => $request->value,
        ]);

        return redirect()->back()->with('success', 'Attribute added successfully.');
    }

    public function destroy(ProductAttribute $productAttribute)
    {
        // When deleting an attribute, set the corresponding field in products table to NULL
        $type = $productAttribute->type;
        $value = $productAttribute->value;

        // Update products using this attribute
        Product::where($type, $value)->update([$type => null]);

        $productAttribute->delete();

        return redirect()->back()->with('success', 'Attribute deleted successfully.');
    }
}
