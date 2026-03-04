<?php

namespace App\Exports;

use App\Models\Recipe;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RecipesExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $search;

    public function __construct($search = null)
    {
        $this->search = $search;
    }

    public function query()
    {
        $query = Recipe::with(['finishedProduct', 'items.rawMaterial']);

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
    * @var Recipe $recipe
    */
    public function map($recipe): array
    {
        $rows = [];
        foreach ($recipe->items as $item) {
            $rows[] = [
                $recipe->finishedProduct->item_code,
                $recipe->finishedProduct->name,
                $recipe->finishedProduct->pack_name,
                $recipe->yield_quantity,
                $recipe->yield_uom,
                $item->rawMaterial->item_code,
                $item->rawMaterial->name,
                $item->rawMaterial->pack_name,
                $item->quantity,
            ];
        }
        return $rows;
    }
}
