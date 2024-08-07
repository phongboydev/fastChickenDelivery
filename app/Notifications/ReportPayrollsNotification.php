<?php

namespace App\Notifications;

use App\User;
use App\Models\Approve;
use App\Models\Client;
use App\Models\CalculationSheet;
use App\Models\Translation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\Constant;
use App\Support\MailEngineTrait;
use App\Support\TranslationTrait;

class ReportPayrollsNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait, TranslationTrait;
    protected $report;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($report)
    {
        $this->report = $report;
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
            return ['mail'];
        }else{
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
        // TODO enable this ApproveNotification after re-test
        
        $user = User::where('id', $this->report->original_creator_id)->with('iGlocalEmployee')->first();

        $predefinedConfig = ["LANGUAGE" => $user->prefered_language ? $user->prefered_language : 'en'];

        $subject = "[VPO][IGLOCAL] Thống kê lương - {$this->report->date_from} ~ {$this->report->date_to} ready for download";

        $predefinedConfig = array_merge($predefinedConfig, [
            'date_from' => $this->report->date_from,
            'date_to' => $this->report->date_to,
            'creator' => $user->iGlocalEmployee,
            'urlDownload'   => $this->report->path
        ]);

        return $this->getMailMessage($subject, 'INTERNAL_REPORT_PAYROLLS', $predefinedConfig, 'emails.internalReportPayrolls');

    }


    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase()
    {

      
    }
}
