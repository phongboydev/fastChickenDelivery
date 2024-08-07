<?php

namespace App\Mail;

use App\Models\Comment;
use App\Support\Constant;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ErrorApplicationWarningEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected Model $target;

    protected string $clientCode;

    protected string $message;

    /**
     * Create a new message instance.
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
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $predefinedConfig = [
            'code' => $this->clientCode,
            'message' => $this->message,
            'model_name' => get_class($this->target),
            'model_id' => optional($this->target)->id,
            'time' => Carbon::now(Constant::TIMESHEET_TIMEZONE)->toDateTimeString(),
        ];

        $this->subject("[VPO] WARNING ERROR!!");

        return $this->markdown('emails.errorApplicationWarning', $predefinedConfig);
    }
}
