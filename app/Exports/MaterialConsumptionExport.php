<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class MaterialConsumptionExport implements
    FromArray
    //, WithHeadings
    ,
    WithStyles
    ,
    WithDrawings
{
    protected $reports;

    public function __construct(array $reports)
    {
        $this->reports = $reports;
    }

    public function array(): array
    {

        $rows = [
            [''],
            ['MATERIAL CONSUMPTION SUMMARY'],
            [Carbon::now()->format('d/m/Y, h:i A')],
            [],
            [
                'Material Name',
                'Material ID',
                'Store',
                'Category',
                'Brand',
                'Quantity Issued(OUT)',
                'Quantity Received(IN)',
                'Date',
            ],
        ];

        // Actual data
        foreach ($this->reports as $report) {
            $rows[] = [
                $report['materialName'] ?? '',
                $report['materialId'] ?? '',
                $report['storeName'] ?? '',
                $report['category'] ?? '',
                $report['brand'] ?? '',
                $report['totalDecreased'] ?? 0,
                $report['totalIncreased'] ?? 0,
                $report['date'] ?? '',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();

        for ($i = 0; $i < 3; $i++) {
            $index = $i + 1;
            $sheet->mergeCells("A$index:$lastColumn$index");
        }
        $sheet->getRowDimension(1)->setRowHeight(90);
        for ($row = 2; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(25);
        }
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }
        return [
            2 => ['font' => ['bold' => true, 'size' => 14], 'alignment' => ['horizontal' => 'center']],
            3 => ['alignment' => ['horizontal' => 'right']],
            4 => ['font' => ['bold' => true], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Company Logo');
        $drawing->setPath(storage_path('app/public/logo.png'));
        $drawing->setHeight(80);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(10);
        $drawing->setOffsetY(5);

        return [$drawing];
    }
}