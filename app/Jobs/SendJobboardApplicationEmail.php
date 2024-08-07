<?php

namespace App\Jobs;

use App\User;
use App\Models\JobboardApplication;
use App\Models\JobboardAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Notifications\JobboardApplicationNotification;

class SendJobboardApplicationEmail implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobboardApplication;

    public function __construct(
        JobboardApplication $jobboardApplication
    )
    {  
        $this->jobboardApplication = $jobboardApplication;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $jobboardAssignments = JobboardAssignment::where('jobboard_job_id', $this->jobboardApplication->jobboard_job_id)->with('user')->get();
        
        if($jobboardAssignments->isEmpty()) return;

        foreach($jobboardAssignments as $assignment){

            if($assignment->user) {
                $assignment->user->notify(new JobboardApplicationNotification($this->jobboardApplication));
            }
        }
    }
}
