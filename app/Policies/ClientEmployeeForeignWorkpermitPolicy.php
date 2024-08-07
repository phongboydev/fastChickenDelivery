<?php

namespace App\Policies;

use App\Models\ClientEmployeeForeignWorkpermit;
use App\Models\ClientEmployee;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientEmployeeForeignWorkpermitPolicy
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
     * @param  \App\ClientEmployeeForeignWorkpermit $clientEmployeeForeignWorkpermit
     *
     * @return mixed
     */
    public function view(User $user, ClientEmployeeForeignWorkpermit $clientEmployeeForeignWorkpermit)
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
     * @param  \App\ClientEmployeeForeignWorkpermit  $clientCustomVariable
     *
     * @return mixed
     */
    public function update(User $user, ClientEmployeeForeignWorkpermit $clientEmployeeForeignWorkpermit)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeForeignWorkpermit  $clientEmployeeForeignWorkpermit
     *
     * @return mixed
     */
    public function delete(User $user, ClientEmployeeForeignWorkpermit $clientEmployeeForeignWorkpermit)
    {
        return true;
    }

    /**
     * Determine whether the user can restore the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeForeignWorkpermit  $clientEmployeeForeignWorkpermit
     *
     * @return mixed
     */
    public function restore(User $user, ClientEmployeeForeignWorkpermit $clientEmployeeForeignWorkpermit)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeForeignWorkpermit  $clientEmployeeForeignWorkpermit
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientEmployeeForeignWorkpermit $clientEmployeeForeignWorkpermit)
    {
        //
    }

    public function upload(User $user, ClientEmployeeForeignWorkpermit $clientEmployeeForeignWorkpermit)
    {
        return true;
    }
}
