<?php

namespace App\Policies;

use App\Models\ClientSettingConditionCompare;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Auth;

class ClientSettingConditionComparePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\User  $user
     * @return Response|bool
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientSettingConditionCompare  $clientSettingConditionCompare
     * @return Response|bool
     */
    public function view(User $user, ClientSettingConditionCompare $clientSettingConditionCompare)
    {
        //
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return Response|bool
     */
    public function create(User $user, array $injected)
    {
       return $this->checkPermission($injected['client_id']);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientSettingConditionCompare  $clientSettingConditionCompare
     * @return Response|bool
     */
    public function update(User $user, ClientSettingConditionCompare $clientSettingConditionCompare)
    {
        return $this->checkPermission($clientSettingConditionCompare['client_id']);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientSettingConditionCompare  $clientSettingConditionCompare
     * @return Response|bool
     */
    public function delete(User $user, ClientSettingConditionCompare $clientSettingConditionCompare)
    {
        return $this->checkPermission($clientSettingConditionCompare['client_id']);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientSettingConditionCompare  $clientSettingConditionCompare
     * @return Response|bool
     */
    public function restore(User $user, ClientSettingConditionCompare $clientSettingConditionCompare)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientSettingConditionCompare  $clientSettingConditionCompare
     * @return Response|bool
     */
    public function forceDelete(User $user, ClientSettingConditionCompare $clientSettingConditionCompare)
    {
        //
    }

    public function checkPermission($clientId) {
        $user = Auth::user();
        if (!$user->isInternalUser()) {
            if ($user->client_id != $clientId) {
                logger(self::class . ": ClientID not match");
                return false;
            }
            $normalPermission = ['manage-payroll'];
            $advancedPermission = ['manage-payroll'];
            // Check permission
            return $user->checkHavePermission($normalPermission, $advancedPermission, $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {
            return $user->checkHavePermission([], [], false, $clientId);
        }
    }
}
