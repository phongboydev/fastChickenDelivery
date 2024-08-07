<?php

namespace App\Jobs;

use App\Models\ClientEmployeeLeaveManagement;
use App\Models\ClientEmployeeLeaveManagementByMonth;
use App\Models\WorkScheduleGroup;
use App\Support\WorktimeRegisterHelper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecalculateLeaveOfClientEmployeeByYear implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The time sheet instance.
     *
     * @var ClientEmployeeLeaveManagement
     */
    private $clientEmployeeLeaveManagement;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ClientEmployeeLeaveManagement $clientEmployeeLeaveManagement)
    {
        $this->clientEmployeeLeaveManagement = $clientEmployeeLeaveManagement;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::beginTransaction();
        try {
            // Pre data
            $clientEmployeeLeaveManagement = $this->clientEmployeeLeaveManagement;
            $leaveCategory = $clientEmployeeLeaveManagement->leaveCategory;
            $employee = $clientEmployeeLeaveManagement->clientEmployee;
            // Loop through each months by year
            WorkScheduleGroup::where('work_schedule_group_template_id', $employee->work_schedule_group_template_id)
                ->where('client_id', $employee->client_id)
                ->where('timesheet_from', '>=', $clientEmployeeLeaveManagement->start_date)
                ->where('timesheet_to', '<=', $clientEmployeeLeaveManagement->end_date)
                ->orderBy('timesheet_from')
                ->chunkById(100, function ($items) use ($clientEmployeeLeaveManagement, $leaveCategory, $employee) {
                    $arrayInsert = [];
                    $remainMonth = $clientEmployeeLeaveManagement->entitlement;
                    $countIsUsedLeaveByYear = 0;
                    foreach ($items as $item) {
                        $condition = [
                            'start' => $item->timesheet_from,
                            'end' => $item->timesheet_to,
                            'client_employee_id' => $employee->id,
                            'sub_type' => $leaveCategory->type,
                            'category' => $leaveCategory->sub_type,
                        ];
                        $countIsUsedLeave = WorktimeRegisterHelper::getCountIsUseLeaveRequest($condition);
                        $remainMonth -= $countIsUsedLeave;
                        $clientEmployeeLeaveManagementByMonth = $clientEmployeeLeaveManagement->clientEmployeeLeaveManagementByMonth
                            ->where('start_date', $item->timesheet_from->toDateString())
                            ->where('end_date', $item->timesheet_to->toDateString())
                            ->first();
                        if ($clientEmployeeLeaveManagementByMonth) {
                            $clientEmployeeLeaveManagementByMonth->entitlement_used = $countIsUsedLeave;
                            $clientEmployeeLeaveManagementByMonth->remaining_entitlement = $remainMonth;
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
                                'year' => $clientEmployeeLeaveManagement->year,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ];
                            $arrayInsert[] = $itemInsert;
                        }
                        $countIsUsedLeaveByYear += $countIsUsedLeave;
                    }

                    $clientEmployeeLeaveManagement->entitlement_used = $countIsUsedLeaveByYear;
                    $clientEmployeeLeaveManagement->remaining_entitlement = $clientEmployeeLeaveManagement->entitlement - $countIsUsedLeaveByYear;
                    $clientEmployeeLeaveManagement->saveQuietly();

                    if (count($arrayInsert) > 0) {
                        ClientEmployeeLeaveManagementByMonth::insert($arrayInsert);
                    }
                });
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return $this->clientEmployeeLeaveManagement->id;
    }
}
