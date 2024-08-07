<?php

namespace App\Observers;

use App\Models\ClientEmployee;
use App\Models\SocialSecurityProfile;
use App\Models\ProvinceHospital;
use App\Models\Province;

class SocialSecurityProfileObserver
{
    public function creating(SocialSecurityProfile $profile)
    {
        $this->updateBankData($profile);
    }

    public function updating(SocialSecurityProfile $profile)
    {
        $this->updateBankData($profile);
    }

    public function updated(SocialSecurityProfile $profile)
    {
        $this->updateClientEmployeeData($profile);
    }

    public function created(SocialSecurityProfile $profile)
    {
        $this->updateClientEmployeeData($profile);
    }

    protected function updateClientEmployeeData(SocialSecurityProfile $profile) 
    {
        $clientEmployee = ClientEmployee::where('id', $profile->client_employee_id)->first();

        $clientEmployee->household_code = $profile->so_ho_gia_dinh_da_cap;

        if ($profile->noi_dk_kcb_ban_dau_benh_vien) 
        {
            $provinceHospital = ProvinceHospital::select('hospital_code')->where('hospital_name', $profile->noi_dk_kcb_ban_dau_benh_vien)->first();
            
            if($provinceHospital) {
                $clientEmployee->medical_care_hospital_code = $provinceHospital->hospital_code;
                $clientEmployee->medical_care_hospital_name = $profile->noi_dk_kcb_ban_dau_benh_vien;
            }
        }

        $clientEmployee->save();
    }

    protected function updateBankData(SocialSecurityProfile $profile)
    {
        if ($profile->noi_dk_kcb_ban_dau_benh_vien) 
        {
            $province = Province::select('id')->where('province_name', $profile->noi_dk_kcb_ban_dau_tinh)->first();

            if($province) {
                $provinceHospital = ProvinceHospital::select(['hospital_code'])
                    ->where('hospital_name', $profile->noi_dk_kcb_ban_dau_benh_vien)
                    ->where('province_id', $province->id)
                    ->first();
                
                if($provinceHospital)
                    $profile->noi_dk_kcb_ban_dau_benh_vien_code = $provinceHospital->hospital_code;
            }
        }
    }

}
