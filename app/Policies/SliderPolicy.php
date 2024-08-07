<?php

namespace App\Policies;

use App\Models\Slider;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Auth;

class SliderPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Slider $slider)
    {
        return true;
    }

    public function create(User $user, array $injected)
    {
        return $this->checkPermission();
    }

    public function update(User $user, Slider $slider)
    {
        return $this->checkPermission();
    }

    public function delete(User $user, Slider $slider)
    {
        return $this->checkPermission();
    }

    public function upload(User $user, Slider $slider): bool
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
