<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use App\Models\EvaluationGroup;


class EvaluationGroupExport implements FromView, WithStyles, WithTitle, WithEvents
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
        $evaluationGroup = EvaluationGroup::with(['evaluationSteps', 'evaluationObjects'])
                            ->findOrFail($this->evaluationGroupId);
        $evaluationObjects = $evaluationGroup->evaluationObjects;
        return view('exports.evaluation-group-excel', [
            'evaluationGroup' => $evaluationGroup,
            'evaluationObjects' => $evaluationObjects,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setShowGridlines(false);

        $outlineBorder = array(
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        );

        $sheet->getStyle("A1:I1")->applyFromArray($outlineBorder)->getFont()->setBold(true)->setSize(20);
        $sheet->getStyle("A3:I6")->applyFromArray($outlineBorder)->getFont()->setBold(true);
        $sheet->getStyle("A8:I8")->applyFromArray($outlineBorder)->getFont()->setBold(true);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {
                $alphabet = $event->sheet->getHighestDataColumn();
                $totalRow = $event->sheet->getHighestDataRow();
                $cellRangeTable = 'A9:' . $alphabet . $totalRow;
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
        return 'Evaluation Group';
    }

}
