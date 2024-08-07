<?php

namespace App\Observers;

use App\Models\ClientEmployeeLeaveManagement;
use App\Models\ClientEmployeeLeaveManagementByMonth;
use App\Models\WorkScheduleGroup;
use App\Support\WorktimeRegisterHelper;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ClientEmployeeLeaveManagementObserver
{

    /**
     * Handle the ClientEmployeeLeaveManagement "created" event.
     *
     * @param ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement
     * @return void
     */
    public function creating(ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
    {
    }

    /**
     * Handle the ClientEmployeeLeaveManagement "created" event.
     *
     * @param ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement
     * @return void
     */
    public function created(ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
    {
        $clientEmployee = $clientEmployeeLeaveManagement->clientEmployee;
        $leaveCategory = $clientEmployeeLeaveManagement->leaveCategory;
        $this->createClientEmployeeLeaveByMonth($clientEmployeeLeaveManagement, $clientEmployee, $leaveCategory, 'create');
    }

    /**
     * Handle the ClientEmployeeLeaveManagement "updated" event.
     *
     * @param ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement
     * @return void
     */
    public function updating(ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
    {
        // Delete month outside range year
        ClientEmployeeLeaveManagementByMonth::where('client_employee_leave_management_id', $clientEmployeeLeaveManagement->id)
            ->where('year', $clientEmployeeLeaveManagement->year)
            ->where(function ($query) use ($clientEmployeeLeaveManagement) {
                $query->where('start_date', '<', $clientEmployeeLeaveManagement->start_date)
                    ->orWhere('end_date', '>', $clientEmployeeLeaveManagement->end_date);
            })
            ->delete();
    }

    /**
     * Handle the ClientEmployeeLeaveManagement "updated" event.
     *
     * @param ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement
     * @return void
     */
    public function updated(ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
    {
        $clientEmployee = $clientEmployeeLeaveManagement->clientEmployee;
        $leaveCategory = $clientEmployeeLeaveManagement->leaveCategory;
        $this->createClientEmployeeLeaveByMonth($clientEmployeeLeaveManagement, $clientEmployee, $leaveCategory, 'update');
    }

    /**
     * Handle the ClientEmployeeLeaveManagementByMonth "deleting" event.
     *
     * @param ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement
     * @return void
     */
    public function deleting(ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
    {
        $clientEmployeeLeaveManagement->clientEmployeeLeaveManagementByMonth()->delete();
    }

    /**
     * Handle the ClientEmployeeLeaveManagement "deleted" event.
     *
     * @param ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement
     * @return void
     */
    public function deleted(ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
    {
    }

    /**
     * Handle the ClientEmployeeLeaveManagement "restored" event.
     *
     * @param ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement
     * @return void
     */
    public function restored(ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
    {
        //
    }

    /**
     * Handle the ClientEmployeeLeaveManagement "force deleted" event.
     *
     * @param ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement
     * @return void
     */
    public function forceDeleted(ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
    {
        //
    }

    public function createClientEmployeeLeaveByMonth($clientEmployeeLeaveManagement, $clientEmployee, $leaveCategory, $action)
    {

        WorkScheduleGroup::where('work_schedule_group_template_id', $clientEmployee->work_schedule_group_template_id)
            ->where('client_id', $clientEmployee->client_id)
            ->where('timesheet_from', '>=', $clientEmployeeLeaveManagement->start_date)
            ->where('timesheet_to', '<=', $clientEmployeeLeaveManagement->end_date)
            ->orderBy('timesheet_from')
            ->chunkById(100, function ($items) use ($clientEmployee, $clientEmployeeLeaveManagement, $leaveCategory, $action) {
                $arrayInsert = [];
                $remainMonth = $clientEmployeeLeaveManagement->entitlement;
                $remainMonthLastYear = $clientEmployeeLeaveManagement->entitlement_last_year;

                $countIsUsedLeaveByYear = 0;
                $countIsUsedLeaveByLastYear = 0;

                foreach ($items as $item) {
                    $condition = [
                        'start' => $item->timesheet_from,
                        'end' => $item->timesheet_to,
                        'client_employee_id' => $clientEmployee->id,
                        'sub_type' => $leaveCategory->type,
                        'category' => $leaveCategory->sub_type,
                    ];
                    $countIsUsedLeave = WorktimeRegisterHelper::getCountIsUseLeaveRequest($condition);
                    $remainMonth -= $countIsUsedLeave;

                    // Last Year
                    $countIsUsedLeaveLastYear = WorktimeRegisterHelper::getCountIsUseLeaveRequest($condition, false);
                    $remainMonthLastYear -= $countIsUsedLeaveLastYear;

                    if ($action === 'create') {
                        $itemInsert = [
                            'id' => Str::uuid(),
                            'client_employee_leave_management_id' => $clientEmployeeLeaveManagement->id,
                            'name' => $item->name,
                            'start_date' => $item->timesheet_from,
                            'end_date' => $item->timesheet_to,
                            'entitlement_used' => $countIsUsedLeave,
                            'remaining_entitlement' => $remainMonth,
                            'used_entitlement_last_year' => $countIsUsedLeaveLastYear,
                            'remaining_entitlement_last_year' => $remainMonthLastYear,
                            'year' => $clientEmployeeLeaveManagement->year,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                        $arrayInsert[] = $itemInsert;
                    } else {
                        $clientEmployeeLeaveManagementByMonth = ClientEmployeeLeaveManagementByMonth::where('start_date', '<=', $item->timesheet_from)
                            ->where('end_date', '>=', $item->timesheet_to)->where('client_employee_leave_management_id', $clientEmployeeLeaveManagement->id)->first();
                        if ($clientEmployeeLeaveManagementByMonth) {
                            $clientEmployeeLeaveManagementByMonth->entitlement_used = $countIsUsedLeave;
                            $clientEmployeeLeaveManagementByMonth->remaining_entitlement = $remainMonth;
                            $clientEmployeeLeaveManagementByMonth->used_entitlement_last_year = $countIsUsedLeaveLastYear;
                            $clientEmployeeLeaveManagementByMonth->remaining_entitlement_last_year = $remainMonthLastYear;
                            $clientEmployeeLeaveManagementByMonth->save();
                        } else {
                            $itemInsert = [
                                'id' => Str::uuid(),
                                'client_employee_leave_management_id' => $clientEmployeeLeaveManagement->id,
                                'name' => $item->name,
                                'start_date' => $item->timesheet_from,
                                'end_date' => $item->timesheet_to,
                                'entitlement_used' => $countIsUsedLeave,
                                'remaining_entitlement' => $remainMonth,
                                'used_entitlement_last_year' => $countIsUsedLeaveLastYear,
                                'remaining_entitlement_last_year' => $remainMonthLastYear,
                                'year' => $clientEmployeeLeaveManagement->year,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ];
                            $arrayInsert[] = $itemInsert;
                        }
                    }

                    $countIsUsedLeaveByYear += $countIsUsedLeave;
                    $countIsUsedLeaveByLastYear += $countIsUsedLeaveLastYear;
                }
                $clientEmployeeLeaveManagement->entitlement_used = $countIsUsedLeaveByYear;
                $clientEmployeeLeaveManagement->remaining_entitlement = $clientEmployeeLeaveManagement->entitlement - $countIsUsedLeaveByYear;
                $clientEmployeeLeaveManagement->used_entitlement_last_year = $countIsUsedLeaveByLastYear;
                $clientEmployeeLeaveManagement->remaining_entitlement_last_year = $clientEmployeeLeaveManagement->entitlement_last_year - $countIsUsedLeaveByLastYear;
                $clientEmployeeLeaveManagement->saveQuietly();

                ClientEmployeeLeaveManagementByMonth::insert($arrayInsert);
            });
    }
}
