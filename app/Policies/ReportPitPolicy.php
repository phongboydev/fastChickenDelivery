<?php

namespace App\Policies;

use App\Models\ReportPit;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ReportPitPolicy
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
    public function view(User $user, ReportPit $reportPayroll)
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
        // return true;
        if (!$user->isInternalUser())
        {
            if ($user->client_id != $injected['client_id']) return false;

            return $user->checkHavePermission(['manage-payroll'], ['advanced-manage-payroll', 'advanced-manage-payroll-list-delete'], $user->getSettingAdvancedPermissionFlow());

        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($injected['client_id'])) {
                    return true;
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
    public function update(User $user, ReportPit $reportPayroll)
    {
        // return true;
        if (!$user->isInternalUser())
        {
            if ($user->client_id != $reportPayroll->client_id) return false;

            return $user->checkHavePermission(['manage-payroll'], ['advanced-manage-payroll', 'advanced-manage-payroll-list-delete'], $user->getSettingAdvancedPermissionFlow());

        } else {
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($reportPayroll->client_id)) {
                    return true;
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
    public function delete(User $user, ReportPit $reportPayroll)
    {
        if (!$user->isInternalUser())
        {
            if ($user->client_id != $reportPayroll->client_id) return false;

            return $user->checkHavePermission(['manage-payroll'], ['advanced-manage-payroll', 'advanced-manage-payroll-list-delete'], $user->getSettingAdvancedPermissionFlow());

        } else {
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($reportPayroll->client_id)) {
                    return true;
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
    public function restore(User $user, ReportPit $reportPayroll)
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
    public function forceDelete(User $user, ReportPit $reportPayroll)
    {
        return true;
    }
}
