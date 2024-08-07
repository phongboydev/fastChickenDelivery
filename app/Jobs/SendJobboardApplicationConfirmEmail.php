<?php

namespace App\Jobs;

use App\Mail\JobboardApplicationConfirmEmail;
use App\Models\JobboardApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendJobboardApplicationConfirmEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobboardApplication;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct( JobboardApplication $jobboardApplication)
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
        try {
            Mail::to($this->jobboardApplication->appliant_email)->send( new JobboardApplicationConfirmEmail($this->jobboardApplication));
        } catch (\Throwable $th) {
            logger($th);
        }

    }

}
