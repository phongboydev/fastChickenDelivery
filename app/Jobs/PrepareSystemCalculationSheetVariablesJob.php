<?php

namespace App\Jobs;

use App\Console\Commands\TidyWorktimeRegisterPeriod;
use App\Events\CalculationSheetReadyEvent;
use App\Models\Allowance;
use App\Models\AllowanceGroup;
use App\Models\CalculationSheet;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeSalaryHistory;
use App\Models\ClientSettingConditionCompare;
use App\Models\ClientWorkflowSetting;
use App\Models\Timesheet;
use App\Models\WorkSchedule;
use App\Models\WorktimeRegister;
use App\Models\WorkTimeRegisterPeriod;
use App\Models\WorkTimeRegisterTimesheet;
use App\Support\ClientHelper;
use App\Support\Constant;
use App\Support\PeriodHelper;
use App\Support\WorktimeRegisterHelper;
use Carbon\Exceptions\InvalidFormatException;
use Error;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PrepareSystemCalculationSheetVariablesJob implements ShouldQueue
{

    protected CalculationSheet $sheet;
    protected CalculationSheetClientEmployee $calculationSheetClientEmployee;

    public $timeout = 600;

    /**
     * PrepareSystemCalculationSheetVariablesJob constructor.
     *
     * @param  CalculationSheet  $sheet
     * @param  CalculationSheetClientEmployee  $calculationSheetClientEmployee
     */
    public function __construct(CalculationSheet $sheet, CalculationSheetClientEmployee $calculationSheetClientEmployee)
    {
        $this->sheet = $sheet;
        $this->calculationSheetClientEmployee = $calculationSheetClientEmployee;
    }

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    /**
     * @throws Exception
     */
    public function handle()
    {
        $calculationSheetClientEmployee = $this->calculationSheetClientEmployee;
        $calculationSheet = $this->sheet;
        $toBeInsert = [];
        $S_HAS_PREVIOUS_SALARY = 0;

        logger("CalculationSheetClientEmployeeObserver::created BEGIN");

        /**
         * get salary of this calculation period.
         */
        $startDate = ($this->sheet->other_from && $this->sheet->date_from > $this->sheet->other_from) ? $this->sheet->other_from : $this->sheet->date_from;
        $endDate = ($this->sheet->other_to && $this->sheet->date_to < $this->sheet->other_to) ? $this->sheet->other_to : $this->sheet->date_to;
        $salaryHistoryInThisPeriod = ClientEmployeeSalaryHistory::where(function ($query) use ($endDate) {
            $query->whereDate('start_date', '<=', $endDate)
                ->orWhereNull('start_date');
        })
            ->where('client_employee_id', $calculationSheetClientEmployee->client_employee_id)
            ->latest('start_date')
            ->latest('created_at')
            ->first();

        /**
         * if the salary is existed, calculating based on this.
         * else: calculating based on salary on the employee table.
         */
        if (!empty($salaryHistoryInThisPeriod)) {
            /**
             * Check this calculation sheet has multi-salary-range settings or not
             * And a second salary range exists in the salary calculation period
             */

            if (
                $calculationSheet->multiple_variables
                && $salaryHistoryInThisPeriod->start_date
                && Carbon::parse($salaryHistoryInThisPeriod->start_date)->toDateString() > $startDate
            ) {
                $previousSalaryHistory = ClientEmployeeSalaryHistory::where(function ($query) use ($endDate) {
                    $query->where('start_date', '<=', $endDate)
                        ->orWhereNull('start_date');
                })
                    ->where('client_employee_id', $calculationSheetClientEmployee->client_employee_id)
                    ->where('id', '!=', $salaryHistoryInThisPeriod->id)
                    ->latest('start_date')
                    ->latest('created_at')
                    ->first();

                /**
                 * if 2 salary ranges exist: Generate PREVIOUS variables based on calculation sheet settings
                 */
                if (!empty($previousSalaryHistory)) {
                    $S_HAS_PREVIOUS_SALARY = 1;

                    $previous_salary = $previousSalaryHistory->new_salary;

                    $previous_expiration_date = Carbon::parse($salaryHistoryInThisPeriod->start_date)->subDay()->toDateString();

                    $previous_variables = $this->loadSystemVariables($previous_salary, $previous_expiration_date);
                }
            }
            $variables = $this->loadSystemVariables($salaryHistoryInThisPeriod->new_salary);
        } else {
            $variables = $this->loadSystemVariables();
        }

        // Variable copy --- Only sys variable, other variable was copied in CalculationSheetObserver
        if (!empty($variables)) {
            foreach ($variables as $v) {
                array_push($toBeInsert, [
                    'id' => DB::raw("UUID()"),
                    'calculation_sheet_id' => $calculationSheet->id,
                    'client_employee_id' => $calculationSheetClientEmployee->client_employee_id,
                    'readable_name' => $v['readable_name'],
                    'variable_name' => $v['variable_name'],
                    'variable_value' => $v['variable_value'],
                ]);
            }
        }

        if (!empty($previous_variables)) {
            foreach ($previous_variables as $v) {
                if (in_array($v['variable_name'], $calculationSheet->multiple_variables)) {
                    array_push($toBeInsert, [
                        'id' => DB::raw("UUID()"),
                        'calculation_sheet_id' => $calculationSheet->id,
                        'client_employee_id' => $calculationSheetClientEmployee->client_employee_id,
                        'readable_name' => "Giai đoạn đầu: " . $v['readable_name'],
                        'variable_name' => 'PREVIOUS_' . $v['variable_name'],
                        'variable_value' => $v['variable_value'],
                    ]);
                }
            }
        }

        array_push($toBeInsert, [
            'id' => DB::raw("UUID()"),
            'calculation_sheet_id' => $calculationSheet->id,
            'client_employee_id' => $calculationSheetClientEmployee->client_employee_id,
            'readable_name' => 'Có 2 mức lương',
            'variable_name' => 'S_HAS_PREVIOUS_SALARY',
            'variable_value' => $S_HAS_PREVIOUS_SALARY,
        ]);


        CalculationSheetVariable::insert($toBeInsert);
        $calculationSheetClientEmployee->system_vars_ready = 1;
        $calculationSheetClientEmployee->save();

        logger("CalculationSheetClientEmployeeObserver::created END");

        $hasNotReadyRecord = CalculationSheetClientEmployee::where("calculation_sheet_id", $calculationSheet->id)
            ->notReady()
            ->exists();
        if (!$hasNotReadyRecord) {
            event(new CalculationSheetReadyEvent($calculationSheet));
        }
    }

    /**
     * Calculate System variable for this employee
     * @return array[]
     * @throws Exception
     */
    protected function loadSystemVariables($previous_salary = null, $previous_expiration_date = null): array
    {
        logger("CalculationSheetClientEmployeeObserver::loadSysteVariables BEGIN");
        $employee = $this->calculationSheetClientEmployee->clientEmployee;
        $employee->position = $employee->client_position_name ?? $employee->position;
        $employee->department = $employee->client_department_name ?? $employee->department;
        $groupTemplate = $employee->workScheduleGroupTemplate;
        logger("CalculationSheetClientEmployeeObserver::loadSysteVariables Employee", ["id" => $employee->id]);

        $calculationSheet = $this->sheet;
        $other_from = $calculationSheet->other_from;
        $other_to = $previous_expiration_date ?? $calculationSheet->other_to;
        $date_from = $calculationSheet->date_from;
        $date_to = $previous_expiration_date ?? $calculationSheet->date_to;

        logger("CalculationSheetClientEmployeeObserver::loadSysteVariables CalculationSheet", ["id" => $calculationSheet->id]);

        /** @var Collection $timesheets */
        $timesheets = $employee->timesheets()
            ->with('timesheetShiftMapping.timesheetShift')
            ->whereBetween('log_date', [
                $date_from,
                $date_to,
            ])
            ->get()
            ->keyBy('log_date');

        logger("CalculationSheetClientEmployeeObserver::loadSysteVariables TimeSheet data", [$timesheets->count()]);

        $OTTimesheets = $employee->timesheets()
            ->with(['workTimeRegisterTimesheets' => function ($query) use ($calculationSheet) {
                $query->where(function ($sub_query2) use ($calculationSheet) {
                    $sub_query2->where(function ($sub_query3) use ($calculationSheet) {
                        $sub_query3->where('month_lock', $calculationSheet->month);
                        $sub_query3->where('year_lock', $calculationSheet->year);
                    });
                    $sub_query2->orWhere(function ($sub_query3) {
                        $sub_query3->where('month_lock', 0);
                        $sub_query3->where('year_lock', 0);
                    });
                });
            }])
            ->whereBetween('log_date', [
                $other_from,
                $other_to,
            ])
            ->get()
            ->keyBy('log_date');

        $travelHours = $timesheets->sum('mission_hours');
        logger("CalculationSheetClientEmployeeObserver::loadSystemVariables Work hours", [$travelHours]);

        /** @var Collection|Timesheet[] $timesheets */
        $workSchedules = WorkSchedule::query()
            ->where('client_id', $employee->client_id)
            ->whereHas(
                'workScheduleGroup',
                function ($group) use ($employee) {
                    $group->where(
                        'work_schedule_group_template_id',
                        $employee->work_schedule_group_template_id
                    );
                }
            )
            ->whereBetween('schedule_date', [
                $date_from,
                $date_to,
            ])
            ->get()
            ->keyBy(function (WorkSchedule $ws) {
                return $ws->schedule_date->toDateString();
            });

        $OTWorkSchedules = WorkSchedule::query()
            ->where('client_id', $employee->client_id)
            ->whereHas(
                'workScheduleGroup',
                function ($group) use ($employee) {
                    $group->where(
                        'work_schedule_group_template_id',
                        $employee->work_schedule_group_template_id
                    );
                }
            )
            ->whereBetween('schedule_date', [
                $other_from,
                $other_to,
            ])
            ->get()
            ->keyBy(function (WorkSchedule $ws) {
                return $ws->schedule_date->toDateString();
            });

        // transform work schedule to timesheet shift if any
        $workSchedules->transform(function (WorkSchedule $ws) use ($timesheets) {
            $logDate = $ws->schedule_date->toDateString();
            if ($timesheets->has($logDate)) {
                /** @var Timesheet $ts */
                $ts = $timesheets->get($logDate);
                return $ts->getShiftWorkSchedule($ws);
            }
            return $ws;
        });

        // transform OT work schedule to timesheet shift if any
        $OTWorkSchedules->transform(function (WorkSchedule $ws) use ($OTTimesheets) {
            $logDate = $ws->schedule_date->toDateString();
            if ($OTTimesheets->has($logDate)) {
                /** @var Timesheet $ts */
                $ts = $OTTimesheets->get($logDate);
                return $ts->getShiftWorkSchedule($ws);
            }
            return $ws;
        });

        $totalOvertimeHours = 0.0;
        $offDayOvertimeHours = 0.0;
        $holidayOvertimeHours = 0.0;
        $satOvertimeHours = 0.0;
        $specialOvertimeHours = 0.0;
        $midnightSpecialOvertimeHours = 0.0;
        $midnightOffDayOvertimeHours = 0.0;
        $midnightHolidayOvertimeHours = 0.0;
        $totalMidnightOvertimeHours = 0.0;
        $onlyMidnightOvertimeHours = 0.0;
        $onlyMidnightOffDayOvertimeHours = 0.0;
        $onlyMidnightHolidayOvertimeHours = 0.0;

        foreach ($OTTimesheets as $item) {
            if ($OTWorkSchedules->has($item->log_date)) {
                /** @var WorkSchedule $ws */
                $ws = $OTWorkSchedules->get($item->log_date);
                if (
                    $item->overtime_hours &&
                    (empty($item->workTimeRegisterTimesheets) || !($item->workTimeRegisterTimesheets->count() > 0))
                ) { //using for old data which don't have worktime_register_timesheet
                    // S_TOTAL_WORK_HOURS_OT
                    $totalOvertimeHours += $item->overtime_hours;

                    // S_TOTAL_WORK_HOURS_OT_AT_NIGHT
                    if ($item->midnight_overtime_hours) {
                        $totalMidnightOvertimeHours += $item->midnight_overtime_hours;
                    }

                    // Số giờ làm thêm các ngày cuối tuần (ngày nghỉ theo quy định của công ty)
                    // S_WORK_HOURS_OT_AT_NIGHT_WEEKEND
                    if ($ws->is_off_day && !$ws->is_holiday) {
                        $offDayOvertimeHours += $item->overtime_hours;
                        $midnightOffDayOvertimeHours += $item->midnight_overtime_hours;
                    }

                    // Số giờ làm thêm ngày Lễ
                    // Số giờ làm thêm ban đêm ngày Lễ
                    if (!$ws->is_off_day && $ws->is_holiday) {
                        $holidayOvertimeHours += $item->overtime_hours;
                        $midnightHolidayOvertimeHours += $item->midnight_overtime_hours;
                    }

                    // S_OT_SAR - Số giờ làm thêm ngày thứ 7
                    $date = Carbon::parse($item->log_date);
                    if ($date->isSaturday()) {
                        $satOvertimeHours += $item->overtime_hours;
                    }

                    // S_SPECIAL_OT_WEEKEND - Overtime on Saturday, Sunday has the status is "Đi làm"
                    if (!$ws->is_off_day && !$ws->is_holiday && ($date->isSaturday() || $date->isSunday())) {
                        $specialOvertimeHours += ($item->overtime_hours - $item->midnight_overtime_hours);
                        $midnightSpecialOvertimeHours += $item->midnight_overtime_hours;
                    }
                } else { // new function to get OT.
                    foreach ($item->workTimeRegisterTimesheets as $wtr) {
                        if ($wtr->type == WorkTimeRegisterTimesheet::OT_TYPE) {
                            // S_TOTAL_WORK_HOURS_OT_AT_NIGHT
                            if ($wtr->time_values) {
                                $totalOvertimeHours += $wtr->time_values;
                            }

                            // Số giờ làm thêm các ngày cuối tuần (ngày nghỉ theo quy định của công ty)
                            if ($ws->is_off_day && !$ws->is_holiday) {
                                $offDayOvertimeHours += $wtr->time_values;
                            }

                            // Số giờ làm thêm ngày Lễ
                            if (!$ws->is_off_day && $ws->is_holiday) {
                                $holidayOvertimeHours += $wtr->time_values;
                            }

                            // S_OT_SAR - Số giờ làm thêm ngày thứ 7
                            $date = Carbon::parse($item->log_date);
                            if ($date->isSaturday()) {
                                $satOvertimeHours += $wtr->time_values;
                            }

                            // S_SPECIAL_OT_WEEKEND - Overtime on Saturday, Sunday has the status is "Đi làm"
                            if (!$ws->is_off_day && !$ws->is_holiday && ($date->isSaturday() || $date->isSunday())) {
                                $specialOvertimeHours += $wtr->time_values;
                            }
                        }
                        if ($wtr->type == WorkTimeRegisterTimesheet::OT_MIDNIGHT_TYPE) {
                            // S_TOTAL_WORK_HOURS_OT_AT_NIGHT
                            if ($wtr->time_values) {
                                $totalMidnightOvertimeHours += $wtr->time_values;
                            }

                            // S_WORK_HOURS_OT_AT_NIGHT_WEEKEND
                            if ($ws->is_off_day && !$ws->is_holiday) {
                                $midnightOffDayOvertimeHours += $wtr->time_values;
                            }

                            // Số giờ làm thêm ban đêm ngày Lễ
                            // S_WORK_HOURS_OT_AT_NIGHT_WEEKEND
                            if (!$ws->is_off_day && $ws->is_holiday) {
                                $midnightHolidayOvertimeHours += $wtr->time_values;
                            }

                            $date = Carbon::parse($item->log_date);
                            // S_SPECIAL_OT_AT_NIGHT_WEEKEND - Overtime on Saturday, Sunday has the status is "Đi làm"
                            // S_SPECIAL_OT_WEEKEND = TOTAL OT OF DATE - S_SPECIAL_OT_AT_NIGHT_WEEKEND
                            if (!$ws->is_off_day && !$ws->is_holiday && ($date->isSaturday() || $date->isSunday())) {
                                $midnightSpecialOvertimeHours += $wtr->time_values;
                                $specialOvertimeHours -= $wtr->time_values;
                            }
                        }
                    }
                }

                //OT only midnight
                if ($item->overtime_hours > 0 && $item->overtime_hours == $item->midnight_overtime_hours) {
                    if ($ws->is_off_day) {
                        $onlyMidnightOffDayOvertimeHours += $item->overtime_hours;
                    } elseif ($ws->is_holiday) {
                        $onlyMidnightHolidayOvertimeHours += $item->overtime_hours;
                    } else {
                        $onlyMidnightOvertimeHours += $item->overtime_hours;
                    }
                }
            }
        }
        // Số giờ làm thêm các ngày trong tuần
        $weekdayOvertimeHours = round($totalOvertimeHours - $offDayOvertimeHours - $holidayOvertimeHours, 2);

        // Số giờ làm thêm các ngày trong tuần
        $midnightWeekdayOvertimeHours = $totalMidnightOvertimeHours - ($midnightOffDayOvertimeHours + $midnightHolidayOvertimeHours);

        //calculating working hours and shift
        $workHours = $shift = $holidayShift = 0;
        foreach ($timesheets as $ts) {
            if ($workSchedules->has($ts->log_date)) {
                $ws = $workSchedules->get($ts->log_date);
                if (!$ws->is_holiday) {
                    $workHours += $ts->working_hours;
                    $shift += $ts->shift;
                } else {
                    $holidayShift += $ts->shift;
                }
            }
        }
        logger("CalculationSheetClientEmployeeObserver::loadSysteVariables Work hours", [$workHours]);

        // calculation number of is_holiday workSchedule
        $holidayHours = $workSchedules->filter(function ($v) {
            return $v->is_holiday;
        })->reduce(function ($carry, WorkSchedule $item) {
            return $carry + $item->workHours;
        }, 0);
        $workHours += $holidayHours;

        // Only apply leave request,bussiness trip for coming late and leave early
        $workTimeRegisterPeriods = WorkTimeRegisterPeriod::whereHas('worktimeRegister', function ($query) use ($employee) {
            $query->where("client_employee_id", $employee->id)
                ->whereIn('type', ['leave_request', 'congtac_request'])
                ->where("status", "approved");
        })
            ->whereBetween("date_time_register", [
                $date_from,
                $date_to,
            ])
            ->orderBy('start_time')
            ->get()->groupBy('date_time_register');

        // Client setting
        $clientSetting = ClientWorkflowSetting::where('client_id', $employee->client_id)->select('enable_makeup_request_form')->first();

        // Số ngày checkin, và checkin đúng giờ
        $checkInCount = 0;
        $checkInLate = 0;
        $checkInLateCoreTime = 0;
        $checkOutCount = 0;
        $checkOutEarly = 0;
        $checkOutEarlyCoreTime = 0;
        $checkInOutCountOffOrHolidayDay = 0;
        $totalNotCheckInOut = 0;
        $totalHoliday = 0;
        foreach ($timesheets as $log_date => $item) {
            if ($workSchedules->has($log_date)) {
                /** @var WorkSchedule $ws */
                $ws = $workSchedules->get($log_date);
                if (!$ws->is_off_day && !$ws->is_holiday) {

                    $workTimeRegisterPeriod = null;
                    if ($workTimeRegisterPeriods->has($log_date)) {
                        $workTimeRegisterPeriod = $workTimeRegisterPeriods->get($log_date);
                    }

                    $startBreakTime = null;
                    $endBreakTime = null;
                    if (!empty($ws->start_break) && !empty($ws->end_break)) {
                        $startBreakTime = Carbon::parse($log_date . " " . $ws->start_break . ":00");
                        $endBreakTime = Carbon::parse($log_date . " " . $ws->end_break . ":00");
                    }

                    $totalHourInDay = $item->working_hours + $item->paid_leave_hours;
                    if ($item->check_in && $item->check_in != '00:00') {
                        $checkInCount++;
                        if ($ws->workHours != $totalHourInDay) {
                            $wsCheckInTime = Carbon::parse($log_date . " " . $ws->check_in . ":00");
                            $employeeCheckInTime = $item->start_next_day ? Carbon::parse($log_date . " " . $item->check_in . ":00")->addDay() : Carbon::parse($log_date . " " . $item->check_in . ":00");
                            if ($workTimeRegisterPeriod) {
                                $firstPeriod = $workTimeRegisterPeriod->first();
                                $param = [
                                    'mode' => 'check_in',
                                    'work_check_in' => $wsCheckInTime,
                                    'start_break_time' => $startBreakTime,
                                    'end_break_time' => $endBreakTime,
                                    'employee_check_in' => $employeeCheckInTime,
                                    'start_period' => Carbon::parse($firstPeriod->date_time_register . " " . $firstPeriod->start_time),
                                    'end_period' => Carbon::parse($firstPeriod->date_time_register . " " . $firstPeriod->end_time),
                                ];
                                $resultCheckInLate = WorktimeRegisterHelper::isCheckInLateOrCheckOutEarly($param);
                                $checkInLate += $resultCheckInLate;

                                // Only case applied_core_time
                                if ($employee->timesheet_exception == Constant::APPLIED_CORE_TIME && $groupTemplate->core_time_in) {
                                    $param['work_check_in'] = $item->shift_enabled ? $wsCheckInTime : Carbon::parse($log_date . " " . $groupTemplate->core_time_in . ":00");
                                    $checkInLateCoreTime += WorktimeRegisterHelper::isCheckInLateOrCheckOutEarly($param);
                                } else {
                                    $checkInLateCoreTime += $resultCheckInLate;
                                }
                            } else if ($employeeCheckInTime->isAfter($wsCheckInTime)) {
                                $checkInLate++;

                                // Only case applied_core_time
                                if ($employee->timesheet_exception == Constant::APPLIED_CORE_TIME && $groupTemplate->core_time_in) {
                                    $wsCheckInTime = $item->shift_enabled ? $wsCheckInTime : Carbon::parse($log_date . " " . $groupTemplate->core_time_in . ":00");
                                    if ($employeeCheckInTime->isAfter($wsCheckInTime)) {
                                        $checkInLateCoreTime++;
                                    }
                                } else {
                                    $checkInLateCoreTime++;
                                }
                            }
                        }
                    }
                    if ($item->check_out && $item->check_out != '00:00') {
                        $checkOutCount++;
                        if ($ws->workHours != $totalHourInDay) {
                            $wsCheckOutTime = !empty($ws->next_day) ? Carbon::parse($log_date . " " . $ws->check_out . ":00")->addDay() : Carbon::parse($log_date . " " . $ws->check_out . ":00");
                            $employeeCheckOutTime = $item->next_day ? Carbon::parse($log_date . " " . $item->check_out . ":00")->addDay() : Carbon::parse($log_date . " " . $item->check_out . ":00");
                            if ($workTimeRegisterPeriod) {
                                $lastPeriod = $workTimeRegisterPeriod->last();
                                $param = [
                                    'mode' => 'check_out',
                                    'start_break_time' => $startBreakTime,
                                    'end_break_time' => $endBreakTime,
                                    'work_check_out' => $wsCheckOutTime,
                                    'employee_check_out' => $employeeCheckOutTime,
                                    'start_period' => Carbon::parse($lastPeriod->date_time_register . " " . $lastPeriod->start_time),
                                    'end_period' => Carbon::parse($lastPeriod->date_time_register . " " . $lastPeriod->end_time),
                                ];
                                $resultCheckOutEarly = WorktimeRegisterHelper::isCheckInLateOrCheckOutEarly($param);
                                $checkOutEarly += $resultCheckOutEarly;

                                // Only case applied_core_time
                                if ($employee->timesheet_exception == Constant::APPLIED_CORE_TIME && $groupTemplate->core_time_out) {
                                    $param['work_check_out'] = $item->shift_enabled ? $wsCheckOutTime : Carbon::parse($log_date . " " . $groupTemplate->core_time_out . ":00");
                                    $checkOutEarlyCoreTime += WorktimeRegisterHelper::isCheckInLateOrCheckOutEarly($param);
                                } else {
                                    $checkOutEarlyCoreTime += $resultCheckOutEarly;
                                }
                            } else if ($employeeCheckOutTime->isBefore($wsCheckOutTime)) {
                                $checkOutEarly++;

                                // Only case applied_core_time
                                if ($employee->timesheet_exception == Constant::APPLIED_CORE_TIME && $groupTemplate->core_time_out) {
                                    $wsCheckOutTime = $item->shift_enabled ? $wsCheckOutTime : Carbon::parse($log_date . " " . $groupTemplate->core_time_out . ":00");
                                    if ($employeeCheckOutTime->isBefore($wsCheckOutTime)) {
                                        $checkOutEarlyCoreTime++;
                                    }
                                } else {
                                    $checkOutEarlyCoreTime++;
                                }
                            }
                        }
                    }

                    if ($item->working_hours == '0.0' && empty($item->check_in) && !empty($item->check_out) || !empty($item->check_in) && empty($item->check_out)) {
                        $totalNotCheckInOut += $ws->work_hours - ($item->paid_leave_hours + $item->unpaid_leave_hours);
                    }
                }
                if ($ws->is_off_day || $ws->is_holiday) {
                    if ($item->check_in && $item->check_in != '00:00' && $item->check_out && $item->check_out != '00:00') {
                        $checkInOutCountOffOrHolidayDay++;
                    }
                }

                if ($ws->is_holiday) {
                    $totalHoliday++;
                }
            }
        }
        logger("----------CalculationSheetClientEmployeeObserver::loadSysteVariables Checkin Count---------- >>>>>", [$checkInCount]);

        // Tmp tính số giờ làm việc của mỗi ngày trong lịch làm việc
        // Số giờ làm việc chuẩn của công ty trong tháng này
        $expectedWorkHours = 0.0;
        // Tính số giờ không đi làm ở công ty
        $totalNotWorkingHours = 0.0;
        $workSchedules->each(function (WorkSchedule $item, $log_date) use ($timesheets, &$expectedWorkHours, &$totalNotWorkingHours) {
            if ($timesheets->has($log_date)) {
                if ($timesheets->get($log_date)->timesheetShiftMapping->count()) {
                    $mapping = $timesheets->get($log_date)->timesheetShiftMapping;
                    foreach ($mapping as $item) {
                        $expectedWorkHours += $item->schedule_shift_hours;
                    }
                } else {
                    $expectedWorkHours += $item->workHours;
                    $ts = $timesheets->get($log_date);
                    if ($ts->working_hours == '0.0' && $ts->paid_leave_hours == '0.0' && $ts->unpaid_leave_hours == '0.0') {
                        $totalNotWorkingHours += $item->workHours;
                    }
                }
            } else {
                $expectedWorkHours += $item->workHours;
                $totalNotWorkingHours += $item->workHours;
            }
        });

        logger(
            "CalculationSheetClientEmployeeObserver::loadSysteVariables expected work hours of company",
            [$expectedWorkHours]
        );
        // Nếu log nhiều hơn số giờ làm việc cơ bản tháng của công ty
        // số giờ làm việc của nv = max số giờ làm việc cơ bản tháng của công ty
        if ($workHours > $expectedWorkHours) {
            logger("CalculationSheetClientEmployeeObserver::loadSysteVariables logged hours is larger than expected work hours");
            $workHours = $expectedWorkHours;
        }

        // Take paid and unpaid leave of work_time_register table
        $timeSheetClone = $timesheets->toArray();

        array_walk($timeSheetClone, array('self', 'convertWorkTimeRegister'));

        // Get setting condition compare
        $clientSettingConditionCompare = ClientSettingConditionCompare::where('client_id', $employee->client_id)->get()->groupBy('key_condition')->toArray();


        // Set default paid leave
        $hourYearPaidLeave = 0;
        $hourSelfMarriagePaidLeave = 0;
        $hourChildMarriagePaidLeave = 0;
        $hourFamilyLostPaidLeave = 0;
        $hourPregnantPaidLeave = 0;
        $hourWomanPaidLeave = 0;
        $hourBabyCarePaidLeave = 0;
        $hourChangedPaidLeave = 0;
        $hourOtherPaidLeave = 0;
        $hourSickNoPaidLeave = 0;

        // Set default unpaid leave
        $hourUnpaidLeave = 0;
        $hourPregnantNoPaidLeave = 0;
        $hourSelfSickNoPaidLeave = 0;
        $hourChildSickNoPaidLeave = 0;
        $hourWifePregnantLeaveNoPaidLeave = 0;
        $hourOtherNoPaidLeave = 0;
        $hourCovidPaidLeave = 0;
        $hourPrenatalCheckupLeave = 0;

        $paidLeaveHours = 0;
        $totalMakeupHours = 0;
        $totalManualMakeupHours = 0;
        $unPaidLeaveHours = 0;
        $missingHoursInCoreTime = 0;
        $listVariableWithCondition = [];
        $listVariableWithNotCondition = Constant::LIST_S_VARIABLE_WITH_NOT_CONDITION;

        // Check to create S variable with condition
        $isCreateWorking = false;
        $isCreatePaidLeave = false;
        $isCreateUnpaidLeave = false;
        $isCreateOT = false;
        $isCreateWfh = false;
        $isCreateOutWorking = false;
        $isCreateBusinessTrip = false;
        $isCreateOther = false;
        if (!empty($clientSettingConditionCompare)) {
            if (isset($clientSettingConditionCompare['NUMBER_WORKING_HOUR'])) {
                $isCreateWorking = true;
                $this->createVariableDefaultCondition($listVariableWithCondition, $clientSettingConditionCompare['NUMBER_WORKING_HOUR']);
            }
            if (isset($clientSettingConditionCompare['NUMBER_PAID_LEAVE_HOUR'])) {
                $isCreatePaidLeave = true;
                $this->createVariableDefaultCondition($listVariableWithCondition, $clientSettingConditionCompare['NUMBER_PAID_LEAVE_HOUR']);
            }
            if (isset($clientSettingConditionCompare['NUMBER_UNPAID_LEAVE_HOUR'])) {
                $isCreateUnpaidLeave = true;
                $this->createVariableDefaultCondition($listVariableWithCondition, $clientSettingConditionCompare['NUMBER_UNPAID_LEAVE_HOUR']);
            }
            if (isset($clientSettingConditionCompare['NUMBER_OT_HOUR'])) {
                $isCreateOT = true;
                $this->createVariableDefaultCondition($listVariableWithCondition, $clientSettingConditionCompare['NUMBER_OT_HOUR']);
            }
            if (isset($clientSettingConditionCompare['NUMBER_WFH_HOURS'])) {
                $isCreateWfh = true;
                $this->createVariableDefaultCondition($listVariableWithCondition, $clientSettingConditionCompare['NUMBER_WFH_HOURS']);
            }
            if (isset($clientSettingConditionCompare['NUMBER_OUTSIDE_WORKING_HOURS'])) {
                $isCreateOutWorking = true;
                $this->createVariableDefaultCondition($listVariableWithCondition, $clientSettingConditionCompare['NUMBER_OUTSIDE_WORKING_HOURS']);
            }
            if (isset($clientSettingConditionCompare['NUMBER_BUSINESS_TRIP_HOURS'])) {
                $isCreateBusinessTrip = true;
                $this->createVariableDefaultCondition($listVariableWithCondition, $clientSettingConditionCompare['NUMBER_BUSINESS_TRIP_HOURS']);
            }
            if (isset($clientSettingConditionCompare['NUMBER_OTHER_HOURS_OF_BUSINESS_AND_WFH'])) {
                $isCreateOther = true;
                $this->createVariableDefaultCondition($listVariableWithCondition, $clientSettingConditionCompare['NUMBER_OTHER_HOURS_OF_BUSINESS_AND_WFH']);
            }
        }
        $listCheckUnique = [];
        $listCheckUniqueNotCondition = [];
        foreach ($timeSheetClone as $keyDate => $time) {
            if ($time['paid_leave_hours'] != 0.0) {
                $listWorkTimeRegister = $time['workTimeRegister']->where('sub_type', 'authorized_leave');
                foreach ($listWorkTimeRegister as $workTimeRegister) {
                    $leaveAuthorization = Constant::LEAVE_REQUEST_CATEGORY['authorized'];
                    $listWorkingRegisterPeriod = $workTimeRegister->workTimeRegisterPeriod->where('date_time_register', $keyDate);
                    foreach ($listWorkingRegisterPeriod as $item) {
                        if ($item->date_time_register === $keyDate && $item->date_time_register <= $date_to) {
                            // year_leave
                            if ($workTimeRegister->category == $leaveAuthorization[0]) {
                                $hourYearPaidLeave += (float)$item->duration_for_leave_request;
                                break;
                            }
                            // self_marriage_leave
                            if ($workTimeRegister->category == $leaveAuthorization[1]) {
                                $hourSelfMarriagePaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // child_marriage_leave
                            if ($workTimeRegister->category == $leaveAuthorization[2]) {
                                $hourChildMarriagePaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // family_lost
                            if ($workTimeRegister->category == $leaveAuthorization[3]) {
                                $hourFamilyLostPaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // pregnant_leave
                            if ($workTimeRegister->category == $leaveAuthorization[4]) {
                                $hourPregnantPaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // woman_leave
                            if ($workTimeRegister->category == $leaveAuthorization[5]) {
                                $hourWomanPaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // baby_care
                            if ($workTimeRegister->category == $leaveAuthorization[6]) {
                                $hourBabyCarePaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // changed_leave
                            if ($workTimeRegister->category == $leaveAuthorization[7]) {
                                $hourChangedPaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // other_leave
                            if ($workTimeRegister->category == $leaveAuthorization[8]) {
                                $hourOtherPaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // covid leave
                            if ($workTimeRegister->category == $leaveAuthorization[9]) {
                                $hourCovidPaidLeave += (float)$item->duration_for_leave_request;
                            }
                        }
                    }
                }
            }
            if ($time['unpaid_leave_hours'] != 0.0) {
                $unPaidLeaveHours += $time['unpaid_leave_hours'];
                $listWorkTimeRegister = $time['workTimeRegister']->where('sub_type', 'unauthorized_leave');
                foreach ($listWorkTimeRegister as $workTimeRegister) {
                    $leaveUnAuthorization = Constant::LEAVE_REQUEST_CATEGORY['unauthorized'];
                    $listWorkingRegisterPeriod = $workTimeRegister->workTimeRegisterPeriod->where('date_time_register', $keyDate);
                    foreach ($listWorkingRegisterPeriod as $item) {
                        if ($item->date_time_register <= $date_to) {
                            // unpaid_leave
                            if ($workTimeRegister->category == $leaveUnAuthorization[0]) {
                                $hourUnpaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // self_marriage_leave
                            if ($workTimeRegister->category == $leaveUnAuthorization[1]) {
                                $hourPregnantNoPaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // child_marriage_leave
                            if ($workTimeRegister->category == $leaveUnAuthorization[2]) {
                                $hourSelfSickNoPaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // family_lost
                            if ($workTimeRegister->category == $leaveUnAuthorization[3]) {
                                $hourChildSickNoPaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // pregnant_leave
                            if ($workTimeRegister->category == $leaveUnAuthorization[4]) {
                                $hourWifePregnantLeaveNoPaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // pregnant_leave
                            if ($workTimeRegister->category == $leaveUnAuthorization[5]) {
                                $hourPrenatalCheckupLeave += (float)$item->duration_for_leave_request;
                            }
                            // other leave
                            if ($workTimeRegister->category == $leaveUnAuthorization[6]) {
                                $hourOtherNoPaidLeave += (float)$item->duration_for_leave_request;
                            }
                            // sick_leave
                            if ($workTimeRegister->category == $leaveUnAuthorization[7]) {
                                $hourSickNoPaidLeave += (float)$item->duration_for_leave_request;
                            }
                        }
                    }
                }
            }
            // Create S variable with condition
            if ($isCreatePaidLeave) {
                $this->createVariableWithCondition($listVariableWithCondition, $listCheckUnique,  $keyDate, $clientSettingConditionCompare['NUMBER_PAID_LEAVE_HOUR'], $time['paid_leave_hours']);
            }

            if ($isCreateUnpaidLeave) {
                $this->createVariableWithCondition($listVariableWithCondition, $listCheckUnique,  $keyDate, $clientSettingConditionCompare['NUMBER_UNPAID_LEAVE_HOUR'], $time['unpaid_leave_hours']);
            }

            if ($isCreateOT) {
                $this->createVariableWithCondition($listVariableWithCondition, $listCheckUnique,  $keyDate, $clientSettingConditionCompare['NUMBER_OT_HOUR'], $time['overtime_hours']);
            }

            if ($isCreateWorking) {
                $this->createVariableWithCondition($listVariableWithCondition, $listCheckUnique,  $keyDate, $clientSettingConditionCompare['NUMBER_WORKING_HOUR'], $time['working_hours']);
            }

            if ($time['makeup_hours'] != '0.0') {
                $totalMakeupHours += $time['makeup_hours'];
                $totalManualMakeupHours += $time['manual_makeup_hours'];
            }

            $paidLeaveHours += $time['paid_leave_hours'];
            $missingHoursInCoreTime += $time['missing_hours_in_core_time'];
        }

        logger(
            "CalculationSheetClientEmployeeObserver::loadSysteVariables paid leave hours",
            [$paidLeaveHours]
        );
        $totalWorkHoursPaidLeave = $workHours + $paidLeaveHours;
        // Số giờ nghỉ phép không có lương
        $noSalaryLeaveHours = round($expectedWorkHours - $totalWorkHoursPaidLeave, 2);
        // Nếu số giờ nghỉ không lương nhỏ hơn 0
        // thì số giờ nghỉ không lương xem như là 0 có
        if ($noSalaryLeaveHours <= 0) {
            $noSalaryLeaveHours = 0;
        }
        logger(
            "CalculationSheetClientEmployeeObserver::loadSysteVariables unpaid leave hours",
            [$noSalaryLeaveHours]
        );

        // Mức trần BH của công ty
        // Nếu giá trị không hợp lệ hoặc rỗng thì coi như bằng 0
        $client = $employee->client;
        $socialInsuranceCeiling = is_numeric($client->social_insurance_and_health_insurance_ceiling) ?
            $client->social_insurance_and_health_insurance_ceiling :
            0;
        $unemploymentInsuranceCeiling = is_numeric($client->unemployment_insurance_ceiling) ?
            $client->unemployment_insurance_ceiling :
            0;


        // Nhut added 2020-10-02
        // S_POSITION
        $employeePosition = $employee->position;

        // S_SALARY_CURRENCY
        $salaryCurrency = $employee->currency ?? "";

        // Tổng số giờ làm việc ban đêm
        $nightWorkHours = $timesheets->reduce(function ($carry, Timesheet $item) use ($employee, $workSchedules) {
            if ($workSchedules->has($item->log_date)) {
                /** @var WorkSchedule $ws */
                $ws = $workSchedules->get($item->log_date);
                if ($ws->is_off_day) {
                    return $carry;
                }
                $wsPeriod = $ws->work_schedule_period;
                $restPeriod = $ws->rest_period;
                [$checkIn, $checkOut] = $item->getCheckInOutCarbonAttribute($employee, $ws);
                $tsPeriod = PeriodHelper::makePeriod($checkIn, $checkOut);
                $midnightOTStart = Carbon::parse($item->log_date . ' 22:00:00');
                $midnightOTEnd = $midnightOTStart->clone()->addDay()->setTime(6, 0, 0);
                $midnightPeriod = PeriodHelper::makePeriod($midnightOTStart, $midnightOTEnd);


                $midnightWsPeriod = $wsPeriod->overlapSingle($midnightPeriod);
                if (empty($midnightWsPeriod)) {
                    return $carry; // Không có work hour ban đêm
                }

                $validTsPeriod = $tsPeriod->overlapSingle($midnightWsPeriod);
                if (!$validTsPeriod) {
                    return $carry; // Không tính nếu không giao nhau với giờ làm
                }

                $overlapWithRest = $restPeriod->overlapSingle($validTsPeriod);
                $carry += PeriodHelper::countHours($validTsPeriod);

                if ($overlapWithRest) {
                    $carry -= PeriodHelper::countHours($overlapWithRest);
                }
            }
            return $carry;
        }, 0);

        $totalYearOvertimeHours = 0.0;
        if ($workSchedules->first()) {
            /** @var Carbon */
            $firstDayOfWorkSchedule = $workSchedules->first()->schedule_date;

            $startOfYear = $firstDayOfWorkSchedule->copy()->startOfYear();
            $endOfYear = $firstDayOfWorkSchedule->copy()->endOfYear();

            $totalYearOvertimeHours = DB::table((new Timesheet)->getTable())
                ->whereBetween('log_date', [
                    $startOfYear->toDateString(),
                    $endOfYear->toDateString(),
                ])
                ->where('client_employee_id', $employee->id)
                ->sum('overtime_hours');
        }

        $type_of_employment_contract = 1;

        switch ($employee->type_of_employment_contract) {
            case "khongthoihan":
                $type_of_employment_contract = 1;
                break;
            case "chinhthuc":
                $type_of_employment_contract = 2;
                break;
            case "thoivu":
                $type_of_employment_contract = 3;
                break;
            case "thuviec":
                $type_of_employment_contract = 4;
                break;
        }

        $allowanceVariable = $this->getAllowanceVariable($employee);

        // #46649085: [CR] Bố sung biến S mới - Biến đếm theo ngày
        // bộ đếm đơn
        $wtrTable = (new WorktimeRegister)->getTable();
        $periodTable = (new WorkTimeRegisterPeriod)->getTable();
        $registers = WorkTimeRegisterPeriod::query()
            ->leftJoin($wtrTable, "$wtrTable.id", "=", "$periodTable.worktime_register_id")
            ->where("$wtrTable.client_employee_id", $employee->id)
            ->where("$wtrTable.status", "approved")
            ->whereBetween("$periodTable.date_time_register", [
                $date_from,
                $date_to,
            ])
            ->groupBy("$wtrTable.type", "$wtrTable.sub_type", "$periodTable.date_time_register")
            ->select([
                "$wtrTable.type",
                "$wtrTable.sub_type",
                "$wtrTable.skip_logic",
                "$periodTable.id",
                "$periodTable.has_fee",
                "$periodTable.date_time_register",
                "$periodTable.start_time",
                "$periodTable.end_time",
                "$periodTable.next_day",
                "$periodTable.type_register",
                "$periodTable.worktime_register_id",
            ])
            ->get();

        // S_WORK_FROM_HOME_DAYS - số ngày làm việc tại nhà
        $S_WORK_FROM_HOME_DAYS = $registers->filter(function ($v) {
            return $v->type == 'congtac_request' && $v->sub_type == 'wfh';
        })->count();
        // Use to count number day work OT in company
        $arrayCountOtInCompany = [];
        // Use to count number day work OT outside company
        $arrayCountOtOutCompany = [];
        // Use to count number day business trip has fee
        $arrayCountBusinessHasFee = [];
        foreach ($registers as $item) {
            if ($workSchedules->has($item['date_time_register'])) {
                // Check with type congtac_request
                if ($item->type == 'congtac_request') {
                    // S_BUSINESS_TRIP_DAYS
                    if ($item->sub_type == 'business_trip') {
                        $this->createVariableWithNotCondition($listVariableWithNotCondition, $listCheckUniqueNotCondition, $item['date_time_register'], 'S_BUSINESS_TRIP_DAYS');
                        if ($isCreateBusinessTrip) {
                            $this->createVariableWithCondition($listVariableWithCondition, $listCheckUnique,  $item['date_time_register'], $clientSettingConditionCompare['NUMBER_BUSINESS_TRIP_HOURS'], $item->duration);
                        }

                        // Business trip has fee
                        if ($item->has_fee && !array_key_exists($item['date_time_register'], $arrayCountBusinessHasFee)) {
                            $arrayCountBusinessHasFee[$item['date_time_register']] = 1;
                        }
                    }

                    // Work from home
                    if ($item->sub_type == 'wfh') {
                        $this->createVariableWithNotCondition($listVariableWithNotCondition, $listCheckUniqueNotCondition, $item['date_time_register'], 'S_WFH_COUNT');
                        if ($isCreateWfh) {
                            $this->createVariableWithCondition($listVariableWithCondition, $listCheckUnique,  $item['date_time_register'], $clientSettingConditionCompare['NUMBER_WFH_HOURS'], $item->duration);
                        }
                    }

                    // S_OUTSIDE_WORKING_DAYS
                    if ($item->sub_type == 'outside_working') {
                        $this->createVariableWithNotCondition($listVariableWithNotCondition, $listCheckUniqueNotCondition, $item['date_time_register'], 'S_OUTSIDE_WORKING_DAYS');
                        if ($isCreateOutWorking) {
                            $this->createVariableWithCondition($listVariableWithCondition, $listCheckUnique,  $item['date_time_register'], $clientSettingConditionCompare['NUMBER_OUTSIDE_WORKING_HOURS'], $item->duration);
                        }
                    }

                    // Work from home
                    if ($item->sub_type == 'other') {
                        $this->createVariableWithNotCondition($listVariableWithNotCondition, $listCheckUniqueNotCondition, $item['date_time_register'], 'S_COUNT_DAY_OF_OTHER_BUSINESS_AND_WFH');
                        if ($isCreateOther) {
                            $this->createVariableWithCondition($listVariableWithCondition, $listCheckUnique,  $item['date_time_register'], $clientSettingConditionCompare['NUMBER_OTHER_HOURS_OF_BUSINESS_AND_WFH'], $item->duration);
                        }
                    }
                }

                // Check with type overtime_request
                if ($item->type == 'overtime_request') {
                    /** @var WorkSchedule $ws */
                    $ws = $workSchedules->get($item['date_time_register']);
                    if ($ws->is_off_day) {
                        if ($item->skip_logic) {
                            if (!array_key_exists($item['date_time_register'], $arrayCountOtOutCompany)) {
                                $arrayCountOtOutCompany[$item['date_time_register']] = 1;
                            }
                        } else {
                            if (!array_key_exists($item['date_time_register'], $arrayCountOtInCompany)) {
                                $arrayCountOtInCompany[$item['date_time_register']] = 1;
                            }
                        }
                    }
                }
            }
        }

        // S_STANDARD_WORKING_DAYS - số ngày công tiêu chuẩn trong tháng
        $S_STANDARD_WORKING_DAYS = $workSchedules->filter(function ($v) {
            return !$v->is_holiday && !$v->is_off_day;
        })->count();
        // S_TIMESHEET_WORKING_DAYS - số ngày làm việc thực tế trong tháng
        $S_TIMESHEET_WORKING_DAYS = $checkInCount;
        // S_MENSTRUAL_LEAVE_HOURS - Số giờ nghỉ hành kinh trong tháng
        $S_MENSTRUAL_LEAVE_HOURS = 0;
        // TODO better way to do this
        $menstrualLeaves = WorkTimeRegisterPeriod::query()
            ->whereHas('worktimeRegister', function ($query) use ($employee) {
                $query->where('type', 'leave_request')
                    ->where('sub_type', 'authorized_leave')
                    ->where('category', 'woman_leave')
                    ->where('status', 'approved')
                    ->where('client_employee_id', $employee->id);
            })
            ->whereBetween('date_time_register', [
                $date_from,
                $date_to,
            ])
            ->get();
        foreach ($menstrualLeaves as $menstrualLeave) {
            $S_MENSTRUAL_LEAVE_HOURS += PeriodHelper::countHours($menstrualLeave->getPeriod());
        }

        $businessTrips = $registers->filter(function ($v) {
            return $v->type === 'congtac_request' && $v->sub_type === 'business_trip';
        });
        $S_BUSINESS_TRIP_HAS_FEE_HOURS = $businessTrips->reduce(function ($sum, $item) {
            return $sum + ($item->has_fee ? $item->duration : 0);
        }, 0);

        $S_BUSINESS_TRIP_NO_FEE_HOURS  = $registers->reduce(function ($sum, $item) {
            return $sum + (!$item->has_fee ? $item->duration : 0);
        }, 0);

        // S_SENIORITY
        $S_SENIORITY = 0;
        $startDate = null;
        if ($client->seniority_contract_type == 'thuviec') {
            try {
                $startDate = Carbon::parse($employee->probation_start_date);
            } catch (InvalidFormatException $e) {
                logger(__METHOD__ . ": " . $employee->code . " invalid probation_start_date: " . $employee->probation_start_date);
            }
        } elseif ($client->seniority_contract_type == 'chinhthuc') {
            try {
                $startDate = Carbon::parse($employee->official_contract_signing_date);
            } catch (InvalidFormatException $e) {
                logger(__METHOD__ . ": " . $employee->code . " invalid official_contract_signing_date: " . $employee->probation_start_date);
            }
        }
        if ($startDate) {
            // incase startDate invalid, result will be 0
            $now = Carbon::now();
            $S_SENIORITY = $now->diffInYears($startDate);  // count theo block 12, nếu nhỏ hơn 12 thì tính là 0,
            // lớn hơn = 12 mới được tính là 1
        }

        $totalLateHours = $noSalaryLeaveHours - $unPaidLeaveHours - $totalNotWorkingHours - $missingHoursInCoreTime - $totalNotCheckInOut;
        $totalLateHours = max($totalLateHours, 0);

        $variableDefault = [
            [
                'readable_name' => 'Số giờ nghỉ phép năm có hưởng lương',
                'variable_name' => 'S_HOURS_PAID_ANNUAL_LEAVE',
                'variable_value' => $hourYearPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép để kết hôn có hưởng lương',
                'variable_name' => 'S_HOURS_GET_MARRIED_PAID_LEAVE',
                'variable_value' => $hourSelfMarriagePaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép để con cái kết hôn có hưởng lương',
                'variable_name' => 'S_HOURS_GET_CHILD_MARRIAGE_PAID_LEAVE',
                'variable_value' => $hourChildMarriagePaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép do người thân mất có hưởng lương',
                'variable_name' => 'S_HOURS_FAMILY_LOST_PAID_LEAVE',
                'variable_value' => $hourFamilyLostPaidLeave,
            ],
            // Keep variable S with old data
            [
                'readable_name' => 'Số giờ nghỉ phép với chế độ mang thai có hưởng lương',
                'variable_name' => 'S_HOURS_PREGNANT_PAID_LEAVE',
                'variable_value' => $hourPregnantPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép hành kinh có hưởng lương',
                'variable_name' => 'S_HOURS_WOMAN_PAID_LEAVE',
                'variable_value' => $hourWomanPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép với chế độ con nhỏ có hưởng lương',
                'variable_name' => 'S_HOURS_BABY_CARE_PAID_LEAVE',
                'variable_value' => $hourBabyCarePaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ bù có hưởng lương',
                'variable_name' => 'S_HOURS_CHANGED_PAID_LEAVE',
                'variable_value' => $hourChangedPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép do covid có hưởng lương',
                'variable_name' => 'S_HOURS_COVID_PAID_LEAVE',
                'variable_value' => $hourCovidPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ với lý do khác có hưởng lương',
                'variable_name' => 'S_HOURS_OTHER_PAID_LEAVE',
                'variable_value' => $hourOtherPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép không hưởng lương',
                'variable_name' => 'S_HOURS_NO_SALARY_INDIVIDUAL',
                'variable_value' => $hourUnpaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép thai sản không hưởng lương',
                'variable_name' => 'S_HOURS_NO_SALARY_PREGNANT',
                'variable_value' => $hourPregnantNoPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép ốm BHXH không hưởng lương',
                'variable_name' => 'S_HOURS_NO_SALARY_SELF_SICK',
                'variable_value' => $hourSelfSickNoPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép BHXH do con ốm không hưởng lương',
                'variable_name' => 'S_HOURS_NO_SALARY_CHILD_SICK',
                'variable_value' => $hourChildSickNoPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép BHXH do vợ đẻ không hưởng lương',
                'variable_name' => 'S_HOURS_NO_SALARY_WIFE_PREGNANT',
                'variable_value' => $hourWifePregnantLeaveNoPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép đi khám thai không hưởng lương',
                'variable_name' => 'S_HOURS_NO_SALARY_PRENATAL_CHECKUP',
                'variable_value' => $hourPrenatalCheckupLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép do nghỉ bệnh không hưởng lương',
                'variable_name' => 'S_HOURS_NO_SALARY_SICK',
                'variable_value' => $hourSickNoPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép khác không hưởng lương',
                'variable_name' => 'S_HOURS_NO_SALARY_OTHER',
                'variable_value' => $hourOtherNoPaidLeave,
            ],
            [
                'readable_name' => 'Số giờ làm việc cơ bản',
                'variable_name' => 'S_TIMESHEET_WORK_HOURS',
                'variable_value' => $workHours,
            ],
            [
                'readable_name' => 'Số công cơ bản',
                'variable_name' => 'S_SHIFT',
                'variable_value' => $shift,
            ],
            [
                'readable_name' => 'Số công ngày lễ',
                'variable_name' => 'S_HOLIDAY_SHIFT',
                'variable_value' => $holidayShift,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép có hưởng lương',
                'variable_name' => 'S_OFF_HOURS_WITH_SALARY',
                'variable_value' => $paidLeaveHours,
            ],
            [
                'readable_name' => 'Số giờ nghỉ phép không hưởng lương',
                'variable_name' => 'S_OFF_HOURS_NO_SALARY',
                'variable_value' => $noSalaryLeaveHours,
            ],
            [
                'readable_name' => 'Số giờ làm thêm các ngày trong tuần',
                'variable_name' => 'S_WORK_HOURS_OT_WEEKDAY',
                'variable_value' => $weekdayOvertimeHours,
            ],
            [
                'readable_name' => 'Số giờ làm thêm các ngày cuối tuần',
                'variable_name' => 'S_WORK_HOURS_OT_WEEKEND',
                'variable_value' => $offDayOvertimeHours,
            ],
            [
                'readable_name' => 'Số giờ làm thêm ngày Lễ',
                'variable_name' => 'S_WORK_HOURS_OT_HOLIDAY',
                'variable_value' => $holidayOvertimeHours,
            ],
            [
                'readable_name' => 'Số giờ đi công tác',
                'variable_name' => 'S_TIMESHEET_TRAVEL_WORK_HOURS',
                'variable_value' => $travelHours,
            ],
            [
                'readable_name' => 'Tiền lương',
                'variable_name' => 'S_SALARY',
                'variable_value' => $previous_salary ? floatval($previous_salary) : floatval($employee->salary),
            ],
            [
                'readable_name' => 'Phụ cấp trách nhiệm',
                'variable_name' => 'S_ALLOWANCE_RESPONSIBILITIES',
                'variable_value' => floatval($employee->allowance_for_responsibilities),
            ],
            [
                'readable_name' => 'Phụ cấp cố định',
                'variable_name' => 'S_ALLOWANCE_FIXED',
                'variable_value' => floatval($employee->fixed_allowance),
            ],
            [
                'readable_name' => 'Đối tượng tính thuế	',
                'variable_name' => 'S_IS_TAXABLE',
                'variable_value' => $employee->is_tax_applicable ?? 0,
            ],
            [
                'readable_name' => 'Đối tượng đóng BHXH, BHYT, BHTN',
                'variable_name' => 'S_IS_INSURANCE_APPLIED',
                'variable_value' => $employee->is_insurance_applicable ?? 0,
            ],
            [
                'readable_name' => 'Tình trạng cư trú',
                'variable_name' => 'S_RESIDENT_STATUS',
                'variable_value' => $employee->resident_status ? 1 : 0,
            ],
            [
                'readable_name' => 'Số người phụ thuộc',
                'variable_name' => 'S_NUMBER_OF_DEPENDENTS',
                'variable_value' => $employee->number_of_dependents,
            ],
            [
                'readable_name' => 'Mức đóng bảo hiểm xã hội',
                'variable_name' => 'S_INSURANCE_SALARY',
                'variable_value' => floatval($employee->salary_for_social_insurance_payment),
            ],
            [
                'readable_name' => 'Số giờ làm việc tiêu chuẩn',
                'variable_name' => 'S_STANDARD_WORK_HOURS',
                'variable_value' => $expectedWorkHours,
            ],
            [
                'readable_name' => 'Mức trần BHYT, BHXH',
                'variable_name' => 'S_SH_INSURANCE_MAXIMUM',
                'variable_value' => $socialInsuranceCeiling,
            ],
            [
                'readable_name' => 'Mức trần BHTN',
                'variable_name' => 'S_U_INSURANCE_MAXIMUM',
                'variable_value' => $unemploymentInsuranceCeiling,
            ],
            [
                'readable_name' => 'Chức vụ',
                'variable_name' => 'S_POSITION',
                'variable_value' => $employeePosition,
            ],
            [
                'readable_name' => 'Ngoại tệ',
                'variable_name' => 'S_SALARY_CURRENCY',
                'variable_value' => $salaryCurrency,
            ],
            [
                'readable_name' => 'Ngày bắt đầu chu kỳ tính lương',
                'variable_name' => 'S_TIMESHEET_START_DATE',
                'variable_value' => $date_from,
            ],
            [
                'readable_name' => 'Ngày kết thúc chu kỳ tính lương',
                'variable_name' => 'S_TIMESHEET_END_DATE',
                'variable_value' => $date_to,
            ],
            [
                'readable_name' => 'Số giờ OT ban đêm vào ngày thường',
                'variable_name' => 'S_WORK_HOURS_OT_AT_NIGHT_WEEKDAY',
                'variable_value' => $midnightWeekdayOvertimeHours,
            ],
            [
                'readable_name' => 'Số giờ OT ban đêm vào ngày cuối tuần',
                'variable_name' => 'S_WORK_HOURS_OT_AT_NIGHT_WEEKEND',
                'variable_value' => $midnightOffDayOvertimeHours,
            ],
            [
                'readable_name' => 'Số giờ OT ban đêm vào ngày Lễ/Tết',
                'variable_name' => 'S_WORK_HOURS_OT_AT_NIGHT_HOLIDAY',
                'variable_value' => $midnightHolidayOvertimeHours,
            ],
            [
                'readable_name' => 'Tổng số giờ OT ban đêm',
                'variable_name' => 'S_TOTAL_WORK_HOURS_OT_AT_NIGHT',
                'variable_value' => $totalMidnightOvertimeHours,
            ],
            [
                'readable_name' => 'Tổng thời gian làm việc vào ban đêm',
                'variable_name' => 'S_TOTAL_WORK_HOURS_AT_NIGHT',
                'variable_value' => $nightWorkHours,
            ],
            [
                'readable_name' => 'Số giờ chỉ OT ban đêm vào ngày cuối tuần',
                'variable_name' => 'S_WORK_HOURS_OT_AT_ONLY_NIGHT_WEEKEND',
                'variable_value' => $onlyMidnightOffDayOvertimeHours,
            ],
            [
                'readable_name' => 'Số giờ chỉ OT ban đêm vào ngày Lễ/Tết',
                'variable_name' => 'S_WORK_HOURS_OT_AT_ONLY_NIGHT_HOLIDAY',
                'variable_value' => $onlyMidnightHolidayOvertimeHours,
            ],
            [
                'readable_name' => 'Số giờ chỉ OT ban đêm vào ngày thường',
                'variable_name' => 'S_WORK_HOURS_OT_AT_ONLY_NIGHT_WEEKDAY',
                'variable_value' => $onlyMidnightOvertimeHours,
            ],
            [
                'readable_name' => 'Tổng thời gian OT trong năm (tính đến thời điểm hiện tại)',
                'variable_name' => 'S_TOTAL_WORK_HOURS_OT_OF_YEAR',
                'variable_value' => $totalYearOvertimeHours,
            ],
            [
                'readable_name' => 'Số giờ OT ngày T7',
                'variable_name' => 'S_OT_SAR',
                'variable_value' => $satOvertimeHours,
            ],
            [
                'readable_name' => 'Số giờ OT ban ngày T7 CN có status là "Đi làm"',
                'variable_name' => 'S_SPECIAL_OT_WEEKEND',
                'variable_value' => $specialOvertimeHours,
            ],
            [
                'readable_name' => 'Số giờ OT ban đêm T7 CN có status là "Đi làm"',
                'variable_name' => 'S_SPECIAL_OT_AT_NIGHT_WEEKEND',
                'variable_value' => $midnightSpecialOvertimeHours,
            ],
            [
                'readable_name' => 'Số ngày phép còn lại trong năm',
                'variable_name' => 'S_REMAIN_DAYS_LEAVE_IN_YEAR',
                'variable_value' => $employee->year_paid_leave_count,
            ],
            [
                'readable_name' => 'Phòng ban',
                'variable_name' => 'S_DEPARTMENT',
                'variable_value' => $employee->department,
            ],
            [
                'readable_name' => 'Quốc tịch',
                'variable_name' => 'S_NATIONALITY',
                'variable_value' => $employee->nationality,
            ],
            [
                'readable_name' => 'Chức danh',
                'variable_name' => 'S_TITLE',
                'variable_value' => $employee->title,
            ],
            [
                'readable_name' => 'Loại hợp đồng lao động',
                'variable_name' => 'S_TYPE_OF_LABOUR_CONTRACT',
                'variable_value' => $type_of_employment_contract,
            ],
            [
                'readable_name' => 'Tình trạng làm việc',
                'variable_name' => 'S_TYPE_OF_WORKING_STATUS',
                'variable_value' => $employee->status,
            ],
            [
                'readable_name' => 'Giới tính',
                'variable_name' => 'S_GENDER',
                'variable_value' => $employee->sex,
            ],
            [
                'readable_name' => 'Tổng phụ cấp / khấu trừ',
                'variable_name' => 'S_ALLOWANCE',
                'variable_value' => $allowanceVariable,
            ],
            [
                'readable_name' => 'Tên người thụ hưởng của số tài khoản',
                'variable_name' => 'S_BANK_ACCOUNT_OF_EMPLOYEES',
                'variable_value' => $employee->bank_account,
            ],
            [
                'readable_name' => 'Số tài khoản ngân hàng của nhân viên',
                'variable_name' => 'S_BANK_ACCOUNT_NUMBER_OF_EMPLOYEES',
                'variable_value' => $employee->bank_account_number,
            ],
            [
                'readable_name' => 'Tên ngân hàng số tài khoản của nhân viên',
                'variable_name' => 'S_BANK_NAME_OF_EMPLOYEES',
                'variable_value' => $employee->bank_name,
            ],
            [
                'readable_name' => 'Chi nhánh ngân hàng số tài khoản của nhân viên',
                'variable_name' => 'S_BANK_BRANCH_NAME_OF_EMPLOYEES',
                'variable_value' => $employee->bank_branch,
            ],
            [
                'readable_name' => 'Số ngày có checkin',
                'variable_name' => 'S_CHECK_IN_COUNT_DAYS',
                'variable_value' => $checkInCount,
            ],
            [
                'readable_name' => 'Số ngày checkin đúng giờ',
                'variable_name' => 'S_CHECK_IN_INTIME_DAYS',
                'variable_value' => $checkInCount - $checkInLate,
            ],
            [
                'readable_name' => 'Số ngày checkin trễ giờ',
                'variable_name' => 'S_CHECK_IN_LATE_DAYS',
                'variable_value' => $checkInLate,
            ],
            [
                'readable_name' => 'Số ngày checkin trễ giờ với core time',
                'variable_name' => 'S_CHECK_IN_LATE_CORE_TIME_DAYS',
                'variable_value' => $checkInLateCoreTime,
            ],
            [
                'readable_name' => 'Số ngày có checkout',
                'variable_name' => 'S_CHECK_OUT_COUNT_DAYS',
                'variable_value' => $checkOutCount,
            ],
            [
                'readable_name' => 'Số ngày checkout sớm',
                'variable_name' => 'S_CHECK_OUT_EARLY_DAYS',
                'variable_value' => $checkOutEarly,
            ],
            [
                'readable_name' => 'Số ngày checkout sớm có core time',
                'variable_name' => 'S_CHECK_OUT_EARLY_CORE_TIME_DAYS',
                'variable_value' => $checkOutEarlyCoreTime,
            ],
            // #46649085: [CR] Bố sung biến S mới - Biến đếm theo ngày
            [
                'readable_name' => 'Số ngày làm việc tại nhà',
                'variable_name' => 'S_WORK_FROM_HOME_DAYS',
                'variable_value' => $S_WORK_FROM_HOME_DAYS,
            ],
            [
                'readable_name' => 'Số ngày công tiêu chuẩn trong tháng',
                'variable_name' => 'S_STANDARD_WORKING_DAYS',
                'variable_value' => $S_STANDARD_WORKING_DAYS,
            ],
            [
                'readable_name' => 'số ngày làm việc thực tế trong tháng',
                'variable_name' => 'S_TIMESHEET_WORKING_DAYS',
                'variable_value' => $S_TIMESHEET_WORKING_DAYS,
            ],
            [
                'readable_name' => 'Số giờ nghỉ hành kinh trong tháng',
                'variable_name' => 'S_MENSTRUAL_LEAVE_HOURS',
                'variable_value' => $S_MENSTRUAL_LEAVE_HOURS,
            ],
            [
                'readable_name' => 'Thâm niên',
                'variable_name' => 'S_SENIORITY',
                'variable_value' => $S_SENIORITY,
            ],
            [
                'readable_name' => 'Số giờ đi công tác có phí',
                'variable_name' => 'S_BUSINESS_TRIP_HAS_FEE_HOURS',
                'variable_value' => $S_BUSINESS_TRIP_HAS_FEE_HOURS,
            ],
            [
                'readable_name' => 'Số giờ đi công tác không có phí',
                'variable_name' => 'S_BUSINESS_TRIP_NO_FEE_HOURS',
                'variable_value' => $S_BUSINESS_TRIP_NO_FEE_HOURS,
            ],
            [
                'readable_name' => 'Tháng tạo bảng lương',
                'variable_name' => 'S_MONTH',
                'variable_value' => $calculationSheet->month
            ],
            [
                'readable_name' => 'Năm tạo bảng lương',
                'variable_name' => 'S_YEAR',
                'variable_value' => $calculationSheet->year
            ],
            [
                'readable_name' => 'Đếm số lượng ngày OT cuối tuần trên công ty (có check_in)',
                'variable_name' => 'S_OT_W_KEND_COUNT',
                'variable_value' => count($arrayCountOtInCompany)
            ],
            [
                'readable_name' => 'Đếm số lượng ngày OT cuối tuần ngoài công ty',
                'variable_name' => 'S_OT_W_END_OUTSIDE_COUNT',
                'variable_value' => count($arrayCountOtOutCompany)
            ],
            [
                'readable_name' => 'Đếm số lượng ngày đi công tác phí',
                'variable_name' => 'S_BUSINESS_TRIP_HAS_FEE_DAY',
                'variable_value' => count($arrayCountBusinessHasFee)
            ],
            [
                'readable_name' => 'Số ngày có checkin/out vào ngày nghỉ và nghỉ lễ',
                'variable_name' => 'S_COUNT_DAY_OF_HOLIDAY_OR_OFF_DAY_WHEN_CHECK_IN_OUT',
                'variable_value' => $checkInOutCountOffOrHolidayDay,
            ],
            [
                'readable_name' => 'Số giờ đi trễ về sớm',
                'variable_name' => 'S_LATE_HOURS',
                'variable_value' => $totalLateHours,
            ],
            [
                'readable_name' => 'Số giờ xin đơn không hưởng lương',
                'variable_name' => 'S_UNPAID_LEAVE_HOURS',
                'variable_value' => $unPaidLeaveHours,
            ],
            [
                'readable_name' => 'Mã số thuế thu nhập cá nhân',
                'variable_name' => 'S_PERSONAL_INCOME_TAX_CODE',
                'variable_value' => $employee->mst_code,
            ],
            [
                'readable_name' => 'Số ngày lễ',
                'variable_name' => 'S_COUNT_HOLIDAY',
                'variable_value' => $totalHoliday,
            ],
        ];

        // If have enable_makeup_request_form
        if ($clientSetting && !empty($clientSetting->enable_makeup_request_form)) {
            $variableDefault = array_merge($variableDefault, [
                [
                    'readable_name' => 'Số giờ không đi làm ở công ty',
                    'variable_name' => 'S_NOT_WORK_HOURS',
                    'variable_value' => $totalNotWorkingHours,
                ],
                [
                    'readable_name' => 'Số giờ đi trễ về sớm trong core time',
                    'variable_name' => 'S_CORE_LATE_HOURS',
                    'variable_value' => $missingHoursInCoreTime,
                ],
                [
                    'readable_name' => 'Số giờ làm bù',
                    'variable_name' => 'S_COMPEN_WORK_HOURS',
                    'variable_value' => $totalMakeupHours,
                ],
                [
                    'readable_name' => 'Số giờ làm bù tạo tay',
                    'variable_name' => 'S_MANUAL_COMPEN_WORK_HOURS',
                    'variable_value' => $totalManualMakeupHours,
                ],
                [
                    'readable_name' => 'Số giờ đi làm không checkin/checkout',
                    'variable_name' => 'S_NOT_CHECK_IN_OUT',
                    'variable_value' => $totalNotCheckInOut,
                ],
            ]);
        }

        // Merge with variable with condition satisfy
        return array_merge($variableDefault, array_merge($listVariableWithNotCondition, $listVariableWithCondition));
    }
    public function convertWorkTimeRegister(&$value, $key)
    {
        $model = new Timesheet();
        $model->fill($value);
        $value['workTimeRegister'] = $model->workTimeRegisterWhereLeaveRequest;
        foreach ($model->workTimeRegisterWhereLeaveRequest as &$item) {
            $item['workTimeRegisterPeriod'] = $item->workTimeRegisterPeriod;
        }
    }
    public function failed($exception)
    {

        // Send user notification of failure, etc...
        $this->sheet->status = 'error';
        if ($exception instanceof Throwable || $exception instanceof Error) {
            $this->sheet->error_message = $exception->getMessage();
        } else {
            $this->sheet->error_message = "Unknown error";
        }
        $this->sheet->save();
    }

    protected function getAllowanceVariable(ClientEmployee $employee)
    {
        if (!$employee->position)
            return 0;

        $position = $employee->position;
        $allowanceGroups = AllowanceGroup::select('*')
            ->where('client_id', $employee->client_id)
            ->where('position', 'LIKE', '%' . $position . '%')->get();
        if ($allowanceGroups->isEmpty())
            return 0;

        $validAllowanceGroups = collect([]);
        foreach ($allowanceGroups as $g) {
            $positions = explode(',', $g['position']);
            if (in_array($position, $positions)) {
                $validAllowanceGroups->push($g);
            }
        }

        if ($validAllowanceGroups->isEmpty())
            return 0;

        $allowanceGroupIds = $validAllowanceGroups->pluck('id')->all();
        $allowance = Allowance::query()
            ->selectRaw('SUM(allowance_value) AS total')
            ->whereIn('allowance_group_id', $allowanceGroupIds)
            ->first();
        return $allowance->total ?: 0;
    }

    public function createVariableWithCondition(&$listWithCondition, &$listCheckUnique, $date, $variables, $hour)
    {
        foreach ($variables as $variable) {
            if (!array_key_exists($variable['name_variable'] . '_' . $date, $listCheckUnique)) {
                if (floatval($hour) > 0) {
                    $isSatisfyCondition = ClientHelper::checkConditionSettingCompare(
                        $hour,
                        $variable['value'],
                        $variable['comparison_operator']
                    );
                    if ($isSatisfyCondition) {
                        $listWithCondition[$variable['name_variable']]['variable_value'] += 1;
                        $listCheckUnique[$variable['name_variable'] . '_' . $date] = 1;
                    }
                }
            }
        }
    }

    public function createVariableWithNotCondition(&$listVariableWithNotCondition, &$listCheckUnique, $date, $variable)
    {
        if (!array_key_exists($date . '_' . $variable, $listCheckUnique)) {
            $listCheckUnique[$date . '_' . $variable] = 1;
            $listVariableWithNotCondition[$variable]['variable_value'] += 1;
        }
    }

    public function createVariableDefaultCondition(&$listVariableWithCondition, $data)
    {
        foreach ($data as $item) {
            $listVariableWithCondition[$item['name_variable']] = [
                'readable_name' => Constant::LIST_S_VARIABLE_WITH_CONDITION[$item['key_condition']]['readable_name'],
                'variable_name' => $item['name_variable'],
                'variable_value' => 0,
            ];
        }
    }
}
