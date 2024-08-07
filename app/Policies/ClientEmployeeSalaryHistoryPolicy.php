<?php

namespace App\Policies;

use App\Models\ClientEmployee;
use App\Models\ClientEmployeeSalaryHistory;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;
use Illuminate\Auth\Access\Response;

class ClientEmployeeSalaryHistoryPolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-payroll';

    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function viewAny(User $user, ClientEmployeeSalaryHistory $clientEmployeeSalaryHistory, array $injected)
    {
        if (!$user->isInternalUser()) {
            // Check permission
             return $user->checkHavePermission([$this->managerPermission], ['advanced-manage-payroll-salary-history-read'], $user->getSettingAdvancedPermissionFlow($user->client_id)) || $user->getRole() !== Constant::ROLE_CLIENT_STAFF;
        } else {
            return true;
        }
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param array $injected
     * @return Response|bool
     */
    public function view(User $user, array $injected)
    {

        if (!$user->isInternalUser()) {
            if ($injected['where']['AND'][0]['value'] == $user->clientEmployee->id) {
                return true;
            }
            // Check permission
             return $user->checkHavePermission([$this->managerPermission, 'manage-employee-payroll'], ['advanced-manage-payroll-salary-history-read', 'advanced-manage-payroll-list-read'], $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {
            return true;
        }
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            // Check permission
            return $user->checkHavePermission([$this->managerPermission], ['advanced-manage-payroll-salary-history-create'], $user->getSettingAdvancedPermissionFlow($user->client_id)) || $user->getRole() !== Constant::ROLE_CLIENT_STAFF;
        } else {
            $clientEmployee = ClientEmployee::find($injected['client_employee_id']);
            // Check permission
            return $user->checkHavePermission([], [], false, $clientEmployee->client_id);
        }
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param ClientEmployeeSalaryHistory $clientEmployeeSalaryHistory
     * @return Response|bool
     */
    public function update(User $user, ClientEmployeeSalaryHistory $clientEmployeeSalaryHistory, array $injected)
    {
        if (!$user->isInternalUser()) {
            // Check permission
            return $user->checkHavePermission([$this->managerPermission], ['advanced-manage-payroll-salary-history-update'], $user->getSettingAdvancedPermissionFlow($user->client_id)) || $user->getRole() !== Constant::ROLE_CLIENT_STAFF;
        } else {
            $clientEmployee = ClientEmployee::find($injected['client_employee_id']);
            // Check permission
            return $user->checkHavePermission([], [], false, $clientEmployee->client_id);
        }
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param ClientEmployeeSalaryHistory $clientEmployeeSalaryHistory
     * @return Response|bool
     */
    public function delete(User $user, ClientEmployeeSalaryHistory $clientEmployeeSalaryHistory, array $injected)
    {
        if (!$user->isInternalUser()) {
            return $user->checkHavePermission([$this->managerPermission], ['advanced-manage-payroll-salary-history-delete'], $user->getSettingAdvancedPermissionFlow($user->client_id)) || $user->getRole() !== Constant::ROLE_CLIENT_STAFF;
        } else {
            $clientEmployee = ClientEmployee::find($injected['client_employee_id']);
            // Check permission
            return $user->checkHavePermission([], [], false, $clientEmployee->client_id);
        }
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param ClientEmployeeSalaryHistory $clientEmployeeSalaryHistory
     * @return Response|bool
     */
    public function restore(User $user, ClientEmployeeSalaryHistory $clientEmployeeSalaryHistory, array $injected)
    {
        if (!$user->isInternalUser()) {
            return $user->hasDirectPermission($this->managerPermission) || $user->getRole() !== Constant::ROLE_CLIENT_STAFF;
        } else {
            $clientEmployee = ClientEmployee::find($injected['client_employee_id']);
            // Check permission
            return $user->checkHavePermission([], [], false, $clientEmployee->client_id);
        }
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param ClientEmployeeSalaryHistory $clientEmployeeSalaryHistory
     * @return Response|bool
     */
    public function forceDelete(User $user, ClientEmployeeSalaryHistory $clientEmployeeSalaryHistory, array $injected)
    {
        if (!$user->isInternalUser()) {
             return $user->checkHavePermission([$this->managerPermission], ['advanced-manage-payroll-salary-history-delete'], $user->getSettingAdvancedPermissionFlow($user->client_id)) || $user->getRole() !== Constant::ROLE_CLIENT_STAFF;
        } else {
            $clientEmployee = ClientEmployee::find($injected['client_employee_id']);
            // Check permission
            return $user->checkHavePermission([], [], false, $clientEmployee->client_id);
        }
    }
}
