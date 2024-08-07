<?php

namespace App\Exports;

use App\Models\ProvinceDistrict;
use App\Models\Province;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\BeforeExport;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Maatwebsite\Excel\Excel;
use Carbon\Carbon;
use DateTime;

class PitReportExport implements WithEvents, ShouldAutoSize
{
    use Exportable;

    protected const NUMBER_FORMAT_1 = '#,##0';

    protected const SHEET_NAME = [
        1 => "Summary",
        2 => "Summary_Client",
        3 => "Declaration (EN)",
        4 => "Payment voucher (EN)",
        5 => "Payment voucher (VN)",
    ];

    protected const SHEET_NAME_FOR_YEAR = [
        1 => "Summary",
        2 => "Summary_Client",
        3 => "Form TK05.QTT-TNCN",
        4 => "05-1.BK-TNCN",
        5 => "05-2.BK-TNCN",
        6 => "05-3.BK-TNCN",
    ];

    protected $COLUMNS_VARIABLES = [
        'en' => [
            'tnct_luy_tien' => '(Taxable income ( progressive))',
            'tnch_khautru' => '(Taxable income ( withholding 10%))',
            'tnch_khautru_20' => '(Taxable income ( withholding 20%))',
            'tong_thu_nhap_chiu_thue' => '(Total Taxable Income)',
            'so_nguoi_phuthuoc' => '(Dependants)',
            'giam_tru_giacanh_tungthang' => '(Monthly Family deduction)',
            'tong_so_nguoi_phu_thuoc' => '(Total number of dependants Calculation)',
            'ten_nguoi_phu_thuoc' => '(Dependant\'s name)',
            'bhbb_do_nld_tra' => '(Compulsory Insurance (SI,HI,UI) paid by employee) (base on payroll)',
            'thu_nhap_tinhthue' => '(Assessable income)',
            'pit_1' => '(PIT (1) Calculation)',
            'pit_theo_bangluong_luytien' => '(PIT base on payroll (progressive))',
            'pit_theo_bangluong_khautru' => '(PIT based on payroll (withholding 10%))',
            'pit_theo_bangluong_khautru_20' => '(PIT based on payroll (withholding 20%))',
            'pit_tong' => '(Total PIT)',
            'pit_1_2' => '(PIT (1) - (2))',
            'tinh_trang_quyet_toan_thue_tncn_nam' => '(Status of PIT finalization)'
        ],
        'vi' => [
            'tnct_luy_tien' => 'Thu nhập chịu thuế (theo biểu thuế lũy tiến)',
            'tnch_khautru' => 'Thu nhập chịu thuế (khấu trừ 10%)',
            'tnch_khautru_20' => 'Thu nhập chịu thuế (khấu trừ 20%)',
            'tong_thu_nhap_chiu_thue' => 'Tổng TNCT',
            'so_nguoi_phuthuoc' => 'Số người phụ thuộc',
            'giam_tru_giacanh_tungthang' => 'Giảm trừ gia cảnh từng tháng',
            'tong_so_nguoi_phu_thuoc' => 'Tổng số người phụ thuộc (tính)',
            'ten_nguoi_phu_thuoc' => 'Tên người phụ thuộc',
            'bhbb_do_nld_tra' => 'BHBB do NLĐ trả (BHXH+BHYT+BHTN) (theo bảng lương)',
            'thu_nhap_tinhthue' => 'Thu nhập tính thuế (tính)',
            'pit_1' => 'PIT (1) (tính)',
            'pit_theo_bangluong_luytien' => 'PIT theo bảng lương (biểu thuế lũy tiến)',
            'pit_theo_bangluong_khautru' => 'PIT theo bảng lương (khấu trừ 10%)',
            'pit_theo_bangluong_khautru_20' => 'PIT theo bảng lương (khấu trừ 20%)',
            'pit_tong' => 'Tổng PIT',
            'pit_1_2' => 'PIT (1) - (2)',
            'tinh_trang_quyet_toan_thue_tncn_nam' => 'Tình trạng quyết toán thuế TNCN năm'
        ]
    ];

    protected $TYPES_OF_CONTRACT = [
        'chinhthuc'                 => "Chính thức",
        'co-thoi-han-lan-1'         => "Chính thức",
        'khongthoihan'              => "Chính thức",
        'khong-xac-dinh-thoi-han'   => "Chính thức",
        'co-thoi-han-lan-2'         => "Chính thức",
        'thuviec'                   => "Thử việc",
        'thoivu'                    => "Thời vụ",
        ''                          => " ",
    ];

    protected $TIME_TEXT = [
        'en' => [
            'nam'   => 'Year',
            'quy'   => 'Quarter',
            'thang' => 'Month',
            'ngay'  => 'Day',
        ],
        'vi' => [
            'nam'   => 'Năm',
            'quy'   => 'Quý',
            'thang' => 'Tháng',
            'ngay'  => 'Ngày'
        ],
    ];

    protected $GLOBAL_DATA = [];

    protected $COLUMNS_VARIABLE_POSITIONS = [];

    protected $calculationSheets = [];
    protected $clientEmployees = [];
    protected $clientEmployeeVariables = [];
    protected $columnVariables = [];
    protected $totalRowData = [];
    protected $totalCal = 0;
    protected $client;
    protected $paymentOnBehalfServiceInformation;
    protected $companyName = '';
    protected $reportPayroll;
    protected $fixedCols = 8;
    protected $endTableColInSheet1  = 0;

    function __construct($reportPayroll, $client, $columnVariables, $calculationSheets, $clientEmployees, $clientEmployeeVariables)
    {
        $this->calculationSheets = $calculationSheets;
        $this->clientEmployees = array_values($clientEmployees);
        $this->clientEmployeeVariables = $clientEmployeeVariables;
        $this->columnVariables = $columnVariables;
        $this->totalCal = $calculationSheets->count();
        $this->client = $client;
        $this->reportPayroll = $reportPayroll;
        if ($this->client->enable_behalf_service && $this->client->behalf_service_information_id) {
            $this->paymentOnBehalfServiceInformation = $client->paymentOnBehalfServiceInformation;
        }
        $this->companyName = $client->company_name;
    }


    public function createPITForMonthOrQuarter(): array
    {
        return [
            BeforeExport::class => function (BeforeExport $event) {

                $templateExport = 'PITReportExport.xlsx';

                $path = storage_path('app/' . $templateExport);

                $pathInfo = pathinfo($path);

                if (!in_array($pathInfo['extension'], ['xls', 'xlsx'])) {
                    return;
                }

                $extension = $pathInfo['extension'] == 'xls' ? Excel::XLS : Excel::XLSX;

                $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);

                $event->writer->getSheetByIndex(0);

                $sheet1 = $event->getWriter()->getSheetByIndex(0);
                $sheet1 = $this->renderSheet1($sheet1);

                $sheet2 = $event->getWriter()->getSheetByIndex(1);
                $sheet2 = $this->renderSheet2($sheet2);

                $sheet3 = $event->getWriter()->getSheetByIndex(2);
                $sheet3 = $this->renderSheet3($sheet3);

                $sheet4 = $event->getWriter()->getSheetByIndex(3);
                $sheet4 = $this->renderSheet4($sheet4);

                $sheet5 = $event->getWriter()->getSheetByIndex(4);
                $sheet5 = $this->renderSheet5($sheet5);

                $this->renderGlobalData($event);
            },
        ];
    }

    private function renderGlobalData($event)
    {
        // GLOBAL_DATA example:
        //        [
        //            "your_key" => [
        //                "source" => [
        //                    "sheet" => 1,
        //                    "value" => "=Summary!E3"
        //                ],
        //                "target_list" => [
        //                    [
        //                        "sheet" => 2,
        //                        "pos" => "A3"
        //                    ],
        //                    [
        //                        "sheet" => 3,
        //                        "pos" => "B12"
        //                    ]
        //                ]
        //            ],
        //        ]
        if ($this->GLOBAL_DATA) {
            foreach ($this->GLOBAL_DATA as $key => $data) {
                if (empty($data['source']) || empty($data['target_list'])) {
                    continue;
                }
                $value = empty($data['source']['value']) ? "" : $data['source']['value'];
                foreach ($data['target_list'] as $target) {
                    $sheet = $event->getWriter()->getSheetByIndex($target["sheet"] - 1);
                    $sheet->setCellValue($target["pos"], $value);
                }
            }
        }
    }

    public function createPITForYear(): array
    {
        return [
            BeforeExport::class => function (BeforeExport $event) {

                $templateExport = 'PITReportExportForYear.xlsx';

                $path = storage_path('app/' . $templateExport);

                $pathInfo = pathinfo($path);

                if (!in_array($pathInfo['extension'], ['xls', 'xlsx'])) {
                    return;
                }

                $extension = $pathInfo['extension'] == 'xls' ? Excel::XLS : Excel::XLSX;

                $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);

                $event->writer->getSheetByIndex(0);

                $sheet1 = $event->getWriter()->getSheetByIndex(0);
                $sheet1 = $this->renderSheet1ForYear($sheet1);

                $sheet2 = $event->getWriter()->getSheetByIndex(1);
                $sheet2 = $this->renderSheet2ForYear($sheet2);

                $sheet4 = $event->getWriter()->getSheetByIndex(3);
                $sheet4 = $this->renderSheet4ForYear($sheet4);

                //We need content of sheet 4 for sheet 3
                $sheet3 = $event->getWriter()->getSheetByIndex(2);
                $sheet3 = $this->renderSheet3ForYear($sheet3);

                $sheet5 = $event->getWriter()->getSheetByIndex(4);
                $sheet5 = $this->renderSheet5ForYear($sheet5);

                $sheet6 = $event->getWriter()->getSheetByIndex(5);
                $sheet6 = $this->renderSheet6ForYear($sheet6);

                $this->renderGlobalData($event);
            },
        ];
    }

    public function registerEvents(): array
    {
        if ($this->reportPayroll->duration_type == 'nam') {
            return $this->createPITForYear();
        } else {
            return $this->createPITForMonthOrQuarter();
        }
    }

    public function setValue($value, $row, $col, $sheet)
    {
        $colIndex = Coordinate::stringFromColumnIndex($col);

        $pos = $colIndex . $row;

        $sheet->setCellValue($pos, $value);

        return $sheet;
    }

    public function setNumberFormat1Columns($varColName, $row, $indexCal, $sheet)
    {
        $sheet = $this->setNumberFormatCell($row, $indexCal, $sheet, self::NUMBER_FORMAT_1);

        return $sheet;
    }

    private function setRowHeight(&$sheet, $row, $height = 21)
    {
        $sheet->getDelegate()->getRowDimension($row)->setRowHeight($height);
    }

    public function setNumberFormatCell($row, $col, $sheet, $numberFormat = '#,##0.00')
    {
        $colIndex = Coordinate::stringFromColumnIndex($col);

        $pos = $colIndex . $row;

        if($numberFormat) {
            $sheet->getDelegate()->getStyle($pos)->getNumberFormat()->setFormatCode($numberFormat);
        }

        return $sheet;
    }

    public function getValue($row, $col, $sheet)
    {
        $colIndex = Coordinate::stringFromColumnIndex($col);

        $pos = $colIndex . $row;

        $cell = $sheet->getCell($pos);

        return $cell->getValue();
    }

    public function getCellIndex($row, $col)
    {

        $colIndex = Coordinate::stringFromColumnIndex($col);

        $pos = $colIndex . $row;

        return $pos;
    }

    public function styleSheet2($sheet, $endTableRow)
    {
        //Table style
        $cellRange = 'A11:L' . ($endTableRow + 4);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ]);

        $cellRangeMerge = 'A' . ($endTableRow) . ':' . 'L' . ($endTableRow);
        $sheet->mergeCells($cellRangeMerge);

        $cellRange = 'A' . ($endTableRow + 1) . ':L' . ($endTableRow + 4);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => array('rgb' => '14762B'),
                'size' => 12,
            ],
        ]);

        $cellRange = 'A' . ($endTableRow + 1) . ':D' . ($endTableRow + 4);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'center'
            ]
        ]);

        $cellRange = 'E' . ($endTableRow + 2) . ':L' . ($endTableRow + 4);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'right'
            ]
        ]);

        $cellRange = 'A' . ($endTableRow + 1) . ':L' . ($endTableRow + 1);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'fill' => array(
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('argb' => 'CCFFCC')
            )
        ]);

        $cellRange = 'A' . ($endTableRow + 3) . ':L' . ($endTableRow + 3);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'fill' => array(
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('argb' => 'CCFFCC')
            )
        ]);

        $cellRange = 'A' . ($endTableRow + 5) . ':L' . ($endTableRow + 5);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'font' => [
                'italic' => true,
            ],
        ]);
        return $sheet;
    }

    public function styleSheet2ForYear($sheet, $endTableRow)
    {
        //Table style
        $cellRange = 'A13:L' . ($endTableRow + 1);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ]);

        $cellRangeMerge = 'A' . ($endTableRow) . ':' . 'L' . ($endTableRow);
        $sheet->mergeCells($cellRangeMerge);

        $cellRange = 'A' . ($endTableRow + 1) . ':L' . ($endTableRow + 1);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => array('rgb' => '14762B'),
                'size' => 12,
            ],
            'fill' => array(
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('argb' => 'CCFFCC')
            )
        ]);

        $cellRange = 'A' . ($endTableRow + 1) . ':D' . ($endTableRow + 1);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'center'
            ]
        ]);

        $sheet->getDelegate()->getRowDimension(($endTableRow + 6))->setRowHeight(31);
        $sheet->getDelegate()->getRowDimension(($endTableRow + 7))->setRowHeight(42.5);

        $cellRange = 'A' . ($endTableRow + 4) . ':L' . ($endTableRow + 4);
        $sheet->mergeCells($cellRange);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => array('argb' => 'FFFFFF'),
                'size' => 16,
            ],
            'fill' => array(
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('argb' => '00B050')
            )
        ]);

        $cellRange = 'A' . ($endTableRow + 6) . ':L' . ($endTableRow + 6);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => array('argb' => '14762B'),
                'size' => 10,
            ],
            'fill' => array(
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('argb' => 'CCFFCC')
            )
        ]);

        $cellRange = 'A' . ($endTableRow + 6) . ':L' . ($endTableRow + 7);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'center'
            ],
            'font' => [
                'bold' => true,
            ],
        ]);
        $sheet->getDelegate()->getStyle($cellRange)->getAlignment()->setWrapText(true);

        $cellRange = 'A' . ($endTableRow + 6) . ':L' . ($endTableRow + 8);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ]);
        $cellRange = 'A' . ($endTableRow + 8) . ':A' . ($endTableRow + 8);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'center'
            ]
        ]);
        return $sheet;
    }

    public function styleSheet1($sheet, $totalList, $column)
    {
        //Company info style
        $sheet->getDelegate()->getStyle('C1:E2')->applyFromArray([
            'font' => ['size' => 14],
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'left'
            ]
        ]);

        //Table style
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
        $col = Coordinate::stringFromColumnIndex($column);
        $cellRange = 'B15:' . $col . (17 + $totalList);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styleArray);
        $sheet->getDelegate()->getStyle($cellRange)->getAlignment()->setWrapText(true);
        //Employee list style
        $cellRange = 'B17:' . $col . (16 + $totalList);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_DOTTED,
                    'color'       => ['rgb' => '000000'],
                ],
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ]);

        //STT style
        $cellRange = 'B17:B' . (16 + $totalList);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'center'
            ],
        ]);

        //Total style
        $cellRangeMerge = 'C' . ($totalList + 17) . ':' . 'D' . ($totalList + 17);
        $sheet->mergeCells($cellRangeMerge);
        $sheet = $this->setValue("TOTAL", $totalList + 17, 3, $sheet);
        $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'center'
            ],
        ]);
        $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'center'
            ],
        ]);
        return $sheet;
    }

    protected function setPITReportDurationWithText($text, $formatQuarter = false) {
        switch ($this->reportPayroll->duration_type) {
            case 'nam':
                return $text . $this->reportPayroll->quy_year;
            case 'quy':
                return $text . ($formatQuarter ? "Q" : "0") . $this->reportPayroll->quy_value . "/" . $this->reportPayroll->quy_year;
            case 'thang':
                $myDateTime = DateTime::createFromFormat('m-Y', $this->reportPayroll->thang_value);
                return $text . $myDateTime->format('m/Y');
            default:
                return '';
        }
    }

    public function renderSheet1ForYear($sheet)
    {
        $from = 0;
        $indexCal = 10;
        $index1 = 0;

        //company information
        $sheet = $this->setValue($this->companyName, 1, 3, $sheet);
        $sheet = $this->setValue("SUMMARY PIT REPORT IN", 2, 3, $sheet);
        $sheet = $this->setValue($this->setPITReportDurationWithText(""), 2, 5, $sheet);

        //render header
        foreach ($this->columnVariables as $vName => $columVariable) {

            switch ($vName) {
                case 'pit_theo_bangluong_luytien':
                case 'pit_theo_bangluong_khautru':
                case 'pit_theo_bangluong_khautru_20':
                    $mergeFrom = $indexCal;
                    $mergeTo = ($indexCal + $this->totalCal);

                    $cellRangeMerge = Coordinate::stringFromColumnIndex($mergeFrom) . '15:' . Coordinate::stringFromColumnIndex($mergeTo) . '15';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'E2EFDA')
                        )
                    ]);
                    break;
                case 'so_nguoi_phuthuoc':
                    $mergeFrom = $indexCal;
                    $mergeTo = ($indexCal + $this->totalCal);

                    $cellRangeMerge = Coordinate::stringFromColumnIndex($mergeFrom) . '15:' . Coordinate::stringFromColumnIndex($mergeTo) . '15';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'DDEBF7')
                        )
                    ]);
                    break;
                case 'tong_thu_nhap_chiu_thue':
                    $cellRangeMerge = Coordinate::stringFromColumnIndex($index1 + 10 + $from) . '15:' . Coordinate::stringFromColumnIndex($index1 + 10 + $from) . '16';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'FFD966')
                        )
                    ]);

                    break;
                case 'ten_nguoi_phu_thuoc':
                case 'tong_so_nguoi_phu_thuoc':
                case 'pit_1':
                case 'pit_1_2':
                    $cellRangeMerge = Coordinate::stringFromColumnIndex($index1 + 10 + $from) . '15:' . Coordinate::stringFromColumnIndex($index1 + 10 + $from) . '16';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'BFBFBF')
                        )
                    ]);

                    break;
                case 'pit_tong':
                    $cellRangeMerge = Coordinate::stringFromColumnIndex($index1 + 10 + $from) . '15:' . Coordinate::stringFromColumnIndex($index1 + 10 + $from) . '16';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'FFFF00')
                        )
                    ]);

                    break;
                case 'tinh_trang_quyet_toan_thue_tncn_nam':
                    $cellRangeMerge = Coordinate::stringFromColumnIndex($indexCal) . '15:' . Coordinate::stringFromColumnIndex($indexCal) . '16';
                    $comment = " UQ: Ủy quyền \r\n Không UQ: Không ủy quyền \r\n Resign: Nghỉ việc";
                    $sheet->getComment(Coordinate::stringFromColumnIndex($indexCal) . '15')
                        ->setWidth(150)->setHeight(50)
                        ->getText()->createTextRun($comment);
                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'FCE4D6')
                        )
                    ]);
                    break;
                case 'giam_tru_giacanh_tungthang':
                    $mergeFrom = $indexCal;
                    $mergeTo = ($indexCal + $this->totalCal);

                    $cellRangeMerge = Coordinate::stringFromColumnIndex($mergeFrom) . '15:' . Coordinate::stringFromColumnIndex($mergeTo) . '15';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'F4B084')
                        )
                    ]);
                    break;
                default:
                    $mergeFrom = $indexCal;
                    $mergeTo = ($indexCal + $this->totalCal);

                    $cellRangeMerge = Coordinate::stringFromColumnIndex($mergeFrom) . '15:' . Coordinate::stringFromColumnIndex($mergeTo) . '15';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'FFD966')
                        )
                    ]);
                    break;
            }

            $richText = new RichText();
            $richText->createText($this->COLUMNS_VARIABLES['vi'][$vName]. PHP_EOL);
            $payable = $richText->createTextRun($this->COLUMNS_VARIABLES['en'][$vName]);
            $payable->getFont()->setItalic(true);
            $payable->getFont()->setBold(false);
            $sheet = $this->setValue($richText, 15, ($index1 + 10 + $from), $sheet);

            $this->COLUMNS_VARIABLE_POSITIONS[$vName] = ($index1 + 10 + $from);

            if (!in_array($vName, ['tong_thu_nhap_chiu_thue', 'tong_so_nguoi_phu_thuoc', 'ten_nguoi_phu_thuoc', 'pit_1', 'pit_1_2', 'pit_tong', 'tinh_trang_quyet_toan_thue_tncn_nam'])) {

                foreach ($this->calculationSheets as $calName => $calculationSheet) {

                    $sheet = $this->setValue($calName, 16, $indexCal, $sheet);

                    $sheet->getDelegate()->getStyle(Coordinate::stringFromColumnIndex($indexCal) . 16)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'E2EFDA')
                        )
                    ]);
                    $indexCal++;
                }

                $sheet = $this->setValue('Tổng', 16, $indexCal, $sheet);
                $sheet->getDelegate()->getStyle(Coordinate::stringFromColumnIndex($indexCal) . 16)->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'vertical'   => 'center',
                        'horizontal' => 'center'
                    ],
                    'fill' => array(
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => array('argb' => 'FFFF00')
                    )
                ]);
                $from += $this->totalCal;
            }
            $indexCal++;
            $index1++;
        }
        $this->endTableColInSheet1 = $indexCal - 1;
        //render employee values
        foreach ($this->clientEmployees as $index => $employee) {
            $row = $index + 17;
            $this->setRowHeight($sheet, $row);
            $sheet = $this->setValue(($index + 1), $row, 2, $sheet);
            $sheet = $this->setValue($employee['code'], $row, 3, $sheet);
            $sheet = $this->setValue($employee['full_name'], $row, 4, $sheet);
            $sheet = $this->setValue($employee['mst_code'], $row, 5, $sheet);
            $sheet = $this->setValue($employee['id_card_number'], $row, 6, $sheet);
            $sheet = $this->setValue($employee['nationality'], $row, 7, $sheet);
            $sheet = $this->setValue($this->TYPES_OF_CONTRACT[$employee['type_of_employment_contract']] ?? "Chính thức", $row, 8, $sheet);
            $sheet = $this->setValue($employee['resident_status'] ? "Cư trú" : "Không cư trú", $row, 9, $sheet);
            $indexCal = 10;
            foreach ($this->columnVariables as $varColName => $columVariable) {
                if (!in_array($varColName, ['tong_thu_nhap_chiu_thue', 'tong_so_nguoi_phu_thuoc', 'ten_nguoi_phu_thuoc', 'pit_1', 'pit_1_2', 'pit_tong', 'tinh_trang_quyet_toan_thue_tncn_nam'])) {
                    $start = $indexCal;
                    foreach ($this->calculationSheets as $calName => $calculationSheet) {
                        switch ($varColName) {
                            case 'thu_nhap_tinhthue':
                                $value = $this->getValueThuNhapTinhThue($calName, $employee['code']);
                                break;
                            case 'pit_theo_bangluong_luytien':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance > 0 && $residentStatus == 1) ? $this->getPITValue($calName, $employee['code'], $varColName) : 0;
                                break;
                            case 'pit_theo_bangluong_khautru':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance == 0 && $residentStatus == 1) ? $this->getPITValue($calName, $employee['code'], $varColName) : 0;
                                break;
                            case 'pit_theo_bangluong_khautru_20':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance == 0 && $residentStatus == 0) ? $this->getPITValue($calName, $employee['code'], $varColName) : 0;
                                break;
                            case 'tnct_luy_tien':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance > 0 && $residentStatus == 1) ? $this->getValueVariableEmployee($calName, $employee['code'], $columVariable) : 0;
                                break;
                            case 'tnch_khautru':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance == 0 && $residentStatus == 1) ? $this->getValueVariableEmployee($calName, $employee['code'], $columVariable) : 0;
                                break;
                            case 'tnch_khautru_20':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance == 0 && $residentStatus == 0) ? $this->getValueVariableEmployee($calName, $employee['code'], $columVariable) : 0;
                                break;
                            case 'giam_tru_giacanh_tungthang':
                                $value = $this->getValueVariableEmployee($calName, $employee['code'], $columVariable);
                                if ($value && empty($this->clientEmployees[$index]['giam_tru_giacanh_tungthang'])) {
                                    $this->clientEmployees[$index]['giam_tru_giacanh_tungthang'] = 1;
                                }
                                break;
                            default:
                                $value = $this->getValueVariableEmployee($calName, $employee['code'], $columVariable);
                                break;
                        }
                        $sheet = $this->setValue($value, $row, $indexCal, $sheet);
                        $sheet = $this->setNumberFormat1Columns($varColName, $row, $indexCal, $sheet);

                        $indexCal++;
                    }
                    $startPos = $this->getCellIndex($row, $start);
                    $endPos = $this->getCellIndex($row, $indexCal - 1);
                    $total = "=SUM(".$startPos.":".$endPos.")";
                    $sheet = $this->setValue($total, $row, $indexCal, $sheet);
                } elseif ($varColName == 'ten_nguoi_phu_thuoc') {
                    $value = '';
                    $sheet = $this->setValue(round($value), $row, $indexCal, $sheet);
                } elseif ($varColName == 'tong_thu_nhap_chiu_thue') {
                    $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnct_luy_tien'] + $this->totalCal) . $row;
                    $pos2 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru']  + $this->totalCal) . $row;
                    $pos3 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru_20']  + $this->totalCal) . $row;

                    $sheet = $this->setValue("={$pos1}+{$pos2}+{$pos3}", $row, $indexCal, $sheet);
                } elseif ($varColName == 'pit_tong') {
                    $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_luytien'] + $this->totalCal) . $row;
                    $pos2 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru'] + $this->totalCal) . $row;
                    $pos3 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru_20'] + $this->totalCal) . $row;

                    $sheet = $this->setValue("={$pos1}+{$pos2}+{$pos3}", $row, $indexCal, $sheet);
                }
                $sheet = $this->setNumberFormat1Columns($varColName, $row, $indexCal, $sheet);

                $indexCal++;
            }
        }

        // Total row
        $sheet = $this->renderTotalRow($sheet);

        $sheet = $this->renderTotalBlock($sheet);

        $totalList = count($this->clientEmployees);
        $totalCalVar = 10;
        $totalExtraVar = 6;
        $totalCol  = ($this->totalCal * $totalCalVar) + $totalCalVar + $this->fixedCols + $totalExtraVar;

        $sheet = $this->styleSheet1($sheet, $totalList, $totalCol);

        return $sheet;
    }

    public function renderSheet2ForYear($sheet)
    {
        //company information
        $sheet = $this->setValue($this->companyName, 1, 1, $sheet);
        $sheet = $this->setValue("Summary Information about PIT Declaration", 2, 1, $sheet);
        $sheet = $this->setValue("Period:", 3, 1, $sheet);
        $sheet->setCellValue('B3', '=Summary!E2');

        $pos1 = '$C$17';
        $pos2 = '$'.Coordinate::stringFromColumnIndex($this->endTableColInSheet1) . (count($this->clientEmployees) + 17);
        $sheet->setCellValue('B5', '=VLOOKUP($E$5,Summary!'.$pos1.':'.$pos2.',2,0)');
        $sheet->setCellValue('B6', '=VLOOKUP($E$5,Summary!'.$pos1.':'.$pos2.',3,0)');
        $sheet->setCellValue('B7', '=VLOOKUP($E$5,Summary!'.$pos1.':'.$pos2.',4,0)');
        $sheet->setCellValue('E6', '=IF(VLOOKUP($E$5,Summary!'.$pos1.':'.$pos2.',7,0)="Cư trú","Resident","Non-Resident")');
        $sheet->setCellValue('E7', '=IF(VLOOKUP($E$5,Summary!'.$pos1.':'.$pos2.','.($this->endTableColInSheet1-2).',0)="UQ","Authorization",IF(VLOOKUP($E$5,Summary!'.$pos1.':'.$pos2.','.($this->endTableColInSheet1-2).',0)="Không UQ","Non-authorization","Resign"))');


        $totalSheet = count($this->calculationSheets);
        $startTableRow = 15;
        $index1 = 0;
        $countRecord = 0;
        $blankRows = 1;
        foreach ($this->calculationSheets as $calName => $calculationSheet) {
            // The starting point of each block in months is:
            // start of table + count (employee which rendered each month ) + block_total * block_index
            $startMonthBlock = $startTableRow + $countRecord + (3 * $index1);

            //render employee value
            $countEmployeeInMonth = $blankRows;
            foreach($this->clientEmployees as $key => $employee) {
                $taxable_income_total = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['tnct_luy_tien'])
                                      + $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['tnch_khautru'])
                                      + $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['tnch_khautru_20']);

                $is_null = !$taxable_income_total
                    && !$this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang'])
                    && !$this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['bhbb_do_nld_tra']);

                if ($is_null) continue;

                $row = $startMonthBlock + $countEmployeeInMonth;
                $rowInSummarySheet = 17 + $key;

                $this->setRowHeight($sheet, $row);

                $sheet = $this->setValue($countEmployeeInMonth, $row, 2, $sheet);
                $sheet = $this->setValue($employee['code'], $row, 3, $sheet);
                $sheet = $this->setValue($employee['full_name'], $row, 4, $sheet);

                //Residents status
                if ($this->checkResidentStatus($calName, $employee['code'], $employee['resident_status'])) {
                    $sheet = $this->setValue("Resident", $row, 5, $sheet);
                } else {
                    $sheet = $this->setValue("Non-resident", $row, 5, $sheet);
                }

                //Total Taxable Income paid to individuals
                $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnct_luy_tien'] + $index1) . $rowInSummarySheet;
                $pos2 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru']  + $index1) . $rowInSummarySheet;
                $pos3 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru_20']  + $index1) . $rowInSummarySheet;
                $sheet->setCellValue('F' . ($startMonthBlock + $countEmployeeInMonth), "=Summary!{$pos1} + Summary!{$pos2} + Summary!{$pos3}");

                //Total Taxable Income paid to individuals subject to withholding tax
                $pos1 = "L" . ($startMonthBlock + $countEmployeeInMonth);
                $pos2 = "F" . ($startMonthBlock + $countEmployeeInMonth);
                $sheet->setCellValue('G' . ($startMonthBlock + $countEmployeeInMonth), "=IF({$pos1}>0,{$pos2},0)");

                //Family deduction
                $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['giam_tru_giacanh_tungthang']  + $index1) . $rowInSummarySheet;
                $sheet->setCellValue('H' . ($startMonthBlock + $countEmployeeInMonth), "=Summary!{$pos1}");

                //Total insurance
                $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['bhbb_do_nld_tra']  + $index1) . $rowInSummarySheet;
                $sheet->setCellValue('I' . ($startMonthBlock + $countEmployeeInMonth), "=Summary!{$pos1}");

                //Total
                $pos1 = "H" . ($startMonthBlock + $countEmployeeInMonth);
                $pos2 = "I" . ($startMonthBlock + $countEmployeeInMonth);
                $sheet->setCellValue('J' . ($startMonthBlock + $countEmployeeInMonth), "=SUM({$pos1}:{$pos2})");

                //Assessable income
                $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['thu_nhap_tinhthue']  + $index1) . $rowInSummarySheet;
                $sheet->setCellValue('K' . ($startMonthBlock + $countEmployeeInMonth), "=Summary!{$pos1}");

                //PIT withheld
                $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_luytien'] + $index1) . $rowInSummarySheet;
                $pos2 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru']  + $index1) . $rowInSummarySheet;
                $pos3 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru_20']  + $index1) . $rowInSummarySheet;
                $sheet->setCellValue('L' . ($startMonthBlock + $countEmployeeInMonth), "=Summary!{$pos1} + Summary!{$pos2} + Summary!{$pos3}");
                $countEmployeeInMonth++;
            }
            $countEmployeeInMonth += $blankRows;
            $countRecord += $countEmployeeInMonth;

            //Group month
            $cellRangeMerge = 'A' . ($startMonthBlock) . ':' . 'A' . ($startMonthBlock + $countEmployeeInMonth - 1);
            $sheet->mergeCells($cellRangeMerge);
            $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                'alignment' => [
                    'vertical'   => 'center',
                    'horizontal' => 'center'
                ]
            ]);
            $formatDate = Carbon::createFromFormat('n/Y', $calName)
                ->format('M-Y');
            $sheet->setCellValue('A' . $startMonthBlock, $formatDate);

            //Render total block
            $cellRangeMerge = 'A' . ($startMonthBlock + $countEmployeeInMonth) . ':' . 'C' . ($startMonthBlock + $countEmployeeInMonth + 2);
            $sheet->mergeCells($cellRangeMerge);
            $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                'alignment' => [
                    'vertical'   => 'center',
                    'horizontal' => 'center'
                ]
            ]);
            $cellStyle = 'A' . ($startMonthBlock + $countEmployeeInMonth) . ':' . 'L' . ($startMonthBlock + $countEmployeeInMonth + 2);
            $sheet->getDelegate()->getStyle($cellStyle)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'italic' => true,
                    'color' => array('rgb' => '14762B'),
                ],
                'fill' => array(
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => array('argb' => 'D9D9D9')
                )
            ]);
            $cellStyle = 'D' . ($startMonthBlock + $countEmployeeInMonth + 2) . ':' . 'L' . ($startMonthBlock + $countEmployeeInMonth + 2);
            $sheet->getDelegate()->getStyle($cellStyle)->applyFromArray([
                'fill' => array(
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => array('argb' => '14762B')
                ),
                'font' => [
                    'color' => array('argb' => 'FFFFFF'),
                ],
            ]);

            $sheet->setCellValue('A' . ($startMonthBlock + $countEmployeeInMonth), 'Summary in ' . $formatDate);
            $sheet->setCellValue('D' . ($startMonthBlock + $countEmployeeInMonth), 'Resident');
            $sheet->setCellValue('D' . ($startMonthBlock + $countEmployeeInMonth + 1), 'Non-resident');
            $sheet->setCellValue('D' . ($startMonthBlock + $countEmployeeInMonth + 2) , 'Total');

            $posResidentStart = 'E' . $startMonthBlock;
            $posResidentEnd = 'E' . ($startMonthBlock + $countEmployeeInMonth - 1);
            $sheet->setCellValue('E' . ($startMonthBlock + $countEmployeeInMonth), "=COUNTIF({$posResidentStart}:{$posResidentEnd},\"Resident\")");
            $sheet->setCellValue('E' . ($startMonthBlock + $countEmployeeInMonth + 1), "=COUNTIF({$posResidentStart}:{$posResidentEnd},\"Non-resident\")");
            $pos1 = 'E' . ($startMonthBlock + $countEmployeeInMonth);
            $pos2 = 'E' . ($startMonthBlock + $countEmployeeInMonth + 1);
            $sheet->setCellValue('E' . ($startMonthBlock + $countEmployeeInMonth + 2), "={$pos1} + {$pos2}");

            foreach(['F', 'G', 'H', 'I', 'J', 'K', 'L'] as $column) {
                $posColStart = $column . $startMonthBlock;
                $posColEnd = $column . ($startMonthBlock + $countEmployeeInMonth - 1);
                $sheet->setCellValue($column . ($startMonthBlock + $countEmployeeInMonth), "=SUMIF({$posResidentStart}:{$posResidentEnd},\"Resident\", {$posColStart}:{$posColEnd})");
                $sheet->setCellValue($column . ($startMonthBlock + $countEmployeeInMonth + 1), "=SUMIF({$posResidentStart}:{$posResidentEnd},\"Non-resident\", {$posColStart}:{$posColEnd})");
                $sheet->setCellValue($column . ($startMonthBlock + $countEmployeeInMonth + 2), "=SUM({$posColStart}:{$posColEnd})");
            }
            $index1++;
        }

        //Render table total: TOTAL in
        $endTableRow = $startTableRow + $countRecord + ($totalSheet * 3);
        $cellRangeMerge = 'A' . ($endTableRow + 1) . ':' . 'D' . ($endTableRow + 1);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->setCellValue('A' . ($endTableRow + 1), '="TOTAL in "&Summary!E2');
        $sheet->setCellValue('E' . ($endTableRow + 1), '=E6');
        foreach (["F", "G", "H", "I", "J", "K", "L"] as $col) {
            $pos = $col . "15:" . $col . ($endTableRow - 1);
            $sheet->setCellValue($col . ($endTableRow + 1), '=SUBTOTAL(9,' . $pos . ')');
        }

        //Render PIT FINALIZATION CALCULATION
        $sheet->setCellValue('A' . ($endTableRow + 4), 'PIT FINALIZATION CALCULATION');

        //total block: title
        $cellRangeMerge = 'A' . ($endTableRow + 6) . ':' . 'A' . ($endTableRow + 7);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->setCellValue('A' . ($endTableRow + 6), 'No. of months');

        $cellRangeMerge = 'B' . ($endTableRow + 6) . ':' . 'B' . ($endTableRow + 7);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->setCellValue('B' . ($endTableRow + 6), 'Total Taxable Income');

        $cellRangeMerge = 'C' . ($endTableRow + 6) . ':' . 'E' . ($endTableRow + 6);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->setCellValue('C' . ($endTableRow + 6), 'Deduction');

        $cellRangeMerge = 'F' . ($endTableRow + 6) . ':' . 'G' . ($endTableRow + 6);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->setCellValue('F' . ($endTableRow + 6), 'Assessable Income');

        $cellRangeMerge = 'H' . ($endTableRow + 6) . ':' . 'L' . ($endTableRow + 6);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->setCellValue('H' . ($endTableRow + 6), 'PIT Calculation');

        //total block: sub-title
        $sheet->setCellValue('C' . ($endTableRow + 7), 'Dependents');
        $sheet->setCellValue('D' . ($endTableRow + 7), 'Adjustment (authorization/ additional register)');
        $sheet->setCellValue('E' . ($endTableRow + 7), 'Insurance');
        $sheet->setCellValue('F' . ($endTableRow + 7), 'Total');
        $sheet->setCellValue('G' . ($endTableRow + 7), 'Monthly Average');
        $sheet->setCellValue('H' . ($endTableRow + 7), 'Average Monthly PIT');
        $sheet->setCellValue('I' . ($endTableRow + 7), 'Total Payable PIT');
        $sheet->setCellValue('J' . ($endTableRow + 7), 'Total PIT withheld in tax period');
        $sheet->setCellValue('K' . ($endTableRow + 7), 'Additional PIT to be withheld');
        $sheet->setCellValue('L' . ($endTableRow + 7), 'Refundable PIT');

        //total block: value
        $pos1 = 'F15';
        $pos2 = 'F' . ($endTableRow - 1);
        $sheet->setCellValue('A' . ($endTableRow + 8), "=IF(E7=\"Authorization\",12,SUBTOTAL(103,{$pos1}:{$pos2}))");
        $pos1 = 'F' . ($endTableRow + 1);
        $sheet->setCellValue('B' . ($endTableRow + 8), "={$pos1}");
        $pos1 = 'H' . ($endTableRow + 1);
        $sheet->setCellValue('C' . ($endTableRow + 8), "={$pos1}");
        $pos1 = 'I' . ($endTableRow + 1);
        $sheet->setCellValue('E' . ($endTableRow + 8), "={$pos1}");
        $pos1 = 'B' . ($endTableRow + 8);
        $pos2 = 'D' . ($endTableRow + 8);
        $pos3 = 'E' . ($endTableRow + 8);
        $pos4 = 'C' . ($endTableRow + 8);
        $sheet->setCellValue('F' . ($endTableRow + 8), "={$pos1}-{$pos2}-{$pos3}-{$pos4}");
        $pos1 = 'F' . ($endTableRow + 8);
        $pos2 = 'A' . ($endTableRow + 8);
        $sheet->setCellValue('G' . ($endTableRow + 8), "={$pos1}/{$pos2}");
        $pos1 = 'E' . ($endTableRow + 1);
        $pos2 = 'G' . ($endTableRow + 8);
        $sheet->setCellValue('H' . ($endTableRow + 8), "=IF({$pos1}=\"Non-resident\",{$pos2}*20%,IFERROR(IF({$pos2}<=5000000,{$pos2}*0.05,IF({$pos2}<=10000000,{$pos2}*0.1-250000,IF({$pos2}<=18000000,{$pos2}*0.15-750000,IF({$pos2}<=32000000,{$pos2}*0.2-1650000,IF({$pos2}<=52000000,{$pos2}*0.25-3250000,IF({$pos2}<=80000000,{$pos2}*0.3-5850000,{$pos2}*0.35-9850000)))))),\"\"))");
        $pos1 = 'H' . ($endTableRow + 8);
        $pos2 = 'A' . ($endTableRow + 8);
        $sheet->setCellValue('I' . ($endTableRow + 8),  "={$pos1}*{$pos2}");
        $pos1 = 'L' . ($endTableRow + 1);
        $sheet->setCellValue('J' . ($endTableRow + 8), "={$pos1}");
        $pos1 = 'I' . ($endTableRow + 8);
        $pos2 = 'J' . ($endTableRow + 8);
        $sheet->setCellValue('K' . ($endTableRow + 8), "=IF({$pos1}-{$pos2}<0,0,{$pos1}-{$pos2})");
        $pos1 = 'J' . ($endTableRow + 8);
        $pos2 = 'I' . ($endTableRow + 8);
        $sheet->setCellValue('L' . ($endTableRow + 8), "=IF({$pos1}-{$pos2}<0,0,{$pos1}-{$pos2})");

        $sheet = $this->styleSheet2ForYear($sheet, $endTableRow);
        return $sheet;
    }

    public function renderSheet3ForYear($sheet)
    {
        $sheet->setCellValue('A9', "[01] Kỳ tính thuế  (Tax period): Năm (Year) {$this->reportPayroll->quy_year}");
        $sheet = $this->setValue('=Summary!$C$1', 20, 5, $sheet);
        $sheet = $this->setValue($this->client->pit_declaration_company_tax_code, 20, 5, $sheet);

        $this->GLOBAL_DATA["total_individuals_authorizing_organization"]["target_list"][] = ["sheet" => 3, "pos" => 'S54'];
        $this->GLOBAL_DATA["total_pit_withheld"]["target_list"][] = ["sheet" => 3, "pos" => 'S55'];
        $this->GLOBAL_DATA["total_pit_payable_amount"]["target_list"][] = ["sheet" => 3, "pos" => 'S57'];
        $this->GLOBAL_DATA["total_pit_amount_to_be_paid"]["target_list"][] = ["sheet" => 3, "pos" => 'S59'];
        $this->GLOBAL_DATA["total_overpaid_pit"]["target_list"][] = ["sheet" => 3, "pos" => 'S60'];

        return $sheet;
    }

    public function renderSheet4ForYear($sheet)
    {
        $totalSheet = count($this->calculationSheets);
        $row = 17;
        $no = 0;
        foreach ($this->clientEmployees as $index => $employee) {
            if (empty($employee['giam_tru_giacanh_tungthang'])) {
                continue;
            }

            ++$row;
            ++$no;

            $this->setRowHeight($sheet, $row);

            $sheet = $this->setValue($no, $row, 1, $sheet);
            $sheet = $this->setValue($employee['full_name'], $row, 3, $sheet);
            $sheet = $this->setValue($employee['mst_code'], $row, 4, $sheet);
            $sheet = $this->setValue($employee['id_card_number'], $row, 6, $sheet);
            $sheet = $this->setValue('=IF(VLOOKUP($F18,Summary!$F$17:$EN$35,139,0)="UQ","X","")', $row, 7, $sheet);

            $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tong_thu_nhap_chiu_thue']) . (17 + $index);
            $sheet = $this->setValue("=Summary!{$pos1}", $row, 9, $sheet);

            $pos1 = '$D' . $row;
            $sheet = $this->setValue("=COUNTIF('05-3.BK-TNCN'!\$C\$18:C118,{$pos1})", $row, 13, $sheet);

            $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['giam_tru_giacanh_tungthang'] + $totalSheet) . (17 + $index);
            $sheet = $this->setValue("=Summary!{$pos1}", $row, 14, $sheet);

            $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['bhbb_do_nld_tra'] + $totalSheet) . (17 + $index);
            $sheet = $this->setValue("=Summary!{$pos1}", $row, 16, $sheet);

            $pos1 = '$I' . $row;
            $pos2 = '$K' . $row;
            $pos3 = '$L' . $row;
            $pos4 = '$N' . $row;
            $pos5 = '$Q' . $row;
            $sheet = $this->setValue("=IF({$pos1}-SUM({$pos2},{$pos3},{$pos4}:{$pos5})<0,0,{$pos1}-SUM({$pos2},{$pos3},{$pos4}:{$pos5}))", $row, 18, $sheet);

            $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_tong']) . (17 + $index);
            $sheet = $this->setValue("=Summary!{$pos1}", $row, 19, $sheet);

            $pos1 = '$G' . $row;
            $pos2 = '$R' . $row;
            $sheet = $this->setValue("=IF({$pos1}=\"\",0,12*IFERROR(IF(({$pos2}/12)<=5000000,({$pos2}/12)*0.05,IF(({$pos2}/12)<=10000000,({$pos2}/12)*0.1-250000,IF(({$pos2}/12)<=18000000,({$pos2}/12)*0.15-750000,IF(({$pos2}/12)<=32000000,({$pos2}/12)*0.2-1650000,IF(({$pos2}/12)<=52000000,({$pos2}/12)*0.25-3250000,IF(({$pos2}/12)<=80000000,({$pos2}/12)*0.3-5850000,({$pos2}/12)*0.35-9850000)))))),\"\"))", $row, 21, $sheet);

            $pos1 = '$G' . $row;
            $pos2 = '$U' . $row;
            $pos3 = '$S' . $row;
            $sheet = $this->setValue("=IF({$pos1}=\"\",0,IF({$pos3}-{$pos2}<0,0,{$pos3}-{$pos2}))", $row, 22, $sheet);
            $sheet = $this->setValue("=IF({$pos1}=\"\",0,IF({$pos2}-{$pos3}<0,0,{$pos2}-{$pos3}))", $row, 23, $sheet);
        }

        //create Data for sheet 3
        $sheetName = self::SHEET_NAME_FOR_YEAR[4];
        $pos1 = '$G' . '18';
        $pos2 = '$G' . $row;
        $pos3 = '$S' . '18';
        $pos4 = '$S' . $row;
        $this->GLOBAL_DATA["total_individuals_authorizing_organization"]["source"] = ["sheet" => 4, "value" => "=COUNTIF('{$sheetName}'!{$pos1}:{$pos2},\"=X\")"];
        $this->GLOBAL_DATA["total_pit_withheld"]["source"] = ["sheet" => 4, "value" => "=SUMIF('{$sheetName}'!{$pos1}:{$pos2}  ,\"=X\",'05-1.BK-TNCN'!{$pos3}:{$pos4})"];

        $pos1 = '$U$' . ($row + 1);
        $this->GLOBAL_DATA["total_pit_payable_amount"]["source"] = ["sheet" => 4, "value" => "='{$sheetName}'!{$pos1}"];

        $pos1 = '$W$' . ($row + 1);
        $this->GLOBAL_DATA["total_pit_amount_to_be_paid"]["source"] = ["sheet" => 4, "value" => "='{$sheetName}'!{$pos1}"];

        $pos1 = '$V$' . ($row + 1);
        $this->GLOBAL_DATA["total_overpaid_pit"]["source"] = ["sheet" => 4, "value" => "='{$sheetName}'!{$pos1}"];


        $cellRangeMerge = 'A' . ($row + 1) . ':' . 'H' . ($row + 1);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'center'
            ]
        ]);
        $sheet->setCellValue('A' . ($row + 1), "Tổng/(Total)");

        foreach (['I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X'] as $col) {
            $pos1 = $col . '18';
            $pos2 = $col . $row;
            $sheet->setCellValue($col . ($row + 1), "=SUM({$pos1}:{$pos2})");
        }

        $sheet->setCellValue('A' . ($row + 3), "(TNCT: Thu nhập chịu thuế; TNCN: thu nhập cá nhân; NPT: người phụ thuộc; SĐDCN: Số định danh cá nhân)");

        $cellRange = 'G' . 18 . ':' . 'G' . ($row + 1);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'center'
            ]
        ]);

        foreach (['M', 'R', 'U', 'V', 'W'] as $col) {
            $cellRange = $col . 18 . ':' . $col . ($row + 1);
            $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
                'fill' => array(
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => array('argb' => 'DCE6F1')
                )
            ]);
        }

        $cellRange = 'D' . 18 . ':' . 'F' . ($row + 1);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'left'
            ]
        ]);

        $cellRange = 'A' . 18 . ':' . 'X' . ($row + 1);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ]);
        $cellRange = 'A' . ($row + 1) . ':' . 'X' . ($row + 1);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'font' => [
                'color' => array('rgb' => '16365C'),
                'bold' => true,
            ],
            'fill' => array(
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('argb' => 'C5D9F1')
            ),
        ]);
        $sheet->getDelegate()->getStyle('A' . ($row + 3))->applyFromArray([
            'font' => [
                'italic' => true,
                'color' => array('rgb' => '16365C'),
            ],
        ]);
        return $sheet;
    }

    public function renderSheet5ForYear($sheet)
    {
        $row = 17;
        $no = 0;
        foreach ($this->clientEmployees as $index => $employee) {
            if (!empty($employee['giam_tru_giacanh_tungthang'])) {
                continue;
            }

            ++$row;
            ++$no;

            $this->setRowHeight($sheet, $row);

            $sheet = $this->setValue($no, $row, 1, $sheet);
            $sheet = $this->setValue($employee['full_name'], $row, 2, $sheet);
            $sheet = $this->setValue($employee['mst_code'], $row, 3, $sheet);
            $sheet = $this->setValue($employee['id_card_number'], $row, 5, $sheet);

            $res = $employee['resident_status'] ? "" : "X";
            $sheet = $this->setValue($res, $row, 6, $sheet);

            $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tong_thu_nhap_chiu_thue']) . (17 + $index);
            $sheet = $this->setValue("=Summary!{$pos1}", $row, 7, $sheet);

            $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_tong']) . (17 + $index);
            $sheet = $this->setValue("=Summary!{$pos1}", $row, 11, $sheet);
        }

        $cellRangeMerge = 'A' . ($row + 1) . ':' . 'F' . ($row + 1);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'center'
            ]
        ]);
        $sheet->setCellValue('A' . ($row + 1), "Tổng/(Total)");

        foreach (['G', 'H', 'I', 'J', 'K', 'L'] as $col) {
            $pos1 = $col . '18';
            $pos2 = $col . $row;
            $sheet->setCellValue($col . ($row + 1), "=SUM({$pos1}:{$pos2})");
        }
        $sheet->setCellValue('A' . ($row + 3), "(BH: Bảo hiểm; DN: doanh nghiệp; CMND: Chứng minh nhân dân; CCCD: Căn cước công dân)");

        $cellRange = 'A' . 18 . ':' . 'L' . ($row + 1);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ]);
        $cellRange = 'A' . ($row + 1) . ':' . 'L' . ($row + 1);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'font' => [
                'color' => array('rgb' => '16365C'),
                'bold' => true,
            ],
            'fill' => array(
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('argb' => 'C5D9F1')
            ),
        ]);
        $sheet->getDelegate()->getStyle('A' . ($row + 3))->applyFromArray([
            'font' => [
                'italic' => true,
                'color' => array('rgb' => '16365C'),
            ],
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'left'
            ]
        ]);
        return $sheet;
    }

    public function renderSheet6ForYear($sheet)
    {
        $row = 17;
        $no = 0;
        $startOfYear = Carbon::parse($this->reportPayroll->quy_year . '-01-01');
        $endOfYear = Carbon::parse($this->reportPayroll->quy_year . '-12-31');
        foreach ($this->clientEmployees as $employee) {
            if (empty($employee->dependentsInformation)) {
                continue;
            }
            foreach ($employee->dependentsInformation as $dependant)
            {
                $dependant['from_date'] = !empty($dependant['from_date']) ? $dependant['from_date'] : $startOfYear->toDateString();
                $dependant['to_date'] = !empty($dependant['to_date']) ? $dependant['to_date'] : $endOfYear->toDateString();
                //period of dependant doesn't belong to this PIT period.
                if ($startOfYear->isAfter($dependant['to_date'])
                    || $endOfYear->isBefore($dependant['from_date'])
                    || Carbon::parse($dependant['to_date'])->isBefore($dependant['from_date'])
                ) {
                    continue;
                }

                ++$row;
                ++$no;

                $this->setRowHeight($sheet, $row);

                $sheet = $this->setValue($no, $row, 1, $sheet);
                $sheet = $this->setValue($employee['full_name'], $row, 2, $sheet);
                $sheet = $this->setValue($employee['mst_code'], $row, 3, $sheet);
                $sheet = $this->setValue($dependant['name_dependents'], $row, 4, $sheet);
                $sheet = $this->setValue($dependant['date_of_birth'], $row, 5, $sheet);
                $sheet = $this->setValue($dependant['tax_code'], $row, 6, $sheet);
                $sheet = $this->setValue($dependant['identification_number'], $row, 8, $sheet);
                $sheet = $this->setValue($dependant['relationship'], $row, 9, $sheet);

                if ($startOfYear->isAfter($dependant['from_date'])) {
                    $startPeriod = $startOfYear->format('m/Y');
                } else {
                    $startPeriod = Carbon::parse($dependant['from_date'])->format('m/Y');
                }
                $sheet = $this->setValue($startPeriod, $row, 10, $sheet);

                if ($endOfYear->isBefore($dependant['to_date'])) {
                    $endPeriod = $endOfYear->format('m/Y');
                } else {
                    $endPeriod = Carbon::parse($dependant['to_date'])->format('m/Y');
                }
                $sheet = $this->setValue($endPeriod, $row, 11, $sheet);
            }
        }

        $sheet->setCellValue('A' . ($row + 2), "(MST: Mã số thuế; CMND: Chứng minh nhân dân; CCCD: Căn cước công dân, GKS: Giấy khai sinh)");


        $cellRange = 'A' . 18 . ':' . 'K' . ($row);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ]);

        $sheet->getDelegate()->getStyle('A' . ($row + 2))->applyFromArray([
            'font' => [
                'italic' => true,
                'color' => array('rgb' => '16365C'),
            ],
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'left'
            ]
        ]);

        return $sheet;
    }

    public function renderSheet1($sheet)
    {

        $from = 0;
        $indexCal = 10;
        $index1 = 0;

        //company information
        $sheet = $this->setValue($this->companyName, 1, 3, $sheet);
        $sheet = $this->setValue("SUMMARY PIT REPORT IN", 2, 3, $sheet);
        $sheet = $this->setValue($this->setPITReportDurationWithText($this->TIME_TEXT['en'][$this->reportPayroll->duration_type] . " "), 2, 5, $sheet);

        //render header
        foreach ($this->columnVariables as $vName => $columVariable) {

            switch ($vName) {
                case 'pit_theo_bangluong_luytien':
                case 'pit_theo_bangluong_khautru':
                case 'pit_theo_bangluong_khautru_20':
                    $mergeFrom = $indexCal;
                    $mergeTo = ($indexCal + $this->totalCal);

                    $cellRangeMerge = Coordinate::stringFromColumnIndex($mergeFrom) . '15:' . Coordinate::stringFromColumnIndex($mergeTo) . '15';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'E2EFDA')
                        )
                    ]);
                    break;
                case 'so_nguoi_phuthuoc':
                    $mergeFrom = $indexCal;
                    $mergeTo = ($indexCal + $this->totalCal);

                    $cellRangeMerge = Coordinate::stringFromColumnIndex($mergeFrom) . '15:' . Coordinate::stringFromColumnIndex($mergeTo) . '15';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'DDEBF7')
                        )
                    ]);
                    break;
                case 'tong_thu_nhap_chiu_thue':
                    $cellRangeMerge = Coordinate::stringFromColumnIndex($index1 + 10 + $from) . '15:' . Coordinate::stringFromColumnIndex($index1 + 10 + $from) . '16';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'FFD966')
                        )
                    ]);

                    break;
                case 'ten_nguoi_phu_thuoc':
                case 'tong_so_nguoi_phu_thuoc':
                case 'pit_1':
                case 'pit_1_2':

                    $column = Coordinate::stringFromColumnIndex($index1 + 10 + $from);
                    $cellRangeMerge = $column . '15:' . $column . '16';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'BFBFBF')
                        )
                    ]);
                    $sheet->getDelegate()->getColumnDimension($column)->setVisible(false);

                    break;
                case 'pit_tong':
                    $cellRangeMerge = Coordinate::stringFromColumnIndex($index1 + 10 + $from) . '15:' . Coordinate::stringFromColumnIndex($index1 + 10 + $from) . '16';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'FFFF00')
                        )
                    ]);

                    break;
                case 'tinh_trang_quyet_toan_thue_tncn_nam':
                    $mergeFrom = $indexCal;
                    $mergeTo = ($indexCal + 2);

                    $cellRangeMerge = Coordinate::stringFromColumnIndex($mergeFrom) . '15:' . Coordinate::stringFromColumnIndex($mergeTo) . '15';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'FCE4D6')
                        )
                    ]);
                    $column = Coordinate::stringFromColumnIndex($mergeFrom);
                    $sheet->getDelegate()->getColumnDimension($column)->setVisible(false);
                    $column = Coordinate::stringFromColumnIndex($mergeFrom + 1);
                    $sheet->getDelegate()->getColumnDimension($column)->setVisible(false);
                    $column = Coordinate::stringFromColumnIndex($mergeFrom + 2);
                    $sheet->getDelegate()->getColumnDimension($column)->setVisible(false);

                    break;
                case 'giam_tru_giacanh_tungthang':
                    $mergeFrom = $indexCal;
                    $mergeTo = ($indexCal + $this->totalCal);

                    $cellRangeMerge = Coordinate::stringFromColumnIndex($mergeFrom) . '15:' . Coordinate::stringFromColumnIndex($mergeTo) . '15';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'F4B084')
                        )
                    ]);
                    break;
                default:
                    $mergeFrom = $indexCal;
                    $mergeTo = ($indexCal + $this->totalCal);

                    $cellRangeMerge = Coordinate::stringFromColumnIndex($mergeFrom) . '15:' . Coordinate::stringFromColumnIndex($mergeTo) . '15';

                    $sheet->mergeCells($cellRangeMerge);

                    $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'FFD966')
                        )
                    ]);
                    break;
            }

            $richText = new RichText();
            $richText->createText($this->COLUMNS_VARIABLES['vi'][$vName]. PHP_EOL);
            $payable = $richText->createTextRun($this->COLUMNS_VARIABLES['en'][$vName]);
            $payable->getFont()->setItalic(true);
            $payable->getFont()->setBold(false);
            $sheet = $this->setValue($richText, 15, ($index1 + 10 + $from), $sheet);

            $this->COLUMNS_VARIABLE_POSITIONS[$vName] = ($index1 + 10 + $from);
            $statusOfPITFinalization = [
                '(Authorization)' => 'Ủy quyền',
                '(Not authorization)' => 'Không ủy quyền',
                '(Resign)' => 'Nghỉ việc'
            ];
            if (!in_array($vName, ['tong_thu_nhap_chiu_thue', 'tong_so_nguoi_phu_thuoc', 'ten_nguoi_phu_thuoc', 'pit_1', 'pit_1_2', 'pit_tong', 'tinh_trang_quyet_toan_thue_tncn_nam'])) {

                foreach ($this->calculationSheets as $calName => $calculationSheet) {

                    $sheet = $this->setValue($calName, 16, $indexCal, $sheet);

                    $sheet->getDelegate()->getStyle(Coordinate::stringFromColumnIndex($indexCal) . 16)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'E2EFDA')
                        )
                    ]);

                    $indexCal++;
                }

                $sheet = $this->setValue('Tổng', 16, $indexCal, $sheet);
                $sheet->getDelegate()->getStyle(Coordinate::stringFromColumnIndex($indexCal) . 16)->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'vertical'   => 'center',
                        'horizontal' => 'center'
                    ],
                    'fill' => array(
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => array('argb' => 'FFFF00')
                    )
                ]);
                $from += $this->totalCal;
            } elseif ($vName == 'tinh_trang_quyet_toan_thue_tncn_nam') {
                foreach ($statusOfPITFinalization as $enName => $viName) {
                    $sheet->getDelegate()->getStyle(Coordinate::stringFromColumnIndex($indexCal) . 16)->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => [
                            'vertical'   => 'center',
                            'horizontal' => 'center'
                        ],
                        'fill' => array(
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => array('argb' => 'ACB9CA')
                        )
                    ]);

                    $richText = new RichText();
                    $richText->createText($viName. PHP_EOL);
                    $payable = $richText->createTextRun($enName);
                    $payable->getFont()->setItalic(true);
                    $payable->getFont()->setBold(false);
                    $sheet = $this->setValue($richText, 16, $indexCal, $sheet);
                    $indexCal++;
                }
            }
            $indexCal++;
            $index1++;
        }

        //render employee values
        foreach ($this->clientEmployees as $index => $employee) {

            $row = $index + 17;
            $sheet = $this->setValue(($index + 1), $row, 2, $sheet);
            $sheet = $this->setValue($employee['code'], $row, 3, $sheet);
            $sheet = $this->setValue($employee['full_name'], $row, 4, $sheet);
            $sheet = $this->setValue($employee['mst_code'], $row, 5, $sheet);
            $sheet = $this->setValue($employee['id_card_number'], $row, 6, $sheet);
            $sheet = $this->setValue($employee['nationality'], $row, 7, $sheet);
            $sheet = $this->setValue($this->TYPES_OF_CONTRACT[$employee['type_of_employment_contract']] ?? "Chính thức", $row, 8, $sheet);
            $sheet = $this->setValue($employee['resident_status'] ? "Cư trú" : "Không cư trú", $row, 9, $sheet);
            $indexCal = 10;
            foreach ($this->columnVariables as $varColName => $columVariable) {
                if (!in_array($varColName, ['tong_thu_nhap_chiu_thue', 'tong_so_nguoi_phu_thuoc', 'ten_nguoi_phu_thuoc', 'pit_1', 'pit_1_2', 'pit_tong', 'tinh_trang_quyet_toan_thue_tncn_nam'])) {
                    $start = $indexCal;
                    foreach ($this->calculationSheets as $calName => $calculationSheet) {
                        switch ($varColName) {
                            case 'thu_nhap_tinhthue':
                                $value = $this->getValueThuNhapTinhThue($calName, $employee['code']);
                                break;
                            case 'pit_theo_bangluong_luytien':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance > 0 && $residentStatus == 1) ? $this->getPITValue($calName, $employee['code'], $varColName) : 0;
                                break;
                            case 'pit_theo_bangluong_khautru':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance == 0 && $residentStatus == 1) ? $this->getPITValue($calName, $employee['code'], $varColName) : 0;
                                break;
                            case 'pit_theo_bangluong_khautru_20':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance == 0 && $residentStatus == 0) ? $this->getPITValue($calName, $employee['code'], $varColName) : 0;
                                break;
                            case 'tnct_luy_tien':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance > 0 && $residentStatus == 1) ? $this->getValueVariableEmployee($calName, $employee['code'], $columVariable) : 0;
                                break;
                            case 'tnch_khautru':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance == 0 && $residentStatus == 1) ? $this->getValueVariableEmployee($calName, $employee['code'], $columVariable) : 0;
                                break;
                            case 'tnch_khautru_20':
                                $allowance = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang']) ?? 0;
                                $residentStatus = $this->checkResidentStatus($calName, $employee['code'], $employee['resident_status']);
                                $value = ($allowance == 0 && $residentStatus == 0) ? $this->getValueVariableEmployee($calName, $employee['code'], $columVariable) : 0;
                                break;
                            default:
                                $value = $this->getValueVariableEmployee($calName, $employee['code'], $columVariable);
                                break;
                        }

                        $sheet = $this->setValue($value, $row, $indexCal, $sheet);
                        $sheet = $this->setNumberFormat1Columns($varColName, $row, $indexCal, $sheet);

                        $indexCal++;
                    }
                    $startPos = $this->getCellIndex($row, $start);
                    $endPos = $this->getCellIndex($row, $indexCal - 1);
                    $total = "=SUM(".$startPos.":".$endPos.")";
                    $sheet = $this->setValue($total, $row, $indexCal, $sheet);
                } elseif ($varColName == 'tinh_trang_quyet_toan_thue_tncn_nam') {

                    foreach ($statusOfPITFinalization as $name) {
                        $value = '';

                        $sheet = $this->setValue(round($value), $row, $indexCal, $sheet);
                        $indexCal++;
                    }
                } elseif ($varColName == 'ten_nguoi_phu_thuoc') {
                    $value = '';
                    $sheet = $this->setValue(round($value), $row, $indexCal, $sheet);
                } elseif ($varColName == 'tong_thu_nhap_chiu_thue') {
                    $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnct_luy_tien'] + $this->totalCal) . $row;
                    $pos2 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru']  + $this->totalCal) . $row;
                    $pos3 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru_20']  + $this->totalCal) . $row;

                    $sheet = $this->setValue("={$pos1}+{$pos2}+{$pos3}", $row, $indexCal, $sheet);
                } elseif ($varColName == 'pit_tong') {
                    $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_luytien'] + $this->totalCal) . $row;
                    $pos2 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru'] + $this->totalCal) . $row;
                    $pos3 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru_20'] + $this->totalCal) . $row;

                    $sheet = $this->setValue("={$pos1}+{$pos2}+{$pos3}", $row, $indexCal, $sheet);
                }

                $sheet = $this->setNumberFormat1Columns($varColName, $row, $indexCal, $sheet);

                $indexCal++;
            }
        }

        // Total row
        $sheet = $this->renderTotalRow($sheet);

        $sheet = $this->renderTotalBlock($sheet);

        $totalList = count($this->clientEmployees);
        $totalCalVar = 10;
        $totalExtraVar = 8;
        $totalCol  = ($this->totalCal * $totalCalVar) + $totalCalVar + $this->fixedCols + $totalExtraVar;

        $sheet = $this->styleSheet1($sheet, $totalList, $totalCol);

        return $sheet;
    }

    protected function renderTotalRow($sheet)
    {
        $totalEmployee   = count($this->clientEmployees);
        $totalCal        = $this->totalCal;
        $columnVariables = $this->columnVariables;
        $indexTotal = 10;

        foreach ($columnVariables as $vName => $columVariable) {
            if (!in_array($vName, ['tong_thu_nhap_chiu_thue', 'tong_so_nguoi_phu_thuoc', 'ten_nguoi_phu_thuoc', 'pit_1', 'pit_1_2', 'pit_tong', 'tinh_trang_quyet_toan_thue_tncn_nam'])) {
                for ($i = 0; $i < ($totalCal + 1); $i++) {

                    $col = Coordinate::stringFromColumnIndex($indexTotal);

                    $totalSum = '=SUM(' . $col . '17:' . $col . ($totalEmployee + 16) . ')';

                    $sheet = $this->setValue($totalSum, $totalEmployee + 17, $indexTotal, $sheet);

                    if ($i == ($totalCal)) {
                        $this->totalRowData[$vName] = ['pos' => [($totalEmployee + 17), $indexTotal], 'value' => $totalSum];
                    }

                    $sheet = $this->setNumberFormat1Columns($vName, $totalEmployee + 17, $indexTotal, $sheet);

                    $indexTotal++;
                }
            } elseif (!in_array($vName, ['ten_nguoi_phu_thuoc', 'tinh_trang_quyet_toan_thue_tncn_nam'])) {

                $col = Coordinate::stringFromColumnIndex($indexTotal);

                $totalSum = '=SUM(' . $col . '17:' . $col . ($totalEmployee + 16) . ')';

                $sheet = $this->setValue($totalSum, $totalEmployee + 17, $indexTotal, $sheet);

                $this->totalRowData[$vName] = ['pos' => [($totalEmployee + 17), $indexTotal], 'value' => $totalSum];

                $sheet = $this->setNumberFormat1Columns($vName, $totalEmployee + 17, $indexTotal, $sheet);

                $indexTotal++;
            } else {
                $indexTotal++;
            }
        }

        $cellRange = 'B' . ($totalEmployee + 17) . ':' . Coordinate::stringFromColumnIndex($indexTotal + 1) . (17 + $totalEmployee);

        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => array('rgb' => 'FF0000'),
            ],
            'fill' => array(
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('argb' => 'EDEDED')
            ),
            'alignment' => [
                'vertical'   => 'center',
                'horizontal' => 'right'
            ],
        ]);
        $sheet->getDelegate()->getRowDimension(($totalEmployee + 17))->setRowHeight(30);
        return $sheet;
    }

    protected function getValueThuNhapTinhThue($calID, $employeeCode)
    {

        $value = $this->getValueVariableEmployee($calID, $employeeCode, $this->columnVariables['thu_nhap_tinhthue']);

        return $value > 0 ? $value : 0;
    }

    protected function getPITValue($calID, $employeeCode, $varColName)
    {
        $value = $this->getValueVariableEmployee($calID, $employeeCode, $this->columnVariables[$varColName]);

        return $value ?? 0;
    }

    protected function checkResidentStatus($calID, $employeeCode, $employeeResident)
    {
        if (!isset($this->clientEmployeeVariables[$calID][$employeeCode]["S_RESIDENT_STATUS"])) {
            return $employeeResident;
        }
        foreach ($this->clientEmployeeVariables[$calID][$employeeCode]["S_RESIDENT_STATUS"] as $item) {
            if ($item == 1) {
                return 1;
            }
        }
        return 0;
    }

    protected function getValueVariableEmployee($calID, $employeeCode, $variableName, $isSum = true)
    {
        if (!isset($this->clientEmployeeVariables[$calID][$employeeCode][$variableName])) return 0;

        $results = $this->clientEmployeeVariables[$calID][$employeeCode][$variableName];
        if ($isSum) {
            return array_sum($results);
        } else {
            return $results;
        }
    }

    protected function renderTotalBlock($sheet) {
        $totalEmployee   = count($this->clientEmployees);

        //Total number of employee in month
        $sheet = $this->setValue($totalEmployee, 5, 6, $sheet);
        //Individual have labor contract
        $posStart1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnct_luy_tien'] + $this->totalCal) . '17';
        $posEnd1   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnct_luy_tien'] + $this->totalCal) . ($totalEmployee + 16);
        $sheet = $this->setValue("=COUNTIF({$posStart1}:{$posEnd1},\">0\")", 5, 9, $sheet);

        //Total number of employee withheld tax
        //(Residents): I6
        $posStart1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_tong']) . '17';
        $posEnd1   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_tong']) . ($totalEmployee + 16);
        $sheet = $this->setValue("=COUNTIF({$posStart1}:{$posEnd1},\">0\")-I7", 6, 9, $sheet);
        //(Non residents): I7
        $posStart1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru_20'] + $this->totalCal) . '17';
        $posEnd1   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru_20'] + $this->totalCal) . ($totalEmployee + 16);
        $sheet = $this->setValue("=COUNTIF({$posStart1}:{$posEnd1},\">0\")", 7, 9, $sheet);
        $sheet = $this->setValue("=I6+I7", 6, 6, $sheet);

        //Total Tax income
        //(Residents): I8
        $posStart1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnct_luy_tien'] + $this->totalCal) . '17';
        $posEnd1   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnct_luy_tien'] + $this->totalCal) . ($totalEmployee + 16);
        $posStart2 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru'] + $this->totalCal) . '17';
        $posEnd2   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru'] + $this->totalCal) . ($totalEmployee + 16);
        $sheet = $this->setValue("=SUM({$posStart1}:{$posEnd1},{$posStart2}:{$posEnd2})", 8, 9, $sheet);

        //(Non residents): I9
        $posStart1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru_20'] + $this->totalCal) . '17';
        $posEnd1   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru_20'] + $this->totalCal) . ($totalEmployee + 16);
        $sheet = $this->setValue("=SUM({$posStart1}:{$posEnd1})", 9, 9, $sheet);
        $sheet = $this->setValue("=I8+I9",8, 6, $sheet);

        //Total taxable income paid to individuals subject to withholding tax
        //(Residents): I10
        $posStart1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_tong']) . '17';
        $posEnd1   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_tong']) . ($totalEmployee + 16);
        $posStart2 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnct_luy_tien'] + $this->totalCal) . '17';
        $posEnd2   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnct_luy_tien'] + $this->totalCal) . ($totalEmployee + 16);
        $posStart3 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru'] + $this->totalCal) . '17';
        $posEnd3   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru'] + $this->totalCal) . ($totalEmployee + 16);
        $sheet = $this->setValue("=SUMIF({$posStart1}:{$posEnd1},\">0\",{$posStart2}:{$posEnd2})+SUMIF({$posStart1}:{$posEnd1},\">0\",{$posStart3}:{$posEnd3})", 10, 9, $sheet);

        //(Non residents): I11
        $posStart1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru_20'] + $this->totalCal) . '17';
        $posEnd1   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru_20'] + $this->totalCal) . ($totalEmployee + 16);
        $posStart2 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru_20'] + $this->totalCal) . '17';
        $posEnd2   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru_20'] + $this->totalCal) . ($totalEmployee + 16);
        $sheet = $this->setValue("=SUMIF({$posStart1}:{$posEnd1},\">0\",{$posStart2}:{$posEnd2})", 11, 9, $sheet);
        $sheet = $this->setValue("=I10+I11",10, 6, $sheet);

        //Total PIT withheld in month/quarter
        //(Residents): I12
        $posStart1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_tong']) . '17';
        $posEnd1   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_tong']) . ($totalEmployee + 16);
        $sheet = $this->setValue("=SUMIF({$posStart1}:{$posEnd1},\">0\")-I13", 12, 9, $sheet);

        //(Non residents): I13
        $posStart1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru_20'] + $this->totalCal) . '17';
        $posEnd1   = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru_20'] + $this->totalCal) . ($totalEmployee + 16);
        $sheet = $this->setValue("=SUMIF({$posStart1}:{$posEnd1},\">0\",{$posStart1}:{$posEnd1})", 13, 9, $sheet);
        $sheet = $this->setValue("=I12+I13",12, 6, $sheet);
        return $sheet;
    }

    public function renderSheet2($sheet)
    {
        //company information
        $sheet = $this->setValue($this->companyName, 1, 1, $sheet);
        $sheet = $this->setValue("Summary Information about PIT Declaration", 2, 1, $sheet);
        $sheet = $this->setValue("Period:", 3, 1, $sheet);
        $sheet->setCellValue('C3', '=Summary!E2');

        $totalSheet = count($this->calculationSheets);
        $startTableRow = 11;
        $index1 = 0;
        $countRecord = 0;
        $blankRows = 1;
        foreach ($this->calculationSheets as $calName => $calculationSheet) {
            // The starting point of each block in months is:
            // start of table + count (employee which rendered each month ) + block_total * block_index
            $startMonthBlock = $startTableRow + $countRecord + (3 * $index1);

            //render employee value
            $countEmployeeInMonth = $blankRows;
            foreach($this->clientEmployees as $key => $employee) {
                $taxable_income_total = $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['tnct_luy_tien'])
                    + $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['tnch_khautru'])
                    + $this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['tnch_khautru_20']);

                $is_null = !$taxable_income_total
                        && !$this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['giam_tru_giacanh_tungthang'])
                        && !$this->getValueVariableEmployee($calName, $employee['code'], $this->columnVariables['bhbb_do_nld_tra']);

                if ($is_null) continue;

                $row = $startMonthBlock + $countEmployeeInMonth;
                $rowInSummarySheet = 17 + $key;

                $sheet = $this->setValue($countEmployeeInMonth, $row, 2, $sheet);
                $sheet = $this->setValue($employee['code'], $row, 3, $sheet);
                $sheet = $this->setValue($employee['full_name'], $row, 4, $sheet);

                //Residents status
                if ($this->checkResidentStatus($calName, $employee['code'], $employee['resident_status'])) {
                    $sheet = $this->setValue("Resident", $row, 5, $sheet);
                } else {
                    $sheet = $this->setValue("Non-resident", $row, 5, $sheet);
                }

                //Total Taxable Income paid to individuals
                $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnct_luy_tien'] + $index1) . $rowInSummarySheet;
                $pos2 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru']  + $index1) . $rowInSummarySheet;
                $pos3 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['tnch_khautru_20']  + $index1) . $rowInSummarySheet;
                $sheet->setCellValue('F' . ($startMonthBlock + $countEmployeeInMonth), "=Summary!{$pos1} + Summary!{$pos2} + Summary!{$pos3}");

                //Total Taxable Income paid to individuals subject to withholding tax
                $pos1 = "L" . ($startMonthBlock + $countEmployeeInMonth);
                $pos2 = "F" . ($startMonthBlock + $countEmployeeInMonth);
                $sheet->setCellValue('G' . ($startMonthBlock + $countEmployeeInMonth), "=IF({$pos1}>0,{$pos2},0)");

                //Family deduction
                $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['giam_tru_giacanh_tungthang']  + $index1) . $rowInSummarySheet;
                $sheet->setCellValue('H' . ($startMonthBlock + $countEmployeeInMonth), "=Summary!{$pos1}");

                //Total insurance
                $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['bhbb_do_nld_tra']  + $index1) . $rowInSummarySheet;
                $sheet->setCellValue('I' . ($startMonthBlock + $countEmployeeInMonth), "=Summary!{$pos1}");

                //Total
                $pos1 = "H" . ($startMonthBlock + $countEmployeeInMonth);
                $pos2 = "I" . ($startMonthBlock + $countEmployeeInMonth);
                $sheet->setCellValue('J' . ($startMonthBlock + $countEmployeeInMonth), "=SUM({$pos1}:{$pos2})");

                //Assessable income
                $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['thu_nhap_tinhthue']  + $index1) . $rowInSummarySheet;
                $sheet->setCellValue('K' . ($startMonthBlock + $countEmployeeInMonth), "=Summary!{$pos1}");

                //PIT withheld
                $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_luytien'] + $index1) . $rowInSummarySheet;
                $pos2 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru']  + $index1) . $rowInSummarySheet;
                $pos3 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['pit_theo_bangluong_khautru_20']  + $index1) . $rowInSummarySheet;
                $sheet->setCellValue('L' . ($startMonthBlock + $countEmployeeInMonth), "=Summary!{$pos1} + Summary!{$pos2} + Summary!{$pos3}");
                $countEmployeeInMonth++;
            }
            $countEmployeeInMonth += $blankRows;
            $countRecord += $countEmployeeInMonth;

            //Group month
            $cellRangeMerge = 'A' . ($startMonthBlock) . ':' . 'A' . ($startMonthBlock + $countEmployeeInMonth - 1);
            $sheet->mergeCells($cellRangeMerge);
            $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                'alignment' => [
                    'vertical'   => 'center',
                    'horizontal' => 'center'
                ]
            ]);
            $formatDate = Carbon::createFromFormat('n/Y', $calName)
                ->format('M-Y');
            $sheet->setCellValue('A' . $startMonthBlock, $formatDate);

            //Render total block
            $cellRangeMerge = 'A' . ($startMonthBlock + $countEmployeeInMonth) . ':' . 'C' . ($startMonthBlock + $countEmployeeInMonth + 2);
            $sheet->mergeCells($cellRangeMerge);
            $sheet->getDelegate()->getStyle($cellRangeMerge)->applyFromArray([
                'alignment' => [
                    'vertical'   => 'center',
                    'horizontal' => 'center'
                ]
            ]);
            $cellStyle = 'A' . ($startMonthBlock + $countEmployeeInMonth) . ':' . 'L' . ($startMonthBlock + $countEmployeeInMonth + 2);
            $sheet->getDelegate()->getStyle($cellStyle)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'italic' => true,
                    'color' => array('rgb' => '14762B'),
                ],
                'fill' => array(
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => array('argb' => 'D9D9D9')
                )
            ]);
            $cellStyle = 'D' . ($startMonthBlock + $countEmployeeInMonth + 2) . ':' . 'L' . ($startMonthBlock + $countEmployeeInMonth + 2);
            $sheet->getDelegate()->getStyle($cellStyle)->applyFromArray([
                'fill' => array(
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => array('argb' => '14762B')
                ),
                'font' => [
                    'color' => array('argb' => 'FFFFFF'),
                ],
            ]);

            $sheet->setCellValue('A' . ($startMonthBlock + $countEmployeeInMonth), 'Summary in ' . $formatDate);
            $sheet->setCellValue('D' . ($startMonthBlock + $countEmployeeInMonth), 'Resident');
            $sheet->setCellValue('D' . ($startMonthBlock + $countEmployeeInMonth + 1), 'Non-resident');
            $sheet->setCellValue('D' . ($startMonthBlock + $countEmployeeInMonth + 2) , 'Total');

            $posResidentStart = 'E' . $startMonthBlock;
            $posResidentEnd = 'E' . ($startMonthBlock + $countEmployeeInMonth - 1);
            $sheet->setCellValue('E' . ($startMonthBlock + $countEmployeeInMonth), "=COUNTIF({$posResidentStart}:{$posResidentEnd},\"Resident\")");
            $sheet->setCellValue('E' . ($startMonthBlock + $countEmployeeInMonth + 1), "=COUNTIF({$posResidentStart}:{$posResidentEnd},\"Non-resident\")");
            $pos1 = 'E' . ($startMonthBlock + $countEmployeeInMonth);
            $pos2 = 'E' . ($startMonthBlock + $countEmployeeInMonth + 1);
            $sheet->setCellValue('E' . ($startMonthBlock + $countEmployeeInMonth + 2), "={$pos1} + {$pos2}");

            foreach(['F', 'G', 'H', 'I', 'J', 'K', 'L'] as $column) {
                $posColStart = $column . $startMonthBlock;
                $posColEnd = $column . ($startMonthBlock + $countEmployeeInMonth - 1);
                $sheet->setCellValue($column . ($startMonthBlock + $countEmployeeInMonth), "=SUMIF({$posResidentStart}:{$posResidentEnd},\"Resident\", {$posColStart}:{$posColEnd})");
                $sheet->setCellValue($column . ($startMonthBlock + $countEmployeeInMonth + 1), "=SUMIF({$posResidentStart}:{$posResidentEnd},\"Non-resident\", {$posColStart}:{$posColEnd})");
                $sheet->setCellValue($column . ($startMonthBlock + $countEmployeeInMonth + 2), "=SUM({$posColStart}:{$posColEnd})");
            }
            $index1++;
        }

        //Render table total: TOTAL in
        $endTableRow = $startTableRow + $countRecord + ($totalSheet * 3);
        $cellRangeMerge = 'A' . ($endTableRow + 1) . ':' . 'D' . ($endTableRow + 1);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->setCellValue('A' . ($endTableRow + 1), '="TOTAL in "&Summary!E2');
        $sheet->setCellValue('E' . ($endTableRow + 1), '=Summary!F5');
        $sheet->setCellValue('F' . ($endTableRow + 1), '=Summary!F8');
        $sheet->setCellValue('G' . ($endTableRow + 1), '=Summary!F10');
        $sheet->setCellValue('L' . ($endTableRow + 1), '=Summary!F12');
        $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['giam_tru_giacanh_tungthang']  + $totalSheet) . (17 + count($this->clientEmployees));
        $sheet->setCellValue('H' . ($endTableRow + 1), "=Summary!{$pos1}");
        $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['bhbb_do_nld_tra']  + $totalSheet) . (17 + count($this->clientEmployees));
        $sheet->setCellValue('I' . ($endTableRow + 1), "=Summary!{$pos1}");
        $pos1 = Coordinate::stringFromColumnIndex($this->COLUMNS_VARIABLE_POSITIONS['thu_nhap_tinhthue']  + $totalSheet) . (17 + count($this->clientEmployees));
        $sheet->setCellValue('K' . ($endTableRow + 1), "=Summary!{$pos1}");
        $pos1 = "H" . ($endTableRow + 1);
        $pos2 = "I" . ($endTableRow + 1);
        $sheet->setCellValue('J' . ($endTableRow + 1), "={$pos1} + {$pos2}");

        //Render table total: Balance of PIT refund in last year
        $cellRangeMerge = 'A' . ($endTableRow + 2) . ':' . 'D' . ($endTableRow + 2);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->setCellValue('A' . ($endTableRow + 2), 'Balance of PIT refund in last year');
        $cellRangeMerge = 'E' . ($endTableRow + 2) . ':' . 'L' . ($endTableRow + 2);
        $sheet->mergeCells($cellRangeMerge);

        //Render table total: Total PIT Payable in
        $cellRangeMerge = 'A' . ($endTableRow + 3) . ':' . 'D' . ($endTableRow + 3);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->setCellValue('A' . ($endTableRow + 3), '="Total PIT Payable in "&Summary!E2');
        $cellRangeMerge = 'E' . ($endTableRow + 3) . ':' . 'L' . ($endTableRow + 3);
        $sheet->mergeCells($cellRangeMerge);
        $pos1 = "L" . ($endTableRow + 1);
        $pos2 = "E" . ($endTableRow + 2);
        $sheet->setCellValue('E' . ($endTableRow + 3), "=IF({$pos1}-{$pos2}>0,{$pos1}-{$pos2},0)");

        $sheetName = self::SHEET_NAME[2];
        $pos = $endTableRow + 3;
        $this->GLOBAL_DATA["total_pit_payable_in"]["source"] = [
            "sheet" => 2,
            "value" =>  "={$sheetName}!E{$pos}"
        ];

        //Render table total: After-deduction balance
        $cellRangeMerge = 'A' . ($endTableRow + 4) . ':' . 'D' . ($endTableRow + 4);
        $sheet->mergeCells($cellRangeMerge);
        $sheet->setCellValue('A' . ($endTableRow + 4), 'After-deduction balance');
        $cellRangeMerge = 'E' . ($endTableRow + 4) . ':' . 'L' . ($endTableRow + 4);
        $sheet->mergeCells($cellRangeMerge);
        $pos1 = "L" . ($endTableRow + 1);
        $pos2 = "E" . ($endTableRow + 2);
        $pos3 = "E" . ($endTableRow + 3);
        $sheet->setCellValue('E' . ($endTableRow + 4), "=IF({$pos3}=0,{$pos2}-{$pos1},0)");

        $sheet->setCellValue('A' . ($endTableRow + 5), "* After-deduction balance will be automatic deduct in next PIT period until it's expired");

        $sheet = $this->styleSheet2($sheet, $endTableRow);
        return $sheet;
    }


    public function renderSheet3($sheet)
    {
        if ($this->reportPayroll->duration_type == 'quy') {
            $sheet->setCellValue('B10', "[01] Kỳ tính thuế  (Tax period): Quý (Quarter) {$this->reportPayroll->quy_value} Năm (Year) {$this->reportPayroll->quy_year}");
        } elseif ($this->reportPayroll->duration_type == 'nam') {
            $sheet->setCellValue('B10', "[01] Kỳ tính thuế  (Tax period): Năm (Year) {$this->reportPayroll->quy_year}");
        } else {
            $monthly = explode('-', $this->reportPayroll->thang_value);
            $sheet->setCellValue('B10', "[01] Kỳ tính thuế  (Tax period): Tháng (Month) {$monthly[0]} Năm (Year) {$monthly[1]}");
        }

        if ($this->reportPayroll->loai_to_khai == 'chinh_thuc') {
            $sheet->setCellValue('B11', "[02] Lần đầu (First time):     [ X ]                          [03] Bổ sung lần thứ (Suplementary):     [    ]");
        } else {
            $sheet->setCellValue('B11', "[02] Lần đầu (First time):     [    ]                          [03] Bổ sung lần thứ (Suplementary):     [ X ]");
        }

        $sheet->setCellValue('E12', $this->companyName ?? "");
        $sheet->setCellValue('D16', $this->client->address ?? "");
        $sheet->setCellValue('D18', $this->client->address_province ?? "");
        $sheet->setCellValue('M18', $this->client->address_city ?? "");

        if ($this->client->pit_declaration_company_tax_code) {

            $companyLicenseNos = explode('-', $this->client->pit_declaration_company_tax_code);

            $codes = str_split($companyLicenseNos[0]);

            for ($i = 0; $i < count($codes); $i++) {
                $colIndex = Coordinate::stringFromColumnIndex(($i + 4));

                $sheet->setCellValue($colIndex . '14', $codes[$i]);
            }

            if (count($companyLicenseNos) == 2) {

                $codes = str_split($companyLicenseNos[1]);

                for ($i = 0; $i < count($codes); $i++) {

                    $colIndex = Coordinate::stringFromColumnIndex(($i + 15));

                    $sheet->setCellValue($colIndex . '14', $codes[$i]);
                }
            }
        }

        //Total number of employees
        $sheet->setCellValue('T33', '=Summary!F5');
        $sheet->setCellValue('T34', '=Summary!I5');

        //Total individual subject to withholding tax
        $sheet->setCellValue('T35', '=Summary!F6');
        $sheet->setCellValue('T36', '=Summary!I6');
        $sheet->setCellValue('T37', '=Summary!I7');

        //Total taxable income paid to individuals
        $sheet->setCellValue('T38', '=Summary!F8');
        $sheet->setCellValue('T39', '=Summary!I8');
        $sheet->setCellValue('T40', '=Summary!I9');

        //Total taxable income paid to individuals subject to withholding tax
        $sheet->setCellValue('T43', '=Summary!F10');
        $sheet->setCellValue('T44', '=Summary!I10');
        $sheet->setCellValue('T45', '=Summary!I11');

        //Total PIT withheld
        $sheet->setCellValue('T46', '=Summary!F12');
        $sheet->setCellValue('T47', '=Summary!I12');
        $sheet->setCellValue('T48', '=Summary!I13');

        $dt = Carbon::now('Asia/Ho_Chi_Minh');
        $sheet->setCellValue('M53', 'Ngày ' . ($dt->day < 10 ? '0' . $dt->day : $dt->day) . ' tháng '. $dt->month . ' năm ' . $dt->year);
        return $sheet;
    }

    public function renderSheet4($sheet)
    {
        $sheet->setCellValue('C7', $this->companyName);
        $sheet->setCellValue('H7', $this->client->pit_declaration_company_tax_code ?? "");
        $sheet->setCellValue('C8', $this->client->address);
        $sheet->setCellValue('C9', $this->client->address_province);
        $sheet->setCellValue('G9', $this->client->address_city);
        if (!empty($this->paymentOnBehalfServiceInformation)) {
            $provinceDistrict = ProvinceDistrict::where('id', $this->paymentOnBehalfServiceInformation->district)->first();
            $province = Province::where('id', $this->paymentOnBehalfServiceInformation->province)->first();
            $sheet->setCellValue('C10', $this->paymentOnBehalfServiceInformation->representative_on_behalf);
            $sheet->setCellValue('C11', $this->paymentOnBehalfServiceInformation->address);
            if(!empty($provinceDistrict) && !empty($provinceDistrict->district_name)){
                $sheet->setCellValue('C12', $provinceDistrict->district_name);
            }
            if(!empty($province) && !empty($province->province_name)){
                $sheet->setCellValue('G12', $province->province_name);
            }
            $sheet->setCellValue('C13', $this->paymentOnBehalfServiceInformation->bank_name);
            $sheet->setCellValue('I13', $this->paymentOnBehalfServiceInformation->account_number);
            $sheet->setCellValue('E43', $this->paymentOnBehalfServiceInformation->presenter_name_on_behalf);
        } else {
            $sheet->setCellValue('C13', $this->client->company_bank_name);
            $sheet->setCellValue('I13', $this->client->company_account_number);
            $sheet->setCellValue('E43', $this->client->presenter_name);
        }
        $sheet->setCellValueExplicit('C15', $this->client->pit_declaration_state_budget_account, 's');
        $sheet->setCellValue('C16', $this->client->pit_declaration_at_the_state_treasury);
        $sheet->setCellValue('G16', $this->client->pit_declaration_province);
        $controlling_agency = $this->client->pit_declaration_district_id ? $this->client->tax_office_district_name_with_code : $this->client->pit_declaration_the_controlling_agency;
        $sheet->setCellValue('C21', $controlling_agency);
        $sheet->setCellValue('C26', $this->setPITReportDurationWithText("00/", true));
        $sheet->setCellValue('D26', $this->setPITReportDurationWithText("Payment for PIT liabilities in ".$this->TIME_TEXT['en'][$this->reportPayroll->duration_type].' '));
        $this->GLOBAL_DATA["total_pit_payable_in"]["target_list"][] = [
            "sheet" => 4,
            "pos" => 'F26'
        ];
        $sheet->setCellValue('H26', $this->client->pit_declaration_chapter);
        $sheet->setCellValue('H26', $this->client->pit_declaration_chapter);
        $sheet->setCellValue('J26', $this->client->pit_declaration_head);

        return $sheet;
    }

    public function renderSheet5($sheet)
    {
        $sheet->setCellValue('C7', $this->companyName);
        $sheet->setCellValue('H7', $this->client->pit_declaration_company_tax_code ?? "");
        $sheet->setCellValue('C8', $this->client->address);
        $sheet->setCellValue('C9', $this->client->address_province);
        $sheet->setCellValue('G9', $this->client->address_city);

        if (!empty($this->paymentOnBehalfServiceInformation)) {
            $provinceDistrict = ProvinceDistrict::where('id', $this->paymentOnBehalfServiceInformation->district)->first();
            $province = Province::where('id', $this->paymentOnBehalfServiceInformation->province)->first();
            $sheet->setCellValue('C10', $this->paymentOnBehalfServiceInformation->representative_on_behalf);
            $sheet->setCellValue('C11', $this->paymentOnBehalfServiceInformation->address);
            if(!empty($provinceDistrict) && !empty($provinceDistrict->district_name)){
                $sheet->setCellValue('C12', $provinceDistrict->district_name);
            }
            if(!empty($province) && !empty($province->province_name)){
                $sheet->setCellValue('G12', $province->province_name);
            }
            $sheet->setCellValue('C13', $this->paymentOnBehalfServiceInformation->bank_name);
            $sheet->setCellValue('I13', $this->paymentOnBehalfServiceInformation->account_number);
            $sheet->setCellValue('E43', $this->paymentOnBehalfServiceInformation->presenter_name_on_behalf);
        } else {
            $sheet->setCellValue('C13', $this->client->company_bank_name);
            $sheet->setCellValue('I13', $this->client->company_account_number);
            $sheet->setCellValue('E43', $this->client->presenter_name);
        }
        $sheet->setCellValueExplicit('C15', $this->client->pit_declaration_state_budget_account, 's');
        $sheet->setCellValue('C16', $this->client->pit_declaration_at_the_state_treasury);
        $sheet->setCellValue('G16', $this->client->pit_declaration_province);
        $controlling_agency = $this->client->pit_declaration_district_id ? $this->client->tax_office_district_name_with_code : $this->client->pit_declaration_the_controlling_agency;
        $sheet->setCellValue('D21', $controlling_agency);

        $sheet->setCellValue('C26', $this->setPITReportDurationWithText("00/", true));
        $sheet->setCellValue('D26', $this->setPITReportDurationWithText("Nộp thuế TNCN từ tiền lương, tiền công ".$this->TIME_TEXT['vi'][$this->reportPayroll->duration_type].' '));
        $this->GLOBAL_DATA["total_pit_payable_in"]["target_list"][] = [
            "sheet" => 5,
            "pos" => 'F26'
        ];
        $sheet->setCellValue('H26', $this->client->pit_declaration_chapter);
        $sheet->setCellValue('J26', $this->client->pit_declaration_head);

        return $sheet;
    }
}
