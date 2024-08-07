<?php

namespace App\Policies;

use App\Models\Approve;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ApproveGroupPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any approves.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the approve.
     *
     * @param  \App\User  $user
     * @param  \App\Approve  $approve
     * @return mixed
     */
    public function view(User $user, Approve $approve)
    {
        //
    }

    /**
     * Determine whether the user can create approves.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the approve.
     *
     * @param  \App\User  $user
     * @param  \App\Approve  $approve
     * @return mixed
     */
    public function update(User $user, Approve $approve)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the approve.
     *
     * @param  \App\User  $user
     * @param  \App\Approve  $approve
     * @return mixed
     */
    public function delete(User $user, Approve $approve)
    {
        return true;
    }

    /**
     * Determine whether the user can restore the approve.
     *
     * @param  \App\User  $user
     * @param  \App\Approve  $approve
     * @return mixed
     */
    public function restore(User $user, Approve $approve)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the approve.
     *
     * @param  \App\User  $user
     * @param  \App\Approve  $approve
     * @return mixed
     */
    public function forceDelete(User $user, Approve $approve)
    {
        //
    }
}
