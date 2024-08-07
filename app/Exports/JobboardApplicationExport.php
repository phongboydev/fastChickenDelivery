<?php

namespace App\Exports;

use App\Exceptions\CustomException;

use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\JobboardApplication;
use App\Models\JobboardJob;
use App\Models\Client;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeExport;
use Maatwebsite\Excel\Excel;

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


class JobboardApplicationExport implements WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $jobboardJobId;
    protected $variables;
    protected $templateExport;
    protected $pathFile;
    protected $dataIndex = 10;

    private $total_list = 0;

    public function __construct(string $jobboardJobId, $templateExport, $pathFile)
    {
        $this->jobboardJobId = $jobboardJobId;
        $this->templateExport = $templateExport;
        $this->pathFile = $pathFile;

        return $this;
    }

    public function registerEvents()
    : array
    {

        return [
            BeforeExport::class => function(BeforeExport $event){

                if( $this->templateExport ) {

                    $path = storage_path('app/' . $this->templateExport);

                    $pathInfo = pathinfo($path);

                    if( !in_array($pathInfo['extension'], ['xls', 'xlsx']) ) { return; }

                    $extension = $pathInfo['extension'] == 'xls' ? Excel::XLS : Excel::XLSX;

                    $event->writer->reopen( new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);

                    $event->writer->getSheetByIndex(0);

                    $sheet1 = $event->getWriter()->getSheetByIndex(0);
                    $sheet1 = $this->getSheet1($sheet1);

                }
            },
        ];
    }

    public function setValue($value, $row, $col, $sheet)
    {
        $colIndex = Coordinate::stringFromColumnIndex($col);

        $pos = $colIndex . $row;

        $sheet->setCellValue($pos, $value);

        return $sheet;
    }

    public function getSheet1($sheet)
    {

        $dataIndex = $this->dataIndex;

        $jobboardJob = JobboardJob::select('*')->where('id', $this->jobboardJobId)->with('client')->first();

        $dataRows = JobboardApplication::select('*')
        ->where('jobboard_job_id', $this->jobboardJobId)
        ->orderBy('created_at', 'desc')->get();

        $sheet = $this->setValue($jobboardJob->client['company_name'], 4, 2, $sheet);
        $sheet = $this->setValue($jobboardJob->title, 6, 2, $sheet);

        if( $dataRows->isNotEmpty() ) {

            $this->total_list = $dataRows->count();

            foreach($dataRows as $index => $d)
            {
                logger(array($d));
                $row = $index + $dataIndex;

                $detailUrl = config('app.customer_url') . "/quan-ly-tin-tuyen-dung/applications/{$d->jobboard_job_id}/chi-tiet/{$d->id}";

                $sheet = $this->setValue(($index+1), $row, 1, $sheet);
                $sheet = $this->setValue($d->appliant_name, $row, 2, $sheet);
                $sheet = $this->setValue($d->appliant_tel, $row, 3, $sheet);
                $sheet = $this->setValue($d->appliant_email, $row, 4, $sheet);
                $sheet = $this->setValue($d->created_at, $row, 5, $sheet);
                $sheet = $this->setValue($detailUrl, $row, 6, $sheet);
            }

            $sheet->getColumnDimension('B')->setAutoSize(true) ;
            $sheet->getColumnDimension('F')->setAutoSize(true) ;
            $sheet->getColumnDimension('D')->setAutoSize(true) ;

            $sheet = $this->styleSheet1($sheet);
        }

        return $sheet;
    }

    public function styleSheet1($sheet)
    {

        $col = Coordinate::stringFromColumnIndex(6);

        $cellRange = 'A' . $this->dataIndex . ':' . $col . ($this->total_list + $this->dataIndex - 1);

        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ]
        ];

        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styleArray);

        return $sheet;
    }
}
