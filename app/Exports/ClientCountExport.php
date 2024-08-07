<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClientCountExport implements FromView, WithStyles
{

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.client-count', [
            'data' => $this->data
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('B')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
    }
}
