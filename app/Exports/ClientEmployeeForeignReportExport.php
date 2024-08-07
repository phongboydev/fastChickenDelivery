<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;

use Maatwebsite\Excel\Events\BeforeExport;
use App\Models\ClientEmployee;
use App\Models\Client;

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ClientEmployeeForeignReportExport implements WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $client_id = null;
    protected $from_date = null;
    protected $to_date   = null;
    protected $templateExport;
    protected $pathFile;
    protected $dataIndex = 11;

    function __construct($clientId, $from_date, $to_date, $templateExport, $pathFile)
    {
        $this->client_id = $clientId;
        $this->from_date = $from_date;
        $this->to_date = $to_date;
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


                    $event->writer->getSheetByIndex(1);

                    $sheet2 = $event->getWriter()->getSheetByIndex(1);
                    $sheet2 = $this->getSheet2($sheet2, $event);
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

    public function styleSheet2($sheet)
    {

        $col = Coordinate::stringFromColumnIndex(16);

        $cellRange = 'A' . $this->dataIndex . ':' . $col . ($this->total_list + $this->dataIndex);

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

    public function getSheet1($sheet)
    {

        $sheet->mergeCells('C8:D8');
        $sheet->mergeCells('G8:H8');
        $sheet->mergeCells('E8:F8');
        $sheet->mergeCells('I8:J8');

        $sheet->mergeCells('H5:K5');
        $sheet->mergeCells('A1:E1');
        // $sheet->mergeCells('A2:F2');
        $sheet->mergeCells('A11:B11'); // Dia chi
        $sheet->mergeCells('C11:P11'); // Dia chi

        $sheet->mergeCells('A10:D10');
        $sheet->mergeCells('E10:P10');

        $sheet->mergeCells('C12:E12');
        $sheet->mergeCells('G12:K12');

        $sheet->mergeCells('A12:B12');

        $sheet->mergeCells('A13:C13');
        $sheet->mergeCells('D13:P13');

        $sheet->mergeCells('A14:D14');
        $sheet->mergeCells('E14:P14');

        $sheet->mergeCells('A15:F15');
        $sheet->mergeCells('G15:P15');

        $sheet->mergeCells('C16:E16');
        $sheet->mergeCells('A16:B16');

        $sheet->mergeCells('G16:K16');
        $sheet->mergeCells('A17:J17');
        $sheet->mergeCells('K17:P17');

        $sheet->mergeCells('A19:L19');
        $sheet->mergeCells('A20:D20');

        $client = Client::select('*')->where('id', $this->client_id)->first();

        $today = new Carbon();

        $dateIntro = "..………, ngày {$today->format('d')} tháng {$today->format('m')} năm {$today->format('Y')}";

        $variables = [
            '5x8'  => $dateIntro,
            '8x5'  => (new Carbon($this->from_date))->format('d/m/Y'),
            '8x9'  => (new Carbon($this->to_date))->format('d/m/Y'),
            '1x1'  => $client->company_name,
            '10x5' => $client->company_name,
            '11x3' => $client->address,
            '12x3' => $client->company_contact_phone,
            '12x7' => $client->company_contact_email,
            '13x4' => $client->company_license_no,
            '14x5' => $client->company_license_at,
            '15x7' => $client->presenter_name,
            '16x3' => $client->presenter_phone,
            '16x7' => $client->presenter_email,
            '18x1' => $client->company_name,
        ];

        foreach($variables as $key => $variable)
        {
            $pos = explode('x', $key);
            $sheet = $this->setValue($variable, $pos[0], $pos[1], $sheet);
        }

        return $sheet;
    }

    public function getSheet2($sheet, $event)
    {
        $dataIndex = $this->dataIndex;

        $client = Client::select('*')->where('id', $this->client_id)->first();

        $loai_hinh_doanh_nghiep = $client->type_of_business;

        $dataRows = ClientEmployee::selectRaw('
            nationality,
            COUNT(IF(DATE(official_contract_signing_date) <= CAST(\'' . $this->to_date . '\' AS DATE), 1, NULL)) AS tong_so,
            COUNT(IF(DATE(official_contract_signing_date) > CAST(\'' . $this->to_date . '\' AS DATE), 1, NULL)) AS so_luong,
            ROUND(AVG(IF(DATE(official_contract_signing_date) > CAST(\'' . $this->to_date . '\' AS DATE), salary, NULL)), 0) AS luong_binh_quan
        ')
        ->groupBy('nationality')
        ->where('nationality', '<>', 'Việt Nam')
        ->where('status', 'đang làm việc')
        ->where('client_id', $this->client_id)->get();

        $nghiDataRows = ClientEmployee::selectRaw('
            nationality,
            COUNT(IF(DATE(quitted_at) >= CAST(\'' . $this->from_date . '\' AS DATE) AND DATE(quitted_at) <= CAST(\'' . $this->to_date . '\' AS DATE), 1, NULL)) AS tong_so
        ')
        ->groupBy('nationality')
        ->where('nationality', '<>', 'Việt Nam')
        ->whereNotNull('quitted_at')
        ->where('client_id', $this->client_id)->get();

        $sheet = $this->setValue("Kèm theo Báo cáo số... ngày... tháng... năm... của {$client->company_name}", 3, 1, $sheet);

        if( $dataRows->isNotEmpty() ) {

            $this->total_list = $dataRows->count();

            $sheet = $this->styleSheet2($sheet);

            foreach($dataRows as $index => $d)
            {

                $tongSo = $d->tong_so;

                if($nghiDataRows->isNotEmpty()){
                    foreach($nghiDataRows as $n) {
                        if($n->nationality == $d->nationality) {
                            $tongSo += $n->tong_so;
                        }
                    }
                }

                $dataPositions = ClientEmployee::select('foreigner_job_position', 'foreigner_contract_status', 'status')
                                    ->where('nationality', $d->nationality)->where('client_id', $this->client_id)->get();

                $nha_quan_ly        = collect($dataPositions)->where('foreigner_job_position', 'Nhà quản lý')->count();
                $giam_doc_dieu_hanh = collect($dataPositions)->where('foreigner_job_position', 'Giám đốc điều hành')->count();
                $chuyen_gia         = collect($dataPositions)->where('foreigner_job_position', 'Chuyên gia')->count();
                $lao_dong_ky_thuat  = collect($dataPositions)->where('foreigner_job_position', 'Lao động kỹ thuật')->count();

                $cap_gpld               = collect($dataPositions)->where('foreigner_contract_status', 'Đã cấp giấy phép lao động')->count();
                $cap_lai_gpld           = collect($dataPositions)->where('foreigner_contract_status', 'Cấp lại GPLĐ')->count();
                $gia_han_gpld           = collect($dataPositions)->where('foreigner_contract_status', 'Gia hạn GPLĐ')->count();
                $ko_thuoc_dien_cap_gpld = collect($dataPositions)->where('foreigner_contract_status', 'Không thuộc diện cấp GPLĐ')->count();
                $chua_nop_ho_so         = collect($dataPositions)->where('foreigner_contract_status', 'Chưa được cấp/cấp lại/gia hạn /xác nhận không thuộc diện cấp GPLĐ')->count();
                $thu_hoi_gpld           = collect($dataPositions)->where('foreigner_contract_status', 'Thu hồi GPLĐ')->count();


                $row = $index + $dataIndex;

                $luong_binh_quan = $d->luong_binh_quan ? $d->luong_binh_quan : 0;

                $sheet = $this->setValue(($index+1), $row, 1, $sheet);
                $sheet = $this->setValue($d->nationality, $row, 2, $sheet);
                $sheet = $this->setValue($tongSo, $row, 3, $sheet);
                $sheet = $this->setValue($d->so_luong, $row, 4, $sheet);
                $sheet = $this->setValue($luong_binh_quan, $row, 5, $sheet);

                $sheet = $this->setValue($nha_quan_ly, $row, 6, $sheet);
                $sheet = $this->setValue($giam_doc_dieu_hanh, $row, 7, $sheet);
                $sheet = $this->setValue($chuyen_gia, $row, 8, $sheet);
                $sheet = $this->setValue($lao_dong_ky_thuat, $row, 9, $sheet);

                $sheet = $this->setValue($cap_gpld, $row, 10, $sheet);
                $sheet = $this->setValue($cap_lai_gpld, $row, 11, $sheet);
                $sheet = $this->setValue($gia_han_gpld, $row, 12, $sheet);
                $sheet = $this->setValue($ko_thuoc_dien_cap_gpld, $row, 13, $sheet);
                $sheet = $this->setValue($chua_nop_ho_so, $row, 14, $sheet);
                $sheet = $this->setValue($thu_hoi_gpld, $row, 15, $sheet);
                $sheet = $this->setValue($loai_hinh_doanh_nghiep, $row, 16, $sheet);

            }

            $totalRowIndex = $dataIndex + $dataRows->count();

            $sheet = $this->setValue('Tổng số', $totalRowIndex, 2, $sheet);

            for($col = 3; $col < 16; $col++) {

                $colFrom = Coordinate::stringFromColumnIndex($col) . $dataIndex;
                $colEnd = Coordinate::stringFromColumnIndex($col) . ($totalRowIndex - 1);

                $sheet = $this->setValue("=SUM({$colFrom}:{$colEnd})", $totalRowIndex, $col, $sheet);
            }

            $colFrom = Coordinate::stringFromColumnIndex(16) . $dataIndex;
            $colEnd = Coordinate::stringFromColumnIndex(16) . ($totalRowIndex - 1);

            $sheet = $this->setValue($dataRows->count(), $totalRowIndex, 16, $sheet);

            $sheet->getColumnDimension('B')->setWidth(22);
            $sheet->getColumnDimension('P')->setWidth(40);

        }

        return $sheet;
    }
}
