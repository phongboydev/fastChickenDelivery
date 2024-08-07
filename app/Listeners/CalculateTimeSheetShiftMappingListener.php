<?php

namespace App\Listeners;

use App\Events\CalculateTimeSheetShiftMappingEvent;
use App\Models\Client;
use App\Models\Timesheet;
use Illuminate\Contracts\Queue\ShouldQueue;

class CalculateTimeSheetShiftMappingListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\CalculateTimeSheetShiftMappingEvent  $event
     * @return void
     */
    public function handle(CalculateTimeSheetShiftMappingEvent $event)
    {
        $client = Client::with('clientWorkflowSetting')->find($event->clientId);
        if (!$client || !$client->clientWorkFlowSetting || !$client->clientWorkFlowSetting->enable_timesheet_rule) {
            // dont enforce any rule
            return;
        }

        $timesheetList = Timesheet::with('timesheetShiftMapping.timesheetShift')
            ->whereIn('id', $event->timesheetIds)->get();

        $timesheetList->each(function($timesheet, $key) use ($client) {
            $timesheet->calculateMultiTimesheet($client->clientWorkFlowSetting);
            $timesheet->saveQuietly();
        });
    }
}
