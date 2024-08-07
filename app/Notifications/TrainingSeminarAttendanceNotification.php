<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingSeminarAttendanceNotification extends Notification implements ShouldQueue
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
            $subject = '[VPO] Thông báo - Bạn được điểm danh trong khóa đào tạo';
        } elseif ($this->data['state'] === 'updated') {
            $subject = '[VPO] Cập nhật - Khóa đào tạo bạn đang tham gia cập nhật thông tin điểm danh';
        } elseif ($this->data['state'] === 'deleted') {
            $subject = '[VPO] Thông báo - Trạng thái điểm danh khóa học của bạn đã bị xóa.';
        }

        return (new MailMessage)->subject($subject)->markdown('emails.trainingSeminarAttendance', $this->data);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase()
    {

        $params = [];
        $params['code'] = $this->data['training']['code'];
        if ($this->data['state'] === 'updated') {
            $params['state_old'] = $this->data['attendance']->getOriginal('state');
            $params['state_new'] = $this->data['attendance']['state'];
        } else {
            $params['state'] = $this->data['attendance']['state'];
        }
        $params['date'] = $this->data['attendance']->trainingSeminarSchedule->start_time . ' - ' . \Carbon\Carbon::parse($this->data['attendance']->trainingSeminarSchedule->end_time)->format('H:i:s');

        return [
            'type' => 'training_seminar_attendance',
            'messages' => [
                'trans' => 'notifications.training_seminar_attendance_' . $this->data['state'],
                'params' => $params
            ],
            'route' => '/dao-tao/' . $this->data['client_employee']['id']
        ];
    }
}
