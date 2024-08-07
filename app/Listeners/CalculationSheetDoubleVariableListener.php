<?php

namespace App\Listeners;

use App\Events\CalculationSheetCalculatedEvent;
use App\Jobs\EmailAdminCalculationSheetDoubleVariable;

class CalculationSheetDoubleVariableListener
{

    public function __construct()
    {
        //
    }

    public function handle(CalculationSheetCalculatedEvent $event)
    {
        logger(self::class . "@handle");

        dispatch(new EmailAdminCalculationSheetDoubleVariable($event->calculationSheet));
    }
}
