<?php

namespace App\Observers;

use App\Jobs\SendJobboardApplicationConfirmEmail;
use App\Models\JobboardApplication;
use App\Models\JobboardAssignment;
use App\Notifications\JobboardApplicationNotification;
use App\Jobs\SendJobboardApplicationEmail;

class JobboardApplicationObserver
{
    public function created(JobboardApplication $jobboardApplication)
    {
        SendJobboardApplicationEmail::dispatch($jobboardApplication);
        SendJobboardApplicationConfirmEmail::dispatch($jobboardApplication);
    }
}
