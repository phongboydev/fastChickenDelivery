<?php

namespace App\GraphQL\Concerns;

use App\DTO\TimesheetSchedule;
use App\Models\ClientEmployee;
use App\Models\ClientWorkflowSetting;
use App\Models\ClientYearHoliday;
use App\Models\Timesheet;
use App\Models\WorkSchedule;
use App\Models\WorktimeRegister;
use App\Models\WorktimeRegisterCategory;
use App\Models\WorkTimeRegisterPeriod;
use App\Support\PeriodHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Spatie\Period\Exceptions\InvalidDate;
use Spatie\Period\Period;
use Spatie\Period\Precision;

trait EmployeeTimesheetResolver
{

    /**
     * @param  string  $fromDate
     * @param  string  $toDate
     * @param  array  $employeeIds
     *
     * @return array{
     *     clientEmployees: array<ClientEmployee>,
     *     timesheets: array<string, array>,
     *     schedules: array<string, array>
     * }
     */
    public function getTimesheetWorkSchedules(string $fromDate, string $toDate, array $employeeIds): array
    {
        $clientEmployees = ClientEmployee::query()
            ->whereIn('id', $employeeIds)
            ->get();

        $workFlowSettingMap = ClientWorkflowSetting::query()
            ->whereIn('client_id', $clientEmployees->pluck('client_id')->unique())
            ->get()
            ->keyBy('client_id');

        // Allow direct input
        $disabled = false;

        // get all work_schedule_group_template_id there is in client_employees
        $wsgtIds = $clientEmployees->pluck('work_schedule_group_template_id')
            ->unique()
            ->toArray();

        // get all work_schedule_group there is for each work_schedule_group_template_id
        // and in between $fromDate and $toDate
        $workSchedulesMap = WorkSchedule::query()
            ->with('workScheduleGroup')
            ->whereHas('workScheduleGroup', function ($query) use ($wsgtIds) {
                $query->whereIn('work_schedule_group_template_id', $wsgtIds);
            })
            ->whereDate('schedule_date', '>=', $fromDate)
            ->whereDate('schedule_date', '<=', $toDate)
            ->get()
            ->groupBy('workScheduleGroup.work_schedule_group_template_id');

        // Get timesheets
        $allEmployeesTimesheets = Timesheet::with('timesheetShiftMapping')
            ->with('timesheetShiftMapping.timesheetShift')
            ->whereIn('client_employee_id', $employeeIds)
            ->whereDate('log_date', '>=', $fromDate)
            ->whereDate('log_date', '<=', $toDate)
            ->get();

        // Get related worktime register
        $allEmployeesWtrs = WorktimeRegister::query()
            ->with('periods')
            ->whereIn('client_employee_id', $employeeIds)
            ->isApproved()
            ->where(function ($subQuery) {
                $subQuery->where('type', 'leave_request')
                    ->whereIn('sub_type', [
                        'authorized_leave',
                        'special_leave',
                        'unauthorized_leave',
                    ])
                    ->orWhere('type', 'overtime_request')
                    ->orWhere('type', 'congtac_request')
                    ->orWhere('type', 'makeup_request');
            })
            ->where(function ($subQuery) use ($fromDate, $toDate) {
                $subQuery
                    ->whereBetween('start_time', [
                        $fromDate,
                        $toDate,
                    ])
                    ->orWhereBetween('end_time', [
                        $fromDate,
                        $toDate,
                    ])
                    ->orWhere(function ($query) use ($fromDate) {
                        $query->where('start_time', '<=', $fromDate)
                            ->where('end_time', '>=', $fromDate);
                    })
                    ->orWhere(function ($query) use ($fromDate, $toDate) {
                        $query->whereDate('start_time', $fromDate)
                            ->orWhereDate('end_time', $fromDate);
                    });
            })
            ->get();

        /**
         * @var array<string, array>
         */
        $result = [];

        foreach ($clientEmployees as $clientEmployee) {
            $wsStart = date('Y-m-d 00:00:00', strtotime($fromDate));
            $wsEnd = date('Y-m-d 23:59:59', strtotime($toDate));

            $workSchedules = $workSchedulesMap[$clientEmployee->work_schedule_group_template_id] ?? [];
            $workSchedules = collect($workSchedules)->keyBy(function ($item) {
                return $item->schedule_date->toDateString();
            });

            $startHoliday =
                $clientEmployee->date_of_entry > $fromDate ? $clientEmployee->date_of_entry :
                    $wsStart;
            $endHoliday = $wsEnd;

            $clientHoliday = ClientYearHoliday::where('client_id', $clientEmployee->client_id)
                ->whereBetween("date", [$startHoliday, $endHoliday])
                ->get()
                ->keyBy("date");

            /**
             * Get wtrs and timesheets for this employee
             */
            $wtrs = $allEmployeesWtrs->where('client_employee_id', $clientEmployee->id);
            $timesheets = $allEmployeesTimesheets
                ->where('client_employee_id', $clientEmployee->id)
                ->keyBy('log_date');

            /** @var \App\Models\WorkTimeRegisterPeriod[]|\Illuminate\Support\Collection $leaveRequests */
            $leaveRequests = collect();
            foreach ($wtrs->where('type', 'leave_request') as $wtr) {
                $leaveRequests = $leaveRequests->concat($wtr->periods);
            }

            /** @var \App\Models\WorkTimeRegisterPeriod[]|\Illuminate\Support\Collection $leaveRequests */
            $otRequests = collect();
            foreach ($wtrs->where('type', 'overtime_request') as $wtr) {
                $otRequests = $otRequests->concat($wtr->periods);
            }

            /** @var \App\Models\WorkTimeRegisterPeriod[]|\Illuminate\Support\Collection $leaveRequests */
            $makeupRequests = collect();
            foreach ($wtrs->where('type', 'makeup_request')->where('auto_created', 0) as $wtr) {
                $makeupRequests = $makeupRequests->concat($wtr->periods);
            }

            /** @var \App\Models\WorkTimeRegisterPeriod[]|\Illuminate\Support\Collection $leaveRequests */
            // $workRequests = collect();
            $congtacRequests = collect();
            foreach ($wtrs->where('type', 'congtac_request') as $wtr) {
                $leaveRequests = $leaveRequests->concat($wtr->periods);
                $congtacRequests = $congtacRequests->concat($wtr->periods);
            }

            // Prepare loop data
            $curDate = Carbon::parse($wsStart);
            $finalDate = Carbon::parse($wsEnd);
            $calculatedSchedules = collect();

            $settings = $workFlowSettingMap->get($clientEmployee->client_id);
            $dayBeginMark = $settings ? $settings->getTimesheetDayBeginAttribute() : '00:00';

            // Loop thourgh every day in Work schedules
            while (!$curDate->isAfter($finalDate)) {
                $date = $curDate->toDateString();
                // day start end
                $dayStart = Carbon::parse($date.' '.$dayBeginMark);
                $dayEnd = $dayStart->clone()->addDay();
                $dayPeriod = PeriodHelper::makePeriod($dayStart, $dayEnd);

                /** @var Timesheet $ts */
                $ts = $timesheets->get($date);
                /** @var \App\Models\WorkSchedule $ws */
                $ws = $workSchedules->get($date);

                if (!$ws) {
                    // Warning?
                    $curDate->addDay();
                    continue;
                }

                // handle shift override
                if ($ts) {
                    $ts->is_holiday = $clientHoliday->has($ts->log_date) ? 1 : 0;
                    $ws = $ts->getShiftWorkSchedule($ws, 1);
                }
                $leaves = $leaveRequests->where('date_time_register', $curDate->toDateString());
                $curDateStr = $curDate->toDateString();

                // concat OT periods
                $hasOT = false;
                foreach ($otRequests as $otPeriod) {
                    /** @var WorkTimeRegisterPeriod $otPeriod */
                    $workPeriod = $dayPeriod->overlapSingle($otPeriod->getPeriod());
                    if (!$workPeriod || PeriodHelper::countMinutes($workPeriod) === 0) {
                        continue;
                    }
                    $breakPeriod = $dayPeriod->overlapSingle($otPeriod->break_period);
                    $hasOT = true;
                    $sub_type = 'ot_weekday';
                    if ($ws->is_off_day) {
                        $sub_type = 'ot_weekend';
                    }
                    if ($ws->is_holiday) {
                        $sub_type = 'ot_holiday';
                    }
                    $stateLabel = 'model.worktime_register.overtime_request.type.'.$sub_type;

                    $startEnd = PeriodHelper::getRoundedStartEndHourString($workPeriod);
                    $calculatedSchedules->add(new TimesheetSchedule([
                        'start' => $startEnd['start'],
                        'start_next_day' => $startEnd['start_date'] != $date ? 1 : 0,
                        'end' => $startEnd['end'],
                        'end_next_day' => $startEnd['end_date'] != $date ? 1 : 0,
                        'duration' => PeriodHelper::countHours($workPeriod) - ($breakPeriod ? PeriodHelper::countHours($breakPeriod) : 0),
                        'effective_duration' => (float) $otPeriod->so_gio_tam_tinh,
                        'disabled' => !($ws->is_off_day || $ws->is_holiday),
                        'state' => 'ot',
                        'date' => $date,
                        'state_label' => $stateLabel,
                    ]));
                }

                foreach ($makeupRequests as $makeupPeriod) {
                    $workPeriod = $dayPeriod->overlapSingle($makeupPeriod->getPeriod());
                    if (!$workPeriod || PeriodHelper::countMinutes($workPeriod) == 0) {
                        continue;
                    }

                    $hasOT = true;
                    /** @var WorkTimeRegisterPeriod $makeupPeriod */
                    $stateLabel = 'model.worktime_register.makeup_request.type.ot_makeup';
                    $startEnd = PeriodHelper::getRoundedStartEndHourString($workPeriod);
                    $calculatedSchedules->add(new TimesheetSchedule([
                        'start' => $startEnd['start'],
                        'start_next_day' => $startEnd['start_date'] != $date ? 1 : 0,
                        'end' => $startEnd['end'],
                        'end_next_day' => $startEnd['end_date'] != $date ? 1 : 0,
                        'duration' => PeriodHelper::countHours($workPeriod),
                        'effective_duration' => (float) $makeupPeriod->so_gio_tam_tinh,
                        'disabled' => !($ws->is_off_day || $ws->is_holiday),
                        'state' => 'makeup',
                        'date' => $date,
                        'state_label' => $stateLabel,
                    ]));
                }

                // IF off_day or holiday BEGIN
                // Off day should display only one state
                if ($ws->is_off_day || $ws->is_holiday) {
                    $state = $ws->is_off_day ? 'off_day' : 'holiday';
                    $hasOtherState = false;
                    $stateLabel = $state === 'off_day' ? "model.timesheets.work_status.weekly_leave" : "model.timesheets.work_status.holidays";

                    // Display wfh state when request on off day or holiday
                    $congtacRequestCurrent = $congtacRequests->where('date_time_register', $curDateStr);
                    if ($congtacRequestCurrent->count() > 0) {
                        foreach ($congtacRequestCurrent as $congTacPeriod) {
                            /** @var WorkTimeRegisterPeriod $congTacPeriod */
                            $hasOtherState = true;
                            $wtrBusiness = $congTacPeriod->worktimeRegister;
                            $type = $wtrBusiness->sub_type ? explode('_', $wtrBusiness->sub_type)[0] : '';
                            $stateLabel = 'leave_request.'.$type.'.'.$wtrBusiness->category ?? '';
                            $period = Period::make($congTacPeriod->start_datetime, $congTacPeriod->end_datetime,
                                Precision::SECOND);
                            $startEnd = PeriodHelper::getRoundedStartEndHourString($period);

                            // calculate state
                            if (in_array($wtrBusiness->sub_type, [
                                'business_trip',
                                'outside_working',
                            ])) {
                                $state = "outside";
                            } elseif (in_array($wtrBusiness->sub_type, ['wfh'])) {
                                $state = "wfh";
                            } else {
                                $state = "other";
                            }

                            $calculatedSchedules->add(
                                new TimesheetSchedule([
                                    'start' => $startEnd['start'],
                                    'start_next_day' => 0,
                                    'end' => $startEnd['end'],
                                    'end_next_day' => $congTacPeriod->next_day,
                                    'duration' => 0.0,
                                    'state' => $state,
                                    'disabled' => $wtrBusiness->sub_type !== "other",
                                    'date' => $date,
                                    'state_label' => $stateLabel,
                                ])
                            );
                        }
                    }

                    if (!$hasOtherState && !$hasOT) {
                        $calculatedSchedules->add(
                            new TimesheetSchedule([
                                'start' => '--.--',
                                'start_next_day' => 0,
                                'end' => '--.--',
                                'end_next_day' => 0,
                                'duration' => 0.0,
                                'state' => $state,
                                'disabled' => true,
                                'date' => $date,
                                'state_label' => $stateLabel,
                            ])
                        );
                    }
                    $curDate->addDay();
                    continue;
                }
                // IF off_day or holiday END

                try {
                    $wsPeriod = null;
                    if (!$ws->is_off_day && !$ws->is_holiday) {
                        $wsPeriod = $ws->work_schedule_period;
                    }
                    /** @var Period[]|Collection $leavePeriods */
                    $leavePeriods = $leaves->where('date_time_register', $date)
                        ->map(function (WorkTimeRegisterPeriod $period) {
                            return $period->getPeriod();
                        });

                    foreach ($leavePeriods as $index => $leavePeriod) {
                        $stateLabel = '';
                        $curLeave = $leaves->where('date_time_register', $date)[$index]; //WTRPeriod
                        if ($curLeave) {
                            $wtrLeave = $curLeave->worktimeRegister;
                            $type = $wtrLeave->sub_type ? explode('_', $wtrLeave->sub_type)[0] : '';
                            $category = WorktimeRegisterCategory::find($wtrLeave->category);
                            if ($category) {
                                $stateLabel = $category->category_name;
                            } else {
                                $stateLabel = 'leave_request.'.$type.'.'.$wtrLeave->category ?? '';
                            }
                        }
                        $overlapPeriod = $leavePeriod;//->overlapSingle($wsPeriod);
                        if (!$overlapPeriod) {
                            // no overlap
                            continue;
                        }
                        /** @var WorkTimeRegisterPeriod $leaveRequest */
                        $leaveRequest = $leaves->get($index);
                        $startEnd = PeriodHelper::getRoundedStartEndHourString($overlapPeriod);
                        $state = 'leave';

                        $wtr = $leaveRequest->worktimeRegister;
                        if ($wtr->type === 'leave_request') {
                            $state = (in_array($wtr->sub_type, [
                                'authorized_leave',
                                'special_leave',
                            ])) ? 'paid_leave' : 'leave';
                        } elseif ($wtr->type === 'congtac_request') {
                            if ($wtr->sub_type === 'wfh') {
                                $state = 'wfh';
                            } else {
                                $state = 'outside';
                            }
                        }
                        $calculatedSchedules->add(
                            new TimesheetSchedule([
                                'start' => $startEnd['start'],
                                'start_next_day' => $startEnd['start_date'] != $date ? 1 : 0,
                                'end' => $startEnd['end'],
                                'end_next_day' => $startEnd['end_date'] != $date ? 1 : 0,
                                'duration' => (float) $curLeave->duration,
                                'state' => $state,
                                'disabled' => true,
                                'date' => $date,
                                'state_label' => $stateLabel,
                            ])
                        );
                    }

                    if ($wsPeriod) {
                        if ($ws->start_break && $ws->end_break) {
                            $restPeriod = $ws->rest_period;
                            $leavePeriods->add($restPeriod);
                        }

                        $leavePeriods->add(Period::make($wsPeriod->getStart(), $wsPeriod->getStart(),
                            Precision::SECOND));
                        $workPeriods = PeriodHelper::subtract($wsPeriod, ...$leavePeriods);
                        foreach ($workPeriods as $workPeriod) {
                            $startEnd = PeriodHelper::getRoundedStartEndHourString($workPeriod);
                            $calculatedSchedules->add(new TimesheetSchedule([
                                'start' => $startEnd['start'],
                                'start_next_day' => $startEnd['start_date'] != $date ? 1 : 0,
                                'end' => $startEnd['end'],
                                'end_next_day' => $startEnd['end_date'] != $date ? 1 : 0,
                                'duration' => PeriodHelper::countHours($workPeriod),
                                'state' => 'work',
                                'disabled' => $disabled,
                                'date' => $date,
                                'state_label' => '',
                            ]));
                        }
                    }
                } catch (InvalidDate $e) {
                    logger()->error(__METHOD__.' '.$e->getMessage());
                }
                $curDate->addDay();
            }

            // collect result
            $result[$clientEmployee->id] = $calculatedSchedules;
        }

        // all is grouped by client_employee_id
        return [
            'clientEmployees' => $clientEmployees->keyBy('id'),
            'timesheets' => $allEmployeesTimesheets->groupBy('client_employee_id'),
            'schedules' => $result,
        ];
    }
}
