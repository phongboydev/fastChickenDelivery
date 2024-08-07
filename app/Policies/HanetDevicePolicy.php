<?php

namespace App\Policies;

use App\Models\HanetDevice;
use App\Support\Constant;
use App\User;
use Auth;
use Illuminate\Auth\Access\HandlesAuthorization;

class HanetDevicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, HanetDevice $hanetDevice)
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

    public function update(User $user, HanetDevice $hanetDevice)
    {
        // Client chỉ có thể update device của client đó
        // User phải có quyền quản lý checkin-camera
        // Internal user chỉ được update device của client được phân công
        $user = Auth::user();
        if (!$user->isInternalUser()) {
            if ($hanetDevice->client_id !== $user->client_id) {
                return false;
            }
            if ($user->hasAnyPermission([Constant::PERMISSION_CLIENT_MANAGE_CAMERA])) {
                return true;
            }
        } else {
            return $user->iGlocalEmployee->isAssignedFor($hanetDevice->client_id);
        }
    }


    public function delete(User $user, HanetDevice $hanetDevice)
    {
        //
    }


    public function restore(User $user, HanetDevice $hanetDevice)
    {
        //
    }


    public function forceDelete(User $user, HanetDevice $hanetDevice)
    {
        //
    }
}
