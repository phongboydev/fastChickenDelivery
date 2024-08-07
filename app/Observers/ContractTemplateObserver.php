<?php

namespace App\Observers;

use App\Models\ContractTemplate;

class ContractTemplateObserver
{
    /**
     * Handle the ContractTemplate "created" event.
     *
     * @param  \App\Models\ContractTemplate  $contractTemplate
     * @return void
     */
    public function created(ContractTemplate $contractTemplate)
    {
        //
    }

    /**
     * Handle the ContractTemplate "updated" event.
     *
     * @param  \App\Models\ContractTemplate  $contractTemplate
     * @return void
     */
    public function updated(ContractTemplate $contractTemplate)
    {
        //
    }

    /**
     * Handle the ContractTemplate "deleted" event.
     *
     * @param  \App\Models\ContractTemplate  $contractTemplate
     * @return void
     */
    public function deleted(ContractTemplate $contractTemplate)
    {
        //
    }

    /**
     * Handle the ContractTemplate "restored" event.
     *
     * @param  \App\Models\ContractTemplate  $contractTemplate
     * @return void
     */
    public function restored(ContractTemplate $contractTemplate)
    {
        //
    }

    /**
     * Handle the ContractTemplate "force deleted" event.
     *
     * @param  \App\Models\ContractTemplate  $contractTemplate
     * @return void
     */
    public function forceDeleted(ContractTemplate $contractTemplate)
    {
        //
    }
}
