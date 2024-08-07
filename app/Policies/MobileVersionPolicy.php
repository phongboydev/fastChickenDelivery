<?php

namespace App\Policies;

use App\Models\MobileVersion;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Auth;

class MobileVersionPolicy
{
    use HandlesAuthorization;

    public function view(User $user, MobileVersion $mobileVersion)
    {
        return true;
    }

    public function create(User $user, array $injected)
    {
        return $this->checkPermission();
    }

    public function update(User $user, MobileVersion $mobileVersion)
    {
        return $this->checkPermission();
    }

    public function delete(User $user, MobileVersion $mobileVersion)
    {
        return $this->checkPermission();
    }

    public function upload(User $user, MobileVersion $mobileVersion): bool
    {
        return $this->checkPermission();
    }

    public function checkPermission() {
        $auth = Auth::user();
        if (!$auth->isInternalUser()) {
            return false;
        } else {
            return true;
        }
    }
}
