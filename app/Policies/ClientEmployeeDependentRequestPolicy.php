<?php

namespace App\Policies;

use App\Models\ClientEmployee;
use App\Models\ClientEmployeeDependentRequest;
use App\Support\Constant;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientEmployeeDependentRequestPolicy
{
    use HandlesAuthorization;
    private $clientManagerPermission = 'manage-employee';

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
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
        //
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user, array $injected)
    {
        return (!$user->isInternalUser() && $user->client_id === $injected['client_id'] && $user->hasAnyPermission([$this->clientManagerPermission]));
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
        if ($user->isInternalUser()) {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients') || $user->iGlocalEmployee->isAssignedFor($clientEmployeeDependentRequest->client_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
        return (!$user->isInternalUser() && $user->client_id === $clientEmployeeDependentRequest->client_id && $clientEmployeeDependentRequest->processing === 'submitted' && $user->hasAnyPermission([$this->clientManagerPermission]));
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
        return (!$user->isInternalUser() && $user->client_id === $clientEmployeeDependentRequest->client_id && $clientEmployeeDependentRequest->processing === 'submitted' && $user->hasAnyPermission([$this->clientManagerPermission]));
    }
}
