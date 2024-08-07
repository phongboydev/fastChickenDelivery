<?php

namespace App\Policies;

use App\Models\ClientEmployeeGroup;
use App\User;
use App\Models\ClientEmployee;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientEmployeeGroupPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any client employee leave requests.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the client employee leave request.
     *
     * @param User                             $user
     * @param  \App\ClientEmployeeLeaveRequest $clientEmployeeLeaveRequest
     *
     * @return mixed
     */
    public function view(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can create client employee leave requests.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $injected['client_id']) return false;
            return $user->checkHavePermission(['manage-employee'], ['advanced-manage-employee-group-create'], $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {

            if (empty($injected['client_id'])) return false;

            return $user->checkHavePermission([], [], false, $injected['client_id']);
        }
    }

    /**
     * Determine whether the user can update the client employee leave request.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeLeaveRequest  $clientEmployeeLeaveRequest
     *
     * @return mixed
     */
    public function update(User $user, ClientEmployeeGroup $clientEmployeeGroup)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id !== $clientEmployeeGroup->client_id) {
                return false;
            }
            return $user->checkHavePermission(['manage-employee'], ['advanced-manage-employee-group-update'], $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {
            return $user->checkHavePermission([], [], false, $clientEmployeeGroup->client_id);
        }
    }

    /**
     * Determine whether the user can delete the client employee leave request.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeLeaveRequest  $clientEmployeeLeaveRequest
     *
     * @return mixed
     */
    public function delete(User $user, ClientEmployeeGroup $clientEmployeeGroup)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id !== $clientEmployeeGroup->client_id) {
                return false;
            }
            return $user->checkHavePermission(['manage-employee'], ['advanced-manage-employee-group-delete'], $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {
            return $user->checkHavePermission([], [], false, $clientEmployeeGroup->client_id);
        }
    }

    /**
     * Determine whether the user can restore the client employee leave request.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeLeaveRequest  $clientEmployeeLeaveRequest
     *
     * @return mixed
     */
    public function restore(User $user, ClientEmployeeGroup $clientEmployeeLeaveRequest)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee leave request.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeLeaveRequest  $clientEmployeeLeaveRequest
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientEmployeeGroup $clientEmployeeLeaveRequest)
    {
        //
    }
}
