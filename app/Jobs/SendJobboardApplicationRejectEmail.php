<?php

namespace App\Jobs;

use App\Mail\JobboardApplicationRejectEmail;
use App\Models\JobboardApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendJobboardApplicationRejectEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobboardApplicationId;
    protected $isSentStatus;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct( $jobboardId, $isSentStatus)
    {
        $this->jobboardApplicationId = $jobboardId;
        $this->isSentStatus = $isSentStatus;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $jobboardApplication =  JobboardApplication::find($this->jobboardApplicationId);
        try {
            Mail::to($jobboardApplication->appliant_email)->send( new JobboardApplicationRejectEmail($jobboardApplication));

            $jobboardApplication->update(['is_sent' => 1]);

        } catch (\Throwable $th) {
            $jobboardApplication->update(['is_sent' => 0]);
            logger($th);
        }

    }

}
