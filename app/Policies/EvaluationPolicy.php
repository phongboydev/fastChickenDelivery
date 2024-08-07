<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EvaluationPolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-evaluation';

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
        if (!$user->isInternalUser()) 
        {
            return $user->client_id == $injected['client_id'] && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }

    public function update(User $user, Evaluation $evalutaion)
    {
        if (!$user->isInternalUser()) 
        {
            return $user->client_id == $evalutaion->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }

    public function delete(User $user, Evaluation $evalutaion)
    {
        if (!$user->isInternalUser()) 
        {
            return $user->client_id == $evalutaion->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }

}
