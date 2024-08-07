<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\BeforeExport;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Maatwebsite\Excel\Excel;
use App\Models\ClientDepartment;
use App\Models\ClientPosition;
use App\Support\ImportHelper;
use Illuminate\Support\Facades\Storage;
use App\Models\Province;
use App\Models\ProvinceDistrict;
use App\Models\ProvinceWard;

class HistoryFileImport implements WithEvents, ShouldAutoSize
{
    use Exportable;

    private $data;
    private $folderName;
    private $type;
    private $activeLang;
    private $clientId;
    private $headersList;

    public function __construct($data, $folderName, $type = ImportHelper::CLIENT_EMPLOYEE, $clientId, $activeLang = 'en', $headersList)
    {
        $this->data = $data;
        $this->folderName = $folderName;
        $this->type = $type;
        $this->activeLang = $activeLang;
        $this->clientId = $clientId;
        $this->headersList = array_merge($headersList, ["ordinal_number" => "A"]);
    }

    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function (BeforeExport $event) {

                $extension = Excel::XLSX;

                switch ($this->type) {
                    case ImportHelper::CLIENT_EMPLOYEE:
                        $path = Storage::disk("local")->path("ClientEmployeeExportTemplate/{$this->type}_export_{$this->activeLang}.xlsx");
                        $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);
                        $sheet = $event->getWriter()->getSheetByIndex(0);
                        $sheet = $this->renderData($sheet, $this->data, $this->headersList, $this->type);
                        $sheetDepartment = $event->getWriter()->getSheetByIndex(1);
                        $sheetDepartment = $this->renderSheetDepartment($sheetDepartment, $this->clientId);
                        $sheetPosition = $event->getWriter()->getSheetByIndex(2);
                        $sheetPosition = $this->renderSheetPosition($sheetPosition, $this->clientId);
                        break;
                    case ImportHelper::SALARY_INFORMATION:
                    case ImportHelper::PAID_LEAVE:
                    case ImportHelper::CONTRACT_INFORMATION:
                    case ImportHelper::DEPENDANT_INFORMATION:
                        $path = Storage::disk("local")->path($this->folderName . "ExportTemplate/{$this->activeLang}.xlsx");
                        $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);
                        if ($this->type === ImportHelper::CONTRACT_INFORMATION) {
                            $sheetSalary = $event->getWriter()->getSheetByIndex(0);
                            $sheetSalary = $this->renderData($sheetSalary, $this->data, $this->headersList, $this->type);
                        } elseif ($this->type === ImportHelper::SALARY_INFORMATION) {
                            $sheetContract = $event->getWriter()->getSheetByIndex(0);
                            $sheetContract = $this->renderData($sheetContract, $this->data, $this->headersList, $this->type);
                        } else {
                            $sheetDependent = $event->getWriter()->getSheetByIndex(0);
                            $sheetDependent = $this->renderData($sheetDependent, $this->data, $this->headersList, $this->type);
                        }
                    case ImportHelper::AUTHORIZED_LEAVE:
                    case ImportHelper::UNAUTHORIZED_LEAVE:
                        $path = Storage::disk("local")->path("LeaveCategoryExportTemplate/{$this->type}_{$this->activeLang}.xlsx");
                        $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);
                        $sheet = $event->getWriter()->getSheetByIndex(0);
                        $sheet = $this->renderData($sheet, $this->data, $this->headersList, $this->type);
                        break;
                }
            }
        ];
    }

    public function styleSheet($sheet, $totalList)
    {
        $getStartRow = ImportHelper::getStartRow($this->type);
        $startRow = "A" . $getStartRow['data'];
        $column = ImportHelper::TOTAL_COLUMNS[$this->type];
        $col = Coordinate::stringFromColumnIndex($column);

        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR,
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ]
        ];

        $cellRange = $startRow . ':' . $col . (3 + $totalList);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styleArray)->getAlignment()->setWrapText(true);

        switch ($this->type) {
            case ImportHelper::CLIENT_EMPLOYEE:
                $sheet->getDelegate()->getStyle('A1')->applyFromArray([
                    'font' => array(
                        'name' => 'Arial',
                        'size' => 13,
                    ),
                ]);

                $sheet->getDelegate()->getStyle($startRow . ':' . $col . '3')->applyFromArray([
                    'font' => array(
                        'name' => 'Arial',
                        'bold' => true
                    )
                ]);
                break;
            case ImportHelper::DEPENDANT_INFORMATION:
                $sheet->getColumnDimension('C')->setWidth(22);
                $sheet->getColumnDimension('D')->setWidth(22);
                break;
        }

        return $sheet;
    }

    /**
     * Get list position and push to sheet position
     */
    public function renderSheetPosition($sheet)
    {
        $positions = ClientPosition::select('*')
            ->where('client_id', '=', $this->clientId)->get();

        if ($positions->count() > 0) {
            $row = 1;
            foreach ($positions as $position) {
                $row++;
                $sheet->setCellValue('A' . $row, $position->name);
                $sheet->setCellValue('B' . $row, $position->code);
            }
        }
        return $sheet;
    }

    /**
     * Get list department and push to sheet department
     */
    public function renderSheetDepartment($sheet)
    {
        $departments = ClientDepartment::select('*')
            ->where('client_id', '=', $this->clientId)->get();

        if ($departments->count() > 0) {
            $row = 1;
            foreach ($departments as $department) {
                $row++;
                $sheet->setCellValue('A' . $row, $department->department);
                $sheet->setCellValue('B' . $row, $department->code);
            }
        }
        return $sheet;
    }

    private function renderData($sheet, $data, $coordinate, $type)
    {
        $startRow = ImportHelper::getStartRow($type)['data'];
        foreach ($data as $rowData) {
            foreach ($coordinate as $field => $column) {
                if ($type === ImportHelper::CLIENT_EMPLOYEE) {
                    if ($field === 'birth_place_city_province' && $rowData['birth_place_city_province'] != null) {
                        $birthdayProvinceData = Province::select('province_name', 'province_code')->where('id', $rowData['birth_place_city_province'])->first();
                        $rowData[$field] = $birthdayProvinceData->province_name . "(" . $birthdayProvinceData->province_code . ")";
                    }

                    if ($field === 'birth_place_district' && $rowData['birth_place_district'] != null) {
                        $birthdayDistrictData = ProvinceDistrict::select('district_name', 'district_code')->where('id', $rowData['birth_place_district'])->first();
                        $rowData[$field] = $birthdayDistrictData->district_name . "(" . $birthdayDistrictData->district_code . ")";
                    }

                    if ($field === 'birth_place_wards' && $rowData['birth_place_wards'] != null) {
                        $birthdayWardData = ProvinceWard::select('ward_name', 'ward_code')->where('id', $rowData['birth_place_wards'])->first();
                        $rowData[$field] = $birthdayWardData->ward_name . "(" . $birthdayWardData->ward_code . ")";
                    }

                    if ($field === 'resident_city_province' && $rowData['resident_city_province'] != null) {
                        $residentProvinceData = Province::select('province_name', 'province_code')->where('id', $rowData['resident_city_province'])->first();
                        $rowData[$field] = $residentProvinceData->province_name . "(" . $residentProvinceData->province_code . ")";
                    }

                    if ($field === 'resident_district' && $rowData['resident_district'] != null) {
                        $residentDistrictData = ProvinceDistrict::select('district_name', 'district_code')->where('id', $rowData['resident_district'])->first();
                        $rowData[$field] = $residentDistrictData->district_name . "(" . $residentDistrictData->district_code . ")";
                    }

                    if ($field === 'resident_wards' && $rowData['resident_wards'] != null) {
                        $residentWardData = ProvinceWard::select('ward_name', 'ward_code')->where('id', $rowData['resident_wards'])->first();
                        $rowData[$field] = $residentWardData->ward_name . "(" . $residentWardData->ward_code . ")";
                    }

                    if ($field === 'contact_city_province' && $rowData['contact_city_province'] != null) {
                        $contactProvinceData = Province::select('*')->where('id', $rowData['contact_city_province'])->first();
                        $rowData[$field] = $contactProvinceData->province_name . "(" . $contactProvinceData->province_code . ")";
                    }

                    if ($field === 'contact_district' && $rowData['contact_district'] != null) {
                        $contactDistrictData = ProvinceDistrict::select('district_name', 'district_code')->where('id', $rowData['contact_district'])->first();
                        $rowData[$field] = $contactDistrictData->district_name . "(" . $contactDistrictData->district_code . ")";
                    }

                    if ($field === 'contact_wards' && $rowData['contact_wards'] != null) {
                        $contactWardData = ProvinceWard::select('ward_name', 'ward_code')->where('id', $rowData['contact_wards'])->first();
                        $rowData[$field] = $contactWardData->ward_name . "(" . $contactWardData->ward_code . ")";
                    }

                    if ($field === 'department' && $rowData['department'] != null) {
                        $ClientPosition = ClientDepartment::select('code')->find($rowData['department']);
                        $rowData[$field] = $ClientPosition->code;
                    }
                    if ($field === 'position') {
                        $ClientPosition = ClientPosition::select('code')->find($rowData['position']);
                        $rowData[$field] = $ClientPosition->code;
                    }

                    if ($field === 'blood_group' && $rowData['blood_group'] != null) {
                        $rowData[$field] = ImportHelper::BLOOD_GROUPS[$rowData[$field]];
                    }
                }
                $cell = $column . ($startRow);
                $value = $rowData[$field];
                $sheet->setCellValue($cell, $value);
            }
            $startRow++;
        }

        $sheet = $this->styleSheet($sheet, count($data));
        return $sheet;
    }
}
