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

class CalculationSheetTemplateNotification extends Notification implements ShouldQueue
{
    use Queueable, ClientNameTrait, MailEngineTrait;
    protected $client;
    protected $leader;
    protected $creator;
    protected $calculationSheetTemplate;

    /**
     * CalculationSheetTemplateNotification constructor.
     *
     * @param Client $client
     * @param ClientEmployee $clientEmployee
     * @param SupportTicket $supportTicket
     */
    public function __construct($leader, $creator, $calculationSheetTemplate)
    {
        $this->leader = $leader;
        $this->creator = $creator;
        $this->calculationSheetTemplate = $calculationSheetTemplate;
    }

    public function toMail($notifiable)
    {
        $client = Client::where('id', $this->calculationSheetTemplate->client_id)->first();

        $predefinedConfig = ["LANGUAGE" => $this->leader->prefered_language ? $this->leader->prefered_language : 'en'];

        $creator = '[' . $this->creator->code . ']' . $this->creator->name;

        $predefinedConfig = array_merge($predefinedConfig, [
            'client' => $client,
            'creator' => $creator,
            'leader' => $this->leader->name,
            'template' => $this->calculationSheetTemplate
        ]);
        
        $subject = "[VPO] Payroll template for [{$client->company_name}] (#{$client->code})";
        
        return $this->getMailMessage($subject, 'INTERNAL_CREATE_CAL_TEMPLATE', $predefinedConfig, 'emails.calculationSheetTemplate');
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
