<?php

namespace App\Policies;

use App\Models\ClientEmployeeForeignVisa;
use App\Models\ClientEmployee;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientEmployeeForeignVisaPolicy
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
     * @param  \App\ClientEmployeeForeignVisa $clientEmployeeForeignVisa
     *
     * @return mixed
     */
    public function view(User $user, ClientEmployeeForeignVisa $clientEmployeeForeignVisa)
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
     * @param  \App\ClientEmployeeForeignVisa  $clientCustomVariable
     *
     * @return mixed
     */
    public function update(User $user, ClientEmployeeForeignVisa $clientEmployeeForeignVisa)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeForeignVisa  $clientEmployeeForeignVisa
     *
     * @return mixed
     */
    public function delete(User $user, ClientEmployeeForeignVisa $clientEmployeeForeignVisa)
    {
        return true;
    }

    /**
     * Determine whether the user can restore the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeForeignVisa  $clientEmployeeForeignVisa
     *
     * @return mixed
     */
    public function restore(User $user, ClientEmployeeForeignVisa $clientEmployeeForeignVisa)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientEmployeeForeignVisa  $clientEmployeeForeignVisa
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientEmployeeForeignVisa $clientEmployeeForeignVisa)
    {
        //
    }

    public function upload(User $user, ClientEmployeeForeignVisa $clientEmployeeForeignVisa)
    {
        return true;
    }
}
