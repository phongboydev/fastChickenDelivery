<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\Approve;
use App\Models\ClientLogDebug;
class ProcessResetApproveNotWork extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Approve:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command reset approve not work';

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
     * @return int
     */
    public function handle()
    {
        $now = Carbon::now();
        $timeBeforceOneHour = Carbon::now()->subMinutes(59);
        $timeBeforceTwoHour = Carbon::now()->subMinutes(121);
        $this->line($now);        
        $this->line($timeBeforceOneHour);
        $this->line($timeBeforceTwoHour);
        $approves = Approve::where(function ($query) {
            $query->where('processing_state','processing')
                ->orWhere('processing_state','fail');
            }
        )
        ->where('updated_at', '>=', $timeBeforceTwoHour)
        ->where('updated_at', '<=', $timeBeforceOneHour)
        ->get();

        foreach( $approves as $approve) {
            
            if($approve->target_type == 'App\Models\WorktimeRegister' ) {
               $this->line($approve->target_id);
               $worktimeRegister = $approve->targetWorktimeRegister()->first();
               if($worktimeRegister->status == 'approved') {
                    $datalog = [];

                    // log data work time register
                    $log_work_time_registers = [
                        'id' => $worktimeRegister->id,
                        'approved_date' => $worktimeRegister->approved_date,
                        'status' => $worktimeRegister->status
                    ];

                    $datalog['log_work_time_registers'] = $log_work_time_registers;

                    // log approve
                    $log_approve = [
                        'id' => $approve->id,
                        'processing_state' => $approve->processing_state
                    ];

                    $datalog['log_approve'] = $log_approve;
                    $this->storeLog('Reset Approve', json_encode($datalog), '', $approve->client_id);

                    // reset worktime register
                    $worktimeRegister->approved_date =  NULL;
                    $worktimeRegister->approved_by =  NULL;
                    $worktimeRegister->status =  'pending';
                    $worktimeRegister->save();

                    // reset approve
                    $approve->processing_state =  NULL;
                    $approve->save();
               }

            } else {
                $datalog = [];
                // log approve
                $log_approve = [
                    'id' => $approve->id,
                    'processing_state' => $approve->processing_state
                ];

                $datalog['log_approve'] = $log_approve;

                $this->storeLog('Reset Approve', json_encode($datalog), '', $approve->client_id);
                $approve->processing_state =  NULL;
                $approve->save();
            }
        }
        return 0;
    }

    private function storeLog($type, $data_log, $note, $client_id) {
        $logDebug = new ClientLogDebug();
        $logDebug->client_id = $client_id;
        $logDebug->type = $type;
        $logDebug->data_log = $data_log;
        $logDebug->note = $note;
        $logDebug->save();
    }
}
