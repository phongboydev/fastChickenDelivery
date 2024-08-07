<?php

namespace App\Observers;

use App\Models\UnpaidLeaveChange;
use App\Models\ClientEmployee;
use App\Support\Constant;

class UnpaidLeaveChangeObserver
{
    public function created(UnpaidLeaveChange $unpaidLeaveChange)
    {
        $clientEmployeeId = $unpaidLeaveChange->client_employee_id;
        $changedAmount = $unpaidLeaveChange->changed_ammount;
        $employee = ClientEmployee::select('leave_balance')->where('id', $clientEmployeeId)->first();

        if (empty($employee)) {
            return;
        }

        switch ($unpaidLeaveChange->changed_reason) {
            case Constant::TYPE_SYSTEM:
                if ($employee) {
                    $category = $unpaidLeaveChange->category;
                    $remainingOfHours = (json_decode($employee->leave_balance, true)[Constant::UNAUTHORIZED_LEAVE][$category] ?? 0) + $changedAmount;

                    ClientEmployee::where('id', $clientEmployeeId)->update([
                        "leave_balance->" . Constant::UNAUTHORIZED_LEAVE . "->" . $category => (float)$remainingOfHours
                    ]);
                }
                break;

            case Constant::TYPE_USER:
                if (!$unpaidLeaveChange->workTimeRegister) {
                    $category = $unpaidLeaveChange->category;
                    if (is_null($changedAmount)) {
                        $remainingOfHours = $changedAmount;
                    } else {
                        $leaveBalance = json_decode($employee->leave_balance, true);
                        (float)$remainingOfHours = ($leaveBalance[Constant::UNAUTHORIZED_LEAVE][$category] ?? 0) + $changedAmount;
                    }

                    ClientEmployee::where('id', $clientEmployeeId)->update([
                        "leave_balance->" . Constant::UNAUTHORIZED_LEAVE . "->" . $category => $remainingOfHours
                    ]);
                }
                break;

            default:
                // Handle other cases here
                break;
        }
    }
}
