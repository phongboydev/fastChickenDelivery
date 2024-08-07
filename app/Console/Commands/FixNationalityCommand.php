<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use App\Models\WorkScheduleGroup;
use App\Models\WorktimeRegister;
use DB;
use Illuminate\Console\Command;

class FixNationalityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:nationality {client_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix nationality';

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
        $clientId = $this->argument('client_id');

        $countryList = [
          'Vietnam' => 'Việt Nam',
          'Vietnamese' => 'Việt Nam',
          'Viet Nam' => 'Việt Nam',
          'Kinh' => 'Việt Nam',
          'VN' => 'Việt Nam',
          '32126' => 'Việt Nam',
          '0' => 'Việt Nam',
          'Japanese' => 'Japan',
          'Nhật Bản' => 'Japan',
          'Trung Quoc' => 'China',
          'Korean' => 'Korea, Republic of',
          'Korea' => 'Korea, Republic of',
          'Bristish' => 'British Indian Ocean Territory',
          'British' => 'British Indian Ocean Territory',
        ];

        foreach( $countryList as $wrongC => $fixC ) 
        {
          if ($clientId) {
              ClientEmployee::where('nationality', $wrongC)->where('client_id', $clientId)->update(['nationality' => $fixC]);
          }else{
            ClientEmployee::where('nationality', $wrongC)->update(['nationality' => $fixC]);
          }
        }

    }
}
