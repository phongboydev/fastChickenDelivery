<?php

namespace App\Policies;

use App\User;
use App\Models\WorkSchedule;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;
use Carbon\Carbon;
use App\Exceptions\HumanErrorException;

class WorkSchedulePolicy
{
    use HandlesAuthorization;

    private $clientManagerPermission = 'manage-workschedule';

    /**
     * Determine whether the user can view any work schedules.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the work schedule.
     *
     * @param  \App\User  $user
     * @param  \App\WorkSchedule  $workSchedule
     * @return mixed
     */
    public function view(User $user, WorkSchedule $workSchedule)
    {
        //
    }

    /**
     * Determine whether the user can generate work schedules.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function generate(User $user, array $injected)
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
     * Determine whether the user can create work schedules.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        //
    }

    /**
     * Determine whether the user can update the work schedule.
     *
     * @param  \App\User  $user
     * @param  \App\WorkSchedule  $workSchedule
     * @return mixed
     */
    public function update(User $user, WorkSchedule $workSchedule, array $injected)
    {
        if (!empty($injected['client_id'])) {

            if (!$user->isInternalUser()) {
                if ($user->client_id == $workSchedule->client_id && $user->hasDirectPermission($this->clientManagerPermission)) {
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
     * Determine whether the user can delete the work schedule.
     *
     * @param  \App\User  $user
     * @param  \App\WorkSchedule  $workSchedule
     * @return mixed
     */
    public function delete(User $user, WorkSchedule $workSchedule)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id == $workSchedule->client_id && $user->hasDirectPermission($this->clientManagerPermission)) {
                return true;
            }

            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($workSchedule['client_id'])) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the work schedule.
     *
     * @param  \App\User  $user
     * @param  \App\WorkSchedule  $workSchedule
     * @return mixed
     */
    public function restore(User $user, WorkSchedule $workSchedule)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the work schedule.
     *
     * @param  \App\User  $user
     * @param  \App\WorkSchedule  $workSchedule
     * @return mixed
     */
    public function forceDelete(User $user, WorkSchedule $workSchedule)
    {
        //
    }
}
