<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TidyOptimizeProvince extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:optimizeProvince';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

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

        $this->line("Starting ... ");

        DB::statement("UPDATE provinces SET province_name = TRIM(province_name) where province_name LIKE '% '");
        DB::statement("UPDATE province_wards SET ward_name = TRIM(ward_name) where ward_name LIKE '% '");
        DB::statement("UPDATE province_districts SET district_name = TRIM(district_name) where district_name LIKE '% '");
        DB::statement("UPDATE province_banks SET bank_name = TRIM(bank_name) where bank_name LIKE '% '");
        DB::statement("UPDATE province_hospitals SET hospital_name = TRIM(hospital_name) where hospital_name LIKE '% '");

        $this->line("Completed");
    }
}
