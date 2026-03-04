<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Product::with(['group', 'type'])->orderBy('name');

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%")
                  ->orWhere('technical_name', 'like', "%{$search}%");
            });
        }

        if (!empty($this->filters['group_id'])) {
            $query->where('group_id', $this->filters['group_id']);
        }

        if (!empty($this->filters['product_type_id'])) {
            $query->where('product_type_id', $this->filters['product_type_id']);
        }

        if (!empty($this->filters['rm_type'])) {
            $query->where('rm_type', $this->filters['rm_type']);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'SNO',
            'ITEM CODE',
            'CATEGORY',
            'FORM',
            'TECHNICAL NAME',
            'RM TYPE',
            'TYPE',
            'GROUP',
            'ITEM NAME',
            'UOM',
            'PACK NAME',
            'UNIT/BOX',
            'WEIGHT/UNIT',
            'WEIGHT(IN)',
            'PRICE',
            'LOW ALERT QTY',
            'CURRENT STOCK'
        ];
    }

    /**
    * @var Product $product
    */
    public function map($product): array
    {
        static $sno = 0;
        $sno++;

        return [
            $sno,
            $product->item_code,
            $product->category,
            $product->form,
            $product->technical_name,
            $product->rm_type,
            $product->type?->type_name,
            $product->group?->group_name,
            $product->name,
            $product->uom,
            $product->pack_name,
            $product->unit_box,
            $product->weight_unit,
            $product->weight_in,
            $product->price,
            $product->low_alert_quantity,
            $product->current_stock,
        ];
    }
}
