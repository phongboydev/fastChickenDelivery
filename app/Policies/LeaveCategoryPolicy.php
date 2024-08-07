<?php

namespace App\Policies;

use App\Models\LeaveCategory;
use App\Models\WorktimeRegisterCategory;
use App\Support\Constant;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeaveCategoryPolicy
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
    public function viewAny(LeaveCategory $leaveCategory)
    {
        //
    }

    /**
     * Determine whether the user can view the client employee leave request.
     *
     * @param User                  $user
     * @param LeaveCategory $leaveCategory
     *
     * @return mixed
     */
    public function view(User $user, LeaveCategory $leaveCategory)
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
        return $this->isPermission($user, $injected['client_id']);
    }

    /**
     * Determine whether the user can update the client employee leave request.
     *
     * @param User                  $user
     * @param LeaveCategory $leaveCategory
     *
     * @return mixed
     */
    public function update(User $user, LeaveCategory $leaveCategory, $injectedArgs = [])
    {
        return $this->isPermission($user, $leaveCategory->client_id);
    }

    /**
     * Determine whether the user can delete the client employee leave request.
     *
     * @param User                  $user
     * @param LeaveCategory $leaveCategory
     *
     * @return mixed
     */
    public function delete(User $user, LeaveCategory $leaveCategory)
    {
        return $this->isPermission($user, $leaveCategory->client_id);
    }

    /**
     * Determine whether the user can restore the client employee leave request.
     *
     * @param User                  $user
     * @param LeaveCategory $leaveCategory
     *
     * @return mixed
     */
    public function restore(User $user, LeaveCategory $leaveCategory)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee leave request.
     *
     * @param User                  $user
     * @param LeaveCategory $leaveCategory
     *
     * @return mixed
     */
    public function forceDelete(User $user, LeaveCategory $leaveCategory)
    {
    }

    public function isPermission($user, $clientId)
    {
        $isHavePermission = false;
        if (!$user->isInternalUser()) {
            if ($user->client_id == $clientId && $user->hasDirectPermission($this->clientManagerPermission) ||
                $user->client_id == $clientId && $user->getRole() === Constant::ROLE_CLIENT_MANAGER) {
                $isHavePermission = true;
            }
        }
        return $isHavePermission;
    }
}
