<?php

namespace App\Policies;

use App\Models\EvaluationObject;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EvaluationObjectPolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-evaluation';

    public function viewAny(User $user)
    {
        //
    }


    public function view(User $user, EvaluationObject $evaluationObject)
    {
        if (!$user->isInternalUser())
        {
           return $user->clientEmployee->id == $evaluationObject->clientEmployee->id;
        }else{
            return $user->isInternalUser();
        }
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

    public function update(User $user, EvaluationObject $evaluationObject)
    {
        if (!$user->isInternalUser())
        {
            return $user->client_id == $evaluationObject->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }

    public function delete(User $user, EvaluationObject $evaluationObject)
    {
        if (!$user->isInternalUser())
        {
            return $user->client_id == $evaluationObject->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }


}
