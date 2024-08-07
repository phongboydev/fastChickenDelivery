<?php

namespace App\Console\Commands;

use App\Models\Approve;
use Illuminate\Console\Command;

class TidyResetClientEmployeeTarget extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:resetClientEmployeeTarget';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh target id for approve type CLIENT_UPDATE_EMPLOYEE_BASIC';

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
      Approve::select('*')->where('type', 'CLIENT_UPDATE_EMPLOYEE_BASIC')
            ->chunkById(100, function($approves) {
                foreach ($approves as $approve) {
                   if($approve->content) {
                     $content = json_decode($approve->content, true);
                     if(isset($content['id']) && $content['id']) {
                       
                      $this->line("Update target_id approve ... " . $approve->id);

                      Approve::where('id', $approve->id)->update(['target_id' => $content['id']]);
                     }
                   }
                }
            }, 'id');
    }
}
