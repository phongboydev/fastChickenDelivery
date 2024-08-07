<?php

namespace App\Policies;

use App\Models\CalculationSheetClientEmployee;
use App\User;
use App\Models\ClientEmployee;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class CalculationSheetClientEmployeePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any calculation sheet client employees.
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
     * Determine whether the user can view the calculation sheet client employee.
     *
     * @param User                                 $user
     * @param  \App\CalculationSheetClientEmployee $calculationSheetClientEmployee
     *
     * @return mixed
     */
    public function view(User $user, CalculationSheetClientEmployee $calculationSheetClientEmployee)
    {
        //
    }

    /**
     * Determine whether the user can create calculation sheet client employees.
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
                default:
                    return false;
            }
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($injected['client_employee_id'])) {
                    $clientEmployee = (new ClientEmployee)->findOrFail($injected['client_employee_id']);
                    if ($user->iGlocalEmployee->isAssignedFor($clientEmployee->client_id)) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can update the calculation sheet client employee.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetClientEmployee  $calculationSheetClientEmployee
     *
     * @return mixed
     */
    public function update(User $user, CalculationSheetClientEmployee $calculationSheetClientEmployee)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    if ($user->client_id == $calculationSheetClientEmployee->clientEmployee->client_id) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (($user->iGlocalEmployee->isAssignedFor($calculationSheetClientEmployee->clientEmployee->client_id)) && ($calculationSheetClientEmployee->calculationSheet->status == Constant::NEW_STATUS)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the calculation sheet client employee.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetClientEmployee  $calculationSheetClientEmployee
     *
     * @return mixed
     */
    public function delete(User $user, CalculationSheetClientEmployee $calculationSheetClientEmployee)
    {
        $role = $user->getRole();

        if (!$user->isInternalUser()) {

            switch ($role) {
                default:
                    return false;
            }
        } else {

            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($calculationSheetClientEmployee->clientEmployee->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the calculation sheet client employee.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetClientEmployee  $calculationSheetClientEmployee
     *
     * @return mixed
     */
    public function restore(User $user, CalculationSheetClientEmployee $calculationSheetClientEmployee)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the calculation sheet client employee.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetClientEmployee  $calculationSheetClientEmployee
     *
     * @return mixed
     */
    public function forceDelete(User $user, CalculationSheetClientEmployee $calculationSheetClientEmployee)
    {
        //
    }
}
