<?php

namespace App\Exports\Sheets;

use App\Models\ClientEmployee;
use App\Models\ClientYearHoliday;
use App\Models\Timesheet;
use App\Models\ViewCombinedTimesheet;
use App\Models\WorkTimeRegisterTimesheet;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class TimesheetOvertimehoursSheet implements WithTitle, FromView, WithStyles
{

    private $fromDate;
    private $toDate;
    private $employeeIds;

    public function __construct($employeeIds, $fromDate, $toDate)
    {
        $this->employeeIds = $employeeIds;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;

    }

    public function view(): View
    {
        logger("@@@ TimesheetTotalSheet");

        // Convert
        $start = Carbon::parse($this->fromDate);
        $end = Carbon::parse($this->toDate);
        $dates = [];

        for ($date = $start; $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        // List employee
        $employees = ClientEmployee::query()
            ->whereIn("id", $this->employeeIds)
            ->get();
        $dataTimesheet = [];
        $fistClientEmployee = $employees->first();
        $listIDTimeSheet = [];
        $listHoliday = ClientYearHoliday::where('client_id', $fistClientEmployee->client_id)->get()->keyBy('date');
        ViewCombinedTimesheet::query()
            ->where('schedule_date', '>=', $this->fromDate)
            ->where('schedule_date', '<=', $this->toDate)
            ->whereIn('client_employee_id', $this->employeeIds)
            ->orderBy("client_employee_id", "ASC")
            ->chunk(4000, function ($timesheets) use ($employees,  $listHoliday, &$dataTimesheet, &$listIDTimeSheet) {
                foreach ($timesheets as $item) {
                    $listIDTimeSheet[] = $item['timesheet_id'];
                }
                foreach($employees as &$employee) {
                    if(empty($dataTimesheet[$employee->id])) {
                        $dataTimesheet[$employee->id] = [];
                    }
                    foreach ($timesheets as $timesheet) {
                        if (!empty($timesheet->client_employee_id) && ($timesheet->client_employee_id != $employee->id)) {
                            continue;
                        }
                        if(empty($dataTimesheet[$employee->id][$timesheet->log_date])) {
                            $dataTimesheet[$employee->id][$timesheet->log_date] = [];
                        }
                        $tempTimeSheet = [];
                        $tempTimeSheet['is_holiday'] = false;
                        $tempTimeSheet['is_off_day'] = $timesheet->is_off_day;
                        if ($listHoliday->has($timesheet->schedule_date)) {
                            $tempTimeSheet['is_holiday'] = true;
                        }

                        $tempTimeSheet['overtime_hours'] = $timesheet->overtime_hours > 0 ? $timesheet->overtime_hours : '' ;
                        $dataTimesheet[$employee->id][$timesheet->log_date] = $tempTimeSheet;
                    }
                }
            });

        // Render three column have value with S_WORK_HOURS_OT_WEEKDAY, S_WORK_HOURS_OT_WEEKEND, S_WORK_HOURS_OT_HOLIDAY
        // Handle bellow to high performance
        $timeSheets = Timesheet::whereIn('id', $listIDTimeSheet)
            ->with('workTimeRegisterTimesheets')
            ->get()->groupBy('client_employee_id');
        foreach ($employees as $employee) {
            // Continue
            if (!$timeSheets->has($employee->id)) continue;
            // Prepare param
            $offDayOvertimeHours = 0;
            $holidayOvertimeHours = 0;
            $totalOvertimeHours = 0;
            $timesheetEmployee = $timeSheets->get($employee->id);
            // Loop to count variable
            foreach ($timesheetEmployee as $item) {
                $isHoliday = $dataTimesheet[$employee->id][$item->log_date]['is_holiday'];
                $isOffDay = $dataTimesheet[$employee->id][$item->log_date]['is_off_day'];
                if ($item->overtime_hours && (empty($item->workTimeRegisterTimesheets) || !($item->workTimeRegisterTimesheets->count() > 0))) {
                    // Total
                    $totalOvertimeHours += $item->overtime_hours;
                    // Off day
                    if ($isOffDay && !$isHoliday) $offDayOvertimeHours += $item->overtime_hours;
                    // Holiday
                    if (!$isOffDay && $isHoliday) $holidayOvertimeHours += $item->overtime_hours;
                } else {
                    foreach ($item->workTimeRegisterTimesheets as $wtr) {
                        if ($wtr->type == WorkTimeRegisterTimesheet::OT_TYPE) {
                            // Total
                            $totalOvertimeHours += $wtr->time_values;
                            // Off day
                            if ($isOffDay && !$isHoliday) $offDayOvertimeHours += $wtr->time_values;
                            // Holiday
                            if (!$isOffDay && $isHoliday) $holidayOvertimeHours += $wtr->time_values;
                        }
                    }
                }
            }

            $employee->off_day_overtime_hours = $offDayOvertimeHours;
            $employee->holiday_overtime_hours = $holidayOvertimeHours;
            $employee->weekday_overtime_hours = round($totalOvertimeHours - $offDayOvertimeHours - $holidayOvertimeHours, 2);
        }

        $data = [
            'employees' => $employees,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'dataTimesheet' => $dataTimesheet,
            'dates' => $dates
        ];

        return view('exports.timesheet-summary-overtime-hours-excel')->with($data);
    }

    // TODO remove?
    public function title(): string
    {
        return "Overtime hours";
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        return [
            3 => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
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
