<?php

namespace App\Exports;

use App\Models\CostingBom;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CostingBomsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $search;

    public function __construct($search = null)
    {
        $this->search = $search;
    }

    public function query()
    {
        $query = CostingBom::with(['finishedProduct', 'items.rawMaterial']);

        if ($this->search) {
            $search = $this->search;
            $query->whereHas('finishedProduct', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'FG ITEM CODE',
            'FINISHED PRODUCT',
            'FG PACKING',
            'YIELD QTY',
            'YIELD UOM',
            'RM ITEM CODE',
            'RAW MATERIAL',
            'RM PACKING',
            'RM QTY'
        ];
    }

    /**
    * @var CostingBom $bom
    */
    public function map($bom): array
    {
        $rows = [];
        foreach ($bom->items as $item) {
            $rows[] = [
                $bom->finishedProduct->item_code,
                $bom->finishedProduct->name,
                $bom->finishedProduct->pack_name,
                $bom->yield_quantity,
                $bom->yield_uom,
                $item->rawMaterial->item_code,
                $item->rawMaterial->name,
                $item->rawMaterial->pack_name,
                $item->quantity,
            ];
        }
        return $rows;
    }
}
