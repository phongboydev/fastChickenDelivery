<?php

namespace App\GraphQL\Mutations;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use App\Models\ContractTemplate;
use App\Models\Contract;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeCustomVariable;
use App\Models\ClientCustomVariable;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Imports\ContractTemplateParseImport;
use App\Jobs\CreateClientEmployeeContract;
use App\Models\Province;
use App\Models\ProvinceDistrict;
use App\Models\ProvinceWard;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Rules\ContractNameRule;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\CustomException;

class ContractTemplateMutator
{
    public function generateContract($root, array $args)
    {
        $contract = Contract::select('*')->where('id', $args['contract_id'])->first();

        if (!$contract) return 'fail';

        $variables = json_decode($contract->contract_variables, true);

        $this->exportFile($contract, $args['template_id'], $variables);

        return 'ok';
    }

    public function generateEmployeesContract($root, array $args)
    {
        $templateId = $args['template_id'];
        $clientId = $args['client_id'];
        $data = $args['data'];

        $validator = Validator::make($args, [
            'data.*.contract_no' => [new ContractNameRule],
        ]);

        if ($validator && $validator->fails()) {
            throw new CustomException(
                __('contract.duplicate_number'),
                'ValidationException',
                'WR0002',
                [],
                "warning",
                "contract"
            );
        }

        $contractTemplate = ContractTemplate::find($templateId);
        $clientCustomVariables = ClientCustomVariable::select('*')->where('client_id', $clientId)->get()->toArray();
        if ($contractTemplate) {
            foreach ($data as $item) {
                $clientEmployeeId = $item['client_employee_id'];
                $employee = ClientEmployee::where(['client_id' => $clientId, 'id' => $clientEmployeeId])->first();
                $variableSelfEntry = $item['contract_variables'] ? json_decode($item['contract_variables'], true) : [];
                $allowVariables = $contractTemplate->allow_variables ? json_decode($contractTemplate->allow_variables, true) : [];
                $customVariables = $this->getEmployeeVariables($employee, $variableSelfEntry, $allowVariables, $clientCustomVariables);
                $contractNo = $this->getCodeNameContract($item['contract_no'], $customVariables);

                $contractType = 'hop_dong_nhan_vien';
                $note = $item['note'];

                $contract = Contract::create([
                    'client_id' => $clientId,
                    'name' => $contractNo,
                    'contract_no' => $contractNo,
                    'contract_type' => $contractType,
                    'contract_variables' => json_encode($customVariables),
                    'note' => $note,
                    'client_employee_id' => $clientEmployeeId,
                    'salary_history_id' => $item['salary_history_id']
                ]);

                CreateClientEmployeeContract::dispatch(
                    $employee,
                    compact('variableSelfEntry', 'clientCustomVariables'),
                    $customVariables,
                    $contract,
                    [
                        'client_id' => $clientId,
                        'contract_no' => $contractNo,
                        'contract_type' => $contractType,
                        'template_id' => $templateId,
                        'note' => $note
                    ]
                );
            }
            return true;
        }
        return false;
    }

    public function generateEmployeesContractFromFile($root, array $args)
    {
        $clientId = $args['client_id'];
        $variableForAll = $args['contract_variables'] ? json_decode($args['contract_variables'], true) : [];
        $path = $args['employees'] ? $args['employees'][0] : false;
        $employees = [];

        $language = in_array($args['language'], ['en', 'ja', 'vi']) ? $args['language'] : 'en';

        App::setLocale($language);

        if ($path && !Storage::missing($path)) {

            $localPath = 'ContractTemplate/' . $path;

            Storage::disk('local')->put($localPath, Storage::get($path));

            $import = new ContractTemplateParseImport;

            Excel::import($import, storage_path('app/' . $localPath));

            $employees = $import->data->toArray();
        }

        if ($employees) {

            $header = array_shift($employees);

            $clientCustomVariables = ClientCustomVariable::select('*')->where('client_id', $clientId)->get()->toArray();

            foreach ($employees as $index => $employee) {
                $customVariables = $this->getImportVariables($header, $employee, $variableForAll, $clientCustomVariables);

                $contractNo = $this->getCodeNameContract($args['contract_no'], $customVariables);

                $contract = Contract::create([
                    'client_id' => $clientId,
                    'name' => $contractNo,
                    'contract_no' => $contractNo,
                    'contract_type' => $args['contract_type'],
                    'contract_variables' => json_encode($customVariables),
                    'note' => $args['note'],
                    ''
                ]);

                CreateClientEmployeeContract::dispatch(
                    [],
                    [],
                    $customVariables,
                    $contract,
                    [
                        'client_id' => $args['client_id'],
                        'contract_no' => $args['contract_no'],
                        'contract_type' => $args['contract_type'],
                        'template_id' => $args['template_id'],
                        'note' => $args['note']
                    ]
                );
            }

            Storage::disk('local')->delete($localPath);
        }

        return 'ok';
    }

    private function getCodeNameContract($template, $variables)
    {
        $m = new \Mustache_Engine(array('entity_flags' => ENT_QUOTES));

        return $m->render($template, $variables);
    }

    private function getEmployeeVariables($employee, $variableSelfEntry, $allowVariables, $clientCustomVariables)
    {
        // Only select necessary columns
        $employeeCustomVariables = ClientEmployeeCustomVariable::where('client_employee_id', $employee['id'])
            ->get(['variable_name', 'variable_value'])
            ->toArray();

        // Merge client and employee custom variables
        $systemVariables = array_merge($clientCustomVariables, $employeeCustomVariables);

        $results = $variableSelfEntry;

        // Date fields to be formatted
        $dateFields = [
            'DATE_OF_BIRTH', 'PROBATION_START_DATE', 'PROBATION_END_DATE',
            'OFFICIAL_CONTRACT_SIGNING_DATE', 'EFFECTIVE_DATE_OF_SOCIAL_INSURANCE',
            'HOUSEHOLD_HEAD_DATE_OF_BIRTH', 'QUITTED_AT',
            'CREATED_AT', 'UPDATED_AT', 'DELETED_AT'
        ];

        // Employment contract types mapping
        $employmentContractTypes = [
            'khongthoihan' => __('model.employees.indefinite_term'),
            'chinhthuc' => __('model.employees.definite_term'),
            'thoivu' => __('model.employees.part_time'),
            'thuviec' => __('model.employees.probationary')
        ];

        // Role mapping
        $roles = [
            'staff' => __('employee'),
            'manager' => __('model.employees.role.manager')
        ];

        // Constant variables to be replaced
        $constantVariables = [
            'POSITION' => 'client_position_name',
            'DEPARTMENT' => 'client_department_name',
        ];

        // Check if $allowVariables has the same variable_name
        foreach ($allowVariables as $allow) {
            foreach ($systemVariables as $var) {
                if ($allow === $var['variable_name']) {
                    $results[strtoupper($allow)] = number_format($var['variable_value']);
                }
            }

            // If not, set the value to empty
            $value = $employee[strtolower($allow)] ?? '';

            if ($allow === 'ID_CARD_ISSUE_DATE') {
                $value = Carbon::parse($employee['is_card_issue_date'])->format('d/m/Y');
            }

            if (in_array($allow, $dateFields) && $value) {
                $value = Carbon::parse($value)->format('d/m/Y');
            }

            if ($allow === 'TYPE_OF_EMPLOYMENT_CONTRACT') {
                $value = $employmentContractTypes[$value] ?? $value;
            } elseif ($allow === 'ROLE') {
                $value = $roles[$value] ?? $value;
            } elseif ($allow === 'SEX') {
                $value = $value === 'female' ? __('model.employees.female') : __('model.employees.male');
            } elseif (in_array($allow, ['IS_TAX_APPLICABLE', 'IS_INSURANCE_APPLICABLE'])) {
                $value = $value ? __('yes') : __('no');
            } elseif (isset($constantVariables[$allow])) {
                $value = $employee[$constantVariables[$allow]];
            } elseif (in_array($allow, ['SALARY', 'ALLOWANCE_FOR_RESPONSIBILITIES', 'FIXED_ALLOWANCE', 'SALARY_FOR_SOCIAL_INSURANCE_PAYMENT'])) {
                $value = is_numeric($value) ? number_format($value) : $value;
            } elseif (in_array($allow, ['BIRTH_PLACE_ADDRESS', 'BIRTH_PLACE_WARDS', 'BIRTH_PLACE_DISTRICT', 'BIRTH_PLACE_CITY_PROVINCE'])) {
                $birthdayProvinceData = Province::select('province_name')->where('id', $employee['birth_place_city_province'])->first();
                $birthdayDistrictData = ProvinceDistrict::select('district_name')->where('id', $employee['birth_place_district'])->first();
                $birthdayWardData = ProvinceWard::select('ward_name')->where('id', $employee['birth_place_wards'])->first();
                if ($allow === 'BIRTH_PLACE_ADDRESS') {
                    $value = $employee['birth_place_address'] . ", " . ($birthdayWardData ? $birthdayWardData->ward_name : '') . ', ' . ($birthdayDistrictData ? $birthdayDistrictData->district_name : '') . ', ' . ($birthdayProvinceData ? $birthdayProvinceData->province_name : '');
                } elseif ($allow === 'BIRTH_PLACE_WARDS') {
                    $value = $birthdayWardData ? $birthdayWardData->ward_name : '';
                } elseif ($allow === 'BIRTH_PLACE_DISTRICT') {
                    $value = $birthdayDistrictData ? $birthdayDistrictData->district_name : '';
                } elseif ($allow === 'BIRTH_PLACE_CITY_PROVINCE') {
                    $value = $birthdayProvinceData ? $birthdayProvinceData->province_name : '';
                }
            } elseif (in_array($allow, ['RESIDENT_ADDRESS', 'RESIDENT_WARDS', 'RESIDENT_DISTRICT', 'RESIDENT_CITY_PROVINCE'])) {
                $residentProvinceData = Province::select('province_name')->where('id', $employee['resident_city_province'])->first();
                $residentDistrictData = ProvinceDistrict::select('district_name')->where('id', $employee['resident_district'])->first();
                $residentWardData = ProvinceWard::select('ward_name')->where('id', $employee['resident_wards'])->first();
                if ($allow === 'RESIDENT_ADDRESS') {
                    $value = $employee['resident_address'] . ", " . ($residentWardData ? $residentWardData->ward_name : '') . ', ' . ($residentDistrictData ? $residentDistrictData->district_name : '') . ', ' . ($residentProvinceData ? $residentProvinceData->province_name : '');
                } elseif ($allow === 'RESIDENT_WARDS') {
                    $value = $residentWardData ? $residentWardData->ward_name : '';
                } elseif ($allow === 'RESIDENT_DISTRICT') {
                    $value = $residentDistrictData ? $residentDistrictData->district_name : '';
                } elseif ($allow === 'RESIDENT_CITY_PROVINCE') {
                    $value = $residentProvinceData ? $residentProvinceData->province_name : '';
                }
            } elseif (in_array($allow, ['CONTACT_ADDRESS', 'CONTACT_WARDS', 'CONTACT_DISTRICT', 'CONTACT_CITY_PROVINCE'])) {
                $contactProvinceData = Province::select('province_name')->where('id', $employee['contact_city_province'])->first();
                $contactDistrictData = ProvinceDistrict::select('district_name')->where('id', $employee['contact_district'])->first();
                $contactWardData = ProvinceWard::select('ward_name')->where('id', $employee['contact_wards'])->first();
                if ($allow === 'CONTACT_ADDRESS') {
                    $value = $employee['contact_address'] . ", " . ($contactWardData ? $contactWardData->ward_name : '') . ', ' . ($contactDistrictData ? $contactDistrictData->district_name : '') . ', ' . ($contactProvinceData ? $contactProvinceData->province_name : '');
                } elseif ($allow === 'CONTACT_WARDS') {
                    $value = $contactWardData ? $contactWardData->ward_name : '';
                } elseif ($allow === 'CONTACT_DISTRICT') {
                    $value = $contactDistrictData ? $contactDistrictData->district_name : '';
                } elseif ($allow === 'CONTACT_CITY_PROVINCE') {
                    $value = $contactProvinceData ? $contactProvinceData->province_name : '';
                }
            }
            $results[$allow] = $value;
        }

        $results = array_merge($results, $variableSelfEntry);

        return $results;
    }

    private function getImportVariables($header, $row, $variableForAll, $clientCustomVariables)
    {

        $employee = [];

        $header = array_map(function ($value) {
            return strtoupper($value);
        }, $header);

        foreach ($row as $index => $value) {
            $employee[strtoupper($header[$index])] = $value;
        }

        $employeeCustomVariables = [];

        if (in_array('CODE', $header)) {
            $clientEmployee = ClientEmployee::select('*')->where('code', $employee['CODE'])->first();

            if ($clientEmployee) {
                $employee = array_merge(array_change_key_case($clientEmployee->toArray(), CASE_UPPER), $employee);
                $employeeCustomVariables = ClientEmployeeCustomVariable::select('*')->where('client_employee_id', $clientEmployee->id)->get()->toArray();
            }
        }

        $customVariables = array_merge($clientCustomVariables, $employeeCustomVariables);

        $results = array_merge($employee, $variableForAll);

        foreach ($customVariables as $v) {
            $results[strtoupper($v['variable_name'])] = number_format($v['variable_value']);
        }

        foreach ($header as $v) {

            $value = isset($employee[$v]) ? $employee[$v] : '';

            if (in_array(strtoupper($v), ['PROBATION_START_DATE', 'PROBATION_END_DATE', 'OFFICIAL_CONTRACT_SIGNING_DATE', 'EFFECTIVE_DATE_OF_SOCIAL_INSURANCE', 'IS_CARD_ISSUE_DATE', 'HOUSEHOLD_HEAD_DATE_OF_BIRTH', 'DATE_OF_BIRTH', 'QUITTED_AT', 'CREATED_AT', 'UPDATED_AT', 'DELETED_AT']) && $value) {

                $date = Date::excelToDateTimeObject($value);

                $value = Carbon::parse($date)->format('Y-m-d');
            }

            $employee[$v] = $value;
        }

        foreach ($employee as $allow => $val) {

            $value = $val;

            if (in_array(strtoupper($v), ['PROBATION_START_DATE', 'PROBATION_END_DATE', 'OFFICIAL_CONTRACT_SIGNING_DATE', 'EFFECTIVE_DATE_OF_SOCIAL_INSURANCE', 'IS_CARD_ISSUE_DATE', 'HOUSEHOLD_HEAD_DATE_OF_BIRTH', 'DATE_OF_BIRTH', 'QUITTED_AT', 'CREATED_AT', 'UPDATED_AT', 'DELETED_AT']) && $value) {

                $value = Carbon::parse($value)->format('d/m/Y');
            }

            if ($allow == 'TYPE_OF_EMPLOYMENT_CONTRACT') {
                switch ($value) {
                    case 'khongthoihan':
                        $value = __('model.employees.indefinite_term');
                        break;
                    case 'chinhthuc':
                        $value = __('model.employees.definite_term');
                        break;
                    case 'thoivu':
                        $value = __('model.employees.part_time');
                        break;
                    case 'thuviec':
                        $value = __('model.employees.probationary');
                        break;
                }
            }

            if ($allow == 'ROLE') {
                switch ($value) {
                    case 'staff':
                        $value = __('employee');
                        break;
                    case 'accountant':
                        $value = __('model.employee_iglocal.accountant');
                        break;
                    case 'hr':
                        $value = __('model.employees.role.hr');
                        break;
                    case 'leader':
                        $value = __('model.employee.role.leader');
                        break;
                    case 'manager':
                        $value = __('model.employees.role.manager');
                        break;
                }
            }

            if ($allow == 'SEX') $value = ($value == 'female') ? __('model.employees.female') : __('model.employees.male');


            if (in_array($allow, ['IS_TAX_APPLICABLE', 'IS_INSURANCE_APPLICABLE'])) {
                $value = $value ? __('yes') : __('no');
            }

            if (in_array($allow, ['SALARY', 'ALLOWANCE_FOR_RESPONSIBILITIES', 'FIXED_ALLOWANCE', 'SALARY_FOR_SOCIAL_INSURANCE_PAYMENT'])) {
                $value = is_numeric($value) ? number_format($value) : $value;
            }

            $results[$v] = $value;
        }

        return $results;
    }

    private function exportFile($contract, $template_id, $variables)
    {
        $contractTemplate = ContractTemplate::select('*')->where('id', $template_id)->first();

        if (!$contractTemplate) return 'fail';

        $mediaItem = $contractTemplate->getFirstMedia('ContractTemplate');

        if (!$mediaItem) return 'fail';

        $templatePath = $mediaItem->getPath();
        $contractPath = 'Contract/' . $contract->id . '.docx';

        Storage::disk('local')->put(
            $contractPath,
            Storage::disk('minio')->get($templatePath)
        );

        $templateProcessor = new TemplateProcessor(storage_path('app/' . $contractPath));

        if ($variables) {
            foreach ($variables as $key => $value) {
                $templateProcessor->setValue($key, $value);
            }
        }

        $templateProcessor->saveAs(storage_path('app/' . $contractPath));

        $contract->addMediaFromDisk($contractPath, 'local')
            ->storingConversionsOnDisk('minio')
            ->toMediaCollection('Contract', 'minio');

        Storage::disk('local')->delete($contractPath);
    }

    public function parseVariables($root, array $args)
    {

        $contractTemplate = ContractTemplate::where('id', $args['id'])->first();

        if (!empty($contractTemplate)) {
            $mediaItem = $contractTemplate->getFirstMedia('ContractTemplate');

            if ($mediaItem) {
                $templatePath = $mediaItem->getPath();

                Storage::disk('local')->put(
                    $templatePath,
                    Storage::disk('minio')->get($templatePath)
                );

                $templateProcessor = new TemplateProcessor(storage_path('app/' . $templatePath));

                $variables = $templateProcessor->getVariables();

                $contractTemplate->update([
                    'contract_variables' => $variables ? json_encode($variables) : ''
                ]);

                Storage::disk('local')->delete($templatePath);

                return 'ok';
            }
        }

        return 'fail';
    }

    public function parseImport($root, array $args)
    {

        if (!Storage::missing($args['path'])) {

            $localPath = 'ContractTemplate/' . $args['path'];

            Storage::disk('local')->put($localPath, Storage::get($args['path']));

            $import = new ContractTemplateParseImport;

            Excel::import($import, storage_path('app/' . $localPath));

            return $import->data->toJson();
        }

        return json_encode([]);
    }

    public function getPermission()
    {
        return false;
    }
}
