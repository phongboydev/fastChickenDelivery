<?php

namespace App\Notifications;

use App\Support\MailEngineTrait;
use App\Support\TranslationTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobboardEvaluationNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait, TranslationTrait;

    protected $jobboardEvaluation;
    protected $assignedUser;

    protected $jobboardJob;
    protected $jobboardApplication;
    protected $lastUpdateBy;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($jobboardEvaluation, $assignedUser)
    {
        //
        $this->jobboardEvaluation = collect(json_decode($jobboardEvaluation, true));
        $this->assignedUser = $assignedUser;

        $this->jobboardApplication = $this->jobboardEvaluation['jobboard_application'];
        $this->jobboardJob = $this->jobboardApplication['jobboard_job'];
        $this->lastUpdateBy = $this->jobboardEvaluation['last_updated_by'];
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return $notifiable->is_email_notification ? ['mail', 'database'] : ['database'];
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
        $url = url("$appUrl/quan-ly-tin-tuyen-dung/applications/" . $this->jobboardJob['id'] . '/chi-tiet/' . $this->jobboardApplication['id']);
        $subject = '[VPO]Position:' . $this->jobboardJob['position'] . '- Candidate: ' . $this->jobboardApplication['appliant_name'];
        $predefinedConfig = [
            'to' => $this->assignedUser['full_name'],
            'commentor' => $this->lastUpdateBy['full_name'],
            'commented_at' => $this->jobboardEvaluation['updated_at'],
            'overview' => $this->jobboardEvaluation['overview'],
            'urlDetail' => $url,
        ];
        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.jobboardEvaluationComment', $predefinedConfig);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }

    public function toDatabase()
    {
        return [
            'type' => 'JobboardApplicationEvaluation',
            'messages' => [
                'trans' => 'notifications.jobboard_evaluation',
                'params' => [
                    'commentor' => $this->lastUpdateBy['full_name'],
                    'candidate' => $this->jobboardApplication['appliant_name']
                ]
            ],
            'route' => '/quan-ly-tin-tuyen-dung/applications/' . $this->jobboardJob['id'] . '/chi-tiet/' . $this->jobboardApplication['id']
        ];
    }
}
