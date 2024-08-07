<?php

namespace App\Notifications;

use App\Support\ClientNameTrait;
use App\Models\ClientEmployee;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Support\MailEngineTrait;
class ClientEmployeeUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable, ClientNameTrait, MailEngineTrait;
    protected $user;
    protected $clientEmployee;
    protected $action;

    /**
     * Create a new notification instance.
     *
     * @param User $user
     * @param ClientEmployee $clientEmployee
     * @param string     $action

     * @return void
     */
    public function __construct($user, $clientEmployee, string $action)
    {
        $this->user = $user;
        $this->clientEmployee = $clientEmployee;
        $this->action = $action;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        if($notifiable->is_email_notification){
            return ['mail', 'database'];
        }else{
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
        $clientCompanyName = $this->getFullname($this->clientEmployee->client);
        $clientEmployeeFullName = $this->getFullname($this->clientEmployee);

        $predefinedConfig = ["LANGUAGE" => $this->user->prefered_language ? $this->user->prefered_language : 'en'];

        $predefinedConfig = array_merge($predefinedConfig, [
            'client' => $this->clientEmployee->client,
            'employee' => $this->clientEmployee,
            'clientName' => $clientCompanyName,
            'employeeName' => $clientEmployeeFullName,
        ]);

        $subject = "[VPO] Update information from {$clientCompanyName}";

        return $this->getMailMessage($subject, 'CLIENT_EMPLOYEE_UPDATED', $predefinedConfig, 'emails.clientEmployeeUpdateInfo');
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
            'type' => 'client_employee_updated',
            'messages' => [
                'trans' => 'notifications.client_employee_updated',
                'params' => [
                    'clientName' => $this->getFullname($this->clientEmployee->client),
                    'employeeName' => $this->getFullname($this->clientEmployee),
                ]
            ],
            'route' => '/khach-hang/nhan-vien/' . $this->clientEmployee->id . '/thong-tin-co-ban',
        ];
    }

}
