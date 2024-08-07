<?php

namespace App\Notifications;

use App\User;
use App\Models\Approve;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\Constant;

class IglocalEmployeeResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $user;
    protected $employee;
    protected $password;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $employee, $password)
    {
        $this->user = $user;
        $this->employee = $employee;
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
        $mailMessage = new MailMessage();
        $appUrl = config('app.iglocal_url', '');
        $urlLogin = url("$appUrl/dang-nhap");

        $username = Str::replaceFirst('000000000000000000000000_', '', $this->user->username);

        return ($mailMessage)
            ->subject('[VPO] Khởi tạo mật khẩu')
            ->markdown('emails.iglocalEmployeeResetPasswordEmail', [
                'employee' => $this->employee,
                'username' => $username,
                'password' => $this->password,
                'urlLogin' => $urlLogin,
            ]);
    }

}
