<?php

namespace App\GraphQL\Queries;

use App\Exceptions\HumanErrorException;
use App\Models\ClientEmployee;
use App\Models\WorkSchedule;

class WorkScheduleByDateQuery
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $clientEmployeeId = $args['client_employee_id'];
        $scheduleDate = $args['schedule_date'];
        $workScheduleGroupTemplateId = isset($args['work_schedule_group_template_id']) && $args['work_schedule_group_template_id'] ? $args['work_schedule_group_template_id'] : false;

        if(!$workScheduleGroupTemplateId) {
            /** @var ClientEmployee $ce */
            $ce = ClientEmployee::query()
                        ->authUserAccessible()
                        ->findOrFail($clientEmployeeId);

            $workScheduleGroupTemplateId = $ce->work_schedule_group_template_id;
        }

        $ws = WorkSchedule::query()
            ->whereHas('workScheduleGroup', function ($q) use ($workScheduleGroupTemplateId) {
                $q->where('work_schedule_group_template_id', $workScheduleGroupTemplateId);
            })
            ->where('schedule_date', $scheduleDate)
            ->first();

        return $ws;
    }
}
