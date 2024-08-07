<?php

namespace App\Policies;

use App\User;
use App\Models\WorkScheduleGroupTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;
use Carbon\Carbon;
use App\Exceptions\HumanErrorException;

class WorkScheduleGroupTemplatePolicy
{
    use HandlesAuthorization;

    private $clientManagerPermission = 'manage-workschedule';

    /**
     * Determine whether the user can view any work schedule group templates.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the work schedule group template.
     *
     * @param  \App\User  $user
     * @param  \App\WorkScheduleGroupTemplate  $workScheduleGroupTemplate
     * @return mixed
     */
    public function view(User $user, WorkScheduleGroupTemplate $workScheduleGroupTemplate)
    {
        //
    }

    /**
     * Determine whether the user can create work schedule group templates.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if(!empty($injected['start_break']) || !empty($injected['end_break'])){
            if($injected['start_break'] != '00:00' || $injected['end_break'] != '00:00'){
                $check_in = Carbon::createFromTimeString($injected['check_in']);
                $start_break = Carbon::createFromTimeString($injected['start_break']);
                $end_break = Carbon::createFromTimeString($injected['end_break']);
                $check_out = Carbon::createFromTimeString($injected['check_out']);
                if (!$start_break->between($check_in, $end_break, false) || !$end_break->between($start_break, $check_out, false)) {
                    throw new HumanErrorException(__("error.invalid_time"));
                }
            }
        }

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
     * Determine whether the user can update the work schedule group template.
     *
     * @param  \App\User  $user
     * @param  \App\WorkScheduleGroupTemplate  $workScheduleGroupTemplate
     * @return mixed
     */
    public function update(User $user, WorkScheduleGroupTemplate $workScheduleGroupTemplate, array $injected)
    {
        if(!empty($injected['start_break']) || !empty($injected['end_break'])){
            if($injected['start_break'] != '00:00' || $injected['end_break'] != '00:00'){
                $check_in = Carbon::createFromTimeString($injected['check_in']);
                $start_break = Carbon::createFromTimeString($injected['start_break']);
                $end_break = Carbon::createFromTimeString($injected['end_break']);
                $check_out = Carbon::createFromTimeString($injected['check_out']);
                if (!$start_break->between($check_in, $end_break, false) || !$end_break->between($start_break, $check_out, false)) {
                    throw new HumanErrorException(__("error.invalid_time"));
                }
            }
        }
        if (!empty($injected['client_id'])) {

            if (!$user->isInternalUser()) {
                if ($user->client_id == $workScheduleGroupTemplate->client_id && $user->hasDirectPermission($this->clientManagerPermission)) {
                    return true;
                }
    
                return false;
            } else {

                if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                    return true;
                }else{
                    if ($user->iGlocalEmployee->isAssignedFor($workScheduleGroupTemplate->client_id)) {
                        return true;
                    }
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the work schedule group template.
     *
     * @param  \App\User  $user
     * @param  \App\WorkScheduleGroupTemplate  $workScheduleGroupTemplate
     * @return mixed
     */
    public function delete(User $user, WorkScheduleGroupTemplate $workScheduleGroupTemplate)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id == $workScheduleGroupTemplate->client_id && $user->hasDirectPermission($this->clientManagerPermission)) {
                return true;
            }

            return false;
        } else {
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($workScheduleGroupTemplate->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the work schedule group template.
     *
     * @param  \App\User  $user
     * @param  \App\WorkScheduleGroupTemplate  $workScheduleGroupTemplate
     * @return mixed
     */
    public function restore(User $user, WorkScheduleGroupTemplate $workScheduleGroupTemplate)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the work schedule group template.
     *
     * @param  \App\User  $user
     * @param  \App\WorkScheduleGroupTemplate  $workScheduleGroupTemplate
     * @return mixed
     */
    public function forceDelete(User $user, WorkScheduleGroupTemplate $workScheduleGroupTemplate)
    {
        //
    }
}
