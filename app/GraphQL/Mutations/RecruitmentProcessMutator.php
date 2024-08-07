<?php
namespace App\GraphQL\Mutations;

use App\Models\JobboardJob;
use App\Models\JobboardAssignment;
use App\Models\RecruitmentProcess;
use App\Models\RecruitmentProcessAssignment;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;

class RecruitmentProcessMutator {

    /**
     * Create a recruitment process from an import
     *
     * @param $root
     * @param array $args
     * @return mixed
     * @throws \Exception
     */
    public function createProcessFromImport($root, array $args)
    {

        $argsValue = isset($args['input']) ? $args['input'] : false;
        $allAssignedClientEmployees = [];

        if (!$argsValue) {
            throw new \Exception("No input provided");
        }
        $jobboardJobId = array_column($argsValue, 'jobboard_job_id')[0];

        //  Check if the jobboard_job_id is valid
        $isValidJobBoardId = JobboardJob::find($jobboardJobId);
        if (!$isValidJobBoardId) {
            throw new \Exception("Jobboard Job ID is not valid");
        }

        $codes = array_column($argsValue, 'code');
        $existingProcesses = RecruitmentProcess::where('jobboard_job_id', $jobboardJobId)
            ->whereIn('code', $codes)
            ->get()->keyBy('code');
        // data to be inserted
        $recruitmentProcessArray = [];
        $recruitmentProcessAssignmentArray = [];
        foreach ($argsValue as $value) {
            // get all assignedClientEmployees for each process
            foreach ($value['assignedClientEmployees'] as $employee) {
                if(isset($employee['id']))
                {
                    $allAssignedClientEmployees[] = $employee['id'];
                }
            }

            $existingProcess = $existingProcesses->get($value['code']);
            if (!$existingProcess) {

                $recruitmentProcessId = Str::uuid();
                $recruitmentProcessArray[] = [
                    'jobboard_job_id' => $jobboardJobId,
                    'code' => $value['code'] ?? null,
                    'name' => $value['name'] ?? null,
                    'desc' => $value['desc'] ?? null,
                    'leader_id' => $value['leader_id'] ?? null,
                    'id' => $recruitmentProcessId
                ];
                if(isset($value['assignedClientEmployees']))
                {
                    foreach ($value['assignedClientEmployees'] as $assignedClientEmployee) {
                        $recruitmentProcessAssignmentArray[] = [
                            'recruitment_process_id' => $recruitmentProcessId,
                            'client_employee_id' => $assignedClientEmployee['id'],

                        ];
                    }
                }
            }
        }
        if(count($allAssignedClientEmployees) >= 0)
        {
            $jobboardAssClientEmployeeIds = JobboardAssignment::where('jobboard_job_id',$jobboardJobId)->pluck('client_employee_id')->toArray();

            //left join to get the employee that need to be assigned ( employee has been assigned not need to be assigned again)
            $employeeNeedSave = array_values(array_unique(array_diff($allAssignedClientEmployees, array_intersect($jobboardAssClientEmployeeIds, $allAssignedClientEmployees))));
            $jobboardAssignmentArray = [];
            foreach ($employeeNeedSave as $employee) {
                $jobboardAssignmentArray[] = [
                    'id' => Str::uuid(),
                    'jobboard_job_id' => $jobboardJobId,
                    'client_employee_id' => $employee,
                    'client_id' => $argsValue[0]['client_id'],
                ];
            }
        }
        DB::beginTransaction();
        try {
            // Insert recruitment processes
            if (!empty($recruitmentProcessArray)) {
                RecruitmentProcess::insert($recruitmentProcessArray);
            }

            // Insert recruitment process assignments
            if (!empty($recruitmentProcessAssignmentArray)) {
                RecruitmentProcessAssignment::insert($recruitmentProcessAssignmentArray);
            }

            // Insert jobboard assignments
            if (!empty($jobboardAssignmentArray)) {
                JobboardAssignment::insert($jobboardAssignmentArray);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


        // Return the created recruitment processes
        return RecruitmentProcess::where('jobboard_job_id', $jobboardJobId)->get();
    }
}
