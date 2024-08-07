<?php

namespace App\Console\Commands;

use App\Models\Approve;
use App\Models\ApproveFlow;
use Illuminate\Console\Command;

class TidyRemoveDoubleApprove extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:removeDoubleApprove {client_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RemoveDoubleApprove';

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
        $this->validateIsSetFinalStep();
        $this->deleteByCase1();
        $this->deleteByCase2();
        
    }

    protected function validateIsSetFinalStep() {

        $clientId = $this->argument("client_id");
        
        $queryApproveGroups = Approve::selectRaw('created_at, approved_at, declined_at, type, MAX(step) AS max_step, is_final_step, approve_group_id, client_employee_group_id')
            ->groupBy(['approve_group_id'])
            ->whereNotNull('approve_group_id');

        if($clientId) {
            $queryApproveGroups->where('client_id', $clientId);   
        }

        $approveGroups = $queryApproveGroups->get();

        if($approveGroups->isNotEmpty()){
            foreach($approveGroups as $approve) {

                $maxAproves = Approve::where('approve_group_id', $approve->approve_group_id)->where('step', $approve->max_step)->get();

                $remainApprove = $maxAproves->first();

                if($maxAproves->count() > 1) {
                    $removeApproves = $maxAproves->slice(1)->pluck('id')->all();
                    Approve::whereIn('id', $removeApproves)->delete();
                }

                if( $remainApprove->is_final_step == 0 && $remainApprove->approved_at ) 
                {
                    $maxApproveFlow = ApproveFlow::selectRaw('flow_name, MAX(step) AS max_step')
                                                ->where('group_id', $remainApprove->client_employee_group_id)
                                                ->where('flow_name', $remainApprove->type)->first();
                                                
                    if( $maxApproveFlow->max_step == $remainApprove->step ) {

                        $remainApprove->is_final_step = 1;

                        if(!$remainApprove->approved_at) 
                        {
                            $remainApprove->approved_at = $remainApprove->created_at;
                        }

                        $remainApprove->saveQuietly();
                    }
                }
            }
        }
    }

    protected function deleteByCase1() 
    {
        $clientId = $this->argument("client_id");

        $queryApproveGroups = Approve::selectRaw('step, approve_group_id')
            ->groupBy(['approve_group_id', 'step'])
            ->havingRaw('COUNT(id) > 1')
            ->whereNotNull('approve_group_id');

        if($clientId) {
            $queryApproveGroups->where('client_id', $clientId);   
        }

        $approveGroups = $queryApproveGroups->get();

        if($approveGroups->isNotEmpty()){
            foreach($approveGroups as $approveGroup) {

                $allApproves = Approve::select('*')
                                ->where('step', $approveGroup->step)
                                ->where('approve_group_id', $approveGroup->approve_group_id)->get();

                $finalStep = $allApproves->firstWhere('is_final_step', 1);
                if( $finalStep ) {
                    $deletedApprove = $allApproves->where('id', '!=', $finalStep->id)->pluck('id')->all();

                    Approve::whereIn('id', $deletedApprove)->delete();
                }else{
                    $remainApprove = $allApproves->whereNotNull('approved_at')->first();

                    $deletedApprove = $allApproves->where('id', '!=', $remainApprove->id)->pluck('id')->all();

                    Approve::whereIn('id', $deletedApprove)->delete();
                }                
            }
        }
    }

    protected function deleteByCase2() 
    {
        $clientId = $this->argument("client_id");

        $queryApproveGroups = Approve::selectRaw('approve_group_id')
            ->groupBy(['approve_group_id'])
            ->whereNotNull('approve_group_id')
            ->where('is_final_step', 1);
            
        if($clientId) {
            $queryApproveGroups->where('client_id', $clientId);   
        }

        $approveGroups = $queryApproveGroups->get();

        if($approveGroups->isNotEmpty()){
            foreach($approveGroups as $approveGroup) {

                Approve::where('approve_group_id', $approveGroup->approve_group_id)->whereNull('approved_at')->delete();
            }
        }
    }
}
