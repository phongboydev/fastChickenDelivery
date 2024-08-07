<?php

namespace App\Notifications;


use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\MailEngineTrait;
class ClientEmployeeResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait;
    protected $user;
    protected $clientEmployee;
    protected $password;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $clientEmployee, $password)
    {
        $this->user = $user;
        $this->clientEmployee = $clientEmployee;
        $this->password = $password;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $appUrl = config('app.customer_url');
        $urlLogin = url("$appUrl/dang-nhap");

        $username = Str::replaceFirst($this->user->client_id . '_', '', $this->user->username);

        $predefinedConfig = ["LANGUAGE" => $this->user->prefered_language ? $this->user->prefered_language : 'en'];

        $predefinedConfig = array_merge($predefinedConfig, [
            'clientEmployee' => $this->clientEmployee,
            'username' => $username,
            'password' => $this->password,
            'urlLogin' => $urlLogin,
        ]);

        $subject = "[VPO] Create your account";

        return $this->getMailMessage($subject, 'CLIENT_EMPLOYEE_RESET_PASSWORD', $predefinedConfig, 'emails.employeeResetPasswordEmail');
    }

}
