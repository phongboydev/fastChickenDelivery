<?php

namespace App\Policies;

use App\Models\ClientEmployeeContract;
use App\Models\ClientEmployee;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientEmployeeContractPolicy
{
    use HandlesAuthorization;

    private $clientManagerPermission = 'manage-contract';

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
     * @param  \App\ClientEmployeeContract $clientEmployeeContract
     *
     * @return mixed
     */
    public function view(User $user, ClientEmployeeContract $clientEmployeeContract)
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
        
        $clientEmployee = ClientEmployee::select('*')->where('id', $injected['client_employee_id'])->first();

        if( empty($clientEmployee) ) return false;

        $client_id = $clientEmployee->client_id;

        if (!$user->isInternalUser()) {
            
            if ($user->client_id == $client_id && $user->hasAnyPermission([$this->clientManagerPermission, 'manage-employee'])) {
                return true;
            }
            
            return false;

        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($client_id)) {
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
     * @param  \App\ClientEmployeeContract  $clientCustomVariable
     *
     * @return mixed
     */
    public function update(User $user, ClientEmployeeContract $clientEmployeeContract)
    {

        $clientEmployee = ClientEmployee::select('*')->where('id', $clientEmployeeContract->client_employee_id)->first();

        if( empty($clientEmployee) ) return false;

        $client_id = $clientEmployee->client_id;

        if (!$user->isInternalUser()) {
            
            if ($user->client_id == $client_id && $user->hasAnyPermission([$this->clientManagerPermission, 'manage-employee'])) {
                return true;
            }
            
            return false;

        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($client_id)) {
                        return true;
                    }
                }
                return false;
            }
        }

    }

    /**
     * Determine whether the user can delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeContract  $clientEmployeeContract
     *
     * @return mixed
     */
    public function delete(User $user, ClientEmployeeContract $clientEmployeeContract)
    {
        
        $clientEmployee = ClientEmployee::select('*')->where('id', $clientEmployeeContract->client_employee_id)->first();

        if( empty($clientEmployee) ) return false;

        $client_id = $clientEmployee->client_id;

        if (!$user->isInternalUser()) {
            if ($user->client_id == $client_id && $user->hasAnyPermission([$this->clientManagerPermission, 'manage-employee'])) {
                return true;
            }
    
            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($client_id)) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeContract  $clientEmployeeContract
     *
     * @return mixed
     */
    public function restore(User $user, ClientEmployeeContract $clientEmployeeContract)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeContract  $clientEmployeeContract
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientEmployeeContract $clientEmployeeContract)
    {
        //
    }

    public function upload(User $user, ClientEmployeeContract $clientEmployeeContract)
    {
        $clientEmployee = ClientEmployee::select('*')->where('id', $clientEmployeeContract->client_employee_id)->first();

        if( empty($clientEmployee) ) return false;

        $client_id = $clientEmployee->client_id;

        if (!$user->isInternalUser()) {
            if ($user->client_id == $client_id && $user->hasAnyPermission([$this->clientManagerPermission, 'manage-employee'])) {
                return true;
            }
    
            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($client_id)) {
                    return true;
                }
                
                return false;
            }
        }
    }
}
