<?php

namespace App\Exports;

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
use PhpOffice\PhpSpreadsheet\Style\PHPExcel_Style_Fill;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use Maatwebsite\Excel\Excel;

class ImportErrorExport implements WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $errors = [];
    protected $importFile;
    protected $startRow = 1;

    function __construct($errors, $importFile)
    {
        $this->errors = $errors;
        $this->importFile = $importFile;
    }

    public function registerEvents(): array
    {

        return [
            BeforeExport::class => function (BeforeExport $event) {

                $path = storage_path('app/' . $this->importFile);

                $pathInfo = pathinfo($path);

                if (!in_array($pathInfo['extension'], ['xls', 'xlsx'])) {
                    return;
                }

                $extension = $pathInfo['extension'] == 'xls' ? Excel::XLS : Excel::XLSX;

                $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);

                $sheets = [];

                foreach ($this->errors as $sheetName => $errors) {
                    $event->writer->getSheetByName($sheetName);
                    $sheets[$sheetName] = $event->getWriter()->getSheetByName($sheetName);
                    $sheets[$sheetName] = $this->renderErrorSheet($sheets[$sheetName], $errors);
                }
            },
        ];
    }

    public function renderErrorSheet($sheet, $errors)
    {
        if (isset($errors['formats']) && $errors['formats'])
            foreach ($errors['formats'] as $error) {

                $col = $error['col'];

                $colIndex = Coordinate::stringFromColumnIndex($col);

                $pos = $colIndex . ($error['row'] + $errors['startRow']);
                $sheet->getStyle($pos)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THICK,
                            'color'       => ['rgb' => 'ff0000'],
                        ],
                    ]
                ]);

                $sheet->getComment($pos)->getText()->createTextRun($error['error']);
            }

        return $sheet;
    }
}
