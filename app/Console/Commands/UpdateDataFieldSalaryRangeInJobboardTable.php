<?php

namespace App\Console\Commands;

use App\Models\JobboardJob;
use Illuminate\Console\Command;

class UpdateDataFieldSalaryRangeInJobboardTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:updateDataFieldSalaryRangeInJobboardTable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to update data field salary range in jobboard table';

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
        $jobboardJobs = JobboardJob::all();
        foreach ($jobboardJobs as $jobboardJob) {
            //if jobboardJob->range_salary has type json then skip
            if (is_array($jobboardJob->salary_range)) {
                continue;
            }
            $salaryRangeOld = $jobboardJob->salary_range ?? '';
            $jobboardJob->salary_range = [
                'from' => $salaryRangeOld,
                'to' => '',
            ];
            $jobboardJob->save();
        }
        return 0;
    }
}
