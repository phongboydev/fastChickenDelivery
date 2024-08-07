<?php

namespace App\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\TrainingSeminarAttendance;
use App\Support\Constant;

class TrainingSeminarAttendancePolicy
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
     * @param TrainingSeminarAttendance $trainingSeminarAttendance
     *
     * @return mixed
     */
    public function view(User $user, TrainingSeminarAttendance $trainingSeminarAttendance)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id == $trainingSeminarAttendance->client_id && $user->hasDirectPermission($this->managerPermission) || $user->client_id == $trainingSeminarAttendance->client_id && $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
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
    public function create(User $user, TrainingSeminarAttendance $trainingSeminarAttendance)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id == $trainingSeminarAttendance->client_id && $user->hasDirectPermission($this->managerPermission) || $user->client_id == $trainingSeminarAttendance->client_id && $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can update the client employee overtime request.
     *
     * @param User                          $user
     * @param TrainingSeminarAttendance $trainingSeminarAttendance
     *
     * @return mixed
     */
    public function update(User $user, TrainingSeminarAttendance $trainingSeminarAttendance)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id == $trainingSeminarAttendance->client_id && $user->hasDirectPermission($this->managerPermission) || $user->client_id == $trainingSeminarAttendance->client_id && $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can delete the client employee overtime request.
     *
     * @param  User  $user
     * @param TrainingSeminarAttendance $trainingSeminarAttendance
     *
     * @return mixed
     */
    public function delete(User $user, TrainingSeminarAttendance $trainingSeminarAttendance)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id == $trainingSeminarAttendance->client_id && $user->hasDirectPermission($this->managerPermission) || $user->client_id == $trainingSeminarAttendance->client_id && $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can restore the client employee overtime request.
     *
     * @param User                          $user
     * @param TrainingSeminarAttendance $trainingSeminarAttendance
     *
     * @return mixed
     */
    public function restore(User $user, TrainingSeminarAttendance $trainingSeminarAttendance)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee overtime request.
     *
     * @param User                          $user
     * @param TrainingSeminarAttendance $trainingSeminarAttendance
     *
     * @return mixed
     */
    public function forceDelete(User $user, TrainingSeminarAttendance $trainingSeminarAttendance)
    {
        //
    }
}
