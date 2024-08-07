<?php

namespace App\Observers;

use Setting;
use App\Models\ClientEmployee;
use App\Models\IglocalAssignment;
use App\Models\SocialSecurityProfileHistory;
use App\Models\SocialSecurityProfile;
use App\Models\SocialSecurityProfileRequest;
use App\Models\ProvinceHospital;
use App\Models\ApproveFlow;
use App\Notifications\SocialSecurityProfileNotification;
use App\Notifications\SocialSecurityProfileRequestNotification;
use Illuminate\Support\Facades\Auth;

class SocialSecurityProfileRequestObserver
{

    public function creating(SocialSecurityProfileRequest $profile)
    {
        $creator = Auth::user();
        if ($creator) {
            $profile->creator_id = $creator->id;
        }
    }

    public function updating(SocialSecurityProfileRequest $profile)
    {
        if ($profile->tinh_trang_giai_quyet_ho_so != 'moi') {

            $currentProfile = SocialSecurityProfileRequest::where('id', $profile->id)->first();

            $isLikeStatusBefore = $currentProfile->tinh_trang_giai_quyet_ho_so == $profile->tinh_trang_giai_quyet_ho_so;

            if (!$isLikeStatusBefore) {

                SocialSecurityProfileHistory::create([
                    'client_id' => $currentProfile->client_id,
                    'profile_id' => $profile->id,
                    'old_status' => $currentProfile->tinh_trang_giai_quyet_ho_so,
                    'new_status' => $profile->tinh_trang_giai_quyet_ho_so,
                    'comment' => $profile->note,
                ]);
            }
        }
    }

    public function updated(SocialSecurityProfileRequest $profileRequest)
    {
        
        $profiles = SocialSecurityProfile::where('social_security_profile_request_id', $profileRequest->id)->get();
        
        if ($profiles->isNotEmpty()) {
            foreach ($profiles as $profile) {

                $enableNotify = Setting::get('enable_notify_after_update_bhxh');
                
                if($enableNotify == 'true') {
                    // Send to IGL
                    $assignmentUsers = IglocalAssignment::where(['client_id' => $profileRequest->client_id])
                    ->with(["user"])
                    ->has("user")
                    ->get();

                    $assignmentUsers->each(
                        function (IglocalAssignment $assignmentUser) use ($profile, $profileRequest) {
                            try {
                                $assignmentUser->user->notify(new SocialSecurityProfileNotification($assignmentUser->user, $profile, 'updated', $profileRequest->ten_ho_so, $profileRequest->id));
                            } catch (\Exception $e) {
                                logger()->warning('SocialSecurityProfileRequestObserver -> SocialSecurityProfileNotification: updated' . $e);
                            }
                        }
                    );
                }
            }
        }

        if($profileRequest->getOriginal("tinh_trang_giai_quyet_ho_so") != $profileRequest->tinh_trang_giai_quyet_ho_so) 
        {
            switch($profileRequest->tinh_trang_giai_quyet_ho_so)
            {
                case 'da_ke_khai_va_luu_tam_ho_so':
                case 'ho_so_ke_khai_loi':
                    $this->notifyIglocalAssignment($profileRequest);
                    $this->notifyClientFlowUser($profileRequest);
                    break;
                case 'cho_phe_duyet_noi_bo':
                    $this->notifyIglocalAssignment($profileRequest);
                    break;
                case 'da_co_ket_qua':
                    $this->notifyIglocalAssignment($profileRequest);
                    $this->notifyClientFlowUser($profileRequest);
                    $this->notifyCreator($profileRequest);
                    break;
            }
        }
        
    }

    public function created(SocialSecurityProfileRequest $profile)
    {
        $this->notifyIglocalAssignment($profile);
    }

    public function deleted(SocialSecurityProfileRequest $profile)
    {
    }

    protected function notifyIglocalAssignment($profile) {
        // Send to IGL
        $assignmentUsers = IglocalAssignment::where(['client_id' => $profile->client_id])
            ->with(["user"])
            ->has("user")
            ->get();

        $assignmentUsers->each(
            function (IglocalAssignment $assignmentUser) use ($profile) {
                try {
                    $assignmentUser->user->notify(new SocialSecurityProfileRequestNotification($profile));
                } catch (\Exception $e) {
                    logger()->warning('SocialSecurityProfileRequestObserver -> SocialSecurityProfileNotification: created' . $e);
                }
            }
        );
    }

    protected function notifyClientFlowUser($profile) 
    {
        $approveFlows = ApproveFlow::where('client_id', $profile->client_id)
                                    ->where('flow_name', 'CLIENT_REQUEST_SOCIAL_SECURITY_PROFILE')->has('approveFlowUsers')->get();
        
        foreach($approveFlows as $approveFlow) 
        {
            foreach($approveFlow->approveFlowUsers as $approveFlowUser) {
                try {
                    if($approveFlowUser->user)
                        $approveFlowUser->user->notify(new SocialSecurityProfileRequestNotification($profile));
                } catch (\Exception $e) {
                    logger()->warning('SocialSecurityProfileRequestObserver -> SocialSecurityProfileNotification: created' . $e);
                }
            }
        }
    }

    protected function notifyCreator($profile) 
    {
        if($profile->creator)
            $profile->creator->notify(new SocialSecurityProfileRequestNotification($profile));
    }
}
