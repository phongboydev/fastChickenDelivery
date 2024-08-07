<?php

namespace App\Notifications;

use App\Support\ClientNameTrait;
use App\Support\MailEngineTrait;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\User;
use App\Models\CalculationSheetTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CalculationSheetDoubleVariableNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait;
    
    protected $calculationSheet;

    /**
     * CalculationSheetTemplateNotification constructor.
     *
     * @param Client $client
     * @param ClientEmployee $clientEmployee
     * @param SupportTicket $supportTicket
     */
    public function __construct($calculationSheet)
    {
     
        $this->calculationSheet = $calculationSheet;
    }

    public function toMail($notifiable)
    {
        $calculationSheet = $this->calculationSheet;
        $client = $calculationSheet->client;

        $appUrl = config('app.iglocal_url');

        $predefinedConfig = [
          'calculationSheetName' => $calculationSheet->name,
          'calculationSheetUrl' => url($appUrl . "/khach-hang/bang-luong/{$calculationSheet->id}/chi-tiet"),
          'client' => "[{$client['code']}] {$client['company_name']}"
        ];
        
        $subject = "[VPO] [{$client['company_name']}] (#{$client['code']}) - Error double variable in the calculationsheet {$calculationSheet->name}";
        
        return $this->getMailMessage($subject, 'INTERNAL_DEBUG_CAL_DOUBLE_VARIABLE', $predefinedConfig, 'emails.debugCalculationSheetDoubleVariable');
    }

    public function via($notifiable)
    {
        if($notifiable->is_email_notification){
            return ['mail'];
        }else{
            return [];
        }
    }

}
