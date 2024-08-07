<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Storage;
use App\Models\HanetLog;

use Illuminate\Console\Command;

class TidyUpdateStatusHanetLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:update_status_hannet_log';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update status hannet logs';

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
        $query = HanetLog::where('is_success', 0);
        $query->chunkById(100, function ($logs) {
            foreach ($logs as $log) {
                if(!empty($log->response_data)){
                    $data = json_decode($log->response_data);
                    if( !empty($data->returnMessage) && $data->returnMessage == 'Success'){
                        HanetLog::find($log->id)->update(['is_success' => 1]);
                        $this->line("Update id : " . $log->id);
                    }
                }
            }
        }, 'id');
    }
}
