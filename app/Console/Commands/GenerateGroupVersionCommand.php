<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\TimeSheet;
use App\Models\TimesheetShiftHistory;
use App\Models\TimesheetShiftHistoryVersion;
use App\Support\Constant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateGroupVersionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:groupVersion {clientCode} {fromDate} {toDate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate group version for assigned shifts in the past';

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
        $fromDate = $this->argument("fromDate");
        $toDate   = $this->argument("toDate");
        $fromDate = Carbon::parse($fromDate)->format('Y-m-d');
        $toDate = Carbon::parse($toDate)->format('Y-m-d');
        $clientCode   = $this->argument("clientCode");
        $client = Client::where('code', $clientCode)->first();
        $history_data = [];

        $version = TimesheetShiftHistoryVersion::withoutGlobalScope('client')
            ->firstOrCreate(['client_id' => $client->id, 'group_name' => 'Previous data']);


         TimeSheet::where("shift_enabled", 1)
            ->whereHas("clientEmployee", function($query) use($client) {
                $query->where("client_id", $client->id);
            })->doesntHave("timesheetShiftHistories")
            ->whereBetween("log_date", [$fromDate, $toDate])
            ->chunkById(100, function ($timesheet) use(&$history_data, $version, $client) {
                foreach($timesheet as $item) {
                    $type_history = $item->shift_is_off_day ? TimesheetShiftHistory::IS_OFF_DAY : ( $item->shift_is_holiday ? TimesheetShiftHistory::IS_HOLIDAY : TimesheetShiftHistory::WORKING);

                    $history_data[] = [
                        'id' => Str::uuid(),
                        'timesheet_id' => $item->id,
                        'timesheet_shift_id' => $item->timesheet_shift_id,
                        'type' => $type_history,
                        'updated_by' => Constant::INTERNAL_DUMMY_CLIENT_ID,
                        'version_group_id' => $version->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ];
                }
            });

         if (!empty($history_data)) {
             TimesheetShiftHistory::insert($history_data);
         }
        return 0;
    }
}
