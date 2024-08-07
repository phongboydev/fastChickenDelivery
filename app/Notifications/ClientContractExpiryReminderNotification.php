<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Support\MailEngineTrait;

class ClientContractExpiryReminderNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait;
    protected $clientEmployeeContract;

    public function __construct($clientEmployeeContract) {
        $this->clientEmployeeContract = $clientEmployeeContract;
        //dd($this->clientEmployeeContract);
    }
    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        if($notifiable->is_email_notification){
            return ['mail'];
        }else{
            return [];
        }
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $predefinedConfig = array_merge([
            'director_name' => $notifiable->name,
            'contracts' => $this->clientEmployeeContract,
            'LANGUAGE' => $notifiable->prefered_language,
        ]);

        $subject = "[VPO] Client Contract Expiry Reminder";

        return $this->getMailMessage($subject, 'CLIENT_CONTRACT_EXPIRY_REMINDER', $predefinedConfig, 'emails.clientContractExpiryReminder');
    }

}
