<?php

namespace App\Exports;

use App\Models\WorkTimeRegisterPeriod;
use App\Support\Constant;
use App\Support\FormatHelper;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\BeforeExport;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Maatwebsite\Excel\Excel;
use App\Models\ClientEmployee;
use App\Models\ClientDepartment;
use App\Models\ClientPosition;
use App\Models\Province;
use App\Models\ProvinceDistrict;
use App\Models\ProvinceWard;
use App\Support\ImportHelper;
use Illuminate\Support\Facades\Storage;

class ClientEmployeeExportMultiSheet implements WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $client_id = null;
    protected $status = null;
    protected $group_ids = [];
    protected $ids = [];
    protected $type;
    protected $folderName;

    function __construct($clientId, $status, $group_ids = [], $ids = [], $type = ImportHelper::CLIENT_EMPLOYEE, $folderName)
    {
        $this->client_id = $clientId;
        $this->status = $status;
        $this->group_ids = $group_ids;
        $this->ids = $ids;
        $this->type = $type;
        $this->folderName = $folderName;
    }

    public function registerEvents(): array
    {

        return [
            BeforeExport::class => function (BeforeExport $event) {

                $activeLang = auth()->user()->prefered_language ? auth()->user()->prefered_language : app()->getLocale();
                $extension = Excel::XLSX;

                $employees = ClientEmployee::select('*')
                    ->where('client_id', '=', $this->client_id)->orderBy('code', 'ASC');

                if ($this->status) {
                    $employees = $employees->where('status', $this->status);
                }
                if (!empty($this->group_ids)) {
                    $user = Auth::user();
                    $listClientEmployeeId = $user->getListClientEmployeeByGroupIds($user, $this->group_ids);
                    if (!empty($listClientEmployeeId)) {
                        $employees = $employees->whereIn('id', $listClientEmployeeId);
                    }
                } else {
                    if ($this->ids) {
                        $employees = $employees->whereIn('id', $this->ids);
                    }
                }

                $employees = $employees->orderBy('full_name', 'ASC')->get();

                switch ($this->type) {
                    case ImportHelper::CLIENT_EMPLOYEE:
                        $employees->load('user');
                        $path = Storage::disk("local")->path("ClientEmployeeExportTemplate/{$this->type}_export_{$activeLang}.xlsx");
                        $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);
                        $sheet1 = $event->getWriter()->getSheetByIndex(0);
                        $sheet1 = $this->renderSheetBasicInfo($sheet1, $employees, $activeLang);
                        $sheetDepartment = $event->getWriter()->getSheetByIndex(1);
                        $sheetDepartment = $this->renderSheetDepartment($sheetDepartment);
                        $sheetPosition = $event->getWriter()->getSheetByIndex(2);
                        $sheetPosition = $this->renderSheetPosition($sheetPosition);
                        break;
                    case ImportHelper::SALARY_INFORMATION:
                        $employees->load('currentSalary');
                        $path = Storage::disk("local")->path("SalaryInformationExportTemplate/{$activeLang}.xlsx");
                        $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);
                        $sheetSalary = $event->getWriter()->getSheetByIndex(0);
                        $sheetSalary = $this->renderSheetSalaryInformation($sheetSalary, $employees, $activeLang);
                        break;
                    case ImportHelper::CONTRACT_INFORMATION:
                    case ImportHelper::DEPENDANT_INFORMATION:
                        $path = Storage::disk("local")->path($this->folderName . "ExportTemplate/{$activeLang}.xlsx");
                        $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);
                        if ($this->type === ImportHelper::CONTRACT_INFORMATION) {
                            $employees->load('contracts');
                            $sheetContract = $event->getWriter()->getSheetByIndex(0);
                            $sheetContract = $this->renderSheet2($sheetContract, $employees);
                        } else {
                            $sheetDependent = $event->getWriter()->getSheetByIndex(0);
                            return $this->renderSheetDependentInformation($sheetDependent, $employees);
                        }
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
            ],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'font' => ['color' => ['rgb' => '000000', 'name' => 'Arial', 'size' => 11, 'italic' => false]]
        ];

        $cellRange = $startRow . ':' . $col . ($getStartRow['header'] + $totalList);
        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styleArray)->getAlignment()->setWrapText(true);

        switch ($this->type) {
            case ImportHelper::CLIENT_EMPLOYEE:
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
            case ImportHelper::SALARY_INFORMATION:
                $sheet->getColumnDimension('C')->setAutoSize(true);
                break;
        }

        return $sheet;
    }

    public function renderSheetBasicInfo($sheet, $employees, $activeLang)
    {
        // Translate
        app()->setlocale($activeLang);

        $sheet->getComment('AB2')
            ->setWidth(240)->setHeight(120)
            ->getText()->createTextRun(__('excel.tax_comment'));

        $sheet->getComment('AT2')
            ->setWidth(280)->setHeight(180)
            ->getText()->createTextRun(__('excel.insurance_comment'));

        if ($employees->isNotEmpty()) {

            $clientEmployeeData = [];

            // Check permissions manage-payroll & manage-employee-payroll
            $permissions_manage_payroll = true;
            // if user not internal then user have permission manage-payroll, manage-employee-payroll
            if (!auth()->user()->is_internal && auth()->user()->getRole() != Constant::ROLE_CLIENT_MANAGER && !auth()->user()->hasDirectPermission('manage-payroll') && auth()->user()->getRole() != Constant::ROLE_CLIENT_MANAGER && !auth()->user()->hasDirectPermission('manage-employee-payroll')) {
                $permissions_manage_payroll = false;
            }

            foreach ($employees as $cIndex => $item) {

                $allow_login = $item->user_id ? 1 : 0;
                $username = $item->user ? $item->user['email'] : '';
                $email = $item->user ? str_replace($item->client_id . '_', '', $item->user['username']) : '';


                $transformRole = function ($value) {
                    $statuses = [
                        "staff" => 1,
                        "leader" => 2,
                        "accountant" => 3,
                        "hr" => 4,
                        "manager" => 5
                    ];
                    return isset($statuses[$value]) ? $statuses[$value] : $statuses['staff'];
                };

                $etmTotalTime = WorkTimeRegisterPeriod::getEstimatedTotalTime($item->id, 'leave_request', 'authorized_leave', 'year_leave');

                $birthdayProvinceData = Province::select('*')->where('id', $item->birth_place_city_province)->first();
                $birthdayDistrictData = ProvinceDistrict::select('*')->where('id', $item->birth_place_district)->first();
                $birthdayWardData     = ProvinceWard::select('*')->where('id', $item->birth_place_wards)->first();

                $residentProvinceData = Province::select('*')->where('id', $item->resident_city_province)->first();
                $residentDistrictData = ProvinceDistrict::select('*')->where('id', $item->resident_district)->first();
                $residentWardData     = ProvinceWard::select('*')->where('id', $item->resident_wards)->first();

                $contactProvinceData = Province::select('*')->where('id', $item->contact_city_province)->first();
                $contactDistrictData = ProvinceDistrict::select('*')->where('id', $item->contact_district)->first();
                $contactWardData     = ProvinceWard::select('*')->where('id', $item->contact_wards)->first();

                $clientEmployeeDataTmp = [
                    'no' => $cIndex + 1,
                    'code' => $item->code,
                    'full_name' => $item->full_name,
                    'sex' => $item->sex,
                    'date_of_birth' => FormatHelper::date($item->date_of_birth, 'Y-m-d'),
                    'nationality' => $item->nationality,
                    'nation' => $item->nation,
                    'religion' => $item->religion,
                    'marital_status' => $item->marital_status,
                    'contact_phone_number' => $item->contact_phone_number,
                    'id_card_number' => $item->id_card_number,
                    'is_card_issue_date' => FormatHelper::date($item->is_card_issue_date, 'Y-m-d'),
                    'id_card_issue_place' => $item->id_card_issue_place,
                    'birth_place_city_province' => $birthdayProvinceData ? "$birthdayProvinceData->province_name($birthdayProvinceData->province_code)"  : '',
                    'birth_place_district' => $birthdayDistrictData ? "$birthdayDistrictData->district_name($birthdayDistrictData->district_code)" : '',
                    'birth_place_wards' => $birthdayWardData ? "$birthdayWardData->ward_name($birthdayWardData->ward_code)" : '',
                    'birth_place_address' => $item->birth_place_address,
                    'birth_place_street' => $item->birth_place_street,
                    'resident_city_province' => $residentProvinceData ? "$residentProvinceData->province_name($residentProvinceData->province_code)" : '',
                    'resident_district' => $residentDistrictData ? "$residentDistrictData->district_name($residentDistrictData->district_code)" : '',
                    'resident_wards' => $residentWardData ? "$residentWardData->ward_name($residentWardData->ward_code)" : '',
                    'resident_address' => $item->resident_address,
                    'resident_street' => $item->resident_street,
                    'contact_city_province' => $contactProvinceData ? "$contactProvinceData->province_name($contactProvinceData->province_code)" : '',
                    'contact_district' => $contactDistrictData ? "$contactDistrictData->district_name($contactDistrictData->district_code)" : '',
                    'contact_wards' => $contactWardData ? "$contactWardData->ward_name($contactWardData->ward_code)" : '',
                    'contact_address' => $item->contact_address,
                    'contact_street' => $item->contact_street,
                    'is_tax_applicable'     => $item->is_tax_applicable,
                    'mst_code'     => $item->mst_code,
                    'number_of_dependents'     => $item->number_of_dependents,
                    'title' => $item->title,
                    'position' => $item->client_position_code,
                    'department' => $item->client_department_code,
                    'workplace' => $item->workplace,
                    'date_of_entry' => $item->date_of_entry,
                    'education_level' => in_array($item->education_level, ImportHelper::EDUCATION_LEVEL) ? $item->education_level : null,
                    'educational_qualification' => $item->educational_qualification,
                    'major' => $item->major,
                    'certificate_1' => $item->certificate_1,
                    'certificate_2' => $item->certificate_2,
                    'certificate_3' => $item->certificate_3,
                    'certificate_4' => $item->certificate_4,
                    'certificate_5' => $item->certificate_5,
                    'certificate_6' => $item->certificate_6,
                    'year_of_graduation' => $item->year_of_graduation,
                    'blood_group' => $item->blood_group ? array_search($item->blood_group, ImportHelper::BLOOD_GROUPS) : null,
                    'emergency_contact_name' => $item->emergency_contact_name,
                    'emergency_contact_relationship' => $item->emergency_contact_relationship,
                    'emergency_contact_phone' => $item->emergency_contact_phone,
                    'spouse_working_at_company' => $item->spouse_working_at_company,
                    'commuting_transportation' => $item->commuting_transportation,
                    'vehicle_license_plate' => $item->vehicle_license_plate,
                    'locker_number' => $item->locker_number,
                    'year_paid_leave_count'     => $item->year_paid_leave_count,
                    'bank_account' => $item->bank_account,
                    'bank_account_number' => $item->bank_account_number,
                    'bank_code' => $item->bank_code,
                    'bank_name' => $item->bank_name,
                    'bank_branch' => $item->bank_branch,
                    'is_insurance_applicable'     => $item->is_insurance_applicable,
                    'social_insurance_number'     => $item->social_insurance_number,
                    'effective_date_of_social_insurance'     => FormatHelper::date($item->effective_date_of_social_insurance, 'Y-m-d'),
                    'salary_for_social_insurance_payment'     => $item->salary_for_social_insurance_payment,
                    'medical_care_hospital_name'     => $item->medical_care_hospital_name,
                    'medical_care_hospital_code'     => $item->medical_care_hospital_code,
                    'role' => $transformRole($item->role),
                    'allow_login'     => $allow_login,
                    'username' => $username,
                    'email' => $email,
                    'overwrite' => 1
                ];

                if (!$permissions_manage_payroll) {
                    $clientEmployeeDataTmp['salary_for_social_insurance_payment'] = "N/A";
                }

                $clientEmployeeData[] = $clientEmployeeDataTmp;
            }

            $startRow = 4;

            foreach ($clientEmployeeData as $cIndex => $cRow) {

                $col = 1;

                foreach ($cRow as $value) {

                    $colIndex = Coordinate::stringFromColumnIndex($col);

                    $sheet->setCellValue($colIndex . ($startRow + $cIndex), $value);

                    $col++;
                }
            }
        }

        $sheet = $this->styleSheet($sheet, $employees->count());

        return $sheet;
    }

    public function renderSheetSalaryInformation($sheet, $employees, $activeLang)
    {
        // Translate
        app()->setlocale($activeLang);

        if ($employees->isNotEmpty()) {

            $clientEmployeeData = [];

            // Check permissions manage-payroll & manage-employee-payroll
            $permissions_manage_payroll = true;
            // if user not internal then user have permission manage-payroll, manage-employee-payroll
            if (!auth()->user()->is_internal && auth()->user()->getRole() != Constant::ROLE_CLIENT_MANAGER && !auth()->user()->hasDirectPermission('manage-payroll') && auth()->user()->getRole() != Constant::ROLE_CLIENT_MANAGER && !auth()->user()->hasDirectPermission('manage-employee-payroll')) {
                $permissions_manage_payroll = false;
            }

            foreach ($employees as $cIndex => $item) {

                $clientEmployeeDataTmp = [
                    'no' => $cIndex + 1,
                    'code' => $item->code,
                    'full_name' => $item->full_name,
                    'currency' => $item->currency,
                    'old_salary' => optional($item->currentSalary)->new_salary ?? $item->salary,
                    'new_salary' => '',
                    'old_allowance_for_responsibilities' => optional($item->currentSalary)->new_allowance_for_responsibilities ?? $item->allowance_for_responsibilities,
                    'new_allowance_for_responsibilities' => '',
                    'old_fixed_allowance' => optional($item->currentSalary)->new_fixed_allowance ?? $item->fixed_allowance,
                    'new_fixed_allowance' => '',
                    'effective_date' => FormatHelper::date(optional($item->currentSalary)->start_date, 'Y-m-d') ?? "N/A"
                ];

                if (!$permissions_manage_payroll) {
                    $clientEmployeeDataTmp['old_salary'] = "N/A";
                    $clientEmployeeDataTmp['old_allowance_for_responsibilities'] = "N/A";
                    $clientEmployeeDataTmp['old_fixed_allowance'] = "N/A";
                }

                $clientEmployeeData[] = $clientEmployeeDataTmp;
            }

            $startRow = 3;

            foreach ($clientEmployeeData as $cIndex => $cRow) {

                $col = 1;

                foreach ($cRow as $value) {

                    $colIndex = Coordinate::stringFromColumnIndex($col);

                    $sheet->setCellValue($colIndex . ($startRow + $cIndex), $value);

                    $col++;
                }
            }
        }

        $sheet = $this->styleSheet($sheet, $employees->count());

        return $sheet;
    }

    public function renderSheet2($sheet, $employees)
    {
        if ($employees->isNotEmpty()) {

            $transformStatus = function ($value) {
                $statuses = [
                    "đang làm việc" => 1,
                    "nghỉ không lương" => 2,
                    "nghỉ thai sản" => 3,
                    "nghỉ việc" => 4,
                ];
                return isset($statuses[$value]) ? $statuses[$value] : $statuses["đang làm việc"];
            };

            $transformContractType = function ($value) {

                switch ($value) {
                    case 'khong-xac-dinh-thoi-han':
                    case "khongthoihan":
                        return 1;
                        break;
                    case 'co-thoi-han-lan-1':
                    case 'co-thoi-han-lan-2':
                    case "chinhthuc":
                        return 2;
                        break;
                    case "thoivu":
                        return 3;
                        break;
                    case "thuviec":
                        return 4;
                        break;

                    default:
                        return $value;
                }
            };

            $clientEmployeeData = [];

            foreach ($employees as $cIndex => $item) {

                $contracts = collect($item->contracts);

                $thuviec = $contracts->firstWhere('contract_type', 'thuviec');
                $coThoiHanLan1 = $contracts->firstWhere('contract_type', 'co-thoi-han-lan-1');
                $coThoiHanLan2 = $contracts->firstWhere('contract_type', 'co-thoi-han-lan-2');
                $voThoiHan = $contracts->firstWhere('contract_type', 'khong-xac-dinh-thoi-han');
                $khac = $contracts->firstWhere('contract_type', 'khac');

                $resignation_date = $item->status == 'nghỉ việc' ? FormatHelper::date($item->updated_at) : '';

                $clientEmployeeDataTmp = [
                    'no' => $cIndex + 1,
                    'code' => $item->code,
                    'full_name' => $item->full_name,
                    'status' => $transformStatus($item->status),
                    'type_of_employment_contract' => $transformContractType($item->type_of_employment_contract),
                    'contract_no_1' => $thuviec ? $thuviec['contract_code'] : '',
                    'probation_start_date' => $thuviec ? FormatHelper::date($thuviec['contract_signing_date'], 'Y-m-d') : '',
                    'probation_end_date' => $thuviec ? FormatHelper::date($thuviec['contract_end_date'], 'Y-m-d') : '',
                    'contract_no_2' => $coThoiHanLan1 ? $coThoiHanLan1['contract_code'] : '',
                    'definite_term_contract_first_time_start_date' => $coThoiHanLan1 ? FormatHelper::date($coThoiHanLan1['contract_signing_date'], 'Y-m-d') : '',
                    'definite_term_contract_first_time_end_date' => $coThoiHanLan1 ? FormatHelper::date($coThoiHanLan1['contract_end_date'], 'Y-m-d') : '',
                    'contract_no_3' => $coThoiHanLan2 ? $coThoiHanLan2['contract_code'] : '',
                    'definite_term_contract_second_time_start_date' => $coThoiHanLan2 ? FormatHelper::date($coThoiHanLan2['contract_signing_date'], 'Y-m-d') : '',
                    'definite_term_contract_second_time_end_date' => $coThoiHanLan2 ? FormatHelper::date($coThoiHanLan2['contract_end_date'], 'Y-m-d') : '',
                    'contract_no_4' => $voThoiHan ? $voThoiHan['contract_code'] : '',
                    'indefinite_term_contract_start_date' => $voThoiHan ? FormatHelper::date($voThoiHan['contract_signing_date'], 'Y-m-d') : '',
                    'contract_no_5' => $khac ? $khac['contract_code'] : '',
                    'other_term_contract_start_date' => $khac ? FormatHelper::date($khac['contract_signing_date'], 'Y-m-d') : '',
                    'resignation_date' => $resignation_date,
                ];

                $clientEmployeeData[] = $clientEmployeeDataTmp;
            }

            $startRow = 4;

            foreach ($clientEmployeeData as $cIndex => $cRow) {

                $col = 1;

                foreach ($cRow as $value) {

                    $colIndex = Coordinate::stringFromColumnIndex($col);

                    $sheet->setCellValue($colIndex . ($startRow + $cIndex), $value);

                    $col++;
                }
            }
        }

        $sheet = $this->styleSheet($sheet, $employees->count(), 20);

        return $sheet;
    }

    public function renderSheet3($sheet, $employees)
    {
        if ($employees->isNotEmpty()) {

            $clientEmployeeData = [];

            foreach ($employees as $cIndex => $item) {

                $clientEmployeeDataTmp = [
                    'no' => $cIndex + 1,
                    'code' => $item->code,
                    'full_name' => $item->full_name,
                    'salary_for_social_insurance_payment' => $item->salary_for_social_insurance_payment,
                    'is_insurance_applicable' => $item->is_insurance_applicable,
                    'social_insurance_number' => $item->social_insurance_number,
                    'medical_care_hospital_name' => $item->medical_care_hospital_name,
                    'medical_care_hospital_code' => $item->medical_care_hospital_code,
                    'number_of_dependents' => $item->number_of_dependents,
                    'is_tax_applicable' => $item->is_tax_applicable,
                    'mst_code' => $item->mst_code,
                ];

                $clientEmployeeData[] = $clientEmployeeDataTmp;
            }

            $startRow = 4;

            foreach ($clientEmployeeData as $cIndex => $cRow) {

                $col = 1;

                foreach ($cRow as $value) {

                    $colIndex = Coordinate::stringFromColumnIndex($col);

                    $sheet->setCellValue($colIndex . ($startRow + $cIndex), $value);

                    $col++;
                }
            }
        }

        $sheet = $this->styleSheet($sheet, $employees->count(), 11);

        return $sheet;
    }


    /**
     * Get list position and push to sheet position
     */
    public function renderSheetPosition($sheet)
    {
        $positions = ClientPosition::select('*')
            ->where('client_id', '=', $this->client_id)->get();

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
            ->where('client_id', '=', $this->client_id)->get();

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

    /**
     * Get list dependent information of staff
     */

    private function renderSheetDependentInformation($sheet, $employees)
    {
        if ($employees->isNotEmpty()) {
            $dependentsInformation = [];
            $totalDependent = 0;
            $row = 3;
            $Index = 1;
            foreach ($employees as $item) {
                $dependentsInformation = collect($item->dependentsInformation);
                foreach ($dependentsInformation as $dependent) {
                    $totalDependent++;
                    $sheet->setCellValue('A' . $row, $Index);
                    $sheet->setCellValue('B' . $row, $item->code);
                    $sheet->setCellValue('C' . $row, $item->full_name);
                    $sheet->setCellValue('D' . $row, $dependent->name_dependents);
                    $sheet->setCellValue('E' . $row, $dependent->tax_code);
                    $sheet->setCellValue('F' . $row, $dependent->relationship_code);
                    $sheet->setCellValue('G' . $row, $dependent->from_date);
                    $sheet->setCellValue('H' . $row, $dependent->to_date);
                    $Index++;
                    $row++;
                }
            }
            $sheet = $this->styleSheet($sheet, $totalDependent);
        }
        return $sheet;
    }
}
