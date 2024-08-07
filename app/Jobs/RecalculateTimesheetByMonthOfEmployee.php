<?php

namespace App\Jobs;

use App\Models\ClientEmployee;
use App\Models\Timesheet;
use App\Models\WorkScheduleGroup;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateTimesheetByMonthOfEmployee implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The time sheet instance.
     *
     * @var WorkScheduleGroup
     */
    public $workScheduleGroup;
    public $employee;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(WorkScheduleGroup $workScheduleGroup, ClientEmployee $employee)
    {
        $this->workScheduleGroup = $workScheduleGroup;
        $this->employee = $employee;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $workScheduleGroup = $this->workScheduleGroup;
        $fromDate = Carbon::parse($workScheduleGroup->timesheet_from);
        $toDate = Carbon::parse($workScheduleGroup->timesheet_to);
        if (!$fromDate || !$toDate) {
            return;
        }
        try {
            $query = Timesheet::query();
            $query->whereBetween("log_date", [$fromDate->toDateString(), $toDate->toDateString()]);
            $query->where('client_employee_id', $this->employee->id);
            foreach ($query->cursor() as $item) {
                /** @var Timesheet $item */
                $item->flexible = 0;
                $item->is_update_work_schedule = true;
                $item->recalculate();
                $item->saveQuietly();
            }
        } catch (\Exception $e) {
            logger($e->getMessage() . ' at line ' . $e->getLine() . ' at file: ' . $e->getFile());
        }
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return $this->employee->id . '_' . $this->workScheduleGroup->id;
    }
}
