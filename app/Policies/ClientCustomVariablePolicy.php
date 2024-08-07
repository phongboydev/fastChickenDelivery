<?php

namespace App\Policies;

use App\Models\ClientCustomVariable;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientCustomVariablePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any client custom variables.
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
     * Determine whether the user can view the client custom variable.
     *
     * @param User                       $user
     * @param  \App\ClientCustomVariable $clientCustomVariable
     *
     * @return mixed
     */
    public function view(User $user, ClientCustomVariable $clientCustomVariable)
    {
        //
    }

    /**
     * Determine whether the user can create client custom variables.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            
            if (!empty($injected['client_id'])) {
                if ($user->client_id == $injected['client_id'] && $user->hasDirectPermission('manage-payroll')) {
                    return true;
                }
            }

            return false;

        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
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
     * Determine whether the user can update the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientCustomVariable  $clientCustomVariable
     *
     * @return mixed
     */
    public function update(User $user, ClientCustomVariable $clientCustomVariable)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id == $clientCustomVariable->client_id && $user->hasDirectPermission('manage-payroll')) {
                return true;
            }

            return false;
        } else {
            
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($clientCustomVariable->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientCustomVariable  $clientCustomVariable
     *
     * @return mixed
     */
    public function delete(User $user, ClientCustomVariable $clientCustomVariable)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id == $clientCustomVariable->client_id && $user->hasDirectPermission('manage-payroll')) {
                return true;
            }
            return false;
        } else {
            
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($clientCustomVariable->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientCustomVariable  $clientCustomVariable
     *
     * @return mixed
     */
    public function restore(User $user, ClientCustomVariable $clientCustomVariable)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientCustomVariable  $clientCustomVariable
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientCustomVariable $clientCustomVariable)
    {
        //
    }
}
