<?php

namespace App\Observers;

use App\Models\Timesheet;
use App\Models\TimesheetShiftHistory;
use Illuminate\Support\Facades\Auth;

class TimesheetObserver
{
    protected $workSchedule;
    protected $workScheduleGroupTemplate;
    protected $timesheet;

    // public function saving(Timesheet $timesheet)
    // {
    //     if ($timesheet->isDirty('check_in') || $timesheet->isDirty('check_out')) {
    //         $timesheet->recalculate();
    //     }
    // }

    public function creating(Timesheet $timesheet)
    {
        $timesheet->recalculate();
    }

    public function updating(Timesheet $timesheet)
    {
        $timesheet->recalculate();
    }
}

