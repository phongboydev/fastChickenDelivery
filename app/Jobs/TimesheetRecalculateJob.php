<?php

namespace App\Jobs;

use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TimesheetRecalculateJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $timesheetList;

    public function __construct(array $timesheetIds)
    {
        $this->timesheetList = Timesheet::whereIn('id', $timesheetIds)->get();
    }

    public function handle()
    {
        if ($this->timesheetList) {
            foreach ($this->timesheetList as $item) {
                $item->recalculate();
                $item->saveQuietLy();
            }
        }
    }
}
