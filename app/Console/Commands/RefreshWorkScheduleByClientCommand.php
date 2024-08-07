<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\WorkScheduleGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RefreshWorkScheduleByClientCommand extends Command
{

    protected $signature = 'timesheet:refresh {year} {month} {clientCode?}';

    protected $description = 'Refresh timesheet using Timesheet schedule';

    public function handle()
    {
        $clientCode = $this->argument('clientCode');
        $year = $this->argument('year');
        $month = $this->argument('month');

        $query = Client::query();
        if ($clientCode) {
            $query->where('code', $clientCode);
        }
        $query->chunk(100, function ($clients) use ($month, $year) {
            foreach ($clients as $client) {
                $rangeStart = Carbon::create($year, $month, 1);
                $rangeEnd = $rangeStart->clone()->lastOfMonth()->addDay();

                /** @var Client $client */
                $wsgs = WorkScheduleGroup::query()->where('client_id', $client->id)
                                         ->where(function ($subQuery) use ($rangeEnd, $rangeStart) {
                                             $subQuery->whereBetween('timesheet_from', [
                                                 $rangeStart,
                                                 $rangeEnd,
                                             ])
                                                      ->orWhereBetween('timesheet_to', [
                                                          $rangeStart,
                                                          $rangeEnd,
                                                      ])
                                                      ->orWhere(function ($query) use ($rangeStart) {
                                                          $query->where('timesheet_from', '<=', $rangeStart)
                                                                ->where('timesheet_to', '>=', $rangeStart);
                                                      });
                                         })
                                         ->get();

                ClientEmployee::where('client_id', $client->id)
                              ->chunk(100, function ($ces) use ($client, $wsgs) {
                                  foreach ($ces as $ce) {
                                      /** @var ClientEmployee $ce */
                                      $groups = $wsgs->where('work_schedule_group_template_id',
                                          $ce->work_schedule_group_template_id);
                                      foreach ($groups as $group) {
                                          $this->line($client->code.'|'.$ce->code.'|'.$group->id);
                                          $ce->refreshTimesheetByWorkScheduleGroup($group);
                                      }
                                  }
                              });
            }
        });
    }
}
