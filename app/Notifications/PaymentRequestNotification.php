<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;


    protected string $deleted_by = '';
    protected string $payment_title = '';

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($payment_title, $deleted_by = '')
    {
        $this->payment_title = $payment_title;
        $this->deleted_by = $deleted_by;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        if ($notifiable->is_email_notification) {
            return ['mail'];
        } else {
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
        $subject = "[VPO] Notification - Payment request";
        $predefinedConfig = [
            'name' => $notifiable->name,
            'content' => $this->getContentMail(),
        ];

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.paymentRequestDeleted', $predefinedConfig);
    }

    private function getContentMail()
    {
        if ($this->deleted_by) {
            return "The payment request - {$this->payment_title} - of employee {$this->deleted_by} has been deleted.";
        } else {
            return "Your payment request - {$this->payment_title} - has been deleted.";
        }
    }
}
