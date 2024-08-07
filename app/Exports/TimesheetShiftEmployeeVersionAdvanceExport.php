<?php

namespace App\Exports;

use App\Exceptions\CustomException;
use App\Exports\Sheets\TimesheetMultipleShiftVersionAdvanceSheet;
use App\Exports\Sheets\TimesheetShiftVersionAdvanceSheet;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\Timesheet;
use App\Models\TimesheetShift;
use App\Models\TimesheetShiftHistory;
use App\Models\TimesheetShiftHistoryVersion;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TimesheetShiftEmployeeVersionAdvanceExport implements WithMultipleSheets
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

        // Translate
        $auth = auth()->user();
        $lang = !empty($auth->prefered_language) ? $auth->prefered_language : app()->getLocale();
        app()->setlocale($lang);

        // Get unique list id shift
        $appliedShifts = TimesheetShift::whereIn("id", $historyVersions->pluck('timesheetShiftHistory.*.timesheet_shift_id')->collapse()->unique()->all())->get()->keyBy('id');

        // Get information client and clientSetting
        $client = Client::find($auth->client_id);
        $clientSetting = $client->clientWorkflowSetting;

        // Loop history version
        $historyVersions->each(function ($version) use ($appliedShifts, &$sheets, $client, $clientSetting) {
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

            $fullNames = [];
            // Create list full name by key id
            $employees->each(function ($item, $key) use (&$fullNames) {
                $fullNames[$item->id] = $item->full_name;
            });

            // From_date and to_date
            $fromDate = $version->timesheetShiftHistory->min('timesheet.log_date');
            $toDate = $version->timesheetShiftHistory->max('timesheet.log_date');

            $mergedData = [];
            $dates = [];

            // Check case with enable multiple shift
            if ($clientSetting->enable_multiple_shift) {
                // Prepare param
                $tempData = $version->timesheetShiftHistory->groupBy('timesheet_id');
                $keyDateTimesheet = $tempData->keys();
                $dateWithTimesheet = Timesheet::whereIn('id', $keyDateTimesheet)->orderBy('log_date')->get()->keyBy('id');
                $countMaxShiftDay = [];
                $tempMergedData = [];

                // Add shift data and find max shift day
                foreach ($dateWithTimesheet as $keyId => $item) {
                    $date = $item->log_date;
                    $tempItems = $tempData->get($keyId);
                    $tempMergedData[$date][] = [
                        'max_row' => $tempItems->count(),
                        'data' => $tempItems,
                        'timesheet' => $item
                    ];

                    if (isset($countMaxShiftDay[$date])) {
                        // Get max
                        if ($countMaxShiftDay[$date] <= $tempItems->count()) {
                            $countMaxShiftDay[$date] = $tempItems->count();
                        }
                    } else {
                        $countMaxShiftDay[$date] = $tempItems->count();
                    }
                }

                // Handle add shift if have any other shift in day by creating new list
                foreach ($countMaxShiftDay as $keyDate => $valueMax) {
                    for ($i = 0; $i < $valueMax; $i++) {
                        $dataDate = $tempMergedData[$keyDate];
                        foreach ($dataDate as $data) {
                            if (isset($data['data'][$i])) {
                                $mergedData[$keyDate][$i][$data['timesheet']->client_employee_id] = $appliedShifts[$data['data'][$i]->timesheet_shift_id];
                            }
                        }
                    }
                }

                $name = Arr::first($this->versionParam, function ($value, $key) use ($version) {
                    return $value['version_id'] == $version->id;
                });
                $name = ($name ? __('version') . " " . $name['name'] : "") . " (" . $version->group_name . ")";
                $sheets[] = new TimesheetMultipleShiftVersionAdvanceSheet($mergedData, $fullNames, $client, $fromDate, $toDate, $name);

            // Not multiple shift
            } else {
                foreach ($version->timesheetShiftHistory as $history) {
                    $date = $history->timesheet->log_date;
                    $employee_id = $history->timesheet->client_employee_id;
                    $dates[$date] = $date;
                    if ($history->timesheet_shift_id) {
                        $shift = $appliedShifts[$history->timesheet_shift_id];
                        $mergedData[$date][$employee_id] = [
                            'client_employee_id' => $history->timesheet->client_employee_id,
                            'shift_is_holiday' => 0,
                            'shift_is_off_day' => 0,
                            'shift_check_in' => $shift->check_in,
                            'shift_check_out' => $shift->check_out,
                            'shift_next_day' => $shift->next_day,
                            'shift_break_start' => $shift->break_start,
                            'shift_break_end' => $shift->break_end,
                            'shift_next_day_break_start' => $shift->next_day_break_start,
                            'shift_next_day_break' => $shift->next_day_break,
                        ];
                    } else {
                        $mergedData[$date][$employee_id] = [
                            'client_employee_id' => $history->timesheet->client_employee_id,
                            'shift_is_holiday' => $history->type == TimesheetShiftHistory::IS_HOLIDAY,
                            'shift_is_off_day' => $history->type == TimesheetShiftHistory::IS_OFF_DAY,
                            'shift_check_in' => 0,
                            'shift_check_out' => 0,
                            'shift_next_day' => 0,
                            'shift_break_start' => 0,
                            'shift_break_end' => 0,
                            'shift_next_day_break_start' => 0,
                            'shift_next_day_break' => 0,
                        ];
                    }
                }
                $name = Arr::first($this->versionParam, function ($value, $key) use ($version) {
                    return $value['version_id'] == $version->id;
                });
                $name = ($name ? __('version') . " " . $name['name'] : "") . " (" . $version->group_name . ")";
                $dates = array_values($dates);
                sort($dates);
                $sheets[] = new TimesheetShiftVersionAdvanceSheet($dates, $fullNames, $mergedData, $client, $fromDate, $toDate, $name);
            }
        });

        return $sheets;
    }
}
