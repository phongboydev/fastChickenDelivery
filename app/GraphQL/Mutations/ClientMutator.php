<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\HumanErrorException;
use App\Exports\ClientHeadCountExport;
use App\Jobs\DeleteFileJob;
use App\Models\CcClientEmail;
use App\Models\ClientSettingConditionCompare;
use App\Models\ClientUnitCode;
use App\Models\ClientYearHoliday;
use App\Models\LeaveCategory;
use App\Support\ErrorCode;
use App\Support\TimesheetsHelper;
use ErrorException;
use HttpException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\DownloadFileErrorException;
use App\Support\ClientHelper;
use App\Models\ReportPayroll;
use App\Models\ReportPit;
use App\Imports\Sheets\SetupCalculationSheetVariableImport;
use App\Imports\Sheets\SetupCalculationSheetColumnImport;
use App\Models\Client;
use App\User;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Exports\ClientCountExport;
use App\Exports\EmployeeCountExportMultipleSheet;

use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Maatwebsite\Excel\Facades\Excel;
use \Maatwebsite\Excel\Validators\ValidationException as ValidationException;
use App\Imports\ClientImport;
use App\Exceptions\CustomException;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use App\Support\Constant;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

use App\Jobs\CreatePayrollReports;
use App\Jobs\CreatePitReports;

use App\Exports\PaidLeaveBalanceExport;
use App\Events\DataImportCreatedEvent;
use App\Models\ClientEmployee;

class ClientMutator
{
    /**
     * Upload a file, store it on the server and return the path.
     *
     * @param  mixed $root
     * @param  mixed[] $args
     * @return string|null
     */
    public function import($root, array $args)
    {
        $inputFileType = 'Xlsx';
        $inputFileName = 'client_import_' . time() . '.xlsx';
        $inputFileImport = 'ClientImport/' . $inputFileName;

        Storage::disk('local')->putFileAs(
            'ClientImport',
            new File($args['file']),
            $inputFileName
        );

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        $reader->setLoadAllSheets();
        $spreadsheet = $reader->load(storage_path('app/' . $inputFileImport));

        $totalSheet = $spreadsheet->getSheetCount();

        $clientImport = new ClientImport();

        $errors = ['FORM_1' => $clientImport->validate($spreadsheet->getSheetByName('FORM_1'))];

        if ($errors['FORM_1']['formats']) {
            throw new DownloadFileErrorException($errors, $inputFileImport);
        }

        try {

            Excel::import($clientImport, $args['file']);

            Storage::disk('local')->delete($inputFileImport);

            return $clientImport->client;
        } catch (HttpException $e) {
            throw new CustomException(
                'The given data was invalid.',
                'HttpException'
            );
        }
    }

    public function generatePayrollReport($root, array $args)
    {
        $from_date = $args['from_date'];
        $to_date = $args['to_date'];

        $path = 'ReportPayroll/report_' . $from_date . '_' . $to_date . '.xlsx';

        if (!Storage::missing($path)) {
            return env('MINIO_URL') . '/' . env('MINIO_BUCKET') . '/' . $path;
        } else {

            $user = Auth::user();

            ReportPayroll::create([
                'date_from' => $from_date,
                'date_to' => $to_date,
                'original_creator_id' => $user->id,
                'status' => 'creating'
            ]);

            CreatePayrollReports::dispatch($from_date, $to_date);

            return 'creating';
        }
    }

    public function generatePITReport($root, array $args)
    {
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            if ($user->client_id != $args['client_id']) {
                throw new HumanErrorException(__("error.permission"));
            }

            $normalPermissions = ["manage-payroll"];
            $advancedPermissions = ["advanced-manage-payroll-info", "advanced-manage-payroll-list-update"];

            if (!$user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow(), null, true)) {
                throw new HumanErrorException(__("error.permission"));
            }
        } else {
            if (
                $user->getRole() != Constant::ROLE_INTERNAL_DIRECTOR
                && !$user->iGlocalEmployee->isAssignedFor($args['client_id'])
            ) {
                throw new HumanErrorException(__("error.permission"));
            }
        }

        $name = $args['name'];
        $payrolls = $args['payrolls'];
        $variables = $args['variables'];
        $loaiToKhai = isset($args['loai_to_khai']) && $args['loai_to_khai'] ? $args['loai_to_khai'] : 'chinh_thuc';

        $user = Auth::user();
        $client = Client::where('id', $args['client_id'])->first();

        $code = $client->code;

        switch ($args['duration_type']) {
            case 'quy':
                $code .= '_QUY_' . $args['quy_value'] . '_' . $args['quy_year'] . '_' . time();
                break;
            case 'nam':
                $code .= '_NAM_' . $args['quy_year'] . '_' . time();
                break;
            case 'thang':
                $code .= '_THANG_' . $args['thang_value'] . '_' . time();
                break;
        }

        $reportPit = ReportPit::create([
            'name' => $name,
            'code' => $code,
            'client_id' => $args['client_id'],
            'original_creator_id' => $user->id,
            'loai_to_khai' => $loaiToKhai,
            'duration_type' => $args['duration_type'],
            'quy_value' => (isset($args['quy_value']) ? $args['quy_value'] : 0),
            'quy_year' => (isset($args['quy_year']) ? $args['quy_year'] : 0),
            'thang_value' => (isset($args['thang_value']) ? $args['thang_value'] : ''),
            'date_from_to' => (isset($args['date_from_to']) ? $args['date_from_to'] : ''),
            'form_data' => json_encode([
                'payrolls' => $payrolls,
                'variables' => $variables
            ]),
            'status' => 'new',
            'export_status' => 'creating'
        ]);

        CreatePitReports::dispatch($args['client_id'], $reportPit->id, $payrolls, $variables, $args['is_deviated'] ?? 0);

        return 'creating';
    }

    public function generatePaidLeaveBalanceReport($root, array $args)
    {
        $client = Client::where('id', $args['client_id'])->first();

        $pathFile = 'PaidLeaveBalance/' . strtolower($client->code) . '_paid_leave_balance_' . time() . '.xlsx';

        Excel::store((new PaidLeaveBalanceExport($client, $args['year'])), $pathFile, 'minio');

        return Storage::temporaryUrl(
            $pathFile,
            now()->addMinutes(config('app.media_temporary_time', 5))
        );
    }

    public function validatePayrollHeadcount($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return ClientHelper::validatePayrollHeadcount($args['client_id'], $args['month'], $args['year'], $args['employees']);
    }

    public function validateLimitActivatedEmployee($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return ClientHelper::validateLimitActivatedEmployee($args['client_id']);
    }

    public function client($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $id = $args['id'];
        $lang = isset($args['lang']) ? $args['lang'] : app()->getLocale();
        app()->setlocale($lang);

        return Client::query()->authUserAccessible()->find($id);
    }

    public function updateClient($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $id = $args['id'];
        $clientUnitCodesUpsert = $args['clientUnitCode']['upsert'] ?? [];
        $clientUnitCodesDelete = $args['clientUnitCode']['delete'] ?? [];
        if (!empty($clientUnitCodesUpsert)) {
            foreach ($clientUnitCodesUpsert as &$unitCode) {
                if (empty($unitCode['id']))
                    $unitCode['id'] = Str::uuid();
            }
            ClientUnitCode::upsert($clientUnitCodesUpsert, 'id');
        }

        if (!empty($clientUnitCodesDelete)) {
            ClientUnitCode::destroy($clientUnitCodesDelete);
        }

        $ccClientEmailsUpsert = $args['ccClientEmail']['upsert'] ?? [];
        $ccClientEmailsDelete = $args['ccClientEmail']['delete'] ?? [];
        if (!empty($ccClientEmailsUpsert)) {
            foreach ($ccClientEmailsUpsert as &$ccClientEmail) {
                $ccClientEmail['client_id'] = $args['id'];
                if (empty($ccClientEmail['id'])) {
                    $ccClientEmail['id'] = Str::uuid();
                }
            }
            CcClientEmail::upsert($ccClientEmailsUpsert, 'id');
        }

        if (!empty($ccClientEmailsDelete)) {
            CcClientEmail::destroy($ccClientEmailsDelete);
        }

        $lang = isset($args['lang']) ? $args['lang'] : app()->getLocale();
        app()->setlocale($lang);
        $client = Client::find($id);
        $client->update($args);

        return $client;
    }

    public function importSetupCalculationSheet($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $inputFileType = 'Xlsx';
        $inputFileName = 'setup_calculation_sheet_import_' . time() . '.xlsx';
        $inputFileImport = 'SetupCalculationSheetImport/' . $inputFileName;

        Storage::disk('local')->putFileAs(
            'SetupCalculationSheetImport',
            new File($args['file']),
            $inputFileName
        );

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        $reader->setLoadAllSheets();
        $spreadsheet = $reader->load(storage_path('app/' . $inputFileImport));

        $errors = [];

        $setupCalculationSheetVariableImport = new SetupCalculationSheetVariableImport($args['client_id']);
        $variablesSheetData = $setupCalculationSheetVariableImport->getProcessedData($spreadsheet->getSheetByName('variables'));

        $columnsSheet = $spreadsheet->getSheetByName('columns');
        $maxColumns = Coordinate::columnIndexFromString($columnsSheet->getHighestColumn());

        $setupCalculationSheetColumnImport = new SetupCalculationSheetColumnImport($maxColumns, $args['client_id'], $variablesSheetData);
        $columnsSheetData = $setupCalculationSheetColumnImport->getProcessedData($spreadsheet->getSheetByName('columns'));

        $sheet2Errors = $setupCalculationSheetColumnImport->validate($variablesSheetData, $spreadsheet->getSheetByName('columns'));

        if ($sheet2Errors) $errors['columns'] = $sheet2Errors;

        if ($errors) {
            throw new DownloadFileErrorException($errors, $inputFileImport);
        }

        $setupCalculationSheetVariableImport->saveProcessedData($variablesSheetData);
        $templateId = $setupCalculationSheetColumnImport->saveProcessedData($args['template_name'], $columnsSheetData);

        DataImportCreatedEvent::dispatch([
            'type' => 'IMPORT_KHOI_TAO_BANG_TINH_LUONG',
            'client_id' => $args['client_id'],
            'user_id' => Auth::user()->id,
            'file' => $inputFileImport
        ]);

        Storage::disk('local')->delete($inputFileImport);

        return $templateId;
    }

    public function companyCounting($root, array $args)
    {
        /* Count outsourcing company for admin */
        $outsourcing_all = Client::where('client_type', 'outsourcing')->where('is_active', 1)->count();
        /* Count system company for admin */
        $system_all = Client::where('client_type', 'system')->where('is_active', 1)->where('is_test', 0)->count();
        $total_all = $outsourcing_all + $system_all;

        /* Count outsourcing company for PIC */
        $outsourcing_pic = Client::where('client_type', 'outsourcing')->where('is_active', 1)->with('assignedInternalEmployees')
            ->whereHas('assignedInternalEmployees', function ($q) {
                $q->where('iglocal_assignments.iglocal_employee_id', '=', auth()->user()->iGlocalEmployee->id);
            })->count();
        /* Count system company for PIC */
        $system_pic = Client::where('client_type', 'system')->where('is_active', 1)->where('is_test', 0)->with('assignedInternalEmployees')
            ->whereHas('assignedInternalEmployees', function ($q) {
                $q->where('iglocal_assignments.iglocal_employee_id', '=', auth()->user()->iGlocalEmployee->id);
            })->count();
        $total_pic = $outsourcing_pic + $system_pic;

        $result = [
            'admin' => [
                'total' => $total_all,
                'outsourcing' => $outsourcing_all,
                'system' => $system_all,
            ],
            'pic' => [
                'total' => $total_pic,
                'outsourcing' => $outsourcing_pic,
                'system' => $system_pic,
            ]
        ];

        return json_encode($result, 200);
    }

    public function employeeCounting($root, array $args)
    {
        /* Count outsourcing employees for admin */
        $outsourcing_employees_all = Client::where('client_type', 'outsourcing')->where('is_active', 1)
            ->withCount([
                'clientEmployees' => function ($q) {
                    $q->where('client_employees.is_involved_payroll', 1)->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING);
                }
            ])->get();

        $total_outsourcing_employees_all = $outsourcing_employees_all->sum('client_employees_count');

        /* Count outsourcing employees for PIC */
        $outsourcing_employees_pic = Client::where('client_type', 'outsourcing')
            ->where('is_active', 1)
            ->whereHas('assignedInternalEmployees', function ($q) {
                $q->where('iglocal_assignments.iglocal_employee_id', '=', auth()->user()->iGlocalEmployee->id);
            })
            ->withCount(['clientEmployees' => function ($q) {
                $q->where('client_employees.is_involved_payroll', 1)->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING);
            }])->get();

        $total_outsourcing_employees_pic = $outsourcing_employees_pic->sum('client_employees_count');

        /* Count system employees for admin */
        $system_employees_all = Client::where('client_type', 'system')->where('is_active', 1)->where('is_test', 0)->withCount(['clientEmployees' => function ($q) {
            $q->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING);
        }])->get();

        $total_system_employees_all = $system_employees_all->sum('client_employees_count');

        /* Count system employees for PIC */
        $system_employees_pic = Client::where('client_type', 'system')->where('is_active', 1)->where('is_test', 0)
            ->with('assignedInternalEmployees')
            ->whereHas('assignedInternalEmployees', function ($q) {
                $q->where('iglocal_assignments.iglocal_employee_id', '=', auth()->user()->iGlocalEmployee->id);
            })
            ->withCount(['clientEmployees' => function ($q) {
                $q->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING);
            }])->get();

        $total_system_employees_pic = $system_employees_pic->sum('client_employees_count');


        $result = [
            'admin' => [
                'total' => $total_system_employees_all + $total_outsourcing_employees_all,
                'system' => $total_system_employees_all,
                'outsourcing' => $total_outsourcing_employees_all
            ],
            'pic' => [
                'total' => $total_system_employees_pic + $total_outsourcing_employees_pic,
                'system' => $total_system_employees_pic,
                'outsourcing' => $total_outsourcing_employees_pic
            ]
        ];

        return json_encode($result, 200);
    }

    public function exportClientCount($root, array $args)
    {
        $role = auth()->user()->iGlocalEmployee->role;
        if ($role === 'director') {
            $data = [
                'clients' => Client::where('is_active', 1)
                    ->with(['clientEmployees' => function ($q) {
                        $q->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING);
                    }])->get(),
                'oursourcing-count' => Client::where('client_type', 'outsourcing')->where('is_active', 1)->count(),
                'system-count' => Client::where('client_type', 'system')->where('is_active', 1)->where('is_test', 0)->count()
            ];
        } else {
            $data = [
                'clients' => Client::where('is_active', 1)->with('assignedInternalEmployees')
                    ->whereHas('assignedInternalEmployees', function ($q) {
                        $q->where('iglocal_assignments.iglocal_employee_id', '=', auth()->user()->iGlocalEmployee->id);
                    })->with(['clientEmployees' => function ($q) {
                        $q->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING);
                    }])->get(),
                'oursourcing-count' => Client::where('client_type', 'outsourcing')->where('is_active', 1)->with('assignedInternalEmployees')
                    ->whereHas('assignedInternalEmployees', function ($q) {
                        $q->where('iglocal_assignments.iglocal_employee_id', '=', auth()->user()->iGlocalEmployee->id);
                    })->count(),
                'system-count' => Client::where('client_type', 'system')->where('is_active', 1)->where('is_test', 0)->with('assignedInternalEmployees')
                    ->whereHas('assignedInternalEmployees', function ($q) {
                        $q->where('iglocal_assignments.iglocal_employee_id', '=', auth()->user()->iGlocalEmployee->id);
                    })->count()
            ];
        }
        // Export excel
        $extension = '.xlsx';
        $fileName = "ClientCountExport_" . time() .  $extension;
        $pathFile = 'ClientCountExport/' . $fileName;


        Excel::store((new ClientCountExport($data)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }

    public function exportEmployeeCount($root, array $args)
    {
        $role = auth()->user()->iGlocalEmployee->role;
        if ($role === 'director') {
            $data = [
                'clients' => Client::where('is_active', 1)->with(['clientEmployees' => function ($q) {
                    $q->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING);
                }])->get()
            ];
        } else {
            $data = [
                'clients' => Client::where('is_active', 1)->whereHas('assignedInternalEmployees', function ($q) {
                    $q->where('iglocal_assignments.iglocal_employee_id', '=', auth()->user()->iGlocalEmployee->id);
                })->with(['clientEmployees' => function ($q) {
                    $q->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING);
                }])->get()
            ];
        }
        // Export excel
        $extension = '.xlsx';
        $fileName = "EmployeeCountExportMultipleSheet" . time() .  $extension;
        $pathFile = 'EmployeeCountExportMultipleSheet/' . $fileName;


        Excel::store((new EmployeeCountExportMultipleSheet($data)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }

    public function insertOrUpdateMultiSettingConditionCompare($root, array $args)
    {
        $data = $args['input'] ?? [];
        if (empty($data)) return false;

        // Validate
        $this->validateSettingConditionCompare($data);

        //Prepare data
        foreach ($data as &$item) {
            if (empty($item['id'])) {
                $item['id'] = Str::uuid();
            }
        }
        ClientSettingConditionCompare::upsert($data, ['id'], ['comparison_operator', 'value']);
        return true;
    }

    /**
     * @throws HumanErrorException
     */
    public function validateSettingConditionCompare($data)
    {
        foreach ($data as $row) {
            if (!in_array($row['key_condition'], Constant::KEY_CONDITION_COMPARE)) {
                throw new HumanErrorException(__("key_condition_is_not_list_condition"));
            }
            if (!in_array($row['comparison_operator'], Constant::COMPARISON_OPERATOR)) {
                throw new HumanErrorException(__("operator_is_not_in_the_list"));
            }
            if (!is_numeric($row['value'])) {
                throw new HumanErrorException(__("value_is_not_type"));
            } else {
                // Number hour of day
                if ($row['value'] < 0 || $row['value'] > 23.99) {
                    throw new HumanErrorException(__("value_is_not_in_range_allow"));
                }
            }
        }
    }

    public function insertOrUpdateClientYearHoliday($root, array $args)
    {
        $data = $args['input'][0] ?? [];
        $action = $args['action'] ?? '';
        if (empty($data) || !$action) return false;
        $authUser = Auth::user();
        // Check permission
        if ($authUser->isInternalUser()) {
            if (!Client::hasInternalAssignment()->find($data['client_id']) && !($authUser->getRole() == Constant::ROLE_INTERNAL_DIRECTOR)) {
                throw new AuthenticationException(__("error.permission"));
            }
        } else {
            if (!($authUser->hasPermissionTo('manage-workschedule'))) {
                throw new AuthenticationException(__("error.permission"));
            }
        }
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        if (empty($endDate)) {
            $endDate = $startDate;
        }

        // Check start date > end date
        if ($startDate > $endDate) {
            throw new HumanErrorException(__("error.invalid_time"));
        }

        $dates = $this->getDatesBetween($startDate, $endDate);
        $arrayInsert = [];
        $arrayUpsert = [];
        $groupId = Str::uuid();
        $clientEmployeeIds = ClientEmployee::where('client_id', $data['client_id'])->whereNull('quitted_at')->get()->pluck('id');
        $itemAction = [];
        if ($action == 'create') {
            $listDateExit = ClientYearHoliday::whereIn('date', $dates)->where('client_id',  $data['client_id'])->pluck('date')->toArray();
            if (count($listDateExit) > 0) {
                throw new HumanErrorException(__("date_is_exit"), ErrorCode::ERR0004);
            } else {
                foreach ($dates as $item) {
                    if (!in_array($item, $listDateExit)) {
                        $itemAction['id'] = Str::uuid();
                        $itemAction['client_id'] = $data['client_id'];
                        $itemAction['created_at'] = Carbon::now();
                        $itemAction['group_id'] = $groupId;
                        $itemAction['date'] = $item;
                        $itemAction['name'] = $data['name'];
                        $arrayInsert[] = $itemAction;
                    }
                }
                if (count($arrayInsert) > 0) {
                    ClientYearHoliday::insert($arrayInsert);
                    // Recalculate timesheet
                    $condition = [
                        'client_employee_ids' => $clientEmployeeIds,
                        'list_date' => $dates
                    ];
                    TimesheetsHelper::recalculateTimesheet($condition);
                }
            }
        } elseif ($action == 'update') {
            $dateUpdate = [];
            $listDateExit = ClientYearHoliday::where('group_id', $data['group_id'])->pluck('id', 'date')->toArray();
            foreach ($dates as $item) {
                $itemAction['id'] = array_key_exists($item, $listDateExit) ? $listDateExit[$item] : Str::uuid();
                $itemAction['client_id'] = $data['client_id'];
                $itemAction['updated_at'] = Carbon::now();
                $itemAction['group_id'] = $data['group_id'];
                $itemAction['date'] = $item;
                $itemAction['name'] = $data['name'];
                $dateUpdate[] = $item;
                $arrayUpsert[] = $itemAction;
            }
            ClientYearHoliday::upsert($arrayUpsert, ['id'], ['client_id', 'name', 'group_id', 'date']);

            $listDateWillDelete = array_diff(array_keys($listDateExit), $dates);
            // Delete date if update record by range start date and end date
            if ($listDateWillDelete) {
                ClientYearHoliday::whereIn('date', $listDateWillDelete)->where('client_id', $data['client_id'])->delete();
                $dateUpdate = array_merge($dateUpdate, $listDateWillDelete);
            }

            // Recalculate timesheet
            $condition = [
                'client_employee_ids' => $clientEmployeeIds,
                'list_date' => $dateUpdate
            ];
            TimesheetsHelper::recalculateTimesheet($condition);
        }

        return  ClientYearHoliday::where('client_id', $data['client_id'])->groupBy('group_id')->get();
    }

    public function getClientYearHolidayCustom($root, array $args)
    {
        return ClientYearHoliday::where('client_id', $args['client_id'])->groupBy('group_id')->get();
    }

    public function getDatesBetween($startDate, $endDate)
    {
        $dates = array();
        $currentDate = strtotime($startDate);
        $endDate = strtotime($endDate);
        $oneDay = 86400; // số giây trong một ngày

        while ($currentDate <= $endDate) {
            $dates[] = date('Y-m-d', $currentDate);
            $currentDate += $oneDay;
        }

        return $dates;
    }

    public function getLeaveCategoryCustom($root, array $args)
    {
        return LeaveCategory::where('client_id', $args['client_id'])
            ->when(isset($args['year']), function ($q) use ($args) {
                $q->where('year', $args['year']);
            })
            ->get();
    }

    public function deleteClientYearHolidays($root, array $args)
    {
        $authUser = Auth::user();
        $clientYearHolidayExit  = ClientYearHoliday::where('group_id', $args['group_id'])->first();
        if (!$clientYearHolidayExit) {
            throw new CustomException(
                'client_employee_salary_history.404',
                'ErrorException'
            );
        }
        // Check permission
        if ($authUser->isInternalUser()) {
            if (!Client::hasInternalAssignment()->find($clientYearHolidayExit->client_id) && !($authUser->getRole() == Constant::ROLE_INTERNAL_DIRECTOR)) {
                throw new AuthenticationException(__("error.permission"));
            }
        } else {
            if (!($authUser->hasPermissionTo('manage-workschedule'))) {
                throw new AuthenticationException(__("error.permission"));
            }
        }
        $listDate = ClientYearHoliday::where('group_id', $args['group_id'])->get()->pluck('date');
        $clientEmployeeIds = ClientEmployee::where('client_id', $authUser->client_id)->whereNull('quitted_at')->get()->pluck('id');
        ClientYearHoliday::where('group_id', $args['group_id'])->delete();
        // Recalculate timesheet
        $condition = [
            'client_employee_ids' => $clientEmployeeIds,
            'list_date' => $listDate
        ];
        TimesheetsHelper::recalculateTimesheet($condition);
        return ClientYearHoliday::where('client_id', $clientYearHolidayExit->client_id)->groupBy('group_id')->get();
    }

    public function exportHeadCountHistory($_, array $args)
    {
        $fileName = "HEAD_COUNT_HISTORY__" . uniqid() .  '.xlsx';
        $pathFile = 'ClientHeadCountExport/' . $fileName;
        Excel::store((new ClientHeadCountExport()), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        // Delete file
        DeleteFileJob::dispatch($pathFile)->delay(now()->addMinutes(3));

        return json_encode($response);
    }
}
