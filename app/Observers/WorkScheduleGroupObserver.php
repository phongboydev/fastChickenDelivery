<?php

namespace App\Observers;

// use App\Jobs\RefreshTimesheetScheduleOfWorkScheduleGroupJob;
use App\Exceptions\HumanErrorException;
use App\Models\Approve;
use App\Models\WorkScheduleGroup;
use App\Models\WorktimeRegister;
use Carbon\Carbon;

class WorkScheduleGroupObserver
{
    public function deleted(WorkScheduleGroup $workScheduleGroup)
    {
        $clientId = $workScheduleGroup->client_id;
        $workScheduleGroupTemplateId = $workScheduleGroup->work_schedule_group_template_id;

        logger('WorkScheduleGroupObserver@deleted workScheduleGroup client_id: ' . $clientId, [
            $workScheduleGroupTemplateId, 
            $workScheduleGroup->timesheet_from, 
            $workScheduleGroup->timesheet_to
        ]);
            
        $query = WorktimeRegister::where('type', 'timesheet')
                                ->whereDate('start_time', '>=', Carbon::parse($workScheduleGroup->timesheet_from)->format('Y-m-d'))
                                ->whereDate('end_time', '<=', Carbon::parse($workScheduleGroup->timesheet_to)->format('Y-m-d'))
                                 ->whereHas('client', function ($query) use ($clientId) {
                                     $query->id = $clientId;
                                 })
                                 ->whereHas('clientEmployee', function ($query) use ($workScheduleGroupTemplateId) {
                                    $query->work_schedule_group_template_id = $workScheduleGroupTemplateId;
                                });

        $worktimeRegisters = $query->get();

        logger('WorkScheduleGroupObserver@deleted worktimeRegisters client_id: ' . $clientId, [$worktimeRegisters]);

        if($worktimeRegisters->isEmpty()) return;

        $worktimeRegisterIds = [];

        foreach($worktimeRegisters as $worktimeRegister) {
            $approves = Approve::where('type', 'CLIENT_REQUEST_TIMESHEET')
                               ->where('client_id', $clientId)
                               ->where('target_type', 'App\\Models\\WorktimeRegister')
                               ->where('target_id', $worktimeRegister->id)
                               ->whereNull('approved_at')
                               ->whereNull('declined_at')->get();

            if($approves->isNotEmpty())
            {
                $approveIds = $approves->pluck('id');
                $worktimeRegisterIds = array_merge($worktimeRegisterIds, $approves->pluck('target_id')->all());

                Approve::whereIn('id', $approveIds->all())->delete();
            }
        }

        logger('WorkScheduleGroupObserver@deleted wtr client_id: ' . $clientId, $worktimeRegisterIds);

        if($worktimeRegisterIds)
            WorktimeRegister::whereIn('id', $worktimeRegisterIds)->delete();
    }

    /**
     * @throws HumanErrorException
     */
    public function updating(WorkScheduleGroup $workScheduleGroup)
    {
        $timesheetTo = Carbon::parse($workScheduleGroup->timesheet_to);
        $timesheetDeadlineAt = Carbon::parse($workScheduleGroup->timesheet_deadline_at);
        // Check if timesheet deadline is before timesheet to
        if($timesheetDeadlineAt->isBefore($timesheetTo))
        {
            throw new HumanErrorException(__('warning.deadline_timesheet_is_greater_end_time'));
        }

        $approveDeadlineAt = Carbon::parse($workScheduleGroup->approve_deadline_at);
        // Check if approve deadline is before timesheet deadline
        if($approveDeadlineAt->isBefore($timesheetDeadlineAt))
        {
            throw new HumanErrorException(__('warning.deadline_approve_is_greater_deadline_timesheet'));
        }
    }
}
