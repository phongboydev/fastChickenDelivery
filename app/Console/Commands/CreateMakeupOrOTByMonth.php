<?php

namespace App\Console\Commands;

use App\Jobs\CreateMakeupOrOTByMonthJob;
use App\Models\ClientWorkflowSetting;
use App\Models\WorkScheduleGroup;
use App\Models\WorkScheduleGroupTemplate;
use App\Support\Constant;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateMakeupOrOTByMonth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:createMakeupOrOTByMonth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Makeup and OT by month';

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
        ClientWorkflowSetting::where('auto_create_makeup_request_form', 1)->orWhere('enable_auto_generate_ot', 1)
            ->with('client')
            ->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    $type = $item->enable_auto_generate_ot ? Constant::OVERTIME_TYPE : Constant::MAKEUP_TYPE;
                    WorkScheduleGroupTemplate::where([
                        'client_id' => $item->client_id,
                        'enable_makeup_or_ot_form' => 1
                    ])
                        ->chunkById(10, function ($workTemplates) use ($item, $type) {
                            $dayBeginMark = $item->getTimesheetDayBeginAttribute();
                            $now = Carbon::now(Constant::TIMESHEET_TIMEZONE);
                            $timesheetDeadline = $now->toDateString() . ' ' . '23:59:59';
                            $end = Carbon::createFromTimeString($dayBeginMark, Constant::TIMESHEET_TIMEZONE)->addDay();
                            $delay = $now->diffInMinutes($end, false);
                            foreach ($workTemplates as $workTemplate) {
                                $workScheduleGroup = WorkScheduleGroup::where([
                                    'work_schedule_group_template_id' => $workTemplate->id,
                                    'timesheet_deadline_at' => $timesheetDeadline
                                ])->first();
                                if ($workScheduleGroup) {
                                    dispatch(new CreateMakeupOrOTByMonthJob($workScheduleGroup, $type, $item->client))->delay($delay);
                                }
                            }
                        });
                }
            });
        return 0;
    }
}
