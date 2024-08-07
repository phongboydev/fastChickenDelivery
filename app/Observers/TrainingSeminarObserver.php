<?php

namespace App\Observers;

use App\Models\TrainingSeminar;

class TrainingSeminarObserver
{
    /**
     * Handle the TrainingSeminar "created" event.
     *
     * @param  \App\Models\TrainingSeminar  $trainingSeminar
     * @return void
     */
    public function created(TrainingSeminar $trainingSeminar)
    {
        
    }

    /**
     * Handle the TrainingSeminar "updated" event.
     *
     * @param  \App\Models\TrainingSeminar  $trainingSeminar
     * @return void
     */
    public function updated(TrainingSeminar $trainingSeminar)
    {
        //
    }

    /**
     * Handle the TrainingSeminar "deleted" event.
     *
     * @param  \App\Models\TrainingSeminar  $trainingSeminar
     * @return void
     */
    public function deleted(TrainingSeminar $trainingSeminar)
    {
        //
    }

    /**
     * Handle the TrainingSeminar "restored" event.
     *
     * @param  \App\Models\TrainingSeminar  $trainingSeminar
     * @return void
     */
    public function restored(TrainingSeminar $trainingSeminar)
    {
        //
    }

    /**
     * Handle the TrainingSeminar "force deleted" event.
     *
     * @param  \App\Models\TrainingSeminar  $trainingSeminar
     * @return void
     */
    public function forceDeleted(TrainingSeminar $trainingSeminar)
    {
        //
    }
}
