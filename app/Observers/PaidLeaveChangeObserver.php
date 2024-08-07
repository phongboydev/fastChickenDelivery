<?php

namespace App\Observers;

use App\Jobs\CreateOrUpdateLeaveHoursOfClientEmployee;
use App\Models\ClientEmployeeLeaveManagement;
use App\Models\PaidLeaveChange;
use App\Models\ClientEmployee;
use Carbon\Carbon;
use App\Support\Constant;
use App\Exceptions\HumanErrorException;

class PaidLeaveChangeObserver
{
    public function creating(PaidLeaveChange $paidLeaveChange)
    {
        // if ($paidLeaveChange['category'] == 'year_leave' && $paidLeaveChange['changed_reason'] == Constant::TYPE_USER && $paidLeaveChange['changed_reason'] == Constant::TYPE_USER) {
        //     // kiểm tra last_year_paid_leave_count còn hạn không
        //     $clientEmployee = ClientEmployee::select('last_year_paid_leave_count')->where('id', $paidLeaveChange->client_employee_id)->first();


        //     throw new HumanErrorException(__("client_employee_salary_history.update.fail"));
        // }
    }

    /**
     * Handle the PaidLeaveChange "created" event.
     *
     * @param PaidLeaveChange $paidLeaveChange
     * @return void
     */


    public function created(PaidLeaveChange $paidLeaveChange)
    {
        $clientEmployeeId = $paidLeaveChange->client_employee_id;
        $changedAmount = $paidLeaveChange->changed_ammount;
        $employee = ClientEmployee::select('year_paid_leave_count', 'last_year_paid_leave_count', 'leave_balance')->where('id', $clientEmployeeId)->first();

        if (empty($employee)) return;

        switch ($paidLeaveChange->changed_reason) {
            case Constant::TYPE_SYSTEM:
                $workTimeRegister = $paidLeaveChange->workTimeRegister;

                if (empty($workTimeRegister)) {
                    return;
                }

                $subType = $workTimeRegister->sub_type;
                $category = $workTimeRegister->category;

                if ($workTimeRegister->sub_type == Constant::AUTHORIZED_LEAVE && $category == 'year_leave') {
                    if ($paidLeaveChange->year_leave_type === 0) {
                        $remainingOfHours = $employee->last_year_paid_leave_count + $changedAmount;
                        ClientEmployee::where('id', $clientEmployeeId)->update(['last_year_paid_leave_count' => (float)$remainingOfHours]);
                    } elseif ($paidLeaveChange->year_leave_type === null || $paidLeaveChange->year_leave_type === 1) {
                        $remainingOfHours = $employee->year_paid_leave_count + $changedAmount;
                        ClientEmployee::where('id', $clientEmployeeId)->update(['year_paid_leave_count' => (float)$remainingOfHours]);
                    }

                    // Refresh count is used leave every month
                    dispatch(new CreateOrUpdateLeaveHoursOfClientEmployee(null, $workTimeRegister));
                } else {
                    $remainingOfHours = (json_decode($employee->leave_balance, true)[$subType][$category] ?? 0) + $changedAmount;
                    ClientEmployee::where('id', $clientEmployeeId)->update(['leave_balance->' . $subType . '->' . $category => (float)$remainingOfHours]);
                }
                break;

            case Constant::TYPE_USER:
                if (!$paidLeaveChange->workTimeRegister) {
                    $category = $paidLeaveChange->category;
                    if ($category == 'year_leave') {
                        // Update hours of year leave type
                        $currentDate = Carbon::now()->format('Y-m-d');
                        $clientEmployeeLeaveManagement = ClientEmployeeLeaveManagement::where('client_employee_id', $clientEmployeeId)
                            ->whereHas('leaveCategory', function ($query) use ($currentDate) {
                                $query->where('type', 'authorized_leave')
                                    ->where('sub_type', 'year_leave')
                                    ->where('start_date', '<=', $currentDate)
                                    ->where('end_date', '>=', $currentDate);
                            })->first();

                        if ($paidLeaveChange->year_leave_type === 0) {
                            $remainingOfHours = $employee->last_year_paid_leave_count + $changedAmount;
                            ClientEmployee::where('id', $clientEmployeeId)->update(['last_year_paid_leave_count' => (float)$remainingOfHours]);

                            if ($clientEmployeeLeaveManagement) {
                                $clientEmployeeLeaveManagement->entitlement_last_year = $clientEmployeeLeaveManagement->entitlement_last_year + $changedAmount;
                                $clientEmployeeLeaveManagement->save();
                            }
                        } elseif ($paidLeaveChange->year_leave_type === null || $paidLeaveChange->year_leave_type === 1) {
                            $remainingOfHours = $employee->year_paid_leave_count + $changedAmount;
                            ClientEmployee::where('id', $clientEmployeeId)->update(['year_paid_leave_count' => (float)$remainingOfHours]);

                            if ($clientEmployeeLeaveManagement) {
                                $clientEmployeeLeaveManagement->entitlement = $clientEmployeeLeaveManagement->entitlement + $changedAmount;
                                $clientEmployeeLeaveManagement->save();
                            }
                        }
                    } else {
                        if (is_null($changedAmount)) {
                            $remainingOfHours = $changedAmount;
                        } else {
                            $leaveBalance = json_decode($employee->leave_balance, true);
                            (float)$remainingOfHours = ($leaveBalance[Constant::AUTHORIZED_LEAVE][$category] ?? 0) + $changedAmount;
                        }
                        ClientEmployee::where('id', $clientEmployeeId)->update([
                            'leave_balance->' . Constant::AUTHORIZED_LEAVE . '->' . $category => $remainingOfHours
                        ]);
                    }
                }
                break;
        }
    }
}
