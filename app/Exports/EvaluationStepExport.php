<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Models\EvaluationStep;


class EvaluationStepExport implements FromView, WithStyles, WithTitle, WithEvents, ShouldAutoSize
{
    /**
    * @return \Illuminate\Support\Collection
    */

    protected $evaluationGroupId;

    public function __construct($evaluationGroupId)
    {
        $this->evaluationGroupId = $evaluationGroupId;
    }

    public function view(): View
    {
        $evaluationSteps = EvaluationStep::with(['clientEmployees'])
                                        ->where(['evaluation_group_id' => $this->evaluationGroupId])
                                        ->get();
        return view('exports.evaluation-step-excel', [
            'evaluationSteps' => $evaluationSteps,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setShowGridlines(false);
        $alphabet = $sheet->getHighestDataColumn();
        $outlineBorder = array(
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        );

        $sheet->getStyle("A1:".$alphabet."1")->applyFromArray($outlineBorder)->getFont()->setBold(true)->setSize(20);
        $sheet->getStyle("A3:".$alphabet."3")->applyFromArray($outlineBorder)->getFont()->setBold(true);
        $sheet->mergeCells("A1:".$alphabet."1");
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {
                $alphabet = $event->sheet->getHighestDataColumn();
                $totalRow = $event->sheet->getHighestDataRow();
                $cellRangeTable = 'A4:' . $alphabet . $totalRow;
                $event->sheet->getStyle($cellRangeTable)->applyFromArray([
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
            },
        ];
    }

    public function title(): string
    {
        return 'Evaluation Step';
    }

   
}
