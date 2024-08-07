<?php

namespace App\Jobs;

use App\Exceptions\CustomException;
use App\Models\ClientEmployee;
use App\Support\ClientHelper;
use App\Support\ErrorCode;
use App\Support\TimesheetsHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Timesheet;
use App\Models\TimesheetShift;
use App\Models\TimesheetShiftHistory;
use App\Models\TimesheetShiftMapping;
use App\Models\TimesheetShiftHistoryVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class SetTimesheetShiftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    protected $user;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $user)
    {
        $this->data = $data;
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $args = $this->data['dataUpdate'];
            $type = $this->data['type'];

            switch ($type) {
                case 'single':
                    $timesheet_shift_ids = array_unique(array_column($args['input'], 'timesheet_shift_id'));
                    $timesheetShiftList = TimesheetShift::whereIn("id", $timesheet_shift_ids)->get()->keyBy('id');

                    if (!empty($this->data['dateRange']) && !empty($this->data['dateRange'][0]) && !empty($this->data['dateRange'][1])) {
                        $timesheetList = Timesheet::whereBetween('log_date', [$this->data['dateRange'][0], $this->data['dateRange'][1]])
                            ->whereHas('clientEmployee', function($query) {
                                $query->where('client_id', $this->user->client_id);
                            })->get()->keyBy('id');
                    } else {
                        $ids = array_unique(array_column($args['input'], 'id'));
                        $timesheetList = Timesheet::whereIn("id", $ids)->get()->keyBy('id');
                    }

                    $updated_by = $this->user->clientEmployee->id ?? "";
                    $version_group_id = Str::uuid();
                    $history_data = [];
                    $recalculatingTimeSheetIds = [];
                    $changingData = collect();

                    //Prepare data
                    foreach ($args['input'] as $p) {
                        if (!empty($p['id'])) {
                            $ts = $timesheetList[$p['id']] ?? null;
                        } else {
                            $ts = null;
                        }

                        if ($ts === null) {
                            $ts = TimesheetsHelper::touchTimesheet($p['client_employee_id'], $p['log_date']);
                        }
                        $timesheet_shift_id_history = null;
                        $type_history = null;
                        switch ($p['timesheet_shift_id']) {
                            case 'is_holiday':
                                $ts->shift_enabled = true;
                                $ts->shift_is_off_day = false;
                                $ts->shift_is_holiday = true;
                                $ts->shift_check_in = null;
                                $ts->shift_check_out = null;
                                $ts->shift_break_start = null;
                                $ts->shift_break_end = null;
                                $ts->shift_next_day = 0;
                                $ts->shift_next_day_break = 0;
                                $ts->timesheet_shift_id = null;
                                $type_history = TimesheetShiftHistory::IS_HOLIDAY;
                                break;
                            case 'is_off_day':
                                $ts->shift_enabled = true;
                                $ts->shift_is_off_day = true;
                                $ts->shift_is_holiday = false;
                                $ts->shift_check_in = null;
                                $ts->shift_check_out = null;
                                $ts->shift_break_start = null;
                                $ts->shift_break_end = null;
                                $ts->shift_next_day = 0;
                                $ts->shift_next_day_break = 0;
                                $ts->timesheet_shift_id = null;
                                $type_history = TimesheetShiftHistory::IS_OFF_DAY;
                                break;
                            case '':
                                $ts->shift_enabled = 0;
                                $ts->timesheet_shift_id = null;
                                $ts->shift_check_in = null;
                                $ts->shift_check_out = null;
                                $ts->shift_break_start = null;
                                $ts->shift_break_end = null;
                                $ts->shift_next_day = 0;
                                $ts->shift_next_day_break = 0;
                                $ts->shift_is_off_day = false;
                                $ts->shift_is_holiday = false;
                                $type_history = TimesheetShiftHistory::IS_EMPTY_SHIFT;
                                break;
                            default:
                                $timesheetShift = $timesheetShiftList[$p['timesheet_shift_id']] ?? null;
                                if ($timesheetShift) {
                                    $ts->shift_enabled = true;
                                    $ts->shift_shift = $timesheetShift->shift;
                                    $ts->timesheet_shift_id = $p['timesheet_shift_id'];
                                    $ts->shift_check_in = substr($timesheetShift->check_in, 0, 5);
                                    $ts->shift_check_out = substr($timesheetShift->check_out, 0, 5);
                                    $ts->shift_break_start = substr($timesheetShift->break_start, 0, 5);
                                    $ts->shift_break_end = substr($timesheetShift->break_end, 0, 5);
                                    $ts->shift_next_day = $timesheetShift->next_day;
                                    $ts->shift_next_day_break = $timesheetShift->next_day_break;
                                    $ts->shift_is_off_day = false;
                                    $ts->shift_is_holiday = false;
                                    $timesheet_shift_id_history = $p['timesheet_shift_id'];
                                    $type_history = TimesheetShiftHistory::WORKING;
                                }
                                break;
                        }

                        if (!empty($p['is_assigned'])) {
                            $changingData->push($ts);
                            $recalculatingTimeSheetIds[] = $ts->id;
                        }

                        if (!empty($args['group_name'])) {
                            if (!empty($p['timesheet_shift_id']) || !empty($p['is_assigned'])) {
                                $history_data[] = [
                                    'id' => Str::uuid(),
                                    'timesheet_id' => $ts->id,
                                    'timesheet_shift_id' => $timesheet_shift_id_history,
                                    'type' => $type_history,
                                    'updated_by' => $updated_by,
                                    'version_group_id' => $version_group_id,
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ];
                            }
                        } else {
                            if (!empty($p['is_assigned'])) {
                                $history_data[] = [
                                    'id' => Str::uuid(),
                                    'timesheet_id' => $ts->id,
                                    'timesheet_shift_id' => $timesheet_shift_id_history,
                                    'type' => $type_history,
                                    'updated_by' => $updated_by,
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ];
                            }
                        }
                    }

                    DB::transaction(function() use($args, $version_group_id, $history_data, $changingData) {
                        if (!empty($args['group_name'])) {
                            TimesheetShiftHistoryVersion::insert([
                                'id' => $version_group_id,
                                'client_id' => $this->user->client_id,
                                'group_name' => $args['group_name'],
                                'sort_by' => $args['order_by'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                        if (!empty($history_data)) {
                            TimesheetShiftHistory::insert($history_data);
                        }

                        foreach ($changingData as $item) {
                            $item->saveQuietly();
                        }
                    });

                    if (!empty($recalculatingTimeSheetIds)) {
                        dispatch(new TimesheetRecalculateJob($recalculatingTimeSheetIds));
                    }

                    break;

                case 'multiple':
                    $now = now();
                    $updated_by = $this->user->clientEmployee->id ?? "";
                    $history_data = [];
                    $recalculatingTimeSheetIds = [];
                    $type = 0;
                    $version_group_id = Str::uuid();
                    $insertOrDeletedData = [];
                    $changedData = [];
                    foreach ($args['input'] as $item) {
                        try {
                            if (empty($item['timesheet_id'])) {
                                $ce = ClientEmployee::find($item['client_employee_id']);
                                $item['timesheet_id'] = $ce->touchTimesheet($item['log_date'])->id;
                            }

                            if (!empty($item['is_assigned'])) {
                                if (!empty($item['old_shift_id'])) {
                                    $tsm = TimesheetShiftMapping::where('timesheet_id', $item['timesheet_id'])
                                        ->where('timesheet_shift_id', $item['old_shift_id'])
                                        ->first();

                                    $changedData[] = [
                                        'id' => Str::uuid(),
                                        'timesheet_id' => $item['timesheet_id'],
                                        'timesheet_shift_id' => $item['timesheet_shift_id'],
                                        'updated_at' => $now,
                                        //'check_in' => optional($tsm)->check_in,
                                        //'check_out' => optional($tsm)->check_out,
                                    ];

                                    if ($tsm) {
                                        $insertOrDeletedData[] = [
                                            'id' => $tsm->id,
                                            'timesheet_id' => $tsm->timesheet_id,
                                            'timesheet_shift_id' => $tsm->timesheet_shift_id,
                                            'updated_at' => $now,
                                            'deleted_at' => $now,
                                        ];
                                    }
                                } else {
                                    $insertOrDeletedData[] = [
                                        'id' => Str::uuid(),
                                        'timesheet_id' => $item['timesheet_id'],
                                        'timesheet_shift_id' => $item['timesheet_shift_id'],
                                        'updated_at' => $now,
                                        'deleted_at' => !empty($item['is_deleting']) ? $now : null,
                                    ];
                                }
                                $recalculatingTimeSheetIds[$item['timesheet_id']] = $item['timesheet_id'];
                            }

                            $push = [
                                'id' => Str::uuid(),
                                'timesheet_id' => $item['timesheet_id'],
                                'timesheet_shift_id' => $item['timesheet_shift_id'],
                                'type' => $type,
                                'updated_by' => $updated_by,
                                'version_group_id' => $version_group_id,
                                'created_at' => $now,
                                'updated_at' => $now
                            ];
                            array_push($history_data, $push);

                        } catch (Exception $e) {
                            // Handle the exception (e.g., log the error, rollback transaction, etc.)
                            logger()->error(__METHOD__ . ' - Error processing input: ' . $e->getMessage());
                            throw $e;
                        }
                    }

                    if ($history_data) {
                        try {
                            if (!empty($args['group_name'])) {
                                TimesheetShiftHistoryVersion::insert([
                                    'id' => $version_group_id,
                                    'client_id' => $this->user->clientEmployee->client_id ?? "",
                                    'group_name' => $args['group_name'] ?? "",
                                    'sort_by' => $args['sort_by'] ?? null,
                                    'created_at' => $now,
                                    'updated_at' => $now
                                ]);
                            }
                            TimesheetShiftHistory::insert($history_data);
                        } catch (Exception $e) {
                            // Handle the exception (e.g., log the error, rollback transaction, etc.)
                            logger()->error(__METHOD__ . ' - Error inserting history data: ' . $e->getMessage());
                            throw $e;
                        }
                    }

                    try {
                        TimesheetShiftMapping::upsert(
                            $insertOrDeletedData,
                            ['timesheet_id', 'timesheet_shift_id'],
                            ['deleted_at']
                        );
                        TimesheetShiftMapping::upsert(
                            $changedData,
                            ['timesheet_id', 'timesheet_shift_id']
                        );
                    } catch (Exception $e) {
                        // Handle the exception (e.g., log the error, rollback transaction, etc.)
                        logger()->error(__METHOD__ . ' - Error upserting timesheet shift mapping: ' . $e->getMessage());
                        throw $e;
                    }

                    if (!empty($recalculatingTimeSheetIds)) {
                        dispatch(new TimesheetCheckingJob($recalculatingTimeSheetIds, $this->user->client_id));
                    }

                    break;
            }
        } catch (\Exception $e) {
            // Handle the top-level exception (e.g., log the error, rollback transaction, etc.)
            ClientHelper::logError($e);
            throw new CustomException(__('ERR0005.assign.error'), 'ErrorException', ErrorCode::ERR0005);
        }
    }
}
