<?php

namespace App\Policies;

use App\Models\SocialSecurityProfileRequest;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class SocialSecurityProfileRequestPolicy
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
    public function view(User $user, SocialSecurityProfileRequest $formula)
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
            if (!empty($injected['client_id']) && $user->client_id != $injected['client_id']) return false;
            // Check permission
            return $user->checkHavePermission(['manage-social'], ['advanced-manage-payroll-social-declaration-create'], $user->getSettingAdvancedPermissionFlow($user->client_id));
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
    public function update(User $user, SocialSecurityProfileRequest $socialSecurityProfile)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $socialSecurityProfile->client_id) return false;
            // Check permission
             return $user->checkHavePermission(['manage-social'], ['advanced-manage-payroll-social-declaration-update'], $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {
            if(empty($socialSecurityProfile->client_id)) return false;
            // Check permission
            return $user->checkHavePermission([], [], false, $socialSecurityProfile->client_id);
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
    public function delete(User $user, SocialSecurityProfileRequest $socialSecurityProfile)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $socialSecurityProfile->client_id) return false;
            // Check permission
            return $user->checkHavePermission(['manage-social'], ['advanced-manage-payroll-social-declaration-delete'], $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {
            if(empty($socialSecurityProfile->client_id)) return false;
            // Check permission
            return $user->checkHavePermission([], [], false, $socialSecurityProfile->client_id);
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
    public function restore(User $user, SocialSecurityProfileRequest $socialSecurityProfile)
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
    public function forceDelete(User $user, SocialSecurityProfileRequest $socialSecurityProfile)
    {
    }

    public function upload(User $user, SocialSecurityProfileRequest $socialSecurityProfileRequest)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id == $socialSecurityProfileRequest->client_id;
        } else {
            if (empty($socialSecurityProfileRequest->client_id)) return false;
            // Check permission
            return $user->checkHavePermission([], [], false, $socialSecurityProfileRequest->client_id);
        }
    }
}
