<?php

namespace App\Policies;

use App\Models\IglocalEmployee;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class IglocalEmployeePolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage_iglocal_user';

    /**
     * Determine whether the user can view any iglocal employees.
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
     * Determine whether the user can view the iglocal employee.
     *
     * @param User                  $user
     * @param  \App\IglocalEmployee $iglocalEmployee
     *
     * @return mixed
     */
    public function view(User $user, IglocalEmployee $iglocalEmployee)
    {
        //
    }

    /**
     * Determine whether the user can create iglocal employees.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user)
    {
        if (!$user->isInternalUser()) {
            return false;
        } else {
            return ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission($this->managerPermission));
        }
    }

    /**
     * Determine whether the user can update the iglocal employee.
     *
     * @param  User  $user
     * @param  \App\IglocalEmployee  $iglocalEmployee
     *
     * @return mixed
     */
    public function update(User $user, IglocalEmployee $iglocalEmployee)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return false;
            }
        } else {
            return ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission($this->managerPermission));
        }
    }

    /**
     * Determine whether the user can delete the iglocal employee.
     *
     * @param  User  $user
     * @param  \App\IglocalEmployee  $iglocalEmployee
     *
     * @return mixed
     */
    public function delete(User $user, IglocalEmployee $iglocalEmployee)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return false;
            }
        } else {
            return ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission($this->managerPermission));
        }
    }

    /**
     * Determine whether the user can restore the iglocal employee.
     *
     * @param  User  $user
     * @param  \App\IglocalEmployee  $iglocalEmployee
     *
     * @return mixed
     */
    public function restore(User $user, IglocalEmployee $iglocalEmployee)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the iglocal employee.
     *
     * @param  User  $user
     * @param  \App\IglocalEmployee  $iglocalEmployee
     *
     * @return mixed
     */
    public function forceDelete(User $user, IglocalEmployee $iglocalEmployee)
    {
        //
    }
}
