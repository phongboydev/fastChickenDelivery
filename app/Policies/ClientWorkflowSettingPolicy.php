<?php

namespace App\Policies;

use App\Exceptions\CustomException;
use App\Models\ClientWorkflowSetting;
use App\Support\ClientHelper;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientWorkflowSettingPolicy
{
    use HandlesAuthorization;

    private const ALLOWING_SETTING_LIST_FOR_CUSTOMER = [
        'number_of_flexible_request_in_month' => [
            'role' => [Constant::ROLE_CLIENT_MANAGER]
        ],
        'client_employee_limit' => [
            'role' => [Constant::ROLE_CLIENT_MANAGER]
        ],
        'template_export' => [
            'permission' => [
                'normal'  => ["manage-timesheet"],
                'advanced' => ["advanced-manage-timesheet-working-update"]
            ]
        ],
        'client_id' => [],
        'id' => [],

    ];

    /**
     * Determine whether the user can view any client workflow settings.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the client workflow setting.
     *
     * @param  \App\User  $user
     * @param  \App\ClientWorkflowSetting  $clientWorkflowSetting
     * @return mixed
     */
    public function view(User $user, ClientWorkflowSetting $clientWorkflowSetting)
    {
        //
    }

    /**
     * Determine whether the user can create client workflow settings.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        //
    }

    /**
     * Determine whether the user can update the client workflow setting.
     *
     * @param  \App\User  $user
     * @param  \App\ClientWorkflowSetting  $clientWorkflowSetting
     * @param array $injected
     * @return mixed
     */
    public function update(User $user, ClientWorkflowSetting $clientWorkflowSetting, $injected)
    {
        $role = $user->getRole();
        if (!$user->isInternalUser()) {

            if (isset($injected['client_employee_limit'])
                && !ClientHelper::validateHeadcountChange($user->client_id, $injected['client_employee_limit'])) {
                throw new CustomException(
                    'error.reduce_update_failed',
                    'ValidationException'
                );
            }

            if (
                array_diff_key($injected, self::ALLOWING_SETTING_LIST_FOR_CUSTOMER)
                || $user->client_id != $clientWorkflowSetting->client_id
            ) {
                return false;
            }

            foreach (self::ALLOWING_SETTING_LIST_FOR_CUSTOMER as $key => $setting) {
                if (isset($injected[$key])) {
                    if (!empty($setting['role']) && !in_array($role, $setting['role'])) {
                        return false;
                    }
                    if (!empty($setting['permission'])
                        && !$user->checkHavePermission($setting['permission']['normal'], $setting['permission']['advanced'], $user->getSettingAdvancedPermissionFlow())) {
                        return false;
                    }
                }
            }

            return true;
        } else {

            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            } else {
                if ($user->iGlocalEmployee->isAssignedFor($clientWorkflowSetting->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the client workflow setting.
     *
     * @param  \App\User  $user
     * @param  \App\ClientWorkflowSetting  $clientWorkflowSetting
     * @return mixed
     */
    public function delete(User $user, ClientWorkflowSetting $clientWorkflowSetting)
    {
        //
    }

    /**
     * Determine whether the user can restore the client workflow setting.
     *
     * @param  \App\User  $user
     * @param  \App\ClientWorkflowSetting  $clientWorkflowSetting
     * @return mixed
     */
    public function restore(User $user, ClientWorkflowSetting $clientWorkflowSetting)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client workflow setting.
     *
     * @param  \App\User  $user
     * @param  \App\ClientWorkflowSetting  $clientWorkflowSetting
     * @return mixed
     */
    public function forceDelete(User $user, ClientWorkflowSetting $clientWorkflowSetting)
    {
        //
    }
}
