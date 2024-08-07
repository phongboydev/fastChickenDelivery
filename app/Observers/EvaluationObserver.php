<?php

namespace App\Observers;

use App\Models\ClientEmployee;
use App\Models\Evaluation;
use App\Notifications\EvaluationNotification;
use App\User;

class EvaluationObserver
{
    /**
     * Handle the Evaluation "created" event.
     *
     * @param  \App\Models\Evaluation  $evaluation
     * @return void
     */
    public function created(Evaluation $evaluation)
    {
    }

    /**
     * Handle the Evaluation "updated" event.
     *
     * @param  \App\Models\Evaluation  $evaluation
     * @return void
     */
    public function updated(Evaluation $evaluation)
    {
        $clientEmployee = ClientEmployee::find($evaluation->client_employee_id);

        $user = User::find($clientEmployee->user_id);

        if (!empty($user)) {
            $user->notify(new EvaluationNotification($evaluation));
        }
    }

    /**
     * Handle the Evaluation "deleted" event.
     *
     * @param  \App\Models\Evaluation  $evaluation
     * @return void
     */
    public function deleted(Evaluation $evaluation)
    {
        //
    }

    /**
     * Handle the Evaluation "restored" event.
     *
     * @param  \App\Models\Evaluation  $evaluation
     * @return void
     */
    public function restored(Evaluation $evaluation)
    {
        //
    }

    /**
     * Handle the Evaluation "force deleted" event.
     *
     * @param  \App\Models\Evaluation  $evaluation
     * @return void
     */
    public function forceDeleted(Evaluation $evaluation)
    {
        //
    }
}
