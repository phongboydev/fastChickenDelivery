<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EvaluationNotification extends Notification
{
    use Queueable;
    protected $evaluation;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($evaluation)
    {
        $this->evaluation = $evaluation;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase()
    {
        $evaluationGroup = $this->evaluation->evaluationGroup;
        return [
            'type' => 'evaluation',
            'messages' => [
                'trans' => 'notifications.evaluation',
                'params' => [
                    'name' => $evaluationGroup->name,
                    'period' => $evaluationGroup->period,
                ]
            ],
            'route' => '/danh-gia-ban-than',
        ];
    }
}
