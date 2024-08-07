<?php

namespace App\Policies;

use App\Models\ClientEmployee;
use App\User;
use App\Models\ClientEmployeePositionHistory;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientEmployeePositionHistoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user, ClientEmployeePositionHistory $clientEmployeePositionHistory, array $injected)
    {
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeePositionHistory  $clientEmployeePositionHistory
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, ClientEmployeePositionHistory $clientEmployeePositionHistory)
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user, array $injected)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeePositionHistory  $clientEmployeePositionHistory
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, ClientEmployeePositionHistory $clientEmployeePositionHistory, array $injected)
    {
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeePositionHistory  $clientEmployeePositionHistory
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, ClientEmployeePositionHistory $clientEmployeePositionHistory, array $injected)
    {
    }
}
