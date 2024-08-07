<?php

namespace App\GraphQL\Queries;

use App\Exceptions\CustomException;
use App\Models\EvaluationStep;
use App\Models\EvaluationObject;

class GetNextEvaluationStepQuery
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        if(isset($args['evaluationObjectId'])){
            $evaluationObjectId = $args['evaluationObjectId'];
            if(!EvaluationObject::where('id', $evaluationObjectId)->exists()){
                throw new CustomException("Evaluation Object not found", CustomException::class);
            }
            return $this->handle($evaluationObjectId);
        }  
    }

    public function handle($evaluationObjectId){
        $evaluationObject = EvaluationObject::findOrFail($evaluationObjectId);
        $evaluatedStep = $evaluationObject->step;
        $evaluationGroupId = $evaluationObject->evaluation_group_id;
        $sortedStep = EvaluationStep::with(['evaluationParticipants' => function($query) use ($evaluationObjectId){
                                        $query->where('evaluation_object_id', $evaluationObjectId); }])
                                    ->where('evaluation_group_id', $evaluationGroupId)
                                    ->orderBy('step', 'ASC')
                                    ->get();
        $nextStep = $sortedStep->where('step', '>', $evaluatedStep)
                               ->first();
                               
        if(is_null($nextStep)){
            throw new CustomException(__("model.evaluation_step.final_step"), CustomException::class);
        } 
        return $nextStep; 
    }
}
