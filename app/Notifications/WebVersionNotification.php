<?php

namespace App\Notifications;

use App\Models\WebVersion;
use App\Support\MailEngineTrait;
use App\Support\TranslationTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WebVersionNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait, TranslationTrait;

    protected $versions;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($versions)
    {
        //
        $this->versions = $versions;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return $this->versions->count() > 0 ? ['database'] : [];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
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

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toDatabase()
    {
        foreach ($this->versions as $version) {
            return [
                'type' => 'new_web_feature',
                'messages' => [
                    'trans' => 'notifications.new_web_feature',
                    'params' => ['featureNumber' => count($version->webFeatureSliders)]
                ],
                'route' => '/phien-ban/' . $version->id
            ];
        }
        return [];
    }
}
