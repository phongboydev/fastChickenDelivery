<?php

namespace App\Policies;

use App\Models\CalculationSheetVariable;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class CalculationSheetVariablePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any calculation sheet variables.
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
     * Determine whether the user can view the calculation sheet variable.
     *
     * @param User                           $user
     * @param  \App\CalculationSheetVariable $calculationSheetVariable
     *
     * @return mixed
     */
    public function view(User $user, CalculationSheetVariable $calculationSheetVariable)
    {
        //
    }

    /**
     * Determine whether the user can create calculation sheet variables.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user)
    {
        return false;
    }

    /**
     * Determine whether the user can update the calculation sheet variable.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetVariable  $calculationSheetVariable
     *
     * @return mixed
     */
    public function update(User $user, CalculationSheetVariable $calculationSheetVariable)
    {
        if (!$user->isInternalUser()) {
            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (($user->iGlocalEmployee->isAssignedFor($calculationSheetVariable->clientEmployee->client_id)) && ($calculationSheetVariable->calculationSheet->status == Constant::NEW_STATUS)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the calculation sheet variable.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetVariable  $calculationSheetVariable
     *
     * @return mixed
     */
    public function delete(User $user, CalculationSheetVariable $calculationSheetVariable)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
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
     * Determine whether the user can restore the calculation sheet variable.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetVariable  $calculationSheetVariable
     *
     * @return mixed
     */
    public function restore(User $user, CalculationSheetVariable $calculationSheetVariable)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the calculation sheet variable.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetVariable  $calculationSheetVariable
     *
     * @return mixed
     */
    public function forceDelete(User $user, CalculationSheetVariable $calculationSheetVariable)
    {
        //
    }
}
