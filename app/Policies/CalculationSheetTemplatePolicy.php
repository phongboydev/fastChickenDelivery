<?php

namespace App\Policies;

use App\Models\CalculationSheetTemplate;
use App\Models\ClientWorkflowSetting;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class CalculationSheetTemplatePolicy
{
    use HandlesAuthorization;

    private $clientManagerPermission = 'manage-payroll';

    /**
     * Determine whether the user can view any calculation sheet templates.
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
     * Determine whether the user can view the calculation sheet template.
     *
     * @param User                           $user
     * @param  \App\CalculationSheetTemplate $calculationSheetTemplate
     *
     * @return mixed
     */
    public function view(User $user, CalculationSheetTemplate $calculationSheetTemplate)
    {
        //
    }

    /**
     * Determine whether the user can create calculation sheet templates.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
           
            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            if ( $clientWorkflowSetting->enable_create_payroll && ($user->client_id == $injected['client_id'] && $user->hasDirectPermission($this->clientManagerPermission))) {
                return true;
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
     * Determine whether the user can update the calculation sheet template.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetTemplate  $calculationSheetTemplate
     *
     * @return mixed
     */
    public function update(User $user, CalculationSheetTemplate $calculationSheetTemplate)
    {
        if (!$user->isInternalUser()) 
        {
            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            if ( $clientWorkflowSetting->enable_create_payroll && ($user->client_id == $calculationSheetTemplate->client_id && $user->hasDirectPermission($this->clientManagerPermission))) {
                return true;
            }
            
            return false;
        } else {
            
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($calculationSheetTemplate->client_id)) {
                    return true;
                }
                return false;
            }

        }
    }

    /**
     * Determine whether the user can delete the calculation sheet template.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetTemplate  $calculationSheetTemplate
     *
     * @return mixed
     */
    public function delete(User $user, CalculationSheetTemplate $calculationSheetTemplate)
    {
        if (!$user->isInternalUser()) {
            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            if ( $clientWorkflowSetting->enable_create_payroll && ($user->client_id == $calculationSheetTemplate->client_id && $user->hasDirectPermission($this->clientManagerPermission))) {
                return true;
            }
            
            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($calculationSheetTemplate->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can enable the calculation sheet template.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetTemplate  $calculationSheetTemplate
     *
     * @return mixed
     */
    public function enable(User $user, CalculationSheetTemplate $calculationSheetTemplate)
    {
        if (!$user->isInternalUser()) {
            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            if ( $clientWorkflowSetting->enable_create_payroll && ($user->client_id == $calculationSheetTemplate->client_id && $user->hasDirectPermission($this->clientManagerPermission))) {
                return true;
            }
            
            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($calculationSheetTemplate->client_id)) {
                    return true;
                }
                return false;
            }

        }
    }

    /**
     * Determine whether the user can restore the calculation sheet template.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetTemplate  $calculationSheetTemplate
     *
     * @return mixed
     */
    public function restore(User $user, CalculationSheetTemplate $calculationSheetTemplate)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the calculation sheet template.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetTemplate  $calculationSheetTemplate
     *
     * @return mixed
     */
    public function forceDelete(User $user, CalculationSheetTemplate $calculationSheetTemplate)
    {
        //
    }
}
