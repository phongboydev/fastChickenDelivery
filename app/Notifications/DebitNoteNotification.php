<?php

namespace App\Notifications;

use App\Support\ClientNameTrait;
use App\Models\DebitNote;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DebitNoteNotification extends Notification implements ShouldQueue
{
    use Queueable, ClientNameTrait;
    protected $debitNote;

    /**
     * SupportTicketEmailNotification constructor.
     *
     * @param DebitNote $debitNote
     */
    public function __construct($debitNote)
    {
        $this->debitNote = $debitNote;
    }

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

        return [
            'type' => 'debitnote',
            'messages' => [
                'trans' => 'notifications.debitnote_export',
                'params' => [
                    'debitnoteCode' => $this->debitNote->batch_no,
                    'calculationSheetName' => $this->debitNote->calculationSheet['name'],
                ]
            ],
            'route' => '/quan-ly-debitnote',
        ];
    }
}
