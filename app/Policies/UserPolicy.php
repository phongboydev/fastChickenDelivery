<?php

namespace App\Policies;

use App\Exceptions\HumanErrorException;
use App\Models\IglocalAssignment;
use App\Models\IglocalEmployee;
use App\User;
use App\Models\ClientEmployee;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;
use App\Support\ClientHelper;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
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
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param User $model
     *
     * @return mixed
     */
    public function view(User $user, User $model)
    {
        //
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        logger('UserPolicy::create: BEGIN');
        logger('UserPolicy::create: isInternalUser=' . $user->isInternalUser());
        logger('UserPolicy::create: role=' . $user->getRole());
        $isTryCreatingInternalUser = $this->isInternalUser($injected);
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            // Customer can not create internal user
            if ($isTryCreatingInternalUser) {
                return false;
            }

            if (!ClientHelper::validateLimitActivatedEmployee($user->client_id)) {
                throw new HumanErrorException(__('error.exceeded_employee_limit'));
            }

            return true;

            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    // Manager can create Employee's login account, but only for his/her own company
                    if ((!empty($injected['client_id'])) && ($user->client_id == $injected['client_id'])) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        } else {

            if( $isTryCreatingInternalUser
                && ($role == Constant::ROLE_INTERNAL_DIRECTOR
                    || ($user->hasDirectPermission('manage_iglocal_user'))
                    || $user->hasDirectPermission('manage_clients'))
               ) {
                return true;
            }else{
                // Internal staff can not create new internal user
                if ($isTryCreatingInternalUser) {
                    return false;
                }
                //Cannot create new employee if exceeding employee limit
                if (!ClientHelper::validateLimitActivatedEmployee($injected['client_id'])) {
                    throw new HumanErrorException(__('error.exceeded_employee_limit'));
                }
                // Staff can only create new User for his/her own assigned client
                /** @var IglocalEmployee $employee */
                $employee = $user->iGlocalEmployee;
                $hasAssignment = $employee->isAssignedFor($injected['client_id']);
                if ($hasAssignment) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User $user
     * @param  User $model
     *
     * @return mixed
     */
    public function update(User $user, User $model)
    {
        $isTryCreatingInternalUser = $this->isInternalUser($model);
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            // Customer can not modify internal user
            if ($isTryCreatingInternalUser) {
                return false;
            }
            if ($user->client_id !== $model->client_id) {
                return false;
            }
            if ($user->hasPermissionTo("manage-employee")) {
                return true;
            }
        } else {

            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_iglocal_user') || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                // Internal staff can not modify internal user
                if ($isTryCreatingInternalUser) {
                    return false;
                }
                // Staff can only modify User for his/her own assigned client
                /** @var IglocalEmployee $employee */
                $employee = $user->iGlocalEmployee;
                $hasAssignment = $employee->isAssignedFor($model->client_id);
                if ($hasAssignment) {
                    return true;
                }
                return false;
            }
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User  $user
     * @param  User $model
     *
     * @return mixed
     */
    public function delete(User $user, User $model)
    {
        $role = $user->getRole();

        $isTryCreatingInternalUser = $this->isInternalUser($model);
        if (!$user->isInternalUser()) {
            // Customer can not delete internal user
            if ($isTryCreatingInternalUser) {
                return false;
            }

            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    if ($model->client_id == $user->clientEmployee->client_id) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        } else {
            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_iglocal_user') || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{

                // Internal staff can delete internal user
                if ($isTryCreatingInternalUser) {
                    return false;
                }
                // Staff can only delete User for his/her own assigned client
                /** @var IglocalEmployee $employee */
                $employee = $user->iGlocalEmployee;
                $hasAssignment = $employee->isAssignedFor($model->client_id);
                if ($hasAssignment) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User $user
     * @param  User $model
     *
     * @return mixed
     */
    public function restore(User $user, User $model)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user
     * @param  User  $model
     *
     * @return mixed
     */
    public function forceDelete(User $user, User $model)
    {
        //
    }

    protected function isInternalUser($model)
    {
        if ($model instanceof User) {
            $isInternal = $model->is_internal;
            $clientId = $model->client_id;
        } elseif (is_array($model)) {
            $isInternal = $model['is_internal'];
            $clientId = $model['client_id'];
        } else {
            return false;
        }
        if (!!$isInternal || $clientId == Constant::INTERNAL_DUMMY_CLIENT_ID) {
            return true;
        }
        return false;
    }

    public function setPreferences(User $user, User $model)
    {
        return $user->id == $model->id;
    }
}
