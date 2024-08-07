<?php

namespace App\Console\Commands;

use App\Models\SocialSecurityProfile;
use App\Models\ProvinceHospital;
use App\Models\Province;
use Illuminate\Console\Command;

class TidySocialSecurityProfileHospitalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:socialSecurityProfileHospital';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'socialSecurityProfileHospital';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        SocialSecurityProfile::select('*')->with('client')
            ->chunkById(100, function($socialSecurityProfiles) {
                foreach ($socialSecurityProfiles as $socialSecurityProfile) {

                    $this->line("Processed ... " . $socialSecurityProfile->id);

                    if (!$socialSecurityProfile->noi_dk_kcb_ban_dau_benh_vien_code && $socialSecurityProfile->noi_dk_kcb_ban_dau_benh_vien) 
                    {
                        $province = Province::select('id')->where('province_name', $socialSecurityProfile->noi_dk_kcb_ban_dau_tinh)->first();

                        if($province) {
                            $provinceHospital = ProvinceHospital::select(['hospital_code'])
                                                        ->where('hospital_name', $socialSecurityProfile->noi_dk_kcb_ban_dau_benh_vien)
                                                        ->where('province_id', $province->id)
                                                        ->first();
                            if($provinceHospital) {
                                $socialSecurityProfile->update(['noi_dk_kcb_ban_dau_benh_vien_code' => $provinceHospital->hospital_code]);
                                $this->line("Hospital code: " . $provinceHospital->hospital_code);
                            }
                        }
                    }
                }
            }, 'id');
    }
}
