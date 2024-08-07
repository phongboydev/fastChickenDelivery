<?php

namespace App\Observers;

use App\Exceptions\HumanErrorException;
use App\Models\Approve;
use App\Models\ClientEmployee;
use App\Models\ClientWorkflowSetting;
use App\Models\PaidLeaveChange;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleGroup;
use App\Models\WorktimeRegister;
use App\Models\Timesheet;
use App\Support\ApproveObserverTrait;
use App\Support\Constant;
use App\Support\ErrorCode;
use App\Support\ClientHelper;
use App\Support\WorktimeRegisterHelper;
use Carbon\Carbon;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;
use App\Notifications\WorktimeRegisterNotification;
use App\Models\WorkTimeRegisterPeriod;
use App\Jobs\ProcessWorktimeRegister;
use App\Models\UnpaidLeaveChange;

class WorktimeRegisterObserver
{
    use ApproveObserverTrait;

    public function creating(WorktimeRegister $worktimeRegister)
    {

        $clientEmployee = ClientEmployee::select('*')->where('id', $worktimeRegister->client_employee_id)->first();
        $type = $worktimeRegister->type;

        if (in_array($type, ['leave_request', 'overtime_request', 'congtac_request'])) {

            $wtrDate = Carbon::parse($worktimeRegister->start_time)->format('Y-m-d');

            $workSchedule = WorkSchedule::where('client_id', $clientEmployee->client_id)
                ->where('schedule_date', $wtrDate)
                ->first();

            if (!$workSchedule) {
                throw new HumanErrorException(__("model.timesheets.calendar_month_not_set_up") . ' ' . $wtrDate);
            }
        }

        if (
            !empty($clientEmployee->client->clientWorkflowSetting->flexible_timesheet_setting['enable_check_in_out'])
            && in_array($clientEmployee->timesheet_exception, ['all', 'checkin', 'checkout'])
            && ($type == 'overtime_request' || $type == 'makeup_request')
        ) {
            $worktimeRegister->skip_logic = 1;
        }

        $item = WorktimeRegister::where('client_employee_id', $worktimeRegister->client_employee_id)
            ->where('type', $type);
        if ($type == Constant::MAKEUP_TYPE) {
            $item = $item->where('code', 'like', '%compensation_request%');
        }
        $item = $item->latest()->first();

        if ($clientEmployee) {
            if ($type == Constant::MAKEUP_TYPE) {
                $type = 'compensation_request';
            }
            $code = strtoupper($clientEmployee->code . '_' . $type . '-00000');

            if ($item) {
                $code = WorktimeRegisterHelper::generateNextID($item->code);
            }

            $worktimeRegister->code = $code;
        }

        // fallback to subtype
        if (!$worktimeRegister->category) {
            $worktimeRegister->category = $worktimeRegister->sub_type;
        }

        // Auto approve when create WorkTimeRegister by group
        if ($worktimeRegister->group_id) {
            if (!is_null($worktimeRegister->creator_id) && $worktimeRegister->type === Constant::TYPE_OT) {
                $worktimeRegister->status = Constant::PENDING_STATUS;
            } else {
                $worktimeRegister->status =  Constant::APPROVE_STATUS;
            }
            $worktimeRegister->approved_date = Carbon::now();
        }

        $worktimeRegister->info_app = ClientHelper::getInfoApp();
    }

    public function created(WorktimeRegister $worktimeRegister)
    {

        ProcessWorktimeRegister::dispatch($worktimeRegister->id)
            ->delay(now()->addSeconds(10));
    }

    protected function validate(WorktimeRegister $worktimeRegister)
    {

        $worktimeRegisters = WorktimeRegister::select('*')
            ->where('client_employee_id', $worktimeRegister->client_employee_id)
            ->where('type', $worktimeRegister->type)
            ->where('sub_type', $worktimeRegister->sub_type)
            ->whereNotIn('status', ['canceled', 'canceled_approved'])->get();

        if ($worktimeRegisters->isEmpty()) return true;

        $startTime = Carbon::parse($worktimeRegister->start_time);
        $endTime   = Carbon::parse($worktimeRegister->end_time);

        $collection = new PeriodCollection();

        foreach ($worktimeRegisters as $w) {
            $collection = $collection->add(Period::make($w->start_time, $w->end_time, Precision::MINUTE, Boundaries::EXCLUDE_END));
        }

        // Boundaries::EXCLUDE_END -> bỏ time cuối, trường hợp xin đơn 16:00 ~ 16:30, xong xin tiếp đơn 16:30 ~ 17:00
        // bị báo trùng
        $current = new PeriodCollection(Period::make($startTime, $endTime, Precision::MINUTE, Boundaries::EXCLUDE_END));
        $overlaps = $current->overlap($collection);

        if (count($overlaps))
            throw new HumanErrorException(__("error.invalid_time"), ErrorCode::ERR0004);

        return true;
    }

    public function updated(WorktimeRegister $worktimeRegister)
    {
        $worktimeRegister->realStatus = true;
        /** @var ClientEmployee $clientEmployee */
        $clientEmployee = $worktimeRegister->clientEmployee;

        if (empty($clientEmployee)) {
            return;
        }
        if ($worktimeRegister->type == 'overtime_request' || $worktimeRegister->type == 'makeup_request') {
            if ($worktimeRegister->status == 'canceled_approved') {
                $worktimeRegister->reCalculatedOTWhenCancel();
            }
        } else {
            // Refresh lại timesheet của nhân viên
            $periods = $worktimeRegister->periods;
            foreach ($periods as $period) {
                $ts = $clientEmployee->touchTimesheet($period->date_time_register);
                $ts->recalculate();
                $ts->saveQuietly();
            }
        }

        if ($worktimeRegister->type == 'leave_request' && $worktimeRegister->status == 'canceled_approved') {

            $this->cancelPaidLeaveChange($worktimeRegister);
        }

        if ($worktimeRegister->status == 'canceled_approved') {

            // Notify to approval user
            $approvedBy = $worktimeRegister->approvedBy;
            if ($approvedBy) {
                $user = $approvedBy->user;
                if ($user) {
                    $employee = $worktimeRegister->clientEmployee;
                    $wtrType = $worktimeRegister->type;
                    $lang = $user->prefered_language;
                    $user->notify(new WorktimeRegisterNotification($employee, $wtrType, 'canceled_approved', $lang));
                }
            }
        }

        // Khi approve CLIENT_REQUEST_TIMESHEET được duyệt hoặc từ chối
        if ($worktimeRegister->type == 'timesheet' && $worktimeRegister->sub_type == 'timesheet') {
            $startTime = Carbon::parse($worktimeRegister->start_time)->format('Y-m-d');
            $endTime = Carbon::parse($worktimeRegister->end_time)->format('Y-m-d');

            Timesheet::where('client_employee_id', $worktimeRegister->client_employee_id)->whereDate('log_date', '>=', $startTime)->whereDate('log_date', '<=', $endTime)->update(['state' => $worktimeRegister->status]);

            logger('Update timesheet approve', [$worktimeRegister->client_employee_id, $startTime, $endTime, $worktimeRegister->status]);

            logger('Timesheet list', [
                Timesheet::where('client_employee_id', $worktimeRegister->client_employee_id)
                    ->whereDate('log_date', '>=', $startTime)
                    ->whereDate('log_date', '<=', $endTime)->get()
            ]);
        }
    }

    protected function cancelPaidLeaveChange($worktimeRegister)
    {
        $type = ($worktimeRegister->sub_type === Constant::AUTHORIZED_LEAVE) ? PaidLeaveChange::class : UnpaidLeaveChange::class;

        $leaveChanges = $type::where('work_time_register_id', $worktimeRegister->id)->get();

        if ($leaveChanges->isEmpty()) {
            return;
        }

        foreach ($leaveChanges as $leaveChange) {

            // Kiểm tra nếu hủy đơn trong quá khứ (hết hạn sử dụng giờ phép năm trước) thì không cần trừ lại
            if ($worktimeRegister->category == 'year_leave' && $leaveChange->year_leave_type == 0) {
                $paidLeaveChangeSummary = WorktimeRegisterHelper::getYearPaidLeaveChange($leaveChange->client_employee_id);

                $lastYearLeaveEnd = $paidLeaveChangeSummary["han_su_dung_gio_phep_nam_truoc"];

                if (empty($lastYearLeaveEnd) || Carbon::parse($lastYearLeaveEnd)->isPast()) {
                    continue;
                }
            }

            $changedValue = -1 * $leaveChange->changed_ammount;

            $type::create([
                'client_id' => $leaveChange->client_id,
                'client_employee_id' => $leaveChange->client_employee_id,
                'work_time_register_id' => $worktimeRegister->id,
                'category' => $leaveChange->category,
                'changed_ammount' => $changedValue,
                'changed_reason' => Constant::TYPE_SYSTEM,
                'year_leave_type' => $leaveChange->year_leave_type,
                'effective_at' => $leaveChange->effective_at,
                'month' => $leaveChange->month,
                'year' => $leaveChange->year
            ]);
        }
    }

    /**
     * @throws HumanErrorException
     */
    public function deleting(WorktimeRegister $workTimeRegister)
    {
        $employee = $workTimeRegister->clientEmployee;
        if ($employee) {
            $clientSetting = ClientWorkflowSetting::where('client_id', $employee->client_id)->first();
            if ($clientSetting) {
                WorktimeRegisterHelper::validateWhenUserChangeSetting($workTimeRegister, $clientSetting);
            }
        }

        $this->checkApproveBeforeDelete($workTimeRegister->id);
    }

    public function deleted(WorktimeRegister $worktimeRegister)
    {
        $this->deleteApprove('App\Models\WorktimeRegister', $worktimeRegister->id);
        $worktimeRegister->workTimeRegisterPeriod()->delete();
    }
}
