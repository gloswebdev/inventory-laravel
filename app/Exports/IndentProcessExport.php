<?php

namespace App\Exports;

use App\Models\Indent;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class IndentProcessExport implements FromCollection, WithHeadings, WithMapping, WithCustomStartCell, WithEvents, ShouldAutoSize
{
    protected $indent;
    protected $branches;
    protected $branchStocks;

    public function __construct(Indent $indent, $branches, $branchStocks)
    {
        $this->indent = $indent;
        $this->branches = $branches;
        $this->branchStocks = $branchStocks;
    }

    public function collection()
    {
        return $this->indent->items;
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function headings(): array
    {
        $headers = [
            'Product Name',
            'Pack',
            'Indent Qty (Box)',
            'Stock at Entry (Box)'
        ];

        foreach ($this->branches as $branch) {
            $headers[] = "{$branch->name} ({$branch->code})";
        }

        return $headers;
    }

    public function map($item): array
    {
        $row = [
            $item->product_name,
            $item->product->pack_name ?? 'N/A',
            $item->final_qty_box,
            $item->stock_box
        ];

        foreach ($this->branches as $branch) {
            $row[] = number_format($this->branchStocks[$item->product_id][$branch->code] ?? 0, 2);
        }

        return $row;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function(BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + count($this->branches));
                
                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->setCellValue('A1', 'INDENT PROCESS - CROSS BRANCH STOCK MATRIX');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

                $sheet->setCellValue('A2', 'TARGET BRANCH:');
                $sheet->setCellValue('B2', "{$this->indent->branch_name} ({$this->indent->branch_code})");
                
                $sheet->setCellValue('A3', 'CREATED BY:');
                $sheet->setCellValue('B3', $this->indent->user->name ?? 'System');

                $sheet->setCellValue('A4', 'INDENT DATE:');
                $sheet->setCellValue('B4', date('d-M-Y', strtotime($this->indent->indent_date)));

                $headerRange = "A2:A4";
                $sheet->getStyle($headerRange)->getFont()->setBold(true);

                // Add colors to data table
                $sheet->getStyle("A6:{$lastColumn}6")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE0E0E0');
            },
        ];
    }
}
