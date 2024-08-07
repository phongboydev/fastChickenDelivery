<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\Timesheet;
use App\Models\WorkSchedule;
use App\Models\WorktimeRegister;
use App\Models\WorkTimeRegisterPeriod;
use App\Support\PeriodHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class TriggerUpdateColumnUnpaidLeaveHoursForOldData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updateColumnUnpaidLeaveHoursForOldData:trigger {fromDate} {toDate?} {--clientCode= : Code of client}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update value of unpaid leave column for old data';

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
        $clientCode = $this->option("clientCode");
        $from = $this->argument("fromDate");
        $to = $this->argument("toDate");
        if (empty($from)) return 0;
        $wsStart = $from . ' 00:00:00';
        $wsEnd = !empty($to) ? $to . ' 23:59:59' : "2030-12-31 23:59:59";
        $clients = collect();
        if ($clientCode) {
            $clients = Client::where('code', $clientCode)->get('id');
            if ($clients->isEmpty()) return 0;
        }
        if ($clients->isNotEmpty()) {
            $clientIds = $clients->pluck('id');
            $this->upsertWorkTimeRegisterTimesheet($wsStart, $wsEnd, $clientIds);
        } else {
            $this->upsertWorkTimeRegisterTimesheet($wsStart, $wsEnd);
        }

        return 1;
    }

    private function upsertWorkTimeRegisterTimesheet($wsStart, $wsEnd, $clientIds = [])
    {
        if (!empty($clientIds)) {
            $listClientEmployeeByClientIds = ClientEmployee::whereIn('client_id', $clientIds)->get();
        } else {
            $listClientEmployeeByClientIds = ClientEmployee::all();
        }

        foreach ($listClientEmployeeByClientIds as $employee) {
            /** @var ClientEmployee $employee */
            $listTimeSheet = $employee->timesheets()
                ->whereBetween('log_date', [
                    $wsStart,
                    $wsEnd,
                ])
                ->get()
                ->keyBy('log_date');
            $client = Client::with('clientWorkflowSetting')
                ->where('id', $employee->client_id)->first();
            foreach ($listTimeSheet as $keyDate => $timeSheet) {
                $dayBeginMark = $client->clientWorkFlowSetting->getTimesheetDayBeginAttribute();
                // Get from client config
                $dayStart = Carbon::parse($keyDate . ' ' . $dayBeginMark);
                $dayEnd = $dayStart->clone()->addDay();
                $dayPeriod = PeriodHelper::makePeriod($dayStart, $dayEnd);

                /** @var \App\Models\WorkSchedule $workSchedule */
                $workSchedule = WorkSchedule::query()
                    ->where('client_id', $employee->client_id)
                    ->where('schedule_date', $keyDate)
                    ->with('workScheduleGroup')
                    ->first();

                $workSchedule->workScheduleGroup->load('workScheduleGroupTemplate');

                $workSchedule = $timeSheet->getShiftWorkSchedule($workSchedule);


                // check if this day is off day
                $isRestDay = $workSchedule->is_off_day || $workSchedule->is_holiday;

                if ($workSchedule->is_off_day) {
                    // package Spatie/Period vẫn cần một khoản để tính overlap
                    // dummy period
                    $wsPeriod = Period::make(
                        $keyDate . ' 00:00:00',
                        $keyDate . ' 00:00:01',
                        Precision::SECOND
                    );
                } else {
                    $wsPeriod = $workSchedule->getWorkSchedulePeriodAttribute();
                }

                // Reset period
                $restPeriod = $workSchedule->rest_period;

                $khlRequests = collect();

                /** @var Timesheet $workSchedule */
                $khlWtrs = $timeSheet->getWorktimeRegistersForDay(
                    "leave_request",
                    [
                        'unauthorized_leave',
                    ],
                    $employee,
                    $dayStart,
                    $dayEnd
                );
                /** @var WorkTimeRegisterPeriod[]|Collection $requests */
                foreach ($khlWtrs as $wtr) {
                    $khlRequests = $khlRequests->concat($wtr->periods);
                }


                $khlPeriods = collect();
                if ($client->clientWorkflowSetting->enable_leave_request) {
                    $khlPeriods = $khlRequests->count() ?
                        $timeSheet->getPeriodFromWtrPeriods($khlRequests, $wsPeriod, $dayPeriod)
                        : collect();
                }
                if (!$isRestDay) {
                    // So gio xin nghỉ không HL
                    $khlHours = $khlPeriods->reduce(function ($carry, $period) use ($restPeriod) {
                        $overlapWithRest = $restPeriod->overlapSingle($period);
                        $carry += round((PeriodHelper::countMinutes($period) - PeriodHelper::countMinutes($overlapWithRest)) / 60,
                            4
                        );
                        return $carry;
                    }, 0);

                    $timeSheet->unpaid_leave_hours = round($khlHours, 2);

                } else {
                    // So gio xin nghỉ không HL
                    $khlHours = $khlPeriods->reduce(function ($carry, $period) use ($restPeriod) {
                        $overlapWithRest = $restPeriod->overlapSingle($period);
                        $carry += round((PeriodHelper::countMinutes($period) - PeriodHelper::countMinutes($overlapWithRest)) / 60,
                            2
                        );
                        return $carry;
                    }, 0);

                    $timeSheet->unpaid_leave_hours = $khlHours;
                }
                $timeSheet->save();
            }
        }
    }
}
