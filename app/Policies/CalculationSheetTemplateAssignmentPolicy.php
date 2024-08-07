<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\User;
use App\Models\CalculationSheetTemplateAssignment;
use App\Models\ClientWorkflowSetting;
use App\Models\ClientEmployee;
use App\Support\Constant;

class CalculationSheetTemplateAssignmentPolicy extends BasePolicy
{
    use HandlesAuthorization;

    private $clientManagerPermission = 'manage-payroll';

    /**
     * Determine whether the user can view any client employees.
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
     * Determine whether the user can view the client employee.
     *
     * @param User                 $user
     * @param  \App\ClientEmployee $clientEmployee
     *
     * @return mixed
     */
    public function view(User $user, CalculationSheetTemplateAssignment $clientEmployee)
    {
        //
    }

    /**
     * Determine whether the user can create client employees.
     *
     * @param  User $user
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
                if ($user->iGlocalEmployee->isAssignedFor($injected['client_id'])) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can update the client employee.
     *
     * @param  User $user
     * @param  \App\ClientEmployee $clientAssignment
     *
     * @return mixed
     */
    public function update(User $user, CalculationSheetTemplateAssignment $clientAssignment)
    {
        if (!$user->isInternalUser()) 
        {
            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            if ( $clientWorkflowSetting->enable_create_payroll && ($user->client_id == $clientAssignment->client_id && $user->hasDirectPermission($this->clientManagerPermission))) {
                return true;
            }
            
            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($clientAssignment->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the client employee.
     *
     * @param  User $user
     * @param  \App\ClientEmployee $clientAssignment
     *
     * @return mixed
     */
    public function delete(User $user, CalculationSheetTemplateAssignment $clientAssignment)
    {
        if (!$user->isInternalUser()) 
        {
            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            if ( $clientWorkflowSetting->enable_create_payroll && ($user->client_id == $clientAssignment->client_id && $user->hasDirectPermission($this->clientManagerPermission))) {
                return true;
            }
            
            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($clientAssignment->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the client employee.
     *
     * @param  User $user
     * @param  \App\ClientEmployee $clientEmployee
     *
     * @return mixed
     */
    public function restore(User $user, CalculationSheetTemplateAssignment $clientEmployee)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee.
     *
     * @param  User $user
     * @param  \App\ClientEmployee $clientEmployee
     *
     * @return mixed
     */
    public function forceDelete(User $user, CalculationSheetTemplateAssignment $clientEmployee)
    {
        //
    }
}
