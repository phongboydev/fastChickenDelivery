<?php

namespace App\Console\Commands;

use App\Models\Timesheet;
use App\Models\WorktimeRegister;
use App\Observers\WorktimeRegisterObserver;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TriggerWorkTimeRegisterCommand extends Command
{

    protected $signature = 'workTimeRegister:trigger {fromDate} {toDate?}';

    protected $description = 'Trigger work time register of date';

    public function handle()
    {
        $from = $this->argument("fromDate");
        $to = $this->argument("toDate");

        $wsStart = $from . ' 00:00:00';
        $wsEnd = $to . ' 23:59:59';

        WorktimeRegister::query()
                        ->whereStatus('approved')
                        ->where(function ($subQuery) use ($wsStart, $wsEnd) {
                            $subQuery->whereBetween('start_time', [
                                $wsStart,
                                $wsEnd,
                            ])
                                     ->orWhereBetween('end_time', [
                                         $wsStart,
                                         $wsEnd,
                                     ])
                                     ->orWhere(function ($query) use ($wsStart) {
                                         $query->where('start_time', '<=', $wsStart)
                                               ->where('end_time', '>=', $wsStart);
                                     });
                        })
                        ->chunk(100, function ($items) {
                            foreach ($items as $item) {
                                /** @var WorktimeRegister $item */
                                $ob = new WorktimeRegisterObserver();
                                $this->line("Process ... " . $item->id);
                                $ob->updated($item);
                            }
                        });
    }
}
