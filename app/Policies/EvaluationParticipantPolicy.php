<?php

namespace App\Policies;

use App\Models\EvaluationParticipant;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EvaluationParticipantPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, EvaluationParticipant $evaluationParticipant)
    {
        if (!$user->isInternalUser())
        {
           return $user->clientEmployee->id == $evaluationParticipant->clientEmployee->id;
        }else{
            return $user->isInternalUser();
        }
    }


    public function create(User $user, array $injected)
    {
        return $user->client_id == $injected['client_id'];   
    }


    public function update(User $user, EvaluationParticipant $evaluationParticipant)
    {
        return  $user->clientEmployee->id == $evaluationParticipant->client_employee_id;    
    }


    public function delete(User $user, EvaluationParticipant $evaluationParticipant)
    {
        return  ($user->clientEmployee->id == $evaluationParticipant->creator->clientEmployee->id) ||
                ($user->clientEmployee->id == $evaluationParticipant->clientEmployee->id);
    }

    public function reassign(User $user, EvaluationParticipant $evaluationParticipant)
    {
       return  $user->clientEmployee->id == $evaluationParticipant->creator->clientEmployee->id;    
    }

}
