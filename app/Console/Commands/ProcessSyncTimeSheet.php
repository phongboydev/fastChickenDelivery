<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Models\SyncTimesheetTmp;
use App\Jobs\SyncTimeCheckingJob;

class ProcessSyncTimeSheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Timesheet:sync-timesheet {date?} {clientCode?} {status?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command store timesheet from client request';

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
        $clientCode = $this->argument('clientCode');
        $date = $this->argument('date');
        $status = $this->argument('status') ? $this->argument('status') : '0';        
        $clientId = '';
        if(!empty($clientCode)) {
            $client = new Client();
            $client = $client->where('code', $clientCode)->first();
            if(!empty($client->id)) {
                $clientId = $client->id;
            }
        }

        $listTimesChecking = new SyncTimesheetTmp();
        $listTimesChecking = $listTimesChecking->where('status', $status);

        if(!empty($clientId)) {
            $listTimesChecking = $listTimesChecking->where('client_id', $clientId);
        }
        if(!empty($date)) {
            $listTimesChecking = $listTimesChecking->whereRaw("DATE(date_time) = '{$date}'");
        }

        $listTimesChecking = $listTimesChecking->get();
        foreach($listTimesChecking as $timesheet) {
            if(!empty($timesheet->data) && $timesheet->date_time) {
                $timesheet->status = 1;
                $timesheet->save();
                dispatch(new SyncTimeCheckingJob( $timesheet->id, $timesheet->status ));
            }
        }
        return 0;
    }
}
