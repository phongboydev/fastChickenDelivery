<?php

namespace App\Policies;

use App\Models\ClientEmployeeDependent;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientEmployeeDependentPolicy
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
     * @param  \App\Models\ClientEmployeeDependent  $clientEmployeeDependent
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, ClientEmployeeDependent $clientEmployeeDependent)
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
     * @param  \App\Models\ClientEmployeeDependent  $clientEmployeeDependent
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, ClientEmployeeDependent $clientEmployeeDependent)
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependent  $clientEmployeeDependent
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, ClientEmployeeDependent $clientEmployeeDependent)
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependent  $clientEmployeeDependent
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, ClientEmployeeDependent $clientEmployeeDependent)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependent  $clientEmployeeDependent
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, ClientEmployeeDependent $clientEmployeeDependent)
    {
        //
    }

    public function upload(User $user, ClientEmployeeDependent $clientEmployeeDependent)
    {
        return true;
    }
}
