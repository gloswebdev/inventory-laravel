<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MobileStockExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $products;
    protected $externalStock;
    protected $displayUnit;
    protected $selectedBranch;

    public function __construct($products, $externalStock, $displayUnit, $selectedBranch = null)
    {
        $this->products = $products;
        $this->externalStock = $externalStock;
        $this->displayUnit = $displayUnit;
        $this->selectedBranch = $selectedBranch;
    }

    public function collection()
    {
        return $this->products;
    }

    public function headings(): array
    {
        $unitLabel = ($this->displayUnit === 'kg') ? 'kg/Ltr' : 'Boxes';
        return [
            'Product Name',
            'Item Code',
            'Pack Size',
            'UOM',
            'Branch: ' . ($this->selectedBranch ?: 'All Branches'),
            'Stock (' . $unitLabel . ')'
        ];
    }

    public function map($product): array
    {
        $qty = 0;
        if ($this->selectedBranch && isset($this->externalStock[$this->selectedBranch][$product->item_code])) {
            $qty = $this->externalStock[$this->selectedBranch][$product->item_code];
        } elseif (!$this->selectedBranch) {
            foreach ($this->externalStock as $items) {
                $qty += ($items[$product->item_code] ?? 0);
            }
        }

        $unitPerBox = (float)($product->unit_box ?: 1);
        $weightPerUnit = (float)($product->weight_unit ?: 1);
        $displayQty = ($this->displayUnit === 'kg') ? ($qty * $weightPerUnit) : ($qty / $unitPerBox);

        return [
            $product->name,
            $product->item_code,
            $product->pack_name,
            $product->uom,
            $this->selectedBranch ?: 'CONSOLIDATED',
            number_format($displayQty, 2, '.', '')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
