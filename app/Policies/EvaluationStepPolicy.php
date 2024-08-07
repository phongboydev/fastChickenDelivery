<?php

namespace App\Policies;

use App\Models\EvaluationStep;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EvaluationStepPolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-evaluation';

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, EvaluationStep $evaluationStep)
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

    public function update(User $user, EvaluationStep $evaluationStep)
    {
        if (!$user->isInternalUser())
        {
            return $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }

    public function delete(User $user, EvaluationStep $evaluationStep)
    {
        if (!$user->isInternalUser())
        {
            return $user->hasDirectPermission($this->managerPermission);
        }else{
            return $user->isInternalUser();
        }
    }
   
}
