<?php

namespace App\GraphQL\Mutations;

use App\Models\ClientEmployee;
use App\Models\TimesheetShiftHistory;
use App\Models\TimesheetShiftHistoryVersion;
use Illuminate\Support\Carbon;
use App\Models\TimesheetShift;
use App\Exceptions\HumanErrorException;
use Illuminate\Support\Str;

class SetTimesheetShiftMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        /**
         * TODO: delete this unused function
         * */
        $inputDateFrom = $args["dateFrom"];
        $dateFrom = Carbon::parse($inputDateFrom);
        $inputDateTo = $args["dateTo"];
        $dateTo = Carbon::parse($inputDateTo);
        $input = $args["input"];
        $listDate = !empty($args["listDate"]) ? json_decode($args["listDate"], true) : '';

        $i = 0;
        $history_data = [];
        $version_group_id = Str::uuid();
        $updated_by = auth()->user()->clientEmployee->id ?? "";
        foreach ($input as $item) {
            $ce = ClientEmployee::authUserAccessible()->find($item['client_employee_id']);
            // TODO touch timesheet
            if ($ce) {
                switch ($args["type"]) {
                    case 'default':
                        if(empty($item['timesheet_shift_id'])){
                            $now = $dateFrom->clone();
                            while ($now->lte($dateTo)) {
                                $timesheet = $ce->touchTimesheet($now->toDateString());
                                $timesheet->shift_enabled = true;
                                $timesheet->shift_is_off_day = true;
                                $timesheet->shift_is_holiday = false;
                                $timesheet->timesheet_shift_id = null;
                                $timesheet->save();
                                $now->addDay();
                                $history_data[] = [
                                    'id' => Str::uuid(),
                                    'timesheet_id' => $timesheet->id,
                                    'timesheet_shift_id' => null,
                                    'type' => TimesheetShiftHistory::IS_OFF_DAY,
                                    'updated_by' => $updated_by,
                                    'version_group_id' => $version_group_id,
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now()
                                ];
                            }
                        } elseif ($item['timesheet_shift_id'] == "is_holiday") {
                            $now = $dateFrom->clone();
                            while ($now->lte($dateTo)) {
                                $timesheet = $ce->touchTimesheet($now->toDateString());
                                $timesheet->shift_enabled = true;
                                $timesheet->shift_is_off_day = false;
                                $timesheet->shift_is_holiday = true;
                                $timesheet->timesheet_shift_id = null;
                                $timesheet->save();
                                $now->addDay();
                                $history_data[] = [
                                    'id' => Str::uuid(),
                                    'timesheet_id' => $timesheet->id,
                                    'timesheet_shift_id' => null,
                                    'type' => TimesheetShiftHistory::IS_HOLIDAY,
                                    'updated_by' => $updated_by,
                                    'version_group_id' => $version_group_id,
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now()
                                ];
                            }
                        } else {
                            $timesheetShift = TimesheetShift::where("id", $item['timesheet_shift_id'])->first();
                            if (!empty($timesheetShift)) {
                                $now = $dateFrom->clone();
                                while ($now->lte($dateTo)) {
                                    $timesheet = $ce->touchTimesheet($now->toDateString());
                                    $timesheet->shift_enabled = true;
                                    $timesheet->shift_shift = $timesheetShift->shift;
                                    $timesheet->timesheet_shift_id = $item['timesheet_shift_id'];
                                    $timesheet->shift_check_in = substr($timesheetShift->check_in,0, 5);
                                    $timesheet->shift_check_out = substr($timesheetShift->check_out,0, 5);
                                    $timesheet->shift_break_start = substr($timesheetShift->break_start,0, 5);
                                    $timesheet->shift_break_end = substr($timesheetShift->break_end,0, 5);
                                    $timesheet->shift_next_day = $timesheetShift->next_day;
                                    $timesheet->shift_next_day_break = $timesheetShift->next_day_break;
                                    $timesheet->shift_is_off_day = false;
                                    $timesheet->shift_is_holiday = false;
                                    $timesheet->save();
                                    $now->addDay();
                                    $history_data[] = [
                                        'id' => Str::uuid(),
                                        'timesheet_id' => $timesheet->id,
                                        'timesheet_shift_id' => $item['timesheet_shift_id'],
                                        'type' => TimesheetShiftHistory::WORKING,
                                        'updated_by' => $updated_by,
                                        'version_group_id' => $version_group_id,
                                        'created_at' => Carbon::now(),
                                        'updated_at' => Carbon::now()
                                    ];
                                }
                            }
                        }
                        break;

                    case 'advanced':
                        $now = $dateFrom->clone();
                        while ($now->lte($dateTo)) {
                            $getDate = $now->toDateString();
                            $idTimesheetShift = $listDate[$i][$getDate];
                            if(empty($idTimesheetShift)){
                                $timesheet = $ce->touchTimesheet($getDate);
                                $timesheet->shift_enabled = true;
                                $timesheet->shift_is_off_day = true;
                                $timesheet->shift_is_holiday = false;
                                $timesheet->timesheet_shift_id = null;
                                $timesheet->save();
                                $history_data[] = [
                                    'id' => Str::uuid(),
                                    'timesheet_id' => $timesheet->id,
                                    'timesheet_shift_id' => null,
                                    'type' => TimesheetShiftHistory::IS_OFF_DAY,
                                    'updated_by' => $updated_by,
                                    'version_group_id' => $version_group_id,
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now()
                                ];
                            } elseif ($idTimesheetShift == "is_holiday") {
                                $timesheet = $ce->touchTimesheet($getDate);
                                $timesheet->shift_enabled = true;
                                $timesheet->shift_is_off_day = false;
                                $timesheet->shift_is_holiday = true;
                                $timesheet->timesheet_shift_id = null;
                                $timesheet->save();
                                $history_data[] = [
                                    'id' => Str::uuid(),
                                    'timesheet_id' => $timesheet->id,
                                    'timesheet_shift_id' => null,
                                    'type' => TimesheetShiftHistory::IS_HOLIDAY,
                                    'updated_by' => $updated_by,
                                    'version_group_id' => $version_group_id,
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now()
                                ];
                            } else {
                                $timesheetShift = TimesheetShift::where("id", $idTimesheetShift)->first();
                                if (!empty($timesheetShift)) {
                                    $timesheet = $ce->touchTimesheet($getDate);
                                    $timesheet->shift_enabled = true;
                                    $timesheet->shift_shift = $timesheetShift->shift;
                                    $timesheet->timesheet_shift_id = $idTimesheetShift;
                                    $timesheet->shift_check_in = substr($timesheetShift->check_in,0, 5);
                                    $timesheet->shift_check_out = substr($timesheetShift->check_out,0, 5);
                                    $timesheet->shift_break_start = substr($timesheetShift->break_start,0, 5);
                                    $timesheet->shift_break_end = substr($timesheetShift->break_end,0, 5);
                                    $timesheet->shift_next_day = $timesheetShift->next_day;
                                    $timesheet->shift_next_day_break = $timesheetShift->next_day_break;
                                    $timesheet->shift_is_off_day = false;
                                    $timesheet->shift_is_holiday = false;
                                    $timesheet->save();
                                    $history_data[] = [
                                        'id' => Str::uuid(),
                                        'timesheet_id' => $timesheet->id,
                                        'timesheet_shift_id' => $idTimesheetShift,
                                        'type' => TimesheetShiftHistory::WORKING,
                                        'updated_by' => $updated_by,
                                        'version_group_id' => $version_group_id,
                                        'created_at' => Carbon::now(),
                                        'updated_at' => Carbon::now()
                                    ];
                                }
                            }
                            $now->addDay();
                        }
                        break;
                }
            }
            $i++;
        }
        if ($history_data) {
            TimesheetShiftHistoryVersion::insert([
                'id' => $version_group_id,
                'client_id' => auth()->user()->clientEmployee->client_id ?? "",
                'group_name' => $args['group_name'] ?? "",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            TimesheetShiftHistory::insert($history_data);
        }
    }


}
