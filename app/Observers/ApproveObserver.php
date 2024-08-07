<?php

namespace App\Observers;

use App\Exceptions\CustomException;
use App\Jobs\AutoGenerateOTRequest;
use App\Jobs\CreateOrUpdateLeaveHoursOfClientEmployee;
use App\Jobs\PushNewApproveNotificationJob;
use App\Jobs\PushUpdatedApproveNotificationJob;
use App\Jobs\SendErrorApplicationWarningJob;
use App\Models\CcClientEmail;
use App\Models\Checking;
use App\Models\ClientUnitCode;
use App\Models\Timesheet;
use App\Models\WorkScheduleGroup;
use App\Models\WorkScheduleGroupTemplate;
use App\Models\WorktimeRegister;
use App\Models\WorkTimeRegisterTimesheet;
use App\Support\ApproveObserverTrait;
use App\Support\ErrorCode;
use App\Support\WorkTimeRegisterPeriodHelper;
use Exception;
use App\Exceptions\HumanErrorException;
use App\Jobs\ConfirmApproveJob;
use App\Jobs\ImportClientEmployee;
use App\Jobs\SetTimesheetShiftJob;
use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\ApproveGroup;
use App\Models\CalculationSheet;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeDependentApplication;
use App\Models\ClientEmployeeLeaveRequest;
use App\Models\IglocalEmployee;
use App\Models\PaidLeaveChange;
use App\Models\SocialSecurityClaim;
use App\Models\WorkTimeRegisterPeriod;
use App\Models\ClientWorkflowSetting;
use App\Notifications\ApproveNotification;
use App\Notifications\ApproveRequestPayrollNotification;
use App\Support\Constant;
use App\Support\ClientHelper;
use App\Support\ImportHelper;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Support\WorktimeRegisterHelper;
use App\Models\ClientEmployeeDependent;
use Spatie\Period\Period;
use Spatie\Period\Precision;
use Spatie\Period\PeriodCollection;
use App\Models\UnpaidLeaveChange;
use App\Support\LeaveHelper;

class ApproveObserver
{
    use ApproveObserverTrait;
    public function creating(Approve $approve)
    {
        if (!$approve->approve_group_id) {
            $approveGroup = ApproveGroup::create(['client_id' => $approve->client_id, 'type' => $approve->type]);

            $approve->approve_group_id = $approveGroup->id;
        } else {
            $hasFinalApprove = Approve::where('client_id', $approve->client_id)
                ->where('approve_group_id', $approve->approve_group_id)
                ->where('is_final_step', 1)
                ->first();

            if ($hasFinalApprove) {
                throw new HumanErrorException('Can not create, this request is approved!');
            }
        }

        $clientSetting = ClientWorkflowSetting::where('client_id', $approve->client_id)->first();

        // Kiểm tra trước khi tạo approve
        switch ($approve->type) {
            case 'CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR':
                $clientId = $approve->client_id;

                //Delete the old request which is not finished yet (processing status).
                //Before create the new request.
                $processingApprove = Approve::where('type', 'CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR')
                    ->where('target_id', $approve->target_id)
                    ->where('client_id', $clientId)
                    ->whereNull('approved_at')
                    ->whereNull('declined_at')
                    ->get();
                //Because ApproveGroup is always created a new on createApproveMutator::editTimesheetWorkHour
                //So we can delete this processing request base on approve_group_id
                if ($processingApprove) {
                    ApproveGroup::destroy($processingApprove->pluck('approve_group_id'));
                    $processingApprove->each(function ($item, $key) {
                        Approve::where('approve_group_id', $item->approve_group_id)->delete();
                    });
                }
                break;
            case 'INTERNAL_UPDATE_CLIENT':
                if (!$approve->assignee_id) {
                    $firstApproveFlow = ApproveFlow::where('client_id', '000000000000000000000000')
                        ->where('flow_name', 'INTERNAL_UPDATE_CLIENT')
                        ->with('approveFlowUsers')
                        ->orderBy('step', 'ASC')->first();

                    if ($firstApproveFlow && $firstApproveFlow->approveFlowUsers) {
                        $approve->target_type = 'App\Models\Client';
                        $approve->assignee_id = $firstApproveFlow->approveFlowUsers[0]['user_id'];
                    } else {
                        throw new HumanErrorException('Chưa có người duyệt thay đổi thông tin khách hàng');
                    }
                }
                break;
            case "CLIENT_REQUEST_OT":
            case "CLIENT_REQUEST_OT_ASSIGNMENT":
            case "CLIENT_REQUEST_OFF":
            case "CLIENT_REQUEST_EARLY_LEAVE":
            case 'CLIENT_REQUEST_CONG_TAC':
            case 'CLIENT_REQUEST_ROAD_TRANSPORTATION':
            case 'CLIENT_REQUEST_AIRLINE_TRANSPORTATION':
                if (!empty($approve->target)) {
                    $target = $approve->target;
                    if ($target->type == Constant::TYPE_LEAVE) {
                        if ($target->category == 'year_leave') {
                            WorktimeRegisterHelper::processYearLeaveChange($target);
                        } elseif (!Str::of($target->category)->isUuid()) {
                            WorktimeRegisterHelper::processLeaveChange($target);
                        }
                    }
                }
                break;
            case 'CLIENT_REQUEST_CANCEL_ROAD_TRANSPORTATION':
            case 'CLIENT_REQUEST_CANCEL_AIRLINE_TRANSPORTATION':
                if (!empty($approve->target)) {
                    $target = $approve->target;
                    // Validate when use change setting internal
                    WorktimeRegisterHelper::validateWhenUserChangeSetting($target, $clientSetting);
                }
                break;
            case "CLIENT_REQUEST_TIMESHEET_SHIFT":

                // Lấy đơn đã request
                $approves = Approve::where([
                    'type' => 'CLIENT_REQUEST_TIMESHEET_SHIFT',
                    'original_creator_id' => auth()->user()->id,
                    'approved_at' => null,
                    'declined_at' => null
                ])->where('processing_state', '!=', 'fail')
                    ->get();

                $duplicatedData = []; // Initialize an array to store duplicated data

                $decodedDataRaw = json_decode($approve->content, true);

                foreach ($decodedDataRaw['dataUpdate']['input'] as $item) {
                    if (
                        empty($item['log_date']) || empty($item['client_employee_id'])
                    ) {
                        //TODO:
                        throw new HumanErrorException(__('warning.WR0005.import'));
                    }
                }

                $approveDateRangeRaw = $decodedDataRaw['dateRange'];

                $groupedDataRaw = collect($decodedDataRaw['dataUpdate']['input'])->groupBy('log_date');


                foreach ($approves as $item) {

                    $decodedData = json_decode($item->content, true);

                    $approveDateRange = $decodedData['dateRange'];

                    $startDate = Carbon::parse($approveDateRange[0])->startOfDay();
                    $endDate = Carbon::parse($approveDateRange[1])->endOfDay();

                    $datarangePeriod = Period::make($startDate, $endDate);

                    $jsonStartDate = Carbon::parse($approveDateRangeRaw[0])->startOfDay();
                    $jsonEndDate = Carbon::parse($approveDateRangeRaw[1])->endOfDay();

                    $jsonDataPeriod = Period::make($jsonStartDate, $jsonEndDate);

                    $overlap = $datarangePeriod->overlapsWith($jsonDataPeriod);

                    if ($overlap) {

                        $groupedData = collect($decodedData['dataUpdate']['input'])->groupBy('log_date');

                        $groupedClientEmployeeIds = $groupedData->map(function ($item) {
                            return $item->pluck('client_employee_id')->unique()->toArray();
                        });
                        $groupedDataRawClientEmployeeIds = $groupedDataRaw->map(function ($item) {
                            return $item->pluck('client_employee_id')->unique()->toArray();
                        });

                        $commonLogDates = $groupedClientEmployeeIds->keys()->intersect($groupedDataRawClientEmployeeIds->keys());

                        foreach ($commonLogDates as $logDate) {
                            $clientEmployeeIds1 = $groupedClientEmployeeIds[$logDate];
                            $clientEmployeeIds2 = $groupedDataRawClientEmployeeIds[$logDate];

                            $duplicatedClientEmployeeIds = array_intersect($clientEmployeeIds1, $clientEmployeeIds2);

                            if (!empty($duplicatedClientEmployeeIds)) {
                                $duplicatedUserData = ClientEmployee::whereIn('id', $duplicatedClientEmployeeIds)->pluck('full_name')->toArray();
                                if (!isset($duplicatedData[$logDate])) {
                                    $duplicatedData[$logDate] = [];
                                }

                                $duplicatedData[$logDate] = array_unique(
                                    array_merge($duplicatedData[$logDate], $duplicatedUserData)
                                );
                            }
                        }
                    }
                }

                if ($duplicatedData) {

                    $errorMsg = __('warning.approve.timesheet_shift');

                    foreach ($duplicatedData as $logDate => $duplicatedEmployees) {
                        $formattedEmployees = implode(', ', $duplicatedEmployees);
                        $errorMsg .= "\n<b>{$logDate}</b>: {$formattedEmployees}";
                    }

                    throw new HumanErrorException($errorMsg);
                }
                break;
        }


        // expected to be prefill by mutator
        // $approve->creator_id = $user->id;
        // $approve->is_final_step = 0;

        if (!$approve->original_creator_id) {
            if ($approve->creator_id) {
                $approve->original_creator_id = $approve->creator_id;
            } elseif (Auth::user()) {
                // Auth is not available when executing Job queue and Event
                $approve->original_creator_id = $approve->creator_id = Auth::user()->id;
                logger()->warning(self::class . "@creating: Approve created without creator_id");
            } else {
                logger()->warning(self::class . "@creating: Approve created without creator_id via Non-HTTP");
            }
        }

        if (
            !$approve->assignee_id &&
            strpos($approve->type, "INTERNAL_") == 0
        ) {
            $user = User::query()->where("id", $approve->creator_id)->first();
            if ($user && $user->is_internal) {

                $role = $user->iGlocalEmployee->role;
                if ($role == Constant::ROLE_INTERNAL_LEADER) {
                    $director = IglocalEmployee::query()
                        ->with('user')
                        ->where('role', Constant::ROLE_INTERNAL_DIRECTOR)
                        ->first();
                    if (!$director) {
                        logger()->error("Internal system doesn't have Director");
                    } elseif (!$director->user) {
                        logger()->error("Internal system's Director doesn't have login account");
                    } else {
                        $approve->assignee_id = $director->user->id;
                    }
                } elseif ($role == Constant::ROLE_INTERNAL_STAFF) {
                    $leader = IglocalEmployee::query()
                        ->with('user')
                        ->where('role', Constant::ROLE_INTERNAL_LEADER)
                        ->first();
                    if (!$leader) {
                        logger()->error("Internal system doesn't have Director");
                    } elseif (!$leader->user) {
                        logger()->error("Internal system's Director doesn't have login account");
                    } else {
                        $approve->assignee_id = $leader->user->id;
                    }
                }
            }
        }

        // Cancellation approval pending
        WorkTimeRegisterPeriodHelper::updateCancellationApprovalPending($approve, true);

        if (in_array($approve->type, Constant::TYPE_CANCEL_ADVANCED_APPROVE) && ($approve->creator_id == $approve->assignee_id)) {
            $approve->processing_state = 'complete';
            $approve->approved_at = Carbon::now();
            $approve->is_final_step = 1;
            $this->handleApprovedCancel($approve);
        } elseif (in_array($approve->type, ['CLIENT_REQUEST_OT']) && ($approve->creator_id == $approve->assignee_id) && $approve->is_final_step == 1) {
            $approve->processing_state = 'complete';
            $approve->approved_at = Carbon::now();
            $this->processFinalStep($approve);
        } else {
            $approve->is_final_step = 0;
        }

        $approve->info_app = ClientHelper::getInfoApp();
    }

    public function updating(Approve $approve)
    {
        if ($approve->processing_state == 'fail') {
            $this->processFailStep($approve);
        } else {
            // can not be changed by client
            if (
                !$approve->assignee_id &&
                strpos($approve->type, "INTERNAL_") == 0
            ) {
                $director = IglocalEmployee::query()
                    ->with('user')
                    ->where('role', Constant::ROLE_INTERNAL_DIRECTOR)
                    ->first();
                if (!$director) {
                    logger()->error("Internal system doesn't have Director");
                } elseif (!$director->user) {
                    logger()->error("Internal system's Director doesn't have login account");
                } else {
                    if ($approve->target_type == 'App\Models\WorktimeRegister') {
                        $target = $approve->target;
                        if ($target->sub_type != 'ot_makeup') {
                            $approve->assignee_id = $director->user->id;
                        }
                    } else {
                        $approve->assignee_id = $director->user->id;
                    }
                }
            }
            // otherwise error may occured

            $oldApprovedAt = $approve->getOriginal('approved_at');
            if ($oldApprovedAt != $approve->approved_at && $oldApprovedAt == null) {
                // check if this is last step in flow
                $step = $approve->step;
                // Comment chỗ này vì approve dùng cả IGLOCAL
                // if (!$user->isInternalUser()) {
                $flow = ApproveFlow::query()
                    ->where('client_id', $approve->client_id)
                    ->where('flow_name', $approve->flow_type ?? $approve->type)
                    ->where('group_id', $approve->client_employee_group_id)
                    ->orderBy('step', 'desc')
                    ->first();
                $maxStep = $flow ? $flow->step : 1;
                logger('maxStep', [$maxStep, $step]);
                if ($step >= $maxStep) {

                    $approve->is_final_step = 1;

                    $this->processFinalStep($approve);
                }
                // }
            }
        }
    }

    protected function processFinalStep(Approve $approve)
    {
        /** @var User $user */
        $user = $approve->assignee;
        $content = json_decode($approve->content, true);
        $isNotification = true;

        logger()->info("[ApproveFlow] Final step is accepted", [$approve->id, $approve->type]);
        try {
            switch ($approve->type) {
                case 'INTERNAL_MANAGE_CALCULATION':
                    if (!empty($approve->target)) {
                        $target = $approve->target;
                        $target->status = "client_review";
                        $target->save();
                    }

                    $calculationSheet = CalculationSheet::where('id', $approve->target_id)->first();
                    if (!empty($calculationSheet))
                        $isNotification = $calculationSheet->enable_notification_new_payroll;

                    break;
                case 'INTERNAL_IMPORT_CLIENT_EMPLOYEE':

                    if (!empty($approve->target)) {
                        $target = $approve->target;
                        $target->status = "approved";
                        $target->save();
                    }

                    ImportClientEmployee::dispatch($approve);

                    break;
                case 'INTERNAL_UPDATE_CLIENT':

                    if (!$content) {
                        break;
                    }
                    $lang = isset($content['lang']) ? $content['lang'] : app()->getLocale();
                    app()->setlocale($lang);
                    $client = Client::where('id', $content['id'])->first();
                    $clientArr = $client->toArray();

                    if ($client) {

                        $newData = [];

                        foreach ($clientArr as $key => $oldData) {

                            if (isset($content[$key]) && $content[$key] != $oldData) {
                                $newData[$key] = $content[$key];
                            }
                        }

                        logger('client update new data', [$newData]);

                        if ($newData) {
                            $client->update($newData);
                        }

                        $clientUnitCodesUpsert = $content['clientUnitCode']['upsert'] ?? [];
                        $clientUnitCodesDelete = $content['clientUnitCode']['delete'] ?? [];
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

                        $ccClientEmailsUpsert = $content['ccClientEmail']['upsert'] ?? [];
                        $ccClientEmailsDelete = $content['ccClientEmail']['delete'] ?? [];
                        if (!empty($ccClientEmailsUpsert)) {
                            foreach ($ccClientEmailsUpsert as &$ccClientEmail) {
                                $ccClientEmail['client_id'] = $client->id;
                                if (empty($ccClientEmail['id']))
                                    $ccClientEmail['id'] = Str::uuid();
                            }
                            CcClientEmail::upsert($ccClientEmailsUpsert, 'id');
                        }

                        if (!empty($ccClientEmailsDelete)) {
                            CcClientEmail::destroy($ccClientEmailsDelete);
                        }
                    }

                    break;
                case "CLIENT_UPDATE_EMPLOYEE_BASIC":
                case "CLIENT_UPDATE_EMPLOYEE_PAYROLL":
                    if (!$content) {
                        break;
                    }
                    $employee = ClientEmployee::where('id', $content['id'])->first();

                    if ($employee) {
                        if (isset($content['currentData'])) {
                            unset($content['currentData']);
                        }
                        if (isset($content['blood_group'])) {
                            $content['blood_group'] = ImportHelper::BLOOD_GROUPS[$content['blood_group']];
                        }
                        $employee->fill($content);
                        $employee->save();
                    }
                    break;
                case "CLIENT_UPDATE_DEPENDENT":
                    if (!$content) {
                        break;
                    }

                    // Flow navigation by client type
                    $dependent = ($approve->client->client_type === Constant::CLIENT_OUTSOURCING)
                        ? ClientEmployeeDependentApplication::create($content)
                        : ClientEmployeeDependent::create($content);

                    dispatch(function () use ($approve, $dependent) {
                        $mediaCollection = $approve->getMedia('Attachments');
                        $mediaCollection->each(function ($mediaItem) use ($dependent) {
                            try {
                                $mediaItem->copy($dependent, 'Attachments', 'minio');
                            } catch (Exception $e) {
                                // Retry the job up to 2 times
                                retry(2, function () use ($mediaItem, $dependent) {
                                    $mediaItem->copy($dependent, 'Attachments', 'minio');
                                });
                            }
                        });
                    })->delay(now()->addSeconds(15));

                    break;
                case "CLIENT_REQUEST_OT_ASSIGNMENT":
                case "CLIENT_REQUEST_OT":
                    /** @var WorktimeRegister $target */
                    if (!empty($approve->target) && !is_null($user)) {
                        $target = $approve->target;
                        $target->fill($content);
                        // Set flexible checkin/out of employee
                        $this->setFlexibleByForm($target, $content);
                        $target->status = "approved";
                        if ($user->clientEmployee) {
                            $target->approved_by = $user->clientEmployee->id;
                        }
                        $target->approved_date = Carbon::now();

                        $target->saveQuietly();
                        // Create makeup request when create OT of the past
                        $this->createMakeupRequestWhenCreateOTOfThePast($target);
                        $target->createOrUpdateOTWorkTimeRegisterTimesheet();
                    }
                    break;
                case "CLIENT_REQUEST_OFF":
                case "CLIENT_REQUEST_EARLY_LEAVE":
                case 'CLIENT_REQUEST_CONG_TAC':
                case 'CLIENT_REQUEST_ROAD_TRANSPORTATION':
                case 'CLIENT_REQUEST_AIRLINE_TRANSPORTATION':
                case "CLIENT_REQUEST_TIMESHEET":
                    /** @var ClientEmployeeLeaveRequest $target */
                    if (!empty($approve->target)) {
                        $target = $approve->target;
                        // Set flexible checkin/out of employee
                        $this->setFlexibleByForm($target, $content);
                        $target->fill($content);
                        $target->status = "approved";
                        if ($user->clientEmployee) {
                            $target->approved_by = $user->clientEmployee->id;
                        }
                        $target->approved_date = Carbon::now();
                        // Update year paid leave count when approve application < now()
                        if ($approve->type == 'CLIENT_REQUEST_OFF') {
                            if ($target->category == 'year_leave') {
                                WorktimeRegisterHelper::processYearLeaveChange($target, true);
                            } elseif (!Str::of($target->category)->isUuid()) {
                                $this->updateLeaveCount($target);
                            }
                        }
                        $target->save();
                    }

                    break;
                case 'CLIENT_UPDATE_EMPLOYEE_OTHERS':
                    if (!empty($approve->target)) {
                        $target = $approve->target;
                        $target->status = "approved";
                        if ($user->clientEmployee) {
                            $target->approved_by = $user->clientEmployee->id;
                        }
                        $target->approved_date = Carbon::now();

                        $target->save();
                    }

                    break;
                case "CLIENT_REQUEST_SOCIAL_SECURITY_PROFILE":
                    /** @var ClientEmployeeLeaveRequest $target */
                    if (!empty($approve->target)) {
                        $target = $approve->target;
                        $target->fill($content);
                        $target->status = "approved";
                        if ($user->clientEmployee) {
                            $target->approved_by = $user->clientEmployee->id;
                        }
                        $target->approved_date = Carbon::now();
                        $target->tinh_trang_phia_khach_hang = 'da_phe_duyet';
                        $target->save();
                    }

                    break;
                case "CLIENT_REQUEST_PAYROLL":

                    if (!empty($approve->target)) {
                        $target = $approve->target;
                        $target->fill(['status' => "client_approved"]);
                        $target->save();
                    }

                    break;
                case "CLIENT_REQUEST_CLAIM_BHXH":
                    /** @var SocialSecurityClaim $target */
                    if (!empty($approve->target)) {
                        $target = $approve->target;
                        $target->state = "processing";
                        $target->client_approved = 1;
                        $target->save();
                    }
                    break;
                case "CLIENT_REQUEST_EDITING_FLEXIBLE_TIMESHEET":
                    if (!empty($approve->target)) {
                        $target = $approve->target;
                        $target->flexible_check_in = $content['request_check_in'];
                        $target->flexible_check_out = $content['request_check_out'];
                        $target->skip_plan_flexible = true;
                        $target->save();
                    }
                    break;
                case "CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR":
                    $checkingList = [];
                    if (!empty($approve->target)) {
                        if ($approve->target_type == "App\Models\TimesheetShiftMapping") {
                            $target = $approve->target;
                            $target->check_in = $content['request_check_in'];
                            $target->check_out = $content['request_check_out'];
                            $target->skip_hanet = true;
                            $target->save();

                            $target->timesheet->storeInOut(Carbon::parse($target->check_in));
                            $target->timesheet->storeInOut(Carbon::parse($target->check_out));
                            $target->timesheet->calculateMultiTimesheet();
                            $target->timesheet->saveQuietly();

                            $checkingList = [
                                [
                                    'client_id' => $approve->client_id,
                                    'client_employee_id' => $target->timesheet->client_employee_id,
                                    'checking_time' => Carbon::parse($target->check_in)->toDateTimeString(),
                                    'source' => 'Request'
                                ],
                                [
                                    'client_id' => $approve->client_id,
                                    'client_employee_id' => $target->timesheet->client_employee_id,
                                    'checking_time' => Carbon::parse($target->check_out)->toDateTimeString(),
                                    'source' => 'Request'
                                ]
                            ];
                        } else {
                            $target = $approve->target;
                            $target->check_in = $content['request_check_in'];
                            $target->check_out = $content['request_check_out'];
                            $target->start_next_day = isset($content['start_next_day']) && $content['start_next_day'] == 1 ? 1 : 0;
                            $target->next_day = isset($content['next_day']) && $content['next_day'] == 1 ? 1 : 0;
                            $target->flexible = 1;
                            $target->skip_hanet = true;
                            $target->recalculate();
                            $target->saveQuietly();

                            // if compensatory working
                            $employee = ClientEmployee::find($target->client_employee_id);
                            $workFlowSetting = $employee->client->clientWorkflowSetting;
                            if ($workFlowSetting->auto_create_makeup_request_form || $workFlowSetting->enable_auto_generate_ot) {
                                $workGroupTemplate = $employee->workScheduleGroupTemplate;
                                if ($workGroupTemplate->enable_makeup_or_ot_form) {
                                    $workGroup = WorkScheduleGroup::where('work_schedule_group_template_id', $workGroupTemplate->id)
                                        ->where('timesheet_from', '<=', $target->log_date)
                                        ->where('timesheet_to', '>=', $target->log_date)->first();
                                    if ($workGroup) {
                                        $now = Carbon::now();
                                        $deadlineTimesheet = !empty($workGroup->timesheet_deadline_at) ? Carbon::parse($workGroup->timesheet_deadline_at) : null;
                                        $deadlineApproved = !empty($workGroup->approve_deadline_at) ? Carbon::parse($workGroup->approve_deadline_at) : null;
                                        if (!is_null($deadlineTimesheet) && !is_null($deadlineApproved) && $now->isAfter($deadlineTimesheet) && $now->isBefore($deadlineApproved)) {
                                            $type = $workFlowSetting->enable_auto_generate_ot ? Constant::OVERTIME_TYPE : Constant::MAKEUP_TYPE;
                                            $this->removeAndCreateAutoApplication($target, $target->log_date, $type);
                                        }
                                    }
                                }
                            }

                            $checkingList = [
                                [
                                    'client_id' => $approve->client_id,
                                    'client_employee_id' => $target->client_employee_id,
                                    'checking_time' => ($target->start_next_day ? Carbon::parse($target->log_date . ' ' . $target->check_in)->addDay() : Carbon::parse($target->log_date . ' ' . $target->check_in))->toDateTimeString(),
                                    'source' => 'Request'
                                ],
                                [
                                    'client_id' => $approve->client_id,
                                    'client_employee_id' => $target->client_employee_id,
                                    'checking_time' => ($target->next_day ? Carbon::parse($target->log_date . ' ' . $target->check_out)->addDay() : Carbon::parse($target->log_date . ' ' . $target->check_out))->toDateTimeString(),
                                    'source' => 'Request'
                                ]
                            ];
                        }
                    } else {
                        if (!empty($approve->targetWithTrashed) && $approve->target_type == "App\Models\TimesheetShiftMapping") {
                            $target = $approve->targetWithTrashed;
                            $target->check_in = $content['request_check_in'];
                            $target->check_out = $content['request_check_out'];
                            $target->skip_hanet = true;
                            $target->save();

                            $checkingList = [
                                [
                                    'client_id' => $approve->client_id,
                                    'client_employee_id' => $target->timesheet->client_employee_id,
                                    'checking_time' => Carbon::parse($target->check_in)->toDateTimeString(),
                                    'source' => 'Request'
                                ],
                                [
                                    'client_id' => $approve->client_id,
                                    'client_employee_id' => $target->timesheet->client_employee_id,
                                    'checking_time' => Carbon::parse($target->check_out)->toDateTimeString(),
                                    'source' => 'Request'
                                ]
                            ];
                        }
                    }

                    if (!empty($checkingList)) {
                        Checking::upsert($checkingList, ['client_employee_id', 'checking_time']);
                    }
                    break;
                case "CLIENT_REQUEST_CANCEL_OT_ASSIGNMENT":
                case "CLIENT_REQUEST_CANCEL_OT":
                case "CLIENT_REQUEST_CANCEL_OFF":
                case "CLIENT_REQUEST_CANCEL_CONG_TAC":
                case "CLIENT_REQUEST_CANCEL_ROAD_TRANSPORTATION":
                case "CLIENT_REQUEST_CANCEL_AIRLINE_TRANSPORTATION":
                    logger("handleApprovedCancel");
                    $this->handleApprovedCancel($approve);
                    break;
                case "CLIENT_REQUEST_PAYMENT":
                    if (!empty($approve->target)) {
                        $target = $approve->target;
                        $target->status = "approved";
                        $target->saveQuietly();
                    }
                    break;
                case "CLIENT_REQUEST_CHANGED_SHIFT":
                case "CLIENT_REQUEST_TIMESHEET_SHIFT":
                    if (!$content) {
                        break;
                    }
                    $originalCreator = User::where('id', $approve->original_creator_id)->first();

                    dispatch_sync(new SetTimesheetShiftJob($content, $originalCreator));

                    break;
                default:
                    if (!empty($approve->target)) {
                        $target = $approve->target;
                        $target->status = "approved";
                        $target->save();
                    }
                    break;
            }
        } catch (Exception $e) {
            $clientCode = $approve->client_id == '000000000000000000000000' ? 'INTERNAL' : optional($approve->client)->code;

            dispatch(new SendErrorApplicationWarningJob($clientCode, $approve, 'Final approve error!!'));

            $approve->refresh();
            $approve->processing_state = 'fail';
            $approve->processing_error = $e->getMessage();
            $approve->saveQuietly();

            throw $e;
        }


        if ($isNotification) {
            try {
                $isNotSend = false;
                $originalCreator = User::where('id', $approve->original_creator_id)->first();
                if ($approve->type == 'CLIENT_REQUEST_OT_ASSIGNMENT' || $approve->type == 'CLIENT_REQUEST_OT') {
                    // Not send notification with automatically generated orders(makeup or OT)
                    if (empty($approve->assignee_id)) {
                        $isNotSend = true;
                    }
                }

                if (!empty($originalCreator) && !$isNotSend) {
                    dispatch(
                        new PushUpdatedApproveNotificationJob($approve, 'approved')
                    );
                    $originalCreator->notify(new ApproveNotification($approve, $approve->type, 'approved'));
                }
            } catch (\Exception $e) {
                logger()->warning($approve->type . " can not sent email");
            }
        }
    }

    protected function processDeclineStep(Approve $approve)
    {

        $originalCreator = User::where('id', $approve->original_creator_id)->first();

        if (!empty($originalCreator)) {
            dispatch(
                new PushUpdatedApproveNotificationJob($approve, 'rejected')
            );
            $originalCreator->notify(new ApproveNotification($approve, $approve->type, 'decline'));
        }

        switch ($approve->type) {
            case "INTERNAL_IMPORT_CLIENT_EMPLOYEE":
            case "INTERNAL_REQUEST_PIT_REPORT":
            case "CLIENT_REQUEST_OT_ASSIGNMENT":
            case "CLIENT_REQUEST_OT":
            case "CLIENT_REQUEST_CONG_TAC":
            case "CLIENT_REQUEST_OFF":
            case "CLIENT_REQUEST_EARLY_LEAVE":
            case "CLIENT_REQUEST_TIMESHEET":
            case "CLIENT_UPDATE_EMPLOYEE_OTHERS":
                /** @var ClientEmployeeLeaveRequest $target */
                if (!empty($approve->target)) {
                    $target = $approve->target;
                    $target->status = "canceled";
                    $target->save();
                }
                break;
            case "CLIENT_REQUEST_SOCIAL_SECURITY_PROFILE":
                if (!empty($approve->target)) {
                    $target = $approve->target;
                    $target->status = "rejected";
                    $target->approved_comment = $approve->approved_comment;
                    $target->tinh_trang_phia_khach_hang = 'da_tu_choi';
                    $target->save();
                }

                break;
            case "CLIENT_REQUEST_PAYROLL":
                if (!empty($approve->target)) {
                    $target = $approve->target;
                    $target->status = "client_rejected";
                    $target->save();
                }
                break;
            case 'INTERNAL_MANAGE_CALCULATION':
                if (!empty($approve->target)) {
                    $target = $approve->target;
                    $target->status = "leader_declined";
                    $target->approved_comment = $approve->approved_comment;
                    $target->save();
                }
                break;
            case "CLIENT_REQUEST_CLAIM_BHXH":
                if (!empty($approve->target)) {
                    $target = $approve->target;
                    $target->state = "done";
                    $target->client_approved = -1;
                    $target->save();
                }
                break;
        }
    }

    protected function processFailStep(Approve $approve)
    {
        if (!empty($approve->target)) {
            $target = $approve->target;

            switch ($approve->type) {
                case "CLIENT_REQUEST_PAYROLL":
                    $target->status = "client_review";
                    $target->saveQuietly();
                    logger('ApproveObserver&processFailStep CLIENT_REQUEST_PAYROLL ' . $approve->id);
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Handle the approve "created" event.
     *
     * @param \App\Models\Approve $approve
     *
     * @return void
     */
    public function created(Approve $approve)
    {
        $action = $approve->type;
        try {
            switch ($approve->type) {
                case 'INTERNAL_ACTIVATE_CLIENT':
                    $directors = User::systemNotifiable()->with('iGlocalEmployee')->get();

                    $directors->each(function (User $director) use ($approve, $action) {
                        $role = $director->iGlocalEmployee->role ?? false;

                        if ($role === Constant::ROLE_INTERNAL_DIRECTOR) {
                            $director->notify(new ApproveNotification($approve, $action));
                        } else {
                            logger()->warning("The user of iGlocal doesn't have the required role");
                        }
                    });
                    break;
                case 'CLIENT_REQUEST_PAYROLL':
                    $assignee = User::find($approve->assignee_id);

                    if ($assignee) {
                        $calculationSheet = CalculationSheet::find($approve->target_id);

                        if ($calculationSheet && $calculationSheet->enable_notification_new_payroll) {
                            // uncomment this if mobile app support calculation sheet approval
                            // dispatch(
                            //     new PushNewApproveNotificationJob($approve)
                            // );
                            $assignee->notify(new ApproveRequestPayrollNotification($approve));
                        }
                    }
                    break;
                case 'INTERNAL_MANAGE_CALCULATION':
                    $target = $approve->target;

                    if ($target && $target->status == 'processed') {
                        $target->status = "director_review";
                        $target->save();
                    }

                    $assignee = User::find($approve->assignee_id);

                    if ($assignee) {
                        $calculationSheet = CalculationSheet::find($approve->target_id);

                        if ($calculationSheet && $calculationSheet->enable_notification_new_payroll) {
                            $assignee->notify(new ApproveNotification($approve, $action, 'processing'));
                        }
                    }
                    break;
                case 'CLIENT_UPDATE_EMPLOYEE_OTHERS':
                    $target = $approve->target;

                    if ($target) {
                        $target->status = 'pending';
                        $target->save();
                    }
                    break;
                default:
                    $assignee = User::find($approve->assignee_id);

                    if ($assignee) {
                        dispatch(
                            new PushNewApproveNotificationJob($approve)
                        );
                        $assignee->notify(new ApproveNotification($approve, $action, 'processing'));
                    }
                    break;
            }
        } catch (\Exception $e) {
            logger()->warning($approve->type . " cannot send email");
        }

        try {
            if ($approve->client_id != Constant::INTERNAL_DUMMY_CLIENT_ID) {

                $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $approve->client_id)->first(['advanced_approval_flow']);
                if (
                    !optional($clientWorkflowSetting)->advanced_approval_flow ||
                    !in_array($approve->type, Constant::TYPE_ADVANCED_APPROVE)
                ) {
                    $finalApproveFlow = ApproveFlow::where('client_id', $approve->client_id)
                        ->where('flow_name', $approve->flow_type ?? $approve->type)
                        ->where('group_id', $approve->client_employee_group_id)
                        ->with('approveFlowUsers')
                        ->orderBy('step', 'DESC')
                        ->first();
                    if (!empty($finalApproveFlow->approveFlowUsers)) {
                        $approveFlowUser = $finalApproveFlow->approveFlowUsers->where('user_id', $approve->original_creator_id)->first();

                        if ($approveFlowUser) {
                            $approve->step = $finalApproveFlow->step;
                            $approve->approved_at = Carbon::now()->toDateTimeString();
                            $approve->save();
                        }
                    }
                }
                $assignee = $approve->assignee;
                $client = $approve->client;
                $settings = $client->clientWorkflowSetting;

                if ($assignee && $assignee->auto_approve && $settings->enable_auto_approve) {
                    $this->autoApprove($approve);
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }


    private function autoApprove($approve)
    {
        $flows = ApproveFlow::where('client_id', $approve->client_id)
            ->where('flow_name', $approve->flow_type ?? $approve->type)
            ->where('group_id', $approve->client_employee_group_id)
            ->get();
        $reviewerId = null;
        if ($approve->step < $flows->max('step')) {
            $nextFlow = $flows->firstWhere('step', $approve->step + 1);
            $nextFlowUsers = $nextFlow->approveFlowUsers;
            if ($nextFlowUsers && $nextFlowUsers->count() > 0) {
                $reviewerId = $nextFlowUsers->first()->user_id;
            }
        }

        switch ($approve->type) {
            case "CLIENT_REQUEST_OT_ASSIGNMENT":
            case "CLIENT_REQUEST_OT":
            case "CLIENT_REQUEST_OFF":
            case "CLIENT_REQUEST_EARLY_LEAVE":
            case "CLIENT_REQUEST_CONG_TAC":
            case "CLIENT_REQUEST_ROAD_TRANSPORTATION":
            case "CLIENT_REQUEST_AIRLINE_TRANSPORTATION":
            case "CLIENT_REQUEST_TIMESHEET":
            case "CLIENT_REQUEST_EDITING_FLEXIBLE_TIMESHEET":
            case "CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR":
                dispatch_sync(new ConfirmApproveJob('accept', $approve->id, "auto approve", $approve->creator_id, $reviewerId));
                break;
            default:
                # code...
                break;
        }
    }

    /**
     * Handle the approve "updated" event.
     *
     * @param \App\Models\Approve $approve
     *
     * @return void
     */
    public function updated(Approve $approve)
    {

        if (empty($approve->declined_at)) {

            if (empty($approve->approved_at)) {

                if (in_array($approve->type, [
                    Constant::INTERNAL_ACTIVATE_CLIENT,
                    Constant::INTERNAL_UPDATE_CLIENT,
                ])) {

                    $employee = User::systemNotifiable()
                        ->where('id', $approve->assignee_id)
                        ->with('iGlocalEmployee')
                        ->first();

                    if (!empty($employee)) {
                        $employee->notify(new ApproveNotification($approve, $approve->type));
                    }
                }
            } else {

                if ($approve->type == Constant::INTERNAL_APPROVED_CLIENT) {

                    $employee = User::systemNotifiable()
                        ->where('id', $approve->creator_id)
                        ->with('iGlocalEmployee')
                        ->first();

                    if (!empty($employee)) {
                        $employee->notify(new ApproveNotification($approve, $approve->type));
                    }
                } else if ($approve->type == Constant::INTERNAL_UPDATE_CLIENT) {

                    $employee = User::systemNotifiable()
                        ->where('id', $approve->creator_id)
                        ->with('iGlocalEmployee')
                        ->first();

                    if (!empty($employee)) {
                        $employee->notify(new ApproveNotification($approve, Constant::INTERNAL_CONFIRM_UPDATED_CLIENT));
                    }
                }
            }
        } else {
            $this->processDeclineStep($approve);
        }
    }

    /**
     * @throws HumanErrorException
     */
    public function deleting(Approve $approve)
    {
        $this->checkApproveBeforeDelete($approve->target_id);
    }

    /**
     * Handle the approve "deleted" event.
     *
     * @param \App\Models\Approve $approve
     *
     * @return void
     */
    public function deleted(Approve $approve)
    {

        if (!empty($approve->declined_at) && ($approve->type == 'INTERNAL_ACTIVATE_CLIENT' ||
            $approve->type == 'INTERNAL_UPDATE_CLIENT')) {

            $employee = User::systemNotifiable()
                ->where('id', $approve->creator_id)
                ->with('iGlocalEmployee')
                ->first();

            if ($employee) {
                $action = Constant::INTERNAL_DISAPPROVED_CLIENT;
                $role = isset($employee->iGlocalEmployee['role']) ? $employee->iGlocalEmployee['role'] : false;
                switch ($role) {
                    case Constant::ROLE_INTERNAL_STAFF:
                    case Constant::ROLE_INTERNAL_LEADER:
                        $employee->notify(new ApproveNotification($approve, $action));
                    default:
                        logger()->warning("The user of iGlocal is don't role");
                }
            } else {
                logger()->error("Internal system doesn't have Employee");
            }
        }
    }

    public function handleApprovedCancel($approve)
    {
        $content = json_decode($approve->content, true);
        if (!empty($approve->target)) {
            $wtr = $approve->target;
            if ($wtr->periods->count() > 0) {
                if ($wtr->periods->count() == 1 || (isset($content['deleteFull']) && $content['deleteFull'] == true)) {
                    $wtr->update(['status' => 'canceled_approved']);
                } else {
                    $start_time = $end_time = null;
                    foreach ($wtr->periods as $item) {
                        if (($item->date_time_register == $content['date_time_register']) &&
                            (substr($item->start_time, 0, 5) == substr($content['start_time'], 0, 5)) &&
                            (substr($item->end_time, 0, 5) == substr($content['end_time'], 0, 5))
                        ) {
                            if ($item->da_tru == 1) {
                                $month = Carbon::parse($item->date_time_register)->format('n');
                                $year = Carbon::parse($item->date_time_register)->format('Y');
                                // Handle Year Leave
                                if ($item->category == 'year_leave' && $item->logical_management) {
                                    // Phép năm trước sẽ được hoàn về nếu còn hạn (logical_management = true)
                                    $paidLeaveChangeSummary = WorktimeRegisterHelper::getYearPaidLeaveChange($content['client_employee_id']);

                                    if ($item->deduction_last_year > 0) {
                                        if (!empty($paidLeaveChangeSummary['han_su_dung_gio_phep_nam_truoc']) && !Carbon::parse($paidLeaveChangeSummary['han_su_dung_gio_phep_nam_truoc'])->isPast()) {
                                            $newLeaveChange = PaidLeaveChange::create([
                                                'client_id' => $item->client_id,
                                                'client_employee_id' => $item->client_employee_id,
                                                'work_time_register_id' => $item->worktime_register_id,
                                                'category' => $item->category,
                                                'year_leave_type' => LeaveHelper::YEAR_LEAVE_TYPE['last_year'],
                                                'changed_ammount' => -1 * $item->deduction_last_year,
                                                'changed_reason' => Constant::TYPE_SYSTEM,
                                                'effective_at' => $item->date_time_register,
                                                'month' => $month,
                                                'year' => $year
                                            ]);
                                        }
                                    } elseif ($item->deduction_current_year > 0) {
                                        $newLeaveChange = PaidLeaveChange::create([
                                            'client_id' => $item->client_id,
                                            'client_employee_id' => $item->client_employee_id,
                                            'work_time_register_id' => $item->worktime_register_id,
                                            'category' => $item->category,
                                            'year_leave_type' => LeaveHelper::YEAR_LEAVE_TYPE['current_year'],
                                            'changed_ammount' => -1 * $item->deduction_current_year,
                                            'changed_reason' => Constant::TYPE_SYSTEM,
                                            'effective_at' => $item->date_time_register,
                                            'month' => $month,
                                            'year' => $year
                                        ]);
                                    }
                                } else {
                                    $changedValue = -1 * $item->so_gio_tam_tinh;

                                    $subTypeleaveChange = ($content['sub_type'] === Constant::AUTHORIZED_LEAVE) ? PaidLeaveChange::class : UnpaidLeaveChange::class;

                                    $leaveChange = $subTypeleaveChange::where('work_time_register_id', $item->worktime_register_id)
                                        ->where('changed_ammount', $changedValue)
                                        ->where('category', $content['category'])
                                        ->where('month', $month)
                                        ->where('year', $year)->first();

                                    if (!empty($leaveChange)) {

                                        $newLeaveChange = $subTypeleaveChange::create([
                                            'client_id' => $leaveChange->client_id,
                                            'client_employee_id' => $leaveChange->client_employee_id,
                                            'work_time_register_id' => $leaveChange->work_time_register_id,
                                            'category' => $leaveChange->category,
                                            'changed_ammount' => $item->so_gio_tam_tinh,
                                            'changed_reason' => Constant::TYPE_SYSTEM,
                                            'effective_at' => $leaveChange->effective_at,
                                            'month' => $leaveChange->month,
                                            'year' => $leaveChange->year
                                        ]);
                                        $leaveChange->delete();
                                        $newLeaveChange->delete();
                                    }
                                }
                            }
                            $item->delete();
                        } else {
                            $st = '00:00:00';
                            $et = '23:59:59';
                            if ($item->type_register == 1) {
                                $st = $item->start_time;
                                $et = $item->end_time;
                            }
                            if (!$start_time) {
                                $start_time = $item->date_time_register . ' ' . $st;
                            }
                            if (!$end_time) {
                                $end_time = $item->date_time_register . ' ' . $et;
                            }
                            $startTime = $item->date_time_register . ' ' . $st;
                            $endTime = ($item->next_day ? Carbon::parse($item->date_time_register . ' ' . $et)->addDay()->format('Y-m-d') : $item->date_time_register) . ' ' . $et;

                            if (!Carbon::parse($start_time)->isBefore(Carbon::parse($startTime))) {
                                $start_time = $startTime;
                            }
                            if (!Carbon::parse($end_time)->isAfter(Carbon::parse($endTime))) {
                                $end_time = $endTime;
                            }
                        }
                    }
                    $wtr->update([
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                    ]);
                    $approveRequest = Approve::query()->where('target_id', $approve->target_id)->whereNotIn('type',  Constant::TYPE_CANCEL_ADVANCED_APPROVE)->first();
                    if ($approveRequest !== NULL) {
                        $workTimeRegisterPeriod = [];
                        $contentRequest = json_decode($approveRequest->content, true);
                        foreach ($contentRequest['workTimeRegisterPeriod'] as $value) {
                            if ($value['date_time_register'] != $content['date_time_register']) {
                                array_push($workTimeRegisterPeriod, $value);
                            }
                        }
                        $contentRequest['workTimeRegisterPeriod'] = $workTimeRegisterPeriod;
                        $approveRequest->content = json_encode($contentRequest);
                        $approveRequest->save();
                    }
                }
            }

            // Reset flexible_checkin and flexible_checkout
            $workTimeRegisterPeriod = $content['workTimeRegisterPeriod'] ?? [];
            if (!empty($workTimeRegisterPeriod)) {
                $clientEmployee = ClientEmployee::where(['code' => $content['clientEmployee']['code'], 'work_schedule_group_template_id' => $content['clientEmployee']['work_schedule_group_template_id']])->first();
                if ($clientEmployee && $clientEmployee->timesheet_exception == 'applied_flexible_time') {
                    foreach ($workTimeRegisterPeriod as $p) {
                        $clientEmployeeWithPeriod = ClientEmployee::select(['timesheet_exception', 'work_schedule_group_template_id', 'client_id'])
                            ->withCount(["worktimeRegisterPeriod" => function ($query) use ($p) {
                                $query->where('date_time_register', $p['date_time_register'])
                                    ->where('status', '!=', 'canceled_approved')
                                    ->where('status', '!=', 'canceled');
                            }])->where('id', $clientEmployee->id)->first();
                        if (!$clientEmployeeWithPeriod->worktime_register_period_count) {
                            $timesheet = Timesheet::where([
                                'client_employee_id' => $clientEmployee->id,
                                'log_date' => $p['date_time_register']
                            ])->first();
                            if ($timesheet) {
                                if (!empty($timesheet->check_in)) {
                                    $timesheet->flexible = 1;
                                    $timesheet->save();
                                } else {
                                    $timesheet->update([
                                        'flexible_check_in' => null,
                                        'flexible_check_out' => null,
                                        'skip_plan_flexible' => false,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            if ($wtr->type == 'leave_request') {
                dispatch(new CreateOrUpdateLeaveHoursOfClientEmployee(null, $wtr));
            }

            // Cancellation approval pending
            WorkTimeRegisterPeriodHelper::updateCancellationApprovalPending($approve, false);
        }
    }

    public function updateLeaveCount($workTimeRegister)
    {
        $today = Carbon::now();
        WorkTimeRegisterPeriod::where('da_tru', false)
            ->where('so_gio_tam_tinh', '>', 0)
            ->whereDate('date_time_register', '<', $today->format('Y-m-d'))
            ->where('worktime_register_id', $workTimeRegister->id)
            ->chunkById(100, function ($periods) use ($workTimeRegister) {
                foreach ($periods as $period) {
                    $month = Carbon::parse($period->date_time_register)->format('n');
                    $year = Carbon::parse($period->date_time_register)->format('Y');
                    $clientId = $workTimeRegister['clientEmployee']['client_id'];
                    if ($workTimeRegister['status'] == 'approved') {
                        $realWorkHours = -1 * $period->so_gio_tam_tinh;
                        $period->update(['da_tru' => 1]);

                        $type = ($workTimeRegister['sub_type'] === Constant::AUTHORIZED_LEAVE) ? PaidLeaveChange::class : UnpaidLeaveChange::class;
                        $type::create([
                            'client_id' => $clientId,
                            'client_employee_id' => $workTimeRegister['client_employee_id'],
                            'work_time_register_id' => $workTimeRegister['id'],
                            'category' => $workTimeRegister['category'],
                            'changed_ammount' => $realWorkHours,
                            'changed_reason' => Constant::TYPE_SYSTEM,
                            'effective_at' => $workTimeRegister['approved_date'],
                            'month' => $month,
                            'year' => $year
                        ]);
                    }
                }
            }, 'id');
    }

    public function createMakeupRequestWhenCreateOTOfThePast($worktimeRegister)
    {
        // Override makeup request form when create OT form request
        /** @var ClientEmployee $clientEmployee */
        $clientEmployee = $worktimeRegister->clientEmployee;
        $clientWorkFlowSetting = ClientWorkflowSetting::where('client_id', $clientEmployee->client_id)->first();
        if ($worktimeRegister->type == 'overtime_request' && $worktimeRegister->status == 'approved' && $clientWorkFlowSetting->enable_makeup_request_form && $clientWorkFlowSetting->auto_create_makeup_request_form) {
            $listDateTimeRegister = $worktimeRegister->workTimeRegisterPeriod->keyBy('date_time_register')->keys();
            // Create Makeup request form
            Timesheet::where('client_employee_id', $worktimeRegister->client_employee_id)->whereIn('log_date', $listDateTimeRegister)->chunkById(10, function ($items) {
                foreach ($items as $item) {
                    if (empty($item->check_in) && empty($item->check_out) || $item->check_in == '00:00' && $item->check_out == '00:00') {
                        continue;
                    }
                    $item->is_update_work_schedule = true;
                    $item->recalculate();
                }
            });
        }
    }

    public function setFlexibleByForm($target, $content)
    {
        $employee = ClientEmployee::find($target->client_employee_id);
        if ($employee->timesheet_exception == Constant::TYPE_FLEXIBLE_TIMESHEET && isset($content['workTimeRegisterPeriod'])) {
            foreach ($content['workTimeRegisterPeriod'] as $period) {
                if (!empty($period['change_flexible_checkin'])) {
                    $timeSheet = Timesheet::where([
                        'client_employee_id' => $target->client_employee_id,
                        'log_date' => $period['date_time_register']
                    ])->first();
                    if ($timeSheet && !$timeSheet->shift_enabled) {
                        $timeSheet->flexible_check_in = $period['change_flexible_checkin'];
                        $timeSheet->setFlexibleInOuT($employee, true);
                        $timeSheet->save();
                    }
                }
            }
        }
    }

    /*
 *  Only for OT and Makeup application
 * */
    public function removeAndCreateAutoApplication($timesheet, $date, $type)
    {
        // Get application
        WorktimeRegister::where('client_employee_id', $timesheet->client_employee_id)
            ->where('status', 'approved')
            ->where('auto_created', 1)
            ->whereIn('type', [Constant::OVERTIME_TYPE, Constant::MAKEUP_TYPE])
            ->whereHas('workTimeRegisterPeriod', function ($query) use ($date) {
                $query->where('date_time_register', $date);
            })->chunkById(10, function ($items) use ($timesheet) {
                foreach ($items as $item) {
                    $item->reCalculatedOTWhenCancel();
                    $item->delete();
                }
            });

        // Create new OT and MAKEUP APPLICATION
        dispatch_sync(new AutoGenerateOTRequest($timesheet->id, $type));
    }
}
