<?php

namespace App\GraphQL\Queries;

use App\Models\Timesheet;
use App\Models\TimesheetShiftMapping;
use App\Models\WorkScheduleGroup;

class GetTimesheetByWorkScheduleGroup
{

    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $workScheduleGroupdId = $args['work_schedule_group_id'];
        $clientEmployeeId = $args['client_employee_id'];
        return $this->handle($workScheduleGroupdId, $clientEmployeeId);
    }

    /**
     * @param $workScheduleGroupdId
     * @param $clientEmployeeId
     *
     * @return \App\Models\Timesheet[]|array|\Illuminate\Support\Collection
     */
    public function handle($workScheduleGroupdId, $clientEmployeeId)
    {
        $wsg = WorkScheduleGroup::query()
                                ->where('id', $workScheduleGroupdId)
                                ->first();

        if (!$wsg) {
            logger(__METHOD__.' WorkScheduleGroup not found.');
            return [];
        }
        logger(__METHOD__.' WorkScheduleGroup id='.$wsg->id);

        // TODO check authUserAccessible()
        $tss = Timesheet::with('timesheetShiftMapping.timesheetShift')
                        ->where('client_employee_id', $clientEmployeeId)
                        ->whereDate('log_date', '>=', $wsg->timesheet_from)
                        ->whereDate('log_date', '<=', $wsg->timesheet_to)
                        ->get();

        return $tss;
    }
}
