<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\HumanErrorException;
use App\Models\Approve;
use App\Models\ApproveGroup;
use App\Models\ApproveFlowUser;
use App\Models\ClientWorkflowSetting;
use App\Models\ClientYearHoliday;
use App\Models\Timesheet;
use App\Models\TimesheetShift;
use App\Models\TimesheetShiftMapping;
use App\Models\WorkScheduleGroup;
use App\Models\WorkTimeRegisterPeriod;
use App\Support\Constant;
use App\Support\TimesheetsHelper;
use App\Support\WorktimeRegisterHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Type\Time;

class CreateApproveMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        if (isset($args['intercede_user_id'])) {
            $user = Auth::user();
            $approve = new Approve($args);
            $approve->client_id = $user->client_id;
            $approve->creator_id = $args['intercede_user_id'];

            $approve->save();

            return $approve;
        } else {
            $user = Auth::user();

            $approve = new Approve($args);
            $approve->client_id = $user->client_id;
            $approve->creator_id = $user->id;

            $approve->save();

            return $approve;
        }
    }

    public function createChangedShiftApprove($root, array $args)
    {
        $user = Auth::user();
        $employee = $user->clientEmployee;

        $timesheet = Timesheet::find($args['timesheet_id']);

        if (!$timesheet || $employee->id != $timesheet->client_employee_id) {
            throw new HumanErrorException(__("authorized"));
        }

        $log_date = $timesheet->log_date;
        $client_employee_id = $employee->id;

        $dateRegister = [
            'date_time_register' => $log_date
        ];
        WorktimeRegisterHelper::checkValidateDeadlineApprove([$dateRegister], $employee);

        $hasPeriod = WorkTimeRegisterPeriod::where('date_time_register', $log_date)
            ->whereHas('worktimeRegister', function ($query) use($client_employee_id) {
                $query->where('client_employee_id', $client_employee_id);
            })->count();

        if ($hasPeriod) {
            throw new HumanErrorException(__("the_same_approve_application"));
        }

        $multiple_shift = ClientWorkflowSetting::select('enable_multiple_shift')
            ->where('client_id', $user->client_id)->first();

        $holiday = ClientYearHoliday::where('client_id', $user->client_id)
            ->where('date', $log_date)
            ->first();

        $timesheetShifts = TimesheetShift::where('client_id', $user->client_id)->get();

        $timesheetShifts->each(function ($item) {
            $item->totalHours = (string)$item->work_hours;
        });

        $timesheetShiftOptions = $timesheetShifts->toArray();

        $timesheetShiftOptionsKeyValue = $timesheetShifts->keyBy('id');

        if ($multiple_shift->enable_multiple_shift) {
            $dataUpdate = [];
            $timesheetShiftMapping = [];

            foreach ($args['data'] as $item) {
                if (empty($item['new_id']) && empty($item['old_id'])) {
                    throw new HumanErrorException(__("authorized"));
                }

                if (!empty($item['new_id'])) {
                    if (!empty($item['old_id'])) {
                        $dataUpdate[] = collect([
                            'timesheet_id' => $timesheet->id,
                            'id_table' => $employee->id . '-' . $log_date,
                            'is_assigned' => true,
                            'old_shift_id' => $item['old_id'],
                            'timesheet_shift_id' => $item['new_id'],
                            'log_date' => $log_date,
                            'client_employee_id' => $employee->id,
                        ]);
                    } else {
                        $dataUpdate[] = collect([
                            'timesheet_id' => $timesheet->id,
                            'id_table' => $employee->id . '-' . $log_date,
                            'is_assigned' => true,
                            'timesheet_shift_id' => $item['new_id'],
                            'log_date' => $log_date,
                            'client_employee_id' => $employee->id,
                        ]);
                    }

                    $shift = $timesheetShiftOptionsKeyValue->get($item['new_id']);
                    $timesheetShiftMapping[] = collect([
                        'id' => optional($shift)->id,
                        'color' => optional($shift)->color,
                        'symbol' => optional($shift)->symbol,
                    ]);
                } else {
                    $dataUpdate[] = collect([
                        'timesheet_id' => $timesheet->id,
                        'id_table' => $employee->id . '-' . $log_date,
                        'is_assigned' => true,
                        'timesheet_shift_id' => $item['old_id'],
                        'log_date' => $log_date,
                        'client_employee_id' => $employee->id,
                        'is_deleting' => true,
                    ]);
                }
            }

            $timesheet_input = collect([
                'id' => $timesheet->id,
                'id_table' => $employee->id . '-' . $log_date,
                'is_assigned' => true,
                'timesheet_shift_id' => $args['data'][0]['new_id'] ?? "",
                'log_date' => $log_date,
                'client_employee_id' => $employee->id,
                'timesheetShiftMapping' => $timesheetShiftMapping
            ]);
        } else {
            $dataUpdate[] = $timesheet_input = collect([
                'id' => $timesheet->id,
                'id_table' => $employee->id . '-' . $log_date,
                'is_assigned' => true,
                'timesheet_shift_id' => $args['data'][0]['new_id'] ?? "",
                'log_date' => $log_date,
                'client_employee_id' => $employee->id,
            ]);
        }

        $dataTable[] = collect([
            'id' => $employee->id,
            'code' => $employee->code,
            'full_name' => $employee->full_name,
            'avatar_path' => $employee->avatar_path,
            'client_department_code' => $employee->client_department_code,
            'client_position_code' => $employee->client_position_code,
            'work_schedule_group_template_id' => $employee->work_schedule_group_template_id,
            'selected' => false,
            $timesheet->log_date => $timesheet_input
        ]);

        $content = [
            'dataTable' => $dataTable,
            'totalDay' => [$log_date],
            'saveByMode' => 'default',
            'targetUpdate' => $dataUpdate,
            'titleRangeDate' => $log_date,
            'clientYearHolidays' =>  $holiday ? [$holiday] : [],
            'timesheetShiftOptions' => $timesheetShiftOptions,
            'timesheetShiftOptionsKeyValue' => $timesheetShiftOptionsKeyValue,
            'type' => $multiple_shift->enable_multiple_shift ? 'multiple' : 'single',
            'dateRange' => [$log_date, $log_date],
            'dataUpdate' => collect([
                'group_name' => "",
                'input' => $dataUpdate
            ]),
            'log_date' => $timesheet->log_date,
            'renderData' => $args['data'],
            'reason' => $args['reason'] ?? ""
        ];

        //Using the same flow with "CLIENT_REQUEST_TIMESHEET_SHIFT"
        if (isset($args['client_employee_group_id'])) {
            $defaultClientEmployeeGroup = $args['client_employee_group_id'];
        } else {
            $defaultClientEmployeeGroup = Approve::getClientEmployeeGroupId($user, "CLIENT_REQUEST_TIMESHEET_SHIFT");
        }

        $approve = new Approve();
        $approveGroup = ApproveGroup::create([
            'client_id' => $user->client_id,
            'type' => "CLIENT_REQUEST_CHANGED_SHIFT"
        ]);

        $approve->client_id = $user->client_id;
        $approve->type = "CLIENT_REQUEST_CHANGED_SHIFT";
        $approve->creator_id = $user->id;
        $approve->assignee_id = $args['assignee_id'];
        $approve->content = json_encode($content);
        $approve->step = $args['step'];
        $approve->target_type = "App\Models\Timesheet";
        $approve->target_id = $timesheet->id;
        $approve->original_creator_id = $user->id;
        $approve->is_final_step = 0;
        $approve->client_employee_group_id = $defaultClientEmployeeGroup;
        $approve->source = $args['source'] ?? "";
        $approve->approve_group_id = $approveGroup->id;
        $approve->saveOrFail();

        return $approve;
    }

    private function validateApproveFlexibleTime($timesheet_id, $client_employee)
    {
        $timesheet = Timesheet::find($timesheet_id);
        // Validate form request when exceed the approve deadline of the past
        $dateRegister = [
            'date_time_register' => $timesheet->log_date
        ];
        WorktimeRegisterHelper::checkValidateDeadlineApprove([$dateRegister], $client_employee);
        if ($timesheet->client_employee_id != $client_employee->id) {
            throw new HumanErrorException(__("authorized"));
        }

        $client_workflow_setting = ClientWorkflowSetting::where('client_id', $client_employee->client_id)->first();
        if (
            empty($client_workflow_setting)
            || !$client_workflow_setting->enable_flexible_request_setting
            || !$client_workflow_setting->number_of_flexible_request_in_month
        ) {
            throw new HumanErrorException(__("authorized"));
        }

        $wsg = WorkScheduleGroup::where('work_schedule_group_template_id', $client_employee->work_schedule_group_template_id)
            ->whereDate('timesheet_from', "<=", $timesheet->log_date)
            ->whereDate('timesheet_to', ">=", $timesheet->log_date)
            ->first();

        $timesheetIds = Timesheet::whereBetween('log_date', [$wsg->timesheet_from, $wsg->timesheet_to])
            ->where('client_employee_id', $timesheet->client_employee_id)
            ->get('id')->pluck('id');

        $approveGroups = Approve::where('type', 'CLIENT_REQUEST_EDITING_FLEXIBLE_TIMESHEET')
            ->where('target_type', Timesheet::class)
            ->whereIn('target_id', $timesheetIds)
            ->get()
            ->groupBy('approve_group_id');

        $numberRequestInMonth = 0;
        $approveGroups->each(function ($group, $key) use (&$numberRequestInMonth) {
            $decline = $group->filter(function ($item, $key_2) {
                return isset($item->declined_at);
            });
            if ($decline->isEmpty()) {
                $numberRequestInMonth++;
            }
        });

        if ($numberRequestInMonth >= $client_workflow_setting->number_of_flexible_request_in_month) {
            throw new HumanErrorException(__("requests_are_over"));
        }
    }

    public function createApproveFlexibleTime($root, array $args)
    {
        $user = Auth::user();
        $employee = $user->clientEmployee;
        $type = Constant::EDIT_FLEXIBLE_TIME;

        // Validate
        {
            self::validateApproveFlexibleTime($args['id'], $employee);
            // Validate exit approve
            $approveExit = Approve::where([
                'type' => $type,
                'target_id' => $args['id'],
                'is_final_step' => 0
            ])->whereNull('approved_at')->whereNull('declined_at')->orderBy('created_at', 'DESC')->first();
            if ($approveExit) {
                 throw new HumanErrorException(__("the_same_approve_application"));
            }
        }

        //Using the same flow with "CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR"
        if (isset($args['client_employee_group_id'])) {
            $defaultClientEmployeeGroup = $args['client_employee_group_id'];
        } else {
            $defaultClientEmployeeGroup = Approve::getClientEmployeeGroupId($user, Constant::EDIT_WORK_HOUR);
        }

        $approveFlowUser = ApproveFlowUser::where('user_id', $args['assignee_id'])
            ->with('approveFlow')
            ->whereHas('approveFlow', function ($query) use ($defaultClientEmployeeGroup) {
                return $query->where('flow_name', Constant::EDIT_WORK_HOUR)->where('group_id', $defaultClientEmployeeGroup);
            })->get();

        if ($approveFlowUser->isEmpty()) {
            throw new HumanErrorException(__("cannot_find_approve_flow_user"));
        }

        $sortedApproveFlow = $approveFlowUser->sortBy(function ($item) {
            return $item->toArray()['approve_flow']['step'];
        });
        $approveFlow = $sortedApproveFlow->values()->last()->toArray();
        $step = $approveFlow['approve_flow']['step'];

        [$rq_check_in, $rq_check_out] = TimesheetsHelper::getFlexibleCheckoutFromCheckin($args['request_check_in'], $employee->work_schedule_group_template_id);

        $content = [
            "id" => $args['id'],
            "log_date" => $args['log_date'],
            "current_check_in" => $args['current_check_in'],
            "current_check_out" => $args['current_check_out'],
            "start" => $args['start'],
            "end" => $args['end'],
            "request_check_in" => $rq_check_in,
            "request_check_out" => $rq_check_out,
            "reason" => $args['reason'],
            "client_employee_id" => $employee->id,
            "full_name" => $employee->full_name,
            "department" => $employee->client_department_name,
        ];

        $approveGroup = ApproveGroup::create([
            'client_id' => $user->client_id,
            'type' => $type
        ]);
        $approve = new Approve();
        $approve->fill([
            'type' => $type,
            'content' => json_encode($content),
            'creator_id' => $user->id,
            'original_creator_id' => $user->id,
            'step' => $step,
            'target_id' => $args['id'],
            'target_type' => Timesheet::class,
            'is_final_step' => 0,
            'client_id' => $user->client_id,
            'approve_group_id' => $approveGroup->id,
            'assignee_id' => $args['assignee_id'],
            'client_employee_group_id' => $defaultClientEmployeeGroup,
            'source' => isset($args['source']) ?? $args['source']
        ]);
        $approve->save();
    }

    public function editTimesheetWorkHour($root, array $args)
    {
        $user = Auth::user();
        $content = json_decode($args['content'], true);
        $type = Constant::EDIT_WORK_HOUR;
        // Validate exit approve
        $approveExit = Approve::where([
            'type' => $type,
            'target_id' => $content['id'],
            'is_final_step' => 0
        ])->whereNull('approved_at')->whereNull('declined_at')->orderBy('created_at', 'DESC')->first();
        if ($approveExit) {
            throw new HumanErrorException(__("the_same_approve_application"));
        }

        $timesheet = Timesheet::where('id', $content['id'])->with('clientEmployee')->first();
        $employee = $timesheet->clientEmployee;

        // Validate form request when exceed the approve deadline of the past
        {
            $dateRegister = [
                'date_time_register' => $timesheet->log_date
            ];
            WorktimeRegisterHelper::checkValidateDeadlineApprove([$dateRegister], $employee);
        }

        $approveGroup = ApproveGroup::create([
            'client_id' => $user->client_id,
            'type' => $type
        ]);

        if (isset($args['client_employee_group_id'])) {
            $defaultClientEmployeeGroup = $args['client_employee_group_id'];
        } else {
            $defaultClientEmployeeGroup = Approve::getClientEmployeeGroupId($user, $type);
        }

        $approveFlowUser = ApproveFlowUser::where('user_id', $args['assignee_id'])
            ->with('approveFlow')
            ->whereHas('approveFlow', function ($query) use ($defaultClientEmployeeGroup, $type) {
                return $query->where('flow_name', $type)->where('group_id', $defaultClientEmployeeGroup);
            })->get();

        $step = 1;

        // Advanced Approval Flow
        $clientWorkflowSetting =  ClientWorkflowSetting::where('client_id', $user->client_id)->first(['advanced_approval_flow']);

        if ($approveFlowUser->isNotEmpty() && !$clientWorkflowSetting->advanced_approval_flow) {
            $sortedApproveFlow = $approveFlowUser->sortBy(function ($item, $key) {
                return $item->toArray()['approve_flow']['step'];
            });

            $approveFlow = $sortedApproveFlow->values()->last()->toArray();
            $step = $approveFlow['approve_flow']['step'];
        }

        $content = json_decode($args['content'], true);

        $timesheet = Timesheet::where('id', $content['id'])->with('clientEmployee')->first();
        $employee = $timesheet->clientEmployee;
        $contentNew = array_merge($content, [
            "client_employee_id" => $employee->id,
            "full_name" => $employee->full_name,
            "department" => $employee->client_department_name
        ]);

        $approve = new Approve([
            'type' => $type,
            'content' => json_encode($contentNew),
            'creator_id' => $user->id,
            'original_creator_id' =>  $employee->user_id,
            'step' => $step,
            'target_id' => $content['id'],
            'target_type' => Timesheet::class,
            'is_final_step' => 0,
            'client_id' => $user->client_id,
            'approve_group_id' => $approveGroup->id,
            'assignee_id' => $args['assignee_id'],
            'client_employee_group_id' => $defaultClientEmployeeGroup,
            'source' => isset($args['source']) ?? $args['source']
        ]);

        $approve->save();
    }

    public function editTimesheetShiftMappingWorkHour($root, array $args)
    {
        $type = Constant::EDIT_WORK_HOUR;
        if (!$args['content']) {
            throw new HumanErrorException(__("missing content"));
        }

        $content = json_decode($args['content'], true);
        if (!$content['request_check_in'] || !$content['request_check_out'] || !$content['id']) {
            throw new HumanErrorException(__("missing_request_check_in_or_check_out"));
        }

        // Validate exit approve
        $approveExit = Approve::where([
            'type' => $type,
            'target_id' => $content['id'],
            'is_final_step' => 0
        ])->whereNull('approved_at')->whereNull('declined_at')->orderBy('created_at', 'DESC')->first();
        if ($approveExit) {
            throw new HumanErrorException(__("the_same_approve_application"));
        }

        $timesheetMapping = TimesheetShiftMapping::where('id', $content['id'])->with('timesheet', function ($query) {
            $query->with('clientEmployee');
        })->first();
        $timesheet = $timesheetMapping->timesheet;
        $employee = $timesheet->clientEmployee;

        // Validate form request when exceed the approve deadline of the past
        $dateRegister = [
            'date_time_register' => $timesheet->log_date
        ];
        WorktimeRegisterHelper::checkValidateDeadlineApprove([$dateRegister], $employee);

        $user = Auth::user();

        $approveGroup = ApproveGroup::create([
            'client_id' => $user->client_id,
            'type' => $type
        ]);

        if (isset($args['client_employee_group_id'])) {
            $defaultClientEmployeeGroup = $args['client_employee_group_id'];
        } else {
            $defaultClientEmployeeGroup = Approve::getClientEmployeeGroupId($user, $type);
        }

        $approveFlowUser = ApproveFlowUser::where('user_id', $args['assignee_id'])
            ->with('approveFlow')
            ->whereHas('approveFlow', function ($query) use ($defaultClientEmployeeGroup) {
                return $query->where('flow_name', 'CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR')->where('group_id', $defaultClientEmployeeGroup);
            })->get();
        $step = 1;
        if ($approveFlowUser->isNotEmpty()) {
            $sortedApproveFlow = $approveFlowUser->sortBy(function ($item, $key) {
                return $item->toArray()['approve_flow']['step'];
            });
            $approveFlow = $sortedApproveFlow->values()->last()->toArray();
            $step = $approveFlow['approve_flow']['step'];
        }
        $timesheetMapping = TimesheetShiftMapping::where('id', $content['id'])->with('timesheet', function ($query) {
            $query->with('clientEmployee');
        })->first();
        $employee = $timesheetMapping->timesheet->clientEmployee;
        $contentNew = array_merge($content, [
            "client_employee_id" => $employee->id,
            "full_name" => $employee->full_name,
            "department" => $employee->client_department_name
        ]);


        $approve = new Approve();
        $approve->fill([
            'type' => $type,
            'content' => json_encode($contentNew),
            'creator_id' => $user->id,
            'original_creator_id' => $employee->user_id,
            'step' => $step,
            'target_id' => $content['id'],
            'target_type' => 'App\Models\TimesheetShiftMapping',
            'is_final_step' => 0,
            'client_id' => $user->client_id,
            'approve_group_id' => $approveGroup->id,
            'assignee_id' => $args['assignee_id'],
            'client_employee_group_id' => $defaultClientEmployeeGroup,
            'source' => isset($args['source']) ?? $args['source']
        ]);
        $approve->save();

        return $approve;
    }
}
