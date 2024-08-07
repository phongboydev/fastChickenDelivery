<?php

namespace App\Exports;

use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;

use Maatwebsite\Excel\Events\BeforeExport;
use Maatwebsite\Excel\Events\AfterSheet;
use App\Models\ClientEmployee;
use App\Models\Client;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Carbon;

class ClientEmployeeStatusExport implements WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $client_id = null;
    protected $from_date = null;
    protected $to_date   = null;
    protected $templateExport;
    protected $pathFile;
    private $total_list = 0;
    private $totalRowPos = 0;
    private $employees = null;

    function __construct($clientId, $fromDate, $toDate, $templateExport, $pathFile)
    {
        $this->client_id = $clientId;
        $this->from_date = $fromDate;
        $this->to_date = $toDate;
        $this->templateExport = $templateExport;
        $this->pathFile = $pathFile;

        return $this;
    }

    public function registerEvents(): array
    {

        return [
            BeforeExport::class => function (BeforeExport $event) {

                if ($this->templateExport) {

                    $path = storage_path('app/' . $this->templateExport);

                    $pathInfo = pathinfo($path);

                    if (!in_array($pathInfo['extension'], ['xls', 'xlsx'])) {
                        return;
                    }

                    $extension = $pathInfo['extension'] == 'xls' ? Excel::XLS : Excel::XLSX;

                    $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);

                    $event->writer->getSheetByIndex(0);

                    $sheet = $event->getWriter()->getSheetByIndex(0);

                    $sheet = $this->setOtherData($sheet);

                    $client = Client::where('id', $this->client_id)->first();


                    $resultsDoing = ClientEmployee::select('*')->where('client_id', $this->client_id)
                        ->where("status", 'đang làm việc')
                        ->whereDate('official_contract_signing_date', '<=', $this->to_date)
                        ->with('contracts')->orderBy('full_name')->get();

                    $resultsOff = ClientEmployee::select('*')->where('client_id', $this->client_id)
                        ->whereBetween('quitted_at', [$this->from_date, $this->to_date])
                        ->with('contracts')->orderBy('full_name')->get();

                    $results = ($resultsDoing->concat($resultsOff))->sortBy('full_name');

                    $this->employees = $employees = $results->values()->all();

                    if (empty($employees)) return;

                    $this->total_list = count($employees);

                    $sheet = $this->styleSheet($sheet);

                    $this->totalRowPos = $this->total_list + 19;

                    $sheet = $this->setValue('Tổng', $this->totalRowPos, 2, $sheet);

                    $sheet = $this->getSexTotal($sheet);

                    // $sheet->setColumnFormat(array(
                    //     'U' => 'dd/mm/yyyy',
                    //     'V' => 'dd/mm/yyyy',
                    // ));

                    $variables = [
                        'col0' => 'index',
                        'col1' => 'full_name',
                        'col2' => 'social_insurance_number',
                        'col3' => 'date_of_birth',
                        'col4' => 'sex',
                        'col5' => 'id_card_number',
                        'col6' => 'position',
                        'col7' => 'role',
                        'col8' => 'education_level',
                        'col9' => 'education_level',
                        'col10' => 'education_level',
                        'col11' => 'salary',
                        'col19' => 'hop-dong-khong-xac-dinh-thoi-han',
                        'col20' => 'contract_signing_date',
                        'col21' => 'contract_end_date',
                        'col22' => 'thuviec_contract_signing_date',
                        'col23' => 'thuviec_contract_end_date',
                        'col24' => 'effective_date_of_social_insurance',
                        'col25' => 'quitted_at'
                    ];

                    if ($employees) {

                        $row = 19;

                        foreach ($employees as $index => $employee) {
                            for ($i = 0; $i < 26; $i++) {
                                $value = '';
                                $isDateValue = false;
                                $colName = 'col' . $i;

                                if (isset($variables['col' . $i]) && ($colName == 'col0')) {
                                    $value = $index + 1;
                                } elseif (isset($variables['col' . $i]) && ($colName == 'col4')) {
                                    $value = $employee['sex'] == 'male' ? 'Nam' : 'Nữ';
                                } elseif (isset($variables['col' . $i]) && ($colName == 'col7')) {
                                    $value = ($employee['role'] == 'manager') ? 'x' : '';
                                } elseif (isset($variables['col' . $i]) && ($colName == 'col8')) {
                                    $value = ($employee['education_level'] == 'university') ? 'x' : '';
                                } elseif (isset($variables['col' . $i]) && ($colName == 'col9')) {
                                    $value = (in_array($employee['education_level'], ['college', 'intermediate', 'elementary_occupations', 'vocational_training_regularly'])) ? 'x' : '';
                                } elseif (isset($variables['col' . $i]) && ($colName == 'col10')) {
                                    $value = ($employee['education_level'] == 'untrained') ? 'x' : '';
                                } elseif (isset($variables['col' . $i]) && ($colName == 'col19')) {

                                    $isDateValue = true;
                                    $type_of_employment_contract = $employee['type_of_employment_contract'];

                                    if ($employee['contracts'] && $type_of_employment_contract == 'khongthoihan') {
                                        $contract = collect($employee['contracts'])->sortByDesc('contract_signing_date')->first();

                                        if ($contract && $contract['contract_type'] == 'khong-xac-dinh-thoi-han')
                                            $value = $contract['contract_signing_date'];
                                    }
                                } elseif (isset($variables['col' . $i]) && ($colName == 'col20')) {
                                    $isDateValue = true;
                                    $type_of_employment_contract = $employee['type_of_employment_contract'];

                                    if ($employee['contracts'] && $type_of_employment_contract == 'chinhthuc') {
                                        $contract = collect($employee['contracts'])->sortByDesc('contract_signing_date')->first();

                                        if ($contract) {
                                            if ($contract['contract_type'] == 'co-thoi-han-lan-2') {
                                                $value = $contract['contract_signing_date'];
                                            } elseif ($contract['contract_type'] == 'co-thoi-han-lan-1') {
                                                $value = $contract['contract_signing_date'];
                                            }
                                        }
                                    }
                                } elseif (isset($variables['col' . $i]) && ($colName == 'col21')) {
                                    $isDateValue = true;
                                    $type_of_employment_contract = $employee['type_of_employment_contract'];

                                    if ($employee['contracts'] && $type_of_employment_contract == 'chinhthuc') {

                                        $contract = collect($employee['contracts'])->sortByDesc('contract_signing_date')->first();

                                        if ($contract) {
                                            if ($contract['contract_type'] == 'co-thoi-han-lan-2') {
                                                $value = $contract['contract_end_date'];
                                            } elseif ($contract['contract_type'] == 'co-thoi-han-lan-1') {
                                                $value = $contract['contract_end_date'];
                                            }
                                        }
                                    }
                                } elseif (isset($variables['col' . $i]) && ($colName == 'col22')) {
                                    $isDateValue = true;
                                    $type_of_employment_contract = $employee['type_of_employment_contract'];

                                    if ($employee['contracts'] && $type_of_employment_contract == 'thuviec') {
                                        $contract = collect($employee['contracts'])->sortByDesc('contract_signing_date')->first();

                                        if ($contract) {
                                            if ($contract['contract_type'] == 'thuviec') {
                                                $value = $contract['contract_signing_date'];
                                            }
                                        }
                                    }
                                } elseif (isset($variables['col' . $i]) && ($colName == 'col23')) {
                                    $isDateValue = true;
                                    $type_of_employment_contract = $employee['type_of_employment_contract'];

                                    if ($employee['contracts'] && $type_of_employment_contract == 'thuviec') {
                                        $contract = collect($employee['contracts'])->sortByDesc('contract_signing_date')->first();

                                        if ($contract) {
                                            if ($contract['contract_type'] == 'thuviec') {
                                                $value = $contract['contract_signing_date'];
                                            }
                                        }
                                    }
                                } elseif (isset($variables['col' . $i]) && (in_array($colName, ['col24', 'col3', 'col25']))) {
                                    $isDateValue = true;
                                    $value = $employee[$variables['col' . $i]];
                                } elseif (isset($variables['col' . $i])) {
                                    $value = $employee[$variables['col' . $i]];
                                }

                                if ($isDateValue) {
                                    $sheet = $this->setDateValue($value, $row, ($i + 1), $sheet);
                                } else {
                                    $sheet = $this->setValue($value, $row, ($i + 1), $sheet);
                                }
                            }

                            $row++;
                        }
                    }
                }
            },
        ];
    }

    public function setOtherData($sheet)
    {

        $sheet->mergeCells('A1:D1');
        $sheet->mergeCells('E8:K8');
        $sheet->mergeCells('C9:K9');
        $sheet->mergeCells('C10:D10');
        $sheet->mergeCells('H10:L10');
        $sheet->mergeCells('E11:G11');
        $sheet->mergeCells('F12:L12');

        $client = Client::select('*')->where('id', $this->client_id)->first();

        $variables = [
            '1x1'  => $client->company_name,
            '8x5' => $client->company_name,
            '9x3' => $client->address,
            '10x3' => $client->company_contact_phone,
            '10x8' => $client->company_contact_email,
            '11x5' => $client->company_license_no,
            '12x6' => $client->company_license_at,
        ];

        foreach ($variables as $key => $variable) {
            $pos = explode('x', $key);
            $sheet = $this->setValue($variable, $pos[0], $pos[1], $sheet);
        }

        $now = Carbon::now();
        $today = 'ngày ' . $now->format('d') . ' tháng ' . $now->format('m') . ' năm ' . $now->format('Y');
        $sheet = $this->setValue($today, 4, 20, $sheet);

        return $sheet;
    }

    public function setValue($value, $row, $col, $sheet)
    {
        $colIndex = Coordinate::stringFromColumnIndex($col);

        $pos = $colIndex . $row;

        $sheet->setCellValue($pos, $value);

        return $sheet;
    }

    public function setDateValue($value, $row, $col, $sheet)
    {
        if (!$value) return $sheet;

        $colIndex = Coordinate::stringFromColumnIndex($col);

        $pos = $colIndex . $row;

        $dateValue = date("d/m/Y", strtotime($value));

        $sheet->setCellValue($pos, $dateValue);

        return $sheet;
    }

    public function styleSheet($sheet)
    {

        $col = Coordinate::stringFromColumnIndex(27);

        $cellRange = 'A19:' . $col . ($this->total_list + 19);

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

    public function getSexTotal($sheet)
    {
        $employeeCollect = collect($this->employees);

        $maleCollect = $employeeCollect->filter(function ($value, $key) {
            return $value['sex'] == 'male';
        });

        $femaleCollect = $employeeCollect->filter(function ($value, $key) {
            return $value['sex'] == 'female';
        });

        $result = $maleCollect->count() . ' Nam / ' . $femaleCollect->count() . ' Nữ';

        return $this->setValue($result, $this->totalRowPos, 5, $sheet);
    }
}
