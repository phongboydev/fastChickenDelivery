<?php

namespace App\Support;


use App\Models\WorkTimeRegisterPeriod;

class WorkTimeRegisterPeriodHelper
{
    public static function getPeriodByCondition($employeeId, $types, $startDate = null, $endDate = null)
    {
        $periods =  WorkTimeRegisterPeriod::whereHas('worktimeRegister', function ($query) use($employeeId, $types) {
            $query->where('client_employee_id', $employeeId)
            ->whereIn('type', $types)
            ->whereNotIn('status', ['canceled', 'canceled_approved']);
        });

        if(!is_null($startDate)) {
            $periods = $periods->where('date_time_register', '>=', $startDate);
        }

        if(!is_null($endDate)) {
            $periods = $periods->where('date_time_register', '<=', $endDate);
        }

        return $periods->with('worktimeRegister')->get();
	}

	public static function updateCancellationApprovalPending($approve, $status) {
	    $isTypeCancel = in_array($approve->type, Constant::TYPE_CANCEL_ADVANCED_APPROVE);
        if($isTypeCancel) {
            $content = json_decode($approve->content, true);
            $workTimeRegisterPeriods = $content['workTimeRegisterPeriod'];
            foreach ($workTimeRegisterPeriods as $item) {
                if (isset($content['id']) && isset($item['date_time_register']) && isset($item['start_time']) && isset($item['end_time'])) {
                    $period = WorkTimeRegisterPeriod::where([
                        'worktime_register_id' => $content['id'],
                        'date_time_register' => $item['date_time_register'],
                        'start_time' => $item['start_time'],
                        'end_time' => $item['end_time'],
                    ])->first();
                    if ($period) {
                        $period->is_cancellation_approval_pending = $status;
                        $period->save();
                    }
                }
            }
        }
	}

	public static function calculationFlexibleCheckout($checkIn, $workTemplate)
    {
        $wsCheckIn = strtotime($workTemplate->check_in);
        $wsCheckOut = strtotime($workTemplate->check_out);
        $flexibleCheckInTime = strtotime($checkIn);
        return date('H:i', $wsCheckOut + ($flexibleCheckInTime - $wsCheckIn));
    }
}
