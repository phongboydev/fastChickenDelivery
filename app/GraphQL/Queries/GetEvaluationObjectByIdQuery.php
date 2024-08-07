<?php

namespace App\GraphQL\Queries;

use App\Exceptions\CustomException;
use App\Models\ClientEmployee;
use App\Models\EvaluationObject;
use App\Models\EvaluationParticipant;
use App\Models\EvaluationStep;
use App\Support\EvaluationParticipantTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class GetEvaluationObjectByIdQuery
{

    use EvaluationParticipantTrait;
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // TODO implement the resolver
    }

    public function getEvaluationObjectById($root, array $args){ 
        $evaluationObjectId = $args['id'];
        $evaluationObject = EvaluationObject::authUserAccessible()
                        ->with([
                            'evaluationGroup.evaluationSteps.evaluationParticipants' => function($query) use ($evaluationObjectId){
                                $query->where('evaluation_object_id', $evaluationObjectId);
                            },
                        ])->findOrFail($evaluationObjectId);
        if (!$evaluationObject){
            throw new CustomException("Evaluation Object not found", CustomException::class);
        } 

        // Ensure only one person can review the evaluation at a time. 
        // If someone else is evaluating, throw an exception
        $selfStep = $evaluationObject->evaluationGroup->evaluationSteps->where('isSelf', true)->first();
        if($selfStep){
          $this->lockEvaluationParticipant($evaluationObjectId);
        }
        return $evaluationObject; 
    }

    public function overviewEvaluationObject($root, array $args){
        $evaluationObjectId = $args['id'];
        $evaluationObject = EvaluationObject::authUserAccessible()
                        ->with([
                            'evaluationGroup.evaluationSteps.evaluationParticipants' => function($query) use ($evaluationObjectId){
                                $query->where('evaluation_object_id', $evaluationObjectId);
                            },
                        ])->findOrFail($evaluationObjectId);
        if (!$evaluationObject){
            throw new CustomException("Evaluation Object not found", CustomException::class);
        } 
        return $evaluationObject; 
    }
}
