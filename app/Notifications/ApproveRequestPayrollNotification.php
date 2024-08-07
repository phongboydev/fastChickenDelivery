<?php

namespace App\Notifications;

use App\User;
use App\Models\Client;
use App\Models\CalculationSheet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\MailEngineTrait;

class ApproveRequestPayrollNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait;
    protected $approve;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($approve)
    {
        $this->approve = $approve;
    }

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
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $user = User::where('id', $this->approve->creator_id)->first();

        $clientContent = json_decode($this->approve->content, true);
        $client = Client::select('*')->where('id', $clientContent['id'])->first();
        $payroll = CalculationSheet::select(['id', 'name', 'status', 'payment_period', 'month', 'year', 'date_from', 'date_to', 'created_at'])->where('id', $this->approve->target_id)->first();

        $requestContent = json_decode($this->approve->content, true);

        $approved_by = User::where('id', $this->approve->assignee_id)->first();

        $calcUrl = config('app.customer_url');

        $calcUrl .= '/yeu-cau-duyet?id=' . $this->approve->id;

        $predefinedConfig = ["LANGUAGE" => $user->prefered_language ? $user->prefered_language : 'en'];

        $detailButton = "<a target=\"_blank\" href=\"" . $calcUrl . "\" class=\"button button-primary\" style=\"font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; box-sizing: border-box; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); color: #fff; display: inline-block; text-decoration: none; -webkit-text-size-adjust: none; background-color: #3490dc; border-top: 10px solid #3490dc; border-right: 18px solid #3490dc; border-bottom: 10px solid #3490dc; border-left: 18px solid #3490dc;\">Detail</a>";

        $predefinedConfig = array_merge($predefinedConfig, [
            'client' => $client,
            'creator' => $user,
            'detailButton' => $detailButton,
            'calcUrl' => $calcUrl,
            'approved_by' => $approved_by,
            'payroll' => $payroll
        ]);

        $subject = "[VPO] Payroll Detail Approved";

        return $this->getMailMessage($subject, 'CLIENT_REQUEST_PAYROLL', $predefinedConfig, 'emails.clientApproveRequestPayroll');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toDatabase()
    {

        $requestContent = json_decode($this->approve->content, true);
        $calculationSheet = CalculationSheet::select('*')->where('id', $requestContent['id'])->first();

        return [
            'type' => 'approve',
            'messages' => [
                'trans' => 'notifications.approve.client_request_payroll',
                'params' => [
                    'calculationSheetName' => $calculationSheet->name
                ]
            ],
            'route' => 'yeu-cau-duyet?id=' . $this->approve->id
        ];
    }
}
