<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use App\Models\ClientEmployeeSalaryHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateSalaryHistoryForEmployee extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:salaryHistory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Generate the salary history for employees which don't have any one";

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
        ClientEmployee::doesntHave('clientEmployeeSalaryHistory')
            ->chunkById(500, function ($employees) use($now) {
                $inserts = [];
                foreach ($employees as $employee) {
                    $inserts[] = [
                        'id' => Str::uuid(),
                        'client_employee_id' => $employee->id,
                        'old_salary' => $employee->salary,
                        'new_salary' => $employee->salary,
                        'cron_job' => 1,
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                }
                ClientEmployeeSalaryHistory::insert($inserts);
            }, 'id');
        return 0;
    }
}
