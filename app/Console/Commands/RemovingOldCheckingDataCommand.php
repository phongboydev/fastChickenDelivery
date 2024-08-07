<?php

namespace App\Console\Commands;

use App\Exceptions\HumanErrorException;
use App\Models\Checking;
use App\Models\CheckingBackup;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemovingOldCheckingDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkingData:remove {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $date = $this->argument("date");
        if (!empty($date)) {
            $date = $date . ' 23:59:59';
        } else {
            $date = Carbon::now()->subMonthsNoOverflow(4)->endOfMonth()->toDateTimeString();
        }

        $dataBackup = [];
        $cursor = Checking::where('checking_time', '<=', $date);
        foreach ($cursor->cursor() as $checking) {
            $dataBackup[$checking->client_id][$checking->client_employee_id][] = $checking->checking_time;
        }

        if (!empty($dataBackup)) {
            foreach ($dataBackup as $client_id => $clientData) {
                $insert[] = [
                    'date' => $date,
                    'client_id' => $client_id,
                    'data' => json_encode($clientData),
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ];
            }
        }

        try {
            DB::beginTransaction();

            if (!empty($insert)) {
                CheckingBackup::insert($insert);
            }
            Checking::where('checking_time', '<=', $date)->delete();

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return 0;
    }
}
