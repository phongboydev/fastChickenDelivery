<?php

namespace App\Console\Commands;

use App\Models\WorktimeRegister;
use App\Observers\WorktimeRegisterObserver;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TidyTimesheetByWTR extends Command
{

    protected $signature = 'tidy:timesheet_wtr {fromDate} {toDate?}';

    protected $description = 'Tự động điền lại các ngày đi làm timesheet dựa vào WTR';

    public function handle()
    {
        $from = $this->argument("fromDate");
        $to = $this->argument("toDate");

        $fromDate = Carbon::parse($from);
        $toDate = $fromDate->clone();
        if ($to) {
            $toDate = Carbon::parse($to);
        }

        $now = $fromDate->clone();
        $total = 0;
        do {
            WorktimeRegister::query()
                            ->whereDate("start_time", $now->toDateString())
                            ->where("status", "approved")
                            ->chunk(100, function ($items) use (&$total) {
                                foreach ($items as $item) {
                                    $this->info("Process ... ".$item->id);
                                    $total++;
                                    $observer = new WorktimeRegisterObserver();
                                    $observer->updated($item);
                                }
                            });
            $now->addDay();
        } while ($now->lte($toDate));
        $this->info("Total processed: ".$total);
    }
}
