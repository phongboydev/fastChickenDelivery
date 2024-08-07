<?php

namespace App\Console\Commands;

use App\User;
use App\Models\Client;
use App\Models\ApproveFlow;
use App\Models\ApproveFlowUser;

use Illuminate\Console\Command;

class TidyUpdateDefaultPermissionManageFormula extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:updateDefaultPermissionManageFormula';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'updateDefaultPermissionManageFormula';

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
      Client::query()
            ->chunkById(100, function ($clients) {
                foreach ($clients as $client) 
                {
                    $approveFlows = ApproveFlow::where('client_id', $client->id)
                                            ->where('step', 1)
                                            ->where('flow_name', 'manage-payroll')->with('approveFlowUsers')->get();
                    if($approveFlows->isNotEmpty())
                      foreach($approveFlows as $approveFlow) 
                      {
                        if($approveFlow->approveFlowUsers) 
                          foreach($approveFlow->approveFlowUsers as $approveFlowUser) 
                          {
                            $user = $approveFlowUser->user;
                            
                            if ($user && !$user->hasPermissionTo("manage-formula"))
                            {
                              $this->line("Processed updating manage-formula permission for user ... " . $user->id);
                              $user->givePermissionTo('manage-formula');
                            }
                          }
                      }
                }
            }, 'id');
    }
}
