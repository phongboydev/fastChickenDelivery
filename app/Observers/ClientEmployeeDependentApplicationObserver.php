<?php

namespace App\Observers;

use App\Models\ClientEmployeeDependentApplication;

class ClientEmployeeDependentApplicationObserver
{
    /**
     * Handle the ClientEmployeeDependentApplication "created" event.
     *
     * @param  \App\Models\ClientEmployeeDependentApplication  $clientEmployeeDependentApplication
     * @return void
     */
    public function created(ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        //
    }

    /**
     * Handle the ClientEmployeeDependentApplication "updated" event.
     *
     * @param  \App\Models\ClientEmployeeDependentApplication  $clientEmployeeDependentApplication
     * @return void
     */
    public function updated(ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        //
    }

    public function updating(ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        if (auth()->user()->is_internal) {
            return true;
        }

        if (
            $clientEmployeeDependentApplication->processing === 'result_sent' &&
            $clientEmployeeDependentApplication->status === 'rejected'
        ) {
            $replicate = $clientEmployeeDependentApplication->replicate()->fill([
                'replicate_id' => $clientEmployeeDependentApplication->id,
                'processing' => 'adjustment',
                'status' => null,
                'internal_note' => null,
                'created_at' => $clientEmployeeDependentApplication->created_at
            ]);

            // Clone Data
            $replicate->save();

            // Copy Media
            $clientEmployeeDependentApplication->media->each(function ($mediaItem) use ($replicate) {
                $mediaItem->copy($replicate, 'Attachments', 'minio');
            });

            // softDelete old data
            $clientEmployeeDependentApplication->delete();

            return false;
        }

        return true;
    }

    /**
     * Handle the ClientEmployeeDependentApplication "deleted" event.
     *
     * @param  \App\Models\ClientEmployeeDependentApplication  $clientEmployeeDependentApplication
     * @return void
     */
    public function deleted(ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        //
    }

    /**
     * Handle the ClientEmployeeDependentApplication "restored" event.
     *
     * @param  \App\Models\ClientEmployeeDependentApplication  $clientEmployeeDependentApplication
     * @return void
     */
    public function restored(ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        //
    }

    /**
     * Handle the ClientEmployeeDependentApplication "force deleted" event.
     *
     * @param  \App\Models\ClientEmployeeDependentApplication  $clientEmployeeDependentApplication
     * @return void
     */
    public function forceDeleted(ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        //
    }
}
