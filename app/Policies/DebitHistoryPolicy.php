<?php

namespace App\Policies;

use App\User;
use App\Models\DebitHistory;
use Illuminate\Auth\Access\HandlesAuthorization;

class DebitHistoryPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function create(User $user)
    {
        if ($user->isInternalUser()) {
            return true;
        }

        return false;
    }

    public function update(User $user)
    {
        if ($user->isInternalUser()) {
            return true;
        }

        return false;
    }

    public function delete(User $user)
    {
        if ($user->isInternalUser()) {
            return true;
        }

        return false;
    }

    public function upload(User $user, DebitHistory $model) {
        return true;
    }
}
