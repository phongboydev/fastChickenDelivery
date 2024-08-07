<?php

namespace App\Policies;

use App\Models\Client;
use App\Support\Constant;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\User;
use App\Models\ClientEmployee;
use App\Models\IglocalEmployee;

class ClientPolicy extends BasePolicy
{

    private $managerPermission = 'manage_clients';

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
    public function view(User $user, ClientEmployee $clientEmployee)
    {
        //
    }

    /**
     * Determine whether the user can create client employees.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            return false;
        } else {

            return ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission($this->managerPermission) || $user->getRole() == Constant::ROLE_INTERNAL_LEADER);
        }
    }

    /**
     * @param User   $user
     * @param Client $client
     *
     * @return bool
     */
    public function update(User $user, Client $client)
    {
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    if ($user->client_id == $client->id) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        } else {

            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission($this->managerPermission)) {
                return true;
            }elseif($user->iGlocalEmployee->isAssignedFor($client->id) && !$client->is_active){
                return true;
            }else{
                return false;
            }
        }
    }

    /**
     * @param User   $user
     * @param Client $client
     *
     * @return bool
     */
    public function delete(User $user, Client $client)
    {
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            switch ($role) {
                default:
                    return false;
            }
        } else {
            return ($role == Constant::ROLE_INTERNAL_DIRECTOR);
        }
    }

    /**
     * Determine whether the user can restore the client employee.
     *
     * @param  User  $user
     * @param  \App\ClientEmployee  $clientEmployee
     *
     * @return mixed
     */
    public function restore(User $user, ClientEmployee $clientEmployee)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee.
     *
     * @param  User  $user
     * @param  \App\ClientEmployee  $clientEmployee
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientEmployee $clientEmployee)
    {
        //
    }
}
