<?php

namespace App\Exports;

use App\Exceptions\CustomException;
use App\Exports\Sheets\TimesheetShiftVersionSheet;
use App\Models\ClientEmployee;
use App\Models\ClientWorkflowSetting;
use App\Models\TimesheetShift;
use App\Models\TimesheetShiftHistory;
use App\Models\TimesheetShiftHistoryVersion;
use Illuminate\Support\Arr;

use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimesheetShiftEmployeeVersionExport implements WithMultipleSheets
{
    protected array $versionParam;

    public function __construct(array $versionParam)
    {
        $this->versionParam = $versionParam;
    }

    /**
     * @throws CustomException
     */
    public function sheets(): array
    {
        $sheets = [];

        // Get data history version
        $historyVersions = TimesheetShiftHistoryVersion::with('timesheetShiftHistory.timesheet')
            ->whereIn('id', Arr::pluck($this->versionParam, 'version_id'))->orderBy('created_at')->get();
        // Check empty
        if ($historyVersions->isEmpty()) {
            throw new CustomException(
                'Not found these versions',
                'ValidationException'
            );
        }

        $clientSetting = ClientWorkflowSetting::where('client_id', Auth::user()->client_id)->select('enable_multiple_shift')->first();
        // Check exit setting
        if (!$clientSetting) {
            throw new CustomException(
                'You are not setting',
                'HumanErrorException'
            );
        }

        // Translate
        $auth = auth()->user();
        $lang = !empty($auth->prefered_language) ? $auth->prefered_language : app()->getLocale();
        app()->setlocale($lang);

        // Get unique list id shift
        $timesheetShiftIds = array_unique(Arr::collapse($historyVersions->pluck('timesheetShiftHistory.*.timesheet_shift_id')));
        // Get information shift
        $appliedShifts = TimesheetShift::whereIn("id", $timesheetShiftIds)->get()->keyBy('id');

        // Loop history version
        $historyVersions->each(function ($version) use ($appliedShifts, &$sheets, $clientSetting) {
            $employees = ClientEmployee::whereIn("id", $version->timesheetShiftHistory->pluck('timesheet.client_employee_id')->unique()->all());
            // Sort by filter
            if ($version->sort_by == "sort_by_position") {
                $employees->withAggregate('clientPosition', 'code')
                    ->orderBy('client_position_code');
            } elseif ($version->sort_by == "sort_by_department") {
                $employees->withAggregate('clientDepartment', 'code')
                    ->orderBy('client_department_code');
            } else {
                $employees->orderBy('full_name');
            }

            // Get employee
            $employees = $employees->get();

            // Param common
            $fromDate = $version->timesheetShiftHistory->min('timesheet.log_date');
            $toDate = $version->timesheetShiftHistory->max('timesheet.log_date');
            $historyGroupByEmployee = $version->timesheetShiftHistory->groupBy('timesheet.client_employee_id');
            $params = [];

            // Check case with multiple shift
            if ($clientSetting->enable_multiple_shift) {
                $paramTemp = [];
                $employees = $employees->groupBy('id');
                $listShiftByVersion = [];
                foreach ($historyGroupByEmployee as $employee_id => $histories) {
                    foreach ($histories as $history) {
                        if ($appliedShifts->has($history->timesheet_shift_id)) {
                            if(!array_key_exists($history->timesheet_shift_id, $listShiftByVersion)) $listShiftByVersion[$history->timesheet_shift_id] =  $history->timesheet_shift_id;
                            $paramTemp[$employee_id]['full_name'] = $employees[$employee_id][0]->full_name;
                            $paramTemp[$employee_id]['code'] = $employees[$employee_id][0]->code;
                            $paramTemp[$employee_id]['shifts'][$history->timesheet->log_date][] = $appliedShifts[$history->timesheet_shift_id]->shift_code;

                            $countMaxShiftDay =  count($paramTemp[$employee_id]['shifts'][$history->timesheet->log_date]);
                            if (isset($paramTemp[$employee_id]['max_shift_day'])) {

                                if ($paramTemp[$employee_id]['max_shift_day'] <= $countMaxShiftDay) {
                                    $paramTemp[$employee_id]['max_shift_day'] = $countMaxShiftDay;
                                }
                            } else {
                                $paramTemp[$employee_id]['max_shift_day'] = $countMaxShiftDay;
                            }
                        }
                    }
                }

                // Handle add shift if have any other shift in day by creating new list
                foreach ($paramTemp as $key => $value) {
                    if (!empty($value['shifts'])) {
                        for ($i = 0; $i < $value['max_shift_day']; $i++) {
                            $tempShift = $value['shifts'];
                            $tempShiftDates = [];
                            foreach ($tempShift as $keyDate => $shifts) {
                                $tempShiftDates[$keyDate] = $shifts[$i] ?? "-:-";
                            }
                            $itemTemp = [
                                'full_name' => $value['full_name'],
                                'code' => $value['code'],
                                'shifts' => $tempShiftDates
                            ];
                            $params[$key . "_$i"] = $itemTemp;
                        }
                    } else {
                        $params[$key] = $value;
                    }
                }
            } else {
                $employees->each(function ($item, &$key) use (&$params) {
                    $params[$item->id]['full_name'] = $item->full_name;
                    $params[$item->id]['code'] = $item->code;
                });

                $listShiftByVersion = [];
                foreach ($historyGroupByEmployee as $employee_id => $histories) {
                    foreach ($histories as $history) {
                        switch ($history->type) {
                            case TimesheetShiftHistory::IS_OFF_DAY:
                                $params[$employee_id]['shifts'][$history->timesheet->log_date] = __('model.timesheets.work_status.weekly_leave');
                                break;
                            case TimesheetShiftHistory::IS_HOLIDAY:
                                $params[$employee_id]['shifts'][$history->timesheet->log_date] = __('holidays');
                                break;
                            case TimesheetShiftHistory::WORKING:
                                if ($appliedShifts->has($history->timesheet_shift_id)) {
                                    if(!array_key_exists($history->timesheet_shift_id, $listShiftByVersion)) $listShiftByVersion[$history->timesheet_shift_id] =  $history->timesheet_shift_id;
                                    $params[$employee_id]['shifts'][$history->timesheet->log_date] = $appliedShifts[$history->timesheet_shift_id]->shift_code;
                                } else {
                                    $params[$employee_id]['shifts'][$history->timesheet->log_date] = "";
                                }
                                break;
                            default:
                                break;
                        }
                    }
                }
            }

            // Override
            $appliedShifts = TimesheetShift::whereIn("id", $listShiftByVersion)->get()->keyBy('id');

            $name = Arr::first($this->versionParam, function ($value, $key) use ($version) {
                return $value['version_id'] == $version->id;
            });
            $name = ($name ? __('version') . " " . $name['name'] : "") . " (" . $version->group_name . ")";
            $sheets[] = new TimesheetShiftVersionSheet($params, $appliedShifts, $fromDate, $toDate, $name);
        });

        return $sheets;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('C')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
    }
}
