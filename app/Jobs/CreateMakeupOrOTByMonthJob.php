<?php

namespace App\Jobs;

use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\ApproveGroup;
use App\Models\ClientEmployee;
use App\Models\ClientWorkflowSetting;
use App\Models\Timesheet;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleGroup;
use App\Models\WorktimeRegister;
use App\Models\WorkTimeRegisterPeriod;
use App\Models\Client;
use App\Support\Constant;
use App\Support\PeriodHelper;
use App\Support\WorktimeRegisterHelper;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class CreateMakeupOrOTByMonthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The product instance.
     *
     * @var WorkScheduleGroup
     */
    protected $workScheduleGroup;
    protected $type;
    protected $client;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(WorkScheduleGroup $workScheduleGroup, $type, $client)
    {
        $this->workScheduleGroup = $workScheduleGroup;
        $this->type = $type;
        $this->client = $client;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client = $this->client;
        $clientWorkFlowSetting = $client->clientWorkFlowSetting;
        $workScheduleGroup = $this->workScheduleGroup;
        $workSchedules = WorkSchedule::where('work_schedule_group_id', $workScheduleGroup->id)->get()->keyBy(function (WorkSchedule $ws) {
                return $ws->schedule_date->toDateString();
            });
        ClientEmployee::where('work_schedule_group_template_id', $workScheduleGroup->work_schedule_group_template_id)
         ->chunkById(100, function ($items) use($workScheduleGroup, $workSchedules, $client, $clientWorkFlowSetting) {
             foreach ($items as $item) {
                Timesheet::where('client_employee_id', $item->id)
                ->whereBetween("log_date", [$workScheduleGroup->timesheet_from, $workScheduleGroup->timesheet_to])
                ->chunkById(100, function ($times) use($item, $workScheduleGroup, $workSchedules, $client, $clientWorkFlowSetting) {
                    foreach ($times as $time) {
                        if($workSchedules->has($time->log_date) && !empty($time->check_out)) {
                            $workSchedule = $workSchedules->get($time->log_date);
                            $timeBlock = $client->ot_min_time_block;
                            // Override
                            $workSchedule = $time->getShiftWorkSchedule($workSchedule);
                            $wsPeriod = $workSchedule->getWorkSchedulePeriodAttribute();
                            [$checkIn, $checkOut] = $time->getCheckInOutCarbonAttribute($item, $workSchedule, $timeBlock, $clientWorkFlowSetting->flexible_timesheet_setting ?? []);
                            if ($checkOut->isAfter($wsPeriod->getEnd())) {
                                $requestPeriod = Period::make($wsPeriod->getEnd(), $checkOut, Precision::SECOND);
                                $allowMinutes = PeriodHelper::countMinutes($requestPeriod);
                                if ($allowMinutes >= $timeBlock) {
                                    dispatch(new AutoGenerateOTRequest($time->id, $this->type));
                                }
                            }
                        }
                    }
                });
             }
         });
    }
}
