<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeExport;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Maatwebsite\Excel\Excel;

use App\Models\ClientEmployee;
use App\Models\Client;

class PayrollReportAllExport implements WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $reportData = [];

    function __construct($reportData)
    {
        $this->reportData = $reportData;
    }

    public function registerEvents()
    : array
    {

        return [
            BeforeExport::class => function(BeforeExport $event){
                
                $templateExport = 'PayrollReportAllExport.xlsx';

                $path = storage_path('app/' . $templateExport);

                $pathInfo = pathinfo($path);

                if( !in_array($pathInfo['extension'], ['xls', 'xlsx']) ) { return; }

                $extension = $pathInfo['extension'] == 'xls' ? Excel::XLS : Excel::XLSX;

                $event->writer->reopen( new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);
    
                $event->writer->getSheetByIndex(0);

                $sheet1 = $event->getWriter()->getSheetByIndex(0);

                $sheet1 = $this->renderSheet1($sheet1);
            },                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            
        ];
    }

    public function styleSheet($sheet, $totalList, $column)
    {
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
            'font'    => array(
                'name' => 'Arial',
                'size' => 11,
            )
        ];

        $sheet->getDelegate()->getStyle('A1')->applyFromArray([
            'font' => array(
                'name' => 'Arial',
                'size' => 13,
            ),
        ]);

        $col = Coordinate::stringFromColumnIndex($column);

        $cellRange = 'A7:' . $col . (6 + $totalList);

        $sheet->getDelegate()->getStyle($cellRange)->getAlignment()->applyFromArray([
            'vertical'   => 'center',
            'horizontal' => 'right'
        ])->setWrapText(true);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styleArray);


        return $sheet;
    }

    public function renderSheet1($sheet) 
    {

        if ($this->reportData) {

            $reportData = $this->reportData;

            $rowData = [];

            $carbon = new Carbon();

            $currentYear = $carbon->format('Y');

            foreach ($reportData as $cIndex => $item) {

                $yearOld = $item['year_of_birth'] ? ($currentYear - $item['year_of_birth']) : '';

                $rowData[] = [
                    $cIndex + 1,
                    $yearOld,
                    $item['position'],
                    $item['career'],
                    $item['luong_binh_quan']
                ];

            }            

            $startRow = 7;

            foreach( $rowData as $cIndex => $cRow ) {

                $col = 1;

                foreach($cRow as $value) {

                    $colIndex = Coordinate::stringFromColumnIndex($col);
                            
                    $sheet->setCellValue($colIndex . ($startRow + $cIndex), $value);
                    
                    $col++;
                }

            }

            $sheet = $this->styleSheet($sheet, count($rowData), 5);
        }

        

        return $sheet;

    }
}