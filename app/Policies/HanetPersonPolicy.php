<?php

namespace App\Policies;

use App\Models\HanetPerson;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HanetPersonPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, HanetPerson $hanetPerson)
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

    public function update(User $user, HanetPerson $hanetPerson)
    {
        // 
    }


    public function delete(User $user, HanetPerson $hanetPerson)
    {
        // 
    }


    public function restore(User $user, HanetPerson $hanetPerson)
    {
        //
    }


    public function forceDelete(User $user, HanetPerson $hanetPerson)
    {
        //
    }
}
