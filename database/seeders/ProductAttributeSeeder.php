<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductAttribute;

class ProductAttributeSeeder extends Seeder
{
    public function run()
    {
        $attributes = [
            'category' => 'category',
            'form' => 'form',
            'rm_type' => 'rm_type',
            'pack_name' => 'pack_name',
        ];

        foreach ($attributes as $type => $column) {
            $values = Product::distinct()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->pluck($column);

            foreach ($values as $value) {
                ProductAttribute::firstOrCreate([
                    'type' => $type,
                    'value' => $value,
                ]);
            }
        }
    }
}
