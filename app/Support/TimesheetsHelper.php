<?php

namespace App\Support;

use App\Exceptions\HumanErrorException;
use App\Models\Timesheet;
use App\Models\WorkScheduleGroup;
use App\Models\WorkScheduleGroupTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TimesheetsHelper
{
    public static function recalculateTimesheet($data)
    {
        $clientId = $data['client_id'] ?? "";
        $employee_ids = [];
        $fromDate = $toDate = '';
        $listDate = [];

        //employee_ids is array
        if (!empty($data['client_employee_ids'])) {
            $employee_ids = $data['client_employee_ids'];
        }

        if (!empty($data['work_schedule_group_id'])) {
            $wsg = WorkScheduleGroup::find($data['work_schedule_group_id']);
            if (!empty($wsg)) {
                $fromDate = Carbon::parse($wsg->timesheet_from);
                $toDate = Carbon::parse($wsg->timesheet_to);
            }
        } elseif (!empty($data['from_date'])) {
            $fromDate = Carbon::parse($data['from_date']);
            $toDate = !empty($data['to_date']) ? Carbon::parse($data['to_date']) : $fromDate->clone();
        }
        if(!empty($data['list_date'])) {
            $listDate = $data['list_date'];
        }

        $query = Timesheet::query();
        if ($fromDate && $toDate) {
            $query->whereBetween("log_date", [$fromDate->toDateString(), $toDate->toDateString()]);
        }
        if ($employee_ids) {
            $query->whereIn('client_employee_id', $employee_ids);
        }
        if ($clientId) {
            $query->whereHas('clientEmployee', function (Builder $query) use($clientId) {
                $query->where('client_id', $clientId);
            });
        }

        if(!empty($listDate)) {
            $query->whereIn('log_date', $listDate);
        }

        // For this case: cursor will have better performance than chunkById
        foreach ($query->cursor() as $item) {
            /** @var Timesheet $item */
            $item->flexible = 0;
            if (empty($listDate)) {
                $item->is_update_work_schedule = true;
            }
            $item->recalculate();
            $item->saveQuietly();
        }

        return true;
    }

    /**
     *
     * @param  string  $check_in_value
     * @param  string  $wsgt_id
     * @return array
     */
    public static function getFlexibleCheckoutFromCheckin($check_in_value, $wsgt_id)
    {
        $wsgTemplate = WorkScheduleGroupTemplate::find($wsgt_id);
        if (!$wsgTemplate) {
            throw new HumanErrorException(__("khong_the_tim_thay_workschedulegroup_cua_template_id") . ' ' . $wsgt_id);
        }

        if ($wsgTemplate->core_time_in && $check_in_value > $wsgTemplate->core_time_in) {
            throw new HumanErrorException(__("cannot_make_a_request_is_over_core_time"));
        }

        if ($wsgTemplate->start_break <= $check_in_value && $check_in_value <= $wsgTemplate->end_break) {
            throw new HumanErrorException(__("cannot_make_a_request_into_the_break_period"));
        }

        if ($check_in_value >= $wsgTemplate->end_break) {
            $minusBreakTime =  strtotime($wsgTemplate->end_break) - strtotime($wsgTemplate->start_break);
        } else {
            $minusBreakTime = 0;
        }
        $return_check_in = $check_in_value;
        $wsCheckIn = strtotime($wsgTemplate->check_in);
        $wsCheckOut = strtotime($wsgTemplate->check_out);
        $flexibleCheckIn = strtotime($return_check_in);
        $flexibleCheckOut = $wsCheckOut + ($flexibleCheckIn - $wsCheckIn) - $minusBreakTime;
        $midnight = strtotime("00:00");
        //don't support next_day
        if ($flexibleCheckOut - $midnight >= 86400) {
            throw new HumanErrorException(__("cannot_make_a_request_with_checkout_time_past_the_next_day"));
        }

        $return_check_out = date('H:i', $flexibleCheckOut);
        return [$return_check_in, $return_check_out];
    }

    /**
     * Create timesheet per date of employee
     */
    public static function createTimeSheetPerDate($clientEmployeeId, $logDate) {

        $timesheet = new Timesheet();
        $timesheet->client_employee_id = $clientEmployeeId;
        $timesheet->log_date = $logDate;
        $timesheet->activity = "activity";
        $timesheet->work_place = "work_place";
        $timesheet->working_hours = 0;
        $timesheet->overtime_hours = 0;
        $timesheet->check_in = "";
        $timesheet->check_out = "";
        $timesheet->leave_type = "early_leave";
        $timesheet->attentdant_status = "doing";
        $timesheet->note = "";
        $timesheet->reason = "";
        $timesheet->next_day = 0;
        $timesheet->save();

        return $timesheet;

    }

    /**
     * Create timesheet per date of employee
     */
    public static function touchTimesheet($clientEmployeeId, $logDate) {
        $timesheet = Timesheet::where("log_date", $logDate)->where('client_employee_id', $clientEmployeeId)->first();
        if (!$timesheet) {
            $timesheet = new Timesheet();
            $timesheet->id = Str::uuid();
            $timesheet->client_employee_id = $clientEmployeeId;
            $timesheet->log_date = $logDate;
            $timesheet->activity = "activity";
            $timesheet->work_place = "work_place";
            $timesheet->working_hours = 0;
            $timesheet->overtime_hours = 0;
            $timesheet->check_in = "";
            $timesheet->check_out = "";
            $timesheet->leave_type = "early_leave";
            $timesheet->attentdant_status = "doing";
            $timesheet->note = "";
            $timesheet->reason = "";
            $timesheet->next_day = 0;
            $timesheet->created_at = Carbon::now()->toDateTimeString();
            $timesheet->updated_at = Carbon::now()->toDateTimeString();
        }

        return $timesheet;

    }

}
