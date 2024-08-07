<?php

namespace App\Console\Commands;

use App\Models\Approve;
use Illuminate\Console\Command;

class TidyResetDKCSApprove extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:reset_dkcs_approve';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove not valid DKCS approve';

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
        $results = Approve::selectRaw('approves.id, approves.type, work_time_registers.id AS work_time_register_id')->leftJoin('work_time_registers', function($join) {
            $join->on('work_time_registers.id', '=', 'approves.target_id');
          })
          ->whereIn('approves.type', ['CLIENT_REQUEST_OFF', 'CLIENT_REQUEST_OT'])->get();

        if($results->isNotEmpty()){
            foreach($results as $r){
                if(!$r->work_time_register_id){
                    Approve::where('id', $r->id)->delete();
                    $this->line("Deleted approve ... " . $r->type . " | " . $r->id);
                }
            }
        }
    }
}
