<?php

namespace App\Policies;

use App\Models\ClientEmployee;
use App\User;
use App\Models\TrainingSeminar;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class TrainingSeminarPolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-training';

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
     * @param TrainingSeminar $trainingSeminar
     *
     * @return mixed
     */
    public function view(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            return $user->hasDirectPermission($this->managerPermission) || $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            return false;
        }
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
            return $user->client_id == $injected['client_id'] && $user->hasDirectPermission($this->managerPermission) || $user->client_id == $injected['client_id'] && $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            return false;
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
    public function update(User $user, TrainingSeminar $trainingSeminar)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id == $trainingSeminar->client_id && $user->hasDirectPermission($this->managerPermission) || $user->client_id == $trainingSeminar->client_id && $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            return false;
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
    public function delete(User $user, TrainingSeminar $trainingSeminar)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id == $trainingSeminar->client_id && $user->hasDirectPermission($this->managerPermission) || $user->client_id == $trainingSeminar->client_id && $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can restore the client employee overtime request.
     *
     * @param User                          $user
     * @param TrainingSeminar $trainingSeminar
     *
     * @return mixed
     */
    public function restore(User $user, TrainingSeminar $trainingSeminar)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee overtime request.
     *
     * @param User                          $user
     * @param TrainingSeminar $trainingSeminar
     *
     * @return mixed
     */
    public function forceDelete(User $user, TrainingSeminar $trainingSeminar)
    {
        //
    }

    public function upload(User $user, TrainingSeminar $trainingSeminar)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id == $trainingSeminar->client_id && $user->hasDirectPermission($this->managerPermission) || $user->client_id == $trainingSeminar->client_id && $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            return false;
        }
    }
}
