<?php

namespace App\Notifications;

use App\Models\Client;
use App\Models\ClientAppliedDocument;
use App\Support\ClientNameTrait;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Support\MailEngineTrait;

class AppliedDocumentNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait, ClientNameTrait;
    protected ClientAppliedDocument $document;
    protected $context;

    public function __construct(ClientAppliedDocument $document, string $context = "created") {
        $this->document = $document;
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
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        /** @var Client $client */
        $client = $this->document->client;
        $trans = 'notifications.client_applied_document_new_ops';
        $route = "/quan-ly-nop-ho-so/{$this->document->id}/chi-tiet";
        if ($notifiable->is_internal) {
            $route = "/khach-hang/{$client->id}/nop-ho-so/{$this->document->id}/chi-tiet";
            if ($this->document->status === 'new') {
                $trans = 'notifications.client_applied_document_new';
            }
        } else {
            $trans = 'notifications.client_applied_document_update_ops';
        }

        return [
            'type' => 'ClientAppliedDocument',
            'messages' => [
                'trans' => $trans,
                'params' => [
                    'clientName' => $this->getFullname($client),
                ]
            ],
            'client_id' => $client->id,
            'route' => $route
        ];
    }

}
