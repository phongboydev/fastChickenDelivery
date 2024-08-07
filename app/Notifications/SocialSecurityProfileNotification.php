<?php

namespace App\Notifications;

use App\Support\ClientNameTrait;
use App\Support\MailEngineTrait;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SocialSecurityProfileNotification extends Notification implements ShouldQueue
{
    use Queueable, ClientNameTrait, MailEngineTrait;
    protected $client;
    protected $leader;
    protected $profile;
    protected $state;
    protected $code;
    protected $url_segment;

    /**
     * SocialSecurityProfileNotification constructor.
     */
    public function __construct($leader, $profile, $state, $code, $id)
    {
        $this->leader = $leader;
        $this->profile = $profile;
        $this->state = $state;
        $this->code = $code;
        $this->client = Client::where('id', $this->profile->client_id)->first();
        $this->url_segment = '/khach-hang/' . $this->profile->client_id . '/ke-khai-bao-hiem/' . $id . '/chi-tiet';

    }

    public function toMail($notifiable)
    {
        $appUrl = config('app.iglocal_url');

        $predefinedConfig = ["LANGUAGE" => $this->leader->prefered_language ? $this->leader->prefered_language : 'en'];

        $predefinedConfig = array_merge($predefinedConfig, [
            'state' =>  $this->state,
            'code' =>  $this->code,
            'client' => $this->client,
            'creator' => $this->profile->creator ? $this->profile->creator->name : "",
            'leader' => $this->leader->name,
            'profile' => $this->profile,
            'url' => url($appUrl . $this->url_segment)
        ]);

        $subject = "[VPO] – Social insurance [{$this->client->company_name}] (#{$this->client->code}) : " . $this->code;

        return $this->getMailMessage($subject, 'INTERNAL_SOCIAL_SECURITY_PROFILE', $predefinedConfig, 'emails.socialSecurityProfile');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase()
    {

        return [
            'type' => 'internal_social_security_profile',
            'messages' => [
                'trans' => 'notifications.internal_social_security_profile_' . $this->state,
                'params' => [
                    'clientName' => $this->client->company_name . ' (#' . $this->client->code . ')',
                    'employeeName' => $this->profile->creator ? $this->profile->creator->name : "",
                    'code' => $this->code
                ]
            ],
            'route' => $this->url_segment,
        ];
    }

    public function via($notifiable)
    {
        if ($notifiable->is_email_notification) {
            return ['mail', 'database'];
        } else {
            return ['database'];
        }
    }
}
