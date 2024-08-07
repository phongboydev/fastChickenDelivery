<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientEmployeeTrainingSeminarNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $data;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
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
            return ['mail', 'database'];
        } else {
            return ['database'];
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
        if ($this->data['state'] === 'created') {
            $subject = '[VPO] Thông báo - Bạn có một khóa đào tạo mới';
        } elseif ($this->data['state'] === 'updated') {
            $subject = '[VPO] Cập nhật - Khóa đào tạo bạn đang tham gia thay đổi thông tin';
        } elseif ($this->data['state'] === 'deleted') {
            $subject = '[VPO] Thông báo - Bạn đã bị xóa khỏi danh sách khóa đào tạo';
        }

        $email = (new MailMessage)->subject($subject)->markdown('emails.clientEmployeeTrainingSeminar', $this->data);

        foreach ($this->data['training']['mediaTemp'] as $value) {
            $email->attach($value->url, ['as' => $value->file_name, 'mime' => $value->mime_type]);
        }

        return $email;
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
            'type' => 'client_employee_training_seminar',
            'messages' => [
                'trans' => 'notifications.client_employee_training_seminar_' . $this->data['state'],
                'params' => [
                    'code' => $this->data['training']['code']
                ]
            ],
            'route' => $this->data['state'] !== 'deleted' ? '/dao-tao/' . $this->data['id'] : '#',
        ];
    }
}
