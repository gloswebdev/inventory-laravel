<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;

class RecipeTemplateExport implements WithHeadings
{
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
}
