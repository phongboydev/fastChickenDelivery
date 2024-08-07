<?php

namespace App\Policies;

use App\Models\Timesheet;
use App\User;
use App\Models\ClientEmployee;
use App\Models\WorkSchedule;
use App\Models\ClientWorkflowSetting;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;
use App\Exceptions\CustomException;

class TimesheetPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any timesheets.
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
     * Determine whether the user can view the timesheet.
     *
     * @param User            $user
     * @param  \App\Timesheet $timesheet
     *
     * @return mixed
     */
    public function view(User $user, Timesheet $timesheet)
    {
        //
    }

    /**
     * Determine whether the user can create timesheets.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        $clientEmployee = (new ClientEmployee)->query()->where("id", $injected['client_employee_id'])->first();
        $clientWorkflowSetting = ClientWorkflowSetting::select('enable_timesheet_input')->where('client_id', '=', $user->client_id)->first();

        if (!$user->isInternalUser()) {
            if ($clientEmployee->client_id != $user->client_id) {
                logger(self::class . ": ClientID not match");
                return false;
            }
            if (!$clientWorkflowSetting->enable_timesheet_input) {
                logger(self::class . ": User input is not allowed");
                return false;
            }
            if ($user->clientEmployee->id == $clientEmployee->id) {
                logger(self::class . ": Set own timesheet");
                return true;
            }
            if ($user->hasPermissionTo("manage-timesheet")) {
                logger(self::class . ": Set other timesheet");
                return true;
            }
            return false;
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_STAFF:
                case Constant::ROLE_INTERNAL_LEADER:
                    if ($user->iGlocalEmployee->isAssignedFor($clientEmployee['client_id'])) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        }
        return false;
    }

    /**
     * Determine whether the user can update the timesheet.
     *
     * @param  User  $user
     * @param  \App\Timesheet  $timesheet
     *
     * @return mixed
     */
    public function update(User $user, Timesheet $timesheet, array $injected)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();

            $clientWorkflowSetting = ClientWorkflowSetting::select('enable_timesheet_input')->where('client_id', '=', $user->client_id)->first();

            return $user->hasAnyPermission(['manage-employee', 'manage-timesheet', 'CLIENT_REQUEST_TIMESHEET']) && $clientWorkflowSetting->enable_timesheet_input;

            // switch ($role) {
            //     case Constant::ROLE_CLIENT_MANAGER:
            //         if (!empty($injected['client_employee_id'])) {
            //             $clientEmployee = (new ClientEmployee)->findOrFail($injected['client_employee_id']);
            //             if ($user->client_id == $clientEmployee['client_id']) {
            //                 return true;
            //             }
            //         }
            //         return false;
            //     case Constant::ROLE_CLIENT_LEADER:
            //         $clientEmployee = (new ClientEmployee)->findOrFail($injected['client_employee_id']);
            //         if ($user->client_id == $clientEmployee->client_id &&
            //             $clientEmployee->isAssignedFor($user->clientEmployee)) {
            //             return true;
            //         }
            //         // Tu duyet cho chinh minh
            //         if ($user->clientEmployee->id == $injected['client_employee_id']) {
            //             return true;
            //         }
            //         return false;
            //     default:
            //         if ($user->clientEmployee->id == $injected['client_employee_id']) {
            //             return true;
            //         }
            //         return false;

            // }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_STAFF:
                case Constant::ROLE_INTERNAL_LEADER:
                    if ($user->iGlocalEmployee->isAssignedFor($timesheet->clientEmployee->client_id)) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the timesheet.
     *
     * @param  User  $user
     * @param  \App\Timesheet  $timesheet
     *
     * @return mixed
     */
    public function delete(User $user, Timesheet $timesheet)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    if ($user->client_id == $timesheet->clientEmployee->client_id) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_STAFF:
                case Constant::ROLE_INTERNAL_LEADER:
                    if ($user->iGlocalEmployee->isAssignedFor($timesheet->clientEmployee->client_id)) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the timesheet.
     *
     * @param  User  $user
     * @param  \App\Timesheet  $timesheet
     *
     * @return mixed
     */
    public function restore(User $user, Timesheet $timesheet)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the timesheet.
     *
     * @param  User  $user
     * @param  \App\Timesheet  $timesheet
     *
     * @return mixed
     */
    public function forceDelete(User $user, Timesheet $timesheet)
    {
        //
    }

    public function updateFlexible(User $user)
    {
        if ($user->isInternalUser()) {
            return true;
        }
        return false;
    }
}
