<?php

namespace App\Exports\Sheets;

use App\Models\ClientEmployee;
use App\Models\ClientYearHoliday;
use App\Models\TimesheetShiftMapping;
use App\Models\Timesheet;
use App\Models\ViewCombinedTimesheet;
use App\Models\WorkTimeRegisterPeriod;
use App\Support\Constant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimesheetTotalSheet implements WithTitle, FromView, WithStyles
{

    private $fromDate;
    private $toDate;
    private $employeeIds;
    private $wt_category;
    private $wt_category_by_id;

    private $template;

    public function __construct($employeeIds, $fromDate, $toDate, $wt_category, $wt_category_by_id, $template = 1)
    {
        $this->employeeIds = $employeeIds;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->wt_category = $wt_category;
        $this->wt_category_by_id =  $wt_category_by_id;
        $this->template = $template;
    }

    public function view(): View
    {
        logger("@@@ TimesheetTotalSheet");

        // Convert
        $fromDate = $this->fromDate;
        $toDate = $this->toDate;
        $wt_category_by_id = $this->wt_category_by_id;

        // List employee
        $employees = ClientEmployee::query()
            ->whereIn("id", $this->employeeIds)
            ->get()
            ->map(function ($employee) use ($wt_category_by_id, $fromDate, $toDate) {
                $employee->standard_working_hours = 0;
                $employee->actual_working_hours = 0;
                $employee->paid_leave_hours = 0;
                $employee->ot_hours = 0;
                $employee->unpaid_leave_hours = 0;
                $employee->count_adjust_hours = 0;
                $employee->shift = 0;

                $periods = WorkTimeRegisterPeriod::query()
                    ->with('worktimeRegister')
                    ->whereHas('worktimeRegister', function ($query) use ($employee) {
                        $query
                            ->where('client_employee_id', $employee->id)
                            ->whereIn('type', ['leave_request', 'congtac_request'])
                            ->whereIn('status', ['approved']);
                    })
                    ->where([
                        ['date_time_register', '>=', $fromDate],
                        ['date_time_register', '<=', $toDate]
                    ])
                    ->orderBy('start_time')
                    ->get();
                    // Only apply leave request,bussiness trip for coming late and leave early
                    $employee['leave_and_business_period'] = $periods->groupBy('date_time_register');

                    $periods = $periods->where('worktimeRegister.type', 'leave_request')->groupBy('worktimeRegister.sub_type');

                foreach ($wt_category_by_id as $subType => $value) {
                    $subTypePeriods = $periods->has($subType) ? $periods->get($subType) : collect();
                    foreach ($value as  $item) {

                        $collection = $subTypePeriods->filter(function ($period) use ($item) {
                            return $period->worktimeRegister->category == $item;
                        })->map(function (WorkTimeRegisterPeriod $period) {
                            return $period->getDurationAttribute();
                        });
                        $employee[$subType . "_" . $item] = $collection->sum();
                    }
                }

                //get leave request which have been applied in the future
                $so_gio_phep_du_kien_tru = WorkTimeRegisterPeriod::getEstimatedTotalTime($employee->id, 'leave_request', 'authorized_leave', 'year_leave');
                $employee->year_paid_leave_count -= $so_gio_phep_du_kien_tru;
                return $employee;
            })
            ->keyBy('id');
        $timesheetShiftMapping = TimesheetShiftMapping::whereHas('timesheetShift')
            ->whereHas('timesheet', function ($query) {
                $query->whereBetween('log_date', [$this->fromDate, $this->toDate])
                    ->whereIn('client_employee_id', $this->employeeIds);
            })->with('timesheetShift')->get()->groupBy('timesheet_id');

        // Timesheet with approved adjust hours
        $timesheetWithApprovesAdjustHours = Timesheet::whereBetween('log_date', [$this->fromDate, $this->toDate])
            ->whereIn('client_employee_id', $this->employeeIds)
            ->where(function ($query) {
                $query->whereHas('approves', function ($subQuery) {
                    $subQuery->whereIn('type', Constant::LIST_TYPE_ADJUST_HOURS)
                        ->where('is_final_step', 1);
                })
                    ->orWhereHas('timesheetShiftMapping', function ($subQuery) {
                        $subQuery->whereHas('approves', function ($subQuery1) {
                            $subQuery1->whereIn('type', Constant::LIST_TYPE_ADJUST_HOURS)
                                ->where('is_final_step', 1);
                        });
                    });
            })
            ->withCount(['timesheetShiftMapping'=> function ($subQuery) {
                $subQuery->whereHas('approves', function ($subQuery1) {
                    $subQuery1->whereIn('type', Constant::LIST_TYPE_ADJUST_HOURS)
                        ->where('is_final_step', 1);
                })->with('approves');
            }])
            ->with('approves', function ($query) {
                $query->whereIn('type', Constant::LIST_TYPE_ADJUST_HOURS)
                    ->where('is_final_step', 1);
            })->get()->keyBy('id');

        $fistClientEmployee = $employees->first();
        $listHoliday = ClientYearHoliday::where('client_id', $fistClientEmployee->client_id)->get()->keyBy('date');

        ViewCombinedTimesheet::query()
            ->where('schedule_date', '>=', $this->fromDate)
            ->where('schedule_date', '<=', $this->toDate)
            ->whereIn('client_employee_id', $this->employeeIds)
            ->orderBy("client_employee_id", "ASC")
            ->chunk(4000, function ($timesheets) use ($employees, $timesheetShiftMapping, $timesheetWithApprovesAdjustHours, $listHoliday) {
                foreach ($timesheets as $timesheet) {
                    $employee = $employees->get($timesheet->client_employee_id);
                    if (!$employee) {
                        continue;
                    }
                    $scheduleWorkHours = 0;
                    $countGoLateAndLeaveEarly = 0;
                    // Mutilple shift
                    if ($timesheetShiftMapping->has($timesheet->timesheet_id)) {
                        foreach ($timesheetShiftMapping->get($timesheet->timesheet_id) as $item) {
                            // Work
                            $checkInWork = Carbon::parse($timesheet->log_date . ' ' . $item->shift_check_in);
                            $checkOutWork = Carbon::parse($timesheet->log_date . ' ' . $item->shift_check_out);
                            if ($item->shift_next_day) {
                                $checkOutWork->addDay();
                            }

                            // Timesheet
                            $checkIn = Carbon::parse($item->check_in);
                            $checkOut = Carbon::parse($item->check_out);

                            if (!empty($item->check_in) && $checkIn->isAfter($checkInWork)) {
                                $countGoLateAndLeaveEarly++;
                            }
                            if (!empty($item->check_out) && $checkOut->isBefore($checkOutWork)) {
                                $countGoLateAndLeaveEarly++;
                            }
                             $scheduleWorkHours += $item->schedule_shift_hours;
                        }

                    } else {
                        if ($listHoliday->has($timesheet->schedule_date)) {
                            $timesheet->is_holiday = true;
                        }
                        $workTimeRegisterPeriod = null;
                        if($employee['leave_and_business_period']->has($timesheet->schedule_date)) {
                            $workTimeRegisterPeriod = $employee['leave_and_business_period']->get($timesheet->schedule_date);
                        }
                        [$checkInLate, $checkOutLate] = $timesheet->getCheckinLateAndCheckOutEarlyLeave($workTimeRegisterPeriod);
                        $countGoLateAndLeaveEarly += $checkInLate ? 1 : 0;
                        $countGoLateAndLeaveEarly += $checkOutLate ? 1 : 0;
                        $scheduleWorkHours = $timesheet->is_holiday ?  $timesheet->working_hours : $timesheet->schedule_work_hours;
                    }
                    $paidLeaveHours = $timesheet->paid_leave_hours;
                    $workHours = $timesheet->is_holiday ? $scheduleWorkHours : $timesheet->working_hours;
                    $unpaidHours = $scheduleWorkHours - $workHours - $paidLeaveHours;
                    $otHours = $timesheet->overtime_hours;

                    if ($unpaidHours < 0) {
                        $unpaidHours = 0;
                    }
                    $employee->shift += $timesheet->shift;
                    $employee->actual_working_hours += $workHours;
                    $employee->paid_leave_hours += $paidLeaveHours;
                    $employee->ot_hours += $otHours;
                    $employee->unpaidHours += $timesheet->unpaid_leave_hours;
                    $employee->unpaid_leave_hours += round($unpaidHours, 2);
                    $employee->standard_working_hours += $scheduleWorkHours;
                    $employee->count_go_late_and_leave_early += $countGoLateAndLeaveEarly;
                    $employee->makeup_hours += $timesheet->makeup_hours;
                    $employee->manual_makeup_hours += $timesheet->manual_makeup_hours;
                    $employee->missing_hours_in_core_time += $timesheet->missing_hours_in_core_time;

                    if (!$timesheet->is_off_day && !$timesheet->is_holiday && $timesheet->working_hours == '0.0') {
                        if (!empty($timesheet->checkin_string) && empty($timesheet->checkout_string) || empty($timesheet->checkin_string) && !empty($timesheet->checkout_string)) {
                            $employee->total_not_checkin_out += $timesheet->schedule_work_hours - ($timesheet->paid_leave_hours + $timesheet->unpaid_leave_hours);
                        } elseif ($timesheet->paid_leave_hours == '0.0' && $timesheet->unpaid_leave_hours == '0.0') {
                            $employee->total_not_working += $timesheet->schedule_work_hours;
                        }
                    }

                    // Count adjust hours
                    if(!empty($timesheetWithApprovesAdjustHours[$timesheet->timesheet_id])) {
                        $timesheetApproved = $timesheetWithApprovesAdjustHours->get($timesheet->timesheet_id);
                        if($timesheetApproved->timesheet_shift_mapping_count) {
                            $employee->count_adjust_hours += $timesheetApproved->timesheet_shift_mapping_count;
                        } else {
                            $employee->count_adjust_hours++;
                        }
                    }
                }
            });

        // Total calculation hours
        foreach ($employees as $employee) {
            $totalCalculationHours = $employee->actual_working_hours + $employee->paid_leave_hours;
            $totalMakeup = $employee->makeup_hours;
            $totalManualMakeupHours = $employee->manual_makeup_hours;
            $totalAutoMakeupHours = $totalMakeup - $totalManualMakeupHours;

            // Thought two step to give a final $totalCalculationHours with client use compensatory
            if ($totalCalculationHours < $employee->standard_working_hours && $totalMakeup > 0) {
                $totalUnpaidLeaveHours = $employee->standard_working_hours - ($employee->actual_working_hours + $employee->paid_leave_hours);
                $remainUnpaid = $totalUnpaidLeaveHours;
                $totalUnpaidByCoverStepOne = $employee->unpaidHours + $employee->total_not_working;
                if ($this->template == 3) {
                    $totalUnpaidByCoverStepOne += $employee->total_not_checkin_out;
                    $totalUnpaidByCoverStepTwo = $totalUnpaidLeaveHours - $totalUnpaidByCoverStepOne;
                    // Step 1
                    if ($totalManualMakeupHours != '0.0') {
                        if ($totalManualMakeupHours <= $totalUnpaidByCoverStepOne) {
                            $totalCalculationHours += $totalManualMakeupHours;
                            $remainUnpaid -= $totalManualMakeupHours;
                        } else {
                            $totalCalculationHours += $totalUnpaidByCoverStepOne;
                            $remainUnpaid -= $totalUnpaidByCoverStepOne;
                            $totalAutoMakeupHours += ($totalManualMakeupHours - $totalUnpaidByCoverStepOne);
                        }
                    }

                    // Step 2
                    if ($totalAutoMakeupHours != '0.0' && $totalManualMakeupHours == '0.0') {
                        $totalCalculationHours += min($totalAutoMakeupHours, $totalUnpaidByCoverStepTwo);
                    } else {
                        $totalCalculationHours += min($totalAutoMakeupHours, $remainUnpaid);
                    }
                } else {
                    // Template 1 or 2
                    $totalUnpaidByCoverStepOne += $employee->missing_hours_in_core_time;
                    $totalUnpaidByCoverStepTwo = $totalUnpaidLeaveHours - $totalUnpaidByCoverStepOne;
                    $totalCalculationHours += min($totalMakeup, $totalUnpaidByCoverStepTwo);
                }
            }

            // Override
            $employee->total_calculation_hours = min($totalCalculationHours, $employee->standard_working_hours);
        }

        $data = [
            'employees' => $employees,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'wt_category' => $this->wt_category,
            'wt_category_by_id' => $this->wt_category_by_id,
        ];

        return view('exports.timesheet-total-excel')->with($data);
    }

    // TODO remove?
    public function title(): string
    {
        return "Total";
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('B')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        return [
            3 => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            4 => [
                'font' => ['bold' => true],
            ],
        ];
    }

    private function getUnPaidLeaveHours($employee)
    {
        $hours = 0;
        $paidLeaves = $employee
            ->worktimeRegister()
            ->where([
                ['start_time', '>=', $this->fromDate],
                ['end_time', '<=', $this->toDate],
                ['type', 'leave_request'],
                ['sub_type', 'unauthorized_leave'],
                ['status', 'approved'],
            ])
            ->get();

        foreach ($paidLeaves as $item) {
            foreach ($item->worktimeRegisterPeriod as $wtrp) {
                $hours += $wtrp->duration;
            }
        }

        return $hours;
    }
}
