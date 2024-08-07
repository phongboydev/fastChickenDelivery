<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\JobboardApplication;
use FontLib\Table\Type\loca;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Support\MailEngineTrait;

class JobboardApplicationConfirmEmail extends Mailable
{
    use Queueable, SerializesModels, MailEngineTrait;

    protected $jobboardApplication;
    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct(JobboardApplication $jobboardApplication)
    {
        $this->jobboardApplication = $jobboardApplication;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $appUrl = config('app.customer_url');


        $senderEmail = config("mail.from.address");
        $senderName  = config("mail.from.name");
        $company = Client::find($this->jobboardApplication->client_id)->company_name;
        $title = $this->jobboardApplication->jobboardJob()->first()->position;
        $subject = 'Thank you for applying for the ' . $title . ' position at ' . $company . '!';

        $predefinedConfig = [
            "LANGUAGE" => "en",
            "company" => $company,
            "position" => $title,
            'appUrl' => $appUrl,

        ];

        $mail = $this->from($senderEmail, $senderName)->subject($subject);

        $compiledTemplate = $this->getTemplate('JOBBOARD_APPLICATION_CONFIRM_MAIL', $predefinedConfig);

        if($compiledTemplate) {
            return $this->html($compiledTemplate);
        }else{
            return $mail->markdown('emails.jobboardApplicationConfirm', $predefinedConfig);
        }
        // return $this->view('view.name');
    }
}
