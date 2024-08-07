<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Models\TimesheetHanetTmp;
use App\DTO\HanetCheckinEvent;
use App\Jobs\HanetCheckinEventHandler;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Carbon\Carbon;

class HanetProcessWebHook extends Command
{
    use DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hanet:processwebhook {date?} {clientCode?} {status?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process webhook Hanet';

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
        $timesheetsHanet = new TimesheetHanetTmp();
        $timesheetsHanet = $timesheetsHanet->where('status', $status);
        
        if(!empty($clientId)) {
            $timesheetsHanet = $timesheetsHanet->where('client_id', $clientId);
        }

        if(!empty($date)) {
            $timesheetsHanet = $timesheetsHanet->whereRaw("DATE(date_time) = '{$date}'");
        }

        $timesheetsHanet = $timesheetsHanet->get();

        // print_r($timesheetsHanet);
        foreach($timesheetsHanet as $timesheet) {
            if(!empty($timesheet->data_hanet)) {
                $datarequest = json_decode($timesheet->data_hanet, true);
                $datarequest['timesheet_hanet_tmp_id'] = $timesheet->id;
                //tạo key hash để check trùng lặp khi import giống thời gian của môt nhân viên
                $datarequest['hash'] = $timesheet->client_employee_id . '_' . Carbon::parse($timesheet->date_time)->format('Y-m-d H:m');
                $timesheet->status = 1;
                $timesheet->save();
                $request = new Request($datarequest);
                $this->checkin($request);
            }
        }

        return 0;
    }

    private function checkin(Request $request)
    {
        $checkin_event = new HanetCheckinEvent($request);
        $this->dispatch(new HanetCheckinEventHandler($checkin_event));
    }
}
