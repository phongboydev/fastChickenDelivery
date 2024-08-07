<?php

namespace App\Policies;

use App\Models\ClientEmployeeLeaveManagement;
use App\Models\LeaveCategory;
use App\Support\Constant;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientEmployeeLeaveManagementPolicy
{

    use HandlesAuthorization;

    private $clientManagerPermission = 'manage-workschedule';

    /**
     * Determine whether the user can view any client employee leave requests.
     *
     * @param ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement
     * @return mixed
     */
    public function viewAny(ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
    {
        //
    }

    /**
     * Determine whether the user can view the client employee leave request.
     *
     * @param User $user
     * @param ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement
     * @return mixed
     */
    public function view(User $user, ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
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
        $leaveCategory = LeaveCategory::find($injected['leave_category_id']);
        return $this->isPermission($user, $leaveCategory->client_id);
    }

    /**
     * Determine whether the user can update the client employee leave request.
     *
     * @param User $user
     * @param LeaveCategory $leaveCategory
     *
     * @return mixed
     */
    public function update(User $user, ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement, $injectedArgs = [])
    {
        $leaveCategory = LeaveCategory::find($clientEmployeeLeaveManagement->leave_category_id);
        return $this->isPermission($user, $leaveCategory->client_id);
    }

    /**
     * Determine whether the user can delete the client employee leave request.
     *
     * @param User $user
     * @param ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement
     * @return bool
     */
    public function delete(User $user, ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
    {
        $leaveCategory = LeaveCategory::find($clientEmployeeLeaveManagement->leave_category_id);
        return $this->isPermission($user, $leaveCategory->client_id);
    }

    /**
     * Determine whether the user can restore the client employee leave request.
     *
     * @param User $user
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
     * @param User $user
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
