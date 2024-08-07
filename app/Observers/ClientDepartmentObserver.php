<?php

namespace App\Observers;

use App\Models\ClientDepartment;
use App\Exceptions\HumanErrorException;
use App\Models\Client;
use Illuminate\Support\Str;

class ClientDepartmentObserver
{

    public function creating(ClientDepartment $clientDepartment)
    {
        $client = auth()->user()->is_internal
            ? Client::select('code')->find($clientDepartment->client_id)
            : auth()->user()->client;

        if ($client && Str::of($clientDepartment->code)->startsWith($client->code)) {
            $department = ClientDepartment::where([
                'code' => $clientDepartment->code,
                'client_id' => $clientDepartment->client_id,
            ])->first();

            if ($department) {
                throw new HumanErrorException(__("error.department_code", ['code' => $clientDepartment->code]));
            }
        } else {
            throw new HumanErrorException(__('error.the_code_is_not_in_the_correct_format'));
        }
    }


    /**
     * Handle the ClientDepartment "created" event.
     *
     * @param  \App\Models\ClientDepartment  $clientDepartment
     * @return void
     */
    public function created(ClientDepartment $clientDepartment)
    {
        //
    }

    public function updating(ClientDepartment $clientDepartment)
    {
        $client = auth()->user()->is_internal
            ? Client::select('code')->find($clientDepartment->client_id)
            : auth()->user()->client;

        if ($client && Str::of($clientDepartment->code)->startsWith($client->code)) {
            if ($clientDepartment->code != $clientDepartment->getOriginal('code')) {
                $department = ClientDepartment::where([
                    'code' => $clientDepartment->code,
                    'client_id' => $clientDepartment->client_id,
                ])->first();
                if ($department) {
                    throw new HumanErrorException(__("error.department_code", ['code' => $clientDepartment->code]));
                }
            }
        } else {
            throw new HumanErrorException(__('error.the_code_is_not_in_the_correct_format'));
        }
    }

    /**
     * Handle the ClientDepartment "updated" event.
     *
     * @param  \App\Models\ClientDepartment  $clientDepartment
     * @return void
     */
    public function updated(ClientDepartment $clientDepartment)
    {
        //
    }

    /**
     * Handle the ClientDepartment "deleted" event.
     *
     * @param  \App\Models\ClientDepartment  $clientDepartment
     * @return void
     */
    public function deleted(ClientDepartment $clientDepartment)
    {
        //
    }

    /**
     * Handle the ClientDepartment "restored" event.
     *
     * @param  \App\Models\ClientDepartment  $clientDepartment
     * @return void
     */
    public function restored(ClientDepartment $clientDepartment)
    {
        //
    }

    /**
     * Handle the ClientDepartment "force deleted" event.
     *
     * @param  \App\Models\ClientDepartment  $clientDepartment
     * @return void
     */
    public function forceDeleted(ClientDepartment $clientDepartment)
    {
        //
    }
}
