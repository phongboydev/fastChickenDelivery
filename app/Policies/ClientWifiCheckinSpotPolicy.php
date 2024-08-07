<?php

namespace App\Policies;

use App\User;
use App\Models\ClientWifiCheckinSpot;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientWifiCheckinSpotPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any client employee overtime requests.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the client employee overtime request.
     *
     * @param User                          $user
     * @param ClientWifiCheckinSpot $clientWifiCheckinSpot
     *
     * @return mixed
     */
    public function view(User $user, ClientWifiCheckinSpot $clientWifiCheckinSpot)
    {
        //
    }

    /**
     * Determine whether the user can create client employee overtime requests.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id == $injected['client_id']) {
                return true;
            }

            return false;
        } else {

            return true;
        }
    }

    /**
     * Determine whether the user can update the client employee overtime request.
     *
     * @param User                          $user
     * @param ClientWifiCheckinSpot $clientWifiCheckinSpot
     *
     * @return mixed
     */
    public function update(User $user, ClientWifiCheckinSpot $clientWifiCheckinSpot)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id == $clientWifiCheckinSpot['client_id']) {
                return true;
            }

            return false;
        } else {
            return true;
        }
    }

    /**
     * Determine whether the user can delete the client employee overtime request.
     *
     * @param  User  $user
     * @param ClientEmployeeOvertimeRequest  $clientEmployeeOvertimeRequest
     *
     * @return mixed
     */
    public function delete(User $user, ClientWifiCheckinSpot $clientWifiCheckinSpot)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id == $clientWifiCheckinSpot['client_id']) {
                return true;
            }

            return false;
        } else {
            return true;
        }
    }

    /**
     * Determine whether the user can restore the client employee overtime request.
     *
     * @param User                          $user
     * @param ClientWifiCheckinSpot $clientWifiCheckinSpot
     *
     * @return mixed
     */
    public function restore(User $user, ClientWifiCheckinSpot $clientWifiCheckinSpot)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee overtime request.
     *
     * @param User                          $user
     * @param ClientWifiCheckinSpot $clientWifiCheckinSpot
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientWifiCheckinSpot $clientWifiCheckinSpot)
    {
        //
    }
}
