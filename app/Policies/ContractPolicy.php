<?php

namespace App\Policies;

use App\User;
use App\Models\Contract;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContractPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any client employee overtime requests.
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
     * Determine whether the user can view the client employee overtime request.
     *
     * @param User                          $user
     * @param Contract $contract
     *
     * @return mixed
     */
    public function view(User $user, Contract $contract)
    {
        //
    }

    /**
     * Determine whether the user can create client employee overtime requests.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        return $this->hasPermission($user, $injected);
    }

    /**
     * Determine whether the user can update the client employee overtime request.
     *
     * @param User                          $user
     * @param Contract $contract
     *
     * @return mixed
     */
    public function update(User $user, Contract $contract)
    {
        return $this->hasPermission($user, $contract->toArray());
    }

    /**
     * Determine whether the user can delete the client employee overtime request.
     *
     * @param  User  $user
     * @param Contract  $contract
     *
     * @return mixed
     */
    public function delete(User $user, Contract $contract)
    {
        return $this->hasPermission($user, $contract->toArray());
    }

    public function hasPermission(User $user, array $input)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id != $input['client_id']) {
                return false;
            }

            if (!$user->hasAnyPermission(['manage-contract'])) {
                return false;
            }

            return true;
        } else {

            return true;
        }
    }

    public function upload(User $user, $model)
    {
        return true;
    }
}
