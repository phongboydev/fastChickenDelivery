<?php

namespace App\Exports\Sheets;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimesheetMultipleShiftVersionAdvanceSheet implements FromView, WithTitle, WithStyles, WithEvents, WithDefaultStyles
{
    private array $dates;
    private array $fullNames;
    private array $mergedData;
    private $client;
    private string $fromDate;
    private string $toDate;
    private string $name;

    public function __construct(array $mergedData, array $fullNames, $client, string $fromDate, string $toDate, string $name)
    {
        $this->fullNames = $fullNames;
        $this->mergedData = $mergedData;
        $this->client = $client;
        $this->toDate = $toDate;
        $this->fromDate = $fromDate;
        $this->name = $name;
    }

    public function view(): View
    {
        return view(
            'exports.timesheet-multiple-shift-export-advance',
            [
                'fullNames' => $this->fullNames,
                'mergedData' => $this->mergedData,
                'client' => $this->client,
                'toDate' => $this->toDate,
                'fromDate' => $this->fromDate
            ]
        );
    }

    public function title(): string
    {
        return $this->name;
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
