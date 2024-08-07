<?php

namespace App\Observers;

use App\Models\TimesheetShiftMapping;

class TimesheetShiftMappingObserver
{
    /**
     * Handle the TimesheetShiftMapping "created" event.
     *
     * @param  \App\Models\TimesheetShiftMapping  $timesheetShiftMapping
     * @return void
     */
    public function created(TimesheetShiftMapping $timesheetShiftMapping)
    {
        //
    }

    /**
     * Handle the TimesheetShiftMapping "updated" event.
     *
     * @param  \App\Models\TimesheetShiftMapping  $timesheetShiftMapping
     * @return void
     */
    public function updated(TimesheetShiftMapping $timesheetShiftMapping)
    {
        //
    }

    /**
     * Handle the TimesheetShiftMapping "deleted" event.
     *
     * @param  \App\Models\TimesheetShiftMapping  $timesheetShiftMapping
     * @return void
     */
    public function deleted(TimesheetShiftMapping $timesheetShiftMapping)
    {
        //
    }

    /**
     * Handle the TimesheetShiftMapping "restored" event.
     *
     * @param  \App\Models\TimesheetShiftMapping  $timesheetShiftMapping
     * @return void
     */
    public function restored(TimesheetShiftMapping $timesheetShiftMapping)
    {
        //
    }

    /**
     * Handle the TimesheetShiftMapping "force deleted" event.
     *
     * @param  \App\Models\TimesheetShiftMapping  $timesheetShiftMapping
     * @return void
     */
    public function forceDeleted(TimesheetShiftMapping $timesheetShiftMapping)
    {
        //
    }
}
