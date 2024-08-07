<?php

namespace App\Policies;

use App\Models\ClientEmployeeCustomVariable;
use App\User;
use App\Models\ClientEmployee;
USE App\Models\ClientWorkflowSetting;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientEmployeeCustomVariablePolicy
{
    use HandlesAuthorization;

    private $clientManagerPermission = 'manage-payroll';

    /**
     * Determine whether the user can view any client employee custom variables.
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
     * Determine whether the user can view the client employee custom variable.
     *
     * @param User                               $user
     * @param  \App\ClientEmployeeCustomVariable $clientEmployeeCustomVariable
     *
     * @return mixed
     */
    public function view(User $user, ClientEmployeeCustomVariable $clientEmployeeCustomVariable)
    {
        //
    }

    /**
     * Determine whether the user can create client employee custom variables.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {

            $clientEmployee = (new ClientEmployee)->findClientEmployee($injected['client_employee_id']);

            $clientId = $clientEmployee['client_id'];

            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            return ($user->client_id == $clientId) && ( ($clientWorkflowSetting->enable_create_payroll && $user->hasDirectPermission($this->clientManagerPermission)) || $user->hasDirectPermission('manage-employee') );

        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($injected['client_employee_id'])) {
                    $clientEmployee = (new ClientEmployee)->findClientEmployee($injected['client_employee_id']);
                    if ($user->iGlocalEmployee->isAssignedFor($clientEmployee['client_id'])) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can update the client employee custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeCustomVariable  $clientEmployeeCustomVariable
     *
     * @return mixed
     */
    public function update(User $user, ClientEmployeeCustomVariable $clientEmployeeCustomVariable)
    {        
        if (!$user->isInternalUser()) {

            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();
            $clientId = $clientEmployeeCustomVariable->client->id;

            return ($user->client_id == $clientId) && ( ($clientWorkflowSetting->enable_create_payroll && $user->hasDirectPermission($this->clientManagerPermission)) || $user->hasDirectPermission('manage-employee') );
 
        } else {
            
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($clientEmployeeCustomVariable->clientEmployee->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the client employee custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeCustomVariable  $clientEmployeeCustomVariable
     *
     * @return mixed
     */
    public function delete(User $user, ClientEmployeeCustomVariable $clientEmployeeCustomVariable)
    {
        if (!$user->isInternalUser()) {
            
            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();
            $clientId = $clientEmployeeCustomVariable->client->id;

            return ($user->client_id == $clientId) && ( ($clientWorkflowSetting->enable_create_payroll && $user->hasDirectPermission($this->clientManagerPermission)) || $user->hasDirectPermission('manage-employee') );
            
        } else {
            
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($clientEmployeeCustomVariable->clientEmployee->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the client employee custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeCustomVariable  $clientEmployeeCustomVariable
     *
     * @return mixed
     */
    public function restore(User $user, ClientEmployeeCustomVariable $clientEmployeeCustomVariable)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeCustomVariable  $clientEmployeeCustomVariable
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientEmployeeCustomVariable $clientEmployeeCustomVariable)
    {
        //
    }
}
