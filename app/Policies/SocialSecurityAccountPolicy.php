<?php

namespace App\Policies;

use App\Models\SocialSecurityAccount;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class SocialSecurityAccountPolicy
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
        return true;
    }

    /**
     * Determine whether the user can view the formula.
     *
     * @param User          $user
     * @param  \App\Formula $formula
     *
     * @return mixed
     */
    public function view(User $user, SocialSecurityAccount $socialSecurityAccount)
    {
        return true;
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
        $role = $user->getRole();

        if (!$user->isInternalUser())
        {
            return false;

        } else {

            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
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

    public function update(User $user, SocialSecurityAccount $socialSecurityAccount)
    {
        if (!$user->isInternalUser())
        {
            return false;

        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($socialSecurityAccount->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($socialSecurityAccount->client_id)) {
                        return true;
                    }
                }
                return false;
            }

        }
    }

    public function delete(User $user, SocialSecurityAccount $socialSecurityAccount)
    {
        if (!$user->isInternalUser())
        {
            return false;

        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($socialSecurityAccount->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($socialSecurityAccount->client_id)) {
                        return true;
                    }
                }
                return false;
            }

        }
    }
}
