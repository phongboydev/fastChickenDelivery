<?php

namespace App\Policies;

use App\Models\SocialSecurityClaim;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class SocialSecurityClaimPolicy
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
    public function view(User $user, SocialSecurityClaim $socialSecurityClaim)
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
        if (!$user->isInternalUser()) {
            if ($user->client_id != $injected['client_id']) return false;

            $normalPermissions = ["manage-social"];
            $advancedPermissions = ["advanced-manage-payroll", "advanced-manage-payroll-social-insurance-update"];

            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow())) {
                return true;
            } else {
                if ($user->clientEmployee->id == $injected['client_employee_id']) {
                    return true;
                }
            }

            return false;
        } else {
             if (empty($injected['client_id'])) return false;
            // Check permission
            return $user->checkHavePermission([], [], false, $injected['client_id']);
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
    public function update(User $user, SocialSecurityClaim $socialSecurityClaim)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $socialSecurityClaim->client_id) return false;
            if ($user->clientEmployee->id == $socialSecurityClaim->client_employee_id) return true;
            // Check permission
            return $user->checkHavePermission(['manage-social'], ['advanced-manage-payroll', 'advanced-manage-payroll-social-insurance-update'], $user->getSettingAdvancedPermissionFlow($user->client_id));

        } else {
            if (empty($socialSecurityClaim->client_id)) return false;
            // Check permission
            return $user->checkHavePermission([], [], false, $socialSecurityClaim->client_id);
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
    public function delete(User $user, SocialSecurityClaim $socialSecurityClaim)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $socialSecurityClaim->client_id) return false;

            if ($user->clientEmployee->id == $socialSecurityClaim->client_employee_id) return true;

            return $user->checkHavePermission(['manage-social'], ['advanced-manage-payroll', 'advanced-manage-payroll-social-insurance-delete'], $user->getSettingAdvancedPermissionFlow($user->client_id));

        } else {
            if (empty($socialSecurityClaim->client_id)) return false;
            // Check permission
            return $user->checkHavePermission([], [], false, $socialSecurityClaim->client_id);
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
    public function restore(User $user, SocialSecurityClaim $socialSecurityClaim)
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
    public function forceDelete(User $user, SocialSecurityClaim $socialSecurityClaim)
    {
        return true;
    }

    public function upload(User $user, SocialSecurityClaim $socialSecurityClaim)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $socialSecurityClaim->client_id) return false;

            if ($user->clientEmployee->id == $socialSecurityClaim->client_employee_id) return true;

            return $user->checkHavePermission(['manage-social'], ['advanced-manage-payroll', 'advanced-manage-payroll-social-insurance-update'], $user->getSettingAdvancedPermissionFlow($user->client_id));

        } else {
            return true;
        }
    }
}
