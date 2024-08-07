<?php

namespace App\Jobs;

use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\ApproveGroup;
use App\Models\ClientEmployee;
use App\Models\ClientWorkflowSetting;
use App\Models\Timesheet;
use App\Models\WorkSchedule;
use App\Models\WorktimeRegister;
use App\Models\WorkTimeRegisterPeriod;
use App\Models\Client;
use App\Support\Constant;
use App\Support\PeriodHelper;
use App\Support\WorktimeRegisterHelper;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class AutoGenerateOTRequest implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The product instance.
     *
     * @var \App\Models\Timesheet
     */
    public $timesheetID;
    public $type;

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->timesheetID;
    }

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($timesheetID, $type)
    {
        $this->timesheetID = $timesheetID;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $timesheet = Timesheet::find($this->timesheetID);
        $clientEmployee = ClientEmployee::find($timesheet->client_employee_id);
        if (empty($clientEmployee->user->id)) return;

        $client = Client::find($clientEmployee->client_id);
        $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $client->id)->first();
        // Check setting internal
        if ($this->type == Constant::OVERTIME_TYPE && !$clientWorkflowSetting
            && !$clientWorkflowSetting->enable_auto_generate_ot
            && !$clientWorkflowSetting->approval_system_assigment_id
            || $this->type == Constant::MAKEUP_TYPE
            && !$client->clientWorkFlowSetting
            && !$client->clientWorkFlowSetting->enable_makeup_request_form
            && !$client->clientWorkFlowSetting->auto_create_makeup_request_form
        ) {
            return;
        }

        $workSchedule = WorkSchedule::query()
            ->where('client_id', $clientEmployee->client_id)
            ->whereHas(
                'workScheduleGroup',
                function ($group) use ($clientEmployee) {
                    $group->where(
                        'work_schedule_group_template_id',
                        $clientEmployee->work_schedule_group_template_id
                    );
                }
            )
            ->where('schedule_date', $timesheet->log_date)
            ->with('workScheduleGroup')
            ->first();

        if (!$workSchedule) return;
        $workSchedule = $timesheet->getShiftWorkSchedule($workSchedule);
        if(empty($workSchedule->check_out) || empty($timesheet->check_out)) return;

        $startTimeRequest = $workSchedule->check_out . ':00';
        $startDateTimeRequest = Carbon::parse($workSchedule->schedule_date_string . ' ' . $startTimeRequest);
        if ($workSchedule->next_day) {
            $startDateTimeRequest = $startDateTimeRequest->addDay();
        }

        $endTimeRequest = $timesheet->check_out . ':00';
        $endDateTimeRequest = Carbon::parse($timesheet->log_date . ' ' . $endTimeRequest);
        if ($timesheet->next_day) {
            $endDateTimeRequest = $endDateTimeRequest->addDay();
        }
        if ($endDateTimeRequest->isBefore($startDateTimeRequest)) return;

        $latestwtr = WorktimeRegister::where('client_employee_id', $clientEmployee->id)
            ->where('type', $this->type)
            ->latest()
            ->first();

        // Get periods exit
        $periods = WorkTimeRegisterPeriod::where('date_time_register', $timesheet->log_date)
        ->whereHas('worktimeRegister', function ($query) use ($timesheet) {
            $query->whereNotIn('status', ['canceled', 'canceled_approved'])
                ->whereIn('type', [Constant::OVERTIME_TYPE, Constant::MAKEUP_TYPE])
                ->where('client_employee_id', $timesheet->client_employee_id);
        })->get();
        $listPeriodAlreadyExits = [];
        $current = Period::make($startDateTimeRequest, $endDateTimeRequest, Precision::MINUTE);
        foreach ($periods as $period) {
            $endTimeAlreadyExits = Carbon::parse($timesheet->log_date . ' ' . $period->end_time);
            if ($period->next_day) {
                $endTimeAlreadyExits = $endTimeAlreadyExits->addDay();
            }
            $startTimeAlreadyExits = Carbon::parse($timesheet->log_date . ' ' . $period->start_time);
            $cloneStartTimeAlreadyExits = clone $startTimeAlreadyExits;
            if ($period->next_day && $cloneStartTimeAlreadyExits->addDay()->isBefore($endTimeAlreadyExits)) {
                $startTimeAlreadyExits = $startTimeAlreadyExits->addDay();
            }
            $listPeriodAlreadyExits[] = Period::make($startTimeAlreadyExits, $endTimeAlreadyExits, Precision::MINUTE, Boundaries::EXCLUDE_ALL);
        }
        // Make current when sub period exits
        if (!empty($listPeriodAlreadyExits)) {
            $current = PeriodHelper::subtract($current, ...$listPeriodAlreadyExits);
        } else {
            $current = (new PeriodCollection($current))->overlap(...Period::make($timesheet->log_date . ' ' . '00:00:00', $timesheet->log_date . ' ' . '00:00:00', Precision::MINUTE, Boundaries::EXCLUDE_ALL));
        }

        $countCheckTheSameTimeOfStartAndEnd = 0;
        foreach ($current as $period) {
            if ($period->getStart() != $period->getEnd()) {
                $countCheckTheSameTimeOfStartAndEnd++;
            }
        }
        // Check the same start == end
        if($countCheckTheSameTimeOfStartAndEnd == 0) {
            return;
        }

        DB::beginTransaction();
        try {
            $workTimeRegister = new WorktimeRegister();
            $workTimeRegister->client_employee_id = $clientEmployee->id;
            $workTimeRegister->end_time = $endDateTimeRequest->format('Y-m-d H:i:s');
            $workTimeRegister->start_time = $startDateTimeRequest->format('Y-m-d H:i:s');
            $workTimeRegister->reason = '';
            $workTimeRegister->skip_logic = 0;
            $workTimeRegister->status = "pending";
            $workTimeRegister->auto_created = 1;
            $workTimeRegister->code = $latestwtr ? WorktimeRegisterHelper::generateNextID($latestwtr->code) : $client->code . '-00000';
            if ($this->type == Constant::OVERTIME_TYPE) {
                $workTimeRegister->sub_type = 'ot_weekday';
                $workTimeRegister->type = Constant::OVERTIME_TYPE;
            } elseif ($this->type == Constant::MAKEUP_TYPE) {
                $workTimeRegister->sub_type = 'ot_makeup';
                $workTimeRegister->type = Constant::MAKEUP_TYPE;
            }

            if ($this->type === Constant::MAKEUP_TYPE) {
                $workTimeRegister->status = "approved";
                $workTimeRegister->approved_date = Carbon::now();
            }
            $workTimeRegister->save();

            $arrayMakeupRequest = [];
            foreach ($current as $period) {
                if ($period->getStart() == $period->getEnd()) {
                    continue;
                }
                $workTimeRegisterPeriod = new WorkTimeRegisterPeriod();
                $workTimeRegisterPeriod->date_time_register = $startDateTimeRequest->format('Y-m-d') == $period->getEnd()->format('Y-m-d') ? $startDateTimeRequest->format('Y-m-d') : $startDateTimeRequest->addDay()->format('Y-m-d');
                $workTimeRegisterPeriod->start_time = $period->getStart()->format('H:i:s');
                $workTimeRegisterPeriod->end_time = $period->getEnd()->format('H:i:s');
                $workTimeRegisterPeriod->next_day = $startDateTimeRequest->format('Y-m-d') == $period->getEnd()->format('Y-m-d') ? 0 : 1;
                $workTimeRegisterPeriod->type_register = 1;
                $workTimeRegister->workTimeRegisterPeriod()->save($workTimeRegisterPeriod);
                $arrayMakeupRequest[] = $workTimeRegisterPeriod->attributesToArray();
            }

            if ($this->type === Constant::MAKEUP_TYPE) {
                $workTimeRegister->createOrUpdateOTWorkTimeRegisterTimesheet();
            }

            $type = 'CLIENT_REQUEST_OT';
            $group_id = 0;
            $finalFlowStep = ApproveFlow::query()
                ->where('client_id', $client->id)
                ->where('flow_name', $type)
                ->where('group_id', $group_id)
                ->orderBy('step', 'desc')
                ->first();

            $approveGroup = ApproveGroup::create(['client_id' => $client->id, 'type' => $type]);

            $content = $workTimeRegister->attributesToArray();
            $content['workTimeRegisterPeriod'] = $arrayMakeupRequest;

            $approve = new Approve();
            $approve->client_id = $client->id;

            $approve->approve_group_id = $approveGroup->id;
            $approve->client_employee_group_id = $group_id;
            $approve->creator_id = $clientEmployee->user->id;
            $approve->original_creator_id = $clientEmployee->user->id;
            $approve->content = json_encode($content);
            $approve->step = $finalFlowStep->step ?? 1;
            $approve->target_id = $workTimeRegister->id;
            $approve->target_type = "App\Models\WorktimeRegister";
            $approve->type = $type;
            $approve->save();
            if ($this->type === Constant::OVERTIME_TYPE) {
                $approve->assignee_id = $clientWorkflowSetting->approval_system_assigment_id->id;
                $approve->save();
            } elseif ($this->type === Constant::MAKEUP_TYPE) {
                $approve->processing_state = 'complete';
                $approve->approved_at = Carbon::now();
                $approve->is_final_step = 1;
                $approve->saveQuietly();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
        }
    }
}
