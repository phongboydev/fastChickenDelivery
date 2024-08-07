<?php

namespace App\Policies;

use App\Models\PayrollAccountantExportTemplate;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class PayrollAccountantExportTemplatePolicy
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
    public function view(User $user, PayrollAccountantExportTemplate $payrollAccountantExportTemplate)
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
        if (!$user->isInternalUser()) 
        {
            if ($user->client_id == $injected['client_id'] && $user->hasDirectPermission('manage-payroll')) {
                return true;
            }
            
            return false;
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
    public function update(User $user, PayrollAccountantExportTemplate $payrollAccountantExportTemplate)
    {
        if (!$user->isInternalUser()) 
        {
            if ($user->client_id == $payrollAccountantExportTemplate->client_id && $user->hasDirectPermission('manage-payroll')) {
                return true;
            }
            
            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($payrollAccountantExportTemplate->client_id)) {
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
    public function delete(User $user, PayrollAccountantExportTemplate $payrollAccountantExportTemplate)
    {
        if (!$user->isInternalUser()) 
        {
            if ($user->client_id == $payrollAccountantExportTemplate->client_id && $user->hasDirectPermission('manage-payroll')) {
                return true;
            }
            
            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($payrollAccountantExportTemplate->client_id)) {
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
    public function restore(User $user, PayrollAccountantExportTemplate $payrollAccountantExportTemplate)
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
    public function forceDelete(User $user, PayrollAccountantExportTemplate $payrollAccountantExportTemplate)
    {
        return true;
    }

    public function upload(User $user, PayrollAccountantExportTemplate $payrollAccountantExportTemplate) 
    {
        if (!$user->isInternalUser()) 
        {
            if ($user->client_id == $payrollAccountantExportTemplate->client_id && $user->hasDirectPermission('manage-payroll')) {
                return true;
            }
            
            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($payrollAccountantExportTemplate->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }
}
