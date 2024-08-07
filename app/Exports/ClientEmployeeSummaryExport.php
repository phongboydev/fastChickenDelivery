<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClientEmployeeSummaryExport implements FromView, WithStyles
{
    protected $data;
    protected $date;
    // protected $fromDate;
    // protected $toDate;
    // protected $type;


    // public function __construct($data = [], $fromDate, $toDate, $type)
    public function __construct($data = [], $date)
    {
        $this->data = $data;
        $this->date = $date;
        // $this->fromDate = $fromDate;
        // $this->toDate = $toDate;
        // $this->type = $type;
    }

    /*
    Export by date range
    public function view(): View
    {
        $typeName = [];
        $colspan = 3;
        if (str_contains($this->type, 'leave_request')) {
            $typeName[] = 'NGHỈ PHÉP';
            $colspan += 3;
        }

        if (str_contains($this->type, 'overtime_request')) {
            $typeName[] = 'LÀM THÊM GIỜ';
            $colspan += 4;
        }
        if (str_contains($this->type, 'congtac_request')) {
            $typeName[] = 'CÔNG TÁC/WFH';
            $colspan += 5;
        }

        if (count($typeName) === 3) {
            $title = 'TỔNG HỢP CÔNG CỦA NHÂN VIÊN';
        } else {
            $title = 'TỔNG HỢP ' . implode(' & ', $typeName);
        }

        return view('exports.client-employee-summary-excel', [
            'data' => $this->data,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'type' => $this->type,
            'colspan' => $colspan,
            'title' => $title
        ]);
    }
    */

    public function view(): View
    {
        return view('exports.client-employee-summary-excel-by-date', [
            'data' => $this->data,
            'date' => $this->date
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            'A' => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            'E' => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            'F' => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            'G' => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            1 => [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            2 => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            3 => [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
