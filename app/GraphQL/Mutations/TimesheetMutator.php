<?php

namespace App\GraphQL\Mutations;


use App\DTO\TimesheetSchedule;
use App\Exceptions\CustomException;
use App\Exceptions\DownloadFileErrorException;
use App\Exceptions\HumanErrorException;
use App\Exports\ApproveExport;
use App\Exports\TimesheetMultipleShiftEmployeeAdvanceExport;
use App\Exports\TimesheetShiftEmployeeExport;
use App\Exports\TimesheetShiftEmployeeAdvanceExport;
use App\GraphQL\Queries\GetTimesheetByWorkScheduleGroup;
use App\GraphQL\Queries\GetTimesheetSchedules;
use App\Imports\Sheets\TimesheetsImportSheetData;
use App\Jobs\ImportTimesheetJob;
use App\Jobs\RecalculateTimesheetByMonthOfEmployee;
use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\Checking;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeLeaveManagement;
use App\Models\ClientWorkflowSetting;
use App\Models\ClientYearHoliday;
use App\Models\OvertimeCategory;
use App\Models\Timesheet;
use App\Models\TimesheetShift;
use App\Models\TimesheetShiftHistory;
use App\Models\TimesheetShiftHistoryVersion;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleGroup;
use App\Models\WorkScheduleGroupTemplate;
use App\Models\WorktimeRegister;
use App\Models\TimeChecking;
use App\Models\ViewCombinedTimesheet;
use App\Notifications\ApproveNotification;
use App\Notifications\TimesheetConfirmApproveNotification;
use App\Notifications\TimesheetRequestApproveNotification;
use App\Support\Constant;
use App\Support\PeriodHelper;
use App\Support\WorktimeRegisterHelper;
use App\User;
use ErrorException;
use GraphQL\Type\Definition\ResolveInfo;
use HttpException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\File;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Spatie\Period\Period;
use Spatie\Period\Precision;
use App\Jobs\TimeSheetEmployeeExportJob;
use App\Models\TimeSheetEmployeeExport as ModelsTimeSheetEmployeeExport;
use App\Models\TimeSheetEmployeeImport;
use App\Support\TimesheetsHelper;
use App\Models\WorktimeRegisterCategory;
use App\Exports\Sheets\TimesheetSheet;
use App\Jobs\DeleteFileJob;
use Carbon\CarbonPeriod;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;

class TimesheetMutator
{

    /**
     * Upload a file, store it on the server and return the path.
     *
     * @param mixed $root
     * @param mixed[] $args
     *
     * @return string|null
     */
    public function import($root, array $args): ?string
    {
        $rules = [
            'client_id' => 'required',
            'file' => 'required',
        ];

        $user = auth()->user();
        $inputFileType = 'Xlsx';
        $inputFileName = 'timesheet_import_' . time() . '.xlsx';
        $inputFileImport = 'TimesheetImport/' . $inputFileName;

        Storage::disk('local')->putFileAs(
            'TimesheetImport',
            new File($args['file']),
            $inputFileName
        );

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        $reader->setLoadAllSheets();
        $spreadsheet = $reader->load(storage_path('app/' . $inputFileImport));

        $errors = [];

        $import = new TimesheetsImportSheetData($args['client_id'], $user);

        $sheet1Errors = $import->validate($spreadsheet->getSheetByName('Sheet1'));

        if ($sheet1Errors)
            $errors['Sheet1'] = $sheet1Errors;

        if ($errors) {
            throw new DownloadFileErrorException($errors, $inputFileImport);
        }

        try {
            Validator::make($args, $rules);
            $file = $args['file'];
            $clientFileName = $file->getClientOriginalName();

            $clientId = $args['client_id'];
            $timeSheetEmployeeImport = new TimeSheetEmployeeImport();
            $timeSheetEmployeeImport->fill([
                'name' => $clientFileName,
                'user_id' => auth()->user()->id,
                'status' => 'processing'
            ]);

            $timeSheetEmployeeImport->save();

            $timeSheetEmployeeImport->addMedia(storage_path('app/' . $inputFileImport))->toMediaCollection('TimeSheetEmployeeImport', 'minio');

            ImportTimesheetJob::dispatch($clientId, $inputFileImport, $user, $timeSheetEmployeeImport->id);

            return json_encode([
                'status' => 200,
                'message' => 'Import Timesheet is successful.',
            ], 200);
        } catch (ValidationException $e) {
            throw new CustomException(
                'The given data was invalid. 1',
                'ValidationException'
            );
        } catch (ErrorException $e) {
            throw new CustomException(
                'The given data was invalid. 2',
                'ErrorException'
            );
        } catch (HttpException $e) {
            throw new CustomException(
                'The given data was invalid. 3',
                'HttpException'
            );
        }
    }

    /**
     * Quick set status
     *
     * @param $rootValue
     * @param array $args
     */
    public function quickSetTimesheet($rootValue, array $args): string
    {
        $user = Auth::user();
        if ($user->isInternalUser()) {
            // do nothing
            return "skip";
        }
        $now = Carbon::now(Constant::TIMESHEET_TIMEZONE);

        $clientEmployee = $user->clientEmployee;

        $clientEmployee->checkTimeAuto($now->toDateString());

        return "ok";
    }

    public function mobileCheckin($rootValue, array $args): string
    {
        $user = Auth::user();
        if (!$user || $user->isInternalUser()) {
            // do nothing
            return "skip";
        }

        $clientEmployee = $user->clientEmployee;

        $now = Carbon::now(Constant::TIMESHEET_TIMEZONE);

        $clientEmployee->checkTimeAuto($now->toDateString());
        $timesheet = (new Timesheet)->findTimeSheet($clientEmployee->id, $now->toDateString());
        $appData = [
            'source' => 'App',
            'longitude' => $args['longitude'],
            'latitude' => $args['latitude'],
            'location_checkin' => isset($args['location_checkin']) && $args['location_checkin'] ? $args['location_checkin'] : null,
            'ssid' => isset($args['ssid']) && $args['ssid'] ? $args['ssid'] : null,
            'bssid' => isset($args['bssid']) && $args['bssid'] ? $args['bssid'] : null,
            'user_location_input' => isset($args['user_location_input']) && $args['user_location_input'] ? $args['user_location_input'] : null,
        ];

        TimeChecking::where('timesheet_id', $timesheet->id)
            ->where('datetime', $now->format("Y-m-d H:i") . ':00')
            ->where('client_employee_id', $clientEmployee->id)
            ->update($appData);

        return "ok";
    }

    public function setTimesheet($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $rules = [
            'client_employee_id' => 'required|exists:client_employees,id',
            'log_date' => 'required|Date',
            'leave_type' => 'required|string',
        ];

        // Validate form request when exceed the approve deadline of the past
        $dateRegister = [
            'date_time_register' => $args['log_date']
        ];
        $clientEmployee = ClientEmployee::find($args['client_employee_id']);
        WorktimeRegisterHelper::checkValidateDeadlineApprove([$dateRegister], $clientEmployee);

        try {
            Validator::make($args, $rules);

            $log_date = Carbon::parse($args['log_date'])->format('Y-m-d');
            $timesheet = (new Timesheet)->findTimeSheet($args['client_employee_id'], $log_date);

            if ($args['work_status'] === '') {
                if ($timesheet) {
                    $timesheet->delete();
                }
                return null;
            } elseif (!$timesheet) {
                $model = new Timesheet();
                $model->client_employee_id = $args['client_employee_id'];
                $model->log_date = $log_date;
                $model->activity = (isset($args['activity'])) ? $args['activity'] : null;
                $model->work_place = (isset($args['work_place'])) ? $args['work_place'] : null;
                $model->working_hours = (isset($args['working_hours'])) ? $args['working_hours'] : null;
                $model->overtime_hours = (isset($args['overtime_hours'])) ? $args['overtime_hours'] : null;
                $model->check_in = (isset($args['check_in'])) ? $args['check_in'] : null;
                $model->check_out = (isset($args['check_out'])) ? $args['check_out'] : null;
                $model->start_next_day = (isset($args['start_next_day'])) ? $args['start_next_day'] : false;
                $model->next_day = (isset($args['next_day'])) ? $args['next_day'] : false;
                $model->leave_type = $args['leave_type'];
                $model->attentdant_status = (isset($args['attentdant_status'])) ? $args['attentdant_status'] : null;
                $model->work_status = (isset($args['work_status'])) ? $args['work_status'] : null;
                $model->note = (isset($args['note'])) ? $args['note'] : null;
                $model->reason = (isset($args['reason'])) ? $args['reason'] : null;
                if ($user->isInternalUser()) {
                    $model->state = 'approved';
                }
            } else {
                // Timesheet::where('id', '!=', $timesheet->id)
                //     ->whereDate('log_date', $log_date)
                //     ->where('client_employee_id', $args['client_employee_id'])
                //     ->delete();
                $model = $timesheet;

                // $model = Timesheet::findOrFail($timesheet->toArray()['id']);
                $model->activity = (isset($args['activity'])) ? $args['activity'] : null;
                $model->work_place = (isset($args['work_place'])) ? $args['work_place'] : null;
                $model->working_hours = (isset($args['working_hours'])) ? $args['working_hours'] : null;
                $model->overtime_hours = (isset($args['overtime_hours'])) ? $args['overtime_hours'] : null;
                $model->check_in = (isset($args['check_in'])) ? $args['check_in'] : null;
                $model->check_out = (isset($args['check_out'])) ? $args['check_out'] : null;
                $model->start_next_day = (isset($args['start_next_day'])) ? $args['start_next_day'] : false;
                $model->next_day = (isset($args['next_day'])) ? $args['next_day'] : false;
                $model->leave_type = $args['leave_type'];
                $model->attentdant_status = (isset($args['attentdant_status'])) ? $args['attentdant_status'] : null;
                $model->work_status = (isset($args['work_status'])) ? $args['work_status'] : null;
                $model->note = (isset($args['note'])) ? $args['note'] : null;
                $model->reason = (isset($args['reason'])) ? $args['reason'] : null;
            }

            $checkingList = [];
            if (!empty($args['check_in']) && $args['check_in'] != $model->getOriginal('check_in')) {
                $checkIn = empty($args['start_next_day']) ? Carbon::parse($log_date . ' ' . $args['check_in']) : Carbon::parse($log_date . ' ' . $args['check_in'])->addDay();

                $checkingList[] = [
                    'client_id' => $user->client_id,
                    'client_employee_id' => $user->clientEmployee->id,
                    'checking_time' => $checkIn->toDateTimeString(),
                    'source' => 'SetManual'
                ];
            }

            if (!empty($args['check_out']) && $args['check_out'] != $model->getOriginal('check_out')) {
                $checkOut = empty($args['next_day']) ? Carbon::parse($log_date . ' ' . $args['check_out']) : Carbon::parse($log_date . ' ' . $args['check_out'])->addDay();

                $checkingList[] = [
                    'client_id' => $user->client_id,
                    'client_employee_id' => $user->clientEmployee->id,
                    'checking_time' => $checkOut->toDateTimeString(),
                    'source' => 'SetManual'
                ];
            }

            if (!empty($checkingList)) {
                Checking::upsert($checkingList, ['client_employee_id', 'checking_time']);
            }

            $model->saveOrFail();
            return $model;
        } catch (ValidationException $e) {
            throw new CustomException(
                'The given data was invalid.',
                'ValidationException'
            );
        }
    }

    public function isCreate(User $user, array $injected)
    {
        $clientEmployee = (new ClientEmployee)->findClientEmployee($injected['client_employee_id']);
        $workSchedule = (new WorkSchedule)->checkExitWorkSchedule($clientEmployee['client_id'], $injected['log_date']);

        if ($workSchedule) {
            if (!$user->isInternalUser()) {
                $role = $user->getRole();
                switch ($role) {
                    case Constant::ROLE_CLIENT_MANAGER:
                        if ($user->client_id == $clientEmployee['client_id']) {
                            return true;
                        }
                        return false;
                    case Constant::ROLE_CLIENT_STAFF:
                        if ((!empty($injected['client_employee_id'])) && ($user->clientEmployee->id == $injected['client_employee_id'])) {
                            return true;
                        }
                        return false;
                    default:
                        return false;
                }
            } else {
                $role = $user->getRole();
                switch ($role) {
                    case Constant::ROLE_INTERNAL_STAFF:
                        if ($user->iGlocalEmployee->isAssignedFor($clientEmployee['client_id'])) {
                            return true;
                        }
                        return false;
                    default:
                        return false;
                }
            }
        }

        return false;
    }

    public function isUpdate(User $user, Timesheet $timesheet, array $injected)
    {
        $workSchedule = (new WorkSchedule)->checkExitWorkSchedule($timesheet->clientEmployee->client_id, $injected['log_date']);
        if (!$workSchedule) {
            return false;
        }

        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    if ($user->client_id == $timesheet->clientEmployee->client_id) {
                        return true;
                    }
                    return false;
                case Constant::ROLE_CLIENT_STAFF:
                    if ($user->id == $timesheet->clientEmployee->user_id) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_STAFF:
                    if ($user->iGlocalEmployee->isAssignedFor($timesheet->clientEmployee->client_id)) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        }
    }

    public function requestApprove($root, array $args)
    {
        $timesheet = Timesheet::find($args['id']);

        if (isset($args['approved_by'])) {
            $timesheet->approved_by = $args['approved_by'];
        }

        $timesheet->approved = 0;
        $timesheet->save();

        return $timesheet;
    }

    public function exportMyTimesheetToExcel($root, array $args)
    {
        $client_id = $args['client_id'];
        $client_employee_id = $args['client_employee_id'];
        $from_date = $args['from_date'];
        $to_date = $args['to_date'];
        $user = auth()->user();

        $wt_category_list = collect(WorktimeRegisterCategory::select('id', 'category_name', 'sub_type')->where('client_id', $client_id)->get()->toArray());

        // Export excel
        $extension = '.xlsx';
        $fileName = "PERSONAL_TIMESHEET_" . uniqid() . $extension;
        $pathFile = 'TimeSheetClientEmployeeExport/user_' . $client_employee_id . '/' . $fileName;

        // Translate
        $lang = $user->prefered_language ? $user->prefered_language : app()->getLocale();
        app()->setlocale($lang);

        // Get template
        $template_export = optional($user->client->clientWorkflowSetting->template_export)['timesheet'] ?? 1;

        Excel::store((new TimesheetSheet($client_employee_id, $from_date, $to_date, $wt_category_list, $template_export)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        DeleteFileJob::dispatch($pathFile)->delay(now()->addMinutes(3));

        return json_encode($response);
    }

    public function getApproveClientEmployee($root, array $args)
    {
    }

    public function getTimesheetSummary($root, array $args)
    {
        $client_employee_id = $args['client_employee_id'];
        $work_schedule_group_id = $args['work_schedule_group_id'];
        $clientEmployee = ClientEmployee::select('*')->with('client')->where('id', $client_employee_id)->first();

        if (empty($clientEmployee))
            return [];

        $client = $clientEmployee->client;

        $timesheetMinTimeBlock = $clientEmployee->client['timesheet_min_time_block'];

        $setting = ClientWorkflowSetting::select('*')->where('client_id', $clientEmployee->client_id)->first();

        $work_schedule_group = WorkScheduleGroup::query()
            ->where('id', $work_schedule_group_id)
            ->with('workScheduleGroupTemplate')
            ->with('workSchedules')
            ->first();
        /** @var WorkScheduleGroupTemplate $workScheduleGroupTemplate */
        $workScheduleGroupTemplate = $work_schedule_group->workScheduleGroupTemplate;

        $holidays = 0;
        $outsideWorking = 0;
        $specialLeave = 0;
        $wfh = 0;
        $weeklyLeave = 0;
        $so_gio_nghi_tieu_chuan = 0;
        $so_gio_lam_viec_tieu_chuan = 0;
        /** @var WorkSchedule[]|Collection $workSchedules */
        $workSchedules = $work_schedule_group->workSchedules;

        if (!empty($setting) && ($setting->enable_leave_request == 1) && $workSchedules->isNotEmpty()) {
            $worktimeRegisters = WorktimeRegister::select('*')
                ->where('client_employee_id', $client_employee_id)
                ->where('type', 'leave_request')->get();

            if ($worktimeRegisters->isNotEmpty()) {
                $wks = $workSchedules->sortBy('schedule_date')->values()->all();

                $checkIn = $wks[0]->check_in ? $wks[0]->check_in . ':00' : '00:00:00';
                $checkOut = $wks[(count($wks) - 1)]->check_out ? $wks[(count($wks) - 1)]->check_out . ':00' : '00:00:00';

                $startDate = Carbon::parse($wks[0]->schedule_date)->format('Y-m-d') . ' ' . $checkIn;
                $endDate = Carbon::parse($wks[(count($wks) - 1)]->schedule_date)->format('Y-m-d') . ' ' . $checkOut;

                if (!Carbon::parse($startDate)->isBefore(Carbon::parse($endDate))) {
                    $endDate = $startDate;
                }

                $schedulePeriod = Period::make($startDate, $endDate, Precision::MINUTE);

                foreach ($worktimeRegisters as $w) {
                    $wreriod = PeriodHelper::makePeriod($w->start_time, $w->end_time, Precision::MINUTE);

                    $schedulePeriodOverlap = $schedulePeriod->overlapSingle($wreriod);

                    if ($schedulePeriodOverlap && $w->type == 'leave_request' && ($w->sub_type == 'outside_working')) {
                        $durationInMinutes = Carbon::parse($w->end_time)->diffInMinutes(Carbon::parse($w->start_time));

                        $differenceInHours = round(($timesheetMinTimeBlock * floor($durationInMinutes / $timesheetMinTimeBlock)) / 60, 2, PHP_ROUND_HALF_DOWN);

                        $outsideWorking += $differenceInHours;
                    }

                    if ($schedulePeriodOverlap && $w->type == 'leave_request' && ($w->sub_type == 'wfh')) {
                        $durationInMinutes = Carbon::parse($w->end_time)->diffInMinutes(Carbon::parse($w->start_time));

                        $differenceInHours = round(($timesheetMinTimeBlock * floor($durationInMinutes / $timesheetMinTimeBlock)) / 60, 2, PHP_ROUND_HALF_DOWN);

                        $wfh += $differenceInHours;
                    }

                    if ($schedulePeriodOverlap && $w->type == 'leave_request' && ($w->sub_type == 'special_leave')) {
                        $specialLeave += 1;
                    }
                }
            }
        }

        $log_date_from = $args['log_date_from'];
        $log_date_to = $args['log_date_to'];

        /** @var Collection $timesheets */
        $timesheets = Timesheet::select('*')
            ->with('timesheetShiftMapping.timesheetShift')
            ->where('log_date', '>=', $log_date_from)
            ->where('log_date', '<=', $log_date_to)
            ->where('client_employee_id', $client_employee_id)
            ->get()
            ->keyBy('log_date');

        $now = Carbon::now();
        $tomorrow = $now->addDay()->setHour(0)->setSecond(0)->setMinute(0);

        // transform work schedule to timesheet shift if any
        $workSchedules->transform(function (WorkSchedule $ws) use ($timesheets) {
            if ($timesheets->has($ws->schedule_date->toDateString())) {
                /** @var Timesheet $ts */
                $timesheet = $timesheets->get($ws->schedule_date->toDateString());
                return $timesheet->getShiftWorkSchedule($ws);
            }
            return $ws;
        });

        $paidLeaveChangeSummary = WorktimeRegisterHelper::getYearPaidLeaveChange($client_employee_id);

        // tinh số giờ tiêu chuẩn và số giờ nghỉ tiêu chuẩn dựa vào lịch làm việc
        $workSchedules->each(function (WorkSchedule $item) use ($workScheduleGroupTemplate, &$so_gio_lam_viec_tieu_chuan, &$holidays, &$weeklyLeave, &$so_gio_nghi_tieu_chuan, $timesheets, $setting) {
            if ($item->is_off_day || $item->is_holiday) {
                if ($item->is_holiday) {
                    $holidays++;
                }
                if ($item->is_off_day) {
                    $weeklyLeave++;
                }

                // Tinh so gio nghi tieu chuan trong mot thang WorkSchedule
                $checkIn = Carbon::parse('2021-01-01 ' . $workScheduleGroupTemplate->check_in . ':00');
                $checkOut = Carbon::parse('2021-01-01 ' . $workScheduleGroupTemplate->check_out . ':00');
                $restOut = Carbon::parse('2021-01-01 ' . $workScheduleGroupTemplate->rest_hours . ':00');
                $dayStart = Carbon::parse('2021-01-01 00:00:00');
                $restHours = round($restOut->diffInMinutes($dayStart) / 60, 2);
                if ($restHours < 0) {
                    $restHours = 0;
                }
                $so_gio_nghi_tieu_chuan += (round($checkOut->diffInMinutes($checkIn) / 60, 2) - $restHours);
            } else {
                if ($timesheets->has($item->schedule_date->toDateString())) {
                    $timesheet = $timesheets->get($item->schedule_date->toDateString());
                    if ($timesheet && $timesheet->isUsingMultiShift($setting)) {
                        foreach ($timesheet->timesheetShiftMapping as $mapping) {
                            $so_gio_lam_viec_tieu_chuan += $mapping->schedule_shift_hours;
                        }
                    } else {
                        $so_gio_lam_viec_tieu_chuan += $item->getWorkHoursWithoutCheckAttribute();
                    }
                } else {
                    $so_gio_lam_viec_tieu_chuan += $item->getWorkHoursWithoutCheckAttribute();
                }
            }
        });

        $summaryItems = [
            'so_ngay_lam' => 0,
            'so_gio_lam' => 0,
            'so_gio_tang_ca' => 0,
            'so_ngay_nghi' => 0,
            'makeup_hours' => 0,
            'so_gio_ditre_vesom' => 0,
            'so_ngay_nghi_khong_huong_luong' => 0,
            'so_ngay_di_cong_tac' => 0,
            'so_ngay_phep_con_lai' => $paidLeaveChangeSummary['so_gio_phep_con_co_the_xin'],
            'so_gio_nghi_tieu_chuan' => $so_gio_nghi_tieu_chuan,
            'holidays' => $holidays,
            'special_leave' => $specialLeave,
            'outside_working' => $outsideWorking,
            'wfh' => $wfh,
            'weekly_leave' => $weeklyLeave,
            'standard_work_hours_per_day' => $client['standard_work_hours_per_day'],
        ];

        $totalOvertimeByMonth = 0;
        $totalHourHoliday = 0;
        foreach ($timesheets as $item) {
            /** @var Timesheet $item */
            /** @var WorkSchedule $workSchedule */

            $workSchedule = $workSchedules->first(function (WorkSchedule $ws) use ($item) {
                return Carbon::parse($item->log_date)->isSameDay($ws->schedule_date);
            });
            if (!$workSchedule) {
                logger()->warning(
                    self::class . '@getTimesheetSummary missing work schedule point for timesheet',
                    ['timesheet' => $item]
                );
                continue;
            }

            // Standard Hours
            $standardHours = $workSchedule->workHours;

            if ($workSchedule['check_out'] && $workSchedule['check_in'] && $item->check_in && $item->check_out) {
                if ($item->check_in) {
                    if ($item->attentdant_status == 'late') {
                        $origin = Carbon::parse('2021-01-01 ' . $workSchedule['check_in'] . ':00');
                        $target = Carbon::parse('2021-01-01 ' . $item->check_in . ':00');

                        $summaryItems['so_gio_ditre_vesom'] += round($origin->diffInMinutes($target) / 60, 2);
                    }
                }

                if ($item->attentdant_status == 'early' && $item->check_out) {
                    $checkOut = Carbon::parse('2021-01-01 ' . $item->check_out . ':00');
                    $checkOutInMs = Carbon::parse('2021-01-01 ' . $workSchedule['check_out'] . ':00');

                    $checkOutInMs = round($checkOut->diffInMinutes($checkOutInMs) / 60, 2);

                    if ($checkOutInMs > 0) {
                        $summaryItems['so_gio_ditre_vesom'] += $checkOutInMs;
                    }
                }
            }

            $missingHours = 0;
            if ($item->work_status == Timesheet::STATUS_NGHI_PHEP_KHL) {
                $missingHours = ($standardHours - $item->paid_leave_hours - $item->working_hours);
                if ($missingHours < 0) {
                    $missingHours = 0;
                }
            } elseif ($item->work_status == Timesheet::STATUS_DI_LAM) {
                if ($item->log_date < $tomorrow->toDateString()) {
                    $missingHours = ($standardHours - $item->paid_leave_hours - $item->working_hours);
                    if ($missingHours < 0) {
                        $missingHours = 0;
                    }
                }
            } elseif ($item->work_status == Timesheet::STATUS_NGHI_CUOI_TUAN) {
                $missingHours = !$workSchedule->is_off_day ? $standardHours : 0;
            } elseif ($item->work_status == Timesheet::STATUS_NGHI_LE) {
                $totalHourHoliday += $item->working_hours;
            }
            if ($workSchedule->is_holiday) {
                $item->working_hours = 0;
            }

            $summaryItems['so_ngay_lam'] += $item->working_hours;
            $summaryItems['so_ngay_di_cong_tac'] += $item->mission_hours;

            $summaryItems['so_gio_lam'] += $item->working_hours;
            $summaryItems['so_gio_tang_ca'] += $item->overtime_hours;
            $summaryItems['so_ngay_nghi_khong_huong_luong'] += $missingHours;
            $summaryItems['so_ngay_nghi'] += $item->paid_leave_hours;
            $summaryItems['makeup_hours'] += $item->makeup_hours;
            $totalOvertimeByMonth += $item->overtime_hours;
        }

        // Get client setting OT
        $clientSettingOt = OvertimeCategory::where([
            ['client_id' , $clientEmployee->client_id],
            ['start_date', '<=', $now->format('Y-m-d')],
            ['end_date', '>=', $now->format('Y-m-d')]
        ])->first();
        if ($clientSettingOt) {
            $numberHourOtFilterByYear = Timesheet::select('overtime_hours')
                ->where([
                    ['log_date', '>=', $clientSettingOt->start_date],
                    ['log_date', '<=', $log_date_to],
                    ['client_employee_id', $client_employee_id]
                ])
                ->get()->sum('overtime_hours');
            // The number of overtime hours filtered by the filter is accumulated by each month of the year
            $summaryItems['remain_overtime_by_month'] = max($clientSettingOt->entitlement_month - $totalOvertimeByMonth, 0);
            $summaryItems['remain_overtime_by_year'] = max($clientSettingOt->entitlement_year - $numberHourOtFilterByYear, 0);
        }

        // Get client setting Leave Time
        $clientEmployeeLeaveManagement = ClientEmployeeLeaveManagement::whereHas('leaveCategory', function ($query) use ($clientEmployee, $log_date_to) {
            $query->where([
                ['client_id', $clientEmployee->client_id],
                ['type', 'authorized_leave'],
                ['sub_type', 'year_leave'],
                ['start_date', '<=', $log_date_to],
                ['end_date', '>=', $log_date_to]
            ]);
        })->where('client_employee_id', $clientEmployee->id)->with('clientEmployeeLeaveManagementByMonth')->first();
        if ($clientEmployeeLeaveManagement) {
            $clientEmployeeLeaveManagementByMonth = $clientEmployeeLeaveManagement->clientEmployeeLeaveManagementByMonth->where("start_date", '<=', $log_date_to)
                ->where("end_date", '>=', $log_date_to)->first();
            if ($clientEmployeeLeaveManagementByMonth) {
                // The number of paid leave hours filtered by the filter is accumulated by each month of the year
                $summaryItems['total_leave_paid_hour_year'] = max($clientEmployeeLeaveManagement->entitlement - $clientEmployee->year_paid_leave_count, 0);
                $summaryItems['remain_total_leave_paid_hour_year'] = $clientEmployee->year_paid_leave_count;
            }
        }
        // Override stander working hours if have holidays
        $so_gio_lam_viec_tieu_chuan += $totalHourHoliday;

        $unpaidHours = $so_gio_lam_viec_tieu_chuan - $summaryItems['so_gio_lam'] - $summaryItems['so_ngay_nghi'] - $totalHourHoliday;
        $summaryItems['so_ngay_nghi_khong_huong_luong'] = max($unpaidHours, 0);
        $summaryItems['so_gio_lam_viec_tieu_chuan'] = round($so_gio_lam_viec_tieu_chuan, 2);
        // Round summary items
        foreach ($summaryItems as $key => $value) {
            $summaryItems[$key] = round($value, 2);
        }

        return json_encode($summaryItems);
    }

    public function getCountTimesheetEmployees($root, array $args)
    {
        $clientID = trim($args['client_id']);

        $workScheduleGroup = null;

        if (isset($args['work_schedule_group_id']) && $args['work_schedule_group_id']) {
            $workScheduleGroup = WorkScheduleGroup::where('id', $args['work_schedule_group_id'])->first();
        }

        $allEmployees = ClientEmployee::select('*')->where('client_id', '=', $clientID);

        $allEmployees = $allEmployees->get();

        $allEmployeeIds = $allEmployees->pluck('id');

        $timesheets = Timesheet::selectRaw(
            '
            client_employee_id,
            COUNT(IF(state = \'new\', 1, NULL)) AS totalNew,
            COUNT(IF(state = \'processing\', 1, NULL)) AS totalProcessing,
            COUNT(IF(state = \'approved\', 1, NULL)) AS totalApproved'
        )
            ->where('log_date', '>=', $workScheduleGroup->timesheet_from)
            ->where('log_date', '<=', $workScheduleGroup->timesheet_to)
            ->whereIn('client_employee_id', $allEmployeeIds->all())
            ->groupBy('client_employee_id');

        $timesheets = $timesheets->get();
        $filteredEmployeeIds = $timesheets->pluck('client_employee_id');

        $filteredEmployees = $filteredEmployeeIds->all();
        $paginated = ClientEmployee::select('*')
            ->where('client_id', '=', $clientID)
            ->whereIn('id', $filteredEmployees)
            ->orderBy('full_name');
        if ($workScheduleGroup) {
            $paginated = $paginated->where('work_schedule_group_template_id', $workScheduleGroup->work_schedule_group_template_id);
        }

        $totalNew = 0;
        $totalProcessing = 0;
        $totalApproved = 0;
        $array = $paginated->get()->toArray();


        $foundItems = [];

        foreach ($array as $item) {
            foreach ($timesheets as $key => $value) {
                if (isset($item['id']) && $item['id'] == $value->client_employee_id) {
                    array_push($foundItems, $value);
                }
            }
        }

        foreach ($foundItems as $item) {
            if ($item['totalNew'] > 0)
                $totalNew++;

            if ($item['totalProcessing'] > 0)
                $totalProcessing++;

            if ($item['totalApproved'] > 0)
                $totalApproved++;
        }

        return [
            'total' => ($totalNew + $totalProcessing + $totalApproved),
            'totalNew' => $totalNew,
            'totalProcessing' => $totalProcessing,
            'totalApproved' => $totalApproved,
        ];
    }

    public function getTimesheetEmployeesByInternalUser($root, array $args)
    {
        $clientID = $args['client_id'];
        $perpage = isset($args['perPage']) ? $args['perPage'] : 10;
        $page = isset($args['page']) ? $args['page'] : '1';
        $workScheduleGroup = null;
        $keywords = isset($args['keywords']) ? $args['keywords'] : '';

        if (isset($args['work_schedule_group_id']) && $args['work_schedule_group_id']) {
            $workScheduleGroup = WorkScheduleGroup::where('id', $args['work_schedule_group_id'])->first();
        }

        $filteredEmployees = [];

        if ($workScheduleGroup) {
            $timesheets = Timesheet::select('client_employee_id')
                ->where('log_date', '>=', $workScheduleGroup->timesheet_from)
                ->where('log_date', '<=', $workScheduleGroup->timesheet_to)
                ->whereHas('client', function ($client) use ($clientID) {
                    /** @var $client Client */
                    return $client->where((new Client)->getTable() . ".id", $clientID);
                })
                ->groupBy('client_employee_id');

            if (isset($args['filter']) && $args['filter']) {
                $timesheets->whereHas('clientEmployee', function ($clientEmployee) use ($args) {
                    return $clientEmployee->where('code', 'LIKE', "%{$args['filter']}%")
                        ->orWhere('full_name', 'LIKE', "%{$args['filter']}%");
                });
            }

            if (isset($args['state']) && $args['state']) {
                $timesheets = $timesheets->where('state', $args['state']);
            }

            $timesheets = $timesheets->get();
            $filteredEmployeeIds = $timesheets->pluck('client_employee_id');
            $filteredEmployees = $filteredEmployeeIds->all();
        }

        $paginated = ClientEmployee::select('*')
            ->where('client_id', '=', $clientID)
            ->whereIn('id', $filteredEmployees)
            ->orderBy('full_name');

        if ($keywords) {
            $paginated = $paginated->where(function ($query) use ($keywords) {
                $query->where('full_name', 'LIKE', "%$keywords%")
                    ->orWhere('code', 'LIKE', "%$keywords%");
            });
        }

        if ($workScheduleGroup) {
            $paginated = $paginated->where(
                'work_schedule_group_template_id',
                $workScheduleGroup->work_schedule_group_template_id
            );
        }

        /** @var Paginator $paginated */
        $paginated = $paginated->paginate($perpage, ['id'], 'page', $page);

        $paginatedItems = $paginated->collect();

        return [
            'data' => $paginatedItems,
            'pagination' => [
                'total' => $paginated->total(),
                'count' => $paginated->count(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'total_pages' => $paginated->lastPage(),
            ],
        ];
    }

    public function timesheetEmployeeQuery(array $args)
    {
        $clientId = $args['client_id'];
        $date = $args['log_date'];

        $query = ViewCombinedTimesheet::select([
            'view_combined_timesheets.client_employee_id',
            'view_combined_timesheets.check_in',
            'view_combined_timesheets.check_out',
            'view_combined_timesheets.work_status',
            'view_combined_timesheets.state',
            'view_combined_timesheets.log_date',
            'view_combined_timesheets.shift_enabled',
            'view_combined_timesheets.shift_is_off_day',
            'view_combined_timesheets.shift_is_holiday',
            'view_combined_timesheets.shift_check_in',
            'view_combined_timesheets.shift_check_out',
            'view_combined_timesheets.shift_next_day',
            'view_combined_timesheets.next_day',
            'client_employees.full_name',
            'client_employees.code',
            'client_employees.department',
            'view_combined_timesheets.schedule_check_in',
            'view_combined_timesheets.schedule_check_out',
            'view_combined_timesheets.timesheet_id',
            'timesheet_shift_mapping.check_in as check_in_for_multiple_shift',
            'timesheet_shift_mapping.check_out as check_out_for_multiple_shift',
            'timesheet_shift_mapping.deleted_at as deleted_at_for_multiple_shift',
            'timesheet_shifts.shift_code as shift_code_for_multiple',
            'timesheet_shifts.symbol as symbol_for_multiple',
            'timesheet_shifts.check_in as check_in_work_shift_for_multiple_shift',
            'timesheet_shifts.check_out as check_out_work_shift_for_multiple_shift'
        ])->leftJoin('timesheet_shift_mapping', 'view_combined_timesheets.timesheet_id', '=', 'timesheet_shift_mapping.timesheet_id')
            ->leftJoin('timesheet_shifts', 'timesheet_shift_mapping.timesheet_shift_id', '=', 'timesheet_shifts.id')
            ->leftJoin('client_employees', 'client_employees.id', '=', 'view_combined_timesheets.client_employee_id')
            ->leftJoin('clients', 'clients.id', '=', 'client_employees.client_id')
            ->where('clients.id', $clientId)
            ->where('view_combined_timesheets.log_date', $date);
        $query = $query->where(function ($subQuery) use ($date) {
            $subQuery->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING)
                ->whereNull('client_employees.deleted_at');

            $subQuery->orWhere(function ($subQueryLevelTwo) use ($date) {
                $subQueryLevelTwo->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                    ->where('client_employees.quitted_at', '>', $date)
                    ->whereNull('client_employees.deleted_at');
            });
        });
        $state = $args['state'] ?? "";
        if ($state) {
            $query = $query->where('view_combined_timesheets.state', $state);
        }

        $employeeFilter = $args['employee_filter'] ?? "";
        if ($employeeFilter) {
            $query = $query->where(function ($subQuery) use ($employeeFilter) {
                $subQuery->where('client_employees.full_name', 'LIKE', '%' . $employeeFilter . '%')
                    ->orWhere('client_employees.code', 'LIKE', '%' . $employeeFilter . '%');
            });
        }

        // Filter by department
        $departmentFilter = !empty($args['department_filter']) ? $args['department_filter'] : '';
        if ($departmentFilter) {
            if (is_array($departmentFilter)) {
                $query->whereIn('client_employees.client_department_id', $departmentFilter);
            } else {
                $query->where('client_employees.client_department_id', $departmentFilter);
            }
        }

        // Filter by status
        $status = !empty($args['status']) ? $args['status'] : [];
        if (!empty($status)) {
            if (is_array($departmentFilter)) {
                $query->whereIn('view_combined_timesheets.work_status', $status);
            } else {
                $query->where('view_combined_timesheets.work_status', $status);
            }
        }

        $listClientEmployeeId = $args['client_employee_ids'] ?? [];
        if (!empty($listClientEmployeeId)) {
            $query = $query->whereIn('client_employees.id', $listClientEmployeeId);
        }

        return $query->orderBy('client_employees.code')->orderBy('check_in_work_shift_for_multiple_shift')->get();
    }

    public function getListTimesheetEmployee($root, array $args)
    {
        $results = $this->timesheetEmployeeQuery($args)->toArray();
        $listHoliday = ClientYearHoliday::where('client_id', $args['client_id'])->pluck('date')->toArray();

        if ($results) {
            $is_holiday = in_array($results[0]['log_date'], $listHoliday);
            foreach ($results as $key => &$r) {
                $r['id'] = $r['code'];
                $r['shift_is_holiday'] = $is_holiday;
                if (isset($r['deleted_at_for_multiple_shift'])) {
                    // if this employee have over 1 shift, remove one.
                    if ((!empty($results[$key - 1]) && $results[$key - 1]['timesheet_id'] == $r['timesheet_id'])
                        || (!empty($results[$key + 1]) && $results[$key + 1]['timesheet_id'] == $r['timesheet_id'])
                    ) {
                        unset($results[$key]);
                    } else {
                        $r['check_in_for_multiple_shift'] = null;
                        $r['check_in_work_shift_for_multiple_shift'] = null;
                        $r['check_out_for_multiple_shift'] = null;
                        $r['check_out_work_shift_for_multiple_shift'] = null;
                        $r['shift_code_for_multiple'] = null;
                    }
                } else {
                    if (!empty($r['check_in_for_multiple_shift'])) {
                        $r['check_in_for_multiple_shift'] = Carbon::parse($r['check_in_for_multiple_shift'])->format('H:i');
                    }
                    if (!empty($r['check_out_for_multiple_shift'])) {
                        $r['check_out_for_multiple_shift'] = Carbon::parse($r['check_out_for_multiple_shift'])->format('H:i');
                    }
                }
            }
        }

        return $results;
    }

    public function getListTimesheetEmployeeByMultipleFilter($root, array $args)
    {
        $results = $this->timesheetEmployeeQuery($args)->toArray();
        $listHoliday = ClientYearHoliday::where('client_id', $args['client_id'])->pluck('date')->toArray();

        if ($results) {
            $is_holiday = in_array($results[0]['log_date'], $listHoliday);
            $listLocationByTimesheetIds = TimeChecking::whereIn('timesheet_id', array_column($results,'timesheet_id'))->orderBy('created_at')->get()->groupBy('timesheet_id');

            foreach ($results as $key => &$r) {
                $r['id'] = $r['code'];
                $r['shift_is_holiday'] = $is_holiday;

                // Location
                $listLocationById = [];
                if($listLocationByTimesheetIds->has($r['timesheet_id'])) {
                    $tempLocations = $listLocationByTimesheetIds->get($r['timesheet_id']);
                    foreach ($tempLocations as $location) {
                        $hourCheckIn = Carbon::parse($location->datetime, Constant::TIMESHEET_TIMEZONE)->toDateTimeString();
                        if(!empty($location->location_checkin)) {
                            $listLocationById[] = [
                                'time' => $hourCheckIn,
                                'url' => '',
                                'location_name' => $location->location_checkin
                            ];
                        } else if(!empty($location->user_location_input)) {
                            $listLocationById[] = [
                                'time' => $hourCheckIn,
                                'url' => '',
                                'location_name' => $location->user_location_input
                            ];
                        } else if(!empty($location->latitude) && !empty($location->longitude))  {
                            $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$location->latitude},{$location->longitude}";
                             $listLocationById[] = [
                                'time' => $hourCheckIn,
                                'url' => $googleMapsUrl,
                                'location_name' => ''
                            ];
                        }
                    }
                }
                $r['location'] = json_encode($listLocationById);

                if (isset($r['deleted_at_for_multiple_shift'])) {
                    // if this employee have over 1 shift, remove one.
                    if ((!empty($results[$key - 1]) && $results[$key - 1]['timesheet_id'] == $r['timesheet_id'])
                        || (!empty($results[$key + 1]) && $results[$key + 1]['timesheet_id'] == $r['timesheet_id'])
                    ) {
                        unset($results[$key]);
                    } else {
                        $r['check_in_for_multiple_shift'] = null;
                        $r['check_in_work_shift_for_multiple_shift'] = null;
                        $r['check_out_for_multiple_shift'] = null;
                        $r['check_out_work_shift_for_multiple_shift'] = null;
                        $r['shift_code_for_multiple'] = null;
                    }
                } else {
                    if (!empty($r['check_in_for_multiple_shift'])) {
                        $r['check_in_for_multiple_shift'] = Carbon::parse($r['check_in_for_multiple_shift'])->format('H:i');
                    }
                    if (!empty($r['check_out_for_multiple_shift'])) {
                        $r['check_out_for_multiple_shift'] = Carbon::parse($r['check_out_for_multiple_shift'])->format('H:i');
                    }
                }
            }
        }

        return $results;
    }

    public function exportListTimesheetEmployeeToExcel($root, array $args)
    {
        // TODO remove this unused function
        return json_encode([]);
    }

    public function exportApproveAdjustHoursToExcel($root, array $args)
    {
        // Pre variable
        $userAuth = Auth::user();
        $clientId = $userAuth->client_id;
        $startDate = !empty($args['start_date']) ? $args['start_date'] : '';
        $endDate = !empty($args['end_date']) ? $args['end_date'] : '';
        $statusFilter = $args['status'] ?? [];
        $clientEmployeeIds = !empty($args['client_employee_ids']) ? $args['client_employee_ids'] : [];
        $departmentFilter = !empty($args['department_filter']) ? $args['department_filter'] : [];
        $employeeFilter = $args['employee_filter'] ?? '';

        $type = Constant::LIST_TYPE_ADJUST_HOURS;
        if (!empty($args['type'])) {
            $type = [$args['type']];
        }

        // Validate
        if (empty($startDate) && empty($endDate)) {
            return false;
        }

        $clientEmployees = ClientEmployee::where('client_id', $userAuth->client_id);

        // Filter by client_employee_ids
        if (!empty($clientEmployeeIds)) {
            $clientEmployees->whereIn('id', $clientEmployeeIds);
        }

        // Filter by departmentFilter
        if (!empty($departmentFilter)) {
            $clientEmployees->whereIn('client_department_id', $departmentFilter);
        }

        // Filter by employee
        if (!empty($employeeFilter)) {
            $clientEmployees->where(function ($query) use ($employeeFilter) {
                $query->where('code', 'LIKE', "%{$employeeFilter}%")
                    ->orWhere('full_name', 'LIKE', "%{$employeeFilter}%");
            });
        }

        $listUserByClientEmployeeId = $clientEmployees->get()->pluck('user_id');

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

        $clientEmployees = $clientEmployees->whereHas('timesheets', function ($query) use ($startDate, $endDate, $type, $statusFilter, $listUserByClientEmployeeId, $maxSubQuery) {
            $query->whereBetween('log_date', [$startDate, $endDate])
                ->whereHas('approves', function ($subQuery) use ($type, $listUserByClientEmployeeId, $statusFilter, $maxSubQuery) {
                    $subQuery->whereIn('type', $type);
                    // Condition status
                    if (!empty($statusFilter)) {
                        $subQuery->where(function ($subQuery1) use ($statusFilter) {
                            if (in_array(Constant::PENDING_STATUS, $statusFilter)) {
                                $subQuery1->whereNull('approved_at')->whereNull('declined_at');
                            }
                            if (in_array(Constant::APPROVE_STATUS, $statusFilter)) {
                                $subQuery1->orWhere('is_final_step', 1);
                            }

                            if (in_array(Constant::DECLINED_STATUS, $statusFilter)) {
                                $subQuery1->orWhereNotNull('declined_at');
                            }
                        });
                    }
                    $subQuery->whereIn('id', function ($subQuery1) use ($type, $listUserByClientEmployeeId, $statusFilter, $maxSubQuery) {
                        $subQuery1->select('id')
                            ->from('approves')
                            ->whereIn('type', $type)
                            ->whereIn('original_creator_id', $listUserByClientEmployeeId)
                            ->join($maxSubQuery, function ($join) {
                                $join->on('approves.approve_group_id', '=', 'max_approves.approve_group_id')
                                    ->on('approves.step', '=', 'max_approves.max_step');
                            });

                        if (!empty($statusFilter)) {
                            $subQuery1->where(function ($subQuery2) use ($statusFilter) {
                                if (in_array(Constant::PENDING_STATUS, $statusFilter)) {
                                    $subQuery2->whereNull('approved_at')->whereNull('declined_at');
                                }
                                if (in_array(Constant::APPROVE_STATUS, $statusFilter)) {
                                    $subQuery2->orWhere('is_final_step', 1);
                                }

                                if (in_array(Constant::DECLINED_STATUS, $statusFilter)) {
                                    $subQuery2->orWhereNotNull('declined_at');
                                }
                            });
                        }
                        $subQuery1->groupBy('approves.approve_group_id');
                    });
                });
        })
            ->with('timesheets', function ($query) use ($startDate, $endDate, $type, $statusFilter, $listUserByClientEmployeeId, $maxSubQuery) {
                $query->whereBetween('log_date', [$startDate, $endDate])
                    ->orderBy('log_date', 'desc')
                    ->whereHas('approves', function ($subQuery) use ($type, $statusFilter, $listUserByClientEmployeeId, $maxSubQuery) {
                        $subQuery->whereIn('type', $type);
                        // Filter by status
                        if (!empty($statusFilter)) {
                            $subQuery->where(function ($subQuery1) use ($statusFilter) {
                                if (in_array(Constant::PENDING_STATUS, $statusFilter)) {
                                    $subQuery1->whereNull('approved_at')->whereNull('declined_at');
                                }
                                if (in_array(Constant::APPROVE_STATUS, $statusFilter)) {
                                    $subQuery1->orWhere('is_final_step', 1);
                                }

                                if (in_array(Constant::DECLINED_STATUS, $statusFilter)) {
                                    $subQuery1->orWhereNotNull('declined_at');
                                }
                            });
                        }
                        $subQuery->whereIn('id', function ($subQuery1) use ($type, $listUserByClientEmployeeId, $statusFilter, $maxSubQuery) {
                            $subQuery1->select('id')
                                ->from('approves')
                                ->whereIn('type', $type)
                                ->whereIn('original_creator_id', $listUserByClientEmployeeId)
                                ->join($maxSubQuery, function ($join) {
                                    $join->on('approves.approve_group_id', '=', 'max_approves.approve_group_id')
                                        ->on('approves.step', '=', 'max_approves.max_step');
                                });
                            if (!empty($statusFilter)) {
                                $subQuery1->where(function ($subQuery2) use ($statusFilter) {
                                    if (in_array(Constant::PENDING_STATUS, $statusFilter)) {
                                        $subQuery2->whereNull('approved_at')->whereNull('declined_at');
                                    }
                                    if (in_array(Constant::APPROVE_STATUS, $statusFilter)) {
                                        $subQuery2->orWhere('is_final_step', 1);
                                    }

                                    if (in_array(Constant::DECLINED_STATUS, $statusFilter)) {
                                        $subQuery2->orWhereNotNull('declined_at');
                                    }
                                });
                            }
                            $subQuery1->groupBy('approves.approve_group_id');
                        });
                    })
                    ->with('approves', function ($subQuery) use ($type, $statusFilter, $listUserByClientEmployeeId, $maxSubQuery) {
                        $subQuery->whereIn('type', $type);
                        // Filter by status
                        if (!empty($statusFilter)) {
                            $subQuery->where(function ($subQuery1) use ($statusFilter) {
                                if (in_array(Constant::PENDING_STATUS, $statusFilter)) {
                                    $subQuery1->whereNull('approved_at')->whereNull('declined_at');
                                }
                                if (in_array(Constant::APPROVE_STATUS, $statusFilter)) {
                                    $subQuery1->orWhere('is_final_step', 1);
                                }

                                if (in_array(Constant::DECLINED_STATUS, $statusFilter)) {
                                    $subQuery1->orWhereNotNull('declined_at');
                                }
                            });
                        }
                        $subQuery->whereIn('id', function ($subQuery1) use ($type, $listUserByClientEmployeeId, $statusFilter, $maxSubQuery) {
                            $subQuery1->select('id')
                                ->from('approves')
                                ->whereIn('type', $type)
                                ->whereIn('original_creator_id', $listUserByClientEmployeeId)
                                ->join($maxSubQuery, function ($join) {
                                    $join->on('approves.approve_group_id', '=', 'max_approves.approve_group_id')
                                        ->on('approves.step', '=', 'max_approves.max_step');
                                });
                            if (!empty($statusFilter)) {
                                $subQuery1->where(function ($subQuery2) use ($statusFilter) {
                                    if (in_array(Constant::PENDING_STATUS, $statusFilter)) {
                                        $subQuery2->whereNull('approved_at')->whereNull('declined_at');
                                    }
                                    if (in_array(Constant::APPROVE_STATUS, $statusFilter)) {
                                        $subQuery2->orWhere('is_final_step', 1);
                                    }

                                    if (in_array(Constant::DECLINED_STATUS, $statusFilter)) {
                                        $subQuery2->orWhereNotNull('declined_at');
                                    }
                                });
                            }
                            $subQuery1->groupBy('approves.approve_group_id');
                        });
                        $subQuery->with('assignee');
                    });
            })->get();

        $timezone_name = !empty($user->timezone_name) ? $user->timezone_name : Constant::TIMESHEET_TIMEZONE;
        foreach ($clientEmployees as $item) {
            $item->rowSpanCount = 0;
            foreach ($item->timesheets as $time) {
                $item->rowSpanCount = $item->rowSpanCount + $time->approves->groupBy('approve_group_id')->count();
            }
        }

        $params = [
            'data' => $clientEmployees,
            'fromDate' => $startDate,
            'toDate' => $endDate,
            'type' => $type,
            'timezoneName' => $timezone_name,
        ];
        // Export excel
        $extension = '.xlsx';

        $fileName = 'Adjust_working_hours' . "_" . time() . $extension;
        $pathFile = 'Approve/' . $fileName;
        Excel::store((new ApproveExport($params)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }


    public function getTimesheetEmployees($root, array $args)
    {
        $clientID = Auth::user()->client_id;

        $perpage = isset($args['perPage']) ? $args['perPage'] : 10;
        $page = isset($args['page']) ? $args['page'] : '1';

        $workScheduleGroup = null;

        if (isset($args['work_schedule_group_id']) && $args['work_schedule_group_id']) {
            $workScheduleGroup = WorkScheduleGroup::where('id', $args['work_schedule_group_id'])->first();
        }

        $filteredEmployees = [];

        $shouldScopeByApprove = false;

        if ($workScheduleGroup) {
            /** @var User $user */
            $user = Auth::user();
            $shouldScopeByApprove = !$user->hasPermissionTo("manage-timesheet");
            if ($shouldScopeByApprove) {
                // TODO create dedicated table: TimesheetRegister to hold value, so we dont need to do query by Approves
                $approves = Approve::query()
                    ->select([
                        "id",
                        "original_creator_id",
                    ])
                    ->where('type', 'CLIENT_REQUEST_TIMESHEET')
                    ->where('assignee_id', $user->id)
                    ->where('target_id', $args['work_schedule_group_id'])
                    ->get()
                    ->keyBy('original_creator_id');
                $filteredUserIds = $approves->pluck('original_creator_id');
                // logger("@@@@ uisd", ['ids' => $filteredUserIds]);
            }

            $timesheets = Timesheet::select('client_employee_id')
                ->where('log_date', '>=', $workScheduleGroup->timesheet_from)
                ->where('log_date', '<=', $workScheduleGroup->timesheet_to)
                ->whereHas('client', function ($client) use ($clientID) {
                    /** @var $client Client */
                    return $client->where((new Client)->getTable() . ".id", $clientID);
                })
                ->groupBy('client_employee_id');

            if ($shouldScopeByApprove) {
                $timesheets->whereHas('clientEmployee', function ($clientEmployee) use ($filteredUserIds) {
                    return $clientEmployee->whereIn('user_id', $filteredUserIds);
                });
            }

            if (isset($args['filter']) && $args['filter']) {
                $timesheets->whereHas('clientEmployee', function ($clientEmployee) use ($args) {
                    return $clientEmployee->where('code', 'LIKE', "%{$args['filter']}%")
                        ->orWhere('full_name', 'LIKE', "%{$args['filter']}%");
                });
            }

            if (isset($args['state']) && $args['state']) {
                $timesheets = $timesheets->where('state', $args['state']);
            }

            $timesheets = $timesheets->get();

            $filteredEmployeeIds = $timesheets->pluck('client_employee_id');

            $filteredEmployees = $filteredEmployeeIds->all();
        }

        // TODO: query bang ClientEmployee + whereHas timesheet se gon hon
        // TODO: Chỉ trả về các thông tin cơ bản, thay vì *
        $paginated = ClientEmployee::select('*')
            ->where('client_id', '=', $clientID)
            ->whereIn('id', $filteredEmployees)
            ->orderBy('full_name');

        if ($workScheduleGroup) {
            $paginated = $paginated->where(
                'work_schedule_group_template_id',
                $workScheduleGroup->work_schedule_group_template_id
            );
        }

        /** @var Paginator $paginated */
        $paginated = $paginated->paginate($perpage, ['id'], 'page', $page);

        $paginatedItems = $paginated->collect();

        if ($shouldScopeByApprove) {
            $paginatedItems = $paginatedItems->transform(function ($v) use ($approves) {
                /** @var ClientEmployee $v */
                $v->approve_id = $approves->has($v->user_id) ? $approves->get($v->user_id)->id : null;
                return $v;
            });
        }

        return [
            'data' => $paginatedItems,
            'pagination' => [
                'total' => $paginated->total(),
                'count' => $paginated->count(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'total_pages' => $paginated->lastPage(),
            ],
        ];
    }

    /**
     * @param $root
     * @param array $args
     *
     * @return string
     * @throws \App\Exceptions\HumanErrorException
     */
    public function autoFillWorkingData($root, array $args)
    {
        $clientEmployeeID = $args['client_employee_id'];
        $wsgId = $args['work_schedule_group_id'];
        $clientId = Auth::user()->client_id;

        $clientEmployee = ClientEmployee::where('id', $clientEmployeeID)
            ->where('client_id', $clientId)
            ->first();

        if (!$clientEmployee) {
            throw new HumanErrorException(__('error.not_found', ['name' => __('employee')]));
        }

        $getSchedules = new GetTimesheetSchedules();
        /** @var TimesheetSchedule[]|\Illuminate\Support\Collection $schedules */
        $schedules = $getSchedules->handle($wsgId, $clientEmployeeID)->whereIn('state', ['work', 'ot']);

        DB::transaction(function () use ($schedules, $clientEmployee) {
            foreach ($schedules->groupBy('date') as $scheduleGroup) {
                /** @var \Illuminate\Support\Collection $scheduleGroup */
                $date = $scheduleGroup->first()->date;
                /** @var Timesheet $timesheet */
                $timesheet = $clientEmployee->touchTimesheet($date);

                if (!$timesheet) {
                    continue;
                }

                if (!$timesheet->check_in) {
                    $timesheet->check_in = $scheduleGroup->min('start');
                }

                if (!$timesheet->check_out) {
                    $timesheet->check_out = $scheduleGroup->max('end');
                }

                if ($timesheet->shift_enabled == 1) {
                    if ($timesheet->shift_check_in && ($timesheet->shift_check_in != $timesheet->check_in)) {
                        $timesheet->check_in = $timesheet->shift_check_in;
                    }
                    if ($timesheet->shift_check_out && ($timesheet->shift_check_out != $timesheet->check_out)) {
                        $timesheet->check_out = $timesheet->shift_check_out;
                    }
                }

                $isNextDay = 0;
                $checkInDate = Carbon::parse('2021-01-01 ' . $timesheet->check_in);
                $checkOutDate = Carbon::parse('2021-01-01 ' . $timesheet->check_out);

                if ($checkOutDate->isBefore($checkInDate)) {
                    $isNextDay = 1;
                }

                $timesheet->next_day = $isNextDay;
                $timesheet->save();
            }
        });

        return 'ok';
    }

    /**
     * @throws \App\Exceptions\HumanErrorException
     */
    public function updateTimesheetStateByWorkScheduleGroup($root, array $args)
    {
        $clientEmployeeID = $args['client_employee_id'];
        $wsgId = $args['work_schedule_group_id'];
        $clientId = isset($args['client_id']) ? $args['client_id'] : '';
        if (!$clientId) {
            $clientId = Auth::user()->client_id;
        }

        $clientEmployee = ClientEmployee::where('id', $clientEmployeeID)
            ->where('client_id', $clientId)
            ->first();
        $wsg = WorkScheduleGroup::where('id', $wsgId)
            ->where('client_id', $clientId)
            ->first();

        if (!$clientEmployee || !$wsg) {
            throw new HumanErrorException(__('error.not_found', ['name' => __('employee')]));
        }

        $handler = new GetTimesheetByWorkScheduleGroup();
        /** @var Timesheet[]|Collection $timesheets */
        $timesheets = $handler->handle($wsgId, $clientEmployeeID);

        DB::transaction(function () use ($args, $timesheets) {
            foreach ($timesheets as $ts) {
                $ts->state = $args['state'];
                if (isset($args['reason'])) {
                    $ts->reason = $args['reason'];
                }
                $ts->save();
            }
        });

        return $timesheets;
    }

    /**
     * @param $root
     * @param array $args
     *
     * @return string
     * @deprecated
     */
    public function confirmApprove($root, array $args)
    {
        // should not be used anymore
        return 'ok';
    }

    public function applyApprove($root, array $args): bool
    {
        $action = $args['action'];
        $timesheets = $args['timesheets'];
        $approveId = $args['approve_id'];
        $clientId = Auth::user()->client_id;
        $approve = Approve::select('*')->where('id', $approveId)->where('client_id', $clientId)->first();
        // Validate
        WorktimeRegisterHelper::validateApplication([$approve]);
        // Bug: tháng nào cũng bị mất wtr, chưa rõ nguyên nhân
        Artisan::call('fix:timesheet_wtr', [
            'id' => $approveId,
        ]);

        if ($action == 'accept') {
            $reviewerId = $args['reviewer_id'];

            $approveFlows = ApproveFlow::where('flow_name', 'CLIENT_REQUEST_TIMESHEET')
                ->where('step', '>', $approve->step)
                ->where('client_id', $clientId)
                ->where('group_id', $approve->client_employee_group_id)
                ->orderBy('step', 'ASC')->get();

            if ($approveFlows->isNotEmpty()) {
                $approveFlow = $approveFlows->first();
                $targetId = $approve->target_id;
                $step = $approveFlow['step'];

                $approveNext = new Approve();
                $approveNext->fill([
                    'client_id' => $approve->client_id,
                    'type' => 'CLIENT_REQUEST_TIMESHEET',
                    'content' => $approve->content,
                    'step' => $step,
                    'target_type' => "App\\Models\\WorktimeRegister",
                    'target_id' => $targetId,
                    'approve_group_id' => $approve->approve_group_id,
                    'creator_id' => Auth::user()->id,
                    'original_creator_id' => $approve->original_creator_id,
                    'assignee_id' => $reviewerId,
                    'is_final_step' => 0,
                    'client_employee_group_id' => $approve->client_employee_group_id,
                    'source' => isset($args['source']) ?? $args['source']
                ])->save();

                Timesheet::whereIn('id', $timesheets)->update(['state' => 'processing']);

                $approve->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $approve->source = isset($args['source']) ?? $args['source'];
                $approve->save();
            } else {
                $approve->is_final_step = 1;
                $approve->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $approve->source = isset($args['source']) ?? $args['source'];
                $approve->save();
                Timesheet::whereIn('id', $timesheets)->update(['state' => 'approved']);
            }

            return true;
        } elseif ($action == 'switch') {
            Approve::where('id', $args['approve_id'])->update([
                'assignee_id' => $args['reviewer_id'],
            ]);

            $reviewer = User::where('id', $args['reviewer_id'])->first();

            if (!empty($reviewer)) {
                $reviewer->notify(new ApproveNotification($approve, $approve->type));
            }
        } else {
            if ($args['comment']) {
                $approve->update(['approved_comment' => $args['comment']]);

                if ($approve->target) {
                    $approve->target->approved_comment = $args['comment'];
                    $approve->target->save();
                }
            }
            $now = Carbon::now();
            $approve->update(['declined_at' => $now->toDateTimeString()]);

            Timesheet::whereIn('id', $timesheets)->update([
                'state' => 'rejected',
                'reason' => $args['comment'],
            ]);

            return true;
        }

        return false;
    }

    public function setFlexibleTimesheet($root, array $args)
    {
        $timesheet = Timesheet::find($args["id"]);
        if (!$timesheet) {
            throw new HumanErrorException(__("error.not_found", ["name" => __("timesheet")]));
        }

        [$flexible_in, $flexible_out] = TimesheetsHelper::getFlexibleCheckoutFromCheckin($args['flexible_check_in'], $timesheet->work_schedule_group_template_id);
        $timesheet->flexible_check_in = $flexible_in;
        $timesheet->flexible_check_out = $flexible_out;
        $timesheet->save();

        return $timesheet;
    }

    public function notifyRequestApprove($root, array $args)
    {
        $user = Auth::user();

        if ($user->clientEmployee->isAssignedFor($args['approved_by'])) {
            $reviewer = ClientEmployee::where('id', $args['approved_by'])->first();
            $workScheduleGroup = WorkScheduleGroup::where('id', $args['id'])->first();

            if (!empty($reviewer) && !empty($workScheduleGroup)) {
                $userReviewer = User::where('id', $reviewer->user_id)->first();

                $userReviewer->notify(new TimesheetRequestApproveNotification($user->clientEmployee, $workScheduleGroup));
            }
        }

        return json_encode(['status' => 'ok']);
    }

    public function notifyConfirmApprove($root, array $args)
    {
        $user = Auth::user();

        $employee = ClientEmployee::where('id', $args['employee_id'])->first();

        if ($employee->isAssignedFor($user->clientEmployee->id)) {
            $workScheduleGroup = WorkScheduleGroup::where('id', $args['id'])->first();

            if (!empty($employee) && !empty($workScheduleGroup)) {
                $userEmployee = User::where('id', $employee->user_id)->first();

                $userEmployee->notify(new TimesheetConfirmApproveNotification($user->clientEmployee, $workScheduleGroup, $args['status']));
            }
        }

        return json_encode(['status' => 'ok']);
    }

    public function exportListTimesheetEmployee($root, array $args)
    {
        $clientId = $args['client_id'];
        $authUser = Auth::user();

        if ($clientId !== $authUser->client_id) {
            throw new AuthorizationException();
        }
        $lang = !empty($authUser->prefered_language) ? $authUser->prefered_language : 'en';
        $fromDate = $args['from_date'];
        $toDate = $args['to_date'];
        $departmentFilter = $args['department_filter'] ?? [];
        $activeStatus = Constant::CLIENT_EMPLOYEE_STATUS_WORKING;
        $query = Timesheet::query()
            ->join('client_employees', 'client_employees.id', '=', 'timesheets.client_employee_id')
            ->join('clients', 'clients.id', '=', 'client_employees.client_id')
            ->select(
                [
                    'timesheets.id as id',
                    'timesheets.client_employee_id',
                    'timesheets.state',
                    'timesheets.log_date',
                    'timesheets.work_status',
                    'client_employees.full_name',
                    'client_employees.code',
                    'client_employees.department',
                ]
            )
            ->where('timesheets.log_date', '>=', $fromDate)
            ->where('timesheets.log_date', '<=', $toDate)
            ->whereNull('client_employees.deleted_at')
            ->where('client_employees.status', $activeStatus)
            ->where('clients.id', $clientId);


        $state = isset($args['state']) ? $args['state'] : "";
        if ($state) {
            $query = $query->where('timesheets.state', $state);
        }

        $employeeFilter = isset($args['employee_filter']) ? $args['employee_filter'] : "";
        if ($employeeFilter) {
            $query = $query->where(function ($subQuery) use ($employeeFilter) {
                $subQuery->where('client_employees.full_name', 'LIKE', '%' . $employeeFilter . '%')
                    ->orWhere('client_employees.code', 'LIKE', '%' . $employeeFilter . '%');
            });
        }

        if (!empty($departmentFilter)) {
            $query->whereIn('client_employees.client_department_id', $departmentFilter);
        }

        if (!empty($args['status'])) {
            $query = $query->whereIn('client_employees.work_status', $args['status']);
        }

        $groupIds = !empty($args['group_ids']) ? $args['group_ids'] : [];
        if (!empty($groupIds)) {
            $user = Auth::user();
            $listClientEmployeeId = $user->getListClientEmployeeByGroupIds($user, $groupIds);
            $query = $query->whereIn('client_employees.id', $listClientEmployeeId);
        }

        $clientEmployeeIds = $query->pluck("timesheets.client_employee_id")->toArray();
        $clientEmployeeIds = array_unique($clientEmployeeIds);
        $formattedFromDate = date('Y-m-d', strtotime($fromDate));
        $formattedToDate = date('Y-m-d', strtotime($toDate));
        $exportId = ModelsTimeSheetEmployeeExport::insertGetId([
            'name' => "TimeSheet Employee - ($formattedFromDate - $formattedToDate)",
            'from_date' => $formattedFromDate,
            'to_date' => $formattedToDate,
            'user_id' => auth()->user()->id,
            'created_at' => Carbon::now()
        ]);
        TimeSheetEmployeeExportJob::dispatch($clientEmployeeIds, $fromDate, $toDate, $exportId, $lang);

        return 'success';
    }

    public function getListTimeSheetEmployeeExport()
    {
        $user = auth()->user();

        return ModelsTimeSheetEmployeeExport::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');
    }

    public function getListTimeSheetEmployeeImport()
    {
        $user = auth()->user();

        return TimeSheetEmployeeImport::where('user_id', $user->id)->orderBy('created_at', 'desc');
    }

    public function getOrCreateTimesheetEmployees($root, array $args)
    {
        if (!empty($args['id'])) {
            $timesheet = Timesheet::find($args['id']);
            return $timesheet;
        } else {
            $input = $args['input'];
            $clientEmployeeId = $input['client_employee_id'];
            $log_date = Carbon::parse($input['log_date'])->format('Y-m-d');
            $clientEmployee = ClientEmployee::where('id', $clientEmployeeId)->first();
            $timesheet = (new Timesheet)->findTimeSheet($clientEmployeeId, $log_date);
            if (!$timesheet) {
                /** @var User $user */
                $user = Auth::user();
                if (($user->hasPermissionTo("manage-timesheet") && ($user->client_id === $clientEmployee->client_id))
                    || ($user->clientEmployee->id == $clientEmployee->id)
                ) {
                    $timesheet = $clientEmployee->touchTimesheet($log_date);
                    return $timesheet;
                }
            }
        }
    }

    public function deleteTimeSheetEmployeeImport($root, array $args)
    {
        $id = $args['id'];
        $user = auth()->user();
        TimeSheetEmployeeImport::where('id', $id)->where('user_id', $user->id)->delete();

        return 'success';
    }

    public function getTimeSheetEmployeeExportDownloadUrl($root, $args)
    {
        $id = $args['id'];
        $user = auth()->user();
        $export = ModelsTimeSheetEmployeeExport::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
        $pathFile = $export->path;
        $fileName = $export->name;

        if (!$pathFile || $pathFile === "") {
            return "error";
        }

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];


        return json_encode($response);
    }


    public function listTimesheetShiftEmployeeAll($root, array $args)
    {
        return $this->timesheetShiftEmployeeQuery($root, $args, false)->get();
    }

    public function listTimesheetShiftEmployee($root, array $args)
    {
        return $this->paginationTimeSheetShiftByEmployee($args);
    }

    public function paginationTimeSheetShiftByEmployee(array $args)
    {
        if (!empty($args['client_id'])) {
            $from_date = $args['from_date'];
            $clientSetting = ClientWorkflowSetting::where('client_id', $args['client_id'])->first();
            $return = ClientEmployee::with(['timesheets' => function ($query) use ($args) {
                $query->withCount('timesheetShiftHistories');
                $query->with(['timesheetShiftMapping' => function ($query_2) {
                    $query_2->withAggregate('timesheetShift', 'check_in')
                        ->orderBy('timesheet_shift_check_in');
                }]);
                if (!empty($args['from_date']) && !empty($args['to_date'])) {
                    $query->whereDate('log_date', '>=', $args['from_date'])
                        ->whereDate('log_date', '<=', $args['to_date']);;
                }
            }])
                ->where('client_employees.client_id', $args['client_id'])
                ->where(function ($query) use ($from_date) {
                    $query->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING)
                        ->orWhere(function ($query_2) use ($from_date) {
                            $query_2->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                                ->where('client_employees.quitted_at', '>', $from_date);
                        });
                })
                ->whereHas('timesheets', function ($query) use ($args, $clientSetting) {
                    if (!$clientSetting->enable_multiple_shift) {
                        $query->where(function ($subQuery) use ($clientSetting) {
                            $subQuery->where('shift_enabled', true)
                                ->orHas('timesheetShiftHistories');
                        });
                    }
                    if (!empty($args['from_date']) && !empty($args['to_date'])) {
                        $query->whereDate('log_date', '>=', $args['from_date'])
                            ->whereDate('log_date', '<=', $args['to_date']);
                    }
                });

            $employeeFilter = isset($args['employee_filter']) ? $args['employee_filter'] : "";
            if ($employeeFilter) {
                $return->where(function ($subQuery) use ($employeeFilter) {
                    $subQuery->where('client_employees.full_name', 'LIKE', '%' . $employeeFilter . '%')
                        ->orWhere('client_employees.code', 'LIKE', '%' . $employeeFilter . '%');
                });
            }

            $clientEmployeeIds = !empty($args['client_employee_ids']) ? $args['client_employee_ids'] : [];
            if (!empty($clientEmployeeIds)) {
                $return->whereIn('client_employees.id', $clientEmployeeIds);
            }

            $departmentIds = !empty($args['department_ids_filter']) ? $args['department_ids_filter'] : [];
            if (!empty($departmentIds)) {
                $return->whereIn('client_employees.client_department_id', $departmentIds);
            }

            $positionIds = !empty($args['position_ids_filter']) ? $args['position_ids_filter'] : [];
            if (!empty($positionIds)) {
                $return->whereIn('client_employees.client_position_id', $positionIds);
            }

            $groupIds = !empty($args['group_ids_filter']) ? $args['group_ids_filter'] : [];
            if (!empty($groupIds)) {
                $return->whereHas('clientEmployeeGroupAssignment', function ($query) use ($groupIds) {
                    $query->whereIn('client_employee_group_id', $groupIds);
                });
            }

            $return->orderBy('client_employees.code');

            return $return;
        }
        return false;
    }

    /**
     * @throws AuthorizationException
     * @throws CustomException
     */
    public function exportTimesheetShiftEmployees($root, array $args)
    {
        $clientId = $args['client_id'];
        $fromDate = $args['from_date'] ?? "";
        $toDate = $args['to_date'] ?? "";

        // Check Auth
        if ($clientId !== Auth::user()->client_id) {
            throw new AuthorizationException();
        }

        // Get setting
        $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $clientId)
            ->select(['enable_timesheet_shift_template_export', 'enable_multiple_shift'])
            ->first();

        // Check exit setting
        if (!$clientWorkflowSetting) {
            throw new CustomException(
                'You are not setting',
                'HumanErrorException'
            );
        }

        $isMultipleShift = $clientWorkflowSetting->enable_multiple_shift;

        $data = $this->{$isMultipleShift ? 'timesheetMultipleShiftEmployeeQuery' : 'timesheetShiftEmployeeQuery'}($root, $args)->get();

        $extension = '.xlsx';

        $template = $clientWorkflowSetting->enable_timesheet_shift_template_export ? ($args['template'] ?? "default") : "default";

        // Translate
        $lang = auth()->user()->prefered_language ? auth()->user()->prefered_language : app()->getLocale();
        app()->setlocale($lang);

        switch ($template) {
            case Constant::ADVANCED:
                // Get company info
                $client = Client::find($clientId);
                if ($isMultipleShift) {
                    $mergedData = $data->groupBy('log_date');
                    $countMaxShiftDay = [];
                    $mergedData->each(function ($item, $key) use (&$countMaxShiftDay) {
                        $countMaxShiftDay[$key] = $item->max('timesheet_shift_mapping_count');
                    });
                    $mergedDataFinal = [];
                    foreach ($countMaxShiftDay as $keyDate => $valueMax) {
                        if ($valueMax == 0) {
                            $valueMax = 1;
                        }
                        for ($i = 0; $i < $valueMax; $i++) {
                            $dataDate = $mergedData->get($keyDate);
                            $dataDate->each(function ($item) use ($keyDate, $i, &$mergedDataFinal) {
                                $mergedDataFinal[$keyDate][$i][$item->client_employee_id] = [];
                                if ($item->timesheet_shift_mapping_count > 0 && isset($item->timesheetShiftMapping[$i])) {
                                    $mergedDataFinal[$keyDate][$i][$item->client_employee_id] = $item->timesheetShiftMapping[$i]->timesheetShift;
                                }
                            });
                        }
                    }

                    // Get list of full names
                    $fullNames = collect($data)->groupBy('client_employee_id')->map(function ($items) {
                        return $items[0]->clientEmployee->full_name;
                    })->toArray();

                    $fileName = "TIMESHEET_MULTIPLE_SHIFT_EMPLOYEES_ADVANCE__" . uniqid() . $extension;
                    $pathFile = 'TimesheetShiftExport/' . $fileName;
                    Excel::store((new TimesheetMultipleShiftEmployeeAdvanceExport($mergedDataFinal, $fullNames, $client, $toDate, $fromDate)), $pathFile, 'minio');
                    break;
                } else {
                    $mergedData = collect($data)->groupBy('log_date')->toArray();
                    // Get a list of dates
                    $period = CarbonPeriod::create($fromDate, $toDate);
                    $dates = collect($period->toArray())->map(function ($date) {
                        return \Carbon\Carbon::parse($date)->format('Y-m-d');
                    });

                    // Get list of full names
                    $fullNames = collect($data)->groupBy('client_employee_id')->map(function ($items) {
                        return $items->pluck('full_name')->unique()->first();
                    })->toArray();
                    $fileName = "TIMESHEET_SHIFT_EMPLOYEES_ADVANCE__" . uniqid() . $extension;
                    $pathFile = 'TimesheetShiftExport/' . $fileName;
                    Excel::store((new TimesheetShiftEmployeeAdvanceExport($dates, $fullNames, $mergedData, $client, $toDate, $fromDate)), $pathFile, 'minio');
                    break;
                }

            default:
                if ($isMultipleShift) {
                    // Get unique list id shift
                    $timesheet_shift_ids = array_unique(Arr::collapse($data->pluck('timesheetShiftMapping.*.timesheet_shift_id')));
                    // Get information shift
                    $appliedShifts = TimesheetShift::whereIn("id", $timesheet_shift_ids)->get();
                    $paramTemps = [];

                    $data->each(function ($item) use (&$paramTemps) {
                        if ($item->timesheetShiftMapping->count() > 0) {
                            $paramTemps[$item->client_employee_id]['full_name'] = $item->clientEmployee->full_name;
                            $paramTemps[$item->client_employee_id]['code'] = $item->clientEmployee->code;

                            $item->timesheetShiftMapping->each(function ($mapping) use (&$paramTemps, $item) {
                                $paramTemps[$item->client_employee_id]['shifts'][$item->log_date][] = $mapping->timesheetShift->shift_code;
                            });

                            if (isset($paramTemps[$item->client_employee_id]['max_shift_day'])) {
                                if ($paramTemps[$item->client_employee_id]['max_shift_day'] <= $item->timesheetShiftMapping->count()) {
                                    $paramTemps[$item->client_employee_id]['max_shift_day'] = $item->timesheetShiftMapping->count();
                                }
                            } else {
                                $paramTemps[$item->client_employee_id]['max_shift_day'] = $item->timesheetShiftMapping->count();
                            }
                        }
                    });


                    $params = [];
                    // Handle add shift if have any other shift in day by creating new list
                    foreach ($paramTemps as $key => $value) {
                        if (!empty($value['shifts'])) {
                            for ($i = 0; $i < $value['max_shift_day']; $i++) {
                                $tempShift = $value['shifts'];
                                $tempShiftDates = [];
                                foreach ($tempShift as $keyDate => $shifts) {
                                    $tempShiftDates[$keyDate] = $shifts[$i] ?? "-:-";
                                }
                                $itemTemp = [
                                    'full_name' => $value['full_name'],
                                    'code' => $value['code'],
                                    'shifts' => $tempShiftDates
                                ];
                                $params[$key . "_$i"] = $itemTemp;
                            }
                        } else {
                            $params[$key] = $value;
                        }
                    }
                } else {
                    // Get unique list id shift
                    $timesheet_shift_ids = $data->pluck('timesheet_shift_id')->unique();
                    // Get information shift
                    $appliedShifts = TimesheetShift::whereIn("id", $timesheet_shift_ids)->get();

                    $params = [];
                    // Loop to add item shift of client employee
                    $data->each(function ($item) use (&$params) {
                        if ($item->shift_code) {
                            $params[$item->client_employee_id]['full_name'] = $item->full_name;
                            $params[$item->client_employee_id]['code'] = $item->code;
                            $params[$item->client_employee_id]['shifts'][$item->log_date] = $item->shift_code;
                        } elseif ($item->shift_is_off_day) {
                            $params[$item->client_employee_id]['full_name'] = $item->full_name;
                            $params[$item->client_employee_id]['code'] = $item->code;
                            $params[$item->client_employee_id]['shifts'][$item->log_date] = __('model.timesheets.work_status.weekly_leave');
                        } elseif ($item->shift_is_holiday) {
                            $params[$item->client_employee_id]['full_name'] = $item->full_name;
                            $params[$item->client_employee_id]['code'] = $item->code;
                            $params[$item->client_employee_id]['shifts'][$item->log_date] = __('holidays');
                        }
                    });
                }

                $fileName = "TIMESHEET_SHIFT_EMPLOYEES__" . uniqid() . $extension;
                $pathFile = 'TimesheetShiftExport/' . $fileName;
                Excel::store((new TimesheetShiftEmployeeExport($params, $appliedShifts, $fromDate, $toDate)), $pathFile, 'minio');
                break;
        }

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        // Delete file
        DeleteFileJob::dispatch($pathFile)->delay(now()->addMinutes(3));

        return json_encode($response);
    }

    public function updateTimesheetShiftEnable($root, array $args)
    {
        $timesheet = Timesheet::query()->where('id', $args["id"])->first();
        if ($timesheet) {
            if (isset($args['timesheet_shift_id'])) {
                if ($args['timesheet_shift_id'] == '') {
                    $timesheet->shift_enabled = true;
                    $timesheet->shift_is_off_day = true;
                    $timesheet->shift_is_holiday = false;
                    $timesheet->timesheet_shift_id = null;
                    $timesheet->save();
                } elseif ($args['timesheet_shift_id'] == 'is_holiday') {
                    $timesheet->shift_enabled = true;
                    $timesheet->shift_is_off_day = false;
                    $timesheet->shift_is_holiday = true;
                    $timesheet->timesheet_shift_id = null;
                    $timesheet->save();
                } else {
                    $timesheetShift = TimesheetShift::where("id", $args['timesheet_shift_id'])->first();
                    if (!empty($timesheetShift)) {
                        $timesheet->shift_enabled = true;
                        $timesheet->shift_shift = $timesheetShift->shift;
                        $timesheet->timesheet_shift_id = $args['timesheet_shift_id'];
                        $timesheet->shift_check_in = substr($timesheetShift->check_in, 0, 5);
                        $timesheet->shift_check_out = substr($timesheetShift->check_out, 0, 5);
                        $timesheet->shift_break_start = substr($timesheetShift->break_start, 0, 5);
                        $timesheet->shift_break_end = substr($timesheetShift->break_end, 0, 5);
                        $timesheet->shift_next_day = $timesheetShift->next_day;
                        $timesheet->shift_next_day_break = $timesheetShift->next_day_break;
                        $timesheet->acceptable_check_in = $args['acceptable_check_in'];
                        $timesheet->shift_is_off_day = false;
                        $timesheet->shift_is_holiday = false;
                        $timesheet->save();
                    }
                }
            } else {
                $timesheet->shift_enabled = 0;
                $timesheet->timesheet_shift_id = null;
                $timesheet->shift_check_in = null;
                $timesheet->shift_check_out = null;
                $timesheet->shift_break_start = null;
                $timesheet->shift_break_end = null;
                $timesheet->shift_next_day = 0;
                $timesheet->shift_next_day_break = 0;
                $timesheet->shift_is_off_day = false;
                $timesheet->shift_is_holiday = false;
                $timesheet->save();
            }
        }
    }

    public function updateMultiTimesheetShiftEnable($root, array $args)
    {
        $timesheet_shift_ids = array_unique(array_column($args['input'], 'timesheet_shift_id'));
        $timesheetShiftList = TimesheetShift::whereIn("id", $timesheet_shift_ids)->get()->keyBy('id');

        $ids = array_unique(array_column($args['input'], 'id'));
        $timesheetList = Timesheet::whereIn("id", $ids)->get()->keyBy('id');

        $updated_by = auth()->user()->clientEmployee->id ?? "";
        $version_group_id = Str::uuid();
        $history_data = [];

        foreach ($args['input'] as $p) {
            /**
             * If database doesn't have this timesheet record, we will create new one.
             */
            if (!empty($timesheetList[$p['id']])) {
                $ts = $timesheetList[$p['id']];
            } else {
                $ce = ClientEmployee::authUserAccessible()->find($p['client_employee_id']);
                $ts = $ce->touchTimesheetWithoutSaving($p['log_date']);
            }

            /** @var Timesheet $ts */
            if (!empty($ts)) {
                $timesheet_shift_id_history = null;
                $type_history = null;
                switch ($p['timesheet_shift_id']) {
                    case 'is_holiday':
                        $ts->shift_enabled = true;
                        $ts->shift_is_off_day = false;
                        $ts->shift_is_holiday = true;
                        $ts->shift_check_in = null;
                        $ts->shift_check_out = null;
                        $ts->shift_break_start = null;
                        $ts->shift_break_end = null;
                        $ts->shift_next_day = 0;
                        $ts->shift_next_day_break = 0;
                        $ts->timesheet_shift_id = null;
                        $type_history = TimesheetShiftHistory::IS_HOLIDAY;
                        break;
                    case 'is_off_day':
                        $ts->shift_enabled = true;
                        $ts->shift_is_off_day = true;
                        $ts->shift_is_holiday = false;
                        $ts->shift_check_in = null;
                        $ts->shift_check_out = null;
                        $ts->shift_break_start = null;
                        $ts->shift_break_end = null;
                        $ts->shift_next_day = 0;
                        $ts->shift_next_day_break = 0;
                        $ts->timesheet_shift_id = null;
                        $type_history = TimesheetShiftHistory::IS_OFF_DAY;
                        break;
                    case '':
                        $ts->shift_enabled = 0;
                        $ts->timesheet_shift_id = null;
                        $ts->shift_check_in = null;
                        $ts->shift_check_out = null;
                        $ts->shift_break_start = null;
                        $ts->shift_break_end = null;
                        $ts->shift_next_day = 0;
                        $ts->shift_next_day_break = 0;
                        $ts->shift_is_off_day = false;
                        $ts->shift_is_holiday = false;
                        $type_history = TimesheetShiftHistory::IS_EMPTY_SHIFT;
                    default:
                        if (!empty($timesheetShiftList[$p['timesheet_shift_id']])) {
                            $timesheetShift = $timesheetShiftList[$p['timesheet_shift_id']];
                            $ts->shift_enabled = true;
                            $ts->shift_shift = $timesheetShift->shift;
                            $ts->timesheet_shift_id = $p['timesheet_shift_id'];
                            $ts->shift_check_in = substr($timesheetShift->check_in, 0, 5);
                            $ts->shift_check_out = substr($timesheetShift->check_out, 0, 5);
                            $ts->shift_break_start = substr($timesheetShift->break_start, 0, 5);
                            $ts->shift_break_end = substr($timesheetShift->break_end, 0, 5);
                            $ts->shift_next_day = $timesheetShift->next_day;
                            $ts->shift_next_day_break = $timesheetShift->next_day_break;
                            $ts->shift_is_off_day = false;
                            $ts->shift_is_holiday = false;
                            $timesheet_shift_id_history = $p['timesheet_shift_id'];
                            $type_history = TimesheetShiftHistory::WORKING;
                        }
                        break;
                }

                if (!empty($p['is_assigned'])) {
                    $ts->save();
                }

                if ($ts->id) {
                    if (!empty($args['group_name'])) {
                        /**
                         * Version will store all current shift histories.
                         */
                        if (!empty($p['timesheet_shift_id']) || !empty($p['is_assigned'])) {
                            $history_data[] = [
                                'id' => Str::uuid(),
                                'timesheet_id' => $ts->id,
                                'timesheet_shift_id' => $timesheet_shift_id_history,
                                'type' => $type_history,
                                'updated_by' => $updated_by,
                                'version_group_id' => $version_group_id,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now()
                            ];
                        }
                    } else {
                        /**
                         * Default only stores the shift history which has the chance.
                         */
                        if (!empty($p['is_assigned'])) {
                            $history_data[] = [
                                'id' => Str::uuid(),
                                'timesheet_id' => $ts->id,
                                'timesheet_shift_id' => $timesheet_shift_id_history,
                                'type' => $type_history,
                                'updated_by' => $updated_by,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now()
                            ];
                        }
                    }
                }
            }
        }

        if ($history_data) {
            if (!empty($args['group_name'])) {
                TimesheetShiftHistoryVersion::insert([
                    'id' => $version_group_id,
                    'client_id' => auth()->user()->clientEmployee->client_id ?? "",
                    'group_name' => $args['group_name'],
                    'sort_by' => !empty($args['order_by']) ? $args['order_by'] : null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
            TimesheetShiftHistory::insert($history_data);
        }
        return true;
    }

    public function timesheetMultipleShiftEmployeeQuery($root, array $args)
    {
        $clientId = $args['client_id'];
        $employeeFilter = $args['employee_filter'] ?? "";
        $clientEmployeeIds = !empty($args['client_employee_ids']) ? $args['client_employee_ids'] : [];
        $groupIds = !empty($args['group_ids']) ? $args['group_ids'] : [];

        $query = Timesheet::whereHas('clientEmployee', function ($subQuery) use ($clientId, $employeeFilter, $clientEmployeeIds, $groupIds) {
            $subQuery->where('client_id', $clientId);
            if ($employeeFilter) {
                $subQuery->where(function ($subQuery1) use ($employeeFilter) {
                    $subQuery1->where('full_name', 'LIKE', '%' . $employeeFilter . '%')
                        ->orWhere('code', 'LIKE', '%' . $employeeFilter . '%');
                });
            }

            if (!empty($clientEmployeeIds)) {
                $subQuery->whereIn('id', $clientEmployeeIds);
            }

            if (!empty($groupIds)) {
                $user = Auth::user();
                $listClientEmployeeId = $user->getListClientEmployeeByGroupIds($user, $groupIds);
                $subQuery->whereIn('client_employees.id', $listClientEmployeeId);
            }
        });

        if (!empty($args['from_date']) && !empty($args['to_date'])) {
            $query->whereDate('timesheets.log_date', '>=', $args['from_date'])
                ->whereDate('timesheets.log_date', '<=', $args['to_date']);
        }

        $query->with(['timesheetShiftMapping.timesheetShift', 'clientEmployee'])->withCount('timesheetShiftMapping')->orderBy('log_date', 'ASC');

        return $query;
    }

    public function timesheetShiftEmployeeQuery($root, array $args, $mode = true)
    {
        $clientId = $args['client_id'];
        $query = Timesheet::select(
            [
                'timesheets.id as id',
                'timesheets.client_employee_id',
                'timesheets.check_in',
                'timesheets.check_out',
                'timesheets.work_status',
                'timesheets.state',
                'timesheets.log_date',
                'timesheets.shift_enabled',
                'timesheets.shift_check_in',
                'timesheets.shift_check_out',
                'timesheets.shift_next_day',
                'timesheets.next_day',
                'timesheets.shift_is_off_day',
                'timesheets.shift_is_holiday',
                'client_employees.full_name',
                'client_employees.code',
                'timesheet_shifts.shift_code',
                'timesheet_shifts.id as timesheet_shift_id',
                'timesheet_shifts.break_start as shift_break_start',
                'timesheet_shifts.break_end as shift_break_end',
                'timesheet_shifts.next_day_break_start as shift_next_day_break_start',
                'timesheet_shifts.next_day_break as shift_next_day_break',
            ]
        )
            ->join('client_employees', 'client_employees.id', '=', 'timesheets.client_employee_id')
            ->join('clients', 'clients.id', '=', 'client_employees.client_id')
            ->leftJoin('timesheet_shifts', 'timesheets.timesheet_shift_id', '=', 'timesheet_shifts.id')
            ->where('clients.id', $clientId)
            ->where(function ($subQuery) use ($mode) {
                if ($mode) {
                    $subQuery->whereHas('timesheetShiftHistories')
                        ->orWhere('timesheets.shift_enabled', true);
                }
            })
            ->orderBy('timesheets.log_date', 'ASC');

        if (!empty($args['from_date']) && !empty($args['to_date'])) {
            $query = $query->whereDate('timesheets.log_date', '>=', $args['from_date'])
                ->whereDate('timesheets.log_date', '<=', $args['to_date']);
        }

        $employeeFilter = isset($args['employee_filter']) ? $args['employee_filter'] : "";

        if ($employeeFilter) {
            $query = $query->where(function ($subQuery) use ($employeeFilter) {
                $subQuery->where('client_employees.full_name', 'LIKE', '%' . $employeeFilter . '%')
                    ->orWhere('client_employees.code', 'LIKE', '%' . $employeeFilter . '%');
            });
        }

        if (!empty($args['client_employee_ids'])) {
            $query = $query->whereIn('client_employees.id', $args['client_employee_ids']);
        }

        $groupIds = !empty($args['group_ids']) ? $args['group_ids'] : [];
        if (!empty($groupIds)) {
            $user = Auth::user();
            $listClientEmployeeId = $user->getListClientEmployeeByGroupIds($user, $groupIds);
            $query = $query->whereIn('client_employees.id', $listClientEmployeeId);
        }

        return $query;
    }

    public function paginationTimesheetHasShift($root, array $args)
    {
        $client_id = Auth::user()->client_id;
        $return = ClientEmployee::with(['timesheets' => function ($query) use ($args) {
            $query->with(['timesheetShiftMapping.timesheetShift'])
                ->where('log_date', $args['log_date'])
                ->where(function ($query_2) {
                    $query_2->where('shift_enabled', 1)
                        ->orWhereHas('timesheetShiftMapping');
                });
        }])
            ->where('client_id', $client_id)
            ->whereHas('timesheets', function ($query) use ($args, $client_id) {
                $query->where('log_date', $args['log_date'])
                    ->where(function ($query_2) {
                        $query_2->where('shift_enabled', 1)
                            ->orWhereHas('timesheetShiftMapping');
                    });
            });

        if (!empty($args['client_employee_ids_filter'])) {
            $return->whereIn('id', $args['client_employee_ids_filter']);
        }

        if (!empty($args['client_department_ids_filter'])) {
            $return->whereIn('client_department_id', $args['client_department_ids_filter']);
        }

        return $return;
    }

    public function recalculatorTimeSheetEmployee($root, array $args)
    {
        $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $args['client_id'])->first(['advanced_permission_flow']);
        $permissions = ['advanced-manage-timesheet-working-read', 'advanced-manage-timesheet-working-update'];
        $user = Auth::user();
        if (empty($args['client_id'])) {
            return false;
        }
        if ($user->isInternalUser()) {
            if (!Client::hasInternalAssignment()->find($args['client_id']) && !($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR)) {
                return false;
            }
        } else {
            if ($clientWorkflowSetting->advanced_permission_flow) {
                if (!($user->hasAllPermissions($permissions))) {
                    throw new AuthenticationException(__("error.permission"));
                    return false;
                }
            } else {
                if (!($user->hasPermissionTo('manage-timesheet'))) {
                    throw new AuthenticationException(__("error.permission"));
                    return false;
                }
            }
        }

        return TimesheetsHelper::recalculateTimesheet($args);
    }

    public function recalculatorTimeSheetByWorkScheduleGroupId($root, array $args)
    {
        if (empty($args['client_id']) || empty($args['work_schedule_group_id'])) {
            return false;
        }

        $user = Auth::user();
        // Check permission
        if ($user->isInternalUser()) {
            if (!Client::hasInternalAssignment()->find($args['client_id']) && !($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR)) {
                throw new AuthenticationException(__("error.permission"));
            }
        } else {
            if (!($user->hasPermissionTo('manage-workschedule'))) {
                throw new AuthenticationException(__("error.permission"));
            }
            // Check setting intenal is enable calculator timesheet
            $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $args['client_id'])->first(['enable_calculator_timesheet']);
            if (!$clientWorkflowSetting->enable_calculator_timesheet) {
                throw new HumanErrorException(__("not_enable_setting"));
            }
        }
        // Check exit template group
        $workScheduleGroup = WorkScheduleGroup::find($args['work_schedule_group_id']);
        if (!$workScheduleGroup) {
            return false;
        }
        ClientEmployee::where([
            'client_id' => $args['client_id'],
            'work_schedule_group_template_id' => $workScheduleGroup['work_schedule_group_template_id'],
        ])
            ->chunkById(100, function ($employees) use ($workScheduleGroup) {
                foreach ($employees as $employee) {
                    // Run job to recalculator timeheets of employee
                    dispatch(new RecalculateTimesheetByMonthOfEmployee($workScheduleGroup, $employee));
                }
            });

        return true;
    }

    public function createTimesheetForListClientEmployee($root, array $args)
    {
        if (!empty($args['client_id']) && !empty($args['work_schedule_group_id']) && !empty($args['client_employee_ids'])) {
            $user = Auth::user();
            // Check permission
            if ($user->isInternalUser()) {
                if (!Client::hasInternalAssignment()->find($args['client_id']) && !($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR)) {
                    throw new AuthenticationException(__("error.permission"));
                }
            } else {
                if (!($user->hasPermissionTo('manage-workschedule'))) {
                    throw new AuthenticationException(__("error.permission"));
                }
            }

            $workScheduleGroup = WorkScheduleGroup::query()->where([
                'id' => $args['work_schedule_group_id']
            ])->first();
            if (!$workScheduleGroup) {
                return false;
            }
            $clientEmployees = ClientEmployee::whereIn('id', $args['client_employee_ids'])->get();
            foreach ($clientEmployees as $clientEmployee) {
                $clientEmployee->refreshTimesheetByWorkScheduleGroupAsync($workScheduleGroup);
            }
            return true;
        } else {
            return false;
        }
        return false;
    }
}
