<?php

namespace App\Exports\Sheets;

use App\Models\ClientEmployee;
use App\Models\ClientYearHoliday;
use App\Models\Timesheet;
use App\Models\ViewCombinedTimesheet;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class TimesheetWorkinghoursSheet implements WithTitle, FromView, WithStyles
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
        $listHoliday = ClientYearHoliday::where('client_id', $fistClientEmployee->client_id)->get()->keyBy('date');
        ViewCombinedTimesheet::query()
            ->where('schedule_date', '>=', $this->fromDate)
            ->where('schedule_date', '<=', $this->toDate)
            ->whereIn('client_employee_id', $this->employeeIds)
            ->orderBy("client_employee_id", "ASC")
            ->chunk(4000, function ($timesheets) use ($employees,  $listHoliday, &$dataTimesheet) {
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
                        $datatimesheets = [];
                        $datatimesheets['working_hour'] = $timesheet->working_hours;
                        $datatimesheets['paid_leave_hours'] = $timesheet->paid_leave_hours;
                        $datatimesheets['unpaid_leave_hours'] = $timesheet->unpaid_leave_hours;
                        $datatimesheets['is_holiday'] = false;
                        $datatimesheets['is_off_day'] = $timesheet->is_off_day;
                        if ($listHoliday->has($timesheet->schedule_date)) {
                            $datatimesheets['is_holiday'] = true;
                        }
                        $datatimesheets['show'] = '';
                        $datatimesheets['color'] = '';
                        if($timesheet->working_hours > 0) {
                            $datatimesheets['show'] = $timesheet->working_hours;
                            if($timesheet->working_hours < $timesheet->schedule_work_hours) {
                                $datatimesheets['color'] = 'red';
                            }
                        }else if($timesheet->paid_leave_hours > 0) {
                            $datatimesheets['show'] = 'P';
                            $datatimesheets['color'] = 'red';
                        }
                        $datatimesheets['schedule_work_hours'] = $timesheet->schedule_work_hours;
                        $dataTimesheet[$employee->id][$timesheet->log_date] = $datatimesheets;

                    }
                }
            })            ;

        $data = [
            'employees' => $employees,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'dataTimesheet' => $dataTimesheet,
            'dates' => $dates
        ];

        return view('exports.timesheet-summary-working-hours-excel')->with($data);
    }

    // TODO remove?
    public function title(): string
    {
        return "Working hours";
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

}
