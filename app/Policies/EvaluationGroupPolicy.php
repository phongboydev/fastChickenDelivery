<?php

namespace App\Policies;

use App\Models\EvaluationGroup;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EvaluationGroupPolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-evaluation';

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, EvaluationGroup $group)
    {
        //
    }

    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser())
        {
            return $user->hasDirectPermission($this->managerPermission); 
        }else{
            return $user->isInternalUser();
        }
    }

    public function update(User $user, EvaluationGroup $group)
    {
        if (!$user->isInternalUser())
        {
            return $user->client_id == $group->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }

    public function delete(User $user, EvaluationGroup $group)
    {
        if (!$user->isInternalUser())
        {
            return $user->client_id == $group->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }

    public function upload(User $user, EvaluationGroup $group): bool
    {
        if (!$user->isInternalUser())
        {
            return $user->client_id == $group->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }
}
