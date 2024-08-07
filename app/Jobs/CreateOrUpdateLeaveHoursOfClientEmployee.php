<?php

namespace App\Jobs;

use App\Models\ClientEmployee;
use App\Models\ClientEmployeeLeaveManagement;
use App\Models\ClientEmployeeLeaveManagementByMonth;
use App\Models\LeaveCategory;
use App\Models\WorkTimeRegisterPeriod;
use App\Support\WorktimeRegisterHelper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateOrUpdateLeaveHoursOfClientEmployee implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $param;
    public $workTimeRegister;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($param = null, $workTimeRegister = null)
    {
        // Using create type leave
        $this->param = $param;

        // Refresh leave by month
        $this->workTimeRegister = $workTimeRegister;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $nextYear = date('Y') + 1;

            // Init first for create setting leave type
            if (!is_null($this->param)) {
                $leaveCategory = $this->param['leave'];
                $action = $this->param['action'];
                // Loop each client employee to assign leave type
                $listClientEmployee = ClientEmployee::select('id', 'year_paid_leave_count', 'last_year_paid_leave_expiry', 'last_year_paid_leave_count', 'hours_import_paidleave')
                    ->where('client_id', $leaveCategory->client_id)
                    ->whereNull('quitted_at');
                if (!empty($this->param['client_employee_ids'])) {
                    $listClientEmployee->whereIn('id', $this->param['client_employee_ids']);
                }

                $listClientEmployee->chunkById(100, function ($clientEmployees) use ($leaveCategory, $action, $nextYear) {
                    foreach ($clientEmployees as $itemStaff) {
                        if ($action == 'create') {
                            logger()->info('CreateOrUpdateLeaveHoursOfClientEmployee: ' . $itemStaff->id . ' - ' . $leaveCategory->id);
                            // Kiểm tra nhân viên có phép năm trước hay không
                            $lastYearPaidLeaveCount = 0;
                            $lastYearPaidLeaveEffective = null;

                            // Kiểm tra hạn có phép năm trước có trùng với năm hiện tại hay không
                            if (!is_null($itemStaff->last_year_paid_leave_expiry) && Carbon::parse($itemStaff->last_year_paid_leave_expiry)->format('Y') == $leaveCategory->year) {
                                $lastYearPaidLeaveCount = $itemStaff->last_year_paid_leave_count;
                                $lastYearPaidLeaveEffective = $itemStaff->last_year_paid_leave_expiry;
                            }

                            // Lưu ngày phép năm tiếp theo
                            if ($nextYear == $leaveCategory->year) {
                                ClientEmployee::where('id', $itemStaff->id)->update([
                                    // 'next_year_paid_leave_count' => $itemStaff->hours_import_paidleave,
                                    'next_year_paid_leave_expiry' => $leaveCategory->end_date . " 23:59:59"
                                ]);
                            }

                            ClientEmployeeLeaveManagement::create([
                                'leave_category_id' => $leaveCategory->id,
                                'client_employee_id' => $itemStaff->id,
                                'entitlement' => date('Y') == $leaveCategory->year ? $itemStaff->year_paid_leave_count : 0,
                                'entitlement_last_year' => $lastYearPaidLeaveCount,
                                'entitlement_last_year_effective_date' => $lastYearPaidLeaveEffective,
                                'start_date' => $leaveCategory->start_date,
                                'end_date' => $leaveCategory->end_date,
                                'year' => $leaveCategory->year,
                            ]);
                        } else {
                            $clientEmployeeLeaveManagement = ClientEmployeeLeaveManagement::where(
                                [
                                    'leave_category_id' => $leaveCategory->id,
                                    'client_employee_id' => $itemStaff->id

                                ]
                            )->first();

                            if ($clientEmployeeLeaveManagement) {
                                $clientEmployeeLeaveManagement->start_date = $leaveCategory->start_date;
                                $clientEmployeeLeaveManagement->end_date = $leaveCategory->end_date;
                                $clientEmployeeLeaveManagement->year = $leaveCategory->year;
                                $clientEmployeeLeaveManagement->save();
                            } else {
                                // Use case when migration old data
                                // Kiểm tra nhân viên có phép năm trước hay không
                                $lastYearPaidLeaveCount = 0;
                                $lastYearPaidLeaveEffective = null;

                                // Kiểm tra hạn có phép năm trước có trùng với năm hiện tại hay không
                                if (!is_null($itemStaff->last_year_paid_leave_expiry) && Carbon::parse($itemStaff->last_year_paid_leave_expiry)->format('Y') == $leaveCategory->year) {
                                    $lastYearPaidLeaveCount = $itemStaff->last_year_paid_leave_count;
                                    $lastYearPaidLeaveEffective = $itemStaff->last_year_paid_leave_expiry;
                                }

                                // Lưu ngày phép năm tiếp theo
                                if ($nextYear == $leaveCategory->year) {
                                    ClientEmployee::where('id', $itemStaff->id)->update([
                                        // 'next_year_paid_leave_count' => $itemStaff->hours_import_paidleave,
                                        'next_year_paid_leave_expiry' => $leaveCategory->end_date . " 23:59:59"
                                    ]);
                                }

                                ClientEmployeeLeaveManagement::create([
                                    'leave_category_id' => $leaveCategory->id,
                                    'client_employee_id' => $itemStaff->id,
                                    'entitlement' => date('Y') == $leaveCategory->year ? $itemStaff->year_paid_leave_count : 0,
                                    'entitlement_last_year' => $lastYearPaidLeaveCount,
                                    'entitlement_last_year_effective_date' => $lastYearPaidLeaveEffective,
                                    'start_date' => $leaveCategory->start_date,
                                    'end_date' => $leaveCategory->end_date,
                                    'year' => $leaveCategory->year,
                                ]);
                            }
                        }
                    }
                });
                // Refresh month of employee by application
            } elseif (!is_null($this->workTimeRegister)) {
                $workTimeRegister = $this->workTimeRegister;
                $workTimeRegisterPeriod = WorkTimeRegisterPeriod::where('worktime_register_id', $workTimeRegister->id)->orderBy('date_time_register')->get();
                if (count($workTimeRegisterPeriod) < 1) {
                    return;
                }
                $clientEmployee = $this->workTimeRegister->clientEmployee;
                $leaveCategory = LeaveCategory::where([
                    'client_id' => $clientEmployee->client_id,
                    'type' => $workTimeRegister->sub_type,
                    'sub_type' => $workTimeRegister->category
                ])->first();
                // Check exit leave setting of company is create ?
                if (!$leaveCategory) {
                    return;
                }
                $firstLeaveManagementMonth = ClientEmployeeLeaveManagementByMonth::whereHas('clientEmployeeLeaveManagement', function ($query) use ($clientEmployee, $workTimeRegister) {
                    $query->where('client_employee_id', $clientEmployee->id)
                        ->whereHas('leaveCategory', function ($sudQuery) use ($clientEmployee, $workTimeRegister) {
                            $sudQuery->where('type', $workTimeRegister->sub_type)
                                ->where('sub_type', $workTimeRegister->category);
                        });
                })
                    ->where('start_date', '<=', $workTimeRegisterPeriod[0]->date_time_register)
                    ->where('end_date', '>=', $workTimeRegisterPeriod[0]->date_time_register)
                    ->with('clientEmployeeLeaveManagement')->orderBy('start_date')->first();
                if (!$firstLeaveManagementMonth) {
                    return;
                }
                $clientEmployeeLeaveManagement = $firstLeaveManagementMonth->clientEmployeeLeaveManagement;
                $countEntitlementUsed = 0;
                $countEntitlementUsedLastYear = 0;

                ClientEmployeeLeaveManagementByMonth::where('client_employee_leave_management_id', $clientEmployeeLeaveManagement->id)
                    ->with('clientEmployeeLeaveManagement')
                    ->orderBy('start_date')
                    ->chunkById(100, function ($items) use ($clientEmployee, $firstLeaveManagementMonth, $clientEmployeeLeaveManagement, &$countEntitlementUsed, &$countEntitlementUsedLastYear, $leaveCategory) {
                        $remainMonth = $clientEmployeeLeaveManagement->entitlement;
                        $remainMonthLastYear = $clientEmployeeLeaveManagement->entitlement_last_year;

                        foreach ($items as $item) {
                            if (strtotime($item->start_date) >= strtotime($firstLeaveManagementMonth->start_date)) {
                                $condition = [
                                    'start' => $item->start_date,
                                    'end' => $item->end_date,
                                    'client_employee_id' => $clientEmployee->id,
                                    'sub_type' => $leaveCategory->type,
                                    'category' => $leaveCategory->sub_type,
                                ];

                                $countIsUsedLeave = WorktimeRegisterHelper::getCountIsUseLeaveRequest($condition);
                                $remainMonth -= $countIsUsedLeave;
                                $item->entitlement_used = $countIsUsedLeave;
                                $item->remaining_entitlement = $remainMonth;
                                // Last Year
                                $countIsUsedLeaveLastYear = 0;
                                if ($remainMonthLastYear > 0) {
                                    $countIsUsedLeaveLastYear = WorktimeRegisterHelper::getCountIsUseLeaveRequest($condition, false);
                                    $remainMonthLastYear -= $countIsUsedLeaveLastYear;
                                    $item->used_entitlement_last_year = $countIsUsedLeaveLastYear;
                                    $item->remaining_entitlement_last_year = $remainMonthLastYear;
                                }
                                $item->save();
                                $countEntitlementUsed += $countIsUsedLeave;
                                $countEntitlementUsedLastYear += $countIsUsedLeaveLastYear;
                            } else {
                                $remainMonth -= $item->entitlement_used;
                                $countEntitlementUsed += $item->entitlement_used;
                                $remainMonthLastYear -= $item->used_entitlement_last_year;
                                $countEntitlementUsedLastYear += $item->used_entitlement_last_year;
                            }
                        }
                    });
                // Update detail employee leave
                $clientEmployeeLeaveManagement->entitlement_used = $countEntitlementUsed;
                $clientEmployeeLeaveManagement->remaining_entitlement = $clientEmployeeLeaveManagement->entitlement - $countEntitlementUsed;
                $clientEmployeeLeaveManagement->used_entitlement_last_year = $countEntitlementUsedLastYear;
                $clientEmployeeLeaveManagement->remaining_entitlement_last_year = $clientEmployeeLeaveManagement->entitlement_last_year - $countEntitlementUsedLastYear;
                $clientEmployeeLeaveManagement->saveQuietly();
            }
        } catch (\Exception $e) {
            logger()->error("CreateOrUpdateLeaveHoursOfClientEmployee Job error: " . $e->getMessage() . ' at line ' . $e->getLine() . ' at file ' . $e->getFile());
        }
    }
}
