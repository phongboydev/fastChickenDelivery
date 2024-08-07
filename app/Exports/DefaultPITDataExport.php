<?php

namespace App\Exports;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DefaultPITDataExport implements FromView, WithStyles, WithColumnFormatting
{
    protected $from;
    protected $to;
    protected $company_name;
    protected $period;

    public function __construct($company_name, $from, $to)
    {
        $this->company_name = $company_name;
        $this->from = Carbon::parse($from)->format('Y-m');
        $this->to = Carbon::parse($to)->format('Y-m');
        $this->period = new CarbonPeriod($this->from, '1 month',  $this->to);
    }

    public function view(): View
    {
        return view(
            'exports.default-pit-data-excel',
            [
                'company_name' => $this->company_name,
                'period' => $this->period,
            ]
        );
    }

    public function columnFormats(): array
    {
        $startCell = 'E4';
        $colIndex = Coordinate::stringFromColumnIndex((4 + ($this->period->count() * 7)));
        $endCell = $colIndex . '4';
        return [
            $startCell . ':' . $endCell => 'mm-yyyy',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            3 => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => 'thin',
                        'color' => ['rgb' => '000000']
                    ],
                ]
            ],
            4 => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => 'thin',
                        'color' => ['rgb' => '000000']
                    ],
                ]
            ],
        ];
    }
}
