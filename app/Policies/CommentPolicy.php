<?php

namespace App\Policies;

use App\User;
use App\Models\Comment;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any client employee overtime requests.
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
     * Determine whether the user can view the client employee overtime request.
     *
     * @param User                          $user
     * @param ClientWifiCheckinSpot $clientWifiCheckinSpot
     *
     * @return mixed
     */
    public function view(User $user, Comment $comment)
    {
        //
    }

    /**
     * Determine whether the user can create client employee overtime requests.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        return true;
    }

    /**
     * Determine whether the user can update the client employee overtime request.
     *
     * @param User                          $user
     * @param Comment $comment
     *
     * @return mixed
     */
    public function update(User $user, Comment $comment)
    {
        if (!$user->isInternalUser()) {

            if ($user->id == $comment['user_id']) {
                return true;
            }

            return false;
        } else {
            return true;
        }
    }

    /**
     * Determine whether the user can delete the client employee overtime request.
     *
     * @param  User  $user
     * @param ClientEmployeeOvertimeRequest  $clientEmployeeOvertimeRequest
     *
     * @return mixed
     */
    public function delete(User $user, Comment $comment)
    {
        return $user->isInternalUser();
    }

    /**
     * Determine whether the user can restore the client employee overtime request.
     *
     * @param User                          $user
     * @param Comment $comment
     *
     * @return mixed
     */
    public function restore(User $user, Comment $comment)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee overtime request.
     *
     * @param User                          $user
     * @param Comment $comment
     *
     * @return mixed
     */
    public function forceDelete(User $user, Comment $comment)
    {
        //
    }

    public function upload(User $user, $model)
    {
        return true;
    }
}
