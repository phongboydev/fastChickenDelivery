<?php

namespace App\Observers;

use App\Models\DependentRequestApplicationLink;

class DependentRequestApplicationLinkObserver
{
    /**
     * Handle the DependentRequestApplicationLink "created" event.
     *
     * @param  \App\Models\DependentRequestApplicationLink  $dependentRequestApplicationLink
     * @return void
     */
    public function created(DependentRequestApplicationLink $dependentRequestApplicationLink)
    {
        $dependentRequestApplicationLink->clientEmployeeDependentApplication->update([
            'processing' => 'submitted'
        ]);
    }

    /**
     * Handle the DependentRequestApplicationLink "updated" event.
     *
     * @param  \App\Models\DependentRequestApplicationLink  $dependentRequestApplicationLink
     * @return void
     */
    public function updated(DependentRequestApplicationLink $dependentRequestApplicationLink)
    {
        //
    }

    /**
     * Handle the DependentRequestApplicationLink "deleted" event.
     *
     * @param  \App\Models\DependentRequestApplicationLink  $dependentRequestApplicationLink
     * @return void
     */
    public function deleted(DependentRequestApplicationLink $dependentRequestApplicationLink)
    {
        //
    }

    /**
     * Handle the DependentRequestApplicationLink "restored" event.
     *
     * @param  \App\Models\DependentRequestApplicationLink  $dependentRequestApplicationLink
     * @return void
     */
    public function restored(DependentRequestApplicationLink $dependentRequestApplicationLink)
    {
        //
    }

    /**
     * Handle the DependentRequestApplicationLink "force deleted" event.
     *
     * @param  \App\Models\DependentRequestApplicationLink  $dependentRequestApplicationLink
     * @return void
     */
    public function forceDeleted(DependentRequestApplicationLink $dependentRequestApplicationLink)
    {
        //
    }
}
