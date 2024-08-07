<?php

namespace App\Observers;

use App\Jobs\RefreshTimesheetScheduleOfWorkScheduleGroupJob;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleGroup;

class WorkScheduleObserver
{

    public function created(WorkSchedule $workSchedule)
    {
        // Re-trigger related timesheet
        dispatch(new RefreshTimesheetScheduleOfWorkScheduleGroupJob($workSchedule->workScheduleGroup));
    }

    public function updated(WorkSchedule $workSchedule)
    {
        $workScheduleGroup = WorkScheduleGroup::select('*')
                                              ->where('id', $workSchedule->work_schedule_group_id)
                                              ->first();

        $expectedWorkHours = $workScheduleGroup->calculateExpectedWorkHours($workSchedule->work_schedule_group_id);

        WorkScheduleGroup::where('id', $workSchedule->work_schedule_group_id)->update([
            'expected_work_hours' => $expectedWorkHours,
        ]);

        // Re-trigger related timesheet
        dispatch(new RefreshTimesheetScheduleOfWorkScheduleGroupJob($workSchedule->workScheduleGroup));
    }
}
