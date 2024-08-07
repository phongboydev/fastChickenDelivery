<?php

namespace App\Observers;

use App\Models\SocialSecurityClaim;
use App\Models\SocialSecurityClaimTracking;
use App\Models\ClientEmployee;
use App\Models\ProvinceBank;
use App\Support\ApproveObserverTrait;

class SocialSecurityClaimObserver
{
    use ApproveObserverTrait;

    public function deleted(SocialSecurityClaim $socialSecurityClaim)
    {
        $this->deleteApprove( 'App\Models\SocialSecurityClaim', $socialSecurityClaim->id );
    }

    public function created(SocialSecurityClaim $profile)
    {
        SocialSecurityClaimTracking::create([
            'social_security_claim_id' => $profile->id,
            'content' => $profile->state,
        ]);

        $this->updateClientEmployeeBank($profile);
    }

    public function updating(SocialSecurityClaim $profile)
    {
        if ($profile->state != 'new') {

            $currentProfile = SocialSecurityClaim::where('id', $profile->id)->first();

            $isLikeStatusBefore    = $currentProfile->state == $profile->state;

            if (!$isLikeStatusBefore) {

                SocialSecurityClaimTracking::create([
                    'social_security_claim_id' => $profile->id,
                    'content' => $profile->state,
                ]);
            }
        }
    }

    public function updated(SocialSecurityClaim $profile)
    {
        $this->updateClientEmployeeBank($profile);
    }   

    protected function updateClientEmployeeBank(SocialSecurityClaim $profile)
    {
        if($profile->hinh_thuc_nhan == 'ATM - Chi trả qua ATM' && $profile->bhxh_bank_name)
        {
            $bank = ProvinceBank::select('*')->where('bank_name', $profile->bhxh_bank_name)->first();

            if($bank) {
                $clientEmployee = $profile->clientEmployee;

                if($clientEmployee) {

                    $parsedBank = explode(' - ', $profile->bhxh_bank_name);
                    
                    $clientEmployee->bank_code = $bank->bank_code;
                    $clientEmployee->bank_name = $parsedBank[0];

                    if(count($parsedBank) == 2){
                        $clientEmployee->bank_branch = $parsedBank[1];
                    }else{
                        $clientEmployee->bank_branch = null;
                    }

                    if($profile->bhxh_bank_account)
                        $clientEmployee->bank_account = $profile->bhxh_bank_account;

                    if($profile->bhxh_bank_account_number)
                        $clientEmployee->bank_account_number = $profile->bhxh_bank_account_number;

                    $clientEmployee->save();
                }
            }
        }
    }
}
