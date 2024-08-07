<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use App\Models\Timesheet;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SyncWorkScheduleGroupTemplateIdToTimesheets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timesheets:syncWorkScheduleGroupTemplateId';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync current work_schedule_group_template_id for all timesheet from today onwards';

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
        $today = Carbon::now()->toDateString();
        foreach (ClientEmployee::whereHas('timesheets', function (Builder $query) use($today) {
            $query->where('log_date', '>=', $today);
        })->cursor() as $employee) {
            Timesheet::where('client_employee_id', $employee->id)
                ->where('log_date', ">=", $today)
                ->update(['work_schedule_group_template_id' => $employee->work_schedule_group_template_id]);
        }
        return 1;
    }
}
