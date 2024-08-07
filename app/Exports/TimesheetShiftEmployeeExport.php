<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;

class TimesheetShiftEmployeeExport implements FromView, WithStyles
{
    protected $from_date;
    protected $to_date;
    protected $name;
    protected $appliedShift;
    protected $params;

    public function __construct($params, $appliedShift, $from_date, $to_date)
    {
        $this->name = __('timesheet_shift_employee');
        $this->from_date = $from_date;
        $this->to_date = $to_date;
        $this->params = $params;
        $this->appliedShift = $appliedShift;
    }

    public function view(): View
    {
        return view(
            'exports.timesheet-shift-excel',
            [
                'params' => $this->params,
                'applied_shift' => $this->appliedShift,
                'from_date' => $this->from_date,
                'to_date' => $this->to_date,
                'name' => $this->name
            ]
        );
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('C')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
    }
}
