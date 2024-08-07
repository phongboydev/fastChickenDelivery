<?php

namespace App\Policies;

use App\Models\EvaluationQuestionTemplate;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EvaluationQuestionTemplatePolicy
{
    use HandlesAuthorization;

    public function create(User $user, array $injected)
    {
        return $user->client_id == $injected['client_id'];   
    }


    public function update(User $user, EvaluationQuestionTemplate $evaluationQuestionTemplate)
    {
        if (!$user->isInternalUser())
        {
            return $user->client_id == $evaluationQuestionTemplate->client_id;
        }
        else{
            return $user->isInternalUser();
        }
    }


    public function delete(User $user, EvaluationQuestionTemplate $evaluationQuestionTemplate)
    {
        if (!$user->isInternalUser())
        {
            return $user->client_id == $evaluationQuestionTemplate->client_id;
        }
        else{
            return $user->isInternalUser();
        }
    }

}
