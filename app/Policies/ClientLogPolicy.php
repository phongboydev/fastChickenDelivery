<?php

namespace App\Policies;

use App\User;
use App\Models\ClientLog;

class ClientLogPolicy extends BasePolicy
{
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
    public function view(User $user, ClientLog $clientLog)
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
    public function create(User $user)
    {
        return $user->isInternalUser();
    }

    /**
     * @param User   $user
     * @param Client $client
     *
     * @return bool
     */
    public function update(User $user, ClientLog $clientLog)
    {
        return $user->isInternalUser();
    }

    /**
     * @param User   $user
     * @param Client $client
     *
     * @return bool
     */
    public function delete(User $user, ClientLog $clientLog)
    {
        return $user->isInternalUser();
    }

    /**
     * Determine whether the user can restore the client employee.
     *
     * @param  User  $user
     * @param  \App\ClientEmployee  $clientEmployee
     *
     * @return mixed
     */
    public function restore(User $user, ClientLog $clientLog)
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
    public function forceDelete(User $user, ClientLog $clientLog)
    {
        //
    }
}
