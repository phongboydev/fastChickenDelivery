<?php

namespace App\Support;

use App\Models\ClientWorkflowSetting;
use App\Models\Timesheet;
use Exception;
use App\Exceptions\HumanErrorException;
use Carbon\Carbon;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;
use App\Support\PeriodHelper;
use App\Models\ClientEmployee;
use App\Models\WorktimeRegister;
use App\Models\WorkTimeRegisterPeriod;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleGroup;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use App\Models\PaidLeaveChange;

class WorktimeRegisterHelper
{

    public static function getLeaveChangeSummary($clientEmployeeId, $subType = 'authorized_leave', $category = 'year_leave')
    {
        $clientEmployee = ClientEmployee::where('id', $clientEmployeeId)->first();
        $so_gio_phep_du_kien_tru = WorkTimeRegisterPeriod::getEstimatedTotalTime($clientEmployeeId, Constant::TYPE_LEAVE, $subType, $category);
        $so_gio_phep_con_lai_thuc_te = WorktimeRegisterHelper::checkLeaveBalanceAvailable($clientEmployee, $subType, $category, true);
        $so_gio_phep_con_co_the_xin = ($so_gio_phep_con_lai_thuc_te !== null) ? round($so_gio_phep_con_lai_thuc_te - $so_gio_phep_du_kien_tru, 2) : null;

        if (is_null($so_gio_phep_con_co_the_xin)) {
            $so_gio_phep_con_co_the_xin_disp = __("leave_request.unlimited");
        } else {
            $client = $clientEmployee->client;
            $displayAsHours = $client->clientWorkflowSetting->enable_show_hour_instead_of_day;
            if ($displayAsHours) {
                $label = __("model.timesheets.work_status.gio");
                $label = strtolower($label);
                if ($so_gio_phep_con_co_the_xin <= 0) {
                    $so_gio_phep_con_co_the_xin_disp = "0 " . $label;
                } else {
                    $so_gio_phep_con_co_the_xin_disp = $so_gio_phep_con_co_the_xin . ' ' . $label;
                }
            } else {
                $label = __("date_lowercase");
                if ($so_gio_phep_con_co_the_xin <= 0) {
                    $so_gio_phep_con_co_the_xin_disp = "0 " . $label;
                } else {
                    $standardWorkHoursPerDay = $client->standard_work_hours_per_day ?? 8;
                    $tempHour = number_format(($so_gio_phep_con_co_the_xin / $standardWorkHoursPerDay), 2) + 0;
                    $so_gio_phep_con_co_the_xin_disp = $tempHour . ' ' . $label;
                }
            }
        }

        return compact('so_gio_phep_con_lai_thuc_te', 'so_gio_phep_du_kien_tru', 'so_gio_phep_con_co_the_xin', 'so_gio_phep_con_co_the_xin_disp');
    }

    public static function getYearPaidLeaveChange($clientEmployeeId)
    {
        $clientEmployee = ClientEmployee::find($clientEmployeeId);
        $so_gio_phep_du_kien_tru_nam_nay = WorkTimeRegisterPeriod::getEstimatedTotalYearLeaveTime($clientEmployeeId);

        // Remaining actual hours (not deducted)
        $so_gio_phep_con_lai_thuc_te = $clientEmployee->year_paid_leave_count;
        $so_gio_con_lai_thuc_te_nam_truoc = $clientEmployee->last_year_paid_leave_count;
        $so_gio_con_lai_thuc_te_nam_sau = $clientEmployee->next_year_paid_leave_count;

        // Remaining leave balance that can be requested
        $so_gio_phep_con_co_the_xin =  $so_gio_phep_con_lai_thuc_te - $so_gio_phep_du_kien_tru_nam_nay;
        $so_gio_phep_con_co_the_xin_nam_truoc = 0;

        $lastYearPaidLeaveExpiry = !empty($clientEmployee->last_year_paid_leave_expiry) ? Carbon::now()->lte($clientEmployee->last_year_paid_leave_expiry) : false;

        if ($clientEmployee->last_year_paid_leave_expiry && $lastYearPaidLeaveExpiry && $so_gio_con_lai_thuc_te_nam_truoc > 0) {
            // Add the leave balance that can be carried forward to the available leave balance
            $so_gio_phep_con_co_the_xin += $so_gio_con_lai_thuc_te_nam_truoc;
            $so_gio_phep_con_co_the_xin_nam_truoc = max(0, $so_gio_con_lai_thuc_te_nam_truoc - $so_gio_phep_du_kien_tru_nam_nay);
        }

        // Expected subsequent year
        $so_gio_phep_du_kien_tru_nam_sau = WorkTimeRegisterPeriod::getEstimatedTotalYearLeaveTime($clientEmployeeId, true);
        $so_gio_phep_con_co_the_xin_nam_sau =  $so_gio_con_lai_thuc_te_nam_sau - $so_gio_phep_du_kien_tru_nam_sau;

        return [
            // This Year
            'bat_dau_su_dung_gio_phep' => $clientEmployee->year_paid_leave_start_original,
            'bat_dau_su_dung_gio_phep_hien_tai' => $clientEmployee->year_paid_leave_start,
            'so_gio_phep_con_lai_thuc_te' => $so_gio_phep_con_lai_thuc_te,
            'so_gio_phep_du_kien_tru' => $so_gio_phep_du_kien_tru_nam_nay,
            'so_gio_phep_con_co_the_xin' => round($so_gio_phep_con_co_the_xin, 2),
            'han_su_dung_gio_phep_hien_tai' => $clientEmployee->year_paid_leave_expiry,
            // Last Year
            'bat_dau_su_dung_gio_phep_nam_truoc' => $clientEmployee->last_year_paid_leave_start,
            'so_gio_con_lai_thuc_te_nam_truoc' => $lastYearPaidLeaveExpiry ? $so_gio_con_lai_thuc_te_nam_truoc : 0,
            'so_gio_phep_con_co_the_xin_nam_truoc' => $lastYearPaidLeaveExpiry ? round($so_gio_phep_con_co_the_xin_nam_truoc, 2) : 0,
            'han_su_dung_gio_phep_nam_truoc' => $lastYearPaidLeaveExpiry ? $clientEmployee->last_year_paid_leave_expiry : null,
            // Next Year
            'so_gio_con_lai_thuc_te_nam_sau' => $so_gio_con_lai_thuc_te_nam_sau,
            'so_gio_phep_con_co_the_xin_nam_sau' => round($so_gio_phep_con_co_the_xin_nam_sau, 2),
            'bat_dau_su_dung_gio_phep_nam_sau' => $clientEmployee->next_year_paid_leave_start,
            'so_gio_phep_du_kien_tru_nam_sau' => $so_gio_phep_du_kien_tru_nam_sau,
            'han_su_dung_gio_phep_nam_sau' => $clientEmployee->next_year_paid_leave_expiry,
        ];
    }

    /*
    * Check the remaining leave balance that can be requested
    * $subType = 'authorized_leave' or 'unauthorized_leave'
    * $category = 'self_marriage_leave', 'child_marriage_leave', 'family_lost'
    * $return = true || false || null (not yet set up)
    */
    public static function checkLeaveBalanceAvailable($clientEmployee, $subType, $category, $return = false)
    {
        if ($clientEmployee) {
            if ($category == 'year_leave') {
                return $return ? $clientEmployee->year_paid_leave_count : $clientEmployee->year_paid_leave_count > 0;
            } else {
                $leaveBalance = $clientEmployee->leave_balance;

                if ($leaveBalance !== null) {
                    $leave = json_decode($leaveBalance, true);
                    if (Arr::has($leave, "$subType.$category")) {
                        return $return ? Arr::get($leave, "$subType.$category") : Arr::get($leave, "$subType.$category") !== null;
                    }
                } else {
                    return null;
                }
            }
        }

        return $return ? null : false;
    }

    public static function processLeaveChange($workTimeRegister)
    {
        if (!$workTimeRegister || !$workTimeRegister->client) return;

        $clientId = $workTimeRegister->client['id'];
        $clientEmployee = $workTimeRegister->clientEmployee;
        $currentWorkScheduleGroupTemplateId = $clientEmployee['work_schedule_group_template_id'];
        $workflowSetting = $workTimeRegister->client->clientWorkflowSetting;
        $checkLeaveBalanceAvailable = WorktimeRegisterHelper::checkLeaveBalanceAvailable($clientEmployee, $workTimeRegister->sub_type, $workTimeRegister->category, true);

        if ($workTimeRegister->type == Constant::TYPE_LEAVE && !Str::of($workTimeRegister->category)->isUuid() && $checkLeaveBalanceAvailable) {
            /** @var WorkTimeRegisterPeriod[]|\Illuminate\Support\Collection $periods */
            $periods = WorkTimeRegisterPeriod::where('worktime_register_id', $workTimeRegister->id)->get();

            if ($periods->isEmpty()) return;

            if ($periods->isNotEmpty()) {
                foreach ($periods as $period) {

                    $workScheduleGroup = WorkScheduleGroup::where('client_id', $clientId)
                        ->where('work_schedule_group_template_id', $currentWorkScheduleGroupTemplateId)
                        ->whereDate('timesheet_from', '<=', $period->date_time_register)
                        ->whereDate('timesheet_to', '>=', $period->date_time_register)->first();

                    if (!$workScheduleGroup) {
                        throw new HumanErrorException('Can not find the workScheduleGroup of template id: ' .  $currentWorkScheduleGroupTemplateId);
                    }

                    $workSchedule = WorkSchedule::where('client_id', $clientId)
                        ->whereDate('schedule_date', '=', $period->date_time_register)
                        ->where('work_schedule_group_id', $workScheduleGroup->id)->first();

                    if (!$workSchedule) {
                        throw new HumanErrorException(__("model.timesheets.calendar_month_not_set_up") . ' ' . $period->date_time_register);
                    }

                    $wtrPeriod = $period->getPeriod();

                    $realWorkMinutes = 0;
                    if ($period->type_register == WorkTimeRegisterPeriod::TYPE_ALL_DAY) {
                        $timesheet = Timesheet::whereDate('log_date', $period->date_time_register)
                            ->where('client_employee_id', $clientEmployee->id)->first();
                        if (!empty($timesheet) && $timesheet->isUsingMultiShift($workflowSetting)) {
                            foreach ($timesheet->timesheetShiftMapping as $item) {
                                $overlap = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                                $realWorkMinutes += $overlap->reduce(function ($carry, $period) {
                                    $carry += PeriodHelper::countMinutes($period);
                                    return $carry;
                                }, 0);
                            }
                        } else {
                            if (!empty($timesheet)) {
                                $workSchedule = $timesheet->getShiftWorkSchedule($workSchedule);
                            }
                            $diff = PeriodHelper::subtract($workSchedule->work_schedule_period, $workSchedule->rest_period);
                            $realWorkMinutes += $diff->reduce(function ($carry, $period) {
                                $carry += PeriodHelper::countMinutes($period);
                                return $carry;
                            }, 0);
                        }
                    } else {
                        $timesheet = Timesheet::whereDate('log_date', $period->date_time_register)
                            ->where('client_employee_id', $clientEmployee->id)->first();
                        if (!empty($timesheet) && $timesheet->isUsingMultiShift($workflowSetting)) {
                            foreach ($timesheet->timesheetShiftMapping as $item) {
                                $schedulePeriodsWithoutRest = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                                $overlap = $schedulePeriodsWithoutRest->overlapSingle(new PeriodCollection($wtrPeriod));
                                $realWorkMinutes += $overlap->reduce(function ($carry, $period) {
                                    $carry += (PeriodHelper::countMinutes($period));
                                    return $carry;
                                }, 0);
                            }
                        } else {
                            if (!empty($timesheet)) {
                                $workSchedule = $timesheet->getShiftWorkSchedule($workSchedule);
                            }
                            $startTime = Carbon::parse($period->date_time_register . ' ' . $period->start_time);
                            $endTime = $period->next_day ? Carbon::parse($period->date_time_register . ' ' . $period->end_time)->addDay() : Carbon::parse($period->date_time_register . ' ' . $period->end_time);
                            $requestPeriod = PeriodHelper::makePeriod($startTime, $endTime);
                            $diff = PeriodHelper::subtract($requestPeriod, $workSchedule->rest_period);

                            $realWorkMinutes += $diff->reduce(function ($carry, $period) {
                                $carry += PeriodHelper::countMinutes($period);
                                return $carry;
                            }, 0);
                        }
                    }

                    if ($realWorkMinutes > 0) {
                        $realWorkHours = round($realWorkMinutes / 60, 2, PHP_ROUND_HALF_DOWN);
                        $period->so_gio_tam_tinh = $realWorkHours;
                        $period->save();
                    }
                }
            }
        }
    }

    /**
     * Calculate the number of hours to be deducted based on work schedule (last year - current year - next year)
     * $mode = false (only calculate the number of hours, without updating it to the employee)
     * $today = false (exclude today)
     */
    public static function processYearLeaveChange($workTimeRegister, $mode = false, $today = false)
    {
        if (!$workTimeRegister || !$workTimeRegister->client) return;

        $clientId = $workTimeRegister->client['id'];
        $clientEmployee = $workTimeRegister->clientEmployee;
        $currentWorkScheduleGroupTemplateId = $clientEmployee['work_schedule_group_template_id'];
        $workflowSetting = $workTimeRegister->client->clientWorkflowSetting;

        /** @var WorkTimeRegisterPeriod[]|\Illuminate\Support\Collection $periods */
        $periods = WorkTimeRegisterPeriod::where('worktime_register_id', $workTimeRegister->id)->get();

        if ($periods->isEmpty()) return;

        if ($periods->isNotEmpty()) {
            foreach ($periods as $period) {

                $paidLeaveChangeSummary = WorktimeRegisterHelper::getYearPaidLeaveChange($clientEmployee->id);

                if ($mode && $period->so_gio_tam_tinh != 0) {
                    if ($today ? $period->date_time_register > date('Y-m-d') : $period->date_time_register >= date('Y-m-d')) {
                        continue;
                    }
                    if ($period->da_tru) {
                        continue;
                    }
                }

                $workScheduleGroup = WorkScheduleGroup::where([
                    ['client_id', '=', $clientId],
                    ['work_schedule_group_template_id', '=', $currentWorkScheduleGroupTemplateId],
                    ['timesheet_from', '<=', $period->date_time_register],
                    ['timesheet_to', '>=', $period->date_time_register]
                ])->first();

                if (!$workScheduleGroup) {
                    throw new HumanErrorException('Can not find the workScheduleGroup of template id: ' .  $currentWorkScheduleGroupTemplateId);
                }

                $workSchedule = WorkSchedule::where([
                    ['client_id', '=', $clientId],
                    ['schedule_date', '=', $period->date_time_register],
                    ['work_schedule_group_id', '=', $workScheduleGroup->id]
                ])->first();

                if (!$workSchedule) {
                    throw new HumanErrorException(__("model.timesheets.calendar_month_not_set_up") . ' ' . $period->date_time_register);
                }

                $wtrPeriod = $period->getPeriod();

                $realWorkMinutes = 0;
                $currentYearOverlapSingleMinutes = 0;
                $lastYearOverlapSingleMinutes = 0;
                $nextYearOverlapSingleMinutes = 0;

                if ($period->type_register == WorkTimeRegisterPeriod::TYPE_ALL_DAY) {
                    $timesheet = Timesheet::whereDate('log_date', $period->date_time_register)
                        ->where('client_employee_id', $clientEmployee->id)->first();

                    if (!empty($timesheet) && $timesheet->isUsingMultiShift($workflowSetting)) {
                        foreach ($timesheet->timesheetShiftMapping as $item) {
                            $overlaps = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                            foreach ($overlaps as $overlap) {
                                self::splitTimeYearLeave($overlap, $paidLeaveChangeSummary, $currentYearOverlapSingleMinutes, $lastYearOverlapSingleMinutes, $nextYearOverlapSingleMinutes, $realWorkMinutes, $mode);
                            }
                        }
                    } else {
                        if (!empty($timesheet)) {
                            $workSchedule = $timesheet->getShiftWorkSchedule($workSchedule);
                        }
                        $diffs = PeriodHelper::subtract($workSchedule->work_schedule_period, $workSchedule->rest_period);
                        foreach ($diffs as $diff) {
                            self::splitTimeYearLeave($diff, $paidLeaveChangeSummary, $currentYearOverlapSingleMinutes, $lastYearOverlapSingleMinutes, $nextYearOverlapSingleMinutes, $realWorkMinutes, $mode);
                        }
                    }
                } else {
                    $timesheet = Timesheet::whereDate('log_date', $period->date_time_register)
                        ->where('client_employee_id', $clientEmployee->id)->first();
                    if (!empty($timesheet) && $timesheet->isUsingMultiShift($workflowSetting)) {
                        foreach ($timesheet->timesheetShiftMapping as $item) {
                            $schedulePeriodsWithoutRest = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                            $overlaps = $schedulePeriodsWithoutRest->overlapSingle(new PeriodCollection($wtrPeriod));
                            foreach ($overlaps as $overlap) {
                                self::splitTimeYearLeave($overlap, $paidLeaveChangeSummary, $currentYearOverlapSingleMinutes, $lastYearOverlapSingleMinutes, $nextYearOverlapSingleMinutes, $realWorkMinutes, $mode);
                            }
                        }
                    } else {
                        if (!empty($timesheet)) {
                            $workSchedule = $timesheet->getShiftWorkSchedule($workSchedule);
                        }
                        $diffs = PeriodHelper::subtract($wtrPeriod, $workSchedule->rest_period);
                        foreach ($diffs as $diff) {
                            self::splitTimeYearLeave($diff, $paidLeaveChangeSummary, $currentYearOverlapSingleMinutes, $lastYearOverlapSingleMinutes, $nextYearOverlapSingleMinutes, $realWorkMinutes, $mode);
                        }
                    }
                }

                if ($realWorkMinutes > 0) {
                    $realWorkHours = PeriodHelper::convertMinutestoHours($realWorkMinutes);
                    $lastYear = PeriodHelper::convertMinutestoHours($lastYearOverlapSingleMinutes);
                    $currentYear = PeriodHelper::convertMinutestoHours($currentYearOverlapSingleMinutes);
                    $nextYear = PeriodHelper::convertMinutestoHours($nextYearOverlapSingleMinutes);
                    $lastYearPaidLeave = $mode ? $paidLeaveChangeSummary["so_gio_con_lai_thuc_te_nam_truoc"] : $paidLeaveChangeSummary["so_gio_phep_con_co_the_xin_nam_truoc"];
                    $deductionLastYear = min($lastYear, $lastYearPaidLeave);
                    if ($deductionLastYear != $lastYear) {
                        $currentYear += $lastYear - $deductionLastYear;
                        $lastYear = $deductionLastYear;
                    }
                    $deductionDetails = [
                        'last_year' => $lastYear,
                        'current_year' => $currentYear,
                        'next_year' => $nextYear
                    ];

                    $period->so_gio_tam_tinh = $realWorkHours;
                    $period->logical_management = true;
                    if ($mode) {
                        $period->da_tru = true;

                        foreach ($deductionDetails as $key => $hours) {
                            if ($hours > 0) {
                                PaidLeaveChange::create([
                                    'client_id' => $clientId,
                                    'client_employee_id' => $workTimeRegister['client_employee_id'],
                                    'work_time_register_id' => $workTimeRegister['id'],
                                    'category' => $workTimeRegister['category'],
                                    'year_leave_type' => LeaveHelper::YEAR_LEAVE_TYPE[$key],
                                    'changed_ammount' => -1 * $hours,
                                    'changed_reason' => Constant::TYPE_SYSTEM,
                                    'effective_at' => $workTimeRegister['approved_date'],
                                    'month' => Carbon::parse($period->date_time_register)->format('n'),
                                    'year' => Carbon::parse($period->date_time_register)->format('Y')
                                ]);
                            }
                        }
                    }
                    $period->deduction_details = json_encode($deductionDetails);
                    $period->save();
                }
            }
        }
    }

    static public function splitTimeYearLeave(Period $period, $paidLeaveChangeSummary, &$currentYearOverlapSingleMinutes, &$lastYearOverlapSingleMinutes, &$nextYearOverlapSingleMinutes, &$realWorkMinutes, $mode = true)
    {
        try {
            $yearLeaveStart = Carbon::parse($paidLeaveChangeSummary["bat_dau_su_dung_gio_phep"]);
            $yearLeaveEnd = Carbon::parse($paidLeaveChangeSummary["han_su_dung_gio_phep_hien_tai"]);
            $thisYearPeriod = Period::make($yearLeaveStart, $yearLeaveEnd, Precision::SECOND);

            if ($thisYearPeriod->overlapsWith($period)) {

                $lastYearLeaveStart = Carbon::parse($paidLeaveChangeSummary["bat_dau_su_dung_gio_phep_nam_truoc"]);
                $lastYearLeaveEnd = Carbon::parse($paidLeaveChangeSummary["han_su_dung_gio_phep_nam_truoc"]);

                $lastYear = Period::make($lastYearLeaveStart, $lastYearLeaveEnd, Precision::SECOND);
                $lastYearOverlapSingle = $lastYear->overlapSingle($period);

                if ($lastYearOverlapSingle) {
                    $lastYearOverlapSingleMinutes += PeriodHelper::countMinutes($lastYearOverlapSingle);
                } else {
                    $thisYearLeaveStart = $paidLeaveChangeSummary["bat_dau_su_dung_gio_phep_hien_tai"];
                    $thisYearLeaveEnd = $paidLeaveChangeSummary["han_su_dung_gio_phep_hien_tai"];

                    $thisYear = Period::make($thisYearLeaveStart, $thisYearLeaveEnd, Precision::SECOND);
                    $thisYearOverlapSingle = $thisYear->overlapSingle($period);
                    if ($thisYearOverlapSingle) {
                        $currentYearOverlapSingleMinutes += PeriodHelper::countMinutes($thisYearOverlapSingle);
                    }
                }
            } else {
                $nextYearLeaveStart =  Carbon::parse($paidLeaveChangeSummary["bat_dau_su_dung_gio_phep_nam_sau"]);
                $nextYearLeaveEnd = Carbon::parse($paidLeaveChangeSummary["han_su_dung_gio_phep_nam_sau"]);
                if ($nextYearLeaveStart && $nextYearLeaveEnd) {
                    $nextYear = Period::make($nextYearLeaveStart, $nextYearLeaveEnd, Precision::SECOND);
                    $nextYearOverlapSingle = $nextYear->overlapSingle($period);
                    if ($nextYearOverlapSingle) {
                        $nextYearOverlapSingleMinutes += PeriodHelper::countMinutes($nextYearOverlapSingle);
                    }
                }
            }
            $realWorkMinutes += PeriodHelper::countMinutes($period);
        } catch (\Exception $e) {
            // Handle the exception here
        }
    }

    public static function generateNextID($ID)
    {

        $ID = explode('-', $ID);

        if (count($ID) != 2) return '';

        $currentID = ltrim($ID[1], '0');

        $nextID = '';

        if (is_numeric($currentID)) {
            $c = $currentID % 10;

            if ($c == 9) {
                $s = count(str_split($currentID));

                $start = pow(10, $s);

                $v = str_split($start);

                array_pop($v);
                array_pop($v);

                $n = floor($currentID / 10) + 1;

                $nextID = $n . 'A';
            } else {
                $nextID = $currentID + 1;
            }
        } else {
            $c = str_split($currentID);
            $n = array_pop($c);

            $nextID = implode('', $c) . (++$n);
        }

        return strtoupper($ID[0] . '-' . str_pad($nextID, 5, '0', STR_PAD_LEFT));
    }


    /**
     * @throws AuthenticationException
     */
    public static function checkValidateDeadlineApprove($newPeriods, $employee, $isApproving = false)
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $touchedDate = [];
        foreach ($newPeriods as $item) {
            if (strtotime($item['date_time_register']) < strtotime($now)) {
                $touchedDate[] = $item['date_time_register'];
            }
        }
        if (empty($touchedDate)) return;
        $workSchedules = WorkSchedule::where('client_id', $employee->client_id)
            ->whereHas('workScheduleGroup', function ($group) use ($employee) {
                $group->where(
                    'work_schedule_group_template_id',
                    $employee->work_schedule_group_template_id
                );
            })
            ->whereIn('schedule_date', $touchedDate)
            ->with('workScheduleGroup')
            ->get();
        foreach ($workSchedules as $workSchedule) {
            if (is_null($workSchedule->workScheduleGroup->approve_deadline_at)) {
                continue;
            }
            if (strtotime($now) > strtotime($workSchedule->workScheduleGroup->approve_deadline_at)) {
                $message = $isApproving ? "warning.show_not_approve_application_when_exceed_the_approved_deadline" : "warning.show_not_create_form_when_exceed_the_approved_deadline";
                throw new AuthenticationException(__($message));
            }
        }
    }

    public static function getCountIsUseLeaveRequest($condition, $thisYear = true)
    {
        $workTimeRegisterPeriod = WorkTimeRegisterPeriod::where('date_time_register', '>=', $condition['start'])
            ->where('date_time_register', '<=', $condition['end'])
            ->whereHas('worktimeRegister', function ($query) use ($condition) {
                $query->where('type', 'leave_request')
                    ->where('sub_type', $condition['sub_type'])
                    ->where('category', $condition['category'])
                    ->where('status', 'approved')
                    ->where('client_employee_id', $condition['client_employee_id']);
            });
        if ($condition['category'] == 'year_leave') {
            $count = $workTimeRegisterPeriod->get()->sum(function ($item) use ($thisYear) {
                $hours = 0;

                if ($thisYear) {
                    $hours += $item->deduction_details == null ? $item->so_gio_tam_tinh : $item->deduction_current_year;
                } else {
                    $hours += $item->deduction_last_year;
                }

                return $hours;
            }) ?? 0;
        } else {
            $count = $workTimeRegisterPeriod->get()->sum('duration_for_leave_request') ?? 0;
        }
        return $count;
    }
    public static function isCheckInLateOrCheckOutEarly($param)
    {
        $isCheckInLateOrCheckOutEarly = 0;
        if ($param['mode'] == 'check_in') {
            if (
                strtotime($param['employee_check_in']) >= strtotime($param['start_period']) &&
                strtotime($param['employee_check_in']) <= strtotime($param['end_period'])
            ) {
                if ($param['start_period']->isAfter($param['work_check_in'])) {
                    $isCheckInLateOrCheckOutEarly = 1;
                }
            } else if (strtotime($param['employee_check_in']) < strtotime($param['start_period'])) {
                if ($param['employee_check_in']->isAfter($param['work_check_in'])) {
                    $isCheckInLateOrCheckOutEarly = 1;
                }
            } else if(strtotime($param['employee_check_in']) > strtotime($param['end_period'])) {
                 if( !empty($param['start_break_time']) && !empty($param['end_break_time']) && strtotime($param['end_period']) >= strtotime($param['start_break_time']) && strtotime($param['end_period']) <= strtotime($param['end_break_time'])){
                     if ($param['employee_check_in']->isAfter($param['end_break_time'])) {
                         $isCheckInLateOrCheckOutEarly = 1;
                     }
                 } else {
                     if ($param['employee_check_in']->isAfter($param['work_check_in'])) {
                         $isCheckInLateOrCheckOutEarly = 1;
                     }
                 }
            }
        } else {
            if (
                strtotime($param['employee_check_out']) >= strtotime($param['start_period']) &&
                strtotime($param['employee_check_out']) <= strtotime($param['end_period'])
            ) {
                if (strtotime($param['end_period']) < strtotime($param['work_check_out'])) {
                    $isCheckInLateOrCheckOutEarly = 1;
                }
            } else if (
                strtotime($param['employee_check_out']) < strtotime($param['start_period']) ||
                strtotime($param['employee_check_out']) > strtotime($param['end_period'])
            ) {
                if ($param['employee_check_out']->isBefore($param['work_check_out'])) {
                    $isCheckInLateOrCheckOutEarly = 1;
                }
            }
        }
        return $isCheckInLateOrCheckOutEarly;
    }

    /**
     * @throws HumanErrorException
     * @throws AuthenticationException
     */
    public static function validateApplication($approves)
    {
        $clientSetting = ClientWorkflowSetting::where('client_id', $approves[0]->client_id)->first();
        foreach ($approves as $approve) {
            $employee = null;
            if (in_array($approve->target_type, Constant::TYPE_CHECK_VALIDATE_APPROVED)) {
                $listDate = [];
                $type = $approve->target_type;
                if ($type == 'App\Models\WorktimeRegister') {
                    $workTimeRegister = WorktimeRegister::where('id', $approve->target_id)->with(['clientEmployee'])->first();
                    if ($workTimeRegister->type == 'timesheet') {
                        $date = Carbon::parse($workTimeRegister->start_time)->format('Y-m-d');
                        $listDate[] = [
                            'date_time_register' => $date
                        ];
                    } else {
                        $content = json_decode($approve->content);
                        if (!empty($content->workTimeRegisterPeriod)) {
                            $workTimeRegisterPeriod = $content->workTimeRegisterPeriod;
                            foreach ($workTimeRegisterPeriod as $item) {
                                $listDate[] = [
                                    'date_time_register' => $item->date_time_register
                                ];
                            }
                        }
                    }
                    $employee = $workTimeRegister->clientEmployee;

                    // Validate form when user change setting
                    self::validateWhenUserChangeSetting($workTimeRegister, $clientSetting);
                } else if ($type == 'App\Models\Timesheet') {
                    $target = $approve->target;
                    $employee = $target->clientEmployee;
                    $listDate[] = [
                        'date_time_register' => $target->log_date
                    ];
                } else if ($type == 'App\Models\TimesheetShiftMapping') {
                    $target = $approve->targetWithTrashed;
                    $timeSheet = $target->timesheet;
                    $employee = $timeSheet->clientEmployee;
                    $listDate[] = [
                        'date_time_register' => $timeSheet->log_date
                    ];
                }
                // Validate form request when exceed the approve deadline of the past
                WorktimeRegisterHelper::checkValidateDeadlineApprove($listDate, $employee, true);
            }
        }
    }

    /**
     * @throws HumanErrorException
     */
    public static function validateWhenUserChangeSetting($wtr, $setting)
    {
        // Validate form when user change setting flow business transportation
        if (
            $wtr['type'] == Constant::TYPE_BUSINESS
            && !$setting->enable_transportation_request
            && (isset($wtr['category']) && in_array($wtr['category'], Constant::TYPE_TRANSPORTATION))
        ) {
            throw new HumanErrorException(__("setting_is_changed_please_reload_page"));
        }
    }
}
