<?php

namespace App\GraphQL\Queries;

use App\Exceptions\CustomException;
use App\Exceptions\HumanErrorException;
use App\Models\ClientEmployee;
use App\Support\Constant;
use App\Support\WorkScheduleHelper;

class WorkScheduleWithShiftByDateQuery
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        if(empty($args['schedule_date'])) {
            return false;
        }

        if (!empty($args['client_employee_id'])) {
            $clientEmployee = ClientEmployee::find($args['client_employee_id']);
        } else {
            $user = auth()->user();
            $clientEmployee = $user->clientEmployee;
        }
        $clientSetting = $clientEmployee->client->clientWorkFlowSetting;
        if (empty($clientEmployee->work_schedule_group_template_id)) {
            throw new CustomException(
                "You don't belong to any work schedule!",
                'AuthorizedException'
            );
        }

        $workSchedule = $clientEmployee->getWorkSchedule($args['schedule_date']);
        if (!$workSchedule) {
            throw new HumanErrorException(__("warning.E9.validate"));
        }

        if ($clientEmployee->timesheet_exception == Constant::TYPE_FLEXIBLE_TIMESHEET) {
            $workGroupTemplate = $clientEmployee->workScheduleGroupTemplate;
            if ($workGroupTemplate && !$workSchedule->shift_enabled) {
                $workSchedule->check_in = $workGroupTemplate->check_in;
                $workSchedule->check_out = $workGroupTemplate->check_out;
            }
        }

        // Override
        if (!$clientSetting->enable_multiple_shift && ($workSchedule->is_off_day || $workSchedule->is_holiday)) {
            $workSchedule->check_in = "";
            $workSchedule->check_out = "";
        }

        return $workSchedule;
    }
}
