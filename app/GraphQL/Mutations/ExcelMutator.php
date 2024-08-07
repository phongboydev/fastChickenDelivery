<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\HumanErrorException;
use App\Support\FormatHelper;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Support\ImportHelper;
use App\Support\Constant;
use App\Models\Province;
use App\Models\ProvinceDistrict;
use App\Models\ProvinceWard;
use App\Models\ClientPosition;
use App\Models\ClientDepartment;
use App\Exceptions\CustomException;
use App\Models\ClientEmployee;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Models\Client;
use App\Jobs\ImportHistory;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ExcelMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // TODO implement the resolver
    }

    public function multiImport($root, array $args)
    {
        $clientId = $args['client_id'];
        $file = $args['file'];
        $type = $args['type'];
        $start_row = ImportHelper::getStartRow($type);
        $start_row_data = $start_row['data'];
        $start_row_header = $start_row['header'];
        logger(__METHOD__ . ": client - {$clientId} - type: {$type}");
        $data = [];
        $dataValidate = ['validate' => [], 'data' => [], 'type' => Str::of($type)->upper()];
        $lang = $args['lang'];
        $user =  auth()->user();
        $authId = $user->id;
        // Translate
        app()->setlocale($lang);

        // Internal User
        if ($user->isInternalUser()) {
            if (ImportHelper::checkApproveRequest($authId, $clientId, $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                throw new CustomException(__('error.has_request_still_pending_approve'), 'ValidationException', 'WR0003', $dataValidate, "warning", "import");
            }
        } else {
            if ($user->client_id != $args['client_id']) {
                throw new HumanErrorException(__('authorized'));
            }
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setLoadAllSheets();
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($file);
        $worksheet = $spreadsheet->getActiveSheet();

        // Get the highest row and column numbers referenced in the worksheet
        $highestRow = $worksheet->getHighestDataRow(); // e.g. 10
        $highestColumn = $worksheet->getHighestDataColumn(); // e.g 'F'
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn); // e.g. 5

        // Validate header names
        $headerRow = collect(range(2, $highestColumnIndex))
            ->map(function ($col) use ($worksheet) {
                return $worksheet->getCellByColumnAndRow($col, 1)->getValue();
            })
            ->toArray();

        // Compare header names
        $headersList = ImportHelper::HEADERS_LIST[$type];
        $headerDefault = collect($headersList)->keys()->all();
        $headerDiff = collect($headerDefault)->diffAssoc($headerRow)->all();

        if (!empty($headerDiff) || $headerRow != $headerDefault) {
            throw new CustomException(__('warning.WR0001.import'), 'ValidationException', 'WR0001', [], 'warning', 'import');
        }

        $excelData = [];
        $no = 1;
        for ($row = $start_row_data; $row <= $highestRow; ++$row) {
            // Initialize an array to store the row data
            $rowData = [];
            $cells = [];
            for ($col = 1; $col <= $highestColumnIndex; ++$col) {
                $value = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                $header = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
                $feRow = ($row - $start_row_header);
                $cellAddress = Coordinate::stringFromColumnIndex($col) . $feRow;

                // Re-order number
                if ($header == 'ordinal_number') {
                    $value = $no++;
                }

                switch ($type) {
                    case ImportHelper::CLIENT_EMPLOYEE:
                        // Format Date
                        if (in_array($header, ImportHelper::CLIENT_EMPLOYEE_FORMAT_DATE) && !empty($value) && is_numeric($value)) {
                            $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
                        }

                        if ($header == 'blood_group' && !empty($value)) {
                            $value = array_search($value, ImportHelper::BLOOD_GROUPS);
                        }

                        if (in_array($header, ImportHelper::PROVINCES) && !empty($value)) {
                            $province = Province::select('id')->where('province_code', ImportHelper::getCode($value))->first();
                            $value = $province ? $province->id : null;
                        }

                        if (in_array($header, ImportHelper::DISTRICTS) && !empty($value)) {
                            $district = ProvinceDistrict::select('id')->where('district_code', ImportHelper::getCode($value))->first();
                            $value = $district ? $district->id : null;
                        }

                        if (in_array($header, ImportHelper::WARDS) && !empty($value)) {
                            $ward = ProvinceWard::select('id')->where('ward_code', ImportHelper::getCode($value))->first();
                            $value = $ward ? $ward->id : null;
                        }

                        if ($header == 'education_level' && !empty($value)) {
                            $value = in_array($value, ImportHelper::EDUCATION_LEVEL) ? $value : null;
                        }

                        if ($header == 'position') {
                            $client_position =  ClientPosition::select('id')->where(['client_id' => $clientId, 'code' => $value])->first();

                            if (empty($value) || !$client_position) {
                                $dataValidate['validate'] = array_merge($dataValidate['validate'], [$cellAddress => empty($value) ? 'Mã chức vụ không được để trống' : 'Mã chức vụ không tồn tại']);
                                $value = null;
                            } else {
                                $value = $client_position->id;
                            }
                        }

                        if ($header == 'department') {
                            $client_department =  ClientDepartment::select('id')->where(['client_id' => $clientId, 'code' => $value])->first();

                            if (empty($value) || !$client_department) {
                                $dataValidate['validate'] = array_merge($dataValidate['validate'], [$cellAddress => empty($value) ? 'Mã phòng ban không được để trống' : 'Mã phòng ban không tồn tại']);
                                $value = null;
                            } else {
                                $value = $client_department->id;
                            }
                        }

                        if ($header == 'role') {
                            $value = 'staff';
                        }

                        break;
                    case ImportHelper::SALARY_INFORMATION:
                        if ($header == 'effective_date' && !empty($value)) {
                            $value = FormatHelper::transformDate($value);
                        }
                        $code = $worksheet->getCellByColumnAndRow(2, $row)->getValue();
                        if ($header == 'old_salary') {
                            $clientEmployee = ImportHelper::getClientEmployeeData($clientId, $code, 'salary');
                            if ($clientEmployee) {
                                $value = ImportHelper::getUpdatedValue($header, $clientEmployee);
                            }
                        }
                        if ($header == 'old_allowance_for_responsibilities') {
                            $clientEmployee = ImportHelper::getClientEmployeeData($clientId, $code, 'allowance_for_responsibilities');
                            if ($clientEmployee) {
                                $value = ImportHelper::getUpdatedValue($header, $clientEmployee);
                            }
                        }
                        if ($header == 'old_fixed_allowance') {
                            $clientEmployee = ImportHelper::getClientEmployeeData($clientId, $code, 'fixed_allowance');
                            if ($clientEmployee) {
                                $value = ImportHelper::getUpdatedValue($header, $clientEmployee);
                            }
                        }
                        break;
                    case ImportHelper::CONTRACT_INFORMATION:
                        if (in_array($header, ImportHelper::CONTRACT_INFORMATION_FORMAT_DATE) && !empty($value) && is_numeric($value)) {
                            $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
                        }
                        break;
                    case ImportHelper::DEPENDANT_INFORMATION:
                        if (in_array($header, ['from_date', 'to_date']) && !empty($value) && is_numeric($value)) {
                            $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
                        }
                        break;
                    case ImportHelper::PAID_LEAVE:
                        if (in_array($header, ['start_import_paidleave']) && !empty($value) && is_numeric($value)) {
                            $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
                        }
                        break;
                    default:
                        break;
                }
                $rowData[$header] = $value;
                $cells[] = $value;
            }
            $data[] = $cells;
            $excelData[] = $rowData;
        }

        // Validate data
        foreach ($excelData as $row) {
            if (empty($row['ordinal_number'])) {
                throw new CustomException(__('validation.required', ['attribute' => __('ordinal_number')]), 'ValidationException', 'WR0002', $dataValidate, 'warning', 'import');
            }

            $row = array_merge($row, ['client_id' => $clientId]);

            switch ($type) {
                case ImportHelper::CLIENT_EMPLOYEE:
                    $validator = ImportHelper::validateClientEmployee($row);
                    break;
                case ImportHelper::SALARY_INFORMATION:
                    $validator = ImportHelper::validateSalaryInformation($row);
                    break;
                case ImportHelper::CONTRACT_INFORMATION:
                    $validator = ImportHelper::validateContractInformation($row);
                    break;
                case ImportHelper::DEPENDANT_INFORMATION:
                    $validator = ImportHelper::validateDependantInformation($row);
                    break;
                case ImportHelper::PAID_LEAVE:
                    $validator = ImportHelper::validatePaidLeave($row);
                    break;
                case ImportHelper::AUTHORIZED_LEAVE:
                case ImportHelper::UNAUTHORIZED_LEAVE:
                    $validator = ImportHelper::validateLeave($row, $type);
                    break;
            }

            if ($validator && $validator->fails()) {
                foreach ($validator->errors()->toArray() as $i => $error) {
                    $dataValidate['validate'] = array_merge($dataValidate['validate'], [$headersList[$i] . $row['ordinal_number'] => $error[0]]);
                }
            }
        }

        if (count($dataValidate['validate']) > 0) {
            $dataValidate['data'] = $data;
            throw new CustomException(__('warning.WR0002.import'), 'ValidationException', 'WR0002', $dataValidate, "warning", "import");
        }

        return json_encode(['type' => Str::of($type)->upper(), 'data' => $data]);
    }

    public function saveBasicInfoFromExcel($root, array $args)
    {
        logger(__METHOD__ . ": client - {$args['client_id']}");
        $data = $args['input'];
        $clientId = $args['client_id'];
        $type = ImportHelper::CLIENT_EMPLOYEE;
        $dataValidate = ['validate' => [], 'data' => collect($data)->map(function ($item) {
            return array_values($item);
        })->toArray(), 'type' => Str::of($type)->upper()];
        $lang = $args['lang'];
        $user = auth()->user();
        $authId = $user->id;
        $isInternalUser = $user->isInternalUser();

        if (!$isInternalUser && $user->client_id != $clientId) {
            throw new HumanErrorException(__('authorized'));
        }

        // Translate
        app()->setlocale($lang);
        $headersList = ImportHelper::HEADERS_LIST[$type];
        // validation
        $validatorDuplicate = Validator::make($data, ['*.ordinal_number' => ['distinct'], '*.code' => ['distinct'], '*.username' => ['distinct', 'nullable']]);

        // Check dupicate
        ImportHelper::validatorDuplicate($validatorDuplicate, $dataValidate, $headersList);

        foreach ($data as $row) {
            $row = array_merge($row, ['client_id' => $clientId]);
            $validator = ImportHelper::validateClientEmployee($row);

            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $i => $error) {
                    $dataValidate['validate'] = array_merge($dataValidate['validate'], [$headersList[$i] . $row['ordinal_number'] => $error[0]]);
                }
            }

            if (!$validator->fails() && !$validatorDuplicate->fails() && !$isInternalUser) {
                if (!$isInternalUser) {
                    try {
                        // Check permissions manage-payroll & manage-employee-payroll
                        $permissions_manage_payroll = true;
                        // if user not internal then user have permission manage-payroll, manage-employee-payroll
                        if (!auth()->user()->is_internal && auth()->user()->getRole() != Constant::ROLE_CLIENT_MANAGER && !auth()->user()->hasDirectPermission('manage-payroll') && auth()->user()->getRole() != Constant::ROLE_CLIENT_MANAGER && !auth()->user()->hasDirectPermission('manage-employee-payroll')) {
                            $permissions_manage_payroll = false;
                        }

                        ImportHelper::updateClientEmployee($row, $clientId, $permissions_manage_payroll);
                    } catch (\Throwable $th) {
                        logger($th);
                        throw new CustomException(__('warning.WR0005.import'), 'ValidationException', 'WR0005', $dataValidate, "warning", "import");
                    }
                }
            }
        }

        if (count($dataValidate['validate']) > 0) {
            throw new CustomException(__('warning.WR0002.import'), 'ValidationException', 'WR0002', $dataValidate, "warning", "import");
        } else {
            if ($isInternalUser) {
                if (ImportHelper::checkApproveRequest($authId, $clientId, $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('error.has_request_still_pending_approve'), 'ValidationException', 'WR0003', $dataValidate, "warning", "import");
                }

                $client = Client::select('code', 'company_name')->find($clientId);

                if (!ImportHelper::internalCreateApprove($authId, $client, $clientId, $data, $args['step'], $args['approve_group_id'], $args['assignee_id'], $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('importing.fail_msg'), 'ValidationException', 'WR0004', $dataValidate, "warning", "import");
                }
            }
            // Save history
            ImportHistory::dispatch($data, $type, $clientId, $authId, $lang, $headersList);
            return [];
        }
    }

    public function saveSalaryInformationFromExcel($root, array $args)
    {
        logger(__METHOD__ . ": client - {$args['client_id']}");
        $data = $args['input'];
        $clientId = $args['client_id'];
        $type = ImportHelper::SALARY_INFORMATION;
        $dataValidate = ['validate' => [], 'data' => collect($data)->map(function ($item) {
            return array_values($item);
        })->toArray(), 'type' => Str::of($type)->upper()];
        $lang = $args['lang'];
        $user = auth()->user();
        $authId = $user->id;
        $isInternalUser = $user->isInternalUser();

        if (!$isInternalUser && $user->client_id != $clientId) {
            throw new HumanErrorException(__('authorized'));
        }

        // Translate
        app()->setlocale($lang);

        $headersList = ImportHelper::HEADERS_LIST[$type];
        // validation
        $validatorDuplicate = Validator::make($data, ['*.ordinal_number' => ['distinct'], '*.code' => ['distinct'],]);

        // check dupicate
        ImportHelper::validatorDuplicate($validatorDuplicate, $dataValidate, $headersList);

        foreach ($data as $row) {
            $row = array_merge($row, ['client_id' => $clientId]);
            $validator = ImportHelper::validateSalaryInformation($row);

            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $i => $error) {
                    $dataValidate['validate'] = array_merge($dataValidate['validate'], [$headersList[$i] . $row['ordinal_number'] => $error[0]]);
                }
            }

            if (!$validator->fails() && !$validatorDuplicate->fails() && !$isInternalUser) {
                ImportHelper::updateSalary($row, $clientId);
            }
        }

        if (count($dataValidate['validate']) > 0) {
            throw new CustomException(__('warning.WR0002.import'), 'ValidationException', 'WR0002', $dataValidate, "warning", "import");
        } else {
            if ($isInternalUser) {
                if (ImportHelper::checkApproveRequest($authId, $clientId, $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('error.has_request_still_pending_approve'), 'ValidationException', 'WR0003', $dataValidate, "warning", "import");
                }

                $client = Client::select('code', 'company_name')->find($clientId);

                if (!ImportHelper::internalCreateApprove($authId, $client, $clientId, $data, $args['step'], $args['approve_group_id'], $args['assignee_id'], $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('importing.fail_msg'), 'ValidationException', 'WR0004', $dataValidate, "warning", "import");
                }
            }
            // Save history
            ImportHistory::dispatch($data, $type, $clientId, $authId, $lang, $headersList);
            return [];
        }
    }

    public function saveContractInformationFromExcel($root, array $args)
    {
        logger(__METHOD__ . ": client - {$args['client_id']}");
        $data = $args['input'];
        $clientId = $args['client_id'];
        $type = ImportHelper::CONTRACT_INFORMATION;
        $dataValidate = ['validate' => [], 'data' => collect($data)->map(function ($item) {
            return array_values($item);
        })->toArray(), 'type' => Str::of($type)->upper()];
        $lang = $args['lang'];
        $user = auth()->user();
        $authId = $user->id;
        $isInternalUser = $user->isInternalUser();

        if (!$isInternalUser && $user->client_id != $clientId) {
            throw new HumanErrorException(__('authorized'));
        }

        // Translate
        app()->setlocale($lang);
        $headersList = ImportHelper::HEADERS_LIST[$type];
        // validation
        $validatorDuplicate = Validator::make($data, ['*.ordinal_number' => ['distinct'], '*.code' => ['distinct'],]);

        // check dupicate
        ImportHelper::validatorDuplicate($validatorDuplicate, $dataValidate, $headersList);

        foreach ($data as $row) {
            $row = array_merge($row, ['client_id' => $clientId]);
            $validator = ImportHelper::validateContractInformation($row);

            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $i => $error) {
                    $dataValidate['validate'] = array_merge($dataValidate['validate'], [$headersList[$i] . $row['ordinal_number'] => $error[0]]);
                }
            }

            if (!$validator->fails() && !$validatorDuplicate->fails() && !$isInternalUser) {
                ImportHelper::updateContract($row, $clientId);
            }
        }

        if (count($dataValidate['validate']) > 0) {
            throw new CustomException(__('warning.WR0002.import'), 'ValidationException', 'WR0002', $dataValidate, "warning", "import");
        } else {
            if ($isInternalUser) {
                if (ImportHelper::checkApproveRequest($authId, $clientId, $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('error.has_request_still_pending_approve'), 'ValidationException', 'WR0003', $dataValidate, "warning", "import");
                }

                $client = Client::select('code', 'company_name')->find($clientId);

                if (!ImportHelper::internalCreateApprove($authId, $client, $clientId, $data, $args['step'], $args['approve_group_id'], $args['assignee_id'], $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('importing.fail_msg'), 'ValidationException', 'WR0004', $dataValidate, "warning", "import");
                }
            }
            // Save history
            ImportHistory::dispatch($data, $type, $clientId, $authId, $lang, $headersList);
            return [];
        }
    }

    public function saveDependantInformationFromExcel($root, array $args)
    {
        logger(__METHOD__ . ": client - {$args['client_id']}");
        $data = $args['input'];
        $clientId = $args['client_id'];
        $type = ImportHelper::DEPENDANT_INFORMATION;
        $dataValidate = ['validate' => [], 'data' => collect($data)->map(function ($item) {
            return array_values($item);
        })->toArray(), 'type' => Str::of($type)->upper()];
        $lang = $args['lang'];
        $user = auth()->user();
        $authId = $user->id;
        $isInternalUser = $user->isInternalUser();

        if (!$isInternalUser && $user->client_id != $clientId) {
            throw new HumanErrorException(__('authorized'));
        }

        // Translate
        app()->setlocale($lang);

        $headersList = ImportHelper::HEADERS_LIST[$type];
        // validation
        $validatorDuplicate = Validator::make($data, ['*.ordinal_number' => ['distinct'], '*.tax_code' => ['distinct'],]);

        // check dupicate
        ImportHelper::validatorDuplicate($validatorDuplicate, $dataValidate, $headersList);

        foreach ($data as $row) {

            $row = array_merge($row, ['client_id' => $clientId]);
            $validator = ImportHelper::validateDependantInformation($row);

            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $i => $error) {
                    $dataValidate['validate'] = array_merge($dataValidate['validate'], [$headersList[$i] . $row['ordinal_number'] => $error[0]]);
                }
            }

            if (!$validator->fails() && !$validatorDuplicate->fails() && !$isInternalUser) {
                ImportHelper::updateDependent($row, $clientId);
            }
        }

        if (count($dataValidate['validate']) > 0) {
            throw new CustomException(__('warning.WR0002.import'), 'ValidationException', 'WR0002', $dataValidate, "warning", "import");
        } else {
            if ($isInternalUser) {
                if (ImportHelper::checkApproveRequest($authId, $clientId, $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('error.has_request_still_pending_approve'), 'ValidationException', 'WR0003', $dataValidate, "warning", "import");
                }

                $client = Client::select('code', 'company_name')->find($clientId);

                if (!ImportHelper::internalCreateApprove($authId, $client, $clientId, $data, $args['step'], $args['approve_group_id'], $args['assignee_id'], $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('importing.fail_msg'), 'ValidationException', 'WR0004', $dataValidate, "warning", "import");
                }
            }
            // Save history
            ImportHistory::dispatch($data, $type, $clientId, $authId, $lang, $headersList);
            return [];
        }
    }

    public function savePaidLeaveFromExcel($root, array $args)
    {
        logger(__METHOD__ . ": client - {$args['client_id']}");
        $data = $args['input'];
        $clientId = $args['client_id'];
        $type = ImportHelper::PAID_LEAVE;
        $dataValidate = ['validate' => [], 'data' => collect($data)->map(function ($item) {
            return array_values($item);
        })->toArray(), 'type' => Str::of($type)->upper()];
        $lang = $args['lang'];
        $user = auth()->user();
        $authId = $user->id;
        $isInternalUser = $user->isInternalUser();

        if (!$isInternalUser && $user->client_id != $clientId) {
            throw new HumanErrorException(__('authorized'));
        }

        // Translate
        app()->setlocale($lang);

        $headersList = ImportHelper::HEADERS_LIST[$type];
        // validation
        $validatorDuplicate = Validator::make($data, ['*.ordinal_number' => ['distinct'], '*.code' => ['distinct'],]);

        // Check dupicate
        ImportHelper::validatorDuplicate($validatorDuplicate, $dataValidate, $headersList);

        foreach ($data as $row) {
            $row = array_merge($row, ['client_id' => $clientId]);
            $ordinalNumber = $row['ordinal_number'];

            $validator = ImportHelper::validatePaidLeave($row);

            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $i => $error) {
                    $dataValidate['validate'] = array_merge($dataValidate['validate'], [$headersList[$i] . $ordinalNumber => $error[0]]);
                }
            }

            if (!$validator->fails() && !$validatorDuplicate->fails() && !$isInternalUser) {
                ImportHelper::updatePaidLeave($row, $clientId);
            }
        }

        if (count($dataValidate['validate']) > 0) {
            throw new CustomException(__('warning.WR0002.import'), 'ValidationException', 'WR0002', $dataValidate, "warning", "import");
        } else {
            if ($isInternalUser) {
                if (ImportHelper::checkApproveRequest($authId, $clientId, $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('error.has_request_still_pending_approve'), 'ValidationException', 'WR0003', $dataValidate, "warning", "import");
                }

                $client = Client::select('code', 'company_name')->find($clientId);

                if (!ImportHelper::internalCreateApprove($authId, $client, $clientId, $data, $args['step'], $args['approve_group_id'], $args['assignee_id'], $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('importing.fail_msg'), 'ValidationException', 'WR0004', $dataValidate, "warning", "import");
                }
            }
            // Save history
            ImportHistory::dispatch($data, $type, $clientId, $authId, $lang, $headersList);
            return [];
        }
    }

    public function saveLeaveFromExcel($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        logger(__METHOD__ . ": client - {$args['client_id']}");
        $data = $args['input'];
        $clientId = $args['client_id'];
        $type = $resolveInfo->fieldName == 'saveAuthorizedLeaveFromExcel' ? ImportHelper::AUTHORIZED_LEAVE : ImportHelper::UNAUTHORIZED_LEAVE;
        $dataValidate = ['validate' => [], 'data' => collect($data)->map(function ($item) {
            return array_values($item);
        })->toArray(), 'type' => Str::of($type)->upper()];
        $lang = $args['lang'];
        $user = auth()->user();
        $authId = $user->id;
        $isInternalUser = $user->isInternalUser();

        if (!$isInternalUser && $user->client_id != $clientId) {
            throw new HumanErrorException(__('authorized'));
        }

        // Translate
        app()->setlocale($lang);

        $headersList = ImportHelper::HEADERS_LIST[$type];
        // validation
        $validatorDuplicate = Validator::make($data, ['*.ordinal_number' => ['distinct'], '*.code' => ['distinct'],]);

        // Check dupicate
        ImportHelper::validatorDuplicate($validatorDuplicate, $dataValidate, $headersList);

        foreach ($data as $row) {
            $row = array_merge($row, ['client_id' => $clientId]);
            $ordinalNumber = $row['ordinal_number'];
            $validator = ImportHelper::validateLeave($row, $type);

            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $i => $error) {
                    $dataValidate['validate'] = array_merge($dataValidate['validate'], [$headersList[$i] . $ordinalNumber => $error[0]]);
                }
            }

            if (!$validator->fails() && !$validatorDuplicate->fails() && !$isInternalUser) {
                ImportHelper::updateLeave($row, $clientId, $type);
            }
        }

        if (count($dataValidate['validate']) > 0) {
            throw new CustomException(__('warning.WR0002.import'), 'ValidationException', 'WR0002', $dataValidate, "warning", "import");
        } else {
            if ($isInternalUser) {
                if (ImportHelper::checkApproveRequest($authId, $clientId, $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('error.has_request_still_pending_approve'), 'ValidationException', 'WR0003', $dataValidate, "warning", "import");
                }

                $client = Client::select('code', 'company_name')->find($clientId);

                if (!ImportHelper::internalCreateApprove($authId, $client, $clientId, $data, $args['step'], $args['approve_group_id'], $args['assignee_id'], $type, 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')) {
                    throw new CustomException(__('importing.fail_msg'), 'ValidationException', 'WR0004', $dataValidate, "warning", "import");
                }
            }
            // Save history
            ImportHistory::dispatch($data, $type, $clientId, $authId, $lang, $headersList);
            return [];
        }
    }
}
