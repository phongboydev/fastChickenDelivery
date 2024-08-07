<?php

namespace App\Policies;

use App\Models\HanetPlace;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HanetPlacePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, HanetPlace $hanetPlace)
    {
        //
    }


    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id == $injected['client_id']) {
                return true;
            }

            return false;
        } else {

            return true;
        }
    }

    public function update(User $user, HanetPlace $hanetPlace)
    {
        // 
    }


    public function delete(User $user, HanetPlace $hanetPlace)
    {
        // 
    }


    public function restore(User $user, HanetPlace $hanetPlace)
    {
        //
    }


    public function forceDelete(User $user, HanetPlace $hanetPlace)
    {
        //
    }
}
