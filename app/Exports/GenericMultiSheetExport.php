<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GenericMultiSheetExport implements WithMultipleSheets
{


    protected array $sheetsData;

    /**
     * @param array $sheetsData [
     *     [
     *         'data' => [...],
     *         'headers' => [...],
     *         'title' => 'MR STATUS'
     *     ],
     *     [
     *         'data' => [...],
     *         'headers' => [...],
     *         'title' => 'PR SUMMARY'
     *     ],
     *     ...
     * ]
     */
    public function __construct(array $sheetsData)
    {
        $this->sheetsData = $sheetsData;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->sheetsData as $sheetInfo) {
            $sheets[] = new GenericExcelExport(
                $sheetInfo['data'],
                $sheetInfo['headers'],
                $sheetInfo['title']
            );
        }

        return $sheets;
    }
}
