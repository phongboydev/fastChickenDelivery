<?php

namespace App\Notifications;

use App\Models\Client;
use App\Models\JobboardApplication;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Support\MailEngineTrait;

class JobboardApplicationNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait;
    protected JobboardApplication $application;
    protected $context;

    public function __construct(JobboardApplication $application, string $context = "created") {
        $this->application = $application;
        $this->context = $context;
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
     * @param  User  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $application = $this->application;

        $client = $application->client;
        $job    = $application->jobboardJob;

        $company = '[' . $client->code . '] ' . $client->company_name;

        $predefinedConfig = [
            'company' => $company,
            'name' =>  $application->appliant_name,
            'tel' => $application->appliant_tel,
            'email' => $application->appliant_email,
            'cover_letter' => $application->cover_letter,
            'job' => $job,
            'LANGUAGE' => $notifiable->prefered_language,
        ];

        $subject = "[VPO] Jobboard application applied";
        return $this->getMailMessage(
            $subject,
            'CLIENT_JOBBOARD_APPLICATION_CREATED',
            $predefinedConfig,
            'emails.jobboardApplicationCreated'
        );
    }

}
