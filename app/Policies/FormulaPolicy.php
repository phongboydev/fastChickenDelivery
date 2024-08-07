<?php

namespace App\Policies;

use App\Models\Formula;
use App\Models\ClientWorkflowSetting;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class FormulaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any formulas.
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
     * Determine whether the user can view the formula.
     *
     * @param User          $user
     * @param  \App\Formula $formula
     *
     * @return mixed
     */
    public function view(User $user, Formula $formula)
    {

    }

    /**
     * Determine whether the user can create formulas.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id == $injected['client_id'] && $user->hasDirectPermission('manage-formula')) {
                return true;
            }
            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_formula')) {
                return true;
            }else{
                if (!empty($injected['client_id'])) {
                    if ($user->iGlocalEmployee->isAssignedFor($injected['client_id'])) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can update the formula.
     *
     * @param  User  $user
     * @param  \App\Formula  $formula
     *
     * @return mixed
     */
    public function update(User $user, Formula $formula)
    {
        if (!$user->isInternalUser()) 
        {
            if ($user->client_id == $formula->client_id && $user->hasDirectPermission('manage-formula')) {
                return true;
            }
            return false;
        } else {

            $role = $user->getRole();

            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_formula')) {
                return true;
            }else{
                if (!empty($formula->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($formula->client_id)) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the formula.
     *
     * @param  User  $user
     * @param  \App\Formula  $formula
     *
     * @return mixed
     */
    public function delete(User $user, Formula $formula)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id == $formula->client_id && $user->hasDirectPermission('manage-formula')) {
                return true;
            }

            return false;
        } else {

            $role = $user->getRole();

            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_formula')) {
                return true;
            }else{
                if (!empty($formula->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($formula->client_id)) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the formula.
     *
     * @param  User  $user
     * @param  \App\Formula  $formula
     *
     * @return mixed
     */
    public function restore(User $user, Formula $formula)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the formula.
     *
     * @param  User  $user
     * @param  \App\Formula  $formula
     *
     * @return mixed
     */
    public function forceDelete(User $user, Formula $formula)
    {
        //
    }
}
