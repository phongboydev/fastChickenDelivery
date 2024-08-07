<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeSalaryHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ImplementSalaryUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:implementSalaryUpdate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Implement salary update command';

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
     * @throws \Throwable
     */
    public function handle()
    {

        try {
            //get date by timezone vietnam
            $date = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
            // Get the employees whose salary update schedule is today
            $clients = Client::select('id')->with(['clientEmployeeSalaryHistory' => function ($q) use($date) {
                $q->where('cron_job', false);
                $q->whereDate('start_date', $date);
            }])->whereHas('clientEmployeeSalaryHistory', function($query) use($date) {
                    $query->where('cron_job', false);
                    $query->whereDate('start_date', $date);
            })->get();

            // According to the company
            foreach ($clients as $item) {
                // List of salary history by employee of each company
                foreach ($item->clientEmployeeSalaryHistory as $value) {
                    $data = [];

                    $client_employee = ClientEmployee::findOrFail($value->client_employee_id);

                    if (!empty($value->new_salary) && $value->new_salary !== $client_employee->salary) {
                        $data['salary'] = $value->new_salary;
                        $client_employee->salary = $value->new_salary;
                    }

                    if (!empty($value->new_fixed_allowance) && $value->new_fixed_allowance != $client_employee->fixed_allowance) {
                        $data['fixed_allowance'] = $value->new_fixed_allowance;
                        $client_employee->fixed_allowance = $value->new_fixed_allowance;
                    }

                    if (!empty($value->new_allowance_for_responsibilities) && $value->new_allowance_for_responsibilities != $client_employee->allowance_for_responsibilities) {
                        $data['allowance_for_responsibilities'] = $value->new_allowance_for_responsibilities;
                        $client_employee->allowance_for_responsibilities = $value->new_allowance_for_responsibilities;
                    }

                    if ($data) {
                        DB::beginTransaction();
                        if ($client_employee->saveQuietly()) {
                            // Run
                            $value->cron_job = TRUE;
                            $value->save();

                            // Old salary history will end
                            $salary_history = ClientEmployeeSalaryHistory::where('client_employee_id', $value->client_employee_id)->whereDate('start_date', '<', now())->latest('start_date')->first();
                            if ($salary_history) {
                                $salary_history->fill(['end_date' => Carbon::now()]);
                                $salary_history->save();
                            }
                            DB::commit();
                        } else {
                            DB::rollback();
                        }
                    }
                }
            }
        } catch (\Throwable $th) {
            logger()->error("command:implementSalaryUpdate: " . $th->getMessage());
            $this->error('Exit.');
            throw $th;
        }
    }
}
