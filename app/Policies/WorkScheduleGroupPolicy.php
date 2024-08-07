<?php

namespace App\Policies;

use App\User;
use App\Models\WorkScheduleGroup;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class WorkScheduleGroupPolicy
{
    use HandlesAuthorization;

    private $clientManagerPermission = 'manage-workschedule';

    /**
     * Determine whether the user can view any work schedule groups.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the work schedule group.
     *
     * @param  \App\User  $user
     * @param  \App\WorkScheduleGroup  $workScheduleGroup
     * @return mixed
     */
    public function view(User $user, WorkScheduleGroup $workScheduleGroup)
    {
        //
    }

    /**
     * Determine whether the user can create work schedule groups.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!empty($injected['client_id'])) {

            if (!$user->isInternalUser()) {
                if (!empty($injected['client_id'])) {
                    if ($user->client_id == $injected['client_id'] && $user->hasDirectPermission($this->clientManagerPermission)) {
                        return true;
                    }
                }
                return false;
            } else {

                if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                    return true;
                }else{
                    if ($user->iGlocalEmployee->isAssignedFor($injected['client_id'])) {
                        return true;
                    }
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Determine whether the user can update the work schedule group.
     *
     * @param  \App\User  $user
     * @param  \App\WorkScheduleGroup  $workScheduleGroup
     * @return mixed
     */
    public function update(User $user, WorkScheduleGroup $workScheduleGroup, array $injected)
    {
        if (!empty($injected['client_id'])) {

            if (!$user->isInternalUser()) {
                if ($user->client_id == $workScheduleGroup->client_id && $user->hasDirectPermission($this->clientManagerPermission)) {
                    return true;
                }
    
                return false;
            } else {
                if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                    return true;
                }else{
                    if ($user->iGlocalEmployee->isAssignedFor($injected['client_id'])) {
                        return true;
                    }
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the work schedule group.
     *
     * @param  \App\User  $user
     * @param  \App\WorkScheduleGroup  $workScheduleGroup
     * @return mixed
     */
    public function delete(User $user, WorkScheduleGroup $workScheduleGroup)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id == $workScheduleGroup->client_id && $user->hasDirectPermission($this->clientManagerPermission)) {
                return true;
            }

            return false;
        } else {
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($workScheduleGroup['client_id'])) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the work schedule group.
     *
     * @param  \App\User  $user
     * @param  \App\WorkScheduleGroup  $workScheduleGroup
     * @return mixed
     */
    public function restore(User $user, WorkScheduleGroup $workScheduleGroup)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the work schedule group.
     *
     * @param  \App\User  $user
     * @param  \App\WorkScheduleGroup  $workScheduleGroup
     * @return mixed
     */
    public function forceDelete(User $user, WorkScheduleGroup $workScheduleGroup)
    {
        //
    }
}
