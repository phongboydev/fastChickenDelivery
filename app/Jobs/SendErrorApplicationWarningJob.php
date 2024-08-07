<?php

namespace App\Jobs;

use App\Mail\ErrorApplicationWarningEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendErrorApplicationWarningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Model $target;

    protected string $clientCode;

    protected string $message;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $clientCode, Model $target, string $message)
    {
        $this->clientCode = $clientCode;
        $this->target = $target;
        $this->message = $message;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $mailToUsers = explode(';', config("mail.warning_mails"));
        if (!empty($mailToUsers)) {
            Mail::to($mailToUsers)->send(new ErrorApplicationWarningEmail($this->clientCode, $this->target, $this->message));
        }
    }
}
