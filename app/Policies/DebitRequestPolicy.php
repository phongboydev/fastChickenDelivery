<?php

namespace App\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\DebitRequest;

class DebitRequestPolicy
{
    use HandlesAuthorization;

    public function __construct()
    {
    }

    public function upload(User $user, DebitRequest $model) {
        return true;
    }
}
