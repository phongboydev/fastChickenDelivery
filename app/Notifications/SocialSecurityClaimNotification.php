<?php

namespace App\Notifications;

use App\Support\MailEngineTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SocialSecurityClaimNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait;
    protected $socialSecurityClaim;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($socialSecurityClaim)
    {   
        $this->socialSecurityClaim = $socialSecurityClaim;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $appIglocalUrl = config('app.iglocal_url');
        $appCustomerUrl = config('app.client_url');

        $predefinedConfig = ["LANGUAGE" => $notifiable->prefered_language ? $notifiable->prefered_language : 'en'];

        $client = $this->socialSecurityClaim->client;

        $urlDetail = $notifiable->is_internal ? url("$appIglocalUrl/khach-hang/social-security-claim/{$this->socialSecurityClaim->id}/chi-tiet") : url("$appCustomerUrl/social-security-claim/{$this->socialSecurityClaim->id}/chi-tiet");

        $tinh_trang_giai_quyet_ho_so = $this->socialSecurityClaim->state;

        $client = $this->socialSecurityClaim->client;
        $clientEmployee = $this->socialSecurityClaim->clientEmployee;

        $predefinedConfig = array_merge($predefinedConfig, [
            'notifiable' => $notifiable,
            'company' => "[{$client['code']}]{$client['company_name']}",
            'clientEmployee' => "[{$clientEmployee['code']}]{$clientEmployee['full_name']}",
            'profileRequest' => $this->socialSecurityClaim,
            'urlDetail' => $urlDetail
        ]);

        $emailTemplate = '';

        switch($tinh_trang_giai_quyet_ho_so) {
            case 'cho_phe_duyet_noi_bo':
                $emailTemplate = 'INTERNAL_SOCIAL_SECURITY_630_CHO_PHE_DUYET_NOI_BO';
                break;
            case 'da_ke_khai_va_luu_tam_ho_so';
                $emailTemplate = 'INTERNAL_SOCIAL_SECURITY_630_DA_KE_KHAI_THANH_CONG';
                break;
            case 'da_co_ket_qua';
                $emailTemplate = 'INTERNAL_SOCIAL_SECURITY_630_DA_CO_KET_QUA';
                break;
            case 'ho_so_ke_khai_loi';
                $emailTemplate = 'INTERNAL_SOCIAL_SECURITY_630_KE_KHAI_LOI';
                break;
            default:
                $emailTemplate = 'INTERNAL_SOCIAL_SECURITY_630_MOI';
                break;
        }

        $subject = "[VPO][BHXH] 630 - [{$clientEmployee['code']}]{$clientEmployee['full_name']}";

        return $this->getMailMessage($subject, $emailTemplate, $predefinedConfig, 'emails.socialSecurityClaim');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase()
    {

        return [];
    }
}