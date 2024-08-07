<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\User;
use App\Models\ClientAssignment;
use App\Models\ClientEmployee;
use App\Support\Constant;

class ClientAssignmentPolicy extends BasePolicy
{
    use HandlesAuthorization;

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
    public function view(User $user, ClientAssignment $clientEmployee)
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
        logger('ClientAssignmentPolicy::create BEGIN');

        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_HR:
                    $staff = ClientEmployee::find($injected['staff_id']);
                    if (!$staff || $staff->client_id != $user->client_id) {
                        logger(
                            'ClientAssignmentPolicy::create staff check failed',
                            ['staff_client_id' => $staff->client_id, 'client_id' => $user->client_id]
                        );
                        return false;
                    }
                    $leader = ClientEmployee::find($injected['leader_id']);
                    if (!$leader || $leader->client_id != $user->client_id) {
                        logger(
                            'ClientAssignmentPolicy::create leader check failed',
                            ['leader_client_id' => $staff->client_id, 'client_id' => $user->client_id]
                        );
                        return false;
                    }
                    return true;
                default:
                    return false;
            }
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
        logger('ClientAssignmentPolicy::create END');
    }

    /**
     * Determine whether the user can update the client employee.
     *
     * @param  User $user
     * @param  \App\ClientEmployee $clientAssignment
     *
     * @return mixed
     */
    public function update(User $user, ClientAssignment $clientAssignment)
    {
        $role = $user->getRole();

        if (!$user->isInternalUser()) {    
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_HR:
                    if ($user->client_id == $clientAssignment->client_id) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        } else {
            
            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
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
    public function delete(User $user, ClientAssignment $clientAssignment)
    {
        $role = $user->getRole();

        if (!$user->isInternalUser()) {    
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_HR:
                    if ($user->client_id == $clientAssignment->client_id) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        } else {
            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
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
    public function restore(User $user, ClientAssignment $clientEmployee)
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
    public function forceDelete(User $user, ClientAssignment $clientEmployee)
    {
        //
    }
}
