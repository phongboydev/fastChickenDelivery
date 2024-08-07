<?php

namespace App\Policies;

use App\Models\UnpaidLeaveChange;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnpaidLeaveChangePolicy
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
    public function create(User $user, array $data)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User $user
     * @param  User $model
     *
     * @return mixed
     */
    public function update(User $user, UnpaidLeaveChange $model)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User  $user
     * @param  User $model
     *
     * @return mixed
     */
    public function delete(User $user, UnpaidLeaveChange $model)
    {
        return true;
    }
}
