<?php

namespace App\Notifications;

use App\User;
use App\Models\Approve;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\Constant;

class IglocalImportClientEmployeeNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $approve;
    protected $status;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($approve, $status)
    {
        $this->approve = $approve;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $mailMessage = new MailMessage();

        $approveContent = json_decode($this->approve->content, true);

        $client = '[' . $approveContent['code'] . ']' . $approveContent['company_name'];
        $comment = $this->approve->approved_comment ? $this->approve->approved_comment : false;

        return ($mailMessage)
            ->subject('[VPO][IGLOCAL] Yêu cầu import nhân viên khách khàng của bạn: ' . $client)
            ->markdown('emails.iglocalImportClientEmployee', [
                'client' => $client,
                'status' => $this->status,
                'comment' => $comment
            ]);
    }
}
