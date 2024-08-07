<?php

namespace App\Notifications;

use App\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPassword;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends ResetPassword implements ShouldQueue
{

    use Queueable;

    /**
     * @param User $notifiable
     *
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $appUrl = config('app.customer_url');

        return (new MailMessage)
            ->subject('[VPO] Reset your password')
            ->line('We are sending this email because we recieved a forgot password request.')
            ->action('Reset Password', $appUrl . "/doi-mat-khau?" . http_build_query(
                    [
                        'token' => $this->token,
                        'email' => $notifiable->getEmailForPasswordReset(),
                        'client_code' => $notifiable->client ? $notifiable->client->code : "",
                        'username' => $notifiable->getShortUsernameAttribute(),
                    ]
                ))
            ->line('If you did not request a password reset, no further action is required. Please contact us if you did not submit this request.');
    }

}
