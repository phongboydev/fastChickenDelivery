<?php

namespace App\Policies;

use App\Models\ClientDepartment;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientDepartmentPolicy
{
    use HandlesAuthorization;

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
     * @param  \App\$ClientDepartment $clientDepartment
     *
     * @return mixed
     */
    public function view(User $user, ClientDepartment $clientDepartment)
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
        return true;
    }

    /**
     * Determine whether the user can update the client custom variable.
     *
     * @param  User  $user
     * @param  \App\$ClientDepartment  $clientDepartment
     *
     * @return mixed
     */
    public function update(User $user, ClientDepartment $clientDepartment)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientDepartment  $clientDepartment
     *
     * @return mixed
     */
    public function delete(User $user, ClientDepartment $clientDepartment)
    {
        return true;
    }

    /**
     * Determine whether the user can restore the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientDepartment  $clientDepartment
     *
     * @return mixed
     */
    public function restore(User $user, ClientDepartment $clientDepartment)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientDepartment  $clientDepartment
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientDepartment $clientDepartment)
    {
        //
    }
}
