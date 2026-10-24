<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DebtorsAgeingExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $rows;

    public function __construct($rows)
    {
        $this->rows = collect($rows);
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Branch Code',
            'Branch Name',
            'Party Code',
            'Party Name',
            'Credit Limit',
            'Opening Balance',
            'Total Debit',
            'Total Credit',
            '0-30 Days',
            '31-60 Days',
            '61-90 Days',
            'Over 90 Days',
            'Closing Balance (Outstanding)'
        ];
    }

    public function map($row): array
    {
        return [
            $row['Branch_Code'] ?? '',
            $row['Branch_Name'] ?? '',
            $row['ACT_CODE'] ?? '',
            $row['act_name'] ?? '',
            isset($row['Credit_Limit']) && $row['Credit_Limit'] !== '' ? (float)$row['Credit_Limit'] : 0,
            (float)($row['OpeningBalance'] ?? 0),
            (float)($row['TotalDebit'] ?? 0),
            (float)($row['TotalCredit'] ?? 0),
            (float)($row['0-30Days'] ?? 0),
            (float)($row['31-60Days'] ?? 0),
            (float)($row['61-90Days'] ?? 0),
            (float)($row['Over90Days'] ?? 0),
            (float)($row['ClosingBalance'] ?? 0),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5']
                ]
            ],
        ];
    }
}
