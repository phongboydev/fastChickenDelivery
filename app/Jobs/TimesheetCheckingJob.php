<?php

namespace App\Jobs;

use App\Models\Checking;
use App\Models\ClientWorkflowSetting;
use App\Support\Constant;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use App\Models\Timesheet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TimesheetCheckingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $timesheetIds;
    protected $clientId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $timesheetIds, string $clientId)
    {
        $this->timesheetIds = $timesheetIds;
        $this->clientId = $clientId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $this->clientId)->first();
        $dayBeginMarks = $clientWorkflowSetting->getTimesheetDayBeginAttribute();
        $query = Timesheet::with('timesheetShiftMapping')->whereIn('id', $this->timesheetIds);

        foreach ($query->cursor() as $timesheet) {
            $dayStart = Carbon::parse($timesheet->log_date . ' ' . $dayBeginMarks)->format('Y-m-d H:i:s');
            $dayEnd = Carbon::parse($timesheet->log_date . ' ' . $dayBeginMarks)->addDay()->subMinute()->format('Y-m-d H:i:s');

            $checkingList = Checking::whereBetween('checking_time', [$dayStart, $dayEnd])
                ->where('client_employee_id', $timesheet->client_employee_id)
                ->get();

            if ($checkingList->isNotEmpty()) {
                $timesheet->resetInOutMultiShift();

                foreach ($checkingList as $checking) {
                    $timesheet->checkTimeWithMultiShift(Carbon::parse($checking->checking_time, Constant::TIMESHEET_TIMEZONE), 'Sys');
                }
            }

            $timesheet->recalculate();
            $timesheet->saveQuietly();
        }
    }
}
