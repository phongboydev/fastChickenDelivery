<?php

namespace App\Policies;

use App\Models\WorktimeRegister;
use App\User;
use App\Models\ClientEmployee;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class WorktimeRegisterPolicy
{

    use HandlesAuthorization;

    /**
     * Determine whether the user can view any client employee leave requests.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function viewAny(WorktimeRegister $worktimeRegister)
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
    public function view(User $user, WorktimeRegister $worktimeRegister)
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
        $clientEmployee = (new ClientEmployee)->findOrFail($injected['client_employee_id']);
        $client = $clientEmployee->client;

        if ($user->client_id != $client->id) {
            return false;
        }
        if (!$user->isInternalUser()) {
            if ((!empty($injected['client_employee_id'])) && ($user->clientEmployee->id == $injected['client_employee_id'])) {
                return true;
            }
            $normalPermission = ["manage-timesheet"];
            $advancedPermission = [];
            $type = $injected['type'] ?? null;
            if(!is_null($type)) {
                if ($type === 'leave_request') {
                    $advancedPermission[] = 'advanced-manage-timesheet-leave-update';
                } else if ($type === 'congtac_request') {
                    $advancedPermission[] = 'advanced-manage-timesheet-outside-working-wfh-update';
                } else if ($type === 'overtime_request') {
                    $advancedPermission[] = 'advanced-manage-timesheet-overtime-update';
                } else if ($type === 'timesheet') {
                    $advancedPermission[] = 'advanced-manage-timesheet-working-update';
                }
            }

            return $user->checkHavePermission($normalPermission, $advancedPermission, $user->getSettingAdvancedPermissionFlow());
        }
        return false;
    }

    /**
     * Determine whether the user can update the client employee leave request.
     *
     * @param User                  $user
     * @param \App\WorktimeRegister $worktimeRegister
     *
     * @return mixed
     */
    public function update(User $user, WorktimeRegister $worktimeRegister, $injectedArgs = [])
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id !== $worktimeRegister->clientEmployee->client_id) {
                return false;
            }
            if ($user->clientEmployee->id == $worktimeRegister->client_employee_id) {
                logger(self::class . ": Update own request");

                // only in case of cancel
                $status = $worktimeRegister->status;

                logger(self::class . ": old=" . $status . ", new=" . $injectedArgs['status']);
                if ($status == "approved" && $injectedArgs['status'] != "canceled_approved") {
                    return false;
                }
                return true;
            }
            // need check Approve
            return true;
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
     * @param User                  $user
     * @param \App\WorktimeRegister $worktimeRegister
     *
     * @return mixed
     */
    public function delete(User $user, WorktimeRegister $worktimeRegister)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    if ($user->client_id == $worktimeRegister->clientEmployee->client_id) {
                        return true;
                    }
                    return false;
                case Constant::ROLE_CLIENT_LEADER:
                    $clientEmployee = $worktimeRegister->clientEmployee;
                    if ($user->client_id == $clientEmployee->client_id &&
                        $clientEmployee->isAssignedFor($user->clientEmployee)) {
                        return true;
                    }
                    // Leader tu duyet leader
                    if ($user->clientEmployee->id == $worktimeRegister->client_employee_id) {
                        return true;
                    }
                    return false;
                default:
                    if (($user->clientEmployee->id == $worktimeRegister->client_employee_id) && ($worktimeRegister->status == 'pending')) {
                        return true;
                    }
                    return false;
            }
        } else {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            } else {
                return $user->iGlocalEmployee->isAssignedFor($worktimeRegister->clientEmployee->client_id);
            }
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
    public function restore(User $user, WorktimeRegister $worktimeRegister)
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
    public function forceDelete(User $user, WorktimeRegister $worktimeRegister)
    {
        if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
            return true;
        } else {
            return $user->iGlocalEmployee->isAssignedFor($worktimeRegister->clientEmployee->client_id);
        }
    }

    public function upload(User $user, WorktimeRegister $worktimeRegister)
    {
        return true;
    }
}
