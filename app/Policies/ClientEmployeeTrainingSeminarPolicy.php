<?php

namespace App\Policies;

use App\User;
use App\Models\ClientEmployeeTrainingSeminar;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientEmployeeTrainingSeminarPolicy
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
     * @param ClientEmployeeTrainingSeminar $trainingSeminar
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
            return $user->hasDirectPermission($this->managerPermission) || $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can update the client employee overtime request.
     *
     * @param User                          $user
     * @param ClientEmployeeTrainingSeminar $trainingSeminar
     *
     * @return mixed
     */
    public function update(User $user, ClientEmployeeTrainingSeminar $trainingSeminar)
    {
        if (!$user->isInternalUser()) {
            return $user->hasDirectPermission($this->managerPermission) || $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can delete the client employee overtime request.
     *
     * @param  User  $user
     * @param ClientEmployeeTrainingSeminar  $clientEmployeeOvertimeRequest
     *
     * @return mixed
     */
    public function delete(User $user, ClientEmployeeTrainingSeminar $trainingSeminar)
    {
        if (!$user->isInternalUser()) {
            return $user->hasDirectPermission($this->managerPermission) || $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can restore the client employee overtime request.
     *
     * @param User                          $user
     * @param ClientEmployeeTrainingSeminar $trainingSeminar
     *
     * @return mixed
     */
    public function restore(User $user, ClientEmployeeTrainingSeminar $trainingSeminar)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee overtime request.
     *
     * @param User                          $user
     * @param ClientEmployeeTrainingSeminar $trainingSeminar
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientEmployeeTrainingSeminar $trainingSeminar)
    {
        //
    }
}
