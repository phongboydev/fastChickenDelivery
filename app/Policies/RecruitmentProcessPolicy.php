<?php

namespace App\Policies;

use App\Models\JobboardJob;
use App\Models\RecruitmentProcess;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RecruitmentProcessPolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-jobboard';

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function create(User $user, array $injected)
    {
        $jobboardJobId = $injected['jobboard_job_id'];
        $jobboardJob = JobboardJob::find($jobboardJobId);
        $recruitmentProcesses = RecruitmentProcess::where('jobboard_job_id', $jobboardJobId)->get();

        if (!$jobboardJob) {
            return false;
        }



        $code = $injected['code'];
        $check = $recruitmentProcesses->contains('code', $code);

        return !$check;
    }

    public function update(User $user, RecruitmentProcess $recruitmentProcess, array $injected)
    {
        $code = $injected['code'];

        if ($code != $recruitmentProcess->code) {
            $check = RecruitmentProcess::where('jobboard_job_id', $recruitmentProcess->jobboard_job_id)
                ->where('code', $code)
                ->exists();

            return !$check;
        }

        return true;

    }
}
