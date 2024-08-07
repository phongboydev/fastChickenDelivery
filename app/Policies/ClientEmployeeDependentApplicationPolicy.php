<?php

namespace App\Policies;

use App\Models\ClientEmployeeDependentApplication;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientEmployeeDependentApplicationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependentApplication  $clientEmployeeDependentApplication
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        //
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependentApplication  $clientEmployeeDependentApplication
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependentApplication  $clientEmployeeDependentApplication
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependentApplication  $clientEmployeeDependentApplication
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependentApplication  $clientEmployeeDependentApplication
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        //
    }

    public function upload(User $user, ClientEmployeeDependentApplication $clientEmployeeDependentApplication)
    {
        return true;
    }
}
