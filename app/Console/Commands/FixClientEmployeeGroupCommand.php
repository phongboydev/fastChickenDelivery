<?php

namespace App\Console\Commands;

use App\Models\ClientEmployeeGroupAssignment;
use App\Models\ClientEmployee;
use App\Models\WorkScheduleGroup;
use App\Models\WorktimeRegister;
use DB;
use Illuminate\Console\Command;

class FixClientEmployeeGroupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:client_employee_group';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix ClientEmployeeGroup';

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
        $clientEmployeeGroupAssignments = ClientEmployeeGroupAssignment::select('*')->get();

        if( $clientEmployeeGroupAssignments ) {

          $deletedIds = [];

          foreach( $clientEmployeeGroupAssignments as $clientEmployeeGroupAssignment ) 
          {
            if(!$clientEmployeeGroupAssignment->clientEmployee) 
            {
              $deletedIds[] = $clientEmployeeGroupAssignment->client_employee_id;

              $this->info("Deleted client employee ... " . $clientEmployeeGroupAssignment->client_employee_id);
            }
          }

          if($deletedIds) 
          {
            ClientEmployeeGroupAssignment::whereIn('client_employee_id', $deletedIds)->delete();
          }

          $this->info("Completed");
        }
    }
}
