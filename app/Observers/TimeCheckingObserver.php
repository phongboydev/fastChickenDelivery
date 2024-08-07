<?php

namespace App\Observers;

use App\Support\ClientHelper;
use App\Models\TimeChecking;

class TimeCheckingObserver
{
    public function creating(TimeChecking $timeChecking)
    {
        $timeChecking->info_app = ClientHelper::getInfoApp();
    }
}