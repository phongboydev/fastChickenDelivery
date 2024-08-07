<?php

namespace App\Notifications;

use App\Support\ClientNameTrait;
use App\Support\MailEngineTrait;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\EmailTemplate;
use App\User;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketEmailNotification extends Notification implements ShouldQueue
{
    use Queueable, ClientNameTrait, MailEngineTrait;

    protected $client;
    protected $clientEmployee;
    protected $supportTicket;
    protected $updateFrom;

    /**
     * SupportTicketEmailNotification constructor.
     *
     * @param Client $client
     * @param ClientEmployee $clientEmployee
     * @param SupportTicket $supportTicket
     */
    public function __construct($client, $clientEmployee, $supportTicket, $updateFrom)
    {
        $this->client = $client;
        $this->clientEmployee = $clientEmployee;
        $this->supportTicket = $supportTicket;
        $this->updateFrom = $updateFrom;
    }

    public function toMail($notifiable)
    {
        $appUrl = env('IGLOCAL_URL', '');
        $urlSupportTicket = url("$appUrl/yeu-cau-ho-tro/chi-tiet/" . (string)$this->supportTicket->id);
        $actorClientCompanyName = $this->getFullname($this->client);
        $otherClientEmployeeFullName = $this->getFullname($this->clientEmployee);
        $predefinedConfig = ["LANGUAGE" => $this->client->prefered_language ? $this->client->prefered_language : 'en'];
        $detailButton = "<a target=\"_blank\" href=\"" . $urlSupportTicket . "\" class=\"button button-primary\" style=\"font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; box-sizing: border-box; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); color: #fff; display: inline-block; text-decoration: none; -webkit-text-size-adjust: none; background-color: #3490dc; border-top: 10px solid #3490dc; border-right: 18px solid #3490dc; border-bottom: 10px solid #3490dc; border-left: 18px solid #3490dc;\">Detail</a>";
        $predefinedConfig = array_merge($predefinedConfig, [
            'client' => $this->client,
            'clientName' => $actorClientCompanyName,
            'creator' => $otherClientEmployeeFullName,
            'title' => $this->supportTicket->subject,
            'description' => $this->supportTicket->message,
            'detailButton' => $detailButton,
            'urlDetail' => $urlSupportTicket,
        ]);

        $subject = '[VPO_CLIENT] Request new support from ' . $actorClientCompanyName;
        return $this->getMailMessage($subject, 'CLIENT_SUPPORT_TICKET', $predefinedConfig, 'emails.supportTicketNewRequest');
    }

    public function via($notifiable)
    {
        if ($notifiable->is_email_notification) {
            return $this->updateFrom == 'new' ? ['mail', 'database'] : ['database'];
        } else {
            return ['database'];
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toDatabase()
    {
        $actorClientCompanyName = $this->getFullname($this->client);
        $trans = 'notifications.support_ticket_new';
        $route = '/yeu-cau-ho-tro/chi-tiet/' . $this->supportTicket->id;
        if ($this->updateFrom == 'response') {
            $trans = 'notifications.support_ticket_response';
            $route = '/yeu-cau-ho-tro/?id=' . $this->supportTicket->id;

        } else if ($this->updateFrom == 'customer_updated') {
            $trans = 'notifications.support_ticket_customer_updated';
        } else if ($this->updateFrom == 'ask_question') {
            $trans = 'notifications.support_ticket_ask_question';
        }
        return [
            'type' => 'support_ticket',
            'messages' => [
                'trans' => $trans,
                'params' => [
                    'companyName' => $actorClientCompanyName,
                    'fullName' => $this->clientEmployee->full_name
                ]
            ],
            'route' => $route,
        ];
    }
}
