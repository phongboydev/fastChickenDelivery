<?php

namespace App\Policies;

use App\Models\IglocalAssignment;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class IglocalAssignmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any iglocal assignments.
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
     * Determine whether the user can view the iglocal assignment.
     *
     * @param User                    $user
     * @param  \App\IglocalAssignment $iglocalAssignment
     *
     * @return mixed
     */
    public function view(User $user, IglocalAssignment $iglocalAssignment)
    {
        //
    }

    /**
     * Determine whether the user can create iglocal assignments.
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
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasAnyPermission(['manage_clients', 'manage_assignement'])) {
                return true;
            }else{
                return false;
            }
        }
    }

    /**
     * Determine whether the user can update the iglocal assignment.
     *
     * @param  User  $user
     * @param  \App\IglocalAssignment  $iglocalAssignment
     *
     * @return mixed
     */
    public function update(User $user, IglocalAssignment $iglocalAssignment)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return false;
            }
        } else {
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasAnyPermission(['manage_clients', 'manage_assignement'])) {
                return true;
            }else{
                return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the iglocal assignment.
     *
     * @param  User  $user
     * @param  \App\IglocalAssignment  $iglocalAssignment
     *
     * @return mixed
     */
    public function delete(User $user, IglocalAssignment $iglocalAssignment)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return false;
            }
        } else {
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasAnyPermission(['manage_clients', 'manage_assignement'])) {
                return true;
            }else{
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the iglocal assignment.
     *
     * @param  User  $user
     * @param  \App\IglocalAssignment  $iglocalAssignment
     *
     * @return mixed
     */
    public function restore(User $user, IglocalAssignment $iglocalAssignment)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the iglocal assignment.
     *
     * @param  User  $user
     * @param  \App\IglocalAssignment  $iglocalAssignment
     *
     * @return mixed
     */
    public function forceDelete(User $user, IglocalAssignment $iglocalAssignment)
    {
        //
    }
}
