<?php

namespace App\Policies;

use App\Support\Constant;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\User;
use App\Models\ClientYearHoliday;

class ClientYearHolidayPolicy extends BasePolicy
{
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
     * @param ClientYearHoliday $clientYearHoliday
     *
     * @return mixed
     */
    public function view(User $user, ClientYearHoliday $clientYearHoliday)
    {
        //
    }

    /**
     * Determine whether the user can create client employees.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, $injected)
    {
        return $this->isPermission($user, $injected['client_id']);
    }

    /**
     * @param User   $user
     * @param ClientYearHoliday $clientYearHoliday
     *
     * @return bool
     */
    public function update(User $user, ClientYearHoliday $clientYearHoliday)
    {
        return $this->isPermission($user, $clientYearHoliday['client_id']);
    }

    /**
     * @param User   $user
     * @param ClientYearHoliday $clientYearHoliday
     *
     * @return bool
     */
    public function delete(User $user, ClientYearHoliday $clientYearHoliday)
    {
        return  $this->isPermission($user, $clientYearHoliday['client_id']);
    }

    /**
     * Determine whether the user can restore the client employee.
     *
     * @param  User  $user
     * @param  ClientYearHoliday $clientYearHoliday
     *
     * @return mixed
     */
    public function restore(User $user, ClientYearHoliday $clientYearHoliday)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee.
     *
     * @param  User  $user
     * @param  ClientYearHoliday $clientYearHoliday
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientYearHoliday $clientYearHoliday)
    {
        //
    }

    public function isPermission($user, $clientId)
    {
        $isPermission = false;
        if (!empty($clientId)) {
            if (!$user->isInternalUser()) {
                if ($user->client_id == $clientId) {
                    $isPermission = true;
                }
            } else {
                if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients') || $user->iGlocalEmployee->isAssignedFor($clientId)) {
                    $isPermission = true;
                }
            }
        }
        return $isPermission;
    }
}
