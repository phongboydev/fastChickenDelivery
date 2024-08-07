<?php

namespace App\Console\Commands;

use App\User;
use App\Models\Client;
use App\Models\SocialSecurityProfileRequest;

use Illuminate\Console\Command;

class TidyUpdateTypeSocialSecurityProfileRequest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:update_type_social_security_profile_request';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update value type loai_ho_so_sub in SocialSecurityProfileRequest';

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
        $listType = [
            'tang_moi' => 'tm_tang_moi',
            'tang_moi_hop_dong' => 'th_tang_moi_hop_dong',
            'di_lam_lai' => 'on_di_lam_lai',
            'tang_do_chuyen_tinh' => 'tc_tang_do_chuyen_tinh',
            'tang_do_chuyen_don_vi' => 'td_tang_do_chuyen_don_vi',
            'tang_tham_gia_tnld_bnn' => 'tl_tang_tham_gia_tnld_bnn',
            'tang_tham_gia_that_nghiep' => 'tn_tang_tham_gia_that_nghiep',
            'bo_sung_tang_quy_kcb' => 'tt_bo_sung_tang_quy_kcb',
            'giam_han' => 'gh_giam_han',
            'giam_do_chuyen_tinh' => 'gc_giam_do_chuyen_tinh',
            'giam_do_chuyen_don_vi' => 'gd_giam_do_chuyen_don_vi',
            'thai_san' => 'ts_thai_san',
            'giam_tham_gia_tnld_bnn' => 'gl_giam_tham_gia_tnld_bnn',
            'giam_tham_gia_that_nghiep' => 'gn_giam_tham_gia_that_nghiep',
            'giam_tham_gia_httt' => 'gv_giam_tham_gia_httt',
            'nghi_kl' => 'kl_nghi_khong_luong',
            'nghi_do_om_dau_nghi_khong_luong' => 'of_nghi_do_om_dau_nghi_khong_luong',
            'giam_do_om_dau_or_nghi_khong_luong' => 'of_nghi_do_om_dau_nghi_khong_luong',
            'bo_sung_giam_quy_kcb' => 'tu_bo_sung_giam_quy_kcb'
        ];
        $listKey = array_keys($listType);
        SocialSecurityProfileRequest::select('*')->whereIn('loai_ho_so_sub', $listKey)
        ->chunkById(100, function($profiles) use ($listType) {
            foreach ($profiles as $profile) {
                $this->line("Update loai_ho_so_sub SocialSecurityProfileRequest ... " . $profile->id);
                SocialSecurityProfileRequest::where('id', $profile->id)->update(['loai_ho_so_sub' => $listType[$profile->loai_ho_so_sub]]);
            }
        }, 'id');
    }
}
