<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RatingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, Evaluation $evalutaion)
    {
        //
    }

    public function create(User $user, array $injected)
    {
        return $user->isInternalUser();
    }


    public function update(User $user, Evaluation $evalutaion)
    {
        return $user->isInternalUser();
    }

    public function delete(User $user, Evaluation $evalutaion)
    {
        return $user->isInternalUser();
    }

}
