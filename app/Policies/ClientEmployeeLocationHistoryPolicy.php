<?php

namespace App\Policies;

use App\Exceptions\HumanErrorException;
use App\Support\ClientHelper;
use App\Support\Constant;
use App\User;
use App\Models\ClientEmployeeLocationHistory;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientEmployeeLocationHistoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can create client employee overtime requests.
     *
     * @param  User  $user
     * @param  array  $injected
     *
     * @return bool
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            if (empty($injected['client_id'])) return false;
            return $user->clientEmployee->id == $injected['client_employee_id']; // can only create for self
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can update the client employee overtime request.
     *
     * @param  User  $user
     * @param  \App\Models\ClientEmployeeLocationHistory  $model
     *
     * @return bool
     */
    public function update(User $user, ClientEmployeeLocationHistory $model)
    {
        $role = $user->getRole();
        if (!$user->isInternalUser()) {
            if (empty($injected['client_id'])) return false;
            return $user->clientEmployee->id == $injected['client_employee_id']; // can only create for self
        } else {
            return false;
        }
        return $user->clientEmployee->id == $model->client_employee_id; // can only update self
    }

    /**
     * Determine whether the user can delete the client employee overtime request.
     *
     * @param  User  $user
     * @param  \App\Models\ClientEmployeeLocationHistory  $model
     *
     * @return mixed
     */
    public function delete(User $user, ClientEmployeeLocationHistory $model)
    {
        if ($user->is_internal) {
            return false; // internal user cannot create
        }
        return $user->clientEmployee->id == $model->client_employee_id; // can only delete self
    }

    public function upload(User $user, ClientEmployeeLocationHistory $model)
    {
        if ($user->is_internal) {
            return false; // internal user cannot create
        }
        return true;
    }

}
