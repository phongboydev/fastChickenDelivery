<?php

namespace App\Policies;

use App\Models\SocialSecurityProfile;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class SocialSecurityProfilePolicy
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
    public function view(User $user, SocialSecurityProfile $formula)
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
            if (!empty($injected['client_id']) && $user->client_id != $injected['client_id']) return false;
            // Check permission

            return $user->checkHavePermission(['manage-social'], ['advanced-manage-payroll', 'advanced-manage-payroll-social-insurance-update'], $user->getSettingAdvancedPermissionFlow($user->client_id));

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
     * Determine whether the user can update the formula.
     *
     * @param  User  $user
     * @param  \App\Formula  $formula
     *
     * @return mixed
     */
    public function update(User $user, SocialSecurityProfile $socialSecurityProfile)
    {
        $role = $user->getRole();

        if (!$user->isInternalUser())
        {
            if ($user->client_id != $socialSecurityProfile->client_id) return false;
            // Check permission

            return $user->checkHavePermission(['manage-social'], ['advanced-manage-payroll', 'advanced-manage-payroll-social-insurance-update'], $user->getSettingAdvancedPermissionFlow($user->client_id));

        } else {

            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($socialSecurityProfile->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($socialSecurityProfile->client_id)) {
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
    public function delete(User $user, SocialSecurityProfile $socialSecurityProfile)
    {
        $role = $user->getRole();

        if (!$user->isInternalUser())
        {
            if ($user->client_id != $socialSecurityProfile->client_id) return false;
            // Check permission

            return $user->checkHavePermission(['manage-social'], ['advanced-manage-payroll', 'advanced-manage-payroll-social-insurance-delete'], $user->getSettingAdvancedPermissionFlow($user->client_id));

        } else {

            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($socialSecurityProfile->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($socialSecurityProfile->client_id)) {
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
    public function restore(User $user, SocialSecurityProfile $socialSecurityProfile)
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
    public function forceDelete(User $user, SocialSecurityProfile $socialSecurityProfile)
    {
        return true;
    }

    public function upload(User $user, SocialSecurityProfile $socialSecurityProfile)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id == $socialSecurityProfile->client_id;
        } else {
            if ($user->iGlocalEmployee->isAssignedFor($socialSecurityProfile->client_id)) {
                return true;
            }
            return false;
        }
    }
}
