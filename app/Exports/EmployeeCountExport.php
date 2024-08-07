<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeCountExport implements FromView, WithTitle, WithStyles
{

    public function __construct($data = [])
    {
        $this->data = $data;
        $this->title = $data['company_name'];
        $this->client_type = $data['client_type'];
        $this->is_test = $data['is_test'];
    }

    public function title(): string
    {
        return $this->title;
    }

    public function view(): View
    {
        return view('exports.employee-count', [
            'data' => $this->data,
            'company_name' => $this->title,
            'client_type' =>  $this->client_type,
            'is_test' =>  $this->is_test
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
    }
}
