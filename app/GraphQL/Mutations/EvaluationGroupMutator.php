<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\CustomException;
use App\Exports\EvaluationGroupExportMultipleSheet;
use App\Jobs\DeleteFileJob;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\EvaluationGroup;
use App\Models\EvaluationObject;
use App\Models\EvaluationParticipant;
use App\Models\EvaluationStep;
use App\Support\WorktimeRegisterHelper;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use stdClass;

class EvaluationGroupMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // TODO implement the resolver
    }

    public function create($_, array $args){
        $user = Auth::user();
        $clientId = $user->client_id;
        $createdBy = $user->id;
        $updatedBy = $user->id;
        $lock = $args['lock'] ?? false;
        $name = $args['name'];
        $phase_begin = $args['phase_begin'];
        $phase_end = $args['phase_end'];
        $configuration = $args['configuration'];
        $deadline_begin = $args['deadline_begin'];
        $deadline_end = $args['deadline_end'];
        $objectIds = $args['objectIds'];
        $total_employee = count($objectIds); 
        $evaluationSteps = $args['evaluationSteps'];
        $evaluationGroup = null;

        // phase_begin < phase_end && deadline_begin < deadline_end 
        if($phase_begin->gt($phase_end) || $deadline_begin->gt($deadline_end))
        {
            throw new CustomException(__("validation.time_greater"), CustomException::class);
        }

        // Validate only one self-assessment step is selected
        $numberOfSelfSteps = 0;
        foreach($evaluationSteps as $step){
            if($step['isSelf'] == 1){
                $numberOfSelfSteps++;
                if($numberOfSelfSteps > 1){
                    throw new CustomException(__("warning.evaluation_step.invalid"), CustomException::class);
                }
            }
        }

        // Validate objects in the company
        $objects = ClientEmployee::whereIn('id', $objectIds)->get();
        foreach($objects as $object){
            if($object->client_id != $clientId){
                throw new CustomException(__("warning.client_employee.invalid"), CustomException::class);
            }
        }

        // Generate code for evaluation group
        $clientCode = Client::findOrFail($clientId)->code;
        $code = strtoupper($clientCode . '-' . '00000');
        $latestEvaluationGroup = EvaluationGroup::withTrashed()->where('client_id', $clientId)->latest()->first();
        if( $latestEvaluationGroup){
            $code = WorktimeRegisterHelper::generateNextID($latestEvaluationGroup->code);
        }

        // Prepare data
        $evaluationGroupId =  Str::uuid();
        $inputEvaluationGroup = [
            'id' =>   $evaluationGroupId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'client_id' => $clientId,
            'created_by' => $createdBy,
            'updated_by' => $updatedBy,
            'code' => $code,
            'name' => $name,
            'lock' => $lock,
            'phase_begin' => $phase_begin,
            'phase_end' => $phase_end,
            'configuration' => $configuration,
            'total_employee' => $total_employee,
            'deadline_begin' => $deadline_begin,
            'deadline_end' => $deadline_end,
        ];

        $inputEvaluationObjects = [];
        $evaluationObjects = [];
        foreach($objects as $object){   
            $evaluationObjectId = Str::uuid();   
            $inputEvaluationObjects[] = [
                'id' =>  $evaluationObjectId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'client_id' => $clientId,
                'client_employee_id' => $object->id,
                'evaluation_group_id' => $evaluationGroupId,
                'step' => 0,
                'total_steps' => sizeof($evaluationSteps),
                'assigned_step' => 1,
            ];

            // If self-assessment is the first step, add value for field assigned_evaluator_id
            if($evaluationSteps[0]['isSelf'] == 1){
                $inputEvaluationObjects[count($inputEvaluationObjects) - 1]['assigned_evaluator_id'] = $object->id;
            }
            $evaluationObjects[$object->id] = $evaluationObjectId;
        }

        $inputEvaluationSteps = [];
        $inputStepEvaluators = [];
        foreach($evaluationSteps as $step){
            $evaluationStepId = Str::uuid();
            $evaluationStep = [
                'id' =>   $evaluationStepId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'title' => $step['title'],
                'step' => $step['step'],
                'isSelf' => $step['isSelf'],
                'deadline_date' => $step['deadline_date'],
                'evaluation_group_id' => $evaluationGroupId
            ];
            $inputEvaluationSteps[] = $evaluationStep; 
            if($step['isSelf'] == 0) {
                $participants = ClientEmployee::whereIn('id', $step['evaluationParticipantIds'])->get();
                foreach($participants as $participant){
                    if($participant->client_id != $clientId){
                        throw new CustomException(__("warning.client_employee.invalid"), CustomException::class);
                    }
                    $inputStepEvaluators[] = [
                        'evaluation_step_id' => $evaluationStepId,
                        'evaluator_id' => $participant->id
                    ];
                }
            }else{
                $clientEmployees = ClientEmployee::whereIn('id', $objectIds)->get();
                foreach($clientEmployees as $participant){
                    if($participant->client_id != $clientId){
                        throw new CustomException(__("warning.client_employee.invalid"), CustomException::class);
                    }
                    $inputStepEvaluators[] = [
                        'evaluation_step_id' => $evaluationStepId,
                        'evaluator_id' => $participant->id
                    ];
                }

                $inputEvaluationParticipants = [];
                foreach($objectIds as $objectId){        
                    $inputEvaluationParticipants[] = [
                        'id' => Str::uuid(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'client_id' => $clientId,
                        'client_employee_id' => $objectId,
                        'evaluation_object_id' =>  $evaluationObjects[$objectId],
                        'evaluation_step_id' => $evaluationStep['id'], 
                        'scoreboard' => "",
                        'created_by' => $createdBy,
                        'updated_by' => $updatedBy
                    ];
                }           
            }    
        }
        try{
            DB::beginTransaction();
            if(!empty($inputEvaluationGroup)){
                EvaluationGroup::insert($inputEvaluationGroup);   
            }
            if(!empty($inputEvaluationObjects)){
                EvaluationObject::insert($inputEvaluationObjects);   
            }
            if(!empty($inputEvaluationSteps)){
                EvaluationStep::insert($inputEvaluationSteps);   
            }
            if(!empty($inputEvaluationParticipants)){
                EvaluationParticipant::insert($inputEvaluationParticipants);   
            }
            if(!empty($inputStepEvaluators)){
                DB::table('step_evaluator')->insert($inputStepEvaluators); 
            } 
            DB::commit();
           return EvaluationGroup::findOrFail($evaluationGroupId);
        }catch(Exception $ex){
            logger()->error("", [$ex->getMessage()]);
            DB::rollBack();
            throw $ex;
        }
    }
    

    public function deleteEvaluationGroup($root, array $args) {
        // Prepare data
        $id = $args['id'];
        $evaluationGroup = EvaluationGroup::with(['evaluationObjects', 'evaluationSteps.evaluationParticipants'])
                                          ->findOrFail($id);
        $objectIds = $evaluationGroup->evaluationObjects->pluck('id')->toArray();
        $evaluationSteps = $evaluationGroup->evaluationSteps;
        $stepIds = $evaluationSteps->pluck('id')->toArray();
        $participantIds = [];
        foreach($evaluationSteps as $evaluationStep){
            $participantIds = array_merge($participantIds, $evaluationStep->evaluationParticipants->pluck('id')->toArray());
        }
    
        try {
            DB::beginTransaction();
            EvaluationObject::whereIn('id', $objectIds)->delete();
            EvaluationParticipant::whereIn('id', $participantIds)->delete();
            EvaluationStep::whereIn('id', $stepIds)->delete();
            $evaluationGroup->delete();
            DB::commit();
            return $evaluationGroup;
        }
        catch(Exception $ex) {
            DB::rollBack();
            logger()->error("deleteEvaluation error: " . $ex->getMessage());
            throw $ex;
        }

    }

    public function exportEvaluationGroup($root, array $args) {
        $extension = '.xlsx';
        $fileName = "EvaluationGroupMultipleSheet" . time() .  $extension;
        $pathFile = 'temp/' . $fileName;

        Excel::store((new EvaluationGroupExportMultipleSheet($args['id'])), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        // Delete file 
        DeleteFileJob::dispatch($pathFile)->delay(now()->addMinutes(3));
        
        return json_encode($response);
    }
}
  