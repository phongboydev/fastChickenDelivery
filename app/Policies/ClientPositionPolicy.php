<?php

namespace App\Policies;

use App\User;
use App\Models\ClientPosition;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPositionPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
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
     * @param  \App\$ClientPosition $ClientPosition
     *
     * @return mixed
     */
    public function view(User $user, ClientPosition $ClientPosition)
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
     * @param  \App\$ClientPosition  $ClientPosition
     *
     * @return mixed
     */
    public function update(User $user, ClientPosition $ClientPosition)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientPosition  $ClientPosition
     *
     * @return mixed
     */
    public function delete(User $user, ClientPosition $ClientPosition)
    {
        return true;
    }

    /**
     * Determine whether the user can restore the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientPosition  $ClientPosition
     *
     * @return mixed
     */
    public function restore(User $user, ClientPosition $ClientPosition)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client custom variable.
     *
     * @param  User  $user
     * @param  \App\ClientPosition  $ClientPosition
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientPosition $ClientPosition)
    {
        //
    }
}
