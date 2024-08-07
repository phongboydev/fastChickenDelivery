<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ApproveExport implements FromView, WithStyles
{
    protected $data;
    protected $fromDate;
    protected $toDate;
    protected $timezone_name;
    protected $type;
    protected $viewTemplate;

    public function __construct($params = [])
    {
        $this->data = $params['data'];
        $this->fromDate = $params['fromDate'];
        $this->toDate = $params['toDate'];
        $this->timezoneName = $params['timezoneName'];
        $this->type = $params['type'];
        $this->viewTemplate = 'exports.approve-adjust-hours';
    }

    public function view(): View
    {

        return view($this->viewTemplate, [
            'data' => $this->data,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'timezoneName' => $this->timezoneName,
            'type' => $this->type
        ]);
    }

    public function styles(Worksheet $sheet)
    {

        $sheet->getStyle('B')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        return [
            3 => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            4 => [
                'font' => ['bold' => true],
            ]
        ];
    }
}
