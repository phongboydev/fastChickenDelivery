<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DependentNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $data;
    protected $to;
    protected $url;
    protected $subject;
    protected $messages;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($to, $data)
    {

        $this->data = $data;
        $this->to = $to;

        switch ($this->to) {
            case 'staff':
                $urlPrefix = config('app.customer_url');

                $routePrefix = $this->data['status'] === 'approved' ?
                    '/ho-so-ca-nhan/thong-tin-luong' : ($this->data['client_employee_id'] !== $this->data['creator_id'] ?
                        '/nhan-vien/' . $this->data['client_employee_id'] . '/thong-tin-luong?open_dependent=' . $this->data['id'] :
                        '/ho-so-ca-nhan/thong-tin-luong?open_dependent=' . $this->data['id']);

                $this->subject = __('mail.dependent.subject.staff', ['name' => $this->data['name_dependents']]);

                $this->messages = [
                    'type' => 'dependent',
                    'messages' => [
                        'trans' => 'dependent_' . $this->data['status'],
                        'params' => ['name' => $this->data['name_dependents']]
                    ],
                    'route' => $routePrefix
                ];
                break;
            case 'admin':
                $urlPrefix = config('app.customer_url');
                $routePrefix = "/ho-so-nguoi-phu-thuoc?open_dependent={$data->id}";
                $this->subject = __('mail.dependent.subject.admin', ['code' => $this->data->code]);
                $this->messages = [
                    'type' => 'dependent',
                    'messages' => [
                        'trans' => 'mail.dependent.subject.admin',
                        'params' => ['code' => $this->data->code]
                    ],
                    'route' => $routePrefix
                ];
                break;
            case 'internal':
                $urlPrefix = config('app.iglocal_url');
                $routePrefix = "/khach-hang/{$data->client_id}/dependent-information?open_dependent={$data->id}";
                $this->subject = __('mail.dependent.subject.internal', ['name' => $this->data->client->company_name, 'code' => $this->data->code]);
                $this->messages = [
                    'type' => 'dependent',
                    'messages' => [
                        'trans' => 'mail.dependent.subject.internal',
                        'params' => [
                            'name' => $this->data->client->company_name, 'code' => $this->data->code
                        ]
                    ],
                    'route' => $routePrefix
                ];
                break;
        }

        $this->url = $urlPrefix . $routePrefix;
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
            return ['mail'];
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
        if ($this->to === 'staff') {
            return (new MailMessage)
                ->subject($this->subject)
                ->line(__('dependent_' . $this->data['status'], ['name' => $this->data['name_dependents']]))
                ->action(__('model.buttons.detail'), $this->url);
        } elseif ($this->to === 'admin' || $this->to === 'internal') {
            return (new MailMessage)
                ->subject($this->subject)
                ->markdown('emails.dependent', [
                    'data' => $this->data,
                    'to' => $this->to,
                    'url' => $this->url
                ]);
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase()
    {
        return $this->messages;
    }
}
