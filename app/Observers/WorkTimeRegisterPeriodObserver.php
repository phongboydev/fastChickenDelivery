<?php

namespace App\Observers;

use App\Models\Timesheet;
use App\Models\WorktimeRegister;
use App\Models\WorkTimeRegisterPeriod;
use App\Support\Constant;
use Carbon\Carbon;

class WorkTimeRegisterPeriodObserver
{

    public function creating(WorkTimeRegisterPeriod $workTimeRegisterPeriod)
    {
        if ($workTimeRegisterPeriod->type_register == WorkTimeRegisterPeriod::TYPE_BY_HOUR) {
            // remove second parts of time
            $workTimeRegisterPeriod->start_time = Carbon::parse($workTimeRegisterPeriod->start_time)
                ->startOfMinute()
                ->format('H:i:s');
            $workTimeRegisterPeriod->end_time = Carbon::parse($workTimeRegisterPeriod->end_time)
                ->startOfMinute()
                ->format('H:i:s');

            // Override change_flexible_checkin to save correct the data to support check bug if have any errors.
            $workTimeRegister = WorktimeRegister::find($workTimeRegisterPeriod->worktime_register_id);
            if ($workTimeRegister) {
                $employee = $workTimeRegister->clientEmployee;
                if ($employee) {
                    $workSchedule = $employee->getWorkSchedule($workTimeRegisterPeriod->date_time_register);
                    if ($employee->timesheet_exception != Constant::TYPE_FLEXIBLE_TIMESHEET || ($workSchedule && $workSchedule->shift_enabled) || $workSchedule->is_off_day || $workSchedule->is_holiday) {
                        $workTimeRegisterPeriod->change_flexible_checkin = null;
                    }
                }
            }
        }
    }

    public function deleted(WorkTimeRegisterPeriod $workTimeRegisterPeriod)
    {
        $wtr = WorktimeRegister::find($workTimeRegisterPeriod->worktime_register_id);
        // Recalculate timesheet
        $ts = Timesheet::where([
            'client_employee_id' => $wtr->client_employee_id,
            'log_date' => $workTimeRegisterPeriod->date_time_register
        ])->first();
        if ($ts) {
            $ts->recalculate();
            $ts->saveQuietly();
        }
    }
}
