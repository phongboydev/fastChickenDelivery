<?php

namespace App\Observers;

use App\Models\JobboardApplication;
use App\Models\JobboardApplicationEvaluation;
use App\Notifications\JobboardApplicationNotification;
use App\Notifications\JobboardEvaluationNotification;
use Illuminate\Support\Facades\Auth;

class JobboardApplicationEvaluationObserver
{
    //
    public function created(JobboardApplicationEvaluation $jobboardApplicationEvaluation)
    {
        $jobboardApplicationEvaluation = JobboardApplicationEvaluation::with([
            'jobboardApplication.jobboardJob',
            'recruitmentProcess.assignedClientEmployees.user',
            'lastUpdatedBy'
        ])
            ->find($jobboardApplicationEvaluation->id);
        foreach ($jobboardApplicationEvaluation->recruitmentProcess->assignedClientEmployees as $assignedClientEmployee) {
            if ($assignedClientEmployee->user && $assignedClientEmployee->id != $jobboardApplicationEvaluation->lastUpdatedBy->id) {
                $assignedClientEmployee->user->notify(new JobboardEvaluationNotification($jobboardApplicationEvaluation, $assignedClientEmployee));
            }
        }
    }
}
