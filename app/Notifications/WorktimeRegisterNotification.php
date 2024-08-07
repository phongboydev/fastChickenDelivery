<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\MailEngineTrait;

class WorktimeRegisterNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait;

    private $employee;
    private $lang = "en";
    private $type;
    private $status;

    public function __construct($employee, $type, $status, $lang = "en")
    {
        $this->employee = $employee;
        $this->type = $type;
        $this->status = $status;
        $this->lang = $lang;
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

    public function toMail($notifiable)
    {
        $predefinedConfig = ["LANGUAGE" => $this->lang];

        $predefinedConfig = array_merge($predefinedConfig, [
            'approvedBy' => $notifiable->name,
            'employeeName' => $this->employee->full_name,
            'employeeCode' => $this->employee->code,
            'type' => $this->getTypeTrans($this->type),
            'status' => $this->getStatusTrans($this->status)
        ]);

        $subject = "[VPO] Work Time Register Update";

        return $this->getMailMessage($subject, 'INTERNAL_APPROVED_CLIENT', $predefinedConfig, 'emails.worktimeRegister');
        // return (new MailMessage)
        //             ->line('The introduction to the notification.')
        //             ->action('Notification Action', url('/'))
        //             ->line('Thank you for using our application!');
    }

    public function toDatabase()
    {
        switch ($this->status) {
            case 'canceled_approved':
                return [
                    'type' => 'canceled_approval',
                    'messages' => [
                        'trans' => 'notifications.worktime_register',
                        'params' => [
                            'name' => $this->employee->full_name,
                            'code' => $this->employee->code,
                            'type' => $this->getTypeTrans($this->type),
                            'status' => $this->getStatusTrans($this->status)
                        ]
                    ],
                    'route' => $this->getRoute($this->type),
                ];

            default:
                break;
        }
    }

    private function getTypeTrans($type = "", $preferredLang = "en")
    {
        if ($preferredLang == 'vi') {
            switch ($type) {
                case 'leave_request':
                    return "Xin nghỉ phép";
                case 'congtac_request':
                    return "Đi công tác";
                default:
                    return 'Làm thêm giờ';
            }
        }

        if ($preferredLang == 'ja') {
            switch ($type) {
                case 'leave_request':
                    return "Leave request";
                case 'congtac_request':
                    return "Business Travel Request";
                default:
                    return 'Overtime Request';
            }
        }

        if ($preferredLang == 'en') {
            switch ($type) {
                case 'leave_request':
                    return "Leave request";
                case 'congtac_request':
                    return "Business Travel Request";
                default:
                    return 'Overtime Request';
            }
        }
    }

    private function getStatusTrans($status = "", $preferredLang = "en")
    {
        if ($preferredLang == 'vi') {
            switch ($status) {
                case 'canceled_approved':
                    return "Huỷ yêu cầu";
                default:
                    return "";
            }
        }

        if ($preferredLang == 'ja') {
            switch ($status) {
                case 'canceled_approved':
                    return "Canceled Approved";
                default:
                    return '';
            }
        }

        if ($preferredLang == 'en') {
            switch ($status) {
                case 'canceled_approved':
                    return "Canceled Approved";
                default:
                    return '';
            }
        }
    }

    private function getRoute($type = "")
    {
        switch ($type) {
            case 'leave_request':
                return 'quan-ly-dang-ky-cong-so-leave';
            case 'ot_request':
                return 'quan-ly-dang-ky-cong-so-ot';
            case 'congtac_request':
                return 'quan-ly-dang-ky-cong-so-cong-tac';
            default:
                return 'quan-ly-timesheet';
        }
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
}
