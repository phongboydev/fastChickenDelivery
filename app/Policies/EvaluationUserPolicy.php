<?php

namespace App\Policies;

use App\Models\EvaluationUser;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EvaluationUserPolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-evaluation';

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, EvaluationUser $evalutaion)
    {
        //
    }

    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser())
        {
            return $user->client_id == $injected['client_id'] && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }

    public function update(User $user, EvaluationUser $evalutaion)
    {
        if (!$user->isInternalUser())
        {
            return $user->client_id == $evalutaion->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }

    public function delete(User $user, EvaluationUser $evalutaion)
    {
        if (!$user->isInternalUser())
        {
            return $user->client_id == $evalutaion->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }

    public function upload(User $user, EvaluationUser $evalutaion): bool
    {
        if (!$user->isInternalUser())
        {
            return $user->client_id == $evalutaion->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }
}
