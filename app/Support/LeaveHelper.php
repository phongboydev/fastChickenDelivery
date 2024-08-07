<?php

namespace App\Support;

use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\Precision;
use Spatie\Period\PeriodCollection;
use App\Models\Timesheet;
use App\Models\WorkTimeRegisterPeriod;
use App\Exceptions\HumanErrorException;
use App\Support\PeriodHelper;

class LeaveHelper
{
    const LEAVE_BALANCES = [
        'authorized_leave' => [
            'self_marriage_leave' => 24,
            'child_marriage_leave' => 8,
            'family_lost' => 24,
            'woman_leave' => 18,
            'baby_care' => 365,
            'changed_leave' => null,
            'covid_leave' => 0,
            'other_leave' => null,
        ],
        'unauthorized_leave' => [
            'unpaid_leave' => null,
            'pregnant_leave' => 1920,
            'self_sick_leave' => 1440,
            'child_sick' => 120,
            'wife_pregnant_leave' => 128,
            'prenatal_checkup_leave' => 40,
            'sick_leave' => 0,
            'other_leave' => null,
        ]
    ];

    const LIMIT_THE_LEAVE_APPLICATION = ["woman_leave" => 0.5, "baby_care" => 1];

    const YEAR_LEAVE_TYPE = ['last_year' => 0, 'current_year' => 1, 'next_year' => 2];

    static public function checkLeaveBalances($clientSetting, $newPeriods, $category, $subType, $employee)
    {
        $diffHours = 0;
        $clientHoursOffRemain = 0;

        $paidLeaveChangeSummary = WorktimeRegisterHelper::getLeaveChangeSummary($employee->id, $subType, $category);
        $clientHoursOffRemain = $paidLeaveChangeSummary['so_gio_phep_con_co_the_xin'];

        if ($clientHoursOffRemain !== null) {

            if ($clientHoursOffRemain <= 0) {
                throw new HumanErrorException(__("warning.E8.validate"));
            }

            $isCoincident = [];

            foreach ($newPeriods as $newPeriod) {
                $date = Carbon::parse($newPeriod['date_time_register'])->format('Y-m-d');
                $startTime = date('Y-m-d H:i:s', strtotime($date . ' ' . $newPeriod['start_time']));
                $endTime = date('Y-m-d H:i:s', strtotime(isset($newPeriod['next_day']) && $newPeriod['next_day'] == 1 ? Carbon::parse($date . ' ' . $newPeriod['end_time'])->addDay()->format('Y-m-d H:i') : $date . ' ' . $newPeriod['end_time']));
                $workSchedule = $employee->getWorkSchedule($date);

                if ($category !== 'year_leave') {
                    if (!Carbon::parse($startTime)->isCurrentYear() || !Carbon::parse($endTime)->isCurrentYear()) {
                        throw new HumanErrorException(__("warning.E14.validate"));
                    }
                }

                $diffHoursByCate = 0;

                // Multiple shift
                if ($clientSetting->enable_multiple_shift && $workSchedule->shift_enabled) {
                    $ts = Timesheet::where('client_employee_id', $employee->id)->whereDate('log_date', $date)->first();
                    $shiftMapping = $ts->timesheetShiftMapping;
                    $wtrPeriod = Period::make($startTime, $endTime, Precision::SECOND);

                    foreach ($shiftMapping as $item) {
                        $schedulePeriodsWithoutRest = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                        $overlap = $newPeriod['type_register'] == WorkTimeRegisterPeriod::TYPE_BY_HOUR ? $schedulePeriodsWithoutRest->overlapSingle(new PeriodCollection($wtrPeriod)) : $schedulePeriodsWithoutRest;

                        $calculate = $overlap->reduce(function ($carry, $period) {
                            return $carry + PeriodHelper::countHours($period);
                        }, 0);

                        $diffHours += $calculate;
                        $diffHoursByCate = $calculate;
                    }
                } else {
                    $diffSeconds = strtotime($endTime) - strtotime($startTime);

                    if (is_numeric($diffSeconds) && $diffSeconds > 0) {
                        $calculate = floatval($diffSeconds / 3600);
                        $diffHours += $calculate;
                        $diffHoursByCate = $calculate;
                    }

                    // subtract lunch break
                    if ($workSchedule->start_break && $workSchedule->end_break) {
                        $breakPeriod = $workSchedule->getRestPeriodAttribute();
                        $workingPeriod = Period::make($startTime, $endTime, Precision::SECOND);
                        $overlap = $breakPeriod->overlapSingle($workingPeriod);

                        if ($overlap) {
                            $overlapDuration = PeriodHelper::countHours($overlap);
                            $diffHours -= $overlapDuration;
                            $diffHoursByCate -= $overlapDuration;
                        }
                    }
                }

                if (isset(self::LIMIT_THE_LEAVE_APPLICATION[$category])) {
                    $categoryHours = self::LIMIT_THE_LEAVE_APPLICATION[$category];
                    if ($diffHoursByCate > $categoryHours) {
                        $typeName = __("leave_request.authorized." . $category);
                        throw new HumanErrorException(__("warning.E16.validate", ['category' => $typeName, 'hours' =>  $categoryHours]));
                    }

                    if (self::checkValidatebyDate($employee->id, $subType, $category, $date)) {
                        $isCoincident[] = $date;
                    }
                }
            }

            if (count($isCoincident)) {
                $errorContent = implode(', ', array_unique($isCoincident));
                $typeName = __("leave_request.authorized." . $category);
                throw new HumanErrorException(__("warning.E15.validate", ['datetime' => $errorContent, 'category' => $typeName, 'hours' => self::LIMIT_THE_LEAVE_APPLICATION[$category]]));
            }

            if ($diffHours > $clientHoursOffRemain) {
                throw new HumanErrorException(__("warning.E8.validate"));
            }
        }
    }

    static public function checkYearLeaveBalances($clientSetting, $newPeriods, $clientEmployee)
    {
        $currentYearDiffHours = 0;
        $nextYearDiffHours = 0;

        $workflowSetting = $clientSetting->clientWorkflowSetting;
        $paidLeaveChangeSummary = WorktimeRegisterHelper::getYearPaidLeaveChange($clientEmployee->id);
        $currentYearLimit = $paidLeaveChangeSummary['so_gio_phep_con_co_the_xin'];
        $nextYearLimit = $paidLeaveChangeSummary['so_gio_phep_con_co_the_xin_nam_sau'];

        foreach ($newPeriods as $newPeriod) {
            $date = Carbon::parse($newPeriod['date_time_register'])->format('Y-m-d');
            $startTime = date('Y-m-d H:i:s', strtotime($date . ' ' . $newPeriod['start_time']));
            $endTime = date('Y-m-d H:i:s', strtotime(isset($newPeriod['next_day']) && $newPeriod['next_day'] == 1 ? Carbon::parse($date . ' ' . $newPeriod['end_time'])->addDay()->format('Y-m-d H:i') : $date . ' ' . $newPeriod['end_time']));

            $workSchedule = $clientEmployee->getWorkSchedule($date);
            $workingPeriod = Period::make($startTime, $endTime, Precision::SECOND);

            $realWorkMinutes = 0;
            $currentYearOverlapSingleMinutes = 0;
            $nextYearOverlapSingleMinutes = 0;

            if ($newPeriod['type_register'] == WorkTimeRegisterPeriod::TYPE_ALL_DAY) {
                $timesheet = Timesheet::whereDate('log_date', $newPeriod['date_time_register'])
                    ->where('client_employee_id', $clientEmployee->id)->first();

                if (!empty($timesheet) && $timesheet->isUsingMultiShift($workflowSetting)) {
                    foreach ($timesheet->timesheetShiftMapping as $item) {
                        $overlaps = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                        foreach ($overlaps as $overlap) {
                            self::separateYearLeave($overlap, $paidLeaveChangeSummary, $currentYearOverlapSingleMinutes, $nextYearOverlapSingleMinutes, $realWorkMinutes);
                        }
                    }
                } else {
                    if (!empty($timesheet)) {
                        $workSchedule = $timesheet->getShiftWorkSchedule($workSchedule);
                    }
                    $diffs = PeriodHelper::subtract($workSchedule->work_schedule_period, $workSchedule->rest_period);
                    foreach ($diffs as $diff) {
                        self::separateYearLeave($diff, $paidLeaveChangeSummary, $currentYearOverlapSingleMinutes, $nextYearOverlapSingleMinutes, $realWorkMinutes);
                    }
                }
            } else {
                $timesheet = Timesheet::whereDate('log_date', $newPeriod['date_time_register'])
                    ->where('client_employee_id', $clientEmployee->id)->first();
                if (!empty($timesheet) && $timesheet->isUsingMultiShift($workflowSetting)) {
                    foreach ($timesheet->timesheetShiftMapping as $item) {
                        $schedulePeriodsWithoutRest = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                        $overlaps = $schedulePeriodsWithoutRest->overlapSingle(new PeriodCollection($workingPeriod));
                        foreach ($overlaps as $overlap) {
                            self::separateYearLeave($overlap, $paidLeaveChangeSummary, $currentYearOverlapSingleMinutes, $nextYearOverlapSingleMinutes, $realWorkMinutes);
                        }
                    }
                } else {
                    if (!empty($timesheet)) {
                        $workSchedule = $timesheet->getShiftWorkSchedule($workSchedule);
                    }
                    $diffs = PeriodHelper::subtract($workingPeriod, $workSchedule->rest_period);
                    foreach ($diffs as $diff) {
                        self::separateYearLeave($diff, $paidLeaveChangeSummary, $currentYearOverlapSingleMinutes, $nextYearOverlapSingleMinutes, $realWorkMinutes);
                    }
                }
            }

            if ($realWorkMinutes > 0) {
                $currentYearDiffHours += PeriodHelper::convertMinutestoHours($currentYearOverlapSingleMinutes);
                $nextYearDiffHours += PeriodHelper::convertMinutestoHours($nextYearOverlapSingleMinutes);

                if ($currentYearDiffHours > $currentYearLimit) {
                    throw new HumanErrorException(__("warning.E8.validate"));
                }

                if ($nextYearDiffHours > $nextYearLimit) {
                    throw new HumanErrorException(__("warning.E18.validate"));
                }
            }
        }
    }

    static public function separateYearLeave(Period $period, $paidLeaveChangeSummary, &$currentYearOverlapSingleMinutes, &$nextYearOverlapSingleMinutes, &$realWorkMinutes, $mode = true)
    {
        try {
            $yearLeaveStart = Carbon::parse($paidLeaveChangeSummary["bat_dau_su_dung_gio_phep"]);
            $yearLeaveEnd = Carbon::parse($paidLeaveChangeSummary["han_su_dung_gio_phep_hien_tai"]);
            $thisYearPeriod = Period::make($yearLeaveStart, $yearLeaveEnd, Precision::SECOND);

            if ($thisYearPeriod->overlapsWith($period)) {
                $currentYearOverlapSingle = $thisYearPeriod->overlapSingle($period);
                if ($currentYearOverlapSingle) {
                    $currentYearOverlapSingleMinutes += PeriodHelper::countMinutes($currentYearOverlapSingle);
                }
            } else {
                $nextYearLeaveStart =  Carbon::parse($paidLeaveChangeSummary["bat_dau_su_dung_gio_phep_nam_sau"]);
                $nextYearLeaveEnd = Carbon::parse($paidLeaveChangeSummary["han_su_dung_gio_phep_nam_sau"]);
                if ($nextYearLeaveStart && $nextYearLeaveEnd) {
                    $nextYear = Period::make($nextYearLeaveStart, $nextYearLeaveEnd, Precision::SECOND);
                    $nextYearOverlapSingle = $nextYear->overlapSingle($period);
                    if ($nextYearOverlapSingle) {
                        $nextYearOverlapSingleMinutes += PeriodHelper::countMinutes($nextYearOverlapSingle);
                    } else {
                        throw new \Exception(__("warning.E19.validate"));
                    }
                } else {
                    throw new \Exception(__("warning.E19.validate"));
                }
            }
            $realWorkMinutes += PeriodHelper::countMinutes($period);
        } catch (\Exception $e) {
            throw new HumanErrorException($e->getMessage());
        }
    }

    static public function checkValidatebyDate($clientEmployeeId, $sub_type, $category, $date)
    {
        return WorkTimeRegisterPeriod::where("date_time_register", $date)
            ->whereHas("worktimeRegister", function ($query) use ($clientEmployeeId, $sub_type, $category) {
                $query->where([
                    ["client_employee_id", $clientEmployeeId],
                    ["type", Constant::TYPE_LEAVE],
                    ["sub_type", $sub_type],
                    ["category", $category],
                    ["status", "!=", "canceled"],
                    ["status", "!=", "canceled_approved"]
                ]);
            })
            ->exists();
    }
}
