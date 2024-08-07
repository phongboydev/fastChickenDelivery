<?php

namespace App\Observers;

use App\Models\ClientPosition;
use App\Exceptions\HumanErrorException;
use App\Models\Client;
use Illuminate\Support\Str;

class ClientPositionObserver
{

    public function creating(ClientPosition $clientPosition)
    {
        $client = auth()->user()->is_internal
            ? Client::select('code')->find($clientPosition->client_id)
            : auth()->user()->client;

        if ($client && Str::of($clientPosition->code)->startsWith($client->code)) {
            $position = ClientPosition::where([
                'code' => $clientPosition->code,
                'client_id' => $clientPosition->client_id,
            ])->first();

            if ($position) {
                throw new HumanErrorException(__("error.position_code", ['code' => $clientPosition->code]));
            }
        } else {
            throw new HumanErrorException(__('error.the_code_is_not_in_the_correct_format'));
        }
    }

    /**
     * Handle the ClientPosition "created" event.
     *
     * @param  \App\Models\ClientPosition  $clientPosition
     * @return void
     */
    public function created(ClientPosition $clientPosition)
    {
        //
    }

    public function updating(ClientPosition $clientPosition)
    {
        $client = auth()->user()->is_internal
            ? Client::select('code')->find($clientPosition->client_id)
            : auth()->user()->client;

        if ($client && Str::of($clientPosition->code)->startsWith($client->code)) {
            if ($clientPosition->code != $clientPosition->getOriginal('code')) {
                $position = clientPosition::where([
                    'code' => $clientPosition->code,
                    'client_id' => $clientPosition->client_id,
                ])->first();
                if ($position) {
                    throw new HumanErrorException(__("error.position_code", ['code' => $clientPosition->code]));
                }
            } else {
                throw new HumanErrorException(__("model.employees.update_failed"));
            }
        } else {
            throw new HumanErrorException(__('error.the_code_is_not_in_the_correct_format'));
        }
    }

    /**
     * Handle the ClientPosition "updated" event.
     *
     * @param  \App\Models\ClientPosition  $clientPosition
     * @return void
     */
    public function updated(ClientPosition $clientPosition)
    {
        //
    }

    /**
     * Handle the ClientPosition "deleted" event.
     *
     * @param  \App\Models\ClientPosition  $clientPosition
     * @return void
     */
    public function deleted(ClientPosition $clientPosition)
    {
        //
    }

    /**
     * Handle the ClientPosition "restored" event.
     *
     * @param  \App\Models\ClientPosition  $clientPosition
     * @return void
     */
    public function restored(ClientPosition $clientPosition)
    {
        //
    }

    /**
     * Handle the ClientPosition "force deleted" event.
     *
     * @param  \App\Models\ClientPosition  $clientPosition
     * @return void
     */
    public function forceDeleted(ClientPosition $clientPosition)
    {
        //
    }
}
