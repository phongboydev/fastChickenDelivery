<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\Timesheet;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TriggerTimesheetCommand extends Command
{

    protected $signature = 'timesheet:trigger {fromDate} {toDate?} {clientCode?} {forceUpdate?}';

    protected $description = 'Recalculate timesheet of date';

    public function handle()
    {
        $from = $this->argument("fromDate");
        $to   = $this->argument("toDate");
        $clientCode   = $this->argument("clientCode");
        $forceUpdate  = $this->argument("forceUpdate");

        $fromDate = Carbon::parse($from);
        $toDate = $fromDate->clone();
        if ($to) {
            $toDate = Carbon::parse($to);
        }

        $now = $fromDate->clone();
        do {
            $query = Timesheet::query()
                     ->where("log_date", $now->toDateString());
            if ($clientCode) {
                $client = Client::where('code', $clientCode)->first();
                if (!$client) {
                    $this->error("Client is not existed.");
                    return;
                }
                $query->whereIn('client_employee_id', function($subQuery) use ($client) {
                    $subQuery->select('id')
                        ->from((new ClientEmployee())->getTable())
                        ->where('client_id', $client->id);
                });
            }
            $query->chunk(100, function ($items) use($forceUpdate) {
                foreach ($items as $item) {
                    /** @var Timesheet $item */
                    // force recalculate
                    $this->line("Process ... " . $item->id);
                    $item->flexible = $forceUpdate ? 1 : 0;
                    $item->recalculate();
                    $item->save();
                }
            });
            $now->addDay();
        } while ($now->lte($toDate));
    }
}
