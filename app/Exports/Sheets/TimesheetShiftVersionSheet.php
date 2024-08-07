<?php

namespace App\Exports\Sheets;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimesheetShiftVersionSheet implements WithTitle, FromView, WithStyles
{

    private array $params;
    private $appliedShifts;
    private string $fromDate;
    private string $toDate;
    private string $name;

    public function __construct(array $params, $appliedShifts, string $fromDate, string $toDate, string $name)
    {
        $this->params = $params;
        $this->appliedShifts = $appliedShifts;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->name =  $name;
    }

    public function view(): View
    {
        return view(
            'exports.timesheet-shift-version-excel',
            [
                'params' => $this->params,
                'applied_shift' => $this->appliedShifts,
                'from_date' => $this->fromDate,
                'to_date' => $this->toDate,
                'name' => $this->name
            ]
        );
    }

    // TODO remove?
    public function title(): string
    {
        return $this->name;
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
            ],
        ];
    }
}
