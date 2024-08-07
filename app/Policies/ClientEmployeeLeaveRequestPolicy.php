<?php

namespace App\Policies;

use App\Models\ClientEmployeeLeaveRequest;
use App\User;
use App\Models\ClientEmployee;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientEmployeeLeaveRequestPolicy
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
    public function view(User $user, ClientEmployeeLeaveRequest $clientEmployeeLeaveRequest)
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
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    if (!empty($injected['client_employee_id'])) {
                        $clientEmployee = (new ClientEmployee)->findOrFail($injected['client_employee_id']);
                        if ($user->client_id == $clientEmployee['client_id']) {
                            return true;
                        }
                    }
                    return false;
                case Constant::ROLE_CLIENT_LEADER:
                    $clientEmployee = (new ClientEmployee)->findOrFail($injected['client_employee_id']);
                    if ($user->client_id == $clientEmployee->client_id && 
                        $clientEmployee->isAssignedFor($user->clientEmployee)) {
                        return true;
                    }
                    // Leader tu duyet leader
                    if ($user->clientEmployee->id == $injected['client_employee_id']) {
                        return true;
                    }
                    return false;
                default:
                    if ((!empty($injected['client_employee_id'])) && ($user->clientEmployee->id == $injected['client_employee_id'])) {
                        return true;
                    }
                    return false;
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return false;
            }
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
    public function update(User $user, ClientEmployeeLeaveRequest $clientEmployeeLeaveRequest)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    if ($user->client_id == $clientEmployeeLeaveRequest->clientEmployee->client_id) {
                        return true;
                    }
                    return false;
                case Constant::ROLE_CLIENT_LEADER:
                    $clientEmployee = $clientEmployeeLeaveRequest->clientEmployee;
                    logger("ClientEmployeeLeaveRequestPolicy::update clientEmployee: " . $clientEmployee->id);
                    logger("ClientEmployeeLeaveRequestPolicy::update isAssigned: " . $clientEmployee->isAssignedFor($user->clientEmployee));
                    if ($user->client_id == $clientEmployee->client_id && 
                        $clientEmployee->isAssignedFor($user->clientEmployee)) {
                        return true;
                    }
                    // Leader tu duyet leader
                    if ($user->clientEmployee->id == $clientEmployeeLeaveRequest->client_employee_id) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return false;
            }
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
    public function delete(User $user, ClientEmployeeLeaveRequest $clientEmployeeLeaveRequest)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    if ($user->client_id == $clientEmployeeLeaveRequest->clientEmployee->client_id) {
                        return true;
                    }
                    return false;
                case Constant::ROLE_CLIENT_LEADER:
                    $clientEmployee = $clientEmployeeLeaveRequest->clientEmployee;
                    if ($user->client_id == $clientEmployee->client_id && 
                        $clientEmployee->isAssignedFor($user->clientEmployee)) {
                        return true;
                    }
                    // Leader tu duyet leader
                    if ($user->clientEmployee->id == $clientEmployeeLeaveRequest->client_employee_id) {
                        return true;
                    }
                    return false;                    
                default:
                    if( ($user->clientEmployee->id == $clientEmployeeLeaveRequest->client_employee_id) && ($clientEmployeeLeaveRequest->status == 'pending') ) {
                        return true;
                    }
                    return false;
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return false;
            }
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
    public function restore(User $user, ClientEmployeeLeaveRequest $clientEmployeeLeaveRequest)
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
    public function forceDelete(User $user, ClientEmployeeLeaveRequest $clientEmployeeLeaveRequest)
    {
        //
    }
}
