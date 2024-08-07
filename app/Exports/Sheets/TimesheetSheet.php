<?php

namespace App\Exports\Sheets;

use App\Models\ClientWorkflowSetting;
use App\Models\ClientYearHoliday;
use App\Models\TimeChecking;
use App\Models\ViewCombinedTimesheet;
use App\Models\WorkTimeRegisterPeriod;
use App\Support\PeriodHelper;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use App\Models\ClientEmployee;
use PhpOffice\PhpSpreadsheet\Style\Style;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class TimesheetSheet implements WithTitle, FromView, WithStyles, WithEvents, WithDefaultStyles
{
    private $fromDate;
    private $toDate;
    private $employee;
    private $employeeId;
    private $wt_category_list;
    private $template;
    private $primary_alphabet;
    private $signature;
    private $default_signature_alphabet;

    public function __construct($employeeId, $fromDate, $toDate, $wt_category_list, $template = 1)
    {
        $this->employeeId = $employeeId;
        $employee = ClientEmployee::where("id", $employeeId)->first();
        if (!$employee) {
            logger()->warning(__METHOD__ . " Unknown client employee found. ID=$employeeId");
            $employee = new ClientEmployee(); // dummy client employee
        }
        $this->employee = $employee;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->wt_category_list = $wt_category_list;
        $this->template = $template;

        switch ($this->template) {
            case 2:
                $this->primary_alphabet = "L";
                $this->default_signature_alphabet = "I";
                $this->signature = false;
                break;
            default:
                $this->primary_alphabet = "J";
                $this->default_signature_alphabet = "G";
                $this->signature = true;
                break;
        }
    }

    public function view(): View
    {
        logger("@@@ TimesheetSheet for " . $this->employee->code . "");
        $clientSetting = $this->employee->client->clientWorkflowSetting;

        $timesheets = ViewCombinedTimesheet::query()
            ->leftJoin('timesheet_shift_mapping', 'view_combined_timesheets.timesheet_id', '=', 'timesheet_shift_mapping.timesheet_id')
            ->leftJoin('timesheet_shifts', 'timesheet_shift_mapping.timesheet_shift_id', '=', 'timesheet_shifts.id')
            ->where('client_employee_id', $this->employee->id)
            ->where('schedule_date', '>=', $this->fromDate)
            ->where('schedule_date', '<=', $this->toDate)
            ->orderBy('schedule_date')
            ->orderBy('check_in_work_shift_for_multiple_shift')
            ->select([
                'view_combined_timesheets.*',
                'timesheet_shift_mapping.working_hours as working_hour_for_multiple',
                'timesheet_shift_mapping.check_in as check_in_for_multiple_shift',
                'timesheet_shift_mapping.check_out as check_out_for_multiple_shift',
                'timesheet_shift_mapping.deleted_at as deleted_at_for_multiple_shift',
                'timesheet_shift_mapping.shift as shift_for_multiple_shift',
                'timesheet_shifts.shift_code as shift_code_for_multiple_shift',
                'timesheet_shifts.next_day as next_day_for_multiple_shift',
                'timesheet_shifts.check_in as check_in_work_shift_for_multiple_shift',
                'timesheet_shifts.check_out as check_out_work_shift_for_multiple_shift',
                'timesheet_shifts.break_start as break_start_shift_for_multiple_shift',
                'timesheet_shifts.break_end as break_end_shift_for_multiple_shift',
                'timesheet_shifts.next_day_break as next_day_break_shift_for_multiple_shift',
                'timesheet_shifts.next_day_break_start as next_day_break_start_shift_for_multiple_shift'
            ])
            ->get();

        $periods = WorkTimeRegisterPeriod::query()
            ->where('date_time_register', '>=', $this->fromDate)
            ->where('date_time_register', '<=', $this->toDate)
            ->whereHas('worktimeRegister', function ($query) {
                $query
                    ->where('client_employee_id', $this->employee->id)
                    ->whereIn('status', ['approved'])
                    ->where('auto_created', 0);
            })
            ->with('worktimeRegister')
            ->get()
            ->groupBy('date_time_register');

        // Only apply leave request,bussiness trip for coming late and leave early
        $workTimeRegisterPeriodWithLeave = WorkTimeRegisterPeriod::whereHas('worktimeRegister', function ($query) {
            $query->where("client_employee_id", $this->employee->id)
                ->whereIn('type', ['leave_request', 'congtac_request'])
                ->where("status", "approved");
        })
            ->whereBetween("date_time_register", [
                $this->fromDate,
                $this->toDate,
            ])
            ->orderBy('start_time')
            ->get()->groupBy('date_time_register');

        $listHoliday = ClientYearHoliday::where('client_id', $this->employee->client_id)->get()->keyBy('date');

        $listCheckInByDate = TimeChecking::whereHas('timesheets', function ($query) {
            $query->whereBetween("log_date", [
                $this->fromDate,
                $this->toDate,
            ])->where('client_employee_id', $this->employee->id);
        })
        ->orderBy('datetime')->get()->groupBy('timesheet_id')->toArray();
        foreach ($timesheets as $key => $timesheet) {
            // Add location check in
            $listCheckIn = [];
            if(isset($listCheckInByDate[$timesheet->timesheet_id])) {
                /** @var TimeChecking[] $items */
                $items = $listCheckInByDate[$timesheet->timesheet_id];
                foreach ($items as $item) {
                    $listCheckIn[] = [
                        'location_checkin' => $item['location_checkin'] ?? $item['user_location_input'],
                        'longitude' => $item['longitude'],
                        'latitude' => $item['latitude']
                    ];
                }
                $timesheet['location'] = $listCheckIn;
            }

            if ($listHoliday->has($timesheet->schedule_date)) {
                $timesheet->is_holiday = true;
            }
            $timesheet['is_check_in_late'] = false;
            $timesheet['is_check_out_leave_early'] = false;
            if (empty($timesheet->deleted_at_for_multiple_shift) && !empty($timesheet->shift_code_for_multiple_shift)) {
                $timesheet['work_schedule_hour_for_multiple_shift'] = (PeriodHelper::countHours($this->getWorkHoursFromShift($timesheet)) - PeriodHelper::countHours($this->getRestShiftPeriod($timesheet)));
                // Check in
                if (!empty($timesheet->check_in_for_multiple_shift) && $timesheet->check_in_for_multiple_shift != '00:00' && !empty($timesheet->check_in_work_shift_for_multiple_shift) && $timesheet->check_in_work_shift_for_multiple_shift != '00:00') {
                    $checkInWork = Carbon::parse($timesheet->log_date . ' ' . $timesheet->check_in_work_shift_for_multiple_shift);
                    $checkIn = Carbon::parse($timesheet->check_in_for_multiple_shift);
                    if ($checkIn->isAfter($checkInWork)) {
                        $timesheet['is_check_in_late'] = true;
                    }
                }

                if (!empty($timesheet->check_out_for_multiple_shift) && $timesheet->check_out_for_multiple_shift != '00:00' && !empty($timesheet->check_out_work_shift_for_multiple_shift) && $timesheet->check_out_work_shift_for_multiple_shift != '00:00') {
                    $checkOutWork = Carbon::parse($timesheet->log_date . ' ' . $timesheet->check_out_work_shift_for_multiple_shift);
                    if ($timesheet->next_day) {
                        $checkOutWork->addDay();
                    }

                    // Timesheet
                    $checkOut = Carbon::parse($timesheet->check_out_for_multiple_shift);
                    if ($checkOut->isBefore($checkOutWork)) {
                        $timesheet['is_check_out_leave_early'] = true;
                    }
                }
            } else {
                // if this employee have over 1 shift, remove one.
                if (!empty($timesheet->deleted_at_for_multiple_shift)) {
                    if ( (!empty($timesheets[$key - 1]) && $timesheets[$key - 1]['timesheet_id'] == $timesheet->timesheet_id)
                        || (!empty($timesheets[$key + 1]) && $timesheets[$key + 1]['timesheet_id'] == $timesheet->timesheet_id)
                    ) {
                        $timesheets->forget($key);
                    } else {
                        $timesheet['working_hour_for_multiple'] = null;
                        $timesheet['check_in_for_multiple_shift'] = null;
                        $timesheet['check_out_for_multiple_shift'] = null;
                        $timesheet['shift_code_for_multiple_shift'] = null;
                        $timesheet['next_day_for_multiple_shift'] = null;
                        $timesheet['check_in_work_shift_for_multiple_shift'] = null;
                        $timesheet['check_out_work_shift_for_multiple_shift'] = null;
                        $timesheet['break_start_shift_for_multiple_shift'] = null;
                        $timesheet['break_end_shift_for_multiple_shift'] = null;
                        $timesheet['next_day_break_shift_for_multiple_shift'] = null;
                        $timesheet['next_day_break_start_shift_for_multiple_shift'] = null;
                    }
                }

                $workTimeRegisterPeriod = null;
                if ($workTimeRegisterPeriodWithLeave->has($timesheet->schedule_date)) {
                    $workTimeRegisterPeriod = $workTimeRegisterPeriodWithLeave->get($timesheet->schedule_date);
                }
                [$checkInLate, $checkOutLate] = $timesheet->getCheckinLateAndCheckOutEarlyLeave($workTimeRegisterPeriod);
                $timesheet['is_check_in_late'] = $checkInLate;
                $timesheet['is_check_out_leave_early'] = $checkOutLate;
            }
            if ($periods->has($timesheet->schedule_date)) {
                $timesheet->periods = $periods->get($timesheet->schedule_date);
            } else {
                $timesheet->periods = collect();
            }
        }

        $data = array(
            'timesheets' => $timesheets,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'client' => $this->employee->client,
            'employee' => $this->employee,
            'wt_category_list' => $this->wt_category_list,
            'clientWorkflowSetting' => $clientSetting
        );

        return view('exports.template-timesheet.personal.template-' . $this->template)->with($data);
    }

    public function getWorkHoursFromShift($timesheet)
    {
        $checkIn = Carbon::parse($timesheet->log_date . ' ' . $timesheet->check_in_work_shift_for_multiple_shift);
        $checkOut = Carbon::parse($timesheet->log_date . ' ' . $timesheet->check_out_work_shift_for_multiple_shift);
        if ($timesheet->next_day_for_multiple_shift) {
            $checkOut->addDay();
        }

        return Period::make($checkIn, $checkOut, Precision::SECOND, Boundaries::EXCLUDE_NONE);
    }

    public function getRestShiftPeriod($timesheet)
    {
        if (!$timesheet->break_start_shift_for_multiple_shift || !$timesheet->break_end_shift_for_multiple_shift) {
            return Period::make(
                $timesheet->log_date . ' 00:00:00',
                $timesheet->log_date . ' 00:00:01',
                Precision::SECOND,
                Boundaries::EXCLUDE_NONE
            );
        }
        $breakIn = Carbon::parse($timesheet->log_date . ' ' . $timesheet->break_start_shift_for_multiple_shift);
        if ($timesheet->next_day_break_start_shift_for_multiple_shift) {
            $breakIn = $breakIn->addDay();
        }
        $breakOut = Carbon::parse($timesheet->log_date . ' ' . $timesheet->break_end_shift_for_multiple_shift);
        if ($timesheet->next_day_break_shift_for_multiple_shift) {
            $breakOut = $breakOut->addDay();
        }

        return Period::make($breakIn, $breakOut, Precision::SECOND, Boundaries::EXCLUDE_NONE);
    }

    public function title(): string
    {
        return $this->employee->full_name ?? $this->employeeId;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setShowGridlines(false);

        $outlineBorder = array(
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        );

        $sheet->getStyle("A1:" . $this->primary_alphabet . "1")->applyFromArray($outlineBorder)->getFont()->setBold(true)->setSize(20);
        $sheet->getStyle("A3:" . $this->primary_alphabet . "4")->applyFromArray($outlineBorder)->getFont()->setBold(true);
        $sheet->getStyle("A6:" . $this->primary_alphabet . "7")->applyFromArray($outlineBorder)->getFont()->setBold(true);
        $sheet->getStyle("A9:" . $this->primary_alphabet . "10")->applyFromArray($outlineBorder)->getFont()->setBold(true);
        $sheet->getStyle("A12:" . $this->primary_alphabet . "13")->applyFromArray($outlineBorder)->getFont()->setBold(true);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getStyle('E4')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
    }

    public function defaultStyles(Style $defaultStyle)
    {
        return [
            "A9:" . $this->primary_alphabet . "10" => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {

                $alphabet = $event->sheet->getHighestDataColumn();
                $totalRow = $event->sheet->getHighestDataRow();

                $event->sheet->getDelegate()->getPageSetup()->setPrintArea('A1:' . $alphabet . $totalRow);
                $event->sheet->getDelegate()->getPageSetup()->setFitToWidth(1);

                if ($this->signature) {
                    $cellRangeTable = 'A14:' . $alphabet . ($totalRow - 4);
                    $cellRangeSignature = $this->default_signature_alphabet . ($totalRow - 1) . ':' . $alphabet . $totalRow;
                    $event->sheet->getStyle($cellRangeSignature)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ],
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ]
                    ])->getAlignment()->setWrapText(true);
                } else {
                    $cellRangeTable = 'A14:' . $alphabet . $totalRow;
                }

                $event->sheet->getStyle($cellRangeTable)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR,
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ]
                ])->getAlignment()->setWrapText(true);



                $event->sheet->getStyle('A1:A7')->getAlignment()->setHorizontal('center');
                $event->sheet->getStyle("A9:" . $this->primary_alphabet . "13")->getAlignment()->setVertical('center')->setHorizontal('center')->setWrapText(true);
                $event->sheet->getStyle('E4')->getAlignment()->setHorizontal('left')->setWrapText(true);
            },
        ];
    }
}
