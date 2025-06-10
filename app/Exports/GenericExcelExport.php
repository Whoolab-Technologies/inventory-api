<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class GenericExcelExport implements FromArray, WithStyles, WithDrawings
{
    protected array $data;
    protected array $headers;
    protected string $title;

    /**
     * @param array $data Rows of data (each row is an array)
     * @param array $headers Column headers
     * @param string $title Title to show on top of the sheet
     */
    public function __construct(array $data, array $headers, string $title, )
    {
        $this->data = $data;
        $this->headers = $headers;
        $this->title = $title;
    }

    public function array(): array
    {
        $rows = [
            [''],                         // empty row for logo space
            [$this->title],               // title row
            [Carbon::now()->format('d/m/Y, h:i A')],  // date/time row
            [],                          // empty row before headers
            $this->headers,              // headers
        ];

        foreach ($this->data as $row) {
            // Ensure row is indexed array matching headers count
            $rows[] = array_values($row);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();

        for ($i = 1; $i <= 3; $i++) {
            $sheet->mergeCells("A{$i}:{$lastColumn}{$i}");
        }

        $sheet->getRowDimension(1)->setRowHeight(90); // logo space
        $sheet->getRowDimension(2)->setRowHeight(40); // title
        $sheet->getRowDimension(3)->setRowHeight(25); // date

        // Default row height for data rows
        for ($row = 4; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(25);
        }

        // Set column widths
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }

        return [
            2 => [ // Title style
                'font' => ['bold' => true, 'size' => 16],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center']
            ],
            3 => [ // Date style
                'font' => ['italic' => true],
                'alignment' => ['horizontal' => 'right', 'vertical' => 'center']
            ],
            4 => [ // Header row style
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'borders' => [
                    'bottom' => ['borderStyle' => 'thin'],
                ],
            ],
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
