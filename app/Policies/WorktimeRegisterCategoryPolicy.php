<?php

namespace App\Policies;

use App\Models\WorktimeRegisterCategory;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorktimeRegisterCategoryPolicy
{

    use HandlesAuthorization;

    private $clientManagerPermission = 'manage-workschedule';

    /**
     * Determine whether the user can view any client employee leave requests.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function viewAny(WorktimeRegisterCategory $worktimeRegister)
    {
        //
    }

    /**
     * Determine whether the user can view the client employee leave request.
     *
     * @param User                  $user
     * @param \App\WorktimeRegister $worktimeRegister
     *
     * @return mixed
     */
    public function view(User $user, WorktimeRegisterCategory $worktimeRegister)
    {
        //
    }

    /**
     * Determine whether the user can create client employee leave requests.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            if (!empty($injected['client_id'])) {
                if ($user->client_id == $injected['client_id'] && $user->hasDirectPermission($this->clientManagerPermission)) {
                    return true;
                }
            }
            return false;
        } else {
            return true;
        }
    }

    /**
     * Determine whether the user can update the client employee leave request.
     *
     * @param User                  $user
     * @param \App\WorktimeRegister $worktimeRegister
     *
     * @return mixed
     */
    public function update(User $user, WorktimeRegisterCategory $worktimeRegister, $injectedArgs = [])
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id == $worktimeRegister->client_id && $user->hasDirectPermission($this->clientManagerPermission)) {
                return true;
            }
            return false;
        } else {
            return true;
        }
    }

    /**
     * Determine whether the user can delete the client employee leave request.
     *
     * @param User                  $user
     * @param \App\WorktimeRegister $worktimeRegister
     *
     * @return mixed
     */
    public function delete(User $user, WorktimeRegisterCategory $worktimeRegister)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id == $worktimeRegister->client_id && $user->hasDirectPermission($this->clientManagerPermission)) {
                return true;
            }
            return false;
        } else {
            return true;
        }
    }

    /**
     * Determine whether the user can restore the client employee leave request.
     *
     * @param User                  $user
     * @param \App\WorktimeRegister $worktimeRegister
     *
     * @return mixed
     */
    public function restore(User $user, WorktimeRegisterCategory $worktimeRegister)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee leave request.
     *
     * @param User                  $user
     * @param \App\WorktimeRegister $worktimeRegister
     *
     * @return mixed
     */
    public function forceDelete(User $user, WorktimeRegisterCategory $worktimeRegister)
    {
    }
}
