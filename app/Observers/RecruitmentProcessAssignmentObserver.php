<?php

namespace App\Observers;

use App\Jobs\SendJobboardApplicationConfirmEmail;
use App\Models\ClientEmployee;
use App\Models\JobboardApplication;
use App\Models\JobboardAssignment;
use App\Models\JobboardJob;
use App\Models\RecruitmentProcess;
use App\Models\RecruitmentProcessAssignment;
use App\Notifications\JobboardApplicationNotification;
use App\Jobs\SendJobboardApplicationEmail;

class RecruitmentProcessAssignmentObserver
{
    public function created(RecruitmentProcessAssignment $recruitmentProcessAssignment)
    {
        $recruitmentProcess = RecruitmentProcess::find($recruitmentProcessAssignment->recruitment_process_id);

        if (!$recruitmentProcess || !$recruitmentProcess->jobboard_job_id) {
            return;
        }

        $clientEmployee = ClientEmployee::find($recruitmentProcessAssignment->client_employee_id);

        if (!$clientEmployee) {
            return;
        }

        $clientId = $clientEmployee->client_id;
        $jobboardJobId = $recruitmentProcess->jobboard_job_id;

        // Check if the employee is already assigned to the jobboard job
        $jobboardAssignmentExists = JobboardAssignment::where('client_employee_id', $recruitmentProcessAssignment->client_employee_id)
            ->where('jobboard_job_id', $jobboardJobId)
            ->exists();

        if ($jobboardAssignmentExists) {
            return;
        }

        $jobboardAssignment = new JobboardAssignment();
        $jobboardAssignment->client_employee_id = $recruitmentProcessAssignment->client_employee_id;
        $jobboardAssignment->jobboard_job_id = $jobboardJobId;
        $jobboardAssignment->client_id = $clientId;

        try {
            $jobboardAssignment->save();
        } catch (\Exception $e) {
            throw new \Exception('Failed to save jobboard assignment');
        }


    }
}
