<?php

namespace App\Notifications;

use App\Support\ClientNameTrait;
use App\Support\MailEngineTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SocialSecurityProfileRequestNotification extends Notification implements ShouldQueue
{
    use Queueable, ClientNameTrait, MailEngineTrait;
    protected $socialSecurityProfileRequest;

    /**
     * SocialSecurityProfileNotification constructor.
     */
    public function __construct($socialSecurityProfileRequest)
    {
        $this->socialSecurityProfileRequest = $socialSecurityProfileRequest;
    }

    public function toMail($notifiable)
    {
        $appIglocalUrl = config('app.iglocal_url');
        $appCustomerUrl = config('app.client_url');

        $predefinedConfig = ["LANGUAGE" => $notifiable->prefered_language ? $notifiable->prefered_language : 'en'];

        $tinh_trang_giai_quyet_ho_so = $this->socialSecurityProfileRequest->tinh_trang_giai_quyet_ho_so;

        $client = $this->socialSecurityProfileRequest->client;

        $urlDetail = $notifiable->is_internal ? url("$appIglocalUrl/khach-hang/{$client['id']}/ke-khai-bao-hiem/{$this->socialSecurityProfileRequest->id}/chi-tiet") : url("$appCustomerUrl/quan-ly-ke-khai-bao-hiem/{$this->socialSecurityProfileRequest->id}/chi-tiet");

        $predefinedConfig = array_merge($predefinedConfig, [
            'notifiable' => $notifiable,
            'company' => "[{$client['code']}]{$client['company_name']}",
            'creator' => $this->socialSecurityProfileRequest->creator,
            'profileRequest' => $this->socialSecurityProfileRequest,
            'urlDetail' => $urlDetail
        ]);

        $emailTemplate = '';

        switch($tinh_trang_giai_quyet_ho_so) {
            case 'cho_phe_duyet_noi_bo':
                $emailTemplate = 'INTERNAL_SOCIAL_SECURITY_600_CHO_PHE_DUYET_NOI_BO';
                break;
            case 'da_ke_khai_va_luu_tam_ho_so';
                $emailTemplate = 'INTERNAL_SOCIAL_SECURITY_600_DA_KE_KHAI_THANH_CONG';
                break;
            case 'da_co_ket_qua';
                $emailTemplate = 'INTERNAL_SOCIAL_SECURITY_600_DA_CO_KET_QUA';
                break;
            case 'ho_so_ke_khai_loi';
                $emailTemplate = 'INTERNAL_SOCIAL_SECURITY_600_KE_KHAI_LOI';
                break;
            default:
                $emailTemplate = 'INTERNAL_SOCIAL_SECURITY_600_MOI';
                break;
        }

        $subject = '[VPO][BHXH] 600 - Báo tăng, báo giảm, điều chỉnh đóng BHXH, BHYT, BHTN, BHTNLĐ, BNN';

        return $this->getMailMessage($subject, $emailTemplate, $predefinedConfig, 'emails.socialSecurityProfileRequest');
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

    public function via($notifiable)
    {
        return ['mail'];
    }
}