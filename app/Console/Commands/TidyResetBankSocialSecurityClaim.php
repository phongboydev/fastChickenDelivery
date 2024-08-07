<?php

namespace App\Console\Commands;


use Illuminate\Console\Command;

use App\Models\SocialSecurityClaim;
use App\Models\ProvinceBank;

class TidyResetBankSocialSecurityClaim extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:resetBankSocialSecurityClaim {from_date} {to_date} {client_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'resetBankSocialSecurityClaim';

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
        $fromDate = $this->argument('from_date');
        $toDate = $this->argument('to_date');
        $clientId = $this->argument('client_id');

        $query = SocialSecurityClaim::select('*')->whereDate('created_at', '>=', $fromDate)->whereDate('created_at', '<=', $toDate);

        if($clientId) $query->where('client_id', $clientId);
        
        $query->chunkById(100, function($socialSecurityClaims) {
            foreach ($socialSecurityClaims as $socialSecurityClaim) {
                $provinceBank = ProvinceBank::select('*')->where('bank_name', $socialSecurityClaim->bhxh_bank_name)->with('province')->first();

                if(!empty($provinceBank)) {

                    $this->line("Updating socialSecurityClaim: " . $socialSecurityClaim->id);

                    $socialSecurityClaim->bhxh_bank_code = $provinceBank->bank_code;

                    if($provinceBank->province)
                        $socialSecurityClaim->bhxh_bank_province = $provinceBank->province['province_name'];

                    $socialSecurityClaim->saveQuietly();
                }
            }
        }, 'id');
    }
}
