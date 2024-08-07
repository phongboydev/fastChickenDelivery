<?php

namespace App\GraphQL\Mutations;

use App\Events\CalculateTimeSheetShiftMappingEvent;
use App\Exceptions\HumanErrorException;
use App\Jobs\TimesheetRecalculateJob;
use App\Models\Checking;
use App\Models\ClientEmployee;
use App\Models\Timesheet;
use App\Models\TimesheetShiftHistory;
use App\Models\TimesheetShiftHistoryVersion;
use App\Models\TimesheetShiftMapping;
use App\Support\Constant;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TimesheetShiftMappingMutator
{
    /**
     * Assign shifts for employees
     *
     * @param mixed $root
     * @param mixed[] $args
     *
     * @return string|boolean
     */
    public function assignShiftsForEmployees($root, array $args)
    {
        $timesheetIds = Arr::pluck($args['input'], 'timesheet_id');
        $employeeIds_1 = Timesheet::select('client_employee_id')->whereIn('id', $timesheetIds)->get()->pluck('client_employee_id')->all();
        $employeeIds_2 = Arr::pluck($args['input'], 'client_employee_id');
        $employeeIds = array_unique(array_merge($employeeIds_1, $employeeIds_2));

        $clientIDs = ClientEmployee::select('client_id')->distinct()->whereIn('id', $employeeIds)->get();

        $this->assignedValidation($clientIDs);

        $this->createOrRestoreMulti($args);
        return true;
    }

    private function assignedValidation($clientID)
    {
        $user = auth()->user();
        if (!$user->getRole() == Constant::ROLE_CLIENT_MANAGER && !$user->hasPermissionTo("manage-timesheet")) {
            throw new HumanErrorException(__("error.permission"));
        }

        if ($clientID->count() != 1) {
            throw new HumanErrorException(__("client"));
        }
        if ($clientID->first()->client_id != $user->client_id) {
            throw new HumanErrorException(__("invalid_shift_creation"));
        }
    }

    private function createOrRestoreMulti($data)
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $updated_by = auth()->user()->clientEmployee->id ?? "";
        $history_data = [];
        $type = 0;
        $version_group_id = Str::uuid();
        $recalculatingTimeSheetIds = [];
        foreach ($data['input'] as $key => &$item) {
            $item['id'] = Str::uuid();
            $item['updated_at'] = $now;
            /**
             * If database doesn't have this timesheet record, we will create new one.
             */
            if (empty($item['timesheet_id'])) {
                $ce = ClientEmployee::authUserAccessible()->find($item['client_employee_id']);
                $item['timesheet_id'] = $ce->touchTimesheet($item['log_date'])->id;
            }

            if (!empty($item['is_deleting'])) {
                $item['deleted_at'] = $now;
                unset($item['is_deleting']);
            } else {
                $item['deleted_at'] = null;
            }

            if (isset($item['old_shift_id'])) {
                $tsm = TimesheetShiftMapping::find($item['old_shift_id']);
                if ($tsm) {
                    $item['check_in'] = $tsm->check_in;
                    $item['check_out'] = $tsm->check_out;
                }
            }
            $push = [
                'id' => Str::uuid(),
                'timesheet_id' => $item['timesheet_id'],
                'timesheet_shift_id' => $item['timesheet_shift_id'],
                'type' => $type,
                'updated_by' => $updated_by,
                'version_group_id' => $version_group_id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
            array_push($history_data, $push);

            $recalculatingTimeSheetIds[$item['timesheet_id']] = $item['timesheet_id'];

            /**
             * If is_assigned is null, we only store history version,
             * don't need to update shifts.
             */
            if (empty($item['is_assigned'])) {
                unset($data['input'][$key]);
                continue;
            } else {
                //remove unused fields
                unset($item['client_employee_id']);
                unset($item['log_date']);
                unset($item['is_assigned']);
            }
        }

        if ($history_data) {
            if (!empty($data['group_name'])) {
                TimesheetShiftHistoryVersion::insert([
                    'id' => $version_group_id,
                    'client_id' => auth()->user()->clientEmployee->client_id ?? "",
                    'group_name' => $data['group_name'] ?? "",
                    'sort_by' => !empty($data['sort_by']) ? $data['sort_by'] : null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
            TimesheetShiftHistory::insert($history_data);
        }
        TimesheetShiftMapping::upsert($data['input'],
            ['timesheet_id', 'timesheet_shift_id'],
            ['deleted_at']
        );

        dispatch(new TimesheetRecalculateJob($recalculatingTimeSheetIds));
    }

    /**
     * Assign shifts for employees
     *
     * @param mixed $root
     * @param mixed[] $args
     *
     * @return
     */
    public function getTimesheetShiftsForEmployees($root, array $args)
    {
        $args['client_id'] = auth()->user()->client_id;
        return $this->queryTimeSheetShiftMapping($args);
    }

    /**
     * Export shifts for employees
     *
     * @param mixed $root
     * @param mixed[] $args
     *
     * @return
     */
    public function exportTimesheetShiftsForEmployees($root, array $args)
    {
        $args['client_id'] = auth()->user()->client_id;
        $data = $this->queryTimeSheetShiftMapping($args)->get();

    }

    private function queryTimeSheetShiftMapping(array $args)
    {
        $return = ClientEmployee::with(['timesheets' => function($query) use($args) {
            $query->with('timesheetShiftMapping');
            $query->whereDate('log_date', '>=', $args['from_date'])
                ->whereDate('log_date', '<=', $args['to_date']);
        }])
            ->where('client_id', $args['client_id'])
            ->has('timesheets.timesheetShiftMapping')
            ->whereHas('timesheets', function ($query) use ($args) {
                $query->whereDate('log_date', '>=', $args['from_date'])
                    ->whereDate('log_date', '<=', $args['to_date']);
            });

        $employeeFilter = isset($args['employee_filter']) ? $args['employee_filter'] : "";
        if ($employeeFilter) {
            $return = $return->where(function ($subQuery) use ($employeeFilter) {
                $subQuery->where('full_name', 'LIKE', '%' . $employeeFilter . '%')
                    ->orWhere('code', 'LIKE', '%' . $employeeFilter . '%');
            });
        }
        return $return;
    }

    public function setTimesheetShiftMapping($root, array $args)
    {
        $user = auth()->user();
        $timesheetShiftMapping = TimesheetShiftMapping::find($args['id']);

        $check_in = Carbon::parse($args['check_in'], Constant::TIMESHEET_TIMEZONE)->setSecond(0);
        $check_out = Carbon::parse($args['check_out'], Constant::TIMESHEET_TIMEZONE)->setSecond(0);

        $timesheetShiftMapping->check_in = $check_in->toDateTimeString();
        $timesheetShiftMapping->check_out = $check_out->toDateTimeString();
        $timesheetShiftMapping->saveQuietly();

        $timesheetShiftMapping->timesheet->storeInOut($check_in);
        $timesheetShiftMapping->timesheet->storeInOut($check_out);

        $timesheetShiftMapping->timesheet->calculateMultiTimesheet();
        $timesheetShiftMapping->timesheet->saveQuietly();

        $checkingList = [
            [
                'client_id' => $user->client_id,
                'client_employee_id' => $user->clientEmployee->id,
                'checking_time' => $check_in,
                'source' => 'SetManual'
            ],
            [
                'client_id' => $user->client_id,
                'client_employee_id' => $user->clientEmployee->id,
                'checking_time' => $check_out,
                'source' => 'SetManual'
            ]
        ];
        Checking::upsert($checkingList, ['client_employee_id', 'checking_time']);

        return TimesheetShiftMapping::find($args['id']);
    }
}
