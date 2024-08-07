<?php

namespace App\GraphQL\Queries;

use App\Exceptions\CustomException;
use App\Models\EvaluationParticipant;
use App\Models\EvaluationStep;
use App\Support\EvaluationParticipantTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class GetEvaluationParticipantByIdQuery
{

    use EvaluationParticipantTrait;

    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        if(isset($args['id'])){
            return $this->getEvaluationParticipantById($args['id']);
        }
    }

    public function getEvaluationParticipantById($evaluationParticipantId){
        $initialEvaluationParticipant = EvaluationParticipant::authUserAccessible()
                               ->findOrFail($evaluationParticipantId);
        $evaluationObjectId = $initialEvaluationParticipant->evaluation_object_id;

        // Ensure only one person can review the evaluation at a time. 
        // If someone else is evaluating, throw an exception
        $this->lockEvaluationParticipant($evaluationObjectId);
            
        $evaluationParticipant = EvaluationParticipant::authUserAccessible()
                                ->with([
                                    'evaluationStep.evaluationGroup.evaluationSteps.evaluationParticipants' => function($query) use ($evaluationObjectId){
                                        $query->where('evaluation_object_id', $evaluationObjectId);
                                    },
                                ])
                                ->findOrFail($evaluationParticipantId);
        if (!$evaluationParticipant){
            throw new CustomException("Evaluation Participant not found",CustomException::class);
        }  

        return $evaluationParticipant;
    }
}
