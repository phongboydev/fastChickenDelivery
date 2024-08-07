<?php

namespace App\Listeners;

use App\Events\CalculationSheetReadyEvent;
use App\Jobs\ProcessCalculationSheetClientEmployeeJob;
use App\Jobs\ProcessUpdateCalculationSheetClientEmployeeJob;
use App\Models\CalculationSheetClientEmployee;

class CalculationSheetReadyListener
{

    public function __construct()
    {
        //
    }

    public function handle(CalculationSheetReadyEvent $event)
    {
        logger(self::class . "@handle");
        $cs = $event->calculationSheet;
        CalculationSheetClientEmployee::query()
                                      ->where("calculation_sheet_id", $cs->id)
                                      ->chunk(100, function ($csces) use ($cs) {
                                            if(isset($cs->handleUpdate)){
                                                $job = new ProcessUpdateCalculationSheetClientEmployeeJob($csces, $cs->client_id);
                                            } else {
                                                $job = new ProcessCalculationSheetClientEmployeeJob($csces, $cs->client_id);
                                            }
                                            if (app()->runningInConsole()) {
                                                dispatch_sync($job);
                                            } else {
                                                dispatch($job);
                                            }
                                      });
    }
}
