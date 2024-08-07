<?php

namespace App\Observers;

use App\Models\Contract;

class ContractObserver
{
    /**
     * Handle the Contract "created" event.
     *
     * @param  \App\Models\Contract  $contract
     * @return void
     */
    public function created(Contract $contract)
    {
        //
    }

    /**
     * Handle the Contract "updating" event.
     *
     * @param  \App\Models\Contract  $contract
     * @return void
     */
    public function updating(Contract $contract)
    {
        if ($contract->isDirty('staff_confirm')) {
            if ($contract->staff_confirm) {
                $contract->status = Contract::STATUS_WAIT_FOR_SETUP;
            } else {
                $contract->status = Contract::STATUS_REJECT;
            }
            $contract->staff_confirmed_at = now();
            $contract->saveQuietly();
        }
    }

    /**
     * Handle the Contract "updated" event.
     *
     * @param  \App\Models\Contract  $contract
     * @return void
     */
    public function updated(Contract $contract)
    {
    }

    /**
     * Handle the Contract "deleted" event.
     *
     * @param  \App\Models\Contract  $contract
     * @return void
     */
    public function deleted(Contract $contract)
    {
        //
    }

    /**
     * Handle the Contract "restored" event.
     *
     * @param  \App\Models\Contract  $contract
     * @return void
     */
    public function restored(Contract $contract)
    {
        //
    }

    /**
     * Handle the Contract "force deleted" event.
     *
     * @param  \App\Models\Contract  $contract
     * @return void
     */
    public function forceDeleted(Contract $contract)
    {
        //
    }
}
