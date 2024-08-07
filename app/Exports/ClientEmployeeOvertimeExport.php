<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class ClientEmployeeOvertimeExport implements FromView, WithStyles
{
    protected $data;
    protected $from;
    protected $to;

    public function __construct($data = [], $from, $to)
    {
        $this->data = $data;
        $this->from = $from;
        $this->to = $to;
    }

    public function view(): View
    {
        return view('exports.client-employee-overtime', [
            'data' => $this->data,
            'from' => $this->from,
            'to' => $this->to
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        return [
            1 => [
                'font' => ['bold' => true],
            ],
            2 => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'font' => ['bold' => true],
            ],
            3 => [
                'font' => ['bold' => true],
            ],
        ];
    }
}
