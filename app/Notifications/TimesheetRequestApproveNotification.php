<?php

namespace App\Notifications;

use App\Support\ClientNameTrait;
use App\Models\ClientEmployee;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TimesheetRequestApproveNotification extends Notification implements ShouldQueue
{
    use Queueable, ClientNameTrait;
    protected $clientEmployee;
    protected $workScheduleGroup;

    /**
     * Create a new notification instance.
     *
     * @param User $user
     * @param ClientEmployee $clientEmployee
     * @param string     $action

     * @return void
     */
    public function __construct($clientEmployee, $workScheduleGroup)
    {
        $this->clientEmployee = $clientEmployee;
        $this->workScheduleGroup = $workScheduleGroup;
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

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase()
    {

        return [
            'type' => 'timesheet_request_approve',
            'messages' => [
                'trans' => 'notifications.timesheet_request_approve',
                'params' => [
                    'employeeName' => $this->getFullname($this->clientEmployee),
                    'workScheduleGroupName' => $this->workScheduleGroup->name
                ]
            ],
            'route' => '/nhan-vien/' . $this->clientEmployee->id . '/timesheet',
        ];
    }

}
