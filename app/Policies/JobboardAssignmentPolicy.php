<?php

namespace App\Policies;

use App\Models\JobboardAssignment;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class JobboardAssignmentPolicy
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
    public function view(User $user, JobboardAssignment $jobboardAssignment)
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
        return true;
    }

    /**
     * Determine whether the user can update the iglocal assignment.
     *
     * @param  User  $user
     * @param  \App\IglocalAssignment  $iglocalAssignment
     *
     * @return mixed
     */
    public function update(User $user, JobboardAssignment $jobboardAssignment)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the iglocal assignment.
     *
     * @param  User  $user
     * @param  \App\IglocalAssignment  $iglocalAssignment
     *
     * @return mixed
     */
    public function delete(User $user, JobboardAssignment $jobboardAssignment)
    {
        return true;
    }

    /**
     * Determine whether the user can restore the iglocal assignment.
     *
     * @param  User  $user
     * @param  \App\IglocalAssignment  $iglocalAssignment
     *
     * @return mixed
     */
    public function restore(User $user, JobboardAssignment $jobboardAssignment)
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
    public function forceDelete(User $user, JobboardAssignment $jobboardAssignment)
    {
        //
    }
}
