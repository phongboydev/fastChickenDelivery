<?php

namespace App\Policies;

use App\Exceptions\HumanErrorException;
use App\Support\ClientHelper;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\User;
use App\Models\ClientEmployee;
use App\Models\IglocalEmployee;
use App\Models\IglocalAssignment;
use App\Support\Constant;

class ClientEmployeePolicy extends BasePolicy
{
    use HandlesAuthorization;

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
    public function view(User $user, ClientEmployee $clientEmployee)
    {
        //
    }

    /**
     * Determine whether the user can create client employees.
     *
     * @param  User $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            if (empty($injected['client_id'])) return false;
            if (!ClientHelper::validateLimitActivatedEmployee($injected['client_id'])) {
                throw new HumanErrorException(__('error.exceeded_employee_limit'));
            }
            if ($user->client_id != $injected['client_id']) return false;

            // Check permission
            return $user->checkHavePermission(['manage-employee'], ['advanced-manage-employee-list-create'], $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {

            if (empty($injected['client_id'])) return false;

            if (!ClientHelper::validateLimitActivatedEmployee($injected['client_id'])) {
                throw new HumanErrorException(__('error.exceeded_employee_limit'));
            }

            // Check permission
            return $user->checkHavePermission([], [], false, $injected['client_id']);
        }
    }

    public function updateBasic(User $user, ClientEmployee $clientEmployee): bool
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $clientEmployee->client_id) return false;
            if ($clientEmployee->id == $user->clientEmployee->id) {
                return true;
            }
            // Check permission
            return $user->checkHavePermission(['manage-employee'], ['advanced-manage-employee-list-update'], $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {
            // Check permission
            return $user->checkHavePermission([], [], false, $clientEmployee->client_id);
        }
    }

    /**
     * Determine whether the user can update the client employee.
     *
     * @param  User $user
     * @param  \App\ClientEmployee $clientEmployee
     *
     * @return mixed
     */
    public function update(User $user, ClientEmployee $clientEmployee)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $clientEmployee->client_id) return false;
            if ($clientEmployee->id == $user->clientEmployee->id) {
                return true;
            }

            return $user->checkHavePermission(['manage-employee'], ['advanced-manage-employee-list-update'], $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {
            // Check permission
            return $user->checkHavePermission([], [], false, $clientEmployee->client_id);
        }
    }

    /**
     * Determine whether the user can delete the client employee.
     *
     * @param User $user
     * @param ClientEmployee $clientEmployee
     *
     * @return mixed
     */
    public function delete(User $user, ClientEmployee $clientEmployee)
    {
        if (!$user->isInternalUser()) {
           return false;
        } else {
            // Check permission
            return $user->checkHavePermission([], [], false, $clientEmployee->client_id);
        }
    }

    /**
     * Determine whether the user can restore the client employee.
     *
     * @param  User $user
     * @param  \App\ClientEmployee $clientEmployee
     *
     * @return mixed
     */
    public function restore(User $user, ClientEmployee $clientEmployee)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee.
     *
     * @param  User $user
     * @param  \App\ClientEmployee $clientEmployee
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientEmployee $clientEmployee)
    {
        //
    }

    public function upload(User $user, ClientEmployee $model)
    {
        return true;
    }
}
