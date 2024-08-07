<?php
namespace App\Exports\VariableImportTemplate;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Style\Border;

class VariableImportSheet implements FromView, WithTitle, WithEvents, WithColumnWidths
{

    private $variables = [];
    private $client    = null;

    function __construct($variables, $client)
    {
        $this->variables = $variables;
        $this->client    = $client;
    }

    public function view(): View
    {
        if ($this->client) {
            $companyName = $this->client->company_name;
        } else {
            $companyName = "";
        }
        return view('exports.variableTemplate', [
            'companyName' => $companyName,
            'variables' => $this->variables,
        ]);
    }

    public function title(): string
    {
        return 'import_template';
    }

    public function registerEvents(): array
    {

        return [
            AfterSheet::class => function(AfterSheet $event) {
                $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                    'wrap' => false
                ];

                $cols = count($this->variables);
                $lastCol = "B";
                for ($i = 1; $i < $cols; $i++) {
                    $lastCol++;
                }
                $cellRange = 'A3:' . ($lastCol . "5");
                $event->sheet->getDelegate()->getStyle($cellRange)->getAlignment()->setWrapText(false);
                $event->sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styleArray);

                // $event->sheet->getDelegate()->getStyle("B2:C2")->applyFromArray($styleArray);
            },
        ];
    }

    public function columnWidths(): array
    {
        $cols = [];
        $colMax = count($this->variables);
        $lastCol = "B";
        for ($i = 1; $i <= $colMax; $i++) {
            $cols[$lastCol++] = 30;
        }

        return array_merge([
            'A' => 20,
        ], $cols);
    }
}
