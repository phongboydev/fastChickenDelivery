<?php

namespace App\Policies;

use App\Models\ClientEmployeeGroupAssignment;
use App\User;
use App\Models\ClientEmployee;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientEmployeeGroupAssignmentPolicy
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
        //
    }

    /**
     * Determine whether the user can view the client employee leave request.
     *
     * @param User                             $user
     * @param  \App\ClientEmployeeLeaveRequest $clientEmployeeLeaveRequest
     *
     * @return mixed
     */
    public function view(User $user, ClientEmployeeGroupAssignment $clientEmployeeLeaveRequest)
    {
        //
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
    public function update(User $user, ClientEmployeeGroupAssignment $clientEmployeeGroupAssignment)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id !== $clientEmployeeGroupAssignment->client_id) {
                return false;
            }
            return $user->checkHavePermission(['manage-employee'], ['advanced-manage-employee-group-update'], $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {
            return $user->checkHavePermission([], [], false, $clientEmployeeGroupAssignment->client_id);
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
    public function delete(User $user, ClientEmployeeGroupAssignment $clientEmployeeGroupAssignment)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id !== $clientEmployeeGroupAssignment->client_id) {
                return false;
            }
            return $user->checkHavePermission(['manage-employee'], ['advanced-manage-employee-group-delete'], $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {
            return $user->checkHavePermission([], [], false, $clientEmployeeGroupAssignment->client_id);
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
    public function restore(User $user, ClientEmployeeGroupAssignment $clientEmployeeLeaveRequest)
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
    public function forceDelete(User $user, ClientEmployeeGroupAssignment $clientEmployeeLeaveRequest)
    {
        //
    }
}
