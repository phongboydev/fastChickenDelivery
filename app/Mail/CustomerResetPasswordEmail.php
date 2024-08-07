<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

use App\User;
use App\Models\ClientEmployee;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Support\MailEngineTrait;

class CustomerResetPasswordEmail extends Mailable
{
    use Queueable, SerializesModels, MailEngineTrait;

    protected $client;
    protected $user;
    protected $clientEmployee;
    protected $password;


    public function __construct(
        Client $client,
        User $user,
        ClientEmployee $clientEmployee,
        string $password
    )
    {
        $this->client = $client;
        $this->user = $user;
        $this->clientEmployee = $clientEmployee;
        $this->password = $password;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $appUrl = config('app.customer_url');
        $urlLogin = url("$appUrl/dang-nhap");
        $user = $this->user;

        $username = Str::replaceFirst($user->client_id . '_', '', $user->username);

        $senderEmail = config("mail.from.address");
        $senderName  = config("mail.from.name");
        $subject = '[VPO] Reset password';

        $loginButton = "<a target=\"_blank\" href=\"" . $urlLogin . "\" class=\"button button-primary\" style=\"font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; box-sizing: border-box; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); color: #fff; display: inline-block; text-decoration: none; -webkit-text-size-adjust: none; background-color: #3490dc; border-top: 10px solid #3490dc; border-right: 18px solid #3490dc; border-bottom: 10px solid #3490dc; border-left: 18px solid #3490dc;\">Login</a>";

        $predefinedConfig = [
            "LANGUAGE" => ($user->prefered_language ? $user->prefered_language : 'en'),
            'client' => $this->client,
            'clientEmployee' => $this->clientEmployee,
            'username' => $username,
            'password' => $this->password,
            'loginButton' => $loginButton,
            'urlLogin' => $urlLogin,
        ];

        $mail = $this->from($senderEmail, $senderName)->subject($subject);

        $compiledTemplate = $this->getTemplate('CLIENT_EMPLOYEE_RESET_PASSWORD', $predefinedConfig);

        if($compiledTemplate) {
            return $this->html($compiledTemplate);
        }else{
            return $mail->markdown('emails.customerResetPasswordEmail', $predefinedConfig);
        }
    }
}
