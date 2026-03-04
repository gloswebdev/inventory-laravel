<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LiveStockExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $products;
    protected $branches;
    protected $externalStock;
    protected $displayUnit;

    public function __construct($products, $branches, $externalStock, $displayUnit)
    {
        $this->products = $products;
        $this->branches = $branches;
        $this->externalStock = $externalStock;
        $this->displayUnit = $displayUnit;
    }

    public function collection()
    {
        return $this->products;
    }

    public function headings(): array
    {
        $unitLabel = ($this->displayUnit === 'kg') ? 'kg/Ltr' : 'Unit';
        $headings = [
            'Product Name',
            'Item Code',
            'Type',
            'RM Type',
            'Pack',
            'UOM'
        ];

        foreach ($this->branches as $branch) {
            $headings[] = $branch->name . " ($unitLabel)";
            $headings[] = $branch->name . " (Boxes)";
        }

        $headings[] = "Total ($unitLabel)";
        $headings[] = "Total Boxes";

        return $headings;
    }

    public function map($product): array
    {
        $row = [
            $product->name,
            $product->item_code,
            $product->type->type_name ?? 'N/A',
            $product->rm_type,
            $product->pack_name,
            $product->uom,
        ];

        $totalQty = 0;
        $unitPerBox = (float)($product->unit_box ?: 1);
        $weightPerUnit = (float)($product->weight_unit ?: 1);

        foreach ($this->branches as $branch) {
            $qty = $this->externalStock[$branch->code][$product->item_code] ?? 0;
            $displayQty = ($this->displayUnit === 'kg') ? ($qty * $weightPerUnit) : $qty;
            
            $row[] = number_format($displayQty, 2, '.', '');
            $row[] = number_format($qty / $unitPerBox, 2, '.', '');
            
            $totalQty += $qty;
        }

        $totalDisplayQty = ($this->displayUnit === 'kg') ? ($totalQty * $weightPerUnit) : $totalQty;
        $row[] = number_format($totalDisplayQty, 2, '.', '');
        $row[] = number_format($totalQty / $unitPerBox, 2, '.', '');

        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
