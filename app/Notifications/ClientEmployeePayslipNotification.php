<?php

namespace App\Notifications;

use App\User;
use App\Models\CalculationSheetVariable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\MailEngineTrait;

class ClientEmployeePayslipNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait;
    protected $clientEmployee;
    protected $calClientEmployee;
    protected $calculationSheet;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($clientEmployee, $calClientEmployee, $calculationSheet)
    {
        $this->clientEmployee = $clientEmployee;
        $this->calClientEmployee = $calClientEmployee;
        $this->calculationSheet = $calculationSheet;
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

        $predefinedConfig = ["LANGUAGE" => $notifiable->prefered_language ? $notifiable->prefered_language : 'en'];

        $payslipUrl = config('app.customer_url');

        $payslipUrl .= '/bang-luong-nhan-vien/' . $this->calculationSheet->id . '/chi-tiet';

        $detailButton = "<a target=\"_blank\" href=\"" . $payslipUrl . "\" class=\"button button-primary\" style=\"font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; box-sizing: border-box; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); color: #fff; display: inline-block; text-decoration: none; -webkit-text-size-adjust: none; background-color: #3490dc; border-top: 10px solid #3490dc; border-right: 18px solid #3490dc; border-bottom: 10px solid #3490dc; border-left: 18px solid #3490dc;\">Detail</a>";

        $predefinedConfig = array_merge($predefinedConfig, [
            'employee' => $this->clientEmployee,
            'payroll' => $this->calculationSheet,
            'detailButton' => $detailButton,
            'payslipUrl' => $payslipUrl
        ], $this->getCalculationSheetVariables($this->calculationSheet->id, $this->clientEmployee->id));

        $subject = "[VPO] Payslip notify";

        return $this->getMailMessage($subject, 'CLIENT_EMPLOYEE_PAYSLIP', $predefinedConfig, 'emails.clientEmployeePayslip');
    }

    private function getCalculationSheetVariables($calculationSheetId, $clientEmployeeId)
    {
        $variables = CalculationSheetVariable::select('*')
                        ->where('calculation_sheet_id', $calculationSheetId)
                        ->where('client_employee_id', $clientEmployeeId)->get();
        
        if($variables->isEmpty()) return [];

        $results = $variables->mapWithKeys(function ($item) {

            $value = is_numeric($item['variable_value']) ? number_format($item['variable_value'], 1) : $item['variable_value'];

            return [$item['variable_name'] => $value];
        });

        return $results->all();
    }

}
