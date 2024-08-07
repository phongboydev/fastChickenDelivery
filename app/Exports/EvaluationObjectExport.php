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
use App\Models\EvaluationObject;

class EvaluationObjectExport  implements FromView, WithStyles, WithTitle, WithEvents, ShouldAutoSize
{
     /**
    * @return \Illuminate\Support\Collection
    */

    protected $evaluationObjectId;

    public function __construct($evaluationObjectId)
    {
        $this->evaluationObjectId = $evaluationObjectId;
    }

    public function view(): View
    {
        $evaluationObjectId = $this->evaluationObjectId;
        $evaluationObject = EvaluationObject::with([
                            'evaluationGroup.evaluationSteps.evaluationParticipants' => function($query) use ($evaluationObjectId){
                                $query->where('evaluation_object_id', $evaluationObjectId);
                            },
                        ])->findOrFail($evaluationObjectId);
        $evaluationSteps =  $evaluationObject->evaluationGroup->evaluationSteps; 
        $configuration =  $evaluationObject->evaluationGroup->configuration;                         
        return view('exports.evaluation-object-excel', [
            'evaluationObject' => $evaluationObject,
            'configuration' => collect(json_decode($configuration, true)),
            'evaluationSteps' => $evaluationSteps
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
        $sheet->getStyle("A3:".$alphabet."5")->applyFromArray($outlineBorder)->getFont()->setBold(true);
        $sheet->getStyle("A7:".$alphabet."8")->applyFromArray($outlineBorder)->getFont()->setBold(true);
        $sheet->getStyle("A10:".$alphabet."10")->applyFromArray($outlineBorder)->getFont()->setBold(true);
       
        $sheet->mergeCells("A1:".$alphabet."1");
        $sheet->mergeCells("B3:".$alphabet."3");
        $sheet->mergeCells("B4:".$alphabet."4");
        $sheet->mergeCells("B5:".$alphabet."5");
        $sheet->mergeCells("C7:".$alphabet."7");
        $sheet->mergeCells("C8:".$alphabet."8");
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {
                $alphabet = $event->sheet->getHighestDataColumn();
                $totalRow = $event->sheet->getHighestDataRow();
                $cellRangeTable = 'A11:' . $alphabet . $totalRow;
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
        $title = EvaluationObject::findOrFail($this->evaluationObjectId)
                                ->clientEmployee->full_name;
        return $title;
    }
}
