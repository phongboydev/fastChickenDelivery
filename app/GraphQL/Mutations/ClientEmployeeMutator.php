<?php

namespace App\GraphQL\Mutations;

use App\DTO\DepartmentSalarySummary;
use App\Exceptions\CustomException;
use App\Exceptions\DownloadFileErrorException;
use App\Exceptions\HumanErrorException;
use App\Exports\ClientEmployeeExportMultiSheet;
use App\Exports\ClientEmployeeExportTemplateImport;
use App\Exports\ClientEmployeeForeignReportExport;
use App\Exports\ClientEmployeeStatusExport;
use App\Exports\ClientEmployeeSummaryExport;
use App\Exports\ClientEmployeeSummaryYearExport;
use App\Imports\ClientEmployeeContacImport;
use App\Imports\ClientEmployeeImportMultiSheet;
use App\Imports\PaidLeaveChangeImport;
use App\Imports\Sheets\ClientEmployeeBasicSheetImport;
use App\Imports\Sheets\ClientEmployeeBasicSheetNewImport;
use App\Imports\Sheets\ClientEmployeeBasicSheetUpdateImport;
use App\Imports\Sheets\ClientEmployeeSalarySheetImport;
use App\Jobs\SendActivationUserEmail;
use App\Jobs\SendCustomerResetPasswordEmail;
use App\Models\Approve;
use App\Models\ApproveGroup;
use App\Models\Checking;
use App\Models\Client;
use App\Models\ClientAssignment;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeCustomVariable;
use App\Models\ClientEmployeeGroupAssignment;
use App\Models\ClientEmployeeLeaveManagement;
use App\Models\ClientEmployeeSalary;
use App\Models\ClientEmployeeSalaryHistory;
use App\Models\ClientLogDebug;
use App\Models\ClientWorkflowSetting;
use App\Models\DataImport;
use App\Models\HanetPerson;
use App\Models\HanetPlace;
use App\Models\HanetSetting;
use App\Models\LeaveCategory;
use App\Models\OvertimeCategory;
use App\Models\Timesheet;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleGroup;
use App\Models\WorkTimeRegisterPeriod;
use App\Models\ApproveFlowUser;
use App\Models\ClientEmployeeTrainingSeminar;
use App\Models\Contract;
use App\Notifications\ClientEmployeeResetPasswordNotification;
use App\Support\ClientHelper;
use App\Support\HanetHelper;
use App\Support\MediaHelper;
use App\Support\PeriodHelper;
use App\Support\SignApiHelper;
use App\Support\WorktimeRegisterHelper;
use App\User;
use DateTime;
use Dompdf\Dompdf;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ValidationException;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Table;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;
use App\Exports\ClientEmployeeOvertimeExport;
use App\Models\WorktimeRegister;
use App\Events\DataImportCreatedEvent;
use Illuminate\Support\Facades\Http;
use App\Models\TrainingSeminar;
use App\Support\Constant;
use App\Support\ImportHelper;
use App\Jobs\DeleteFileJob;

class ClientEmployeeMutator
{

    public function import($root, array $args): ?string
    {
        logger("ClientEmployeeMutator::import BEGIN " . $args['client_id']);

        $rules = array(
            'file' => 'required',
            'client_id' => 'required',
        );

        $inputFileType = 'Xlsx';
        $inputFileName = 'client_employee_import_' . time() . '.xlsx';
        $inputFileImport = 'ClientEmployeeImport/' . $inputFileName;

        Storage::disk('local')->putFileAs(
            'ClientEmployeeImport',
            new File($args['file']),
            $inputFileName
        );

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        $reader->setLoadAllSheets();
        $spreadsheet = $reader->load(storage_path('app/' . $inputFileImport));

        $totalSheet = $spreadsheet->getSheetCount();
        $sheetNames = $spreadsheet->getSheetNames();

        $errors = [];

        $clientEmployeeBasicSheetImport = new ClientEmployeeBasicSheetImport($args['client_id']);

        if (Auth::user()->isInternalUser()) {
            $clientEmployeeBasicSheetImport = $args['is_new'] ? new ClientEmployeeBasicSheetNewImport($args['client_id']) : new ClientEmployeeBasicSheetUpdateImport($args['client_id'], Auth::user()->id);
        }

        $clientEmployeeSalarySheetImport = new ClientEmployeeSalarySheetImport($args['client_id']);

        $sheet1Errors = $clientEmployeeBasicSheetImport->validate($spreadsheet->getSheet(0));
        $sheet2Errors = $clientEmployeeSalarySheetImport->validate($spreadsheet->getSheet(1));

        if ($sheet1Errors) $errors[$sheetNames[0]] = $sheet1Errors;
        if ($sheet2Errors) $errors[$sheetNames[1]] = $sheet2Errors;

        if ($errors) {
            throw new DownloadFileErrorException($errors, $inputFileImport);
        }

        try {
            Validator::make($args, $rules);

            Excel::import(new ClientEmployeeImportMultiSheet($args['client_id'], $totalSheet, false, $sheetNames), $args['file']);

            DataImportCreatedEvent::dispatch([
                'type' => 'IMPORT_CLIENT_EMPLOYEE',
                'client_id' => $args['client_id'],
                'user_id' => Auth::user()->id,
                'file' => $inputFileImport
            ]);

            Storage::disk('local')->delete($inputFileImport);

            return json_encode(['status' => 200, 'message' => 'Import Client Employee is successful.'], 200);
        } catch (ValidationException $e) {

            $message = '';

            if ($e->errors()) {
                $errors = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($e->errors())), false);

                $message = implode(' <br/> ', $errors);
            }

            Storage::disk('local')->delete($inputFileImport);

            throw new CustomException(
                $message,
                'ValidationException'
            );
        }
    }

    public function createFullClientEmployeeInformation($root, array $args)
    {
        $user = Auth::user();
        if ($user->isInternalUser()) {
            if (!$user->iGlocalEmployee->isAssignedFor($args['client_id'])) {
                throw new HumanErrorException(__('authorized'));
            }
        } else {
            if ($user->client_id != $args['client_id']) {
                throw new HumanErrorException(__('authorized'));
            }
        }

        if (!ClientHelper::validateLimitActivatedEmployee($args['client_id'])) {
            throw new HumanErrorException(__('error.exceeded_employee_limit'));
        }

        if (ClientEmployee::where('client_id', $args['client_id'])->where('code', $args['code'])->count()) {
            throw new HumanErrorException(__('importing.already_taken_msg', ['msg' => $args['code']]));
        }

        if (!empty($args['user'])) {
            if ($args['user']['is_internal'] == 1) {
                throw new HumanErrorException(__('authorized'));
            }

            if (User::where('client_id', $args['client_id'])->where('username', $args['client_id'] . "_" . $args['user']['username'])->count()) {
                throw new HumanErrorException(__('importing.already_taken_msg', ['msg' => $args['user']['username']]));
            }
        }

        DB::transaction(function () use ($args) {
            $args['year_paid_leave_expiry'] = Carbon::now()->endOfYear()->format('Y-m-d H:i:s');
            if (!empty($args['user'])) {
                $args['user']['client_id'] = $args['client_id'];
                $args['user']['password'] = bcrypt(Str::random(10));
                $user = User::create($args['user']);
                $employee = $user->clientEmployee()->create($args);
            } else {
                $employee = ClientEmployee::create($args);
            }

            if (!empty($args['client_employee_contract'])) {
                $employee->contract()->create($args['client_employee_contract']);
            }

            if (!empty($args['client_employee_custom_variables'])) {
                $employee->customVariables()->createMany($args['client_employee_custom_variables']);
            }
        });
    }

    public function export($root, array $args): ?string
    {
        $client_id = $args['client_id'];
        $user = auth()->user();

        if (!$user->isInternalUser() && $user->client_id != $client_id) {
            throw new HumanErrorException(__('authorized'));
        }

        $status = isset($args['status']) ? $args['status'] : null;
        $groupIds = !empty($args['group_ids']) ? $args['group_ids'] : [];
        $ids = !empty($args['ids']) ? $args['ids'] : [];
        $type = $args['type'] ?? ImportHelper::CLIENT_EMPLOYEE;
        $lang = $user->prefered_language ?? app()->getLocale();
        $client = Client::select('code')->where('id', $client_id)->first();
        $fileName = Str::finish(sprintf('%s_%s_%s', $client->code, $type, now()->format('Y-m-d-H-i-s-u')), "_{$lang}.xlsx");
        $folderName = Str::replace('_', '', Str::title($type));
        $pathFile = $folderName . 'Export/' . $fileName;

        Excel::store((new ClientEmployeeExportMultiSheet($client_id, $status, $groupIds, $ids, $type, $folderName)), $pathFile, 'minio');

        // Delete File
        DeleteFileJob::dispatch($pathFile)->delay(now()->addMinutes(3));

        return json_encode([
            'name' => $fileName,
            'file' => MediaHelper::getPublicTemporaryUrl($pathFile)
        ]);
    }

    public function sendApproveImport($root, array $args): ?string
    {
        $inputFileType = 'Xlsx';
        $inputFileName = 'client_employee_import_' . time() . '.xlsx';
        $inputFileImport = 'ClientEmployeeImport/' . $inputFileName;
        $user = Auth::user();
        $client = Client::where('id', $args['client_id'])->first();


        $hasDataImport = DataImport::where('client_id', $args['client_id'])
            ->where('type', 'internal_client_employee')
            ->where('status', 'pending')->first();

        if (!empty($hasDataImport)) {
            throw new HumanErrorException(__('error.has_request_still_pending_approve'));
        }

        Storage::disk('local')->putFileAs(
            'ClientEmployeeImport',
            new File($args['file']),
            $inputFileName
        );

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        $reader->setLoadAllSheets();
        $spreadsheet = $reader->load(storage_path('app/' . $inputFileImport));

        $totalSheet = $spreadsheet->getSheetCount();
        $sheetNames = $spreadsheet->getSheetNames();

        $errors = [];

        $activeLang = isset($args['active_lang']) ? $args['active_lang'] : 'vi';

        $clientEmployeeBasicSheetImport = new ClientEmployeeBasicSheetImport($args['client_id']);

        if ($user->isInternalUser()) {
            $clientEmployeeBasicSheetImport = $args['is_new'] ? new ClientEmployeeBasicSheetNewImport($args['client_id']) : new ClientEmployeeBasicSheetUpdateImport($args['client_id'], $user->id);
        }

        $clientEmployeeSalarySheetImport = new ClientEmployeeSalarySheetImport($args['client_id']);

        $sheet1Errors = $clientEmployeeBasicSheetImport->validate($spreadsheet->getSheet(0));
        $sheet2Errors = $clientEmployeeSalarySheetImport->validate($spreadsheet->getSheet(1));

        if ($sheet1Errors) $errors[$sheetNames[0]] = $sheet1Errors;
        if ($sheet2Errors) $errors[$sheetNames[1]] = $sheet2Errors;

        if ($errors) {
            throw new DownloadFileErrorException($errors, $inputFileImport);
        }

        DB::beginTransaction();

        try {

            Excel::import(new ClientEmployeeImportMultiSheet($args['client_id'], $totalSheet, false, $sheetNames), $args['file']);

            DB::rollBack();

            $dataImport = DataImport::create([
                'client_id' => $args['client_id'],
                'type' => 'internal_client_employee',
                'status' => 'pending',
                'creator_id' => $user->id,
            ]);

            $dataImport->addMediaFromDisk($inputFileImport, 'local')
                ->toMediaCollection('DataImport', 'minio');

            Storage::disk('local')->delete($inputFileImport);

            $approveGroup = ApproveGroup::create([
                'client_id' => '000000000000000000000000',
                'type' => 'INTERNAL_IMPORT_CLIENT_EMPLOYEE'
            ]);

            $defaultClientEmployeeGroup = '0';

            $approveFlowUser = ApproveFlowUser::where('user_id', $args['reviewer_id'])
                ->with('approveFlow')
                ->whereHas('approveFlow', function ($query) use ($defaultClientEmployeeGroup) {
                    return $query->where('flow_name', 'INTERNAL_IMPORT_CLIENT_EMPLOYEE')->where('group_id', $defaultClientEmployeeGroup);
                })->get();

            $step = 1;

            if ($approveFlowUser->isNotEmpty()) {

                $sortedApproveFlow = $approveFlowUser->sortBy(function ($item, $key) {

                    return $item->toArray()['approve_flow']['step'];
                });
                $approveFlow = $sortedApproveFlow->values()->last()->toArray();
                $step = $approveFlow['approve_flow']['step'];
            }

            $approve = new Approve();
            $approve->fill([
                'type' => 'INTERNAL_IMPORT_CLIENT_EMPLOYEE',
                'content' => json_encode([
                    'client_id' => $args['client_id'],
                    'company_name' => $client->company_name,
                    'code' => $client->code,
                    'is_new' => $args['is_new'],
                    'data_import_id' => $dataImport->id,
                    'active_lang' => $activeLang
                ]),
                'creator_id' => $user->id,
                'original_creator_id' => $user->id,
                'step' => $step,
                'target_id' => $dataImport->id,
                'target_type' => 'App\Models\DataImport',
                'is_final_step' => 0,
                'client_id' => '000000000000000000000000',
                'approve_group_id' => $approveGroup->id,
                'assignee_id' => $args['reviewer_id'],
                'client_employee_group_id' => $defaultClientEmployeeGroup
            ]);

            $approve->save();

            return json_encode(['status' => 200, 'message' => 'Sending request approve is successful.'], 200);
        } catch (ValidationException $e) {

            DB::rollBack();

            $message = '';

            if ($e->errors()) {
                $errors = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($e->errors())), false);

                $message = implode(' <br/> ', $errors);
            }

            Storage::disk('local')->delete($inputFileImport);

            throw new CustomException(
                $message,
                'ValidationException'
            );
        }
    }

    public function importPaidLeave($root, array $args)
    {
        $inputFileType = 'Xlsx';
        $inputFileName = 'paid_leave_import_' . time() . '.xlsx';
        $inputFileImport = 'ClientEmployeeImport/' . $inputFileName;
        Storage::disk('local')->putFileAs(
            'ClientEmployeeImport',
            new File($args['file']),
            $inputFileName
        );

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        $reader->setLoadAllSheets();
        $spreadsheet = $reader->load(storage_path('app/' . $inputFileImport));

        $errors = [];
        $clientEmployeeBasicSheetImport = new PaidLeaveChangeImport($args['client_id'], $args['type']);

        $sheet1Errors = $clientEmployeeBasicSheetImport->validate($spreadsheet->getSheetByName('Sheet1'));

        if ($sheet1Errors) $errors['Sheet1'] = $sheet1Errors;

        if ($errors) {
            throw new DownloadFileErrorException($errors, $inputFileImport);
        }

        try {

            Excel::import(new PaidLeaveChangeImport($args['client_id'], $args['type']), $args['file']);

            DataImportCreatedEvent::dispatch([
                'type' => 'IMPORT_NGAY_PHEP',
                'client_id' => $args['client_id'],
                'user_id' => Auth::user()->id,
                'file' => $inputFileImport
            ]);

            Storage::disk('local')->delete($inputFileImport);

            return json_encode(['status' => 200, 'message' => 'Import Paid Leave Change is successful.'], 200);
        } catch (ValidationException $e) {
            logger($e->getMessage());
            $message = '';

            if ($e->errors()) {
                $errors = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($e->errors())), false);

                $message = implode(' <br/> ', $errors);
            }

            Storage::disk('local')->delete($inputFileImport);

            throw new CustomException(
                $message,
                'ValidationException'
            );
        }
    }

    public function salaryHistories($root, array $args)
    {
        $user = auth()->user();
        $clientEmployeeId = $args['client_employee_id'];

        // Init permission
        $advancedPermissions = ['advanced-manage-payroll-salary-history-read', 'advanced-manage-payroll-list-read'];
        $normalPermissions = ['manage-payroll', 'manage-employee-payroll'];
        // Check have permission
        $permission = $user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow($user->client_id));

        if ($user->isInternalUser() || $user->ClientEmployee->client_employee_id == $clientEmployeeId || $permission) {

            return ClientEmployeeSalaryHistory::authUserAccessible()
                ->where('client_employee_id', $clientEmployeeId)
                ->orderBy('created_at', 'DESC')
                ->get();
        }

        return null;
    }

    public function getListClientEmployeeIdByGroupId($root, array $args)
    {
        $user = Auth::user();
        if (!isset($args['group_ids'])) return [];
        $clientEmployeeId = $user->clientEmployee->id;
        $clientEmployeeGroupAssignment = ClientEmployeeGroupAssignment::where('client_employee_id', $clientEmployeeId)
            ->whereIn('client_employee_group_id', $args['group_ids'])->first();
        if (!$clientEmployeeGroupAssignment) {
            return [];
        }

        return array_unique(ClientEmployeeGroupAssignment::whereIn('client_employee_group_id', $args['group_ids'])->get()->pluck('client_employee_id')->toArray());
    }

    public function getListClientEmployeeByGroupIds($root, array $args)
    {
        $user = Auth::user();
        if (!isset($args['group_ids'])) return [];
        $clientEmployeeId = $user->clientEmployee->id;
        $clientEmployeeGroupAssignment = ClientEmployeeGroupAssignment::where('client_employee_id', $clientEmployeeId)
            ->whereIn('client_employee_group_id', $args['group_ids'])->first();
        if (!$clientEmployeeGroupAssignment) {
            return [];
        }

        $listIdClientEmployee = array_unique(ClientEmployeeGroupAssignment::whereIn('client_employee_group_id', $args['group_ids'])->get()->pluck('client_employee_id')->toArray());
        $listClientEmployee = [];
        if (count($listIdClientEmployee) > 0) {
            $listClientEmployee = ClientEmployee::whereIn('id', $listIdClientEmployee)->get();
        }
        return $listClientEmployee;
    }

    public function exportForeignWorkerReport2($root, array $args)
    {

        $client = Client::where('id', $args['client_id'])->first();

        $today = Carbon::now();

        $filter_quarter = $args['quarter'];
        $filter_year = $args['year'];

        $file_1 = $client->id . "_{$filter_year}_{$filter_quarter}_report_foreign_1.docx";
        $file_2 = $client->id . "_{$filter_year}_{$filter_quarter}_report_foreign_2.docx";

        $company_name = $client->company_name ? $client->company_name : '......................';
        $address = $client->address ? $client->address : '......................';
        $company_contact_phone = $client->company_contact_phone ? $client->company_contact_phone : '......................';
        $company_contact_email = $client->company_contact_email ? $client->company_contact_email : '......................';
        $company_license_no = $client->company_license_no ? $client->company_license_no : '......................';
        $company_license_issuer = $client->company_license_issuer ? $client->company_license_issuer : '......................';
        $company_license_issued_at = $client->company_license_issued_at ? $client->company_license_issued_at : '.............';
        $company_license_updated_at = $client->company_license_updated_at ? $client->company_license_updated_at : '.............';
        $presenter_name = $client->presenter_name ? $client->presenter_name : '......................';
        $presenter_phone = $client->presenter_phone ? $client->presenter_phone : '......................';
        $presenter_email = $client->presenter_email ? $client->presenter_email : '............................................';

        $phpWord = new PhpWord();

        $section = $phpWord->addSection();

        $section->getStyle()->setBreakType('continuous');

        $cellRowSpan = array('vMerge' => 'restart', 'valign' => 'center');
        $cellRowContinue = array('vMerge' => 'continue');
        $cellColSpan = array('gridSpan' => 2, 'valign' => 'center');
        $cellHCentered = array('alignment' => Jc::CENTER);
        $cellVCentered = array('valign' => 'center');

        $table = $section->addTable([
            'unit' => Table::WIDTH_PERCENT,
            'width' => 100 * 50
        ]);

        $generalStyle = ['size' => 10, 'name' => 'Times New Roman'];
        $generalStyleItalic = array_merge($generalStyle, ['italic' => true]);
        $generalStyleBold = array_merge($generalStyle, ['bold' => true]);

        $generalParagraph = ['lineHeight' => 1.5];
        $generalParagraphCenter = array_merge($generalParagraph, ['alignment' => Jc::CENTER]);

        $styleNoBorderCell = ['borderColor' => 'ffffff', 'borderSize' => 6];

        $table->addRow();

        $table->addCell('30%', $styleNoBorderCell)->addText($company_name, $generalStyleBold);

        $c1 = $table->addCell('70%', $styleNoBorderCell);

        $c1->addText('CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', $generalStyleBold, ['alignment' => Jc::CENTER]);
        $c1->addText('--------------', $generalStyleBold, ['alignment' => Jc::CENTER]);
        $c1->addText('Độc lập - Tự do - Hạnh phúc', $generalStyleBold, ['alignment' => Jc::CENTER]);

        $table->addRow(900);

        $table->addCell('30%', array_merge($styleNoBorderCell, ['valign' => 'center']))->addText('Số: ......................', $generalStyle);
        $table->addCell('70%', array_merge($styleNoBorderCell, ['valign' => 'center']))->addText('TPHCM, ngày ' . $today->format('d') . ' tháng ' . $today->format('m') . ' năm ' . $today->format('Y'), $generalStyle, ['alignment' => Jc::CENTER]);

        $section->addText(
            'BÁO CÁO TÌNH HÌNH SỬ DỤNG NGƯỜI LAO ĐỘNG NƯỚC NGOÀI',
            ['size' => 10, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spacing' => 240, 'spaceBefore' => 550]
        );

        $section->addText('Kính gửi: Sở Lao động - Thương binh và Xã hội …………………', $generalStyle, ['alignment' => Jc::CENTER]);

        $section->addText('1. Tên văn phòng đại điện: ' . $company_name, $generalStyle, ['spaceBefore' => 400, 'lineHeight' => 1.5]);
        $section->addText('2. Địa chỉ: ' . $address, $generalStyle, $generalParagraph);
        $section->addText('3. Điện thoại: ' . $company_contact_phone, $generalStyle, $generalParagraph);
        $section->addText('4. Email: ' . $company_contact_email, $generalStyle, $generalParagraph);
        $section->addText('5. Giấy phép thành lập số: ' . $company_license_no, $generalStyle, $generalParagraph);

        $table = $section->addTable([
            'unit' => Table::WIDTH_PERCENT,
            'width' => 100 * 50
        ]);

        $table->addRow();
        $table->addCell('50%', $styleNoBorderCell)->addText('Cơ quan cấp: ' . $company_license_issuer, $generalStyle, $generalParagraph);
        $table->addCell('50%', $styleNoBorderCell)->addText('Ngày cấp lần đầu: ' . $company_license_issued_at . ', thay đổi: ' . $company_license_updated_at, $generalStyle, $generalParagraph);

        $section->addText('6. Lĩnh vực hoạt động: ..........................', $generalStyle, array_merge($generalParagraph, ['spaceBefore' => 135]));

        $table = $section->addTable([
            'unit' => Table::WIDTH_PERCENT,
            'width' => 100 * 50
        ]);

        $table->addRow();
        $table->addCell('50%', $styleNoBorderCell)->addText('7. Người đại diện: ' . $presenter_name, $generalStyle, $generalParagraph);
        $table->addCell('50%', $styleNoBorderCell)->addText('Số điện thoại: ' . $presenter_phone, $generalStyle, $generalParagraph);

        $section->addText('Email: ' . $presenter_email, $generalStyle, array_merge($generalParagraph, ['spaceBefore' => 135]));

        $section->addText('Báo cáo tình hình tuyển dụng, sử dụng và quản lý người lao động nước ngoài như sau:', $generalStyle, $generalParagraph);

        $textrun = $section->addTextRun();

        $textrun->addText('1. Số liệu về người lao động nước ngoài ', $generalStyle, $generalParagraph);
        $textrun->addText('(có bảng số liệu kèm theo)', $generalStyleItalic, $generalParagraph);

        $textrun = $section->addTextRun();
        $textrun->addText('2. Đánh giá, kiến nghị ', $generalStyle, $generalParagraph);
        $textrun->addText('(nếu có)', $generalStyleItalic, $generalParagraph);
        $textrun->addText(':…………………………………………………………….', $generalStyle, $generalParagraph);

        $section->addTextBreak(3);

        $table = $section->addTable([
            'unit' => Table::WIDTH_PERCENT,
            'width' => 100 * 50
        ]);

        $table->addRow();

        $c1 = $table->addCell('30%', $styleNoBorderCell);
        $c1->addText('Nơi nhận:', array_merge($generalStyleBold, ['italic' => true]));
        $c1->addText('- Như trên;', array_merge($generalStyle, ['size' => 8]));
        $c1->addText('- Lưu VT.', array_merge($generalStyle, ['size' => 8]));

        $c1 = $table->addCell('70%', $styleNoBorderCell);

        $c1->addText($company_name, $generalStyleBold, ['alignment' => Jc::CENTER]);
        $c1->addText('(Ký và ghi rõ họ tên, đóng dấu)', $generalStyleItalic, ['alignment' => Jc::CENTER]);
        $c1->addTextBreak(4);
        $c1->addText('[HỌ TÊN NGƯỜI KÍ]', $generalStyle, ['alignment' => Jc::CENTER]);

        $section->addTextBreak(3);

        $storagePath = Storage::disk('public')->getDriver()->getAdapter()->getPathPrefix();

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');

        $objWriter->save($storagePath . '/' . $file_1);

        $templateProcessor = new TemplateProcessor(base_path('reports/template_report_foreign_2.docx'));

        $startDateOfQuarter = 'DATE_FORMAT(\'' . $filter_year . '-' . (array_chunk(range(1, 12), 4))[($filter_quarter - 1)][0] . '-01\', \'%Y-%m-%d\')';

        $dataRows = ClientEmployee::selectRaw('
            nationality,
            COUNT(IF(DATE_FORMAT(official_contract_signing_date, \'%Y-%m-%d\') < \'2020-01-01\', 1, NULL)) AS tong_so_luy_ke_tu_dau_nam,
            COUNT(IF((QUARTER(official_contract_signing_date) = \'' . $filter_quarter . '\') AND (YEAR(official_contract_signing_date) = ' . $filter_year . '), 1, NULL)) AS tong_so,
            COUNT(IF(DATEDIFF(' . $startDateOfQuarter . ', official_contract_signing_date) < 365, 1, NULL)) AS so_luong,
            ROUND(AVG(IF(DATEDIFF(' . $startDateOfQuarter . ', official_contract_signing_date) < 365, salary, NULL)), 0) AS luong_binh_quan
        ')
            ->groupBy('nationality')
            ->where('nationality', '<>', 'Viet Nam')
            ->where('client_id', $client->id)->get();

        if (!empty($dataRows)) {

            $templateProcessor->setValue('company_name', $company_name);
            $templateProcessor->setValue('quarter', $filter_quarter);
            $templateProcessor->setValue('year', $filter_year);

            $templateProcessor->cloneRow('stt', count($dataRows));


            foreach ($dataRows as $index => $d) {

                $dataPositions = ClientEmployee::select('foreigner_job_position', 'foreigner_contract_status')
                    ->where('nationality', $d->nationality)->where('client_id', $client->id)->get();

                $nha_quan_ly = collect($dataPositions)->where('foreigner_job_position', 'Nhà quản lý')->count();
                $giam_doc_dieu_hanh = collect($dataPositions)->where('foreigner_job_position', 'Giám đốc điều hành')->count();
                $chuyen_gia = collect($dataPositions)->where('foreigner_job_position', 'Chuyên gia')->count();
                $lao_dong_ky_thuat = collect($dataPositions)->where('foreigner_job_position', 'Lao động kỹ thuật')->count();

                $cap_gpld = collect($dataPositions)->where('foreigner_contract_status', 'Cấp GPLĐ')->count();
                $ko_thuoc_dien_cap_gpld = collect($dataPositions)->where('foreigner_contract_status', 'Không thuộc diện cấp GPLĐ')->count();
                $da_nop_ho_so = collect($dataPositions)->where('foreigner_contract_status', 'Đã nộp hồ sơ đề nghị cấp GPLĐ')->count();
                $chua_nop_ho_so = collect($dataPositions)->where('foreigner_contract_status', 'Chưa nộp hồ sơ đề nghị cấp GPLĐ')->count();
                $thu_hoi_gpld = collect($dataPositions)->where('foreigner_contract_status', 'Thu hồi GPLĐ')->count();

                $i = $index + 1;

                $templateProcessor->setValue('stt#' . $i, $i);
                $templateProcessor->setValue('quoc_tich#' . $i, $d->nationality);
                $templateProcessor->setValue('tong_so_luy_ke_tu_dau_nam#' . $i, $d->tong_so_luy_ke_tu_dau_nam);
                $templateProcessor->setValue('tong_so#' . $i, $d->tong_so);
                $templateProcessor->setValue('so_luong#' . $i, $d->so_luong);
                $templateProcessor->setValue('luong_binh_quan#' . $i, $d->luong_binh_quan);

                $templateProcessor->setValue('nha_quan_ly#' . $i, $nha_quan_ly);
                $templateProcessor->setValue('giam_doc_dieu_hanh#' . $i, $giam_doc_dieu_hanh);
                $templateProcessor->setValue('chuyen_gia#' . $i, $chuyen_gia);
                $templateProcessor->setValue('lao_dong_ky_thuat#' . $i, $lao_dong_ky_thuat);

                $templateProcessor->setValue('cap_gpld#' . $i, $cap_gpld);
                $templateProcessor->setValue('ko_thuoc_dien_cap_gpld#' . $i, $ko_thuoc_dien_cap_gpld);
                $templateProcessor->setValue('da_nop_ho_so#' . $i, $da_nop_ho_so);
                $templateProcessor->setValue('chua_nop_ho_so#' . $i, $chua_nop_ho_so);
                $templateProcessor->setValue('thu_hoi_gpld#' . $i, $thu_hoi_gpld);
            }
        }

        $templateProcessor->saveAs($storagePath . '/' . $file_2);

        $zipFile = uniqid($client->code . '_') . '.zip';

        $zipFilePath = $storagePath . $zipFile;

        $zip = new ZipArchive();
        $zip->open($zipFilePath, ZipArchive::CREATE);

        $zip->addFile($storagePath . '/' . $file_1, $file_1);
        $zip->addFile($storagePath . '/' . $file_2, $file_2);

        $zip->close();

        $response = array(
            'name' => $zipFile,
            'file' => "data:application/zip;base64," . base64_encode(file_get_contents($zipFilePath))
        );

        unlink($storagePath . '/' . $file_1);
        unlink($storagePath . '/' . $file_2);
        unlink($zipFilePath);

        return json_encode($response);
    }

    public function exportForeignWorkerReport($root, array $args)
    {
        $client_id = $args['client_id'];

        $from_date = $args['from_date'];
        $to_date = $args['to_date'];

        $templateExport = 'ClientEmployeeExportTemplate/bao_cao_tinh_hinh_su_dung_nguoi_nuoc_ngoai.xlsx';

        $fileName = $client_id . '/' . "report_foreign_worker_{$from_date}_{$to_date}.xlsx";

        $pathFile = 'ClientEmployeeReport/' . $fileName;

        $errors = false;

        try {

            if (!Storage::disk('local')->missing($templateExport)) {

                Excel::store((new ClientEmployeeForeignReportExport($client_id, $from_date, $to_date, $templateExport, $pathFile)), $pathFile, 'minio');
            } else {
                throw new CustomException(
                    'File template báo cáo bị mất',
                    'ValidationException'
                );
            }
        } catch (CustomException $e) {
            $errors = [
                'error' => true,
                'message' => $e->getMessage()
            ];
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = [
                'error' => true,
                'message' => 'File template bị lỗi'
            ];
        }

        if ($errors) {
            return json_encode($errors);
        } else {

            $response = [
                'error' => false,
                'name' => $fileName,
                'file' => MediaHelper::getPublicTemporaryUrl($pathFile)
            ];

            return json_encode($response);
        }
    }

    public function exportStatusWorkerReport($root, array $args)
    {
        $client_id = $args['client_id'];
        $from_date = $args['from_date'];
        $to_date = $args['to_date'];

        $templateExport = 'ClientEmployeeExportTemplate/bao_cao_tinh_hinh_su_dung_lao_dong.xlsx';

        $fileName = $client_id . '/' . "report_status_worker_{$from_date}_{$to_date}.xlsx";

        $pathFile = 'ClientEmployeeReport/' . $fileName;

        $errors = false;

        try {

            if (!Storage::disk('local')->missing($templateExport)) {

                Excel::store((new ClientEmployeeStatusExport($client_id, $from_date, $to_date, $templateExport, $pathFile)), $pathFile, 'minio');
            } else {
                throw new CustomException(
                    'File template báo cáo bị mất',
                    'ValidationException'
                );
            }
        } catch (CustomException $e) {
            $errors = [
                'error' => true,
                'message' => $e->getMessage()
            ];
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = [
                'error' => true,
                'message' => 'File template bị lỗi'
            ];
        }

        if ($errors) {
            return json_encode($errors);
        } else {

            $response = [
                'error' => false,
                'name' => $fileName,
                'file' => MediaHelper::getPublicTemporaryUrl($pathFile)
            ];

            return json_encode($response);
        }
    }

    public function exportContact($root, array $args)
    {
        $client_id = $args['client_id'];

        $user = Auth::user();

        if (!$user->isInternalUser()) {
            if ($user->client_id != $client_id || !$user->hasAnyPermission(['manage-contract', 'manage-employee'])) {
                throw new HumanErrorException(__("error.permission"));
            }
        } else {
            $role = $user->getRole();
            if (
                $role != Constant::ROLE_INTERNAL_DIRECTOR
                && $user->hasDirectPermission('manage_clients')
                && $user->iGlocalEmployee->isAssignedFor($client_id)
            ) {
                throw new HumanErrorException(__("error.permission"));
            }
        }


        $client = Client::select('*')->where('id', $client_id)->first();

        $templateExport = 'ClientEmployeeContactExportTemplate/client_employee_contact_export_template.xlsx';

        $fileName = $client->code . '_client_employee_contact_export.xlsx';

        $pathFile = 'ClientEmployeeContactExport/' . $fileName;

        $groupIds = !empty($args['group_ids']) ? $args['group_ids'] : [];

        Storage::disk('local')->put(
            $templateExport,
            Storage::disk('minio')->get($templateExport)
        );

        Excel::import(new ClientEmployeeContacImport($client_id, $templateExport, $pathFile, $groupIds), storage_path('app/' . $templateExport));

        Storage::disk('local')->delete($templateExport);

        $response = [
            'name' => $fileName,
            'file' => MediaHelper::getPublicTemporaryUrl($pathFile)
        ];

        return json_encode($response);
    }

    // Tong luong group theo Department and Position
    public function sumSalaryByDepartmentOrPosition($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        // Default set group by department and position
        $clientId = isset($args['client_id']) ? $args['client_id'] : '';
        $filterMonth = isset($args['filter_month']) ? $args['filter_month'] : '';
        $filterYear = isset($args['filter_year']) ? $args['filter_year'] : '';
        $filterDepartment = isset($args['filter_department']) ? $args['filter_department'] : '';
        $filterPosition = isset($args['filter_position']) ? $args['filter_position'] : '';

        /** @var Builder $query */
        $query = ClientEmployeeSalary::query()
            ->select(['department', 'position', DB::raw('SUM(salary) as salary')])
            ->groupBy(['department', 'position'])
            ->authUserAccessible();


        if ($clientId && $filterMonth && $filterYear) {
            $query->where('client_id', $clientId)
                ->whereYear('created_at', $filterYear)
                ->whereMonth('created_at', $filterMonth);
        } else {
            // empty result
            return [];
        }

        if ($filterDepartment) {
            $query->where('department', $filterDepartment);
        }

        if ($filterPosition) {
            $query->where('position', $filterPosition);
        }

        return $query->get()->map(function ($row) {
            return new DepartmentSalarySummary($row->toArray());
        });
    }

    public function historicalSalaryStatistics($root, array $args): ?string
    {
        $query = ClientEmployeeSalaryHistory::query()
            ->where('client_employee_id', $args['id'])->orderBy('created_at', 'ASC');
        return $query->get();
    }

    public function clientEmployeeSalaries($root, array $args)
    {
        $clientId = $args['client_id'];
        $filterPosition = isset($args['filter_position']) ? $args['filter_position'] : '';
        $filterDepartment = isset($args['filter_department']) ? $args['filter_department'] : '';
        $filterMonth = isset($args['filter_month']) ? $args['filter_month'] : '';
        $filterYear = isset($args['filter_year']) ? $args['filter_year'] : '';

        $query = ClientEmployeeSalary::query()
            ->select(['id', 'client_employee_id', 'department', 'position', 'salary', 'title', 'created_at'])
            ->where('client_id', $clientId)->with('clientEmployee')
            ->authUserAccessible();

        if ($filterMonth && $filterYear) {
            $query->whereYear('created_at', $filterYear)
                ->whereMonth('created_at', $filterMonth);
        }

        if ($filterDepartment) {
            $query->where('department', $filterDepartment);
        }

        if ($filterPosition) {
            $query->where('position', $filterPosition);
        }

        return $query->get();
    }

    public function reviewersClientAssignments($root, array $args)
    {

        $user = Auth::user();

        $clientId = $args['client_id'];
        $staffId = $args['staff_id'];

        $data = ClientAssignment::where('client_id', $args['client_id'])->where('staff_id', $args['staff_id'])->with('leader')->orderBy('created_at', 'DESC')->get();

        $clientWorkflowSetting = ClientWorkflowSetting::select('manage_user')->where('client_id', '=', $user->client_id)->first();

        $employees = [];

        if (!empty($data) && ($clientWorkflowSetting['manage_user'] == 'mini')) {

            foreach ($data as $em) {
                if (in_array($em->leader->role, ['manager'])) {
                    $employees[] = $em;
                }
            }
        } else {
            $employees = $data;
        }

        return $employees;
    }

    public function updateProfile($root, array $args)
    {
        $bankName = isset($args['bank_name']) ? $args['bank_name'] : '';

        ClientEmployee::whereId($args['id'])->update([
            'marital_status' => $args['marital_status'],
            'contact_phone_number' => $args['contact_phone_number'],
            'nationality' => $args['nationality'],
            'bank_name' => $bankName
        ]);

        return ClientEmployee::whereId($args['id'])->first();
    }

    public function updateYearPaidLeave($root, array $args)
    {
        // $employee = ClientEmployee::where('id', $args['id'])->update([
        //     'year_paid_leave_count' => $args['value']
        // ]);

        // $paidLeaveChange = new PaidLeaveChange();

        // $paidLeaveChange->fill([
        //     'changed_ammount' =>
        // ]);

        // $paidLeaveChange->save();
    }

    public function resetPassword($root, array $args)
    {

        $clientEmployee = null;

        if (isset($args['filter_code']) && isset($args['filter_client_id'])) {

            $clientEmployee = ClientEmployee::where('code', $args['filter_code'])->where('client_id', $args['filter_client_id'])->with('client')->first();
        } else {

            $clientEmployee = ClientEmployee::where('id', $args['id'])->with('client')->first();
        }

        if ($clientEmployee) {
            $user = User::find($clientEmployee->user_id);

            if (!$user) {
                if (isset($args['filter_code'])) {

                    throw new CustomException(
                        $args['filter_code'] . ' : was invalid.',
                        'ErrorException'
                    );
                } else {

                    throw new CustomException(
                        'This user was invalid',
                        'ErrorException'
                    );
                }
            }

            $random_password = Str::random(10);
            $user->changed_random_password = 0;
            $user->password = Hash::make($random_password);
            $user->update();

            SendActivationUserEmail::dispatch(
                $clientEmployee->client,
                $user,
                $clientEmployee,
                $random_password
            );

            // $user->notify(new ClientEmployeeResetPasswordNotification($user, $clientEmployee, $random_password));
            return $clientEmployee;
        } else {

            if (isset($args['filter_code'])) {

                throw new CustomException(
                    $args['filter_code'] . ' : was invalid.',
                    'ErrorException'
                );
            } else {

                throw new CustomException(
                    'This user was invalid',
                    'ErrorException'
                );
            }
        }
    }

    public function resetCustomerPassword($root, array $args)
    {
        $query = ClientEmployee::query()->authUserAccessible();

        if (isset($args['filter_code'])) {
            $query->where('code', $args['filter_code']);
        }

        if (isset($args['filter_client_id'])) {
            $query->where('client_id', $args['filter_client_id']);
        }

        if (isset($args['id'])) {
            $query->where('id', $args['id']);
        }

        $clientEmployee = $query->first();

        if ($clientEmployee) {
            $user = User::find($clientEmployee->user_id);

            if (!$user) {
                if (isset($args['filter_code'])) {
                    throw new CustomException(
                        $args['filter_code'] . ' : was invalid.',
                        'ErrorException'
                    );
                } else {
                    throw new CustomException(
                        'This user was invalid',
                        'ErrorException'
                    );
                }
            }

            $random_password = Str::random(10);
            $user->changed_random_password = 0;
            $user->password = Hash::make($random_password);
            $user->update();

            SendCustomerResetPasswordEmail::dispatch(
                $clientEmployee->client,
                $user,
                $clientEmployee,
                $random_password
            );

            return $clientEmployee;
        } else {
            if (isset($args['filter_code'])) {
                throw new CustomException(
                    $args['filter_code'] . ' : was invalid.',
                    'ErrorException'
                );
            } else {
                throw new CustomException(
                    'This user was invalid',
                    'ErrorException'
                );
            }
        }
    }

    public function resetPasswordAll($root, array $args)
    {
        $clientEmployee = ClientEmployee::where('code', $args['id'])->orWhere('code', $args['id'])->first();

        if ($clientEmployee) {
            $user = User::find($clientEmployee->user_id);

            $random_password = Str::random(10);
            $user->changed_random_password = 0;
            $user->password = Hash::make($random_password);
            $user->update();

            $user->notify(new ClientEmployeeResetPasswordNotification($user, $clientEmployee, $random_password));

            return $clientEmployee;
        } else {
            throw new HumanErrorException(__('error.not_found', ['name' => __('employee')]));
        }
    }

    public function changeRandomPassword($root, array $args)
    {
        /** @var User */
        $user = Auth::user();

        $user->password = Hash::make($args['password']);
        $user->changed_random_password = 1;
        $user->update();

        return $user;
    }

    public function clientEmployeeDepartments($root, array $args)
    {
        $departments = CLientEmployee::selectRaw('department, COUNT(id) AS employees')->where('client_id', $args['client_id'])->groupBy('department')->orderBy('department', 'asc')->get();

        return json_encode($departments);
    }

    public function clientEmployeePositions($root, array $args)
    {
        $positions = CLientEmployee::selectRaw('position, COUNT(id) AS employees')->where('client_id', $args['client_id'])->groupBy('position')->get();

        return json_encode($positions);
    }

    public function requestClientEmployeeAccessToken($root, array $args)
    {

        $user = Auth::user();

        $clientEmployee = ClientEmployee::select('*')->where('id', $args['id'])->authUserAccessible()->first();

        if (empty($clientEmployee) || !$clientEmployee->user_id) return 'fail';

        $user = User::find($clientEmployee->user_id);

        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;

        $token->save();

        $url = config('app.customer_url', '') . '/dang-nhap?token=' . json_encode([
            'access_token' => $tokenResult->accessToken,
            'refresh_token' => '',
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString()
        ]);

        return $url;
    }

    private function clientEmployeeSummaryQuery($date = "", $filters = [])
    {
        $statuses = !empty($filters['statuses']) ? explode(',', $filters['statuses']) : [];
        $departments = !empty($filters['departments']) ? explode(',', $filters['departments']) : [];
        $types = !empty($filters['types']) ? explode(',', $filters['types']) : [];
        $nameOrCode = !empty($filters['nameOrId']) ? $filters['nameOrId'] : "";
        $groupIds = !empty($filters['group_ids']) ? $filters['group_ids'] : [];
        $clientEmployeeIds = !empty($filters['client_employee_ids']) ? $filters['client_employee_ids'] : [];
        $clientId = Auth::user()->client_id;
        $client = Client::find($clientId);

        $wtr = WorkTimeRegisterPeriod::select(
            'clients.standard_work_hours_per_day',
            'client_employees.full_name',
            'client_employees.code',
            'client_employees.id',
            'client_departments.department',
            'client_position.name as position',
            'work_time_registers.status',
            'work_time_registers.type',
            'work_time_registers.sub_type',
            'work_time_registers.reason',
            'work_time_registers.client_employee_id',
            'work_time_register_periods.start_time',
            'work_time_register_periods.end_time',
            'work_time_register_periods.start_break',
            'work_time_register_periods.end_break',
            'work_time_register_periods.start_break_next_day',
            'work_time_register_periods.end_break_next_day',
            'work_time_register_periods.type_register',
            'work_time_register_periods.date_time_register',
            'work_time_register_periods.id as period_id',
            'work_time_register_periods.worktime_register_id',
            'work_time_register_periods.so_gio_tam_tinh',
            'work_time_register_periods.next_day',
            'work_time_register_periods.is_cancellation_approval_pending',
            'work_schedules.check_in',
            'work_schedules.check_out',
            'work_schedules.schedule_date',
            'work_schedules.id as work_schedule_id'
        )
            ->join('work_time_registers', 'work_time_register_periods.worktime_register_id', 'work_time_registers.id')
            ->join('client_employees', 'client_employees.id', 'work_time_registers.client_employee_id')
            ->join('client_departments', 'client_departments.id', 'client_employees.client_department_id')
            ->join('client_position', 'client_position.id', 'client_employees.client_position_id')
            ->join('work_schedule_group_templates', 'work_schedule_group_templates.id', 'client_employees.work_schedule_group_template_id')
            ->join('clients', 'clients.id', 'client_employees.client_id')
            ->join('work_schedule_groups', 'work_schedule_groups.work_schedule_group_template_id', '=', 'work_schedule_group_templates.id')
            ->join('work_schedules', function ($join) use ($date) {
                $join->on('work_schedules.work_schedule_group_id', '=', 'work_schedule_groups.id')
                    ->where('work_schedules.schedule_date', $date);
            })
            ->whereDate('work_time_register_periods.date_time_register', $date)
            ->whereIn('work_time_registers.status', ['pending', 'approved'])
            ->where('client_employees.client_id', $clientId)
            ->whereNull('client_employees.deleted_at')
            ->where(function ($subQuery) use ($date) {
                $subQuery->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING)
                    ->orWhere(function ($subQueryLevelTwo) use ($date) {
                        $subQueryLevelTwo->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                            ->where('client_employees.quitted_at', '>', now()->format('Y-m-d H:i:s'));
                    });
            });

        if ($statuses) {
            $wtr = $wtr->whereIn('work_time_registers.status', $statuses);
            if (in_array(Constant::WAIT_CANCEL_APPROVE, $statuses)) {
                $wtr = $wtr->orWhere(function ($subQuery) use ($date) {
                    $subQuery->where('date_time_register', $date)
                        ->where('is_cancellation_approval_pending', 1);
                });
            } else {
                $wtr = $wtr->where(function ($subQuery) use ($date) {
                    $subQuery->where('date_time_register', $date)
                        ->where('is_cancellation_approval_pending', 0);
                });
            }
        }
        if ($departments) {
            $wtr = $wtr->whereIn('client_employees.client_department_id', $departments);
        }
        if ($types) {
            $wtr = $wtr->whereIn('work_time_registers.sub_type', $types);
        }
        if ($nameOrCode) {
            $wtr = $wtr->where(function ($query) use ($nameOrCode) {
                $query->where('client_employees.full_name', 'LIKE', '%' . $nameOrCode . '%')
                    ->orWhere('client_employees.code', 'LIKE', '%' . $nameOrCode . '%');
            });
        }
        if (!empty($clientEmployeeIds)) {
            $wtr = $wtr->whereIn('client_employee_id', $clientEmployeeIds);
        }
        if (!empty($groupIds)) {
            $user = Auth::user();
            $listClientEmployeeId = $user->getListClientEmployeeByGroupIds($user, $groupIds);
        }
        if (!empty($listClientEmployeeId)) {
            $wtr = $wtr->whereIn('client_employees.id', $listClientEmployeeId);
        }

        $wtr = $wtr->orderBy('work_schedules.schedule_date', 'asc')
            ->orderBy('client_employees.full_name', 'asc')
            ->get();

        $wtr->each(function ($item) use ($client) {
            $item->status = $item->getStatusAttribute();
            // set type_register = 0 (0: all day, 1: hour) when request application same work hour per day
            if ($item->type_register == 1 && $client->standard_work_hours_per_day == $item->duration) {
                $item->type_register = 0;
            }
        });

        //get all requests which are approves for 1 full day
        $rejectList = $wtr->where('type_register', 0)->pluck('client_employee_id');
        $ts = Timesheet::select(
            'client_employees.full_name',
            'client_employees.code',
            'client_departments.department',
            'client_position.name as position',
            'timesheets.client_employee_id',
            'timesheets.log_date as date_time_register',
            'clients.standard_work_hours_per_day',
            'timesheets.work_status as sub_type',
            'timesheets.state as status',
            'work_schedules.check_in',
            'work_schedules.check_out',
            'work_schedules.id as work_schedule_id'
        )
            ->join('client_employees', 'client_employees.id', 'timesheets.client_employee_id')
            ->join('clients', 'clients.id', 'client_employees.client_id')
            ->join('client_departments', 'client_departments.id', 'client_employees.client_department_id')
            ->join('client_position', 'client_position.id', 'client_employees.client_position_id')
            ->join('work_schedule_group_templates', 'work_schedule_group_templates.id', 'client_employees.work_schedule_group_template_id')
            ->join('work_schedule_groups', 'work_schedule_groups.work_schedule_group_template_id', '=', 'work_schedule_group_templates.id')
            ->join('work_schedules', function ($join) use ($date) {
                $join->on('work_schedules.work_schedule_group_id', '=', 'work_schedule_groups.id')
                    ->where('work_schedules.schedule_date', $date);
            })
            ->whereNull('client_employees.deleted_at')
            ->where(function ($subQuery) use ($date) {
                $subQuery->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING)
                    ->orWhere(function ($subQueryLevelTwo) use ($date) {
                        $subQueryLevelTwo->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                            ->where('client_employees.quitted_at', '>', now()->format('Y-m-d H:i:s'));
                    });
            });

        $ts = $ts->where(function ($subQuery) use ($date) {
            $subQuery->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING)
                ->whereNull('client_employees.deleted_at');

            $subQuery->orWhere(function ($subQueryLevelTwo) use ($date) {
                $subQueryLevelTwo->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                    ->where('client_employees.quitted_at', '>', now()->format('Y-m-d H:i:s'))
                    ->whereNull('client_employees.deleted_at');
            });
        });

        if ($statuses) {
            $ts = $ts->whereIn('timesheets.state', $statuses);
        }
        if ($departments) {
            $ts = $ts->whereIn('client_employees.client_department_id', $departments);
        }
        if ($types) {
            $ts = $ts->whereIn('timesheets.work_status', $types);
        }
        if ($nameOrCode) {
            $ts = $ts->where(function ($query) use ($nameOrCode) {
                $query->where('client_employees.full_name', 'LIKE', '%' . $nameOrCode . '%')
                    ->orWhere('client_employees.code', 'LIKE', '%' . $nameOrCode . '%');
            });
        }
        if (!empty($clientEmployeeIds)) {
            $ts = $ts->whereIn('client_employee_id', $clientEmployeeIds);
        }
        if (!empty($listClientEmployeeId)) {
            $ts = $ts->whereIn('client_employees.id', $listClientEmployeeId);
        }
        $ts = $ts->where('client_employees.client_id', $clientId)
            ->whereNotIn('client_employees.id', $rejectList)
            ->whereDate('timesheets.log_date', $date)->get();
        return $wtr->concat($ts);
    }

    public function getRequestName($type = '')
    {
        switch ($type) {
            case 'leave_early':
                return 'Leave early';
            case 'authorized_leave':
                return 'Paid leave';
            case 'unauthorized_leave':
                return 'Unpaid leave';
            case 'wfh':
                return 'Work from home';
            case 'ot_holiday':
                return 'Holiday overtime work';
            case 'ot_weekday':
                return 'Weekday overtime work';
            case 'ot_weekend':
                return 'Weekend overtime work';
            case 'business_trip':
                return 'Business Trip';
            case 'outside_working':
                return 'Outsite working';
            case 'other':
                return 'Other';
            default:
                return $type;
        }
    }

    public function clientEmployeeSummary($root, array $args)
    {
        $date = $args['log_date'];
        $filters['statuses'] = $args['filter_statues'] ?? "";
        $filters['departments'] = $args['filter_departments'] ?? "";
        $filters['types'] = $args['filter_types'] ?? "";
        $filters['nameOrId'] = $args['filter_full_name'] ?? "";
        $filters['client_employee_ids'] = !empty($args['client_employee_ids']) ? $args['client_employee_ids'] : [];
        $data = $this->clientEmployeeSummaryQuery($date, $filters);

        foreach ($data as $item) {
            if ($item->type_register != 1) {
                $item->standard_work_hours_per_day = WorkSchedule::find($item->work_schedule_id)->work_hours ?? $item->standard_work_hours_per_day;
            }
        }
        return $data;
    }

    public function exportClientEmployeeSummaryPdf($root, array $args)
    {
        $date = $args['date'];
        $filters['statuses'] = $args['filter_statues'] ?? "";
        $filters['departments'] = $args['filter_departments'] ?? "";
        $filters['types'] = $args['filter_types'] ?? "";
        $filters['nameOrId'] = $args['filter_full_name'] ?? "";
        $data = $this->clientEmployeeSummaryQuery($date, $filters);
        foreach ($data as $value) {
            $value->type_name = $this->getRequestName($value->sub_type);
            if ($value->type_register == 1) {
                $value->start_time = date('H:i', strtotime($value->period_start_time));
                $value->end_time = date('H:i', strtotime($value->period_end_time));
            } else {
                $value->standard_work_hours_per_day = WorkSchedule::find($value->work_schedule_id)->work_hours ?? $value->standard_work_hours_per_day;
                $value->start_time = date('H:i', strtotime($value->check_in));
                $value->end_time = date('H:i', strtotime($value->check_out));
            }
        }

        // Render pdf
        $view = view('exports.client-employee-summary-pdf')->with(['data' => $data]);
        $dompdf = new Dompdf();
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml($view);
        $dompdf->render();

        // Save to server
        $fileName = "client_employee_summary_" . time() . "_{$date}.pdf";
        $pathFile = 'ClientEmployeeSummary/' . $fileName;
        Storage::disk('minio')->put($pathFile, $dompdf->output());
        $url = Storage::temporaryUrl(
            $pathFile,
            Carbon::now()->addMinutes(config('app.media_temporary_time', 5))
        );

        $response = [
            'name' => $fileName,
            'url' => $url
        ];

        return json_encode($response);
    }

    /**
     * @throws HumanErrorException
     */
    public function exportSummary($root, array $args)
    {
        if ($args['type'] == 'leave_request') {
            $dataSummary = $this->getDataSummaryLeave($args, 'export');
        } else {
            $dataSummary = $this->getDataSummaryOtOrBusiness($args, 'export');
        }


        $params = [
            'data' => $dataSummary['data'],
            'range_month' => $dataSummary['range_month'] ?? [],
            'year' => $dataSummary['year'] ?? '',
            'type' => $args['type'],
            'header' => $dataSummary['header']
        ];
        // Export excel
        $extension = '.xlsx';
        $fileName = Str::upper($args['type']) . "_" . time() . $extension;
        $pathFile = 'ClientEmployeeSummary' . str_replace('_', '', ucwords($args['type'], '_')) . '/' . $fileName;
        Excel::store((new ClientEmployeeSummaryYearExport($params)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }

    public function getDataSummaryOtOrBusiness($args, $actionType = 'get')
    {
        $user = Auth::user();
        $employee = $user->clientEmployee;
        $clientId = $user->client_id;
        $groupIds = !empty($args['group_ids']) ? $args['group_ids'] : [];
        $now = Carbon::now()->format('Y-m-d');
        $type = $args['type'] ?? '';
        $subType = $args['sub_type'] ?? '';
        $category = $args['category'] ?? '';
        $rangeMonth = $args['range_month'] ?? [];
        if (empty($rangeMonth)) {
            $year = Carbon::now()->format('Y');
        } else {
            $year = DateTime::createFromFormat('m/Y', $rangeMonth[1])->format('Y');
        }
        $departmentFilter = !empty($args['department_filter']) ? $args['department_filter'] : [];
        $employeeFilter = !empty($args['employee_filter']) ? $args['employee_filter'] : '';
        $perPage = !empty($args['per_page']) ? $args['per_page'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 1;
        $filterYearByType = $args['year_type'] ?? '';
        $offset = ($page - 1) * $perPage;
        $clientSetting = ClientWorkflowSetting::where('client_id', $clientId)->first();

        $workScheduleGroups = [];
        if ($filterYearByType == 'by_calendar_year') {
            // Lặp qua từ tháng 1 đến tháng 12
            for ($month = 1; $month <= 12; $month++) {
                $formattedMonth = sprintf('%02d', $month); // Định dạng tháng thành dạng "01", "02",...
                $workScheduleGroups["$formattedMonth/$year"] = [
                    'timesheet_from' => Carbon::createFromDate($year, $month, 1)->format('Y-m-d'),
                    'timesheet_to' => $month == 2 ? Carbon::createFromDate($year, $month, 29)->format('Y-m-d') : Carbon::createFromDate($year, $month)->endOfMonth()->format('Y-m-d'),
                ];
            }
        } else {
            $workScheduleGroups = WorkScheduleGroup::where([
                ['work_schedule_group_template_id', $employee->work_schedule_group_template_id],
                ['client_id', $employee->client_id]
            ]);
            $months = [];
            if (!empty($rangeMonth)) {
                $startMonth = Carbon::createFromFormat('m/Y', $rangeMonth[0]);
                $endMonth = Carbon::createFromFormat('m/Y', $rangeMonth[1]);

                while ($startMonth <= $endMonth) {
                    $months[] = $startMonth->format('m/Y');
                    $startMonth->addDay(28);
                }
                $workScheduleGroups->whereIn('name', $months);
            } else {
                $workScheduleGroups->whereYear('timesheet_to', $year);
            }

            $workScheduleGroups = $workScheduleGroups->orderBy('timesheet_from')->get()->keyBy('name');
        }

        if (empty($workScheduleGroups)) return;

        $stringColumn = null;
        switch ($type) {
            case Constant::TYPE_OT:
            case Constant::TYPE_MAKEUP:
                // Render string column to summary hours of type
                $stringColumn = $type == Constant::TYPE_OT ? 'overtime_hours' : 'makeup_hours';
                break;
            case Constant::TYPE_BUSINESS:
                // Render string column to summary hours of type
                switch ($subType) {
                    case Constant::TYPE_BUSINESS_TRIP:
                        $stringColumn = 'mission_hours';
                        if ($clientSetting->enable_transportation_request && !empty($category)) {
                            $stringColumn = "mission_{$category}_hours";
                        }
                        break;
                    case Constant::TYPE_OUTSIDE_WORKING:
                        $stringColumn = 'outside_working_hours';
                        break;
                    case Constant::TYPE_WFH:
                        $stringColumn = 'wfh_hours';
                        break;
                    case Constant::TYPE_OTHER_BUSINESS:
                        $stringColumn = 'other_business_hours';
                        break;
                    default:
                        break;
                }
            default:
                break;
        }

        // Get startDate and endDate of the year
        if ($workScheduleGroups instanceof Collection) {
            $startMonth = $workScheduleGroups->first();
            $endMonth = $workScheduleGroups->last();
            $startDateOfTheYear = Carbon::parse($startMonth->timesheet_from)->toDateString();
            $endDateOfTheYear = Carbon::parse($endMonth->timesheet_to)->toDateString();
            $rangeMonth = [$startMonth->name, $endMonth->name];
            $header = $workScheduleGroups->keys();
        } else {
            $startDateOfTheYear =  Carbon::parse(reset($workScheduleGroups)['timesheet_from'])->toDateString();
            $endDateOfTheYear = Carbon::parse(end($workScheduleGroups)['timesheet_to'])->toDateString();
            $header = array_keys($workScheduleGroups);
        }

        // Get data
        $employees = ClientEmployee::where('client_id', $employee->client_id);

        // Filter by employee code or full_name
        if ($employeeFilter) {
            $employees->where(function ($query) use ($employeeFilter) {
                $query->where('full_name', 'LIKE', '%' . $employeeFilter . '%')
                    ->orWhere('code', 'LIKE', '%' . $employeeFilter . '%');
            });
        }

        // Filter by group id
        if (!empty($groupIds)) {
            $employees->whereHas('clientEmployeeGroupAssignments', function ($query) use ($groupIds) {
                $query->whereIn('client_employee_group_id', $groupIds);
            });
        }

        // Filter by department
        if (!empty($departmentFilter)) {
            $employees->whereIn('client_department_id', $departmentFilter);
        }

        // Action get data or export
        if ($actionType == 'get') {
            $countTotal = $employees->count();
            $employees->offset($offset)->limit($perPage);
        }

        $employees = $employees->with('timesheets', function ($query) use ($startDateOfTheYear, $endDateOfTheYear) {
            $query->where('log_date', '>=', $startDateOfTheYear)
                ->where('log_date', '<=', $endDateOfTheYear);
        })->get();

        // Calculation data
        $dataSummary = [];
        foreach ($employees as $employee) {
            $countUsedEveryMonth = 0;
            $keyEmployee = "[$employee->code] - $employee->full_name";
            $timeSheets = $employee->timesheets;

            foreach ($workScheduleGroups as $keyMonth => $workScheduleGroup) {
                $startTimeGroup = Carbon::parse($workScheduleGroup['timesheet_from'])->toDateString();
                $endTimeGroup = Carbon::parse($workScheduleGroup['timesheet_to'])->toDateString();
                $countIsUsed = 0;
                $countNotUsed = 0;

                if (strtotime($now) >= strtotime($endTimeGroup)) {
                    $dataSummary[$keyEmployee][$keyMonth] = round($timeSheets->where('log_date', '>=', $startTimeGroup)->where('log_date', '<=', $endTimeGroup)->sum($stringColumn), 2);
                    $hourUsedForMonth = $dataSummary[$keyEmployee][$keyMonth];
                } else if (strtotime($now) >= strtotime($startTimeGroup) && strtotime($now) <= strtotime($endTimeGroup)) {
                    $data = $timeSheets->whereBetween('log_date', [$startTimeGroup, $endTimeGroup]);
                    foreach ($data as $item) {
                        if ($now >= $item->log_date) {
                            $countIsUsed += $item->{$stringColumn};
                        } else {
                            $countNotUsed += $item->{$stringColumn};
                        }
                    }
                    $countIsUsed = round($countIsUsed, 2);
                    $countNotUsed = round($countNotUsed, 2);
                    $hourUsedForMonth = $countIsUsed + $countNotUsed;
                    if ($hourUsedForMonth) {
                        if ($countIsUsed > 0 && $countNotUsed) {
                            $dataSummary[$keyEmployee][$keyMonth] = "$countIsUsed($countNotUsed)";
                        } elseif ($countIsUsed > 0 && $countNotUsed == 0) {
                            $dataSummary[$keyEmployee][$keyMonth] = $countIsUsed;
                        } else {
                            $dataSummary[$keyEmployee][$keyMonth] = "($countNotUsed)";
                        }
                    } else {
                        $dataSummary[$keyEmployee][$keyMonth] = 0;
                    }
                } else {
                    $hourUsedForMonth = round($timeSheets->whereBetween('log_date', [$startTimeGroup, $endTimeGroup])->sum($stringColumn), 2);
                    $dataSummary[$keyEmployee][$keyMonth] = $hourUsedForMonth != 0 ? "(" . $hourUsedForMonth . ")" : 0;
                }

                $countUsedEveryMonth += floatval($hourUsedForMonth) ?? 0;
            }

            if (!empty($dataSummary[$keyEmployee])) {
                $dataSummary[$keyEmployee]['total'] = round(floatval($countUsedEveryMonth), 2);
            }

            if ($stringColumn == 'overtime_hours') {
                $overtimeWeekend = 0;
                $overtimeHoliday = 0;
                $totalOvertime = 0;

                $timeSheets->each(function ($item) use (&$totalOvertime, &$overtimeHoliday, &$overtimeWeekend) {
                    $totalOvertime += $item->overtime_hours;
                    if ($item->work_status == Timesheet::STATUS_NGHI_LE) {
                        $overtimeHoliday += $item->overtime_hours;
                    } elseif ($item->work_status == Timesheet::STATUS_NGHI_CUOI_TUAN) {
                        $overtimeWeekend += $item->overtime_hours;
                    }
                });

                $overtimeWeekday = $totalOvertime - $overtimeHoliday - $overtimeWeekend;

                $dataSummary[$keyEmployee] = [
                    'overtime_weekday' => $overtimeWeekday,
                    'overtime_weekend' => $overtimeWeekend,
                    'overtime_holiday' => $overtimeHoliday,
                ] + $dataSummary[$keyEmployee];
            }
        }

        // Action get data or export
        if ($actionType == 'get') {
            return [
                'data' => $dataSummary,
                'pagination' => [
                    'total' => $countTotal,
                    'per_page' => $perPage,
                    'current_page' => $page,
                ],
            ];
        } else {
            return  [
                'data' => $dataSummary,
                'header' => $header,
                'range_month' => $rangeMonth
            ];
        }
    }

    public function exportOvertimeSummary($root, array $args)
    {
        $user = Auth::user();
        $employee = $user->clientEmployee;

        $yearOriginal = $args['year'] ?? '';
        $groupIds = !empty($args['group_ids']) ? $args['group_ids'] : [];
        $now = Carbon::now()->format('Y-m-d');
        if (!$yearOriginal) {
            $yearOriginal = Carbon::now()->format('Y');
        }
        $clientSettingOt = OvertimeCategory::where([
            'client_id' => $user->client_id,
        ])
            ->first();
        if ($clientSettingOt) {
            $startDateInit = $clientSettingOt->start_date;
            $endDateInit = $clientSettingOt->end_date;
        } else {
            $startDateInit = $yearOriginal . '-01-01';
            $endDateInit = $yearOriginal . '-12-31';
        }
        $startDateArray = explode('-', $startDateInit);
        $endDateArray = explode('-', $endDateInit);
        $startDate = $yearOriginal . "-" . $startDateArray[1] . "-" . $startDateArray[2];
        $endDate = $yearOriginal . "-" . $endDateArray[1] . "-" . $endDateArray[2];
        if (strtotime($startDate) > strtotime($endDate)) {
            $endDate = $yearOriginal + 1 . "-" . $endDateArray[1] . "-" . $endDateArray[2];
        }

        $workScheduleGroups = WorkScheduleGroup::where(
            'work_schedule_group_template_id',
            $employee->work_schedule_group_template_id
        )
            ->where('client_id', $employee->client_id)
            ->where('timesheet_from', '>=', $startDate)
            ->where('timesheet_to', '<=', $endDate)
            ->orderBy('timesheet_from')
            ->get()->keyBy('name');
        $employeeWithTimeSheet = ClientEmployee::where('client_id', $employee->client_id);
        if (!empty($groupIds)) {
            $employeeWithTimeSheet = $employeeWithTimeSheet->whereHas('clientEmployeeGroupAssignments', function ($query) use ($groupIds) {
                $query->whereIn('client_employee_group_id', $groupIds);
            });
        }
        $employeeWithTimeSheet = $employeeWithTimeSheet->with('timesheets', function ($query) use ($startDate, $endDate) {
            $query->where('log_date', '>=', $startDate)
                ->where('log_date', '<=', $endDate);
        })->get();
        $listSummaryOt = [];
        foreach ($employeeWithTimeSheet as $employee) {
            $countUsedEveryMonth = 0;
            $keyEmployee = "[$employee->code] - $employee->full_name";
            foreach ($workScheduleGroups as $keyName => $workScheduleGroup) {
                $startTimeGroup = Carbon::parse($workScheduleGroup->timesheet_from)->toDateString();
                $endTimeGroup = Carbon::parse($workScheduleGroup->timesheet_to)->toDateString();
                $countIsUsed = 0;
                $countNotUsed = 0;
                $timesheetByEmployee = $employee->timesheets;

                if (strtotime($now) >= strtotime($endTimeGroup)) {
                    $listSummaryOt[$keyEmployee][$keyName] = round($timesheetByEmployee->where('log_date', '>=', $startTimeGroup)->where('log_date', '<=', $endTimeGroup)->sum('overtime_hours'), 2);
                    $overtimeUsedForMonth = $listSummaryOt[$keyEmployee][$keyName];
                } else if (strtotime($now) >= strtotime($startTimeGroup) && strtotime($now) <= strtotime($endTimeGroup)) {
                    $data = $timesheetByEmployee->whereBetween('log_date', [$startTimeGroup, $endTimeGroup]);
                    foreach ($data as $item) {
                        if ($now >= $item->log_date) {
                            $countIsUsed += $item->overtime_hours;
                        } else {
                            $countNotUsed += $item->overtime_hours;
                        }
                    }
                    $countIsUsed = round($countIsUsed, 2);
                    $countNotUsed = round($countNotUsed, 2);
                    $overtimeUsedForMonth = $countIsUsed + $countNotUsed;
                    if ($overtimeUsedForMonth) {
                        if ($countIsUsed > 0 && $countNotUsed) {
                            $listSummaryOt[$keyEmployee][$keyName] = "$countIsUsed($countNotUsed)";
                        } elseif ($countIsUsed > 0 && $countNotUsed == 0) {
                            $listSummaryOt[$keyEmployee][$keyName] = $countIsUsed;
                        } else {
                            $listSummaryOt[$keyEmployee][$keyName] = "($countNotUsed)";
                        }
                    } else {
                        $listSummaryOt[$keyEmployee][$keyName] = 0;
                    }
                } else {
                    $overtimeUsedForMonth = round($timesheetByEmployee->whereBetween('log_date', [$startTimeGroup, $endTimeGroup])->sum('overtime_hours'), 2);
                    $listSummaryOt[$keyEmployee][$keyName] = $overtimeUsedForMonth != 0 ? "(" . $overtimeUsedForMonth . ")" : 0;
                }

                $countUsedEveryMonth += floatval($overtimeUsedForMonth) ?? 0;
            }
            if (!empty($listSummaryOt[$keyEmployee])) {
                $listSummaryOt[$keyEmployee]['total'] = round(floatval($countUsedEveryMonth), 2);
            }
        }

        $params = [
            'data' => $listSummaryOt,
            'year' => $yearOriginal,
            'is_overtime' => true,
            'header' => $workScheduleGroups->keys()
        ];
        // Export excel
        $extension = '.xlsx';
        $fileName = Str::upper("OVERTIME_REQUEST") . "_" . time() . $extension;
        $pathFile = 'ClientEmployeeSummaryOvertime/' . $fileName;
        Excel::store((new ClientEmployeeSummaryYearExport($params)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }

    public function getDataSummaryLeave(array $args)
    {
        $user = Auth::user();
        $employee = $user->clientEmployee;

        $year = $args['year'] ?? '';
        $groupIds = !empty($args['group_ids']) ? $args['group_ids'] : [];
        $now = Carbon::now()->format('Y-m-d');

        if ($year) {
            $clientSettingLeave = LeaveCategory::where([
                ['client_id', $employee->client_id],
                ['year', $year]
            ])->first();
        } else {
            $clientSettingLeave = LeaveCategory::where([
                ['client_id', $employee->client_id],
                ['start_date', '<=', Carbon::now()->toDateString()],
                ['end_date', '>=', Carbon::now()->toDateString()]
            ])->first();
        }
        // Check exit
        if (!$clientSettingLeave) {
            return [];
        }
        $startDate = $clientSettingLeave->start_date;
        $endDate = $clientSettingLeave->end_date;

        $workScheduleGroups = WorkScheduleGroup::where(
            'work_schedule_group_template_id',
            $employee->work_schedule_group_template_id
        )
            ->where('client_id', $employee->client_id)
            ->where('timesheet_from', '>=', $startDate)
            ->where('timesheet_to', '<=', $endDate)->get()->keyBy('name')->sortBy('name');
        if (!$workScheduleGroups) {
            throw new HumanErrorException(__('error.not_work_schedule_of_this_year'));
        }

        $clientEmployeeLeaveManagement = ClientEmployeeLeaveManagement::whereHas('leaveCategory', function ($query) use ($clientSettingLeave) {
            $query->where('id', $clientSettingLeave->id);
        });
        $clientEmployeeLeaveManagement = $clientEmployeeLeaveManagement->whereHas('clientEmployee', function ($query) use ($employee, $groupIds) {
            $query->where('client_id', $employee->client_id);
            if (!empty($groupIds)) {
                $query->whereHas('clientEmployeeGroupAssignments', function ($query) use ($groupIds) {
                    $query->whereIn('client_employee_group_id', $groupIds);
                });
            }
        })->with('clientEmployeeLeaveManagementByMonth')->get()->keyBy('client_employee_id');
        $workScheduleGroupCurrent = $workScheduleGroups->where('timesheet_from', '<=', $now)->where('timesheet_to', '>=', $now)->first();
        if (!empty($workScheduleGroupCurrent)) {
            $condition = [
                'start' => $workScheduleGroupCurrent->timesheet_from,
                'end' => $workScheduleGroupCurrent->timesheet_to,
                'client_employee_id' => $clientEmployeeLeaveManagement->keys(),
                'type' => 'leave_request',
                'sub_type' => $clientSettingLeave->type,
                'category' => $clientSettingLeave->sub_type,
            ];
            $clientEmployeeWithPeriod = $this->clientEmployeeWithPeriod($condition);
        }
        $clientEmployee = ClientEmployee::where('client_id', $employee->client_id)->select('id', 'full_name', 'code')->get()->keyBy('id');
        $listSummaryLeave = [];
        foreach ($clientEmployeeLeaveManagement as $keyEmployee => $itemLeaveManagement) {
            if (isset($clientEmployee[$keyEmployee])) {
                $keyEmployeeFinal = '[' . $clientEmployee[$keyEmployee]->code . '] - ' . $clientEmployee[$keyEmployee]->full_name;
                $listSummaryLeave[$keyEmployeeFinal]['initial_hours'] = floatval($itemLeaveManagement->entitlement);
                $countUsedEveryMonth = 0;
                foreach ($workScheduleGroups as $keyName => $workScheduleGroup) {
                    $countIsUsed = 0;
                    $countNotUsed = 0;
                    $leaveUsedForMonth = 0;

                    if (strtotime($now) >= strtotime($workScheduleGroup->timesheet_to)) {
                        $clientEmployeeLeaveManagementByMonth = $itemLeaveManagement->clientEmployeeLeaveManagementByMonth->where('name', $keyName)->first();
                        if (!empty($clientEmployeeLeaveManagementByMonth)) {
                            $listSummaryLeave[$keyEmployeeFinal][$keyName] = floatval($clientEmployeeLeaveManagementByMonth->entitlement_used);
                        } else {
                            $listSummaryLeave[$keyEmployeeFinal][$keyName] = 0;
                        }
                        $leaveUsedForMonth = $listSummaryLeave[$keyEmployeeFinal][$keyName];
                    } else if (strtotime($now) >= strtotime($workScheduleGroup->timesheet_from) && strtotime($now) <= strtotime($workScheduleGroup->timesheet_to)) {
                        if (isset($clientEmployeeWithPeriod[$keyEmployee])) {
                            $data = $clientEmployeeWithPeriod[$keyEmployee]->worktimeRegisterPeriod;
                            foreach ($data as $item) {
                                if ($now >= $item->date_time_register) {
                                    $countIsUsed += $item->duration_for_leave_request;
                                } else {
                                    $countNotUsed += $item->duration_for_leave_request;
                                }
                            }
                            if ($countIsUsed > 0 && $countNotUsed) {
                                $listSummaryLeave[$keyEmployeeFinal][$keyName] = "$countIsUsed($countNotUsed)";
                            } elseif ($countIsUsed > 0 && $countNotUsed == 0) {
                                $listSummaryLeave[$keyEmployeeFinal][$keyName] = $countIsUsed;
                            } else {
                                $listSummaryLeave[$keyEmployeeFinal][$keyName] = "($countNotUsed)";
                            }
                        } else {
                            $listSummaryLeave[$keyEmployeeFinal][$keyName] = 0;
                        }
                        $leaveUsedForMonth = $countIsUsed + $countNotUsed;
                    } else {
                        $clientEmployeeLeaveManagementByMonth = $itemLeaveManagement->clientEmployeeLeaveManagementByMonth->where('name', $keyName)->first();
                        if (!empty($clientEmployeeLeaveManagementByMonth)) {
                            $leaveUsedForMonth = floatval($clientEmployeeLeaveManagementByMonth->entitlement_used);
                            $listSummaryLeave[$keyEmployeeFinal][$keyName] = $clientEmployeeLeaveManagementByMonth->entitlement_used != '0.00' ? "(" . floatval($clientEmployeeLeaveManagementByMonth->entitlement_used) . ")" : 0;
                        } else {
                            $listSummaryLeave[$keyEmployeeFinal][$keyName] = 0;
                        }
                    }

                    $countUsedEveryMonth += floatval($leaveUsedForMonth) ?? 0;
                }
                if (!empty($listSummaryLeave[$keyEmployeeFinal])) {
                    $listSummaryLeave[$keyEmployeeFinal]['remaining_hours'] = floatval($itemLeaveManagement->entitlement - $countUsedEveryMonth);
                }
            }
        }

        return [
            'data' => $listSummaryLeave,
            'year' => $clientSettingLeave->year,
            'type' => Constant::TYPE_LEAVE,
            'header' => $workScheduleGroups->keys()
        ];
    }

    public function exportClientEmployeeSummaryExcel($root, array $args)
    {
        $date = $args['date'];
        $filters['statuses'] = $args['filter_statues'] ?? "";
        $filters['departments'] = $args['filter_departments'] ?? "";
        $filters['types'] = $args['filter_types'] ?? "";
        $filters['nameOrId'] = $args['filter_full_name'] ?? "";
        $filters['group_ids'] = !empty($args['group_ids']) ? $args['group_ids'] : [];
        $data = $this->clientEmployeeSummaryQuery($date, $filters);
        $statuses = WorktimeRegister::statuses();
        foreach ($data as $value) {
            $value->status_name = isset($statuses[$value->status]) ? $statuses[$value->status] : $value->status;
            $value->type_name = $this->getRequestName($value->sub_type);
            if ($value->type_register == 1) {
                $value->start_time_display = date('H:i', strtotime($value->start_time));
                $addedDay = $value->next_day ? 1 : 0;
                $endTime = Carbon::parse($value->date_time_register . ' ' . $value->end_time)->addDays($addedDay);
                $value->end_time_display = $value->next_day ?
                    $endTime->format("d/m/Y H:i") :
                    $endTime->format("H:i");
            } else {
                $value->standard_work_hours_per_day = WorkSchedule::find($value->work_schedule_id)->work_hours ?? $value->standard_work_hours_per_day;
                $value->start_time_display = date('H:i', strtotime($value->check_in));
                $value->end_time_display = date('H:i', strtotime($value->check_out));
            }
        }

        // Export excel
        $extension = '.xlsx';
        $fileName = "SUMMARY_" . (date('Y-m-d', strtotime($date))) . "." . $extension;
        // $fileName = "SUMMARY_" . (date('Y-m-d', strtotime($fromDate))) . "-" . (date('Y-m-d', strtotime($toDate))) . "." .  $extension;
        $pathFile = 'ClientEmployeeSummaryExport/' . $fileName;
        Excel::store((new ClientEmployeeSummaryExport($data, $date)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];
        return json_encode($response);
    }

    public function updateClientEmployeeSalaryForm($root, array $args)
    {
        $id = $args['id'];
        $postData = $args;

        $clientEmployee = ClientEmployee::where('id', $id)->first();

        $customVariables = $args['custom_variables'];

        unset($postData['custom_variables']);

        $clientEmployee->update($postData);

        if ($customVariables) {
            foreach ($customVariables as $customVariable) {
                if ($customVariable['id']) {
                    ClientEmployeeCustomVariable::where('id', $customVariable['id'])->update([
                        'variable_value' => $customVariable['variable_value'],
                    ]);
                } else {
                    ClientEmployeeCustomVariable::create([
                        'client_employee_id' => $id,
                        'variable_name' => $customVariable['variable_name'],
                        'variable_value' => $customVariable['variable_value'],
                        'readable_name' => $customVariable['readable_name'],
                    ]);
                }
            }
        }

        return $clientEmployee;
    }

    public function clientEmployeesOvertime($root, array $args)
    {
        $filterStart = isset($args['filter_start']) ? $args['filter_start'] : null;
        $filterEnd = isset($args['filter_end']) ? $args['filter_end'] : null;
        $filterName = isset($args['filter_name']) ? $args['filter_name'] : null;
        $filterDepartment = isset($args['filter_department']) ? $args['filter_department'] : null;
        $orderby = isset($args['orderby']) ? $args['orderby'] : 'FULL_NAME';
        $order = isset($args['order']) ? $args['order'] : 'ASC';
        $perpage = isset($args['perpage']) ? $args['perpage'] : 200;
        $page = isset($args['page']) ? $args['page'] : '1';

        $data = ClientEmployee::select('*')
            ->where('client_id', $args['client_id'])
            ->whereHas('worktimeRegister', function ($query) use ($filterStart, $filterEnd) {
                if ($filterStart && $filterEnd) {
                    $query->whereDate('start_time', '>=', $filterStart)->whereDate('end_time', '<=', $filterEnd);
                }
                return $query->where('type', 'overtime_request')->where('status', 'approved');
            });
        if ($filterName) {
            $data = $data->where(function ($query) use ($filterName) {
                $query->where('full_name', 'LIKE', "%$filterName%")->orWhere('code', 'LIKE', "%$filterName%");
            });
        }
        if ($filterDepartment) {
            $data = $data->where('client_department_id', $filterDepartment);
        }

        if ($orderby != 'TOTAL_TIME') {
            $data = $data->orderBy($orderby, $order);
        }

        $data = $data->paginate($perpage, ['*'], 'page', $page);
        $list = [];
        foreach ($data as $item) {
            $agr = [
                'id' => $item->id,
                'code' => $item->code,
                'full_name' => $item->full_name,
                'department' => $item->client_department_name,
                'total_time' => $item->worktimeRegisterOvertime($filterStart, $filterEnd)
            ];
            $list[] = $agr;
        }

        if ($orderby == 'TOTAL_TIME') {
            if ($order == 'DESC') {
                usort($list, function ($item1, $item2) {
                    return $item1['total_time'] <= $item2['total_time'];
                });
            } else {
                usort($list, function ($item1, $item2) {
                    return $item1['total_time'] >= $item2['total_time'];
                });
            }
        }

        return [
            'data' => $list,
            'pagination' => [
                'total' => $data->total(),
                'count' => $data->count(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'total_pages' => $data->lastPage()
            ],
        ];
    }

    public function exportClientEmployeeOvertime($root, array $args): ?string
    {
        $client_id = $args['client_id'];
        $from = isset($args['from']) ? $args['from'] : null;
        $to = isset($args['to']) ? $args['to'] : null;
        $department = isset($args['department']) ? $args['department'] : null;
        $name = isset($args['name']) ? $args['name'] : null;
        $query = ClientEmployee::select('*')
            ->where('client_id', $client_id)
            ->whereHas('worktimeRegister', function ($query) use ($from, $to) {
                if ($from && $to) {
                    $query->whereDate('start_time', '>=', $from)->whereDate('end_time', '<=', $to);
                }
                return $query->where('type', 'overtime_request')->where('status', 'approved');
            });

        if ($name) {
            $query = $query->where(function ($query) use ($name) {
                $query->where('full_name', 'LIKE', "%$name%")->orWhere('code', 'LIKE', "%$name%");
            });
        }

        if ($department) {
            $query = $query->where('client_department_id', $department);
        }

        $list = $query->get();

        $data = [];
        foreach ($list as $item) {
            $agr = [
                'code' => $item->code,
                'full_name' => $item->full_name,
                'client_department_name' => $item->client_department_name,
                'total_time' => $item->worktimeRegisterOvertime($from, $to)
            ];
            $data[] = $agr;
        }
        usort($data, function ($item1, $item2) {
            return $item1['total_time'] <= $item2['total_time'];
        });
        // format datetime
        if ($from) {
            $from = Carbon::parse($from)->format('Y-m-d');
        }
        if (is_null($to)) {
            $to = Carbon::now()->toDateString();
        } else {
            $to = Carbon::parse($to)->format('Y-m-d');
        }
        $client = Client::select('*')->where('id', $client_id)->first();
        $fileName = $client->code . '_OVERTIME.xlsx';
        $pathFile = 'ClientEmployeeExport/' . $fileName;
        Excel::store((new ClientEmployeeOvertimeExport(
            $data,
            $from,
            $to
        )), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'file' => MediaHelper::getPublicTemporaryUrl($pathFile)
        ];

        return json_encode($response);
    }

    public function clientEmployeesTrainingSeminar($root, array $args)
    {

        $training_seminar = TrainingSeminar::where(['id' => $args['training_seminar_id'], 'client_id' => auth()->user()->client_id])->exists();

        if (auth()->user()->getRole() == Constant::ROLE_CLIENT_MANAGER && $training_seminar || auth()->user()->hasDirectPermission('manage-training') && $training_seminar) {

            $perpage = isset($args['perPage']) ? $args['perPage'] : 10;
            $page = isset($args['page']) ? $args['page'] : 1;
            $keywords = isset($args['keywords']) ? $args['keywords'] : "";
            $client_department_id = isset($args['client_department_id']) ? $args['client_department_id'] : "";
            $client_position_id = isset($args['client_position_id']) ? $args['client_position_id'] : "";

            $employeeTrainingSeminars = collect(ClientEmployeeTrainingSeminar::select('client_employee_id')
                ->where('client_id', auth()->user()->client_id)
                ->where('training_seminar_id', $args['training_seminar_id'])
                ->get()
                ->toArray());

            $employees = ClientEmployee::where("client_id", auth()->user()->client_id)
                ->where('status', '!=', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                ->whereNotIn("id", $employeeTrainingSeminars->pluck('client_employee_id'))
                ->orderBy("code", "ASC");

            if ($keywords) {
                $employees = $employees->where('code', 'LIKE', "%$keywords%")->orWhere('full_name', 'LIKE', "%$keywords%");
                if ($client_department_id) {
                    $employees = $employees->where('code', 'LIKE', "%$keywords%")->orWhere('full_name', 'LIKE', "%$keywords%")->where('client_department_id', $client_department_id);
                }
                if ($client_position_id) {
                    $employees = $employees->where('code', 'LIKE', "%$keywords%")->orWhere('full_name', 'LIKE', "%$keywords%")->where('client_position_id', $client_position_id);
                }
            }

            if ($client_department_id) {
                $employees = $employees->where('client_department_id', $client_department_id);
            }

            if ($client_position_id) {
                $employees = $employees->where('client_position_id', $client_position_id);
            }

            $employees = $employees->paginate($perpage, ['*'], 'page', $page);

            return [
                'data' => $employees,
                'pagination' => [
                    'total' => $employees->total(),
                    'count' => $employees->count(),
                    'per_page' => $employees->perPage(),
                    'current_page' => $employees->currentPage(),
                    'total_pages' => $employees->lastPage()
                ]
            ];
        } else {
            throw new CustomException(
                'You do not have permission to use this feature.',
                'ValidationException'
            );
        }
    }

    public function getClientEmployees($root, array $args)
    {
        $query = ClientEmployee::with(['clientPosition', 'clientDepartment', 'client', 'media', 'clientEmployeeGroupAssignment']);

        if (isset($args['onlyDefaultGroup'])) {
            $query->doesntHave('clientEmployeeGroupAssignment');
        }

        return $query;
    }

    public function clientEmployeeContracts($root, array $args)
    {
        $query = ClientEmployee::join('client_employee_contracts', 'client_employee_contracts.client_employee_id', 'client_employees.id')
            ->groupBy('client_employees.id')
            ->orderBy('client_employees.code', 'ASC')
            ->select('client_employees.*');

        return $query->authUserAccessible();
    }

    public function clientEmployeeWithApproveAdjustHours($root, array $args)
    {
        // Pre variable
        $perpage = $args['per_page'] ?? 10;
        $page = $args['page'] ?? 1;
        $startDate = $args['start_date'] ?? '';
        $endDate = $args['end_date'] ?? '';
        $isIndividual = $args['is_individual'] ?? false;
        $status = $args['status'] ?? '';
        $clientEmployeeIds = !empty($args['client_employee_ids']) ? $args['client_employee_ids'] : [];
        $userAuth = Auth::user();
        $userId = $userAuth->id;
        $clientId = $userAuth->client_id;

        // Override type
        if (!empty($args['type'])) {
            $type = [$args['type']];
        } else {
            $type = Constant::LIST_TYPE_ADJUST_HOURS;
        }

        // Check permission
        if (!$isIndividual) {
            $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $clientId)->first(['advanced_permission_flow']);
            $normalPermission = ['manage-timesheet'];
            $advancedPermission = ['advanced-manage-timesheet-adjust-hours-read'];
            if (!($userAuth->checkHavePermission($normalPermission, $advancedPermission, $clientWorkflowSetting->advanced_permission_flow))) {
                throw new AuthenticationException(__("error.permission"));
            }
        }

        // Filter status
        $listApproveByClientEmployee = Approve::whereIn('type', $type);
        // Condition status
        if ($status) {
            if ($status == 'pending') {
                $listApproveByClientEmployee = $listApproveByClientEmployee->whereNull('approved_at')->whereNull('declined_at');
            } elseif ($status == 'approved') {
                $listApproveByClientEmployee = $listApproveByClientEmployee->where('is_final_step', 1);
            } elseif ($status == 'declined') {
                $listApproveByClientEmployee = $listApproveByClientEmployee->whereNotNull('declined_at');
            }
        }

        //Filter by individual or company
        if ($isIndividual) {
            $listApproveByClientEmployee = $listApproveByClientEmployee->where('original_creator_id', $userId);
        } else {
            $listApproveByClientEmployee = $listApproveByClientEmployee->where('client_id', $userAuth->client_id);
            if (!empty($clientEmployeeIds)) {
                $listUserByClientEmployeeId = ClientEmployee::whereIn('id', $clientEmployeeIds)->get()->pluck('user_id');
                $listApproveByClientEmployee = $listApproveByClientEmployee->whereIn('original_creator_id', $listUserByClientEmployeeId);
            }
        }

        // Filter by range date
        if ($startDate && $endDate) {
            $listApproveByClientEmployee = $listApproveByClientEmployee->whereHas('timesheetTarget', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('log_date', [$startDate, $endDate]);
            });
        };

        $conditionTypeArrayWithQuotes = array_map(function ($value) {
            return "'$value'";
        }, $type);
        $conditionTypeString = implode(', ', $conditionTypeArrayWithQuotes);
        $maxSubQuery = DB::raw(
            "(SELECT approve_group_id, MAX(step) as max_step
            FROM approves
            WHERE client_id = '$clientId' AND type IN ($conditionTypeString)
            GROUP BY approve_group_id)
            as max_approves"
        );

        $listApproveByClientEmployee = $listApproveByClientEmployee->whereIn('id', function ($query) use ($maxSubQuery) {
            $query->select('id')
                ->from('approves')
                ->join($maxSubQuery, function ($join) {
                    $join->on('approves.approve_group_id', '=', 'max_approves.approve_group_id')
                        ->on('approves.step', '=', 'max_approves.max_step');
                });
        })->groupBy('approve_group_id')
            ->orderBy('created_at', 'DESC');

        $listApproveByClientEmployee = $listApproveByClientEmployee->paginate($perpage, ['*'], 'page', $page);
        return [
            'data' => $listApproveByClientEmployee,
            'pagination' => [
                'total' => $listApproveByClientEmployee->total(),
                'count' => $listApproveByClientEmployee->count(),
                'per_page' => $listApproveByClientEmployee->perPage(),
                'current_page' => $listApproveByClientEmployee->currentPage(),
                'total_pages' => $listApproveByClientEmployee->lastPage()
            ]
        ];
    }

    public function clientEmployeeWithApproveAdjustHoursNew($root, array $args)
    {
        // Pre variable
        $perpage = $args['per_page'] ?? 10;
        $page = $args['page'] ?? 1;
        $startDate = $args['start_date'] ?? '';
        $endDate = $args['end_date'] ?? '';
        $isIndividual = $args['is_individual'] ?? false;
        $statusFilter = $args['status'] ?? '';
        $employeeFilter = $args['employee_filter'] ?? '';
        $clientEmployeeIds = !empty($args['client_employee_ids']) ? $args['client_employee_ids'] : [];
        $departmentFilter = !empty($args['department_filter']) ? $args['department_filter'] : [];
        $userAuth = Auth::user();
        $userId = $userAuth->id;
        $clientId = $userAuth->client_id;

        // Override type
        $type = Constant::LIST_TYPE_ADJUST_HOURS;
        if (!empty($args['type'])) {
            $type = [$args['type']];
        }

        // Check permission
        if (!$isIndividual) {
            $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $clientId)->first(['advanced_permission_flow']);
            $normalPermission = ['manage-timesheet'];
            $advancedPermission = ['advanced-manage-timesheet-adjust-hours-read'];
            if (!($userAuth->checkHavePermission($normalPermission, $advancedPermission, $clientWorkflowSetting->advanced_permission_flow))) {
                throw new AuthenticationException(__("error.permission"));
            }
        }

        // Filter status
        $approves = Approve::whereIn('type', $type);

        // Condition status
        if ($statusFilter) {
            $approves->where(function ($query) use ($statusFilter) {
                if (in_array(Constant::PENDING_STATUS, $statusFilter)) {
                    $query->whereNull('approved_at')->whereNull('declined_at');
                }
                if (in_array(Constant::APPROVE_STATUS, $statusFilter)) {
                    $query->orWhere('is_final_step', 1);
                }
                if (in_array(Constant::DECLINED_STATUS, $statusFilter)) {
                    $query->orWhereNotNull('declined_at');
                }
            });
        }

        // Filter by individual or company
        if ($isIndividual) {
            $approves->where('original_creator_id', $userId);
            $listUserByClientEmployeeId = [$userId];
        } else {
            $approves->where('client_id', $userAuth->client_id);

            $clientEmployee = ClientEmployee::where('client_id', $userAuth->client_id);
            if (!empty($clientEmployeeIds)) {
                $clientEmployee->whereIn('id', $clientEmployeeIds);
            }
            // Filter by employee
            if (!empty($employeeFilter)) {
                $clientEmployee->where(function ($clientEmployee) use ($employeeFilter) {
                    $clientEmployee->where('code', 'LIKE', "%{$employeeFilter}%")
                        ->orWhere('full_name', 'LIKE', "%{$employeeFilter}%");
                });
            }
            // Filter by departmentFilter
            if (!empty($departmentFilter)) {
                $clientEmployee->whereIn('client_department_id', $departmentFilter);
            }

            $listUserByClientEmployeeId = $clientEmployee->get()->pluck('user_id');
            $approves->whereIn('original_creator_id', $listUserByClientEmployeeId);
        }

        // Filter by range date
        if ($startDate && $endDate) {
            $approves->whereHas('timesheetTarget', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('log_date', [$startDate, $endDate]);
            });
        }

        $conditionTypeArrayWithQuotes = array_map(function ($value) {
            return "'$value'";
        }, $type);
        $conditionTypeString = implode(', ', $conditionTypeArrayWithQuotes);
        $maxSubQuery = DB::raw(
            "(SELECT approve_group_id, MAX(step) as max_step
            FROM approves
            WHERE client_id = '$clientId' AND type IN ($conditionTypeString)
            GROUP BY approve_group_id)
            as max_approves"
        );

        // Get ID of
        $approves = $approves->whereIn('id', function ($query) use ($userAuth, $type, $startDate, $endDate, $listUserByClientEmployeeId, $statusFilter, $clientId, $maxSubQuery) {
            $query->select('id')
                ->from('approves')
                ->join($maxSubQuery, function ($join) {
                    $join->on('approves.approve_group_id', '=', 'max_approves.approve_group_id')
                        ->on('approves.step', '=', 'max_approves.max_step');
                })
                ->where('client_id', $clientId)
                ->whereIn('type', $type)
                ->whereIn('original_creator_id', $listUserByClientEmployeeId);

            if ($statusFilter) {
                $query->where(function ($subQuery) use ($statusFilter) {
                    if (in_array(Constant::PENDING_STATUS, $statusFilter)) {
                        $subQuery->whereNull('approved_at')->whereNull('declined_at');
                    }
                    if (in_array(Constant::APPROVE_STATUS, $statusFilter)) {
                        $subQuery->orWhere('is_final_step', 1);
                    }

                    if (in_array(Constant::DECLINED_STATUS, $statusFilter)) {
                        $subQuery->orWhereNotNull('declined_at');
                    }
                });
            };
            $query->groupBy('approves.approve_group_id');
        })
            ->orderBy('created_at', 'DESC')->paginate($perpage, ['*'], 'page', $page);

        return [
            'data' => $approves,
            'pagination' => [
                'total' => $approves->total(),
                'count' => $approves->count(),
                'per_page' => $approves->perPage(),
                'current_page' => $approves->currentPage(),
                'total_pages' => $approves->lastPage()
            ]
        ];
    }


    /**
     * @throws HumanErrorException
     * @throws AuthenticationException
     */
    public function clientEmployeeWithSummary($root, array $args)
    {
        if ($args['type'] == 'leave_request') {
            $dataResult = $this->clientEmployeeWithSummaryLeave($root, $args);
        } else {
            $dataResult = $this->getDataSummaryOtOrBusiness($args);
        }

        if (empty($dataResult)) return [];

        return $dataResult;
    }

    public function clientEmployeeWithSummaryOvertime($root, array $args)
    {
        $user = Auth::user();
        $employee = $user->clientEmployee;

        $year = $args['year'] ?? '';
        $groupIds = !empty($args['group_ids']) ? $args['group_ids'] : [];
        $employeeFilter = !empty($args['employee_filter']) ? $args['employee_filter'] : '';
        $perPage = !empty($args['per_page']) ? $args['per_page'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 1;
        $offset = ($page - 1) * $perPage;
        $now = Carbon::now()->format('Y-m-d');

        if (!$year) {
            $year = Carbon::now()->format('Y');
        }
        $clientSettingOt = OvertimeCategory::where([
            'client_id' => $user->client_id,
        ])
            ->first();
        if ($clientSettingOt) {
            $startDateInit = $clientSettingOt->start_date;
            $endDateInit = $clientSettingOt->end_date;
        } else {
            $startDateInit = $year . '-01-01';
            $endDateInit = $year . '-12-31';
        }
        $startDateArray = explode('-', $startDateInit);
        $endDateArray = explode('-', $endDateInit);
        $startDate = $year . "-" . $startDateArray[1] . "-" . $startDateArray[2];
        $endDate = $year . "-" . $endDateArray[1] . "-" . $endDateArray[2];
        if (strtotime($startDate) > strtotime($endDate)) {
            $year++;
            $endDate = $year . "-" . $endDateArray[1] . "-" . $endDateArray[2];
        }

        $workScheduleGroups = WorkScheduleGroup::where(
            'work_schedule_group_template_id',
            $employee->work_schedule_group_template_id
        )
            ->where('client_id', $employee->client_id)
            ->where('timesheet_from', '>=', $startDate)
            ->where('timesheet_to', '<=', $endDate)
            ->orderBy('timesheet_from')
            ->get()->keyBy('name');

        $employeeWithTimeSheet = ClientEmployee::where('client_id', $employee->client_id);
        if (!empty($employeeFilter)) {
            $employeeWithTimeSheet = $employeeWithTimeSheet->where(function ($query) use ($employeeFilter) {
                $query->where('full_name', 'LIKE', '%' . $employeeFilter . '%')
                    ->orWhere('code', 'LIKE', '%' . $employeeFilter . '%');
            });
        }
        if (!empty($groupIds)) {
            $employeeWithTimeSheet = $employeeWithTimeSheet->whereHas('clientEmployeeGroupAssignments', function ($query) use ($groupIds) {
                $query->whereIn('client_employee_group_id', $groupIds);
            });
        }
        $countTotal = $employeeWithTimeSheet->count();
        $employeeWithTimeSheet = $employeeWithTimeSheet->with('timesheets', function ($query) use ($startDate, $endDate) {
            $query->where('log_date', '>=', $startDate)
                ->where('log_date', '<=', $endDate);
        })->offset($offset)->limit($perPage)->get();
        $listSummaryOt = [];
        foreach ($employeeWithTimeSheet as $employee) {
            $countUsedEveryMonth = 0;
            $keyEmployee = "[$employee->code] - $employee->full_name";
            foreach ($workScheduleGroups as $keyName => $workScheduleGroup) {
                $startTimeGroup = Carbon::parse($workScheduleGroup->timesheet_from)->toDateString();
                $endTimeGroup = Carbon::parse($workScheduleGroup->timesheet_to)->toDateString();
                $countIsUsed = 0;
                $countNotUsed = 0;
                $timesheetByEmployee = $employee->timesheets;

                if (strtotime($now) >= strtotime($endTimeGroup)) {
                    $listSummaryOt[$keyEmployee][$keyName] = round($timesheetByEmployee->where('log_date', '>=', $startTimeGroup)->where('log_date', '<=', $endTimeGroup)->sum('overtime_hours'), 2);
                    $overtimeUsedForMonth = $listSummaryOt[$keyEmployee][$keyName];
                } else if (strtotime($now) >= strtotime($startTimeGroup) && strtotime($now) <= strtotime($endTimeGroup)) {
                    $data = $timesheetByEmployee->whereBetween('log_date', [$startTimeGroup, $endTimeGroup]);
                    foreach ($data as $item) {
                        if ($now >= $item->log_date) {
                            $countIsUsed += $item->overtime_hours;
                        } else {
                            $countNotUsed += $item->overtime_hours;
                        }
                    }
                    $countIsUsed = round($countIsUsed, 2);
                    $countNotUsed = round($countNotUsed, 2);
                    $overtimeUsedForMonth = $countIsUsed + $countNotUsed;
                    if ($overtimeUsedForMonth) {
                        if ($countIsUsed > 0 && $countNotUsed) {
                            $listSummaryOt[$keyEmployee][$keyName] = "$countIsUsed($countNotUsed)";
                        } elseif ($countIsUsed > 0 && $countNotUsed == 0) {
                            $listSummaryOt[$keyEmployee][$keyName] = $countIsUsed;
                        } else {
                            $listSummaryOt[$keyEmployee][$keyName] = "($countNotUsed)";
                        }
                    } else {
                        $listSummaryOt[$keyEmployee][$keyName] = 0;
                    }
                } else {
                    $overtimeUsedForMonth = round($timesheetByEmployee->whereBetween('log_date', [$startTimeGroup, $endTimeGroup])->sum('overtime_hours'), 2);
                    $listSummaryOt[$keyEmployee][$keyName] = $overtimeUsedForMonth != 0 ? "(" . $overtimeUsedForMonth . ")" : 0;
                }

                $countUsedEveryMonth += floatval($overtimeUsedForMonth) ?? 0;
            }
            if (!empty($listSummaryOt[$keyEmployee])) {
                $listSummaryOt[$keyEmployee]['total'] = round(floatval($countUsedEveryMonth), 2);
            }
        }
        return [
            'data' => $listSummaryOt,
            'pagination' => [
                'total' => $countTotal,
                'per_page' => $perPage,
                'current_page' => $page,
            ],
        ];
    }

    /**
     * @throws HumanErrorException
     * @throws AuthenticationException
     */
    public function clientEmployeeWithSummaryLeave($root, array $args)
    {
        $user = Auth::user();
        $employee = $user->clientEmployee;

        $groupIds = !empty($args['group_ids']) ? $args['group_ids'] : [];
        $employeeFilter = !empty($args['employee_filter']) ? $args['employee_filter'] : '';
        $perPage = !empty($args['per_page']) ? $args['per_page'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 1;
        $offset = ($page - 1) * $perPage;
        $now = Carbon::now()->format('Y-m-d');
        $year = $args['year'] ?? '';
        if ($year) {
            $clientSettingLeave = LeaveCategory::where([
                ['client_id', $employee->client_id],
                ['year', $year]
            ])->first();
        } else {
            $clientSettingLeave = LeaveCategory::where([
                ['client_id', $employee->client_id],
                ['start_date', '<=', Carbon::now()->toDateString()],
                ['end_date', '>=', Carbon::now()->toDateString()]
            ])->first();
        }
        //check exit
        if (!$clientSettingLeave) return [];

        $startDate = $clientSettingLeave->start_date;
        $endDate = $clientSettingLeave->end_date;

        $workScheduleGroups = WorkScheduleGroup::where([
            ['work_schedule_group_template_id', $employee->work_schedule_group_template_id],
            ['client_id', $employee->client_id],
            ['timesheet_from', '>=', $startDate],
            ['timesheet_to', '<=', $endDate]
        ])->orderBy('timesheet_from')->get()->keyBy('name');


        if (!$workScheduleGroups) {
            throw new HumanErrorException(__('error.not_work_schedule_of_this_year'));
        }

        $clientEmployeeLeaveManagement = ClientEmployeeLeaveManagement::where(function ($q) use ($args) {
            if (isset($args['client_employee_id'])) {
                $q->where('client_employee_id', $args['client_employee_id']);
            }
        })
            ->whereHas('leaveCategory', function ($query) use ($clientSettingLeave) {
                $query->where('id', $clientSettingLeave->id);
            });

        $clientEmployeeLeaveManagement = $clientEmployeeLeaveManagement->whereHas('clientEmployee', function ($query) use ($employee, $groupIds, $employeeFilter) {
            $query->where('client_id', $employee->client_id);
            if (!empty($employeeFilter)) {
                $query->where(function ($subQuery) use ($employeeFilter) {
                    $subQuery->where('full_name', 'LIKE', '%' . $employeeFilter . '%')
                        ->orWhere('code', 'LIKE', '%' . $employeeFilter . '%');
                });
            }
            if (!empty($groupIds)) {
                $query->whereHas('clientEmployeeGroupAssignments', function ($query) use ($groupIds) {
                    $query->whereIn('client_employee_group_id', $groupIds);
                });
            }
        });

        $countTotal = $clientEmployeeLeaveManagement->count();

        $clientEmployeeLeaveManagement = $clientEmployeeLeaveManagement->with('clientEmployeeLeaveManagementByMonth')->offset($offset)->limit($perPage)->get()->keyBy('client_employee_id');

        $clientEmployee = ClientEmployee::select('id', 'full_name', 'code')
            ->where('client_id', $employee->client_id)
            ->when(isset($args['client_employee_id']), function ($q) use ($args) {
                $q->where('id', $args['client_employee_id']);
            })
            ->get()->keyBy('id');

        $workScheduleGroupCurrent = $workScheduleGroups->where('timesheet_from', '<=', $now)->where('timesheet_to', '>=', $now)->first();
        if (!empty($workScheduleGroupCurrent)) {
            $condition = [
                'start' => $workScheduleGroupCurrent->timesheet_from,
                'end' => $workScheduleGroupCurrent->timesheet_to,
                'client_employee_id' => $clientEmployeeLeaveManagement->keys(),
                'type' => 'leave_request',
                'sub_type' => $clientSettingLeave->type,
                'category' => $clientSettingLeave->sub_type,
            ];
            $clientEmployeeWithPeriod = $this->clientEmployeeWithPeriod($condition);
        }
        $listSummaryLeave = [];
        foreach ($clientEmployeeLeaveManagement as $keyEmployee => $itemLeaveManagement) {
            if (isset($clientEmployee[$keyEmployee])) {
                $keyEmployeeFinal = '[' . $clientEmployee[$keyEmployee]->code . '] - ' . $clientEmployee[$keyEmployee]->full_name;
                $listSummaryLeave[$keyEmployeeFinal]['initial_hours'] = floatval($itemLeaveManagement->entitlement);
                $countUsedEveryMonth = 0;
                foreach ($workScheduleGroups as $keyName => $workScheduleGroup) {
                    $countIsUsed = 0;
                    $countNotUsed = 0;
                    $leaveUsedForMonth = 0;
                    if (strtotime($now) >= strtotime($workScheduleGroup->timesheet_to)) {
                        $clientEmployeeLeaveManagementByMonth = $itemLeaveManagement->clientEmployeeLeaveManagementByMonth->where('name', $keyName)->first();
                        if (!empty($clientEmployeeLeaveManagementByMonth)) {
                            $listSummaryLeave[$keyEmployeeFinal][$keyName] = round(floatval($clientEmployeeLeaveManagementByMonth->entitlement_used), 2);
                        } else {
                            $listSummaryLeave[$keyEmployeeFinal][$keyName] = 0;
                        }
                        $leaveUsedForMonth = $listSummaryLeave[$keyEmployeeFinal][$keyName];
                    } else if (strtotime($now) >= strtotime($workScheduleGroup->timesheet_from) && strtotime($now) <= strtotime($workScheduleGroup->timesheet_to)) {
                        if (isset($clientEmployeeWithPeriod[$keyEmployee])) {
                            $data = $clientEmployeeWithPeriod[$keyEmployee]->worktimeRegisterPeriod;
                            foreach ($data as $item) {
                                if ($now >= $item->date_time_register) {
                                    $countIsUsed += $item->duration_for_leave_request;
                                } else {
                                    $countNotUsed += $item->duration_for_leave_request;
                                }
                            }

                            if ($countIsUsed > 0 && $countNotUsed) {
                                $listSummaryLeave[$keyEmployeeFinal][$keyName] = "$countIsUsed($countNotUsed)";
                            } elseif ($countIsUsed > 0 && $countNotUsed == 0) {
                                $listSummaryLeave[$keyEmployeeFinal][$keyName] = $countIsUsed;
                            } else {
                                $listSummaryLeave[$keyEmployeeFinal][$keyName] = "($countNotUsed)";
                            }
                        } else {
                            $listSummaryLeave[$keyEmployeeFinal][$keyName] = 0;
                        }
                        $leaveUsedForMonth = $countIsUsed + $countNotUsed;
                    } else {
                        $clientEmployeeLeaveManagementByMonth = $itemLeaveManagement->clientEmployeeLeaveManagementByMonth->where('name', $keyName)->first();
                        if (!empty($clientEmployeeLeaveManagementByMonth)) {
                            $leaveUsedForMonth = round(floatval($clientEmployeeLeaveManagementByMonth->entitlement_used), 2);
                            $listSummaryLeave[$keyEmployeeFinal][$keyName] = $clientEmployeeLeaveManagementByMonth->entitlement_used != '0.00' ? "(" . floatval($clientEmployeeLeaveManagementByMonth->entitlement_used) . ")" : 0;
                        } else {
                            $listSummaryLeave[$keyEmployeeFinal][$keyName] = 0;
                        }
                    }

                    $countUsedEveryMonth += floatval($leaveUsedForMonth) ?? 0;
                }
                if (!empty($listSummaryLeave[$keyEmployeeFinal])) {
                    $listSummaryLeave[$keyEmployeeFinal]['remaining_hours'] = round(floatval($itemLeaveManagement->entitlement - $countUsedEveryMonth), 2);
                }
            }
        }

        return [
            'data' => $listSummaryLeave,
            'pagination' => [
                'total' => $countTotal,
                'per_page' => $perPage,
                'current_page' => $page,
            ],
        ];
    }

    public function updateTimesheetException($root, array $args)
    {
        return ClientEmployee::where('client_id', $args['client_id'])
            ->whereIn('id', $args['ids'])
            ->update(['timesheet_exception' => $args['timesheet_exception']]);
    }

    public function processSigning($root, array $args)
    {
        $token = SignApiHelper::loginSignApi();
        if (!$token) {
            throw new CustomException(
                'You are not authorized to access digital sign.',
                'AuthorizedException'
            );
        }

        if (!$args['id']) {
            throw new CustomException(
                'The given data was invalid.',
                'ErrorException'
            );
        }
        $maThamChieu = SignApiHelper::randomString();

        $contractId = $args['id'];
        $contractSql = Contract::find($contractId);

        // TODO replace with real contract pdf file
        $contractDemo = 'https://hattlaw.com/hd.pdf';
        $contractData = file_get_contents($contractDemo);
        $contractBase64 = base64_encode($contractData);

        if ($contractSql->status == 'new' || $contractSql->status == 'wait_for_company') {
            if (empty($contract->maThamChieu)) {
                $contractSql->ma_tham_chieu = $maThamChieu;
            }
            if ($contractSql->status == 'new') {
                $contractSql->status = 'wait_for_company';
            }
            $contractSql->save();

            $client = $this->getClientDetail($contractSql->client_id);
            //send data to API
            $tailieu = [
                "MaThamChieu" => $maThamChieu,
                "ThuTuKy" => 1,
                "HoVaTen" => __($client->company_name),
                "Email" => $client->company_contact_email ?? 'abc@test.com', //email cannot null
                "IsKyTheoToaDo" => "false",
                "ViTriHienThiChuKy" => 1,
                "ViTriTrangKy" => 1,
                "FileContent" => $contractBase64
            ];
            $companyDetails = array(
                "MaSoThue" => $client->company_license_no,
                "IsUsbToken" => "true",
                "TaiLieus" => [$tailieu]
            );

            $responseApiCompany = Http::withHeaders(['Content-Type' => 'application/json'])
                ->withToken($token)
                ->withBody(json_encode($companyDetails), 'application/json')
                ->post(Constant::API_DIGITAL_SIGN_URL . Constant::API_CREATE_DATA);
            if ($responseApiCompany->successful()) {
                $contractSql->status = 'wait_for_employee';
                $contractSql->company_signed_at = Carbon::now()->format('Y-m-d H:i:s');
                $contractSql->save();

                $response = [
                    'status' => 'success',
                    'contract' => $responseApiCompany['Data']['ThanhCongs']['Data']
                ];
                return json_encode($response, 200);
            }
        } elseif ($contractSql->status == 'wait_for_employee') {
            $user = Auth::user();

            $mediaItems = $contractSql->getMedia('UploadDigitalSign');
            if ($mediaItems) {
                $mediaTemporaryTime = config('app.media_temporary_time', 5);

                $temporaryUrl = $mediaItems[0]->getTemporaryUrl(Carbon::now()->addMinutes($mediaTemporaryTime));

                $signatureFile = file_get_contents($temporaryUrl, true);
                $signatureBase64 = base64_encode($signatureFile);
                $mediaItems[0]->delete();
            }

            // send to API create
            $tailieu = [
                "MaThamChieu" => $maThamChieu,
                "ThuTuKy" => 1,
                "HoVaTen" => $user->name,
                "Email" => $user->email,
                "IsKyTheoToaDo" => "false",
                "ViTriHienThiChuKy" => 1,
                "ViTriTrangKy" => 1,
                "FileContent" => $contractBase64
            ];
            $details = array(
                "IsUsbToken" => "false",
                "ChuKyImg" => $signatureBase64,
                "TaiLieus" => [$tailieu]
            );
            $responseApiEmployee = Http::withHeaders(['Content-Type' => 'application/json'])
                ->withToken($token)
                ->withBody(json_encode($details), 'application/json')
                ->post(Constant::API_DIGITAL_SIGN_URL . Constant::API_CREATE_DATA);

            if ($responseApiEmployee->successful()) {
                $contractSql->status = 'completed';
                $contractSql->employee_signed_at = Carbon::now()->format('Y-m-d H:i:s');
                $contractSql->save();

                $decoded = base64_decode($responseApiEmployee['Data']['ThanhCongs']['Data']);
                $file = 'contract.pdf';
                file_put_contents($file, $decoded);
                if (file_exists($file)) {
                    header('Access-Control-Allow-Origin: *');
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($file));
                    readfile($file);
                    exit;
                }
            }
        }
        return 'fail';
    }

    public function getClientDetail($clientId)
    {
        if (!$clientId) {
            throw new CustomException(
                'The given data was invalid.',
                'ErrorException'
            );
        }
        $client = Client::select('*')->whereId($clientId);
        if (empty($client)) {
            throw new CustomException(
                'Data was invalid.',
                'ErrorException'
            );
        }
        return $client->first();
    }

    public function createClientEmployeeSalaryHistory($root, array $args)
    {
        try {
            if (!isset($args['new_salary']) && !isset($args['new_fixed_allowance']) && !isset($args['new_allowance_for_responsibilities'])) {
                throw new CustomException(
                    'client_employee_salary_history.update.fail',
                    'ErrorException'
                );
            }

            $existedHistory = ClientEmployeeSalaryHistory::whereDate('start_date', $args['start_date'])
                ->where('client_employee_id', $args['client_employee_id'])->count();
            if ($existedHistory) {
                throw new CustomException(
                    'client_employee_salary_history.update.fail',
                    'ErrorException'
                );
            }

            $client_employee = ClientEmployee::where('id', $args['client_employee_id'])->first();

            // Check employee salary
            if ($client_employee) {
                // Check old and new salary
                if (isset($args['new_salary'])) {
                    $data['old_salary'] = $args['old_salary'];
                    $data['new_salary'] = $args['new_salary'];
                } elseif (isset($args['old_salary'])) {
                    $data['old_salary'] = $args['old_salary'];
                }

                // Check out the new fixed allowance
                if (isset($args['new_fixed_allowance'])) {
                    $data['old_fixed_allowance'] = $args['old_fixed_allowance'];
                    $data['new_fixed_allowance'] = $args['new_fixed_allowance'];
                } elseif (isset($args['old_fixed_allowance'])) {
                    $data['old_fixed_allowance'] = $args['old_fixed_allowance'];
                }

                // Check responsibility allowance salary
                if (isset($args['old_allowance_for_responsibilities']) && isset($args['new_allowance_for_responsibilities'])) {
                    $data['old_allowance_for_responsibilities'] = $args['old_allowance_for_responsibilities'];
                    $data['new_allowance_for_responsibilities'] = $args['new_allowance_for_responsibilities'];
                } elseif (isset($args['old_allowance_for_responsibilities'])) {
                    $data['old_allowance_for_responsibilities'] = $args['old_allowance_for_responsibilities'];
                }

                if (!empty($data)) {
                    $salary_history = ClientEmployeeSalaryHistory::where('client_employee_id', $args['client_employee_id'])->latest()->first();
                    // Check salary history
                    if ($salary_history) {
                        if (Carbon::parse($salary_history->start_date)->gte(Carbon::now()) && !$salary_history->cron_job) {
                            throw new CustomException(
                                'client_employee_salary_history.validate.start_date',
                                'ErrorException'
                            );
                        }
                    }

                    DB::beginTransaction();
                    // If the salary history has passed the effective date or there is no salary history
                    $salary_history = ClientEmployeeSalaryHistory::create($args);
                    DB::commit();
                    return $salary_history;
                } else {
                    throw new CustomException(
                        'client_employee_salary_history.update.fail',
                        'ErrorException'
                    );
                }
            }
        } catch (\Throwable $th) {
            throw new CustomException(
                $th->getMessage(),
                'ErrorException'
            );
        }
    }

    public function updateClientEmployeeSalaryHistory($root, array $args)
    {
        /**
         * Closed the changing history feature
         * Don't remove function because the mobile still calls this function.
         */
        throw new CustomException(
            'client_employee_salary_history.update.fail',
            'ErrorException'
        );
        try {
            $salary = ClientEmployeeSalaryHistory::where([
                'id' => $args['id'],
                'client_employee_id' => $args['client_employee_id'],
                'old_salary' => $args['old_salary'],
            ])->first();

            if ($salary) {
                if (Carbon::parse($salary->start_date)->gt(Carbon::now()) && !$salary->cron_job) {
                    // Check input data
                    if (Carbon::parse($args['start_date'])->gte(Carbon::now()) && Carbon::parse($args['end_date'])->gt($args['start_date'])) {

                        DB::beginTransaction();

                        $data = [];

                        if (isset($args['old_salary']) && isset($args['new_salary'])) {
                            if ($salary->getOriginal('old_salary') == $args['old_salary'] && $salary->getOriginal('new_salary') != $args['new_salary']) {
                                $data['new_salary'] = $args['new_salary'];
                            }
                            if ((int)$args['old_salary'] == (int)$args['new_salary']) {
                                throw new CustomException(
                                    'client_employee_salary_history.validate.new_salary',
                                    'ErrorException'
                                );
                            }
                        }

                        if (isset($args['start_date']) && isset($args['end_date'])) {
                            if ($salary->getOriginal('start_date') != $args['start_date']) {
                                $data['start_date'] = $args['start_date'];
                            }
                            if ($salary->getOriginal('end_date') != $args['end_date']) {
                                $data['end_date'] = $args['end_date'];
                            }
                        }

                        if (isset($args['old_fixed_allowance']) && isset($args['new_fixed_allowance'])) {
                            if ($salary->getOriginal('old_fixed_allowance') != $args['old_fixed_allowance']) {
                                $data['old_fixed_allowance'] = $args['old_fixed_allowance'];
                            }
                            if ($salary->getOriginal('new_fixed_allowance') != $args['new_fixed_allowance']) {
                                $data['new_fixed_allowance'] = $args['new_fixed_allowance'];
                            }
                        }

                        if (isset($args['old_allowance_for_responsibilities']) && isset($args['new_allowance_for_responsibilities'])) {
                            if ($salary->getOriginal('old_allowance_for_responsibilities') != $args['old_allowance_for_responsibilities']) {
                                $data['old_allowance_for_responsibilities'] = $args['old_allowance_for_responsibilities'];
                            }
                            if ($salary->getOriginal('new_allowance_for_responsibilities') != $args['new_allowance_for_responsibilities']) {
                                $data['new_allowance_for_responsibilities'] = $args['new_allowance_for_responsibilities'];
                            }
                        }

                        // Check data
                        if ($data) {
                            // Update DB
                            $salary->fill($data);
                            if ($salary->save()) {
                                DB::commit();
                                return $salary;
                            } else {
                                DB::rollback();
                                return $salary;
                            }
                        } else {
                            throw new CustomException(
                                'client_employee_salary_history.update.fail',
                                'ErrorException'
                            );
                        }
                    } else {
                        throw new CustomException(
                            'client_employee_salary_history.validate.date',
                            'ErrorException'
                        );
                    }
                } else {
                    throw new CustomException(
                        'client_employee_salary_history.update.validate.start_date',
                        'ErrorException'
                    );
                }
            } else {
                throw new CustomException(
                    'client_employee_salary_history.404',
                    'ErrorException'
                );
            }
        } catch (\Throwable $th) {
            throw new CustomException(
                $th->getMessage(),
                'ErrorException'
            );
        }
    }

    public function deleteClientEmployeeSalaryHistory($root, array $args)
    {
        try {
            /**
             * @var ClientEmployeeSalaryHistory $salary
             */
            $salary = ClientEmployeeSalaryHistory::where([
                'id' => $args['id'],
                'client_employee_id' => $args['client_employee_id']
            ])->first();

            if ($salary) {
                // Check salary history
                $salary_history = ClientEmployeeSalaryHistory::where('client_employee_id', $args['client_employee_id'])->latest()->first();
                if ($salary_history) {
                    $salary_history->fill(['end_date' => Carbon::now()->addYears(50)]);
                    $salary_history->save();
                }
                $salary->softDeleteWithUserId();
                return $salary;
            } else {
                throw new CustomException(
                    'client_employee_salary_history.404',
                    'ErrorException'
                );
            }
        } catch (\Throwable $th) {
            throw new CustomException(
                $th->getMessage(),
                'ErrorException'
            );
        }
    }

    /**
     * create file template import info staff
     */

    public function exportTemplateImportEmployee($root, array $arg)
    {
        // check account internal or client
        $client_id = auth()->user()->is_internal ? ($arg['client_id'] ?? null) : auth()->user()->client_id;
        if (auth()->user()->is_internal && empty($client_id)) {
            $response = [
                'status' => false,
                'message' => 'Client id not empty!'
            ];
            return json_encode($response);
        }

        $type = $arg['type'];
        $lang = $arg['lang'];

        if (!empty($client_id)) {
            $time = now()->format('Y-m-d-H-i-s-u');
            $fileName = "{$type}_import_{$time}_{$lang}.xlsx";

            switch ($type) {
                case ImportHelper::CLIENT_EMPLOYEE:
                    $pathFile = "ClientEmployeeExport/{$type}_import_" . $time . ".xlsx";
                    Excel::store((new ClientEmployeeExportTemplateImport($client_id)), $pathFile, 'minio');
                    $url = Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)));
                    break;
                case ImportHelper::AUTHORIZED_LEAVE:
                case ImportHelper::UNAUTHORIZED_LEAVE:
                    $url = config('app.customer_url') . "/import_files/leave_category/{$type}_{$lang}.xlsx";
                    break;
                default:
                    $url = config('app.customer_url') . "/import_files/{$type}/{$lang}.xlsx";
                    break;
            }

            $response = [
                'status' => true,
                'name' => $fileName,
                'url' => $url,
                'message' => 'Successful!'
            ];
        } else {
            $response = [
                'status' => false,
                'name' => '',
                'url' => '',
                'message' => 'Not found client!'
            ];
        }

        return json_encode($response);
    }

    public function updateClientEmployeeWorkScheduleGroupTemplateId($root, array $args)
    {
        if (empty($args['date'])) {
            $today = Carbon::now()->toDateString();
        } else {
            $today = $args['date'];
        }
        ClientEmployee::where('id', $args['id'])
            ->update(['work_schedule_group_template_id' => $args['work_schedule_group_template_id']]);

        $clientEmployee = ClientEmployee::find($args['id']);

        $this->generateTimesheet($today, $args['work_schedule_group_template_id'], $clientEmployee);

        Timesheet::where('client_employee_id', $args['id'])
            ->where('log_date', ">=", $today)
            ->update(['work_schedule_group_template_id' => $args['work_schedule_group_template_id']]);

        return $clientEmployee;
    }

    public function generateTimesheet($date, $work_schedule_group_template_id, $clientEmployee)
    {
        /** @var Client $client */
        $wsgs = WorkScheduleGroup::query()->where('work_schedule_group_template_id', $work_schedule_group_template_id)
            ->where('timesheet_to', '>=', $date)
            ->get();
        foreach ($wsgs as $workScheduleGroup) {
            $clientEmployee->refreshTimesheetByWorkScheduleGroupAsync($workScheduleGroup);
        }
    }

    public function updateClientEmployeeInvolvedPayroll($root, array $args)
    {
        try {
            if ($args['input']) {
                foreach ($args['input'] as $key => $value) {
                    if (isset($value['id'])) {
                        ClientEmployee::where('id', $value['id'])->update(['is_involved_payroll' => $value['is_involved_payroll']]);
                    }
                }
            }
        } catch (\Throwable $th) {
            throw new CustomException(
                $th->getMessage(),
                'ErrorException'
            );
        }
    }

    public function fetchDataHanetByTimesheetId($root, array $args)
    {
        if (empty($args['timesheet_id'])) {
            return false;
        }
        $timesheet = Timesheet::find($args['timesheet_id']);
        $user = Auth::user();
        $clientEmployee = $timesheet->clientEmployee;
        // Validate form request when exceed the approve deadline of the past
        $dateRegister = [
            'date_time_register' => $timesheet->log_date
        ];
        WorktimeRegisterHelper::checkValidateDeadlineApprove([$dateRegister], $clientEmployee);
        // Check permision of user when is assign hanet
        if (!$clientEmployee->hanetPerson) {
            return false;
        }
        // Check customer
        if (!$user->is_internal) {
            $clientEmployeeLogin = $user->clientEmployee;
            if (
                $clientEmployeeLogin->client_id !== $clientEmployee->client_id
                || $clientEmployeeLogin->id != $timesheet->client_employee_id
                && !$user->hasAnyPermission(['manage-timesheet'])
                && $user->getRole() !== Constant::ROLE_CLIENT_MANAGER
            ) {
                return false;
            }
        }

        // Check internal
        if ($user->is_internal && !Client::hasInternalAssignment()->find($clientEmployee['client_id']) && !($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR)) {
            return false;
        }

        $client = $clientEmployee->client;
        // Adjust from to according to date begin mark
        $workflowSetting = $client->clientWorkflowSetting;
        $dayBeginMark = $workflowSetting->getTimesheetDayBeginAttribute();
        $dayBeginMarkCarbon = Carbon::parse($dayBeginMark, Constant::TIMESHEET_TIMEZONE);
        $from = Carbon::parse($timesheet->log_date, Constant::TIMESHEET_TIMEZONE)
            ->setHour($dayBeginMarkCarbon->hour)
            ->setMinute($dayBeginMarkCarbon->minute);
        $to = $from->clone()->addDay();
        // split $from and $to into 2 periods separated by 00:00
        // Reason: Hanet API does not support querying across 00:00 (bug?)
        $periods = [
            [$from, $from->clone()->setTime(23, 59, 59)],
        ];
        if ($to->hour > 0 || $to->minute > 0) {
            $periods[] = [$to->clone()->setTime(0, 0, 1), $to];
        }

        $hanetSetting = HanetSetting::where('client_id', $client->id)->first();
        $accessToken = $hanetSetting->token;
        $places = HanetPlace::where('client_id', $client->id)->groupBy('hanet_place_id')->pluck('hanet_place_id');
        $checkingList = [];

        foreach ($places as $place) {
            $checkInRecords = [];
            foreach ($periods as $period) {
                $condition = [
                    'token' => $accessToken,
                    'placeID' => $place,
                    'from' => $period[0]->getTimestampMs(),
                    'to' => $period[1]->getTimestampMs(),
                    'aliasID' => $clientEmployee->code,
                    'size' => Constant::HANET_PAGE_SIZE
                ];
                $this->fetchDataHanetByCondition($checkInRecords, $condition, $client->id);
            }

            $logs = collect($checkInRecords)
                ->reject(function ($item) {
                    return $item['aliasID'] === ''; // User Unlinked Hanet
                })
                ->sortBy('checkinTime')->groupBy('aliasID');

            $persons = HanetPerson::query()
                ->with("clientEmployee")
                ->where(
                    "client_id",
                    $hanetSetting->client_id
                )
                ->whereIn("alias_id", $logs->keys())
                ->get()
                ->keyBy('alias_id');

            foreach ($logs as $aliasId => $checkinByAliasID) {
                if ($clientEmployee->code && $aliasId != $clientEmployee->code) {
                    continue;
                }

                // Check if the person is synced
                $person = $persons[$aliasId] ?? null;

                if (!$person || !$person->clientEmployee) {
                    continue;
                }

                $timesheet = (new Timesheet())->findTimeSheet($person->clientEmployee->id, $timesheet->log_date);
                if ($timesheet && $timesheet->isUsingMultiShift($workflowSetting)) {
                    $tableContent = [];

                    foreach ($checkinByAliasID as $index => $item) {
                        // check checkinTime between begin time to end time
                        if ($item['checkinTime'] >= $from->getTimestampMs() && $item['checkinTime'] <= $to->getTimestampMs()) {
                            $intime = Carbon::createFromTimestampMs($item['checkinTime'], Constant::TIMESHEET_TIMEZONE);
                            $timesheet->checkTimeWithMultiShift($intime, 'Hanet');
                            $tableContent[] = [
                                '#' => $index + 1,
                                'aliasId' => $aliasId,
                                'clientEmployeeID' => $person->clientEmployee->id,
                                'timeSheetID' => $timesheet->id,
                                'personName' => $item['personName'],
                                'checkinTime' => $intime
                            ];
                        }

                        $checkingList[] = [
                            'client_id' => $person->clientEmployee->client_id,
                            'client_employee_id' => $person->clientEmployee->id,
                            'checking_time' => Carbon::createFromTimestampMs($item['checkinTime'])->toDateTimeString(),
                            'source' => 'ManualSyncHanet'
                        ];
                    }
                    $timesheet->recalculate();
                    $timesheet->saveQuietly();
                } else {
                    $in = null;
                    $out = null;

                    foreach ($checkinByAliasID as $item) {
                        // check checkinTime between begin time to end time
                        if ($item['checkinTime'] >= $from->getTimestampMs() && $item['checkinTime'] <= $to->getTimestampMs()) {
                            $checkInTime = $item['checkinTime'];

                            if ($in === null || $checkInTime < $in) {
                                $out = $in;
                                $in = $checkInTime;
                            } elseif ($out === null || $checkInTime > $out) {
                                $out = $checkInTime;
                            }
                        }

                        $checkingList[] = [
                            'client_id' => $person->clientEmployee->client_id,
                            'client_employee_id' => $person->clientEmployee->id,
                            'checking_time' => Carbon::createFromTimestampMs($item['checkinTime'])->toDateTimeString(),
                            'source' => 'ManualSyncHanet'
                        ];
                    }

                    $inTime = $in ? Carbon::createFromTimestampMs($in, Constant::TIMESHEET_TIMEZONE) : null;
                    $outTime = $out ? Carbon::createFromTimestampMs($out, Constant::TIMESHEET_TIMEZONE) : null;

                    // Store log response Hanet
                    $logDebug = new ClientLogDebug();
                    $logDebug->place_id = $condition['placeID'];
                    $logDebug->type = 'Hanet';
                    $logDebug->data_log = json_encode($checkinByAliasID);
                    $logDebug->note = 'Sync Hanet individual with employee code: ' . $condition['aliasID'] . 'with checkin' . $inTime . 'and checkout: ' . $outTime;
                    $logDebug->save();
                    if ($inTime) {
                        $person->clientEmployee->checkIn($timesheet->log_date, PeriodHelper::getHourString($inTime), $timesheet->log_date != $inTime->toDateString(), 'Hanet');
                    }
                    if ($outTime) {
                        $person->clientEmployee->checkOut($timesheet->log_date, PeriodHelper::getHourString($outTime), $timesheet->log_date != $outTime->toDateString(), 'Hanet');
                    }

                    if ($inTime || $outTime) {
                        $timesheet->recalculate();
                        $timesheet->saveQuietly();
                    }
                }
            }
        }

        if (!empty($checkingList)) {
            Checking::upsert($checkingList, ['client_employee_id', 'checking_time']);
        }

        return true;
    }

    public function isUseHanet($root, array $args)
    {
        if (empty($args['client_employee_id'])) {
            return false;
        }

        return !is_null(ClientEmployee::where('id', $args['client_employee_id'])->whereHas('hanetPerson')->first());
    }

    private function fetchDataHanetByCondition(
        array &$checkInRecords,
        array $condition,
        $clientId
    ): array {
        $page = 1;
        do {
            $hanet = new HanetHelper();
            $condition['page'] = $page;
            $response = $hanet->getCheckinByPlaceIdInTimestamp($condition);

            // Store log response Hanet
            $logDebug = new ClientLogDebug();
            $logDebug->place_id = $condition['placeID'];
            $logDebug->type = 'Hanet';
            $logDebug->data_log = $response;
            $logDebug->note = 'Sync Hanet individual with employee code: ' . $condition['aliasID'] . ' accessToken: ' . $condition['placeID'] . ' - from: (' . $condition['from'] . ')' . ' ' . ' - to: (' . $condition['to'] . ')' . ' ' . " - Page: " . $page;
            $logDebug->alias_id = $condition['aliasID'];
            $logDebug->user_id = Auth::user()->id ?? null;
            $logDebug->client_id = $clientId;
            $logDebug->save();
            $responseBody = json_decode($response, true);
            if (empty($responseBody['data'])) {
                break;
            }
            // Merge data
            $checkInRecords = array_merge($checkInRecords, $responseBody['data']);

            if (count($responseBody['data']) < Constant::HANET_PAGE_SIZE) {
                break;
            }

            $page++;
        } while (true);
        return $checkInRecords;
    }

    public function clientEmployeeWithPeriod($condition)
    {
        return ClientEmployee::whereIn("id", $condition['client_employee_id'])->whereHas('worktimeRegisterPeriod', function ($query) use ($condition) {
            $query->where('date_time_register', '>=', $condition['start'])
                ->where('date_time_register', '<=', $condition['end'])
                ->whereHas('worktimeRegister', function ($subQuery) use ($condition) {
                    $subQuery->where("status", "approved");
                    if ($condition['type'] === 'leave_request') {
                        $subQuery->where('type', $condition['type'])
                            ->where('sub_type', $condition['sub_type'])
                            ->where('category', $condition['category']);
                    }
                });
        })->with('worktimeRegisterPeriod', function ($query) use ($condition) {
            $query->where('date_time_register', '>=', $condition['start'])
                ->orderBy('date_time_register')
                ->where('date_time_register', '<=', $condition['end'])
                ->whereHas('worktimeRegister', function ($subQuery) use ($condition) {
                    $subQuery->where("status", "approved");
                    if ($condition['type'] === 'leave_request') {
                        $subQuery->where('type', $condition['type'])
                            ->where('sub_type', $condition['sub_type'])
                            ->where('category', $condition['category']);
                    }
                });
        })->get()->keyBy('id');
    }

    public function countClientEmployeeByStatus()
    {
        $user = Auth::user();
        return ClientHelper::countClientEmployeeByStatus($user->client_id);
    }
}
