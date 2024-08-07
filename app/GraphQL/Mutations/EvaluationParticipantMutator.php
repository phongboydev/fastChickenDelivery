<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\CustomException;
use App\Models\EvaluationGroup;
use App\Models\EvaluationParticipant;
use App\Models\EvaluationStep;
use App\Models\EvaluationObject;
use App\Support\EvaluationParticipantTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class EvaluationParticipantMutator
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

    public function createEvaluationParticipant($root, array $args)
    {
        $user = Auth::user();
        $createdBy = $user->id;
        $updatedBy = $user->id;
        $clientId = $args['client_id'];
        $clientEmployeeId = $args['client_employee_id'];
        $evaluationObjectId = $args['evaluation_object_id'];
        $evaluationStepId = $args['evaluation_step_id'];
        $scoreboard = $args['scoreboard'] ?? null;

        if($user->client_id != $clientId){
            throw new CustomException(__("authorized"), CustomException::class);
        } 

        $evaluationParticipant = EvaluationParticipant::where('evaluation_object_id', $evaluationObjectId)
            ->where('evaluation_step_id', $evaluationStepId)
            ->first();
        if ($evaluationParticipant) {
            throw new CustomException("Each step only has one participant", CustomException::class);
        }

        $evaluationStep = EvaluationStep::with('clientEmployees')->find($evaluationStepId);
        $evaluationObject = EvaluationObject::with('evaluationGroup')->find($evaluationObjectId);
        $evaluationGroup = $evaluationObject->evaluationGroup;
        $evaluatedStep = $evaluationObject->step;

        $evaluatorIdOfStep = $evaluationStep->clientEmployees->pluck('id')->toArray();
        if (!in_array($clientEmployeeId, $evaluatorIdOfStep)) {
            throw new CustomException(__("warning.client_employee.invalid"), CustomException::class);
        }

        $isSkiped = false;
        // If this step is not self-evaluation and employee is evaluating themselves, skip this step
        if (!$evaluationStep->isSelf && $evaluationObject->client_employee_id == $clientEmployeeId) {
            $isSkiped = true;
            $this->handleInvalidNonSelfEvaluationStep($evaluatedStep, $evaluationStep, $evaluationGroup, $evaluationObject);
        }
        $inputEvaluationParticipant = [ 
            'id' => Str::uuid(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'client_id' => $clientId,
            'client_employee_id' => $clientEmployeeId,
            'evaluation_object_id' => $evaluationObjectId,
            'evaluation_step_id' => $evaluationStepId,
            'scoreboard' => $scoreboard,
            'created_by' => $createdBy,
            'updated_by' => $updatedBy,
            'lock' => false,
            'is_skiped' => $isSkiped
        ];
        $inputUpdateEvaluationObject = [
            'assigned_step' => $evaluationStep->step,
            'assigned_evaluator_id' => $clientEmployeeId
        ];
        return $this->handleValidEvaluationStep($inputEvaluationParticipant, $evaluationObjectId, $inputUpdateEvaluationObject);
    }

    public function handleInvalidNonSelfEvaluationStep($evaluatedStep, $evaluationStep, $evaluationGroup, $evaluationObject){
        if( $evaluationStep->step == $evaluatedStep + 1 && $evaluatedStep != 0){
            $previousStep = EvaluationStep::where([
                                            ['evaluation_group_id', $evaluationGroup->id],
                                            ['step', $evaluatedStep]
                                            ])->first();
                                    
            if($previousStep){
                $participantOfPreviousStep = EvaluationParticipant::where([
                                                    ['evaluation_step_id', $previousStep->id],
                                                    ['evaluation_object_id', $evaluationObject->id],
                                            ])->first();
            }               
        }
        try{
            DB::beginTransaction();

            // Update step of evaluation object
            $evaluationObject->update(['step' => $evaluationStep->step]);

            // Lock evaluation participant of previous step 
            if($participantOfPreviousStep){
                $participantOfPreviousStep->update([
                    'lock' => true
                ]);                        
            }
                     
            DB::commit();
        }catch(Exception $ex){
            logger()->error("", [$ex->getMessage()]);
            DB::rollBack();
            throw $ex;
        }

       return "Can't evaluate yourself in this step";
    }

    public function handleValidEvaluationStep($inputEvaluationParticipant, $evaluationObjectId, $inputUpdateEvaluationObject){
        try{
            DB::beginTransaction();
            
            // Insert evaluation participant
            if(!empty($inputEvaluationParticipant)){
                EvaluationParticipant::insert($inputEvaluationParticipant);   
            }

             // Update assigned_step and assigned_evaluator_id of EvaluationObject 
            if(!empty($inputUpdateEvaluationObject)){
                EvaluationObject::findOrFail($evaluationObjectId)
                                ->update($inputUpdateEvaluationObject);
            }

            DB::commit();
        } catch(Exception $ex){
            logger()->error("", [$ex->getMessage()]);
            DB::rollBack();
            throw $ex;
        }

        return "Create evaluation participant successfully";
    }

    public function updateEvaluationParticipant($root, array $args)
    {
        $user = Auth::user();
        $updatedBy = $user->id;
        $evaluationParticipantId = $args['id'];
        $scoreboard = $args['scoreboard'] ?? null;
    
        $evaluationParticipant = EvaluationParticipant::with(["evaluationStep", "evaluationObject"])->findOrFail($evaluationParticipantId);
        $evaluationGroup = $evaluationParticipant->evaluationStep->evaluationGroup;
        $evaluationObject = $evaluationParticipant->evaluationObject;

        $this->unlockEvaluationParticipant($evaluationObject->id);
     
        $lock = $evaluationGroup->lock;
        $deadline_end =  $evaluationGroup->deadline_end;
        if ($lock == true && $deadline_end <= Carbon::now()->toDateString()) {
            throw new CustomException("The deadline has been exceeded", CustomException::class);
        }

        if(isset($scoreboard)){
            $this->validateScoreboard($scoreboard);
            $currentStep = $evaluationParticipant->evaluationStep->step;
            $evaluatedStep = $evaluationObject->step;

            if($currentStep < $evaluatedStep){
                throw new CustomException(__("warning.evaluation_participant.not_resubmit"), CustomException::class);
            }
            
            if ($evaluationParticipant->lock == false && ( $currentStep == $evaluatedStep || $currentStep == $evaluatedStep + 1)) {
                // Check if the next step is a self-evaluation step
                $nextStep = EvaluationStep::where([
                    ['evaluation_group_id', $evaluationGroup->id],
                    ['step', $currentStep + 1],
                    ['isSelf', true],
                    ])->first();

                // Check if the previous step exists
                if($currentStep == $evaluatedStep + 1 && $evaluatedStep != 0){
                    $previousStep = EvaluationStep::where([
                                                    ['evaluation_group_id', $evaluationGroup->id],
                                                    ['step', $evaluatedStep]
                                                    ])->first();
                                        
                    if($previousStep){
                        $participantOfPreviousStep = EvaluationParticipant::where([
                                                            ['evaluation_step_id', $previousStep->id],
                                                            ['evaluation_object_id', $evaluationParticipant->evaluation_object_id],
                                                    ])->first();
                    }               
                }
               
                try {
                    DB::beginTransaction();
                    // Update scoreboard of evaluation participant
                    $evaluationParticipant->update([
                        'scoreboard' => $scoreboard,
                        'updated_by' => $updatedBy,
                        'evaluation_date' => Carbon::now()
                    ]);

                    // Update step of evaluation object
                    $evaluationObject->update(['step' => $currentStep]);

                    // Check the next step is self-step, then update the assigned_step and assigned_evaluator_id of the object
                    if($nextStep){
                        $evaluationObject->update(['assigned_step' => $currentStep + 1,
                                                   'assigned_evaluator_id' => $evaluationObject->clientEmployee->id]);
                    }

                    // Lock evaluation participant of previous step 
                    if(isset($participantOfPreviousStep)){
                        $participantOfPreviousStep->update([
                            'lock' => true
                        ]);                        
                    }         
             
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    logger()->error("updateEvaluationParticipant error: " . $e->getMessage());
                    throw $e;
                }
            }
            else {
                throw new CustomException(__("warning.evaluation_participant.lock"), CustomException::class);
            }
        }
        return $evaluationParticipant;
    }

    public function reassignEvaluationParticipant($root, array $args)
    {
        $user = Auth::user();
        $createdBy = $user->id;
        $updatedBy = $user->id;
        $evaluationParticipantId = $args['id'];
        $clientEmployeeId = $args['client_employee_id'] ?? null;

        if (isset($clientEmployeeId)) {
            $evaluationParticipant = EvaluationParticipant::findOrFail($evaluationParticipantId);
            $evaluatorIdOfStep = $evaluationParticipant->evaluationStep->clientEmployees->pluck('id')->toArray();
            if (!in_array($clientEmployeeId, $evaluatorIdOfStep)) {
                throw new CustomException(__("warning.client_employee.invalid"), CustomException::class);
            }

            if ($evaluationParticipant->scoreboard != null) {
                throw new CustomException(__("warning.evaluation_participant.not_reassign"), CustomException::class);
            }
            $evaluationParticipant->update([
                'client_employee_id' => $clientEmployeeId,
                'updated_by' => $updatedBy,
                'created_by' => $createdBy,
            ]);
        }
        return $evaluationParticipant;
    }

    public function unlockRelatedEvaluationParticipant($root, array $args){
        $user = Auth::user();
        $evaluationParticipantId = $args['id'];
        $evaluationParticipant = EvaluationParticipant::findOrFail($evaluationParticipantId);

        if (!$evaluationParticipant){
            throw new CustomException(__("warning.client_employee.invalid"), CustomException::class);
        } 

        if($user->clientEmployee->id != $evaluationParticipant->client_employee_id){
            throw new CustomException(__("authorized"), CustomException::class);
        } 

        $evaluationObjectId = $evaluationParticipant->evaluation_object_id;

        $this->unlockEvaluationParticipant($evaluationObjectId);
    }

    public function deleteEvaluationParticipant($root, array $args)
    {
        $evaluationParticipantId = $args['id'];
        $evaluationParticipant = EvaluationParticipant::findOrFail($evaluationParticipantId);

        if ($evaluationParticipant->scoreboard != null) {
            throw new CustomException(__("warning.evaluation_participant.not_reassign"), CustomException::class);
        }

        try{
            DB::beginTransaction();

            $evaluationObject =  EvaluationObject::findOrFail($evaluationParticipant->evaluation_object_id);
            $assignedStep =  $evaluationObject->assigned_step;
            $evaluationObject->update([
                'assigned_step' =>  $assignedStep - 1,
                'assigned_evaluator_id' => null,
            ]);

            $evaluationParticipant->delete();
            DB::commit();
        }catch(Exception $ex){
            logger()->error("", [$ex->getMessage()]);
            DB::rollBack();
            throw $ex;
        }
        return $evaluationParticipant;
    }
}
