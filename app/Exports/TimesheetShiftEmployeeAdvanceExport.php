<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use PhpOffice\PhpSpreadsheet\Style\Style;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;

class TimesheetShiftEmployeeAdvanceExport implements FromView, WithStyles, WithEvents, WithDefaultStyles
{
    protected $dates;
    protected $fullNames;
    protected $client;
    protected $mergedData;
    protected $toDate;
    protected $fromDate;

    public function __construct($dates, $fullNames, $mergedData, $client, $toDate, $fromDate)
    {
        $this->dates = $dates;
        $this->fullNames = $fullNames;
        $this->mergedData = $mergedData;
        $this->client = $client;
        $this->toDate = $toDate;
        $this->fromDate = $fromDate;
    }

    public function view(): View
    {
        return view(
            'exports.timesheet-shift-export-advance',
            [
                'dates' => $this->dates,
                'fullNames' => $this->fullNames,
                'mergedData' => $this->mergedData,
                'client' => $this->client,
                'toDate' => $this->toDate,
                'fromDate' => $this->fromDate
            ]
        );
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setShowGridlines(false);
    }

    public function defaultStyles(Style $defaultStyle)
    {
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {

                $alphabet = $event->sheet->getHighestDataColumn();
                $totalRow = $event->sheet->getHighestDataRow();

                $event->sheet->getStyle('A1:' . $alphabet . $totalRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR,
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ]
                ])->getAlignment()->setWrapText(true);

                $event->sheet->getStyle('A3:' . $alphabet . '3')->getFont()->setBold(true);
                $event->sheet->getColumnDimension($alphabet)->setAutoSize(true);
            },
        ];
    }
}
