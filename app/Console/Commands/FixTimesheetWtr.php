<?php

namespace App\Console\Commands;

use App\Models\Approve;
use App\Models\ClientEmployee;
use App\Models\WorkScheduleGroup;
use App\Models\WorktimeRegister;
use DB;
use Illuminate\Console\Command;

class FixTimesheetWtr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:timesheet_wtr {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix timesheet register';

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
        $approveId = $this->argument('id');

        $query = Approve::where('type', 'CLIENT_REQUEST_TIMESHEET')->whereRaw('target_id NOT IN (SELECT id from work_time_registers)');

        if ($approveId) {
            $query = Approve::where('id', $approveId)->whereRaw('target_id NOT IN (SELECT id from work_time_registers)');
        }

        $approves = $query->get();

        if ($approves->isNotEmpty()) {
            foreach ($approves as $approve) {
                $this->info("Processing ... ".$approve->id);

                $content = $approve->content ? json_decode($approve->content, true) : false;

                if ($content) {
                    $clientEmployee = ClientEmployee::where('id', $content['employee_id'])->first();
                    $workScheduleGroup = WorkScheduleGroup::where('id', $content["id"])->first();

                    if (!empty($clientEmployee) && !empty($workScheduleGroup)) {
                        $code = strtoupper($clientEmployee->code.'_TIMESHEET-99999');

                        DB::table((new WorktimeRegister)->getTable())->insert([
                            'id' => $approve->target_id,
                            'client_employee_id' => $content['employee_id'],
                            'code' => $code,
                            'type' => 'timesheet',
                            'sub_type' => 'timesheet',
                            'reason' => 'timesheet fix',
                            'status' => 'processing',
                            'start_time' => $workScheduleGroup->timesheet_from,
                            'end_time' => $workScheduleGroup->timesheet_to,
                        ]);
                    }
                }
            }
        }
    }
}
