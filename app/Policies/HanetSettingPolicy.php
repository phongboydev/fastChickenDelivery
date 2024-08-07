<?php

namespace App\Policies;

use App\Models\HanetSetting;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class HanetSettingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, HanetSetting $hanetSetting)
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

    public function update(User $user, HanetSetting $hanetSetting)
    {
        // 
    }


    public function delete(User $user, HanetSetting $hanetSetting)
    {
        // 
    }


    public function restore(User $user, HanetSetting $hanetSetting)
    {
        //
    }


    public function forceDelete(User $user, HanetSetting $hanetSetting)
    {
        //
    }
}
