<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use App\Models\ClientEmployeeLeaveManagement;
use Carbon\Carbon;
use Illuminate\Console\Command;

class LeaveManagementIncrease extends Command
{
    protected $signature = 'leave-management:increase {nowDate?}';
    protected $description = 'Update time by year/month';

    public function handle()
    {
        $nowDate = $this->argument("nowDate") ? Carbon::parse($this->argument("nowDate")) : Carbon::now();
        $chunks = config('app.paidleave_change_chunks', 600);

        ClientEmployee::select('*')
            ->chunkById($chunks, function ($employees) use ($nowDate) {
                foreach ($employees as $employee) {
                    $hours = $employee->year_paid_leave_count;
                    $currentDate = Carbon::parse($nowDate->format('Y-m-d'));

                    switch ($employee['case_import_paidleave']) {
                        case 'tang_hang_thang':
                            if (!$employee['started_import_paidleave'] || Carbon::parse($employee['started_import_paidleave'])->addMonth(1)->isSameDay($currentDate)) {
                                $hours += $employee['hours_import_paidleave'];
                                $this->updateEmployeeAndLeaveManagement($employee, $currentDate, $hours);
                            }
                            break;

                        case 'tang_hang_nam':
                            if (!$employee['started_import_paidleave'] || Carbon::parse($employee['started_import_paidleave'])->addYear(1)->isSameDay($currentDate)) {
                                $hours = $employee['hours_import_paidleave'];
                                $this->updateEmployeeAndLeaveManagement($employee, $currentDate, $hours);
                            }
                            break;

                        default:
                            break;
                    }
                }
            }, 'id');
    }

    private function updateEmployeeAndLeaveManagement($employee, $currentDate, &$hours)
    {
        if ($hours != $employee->year_paid_leave_count) {
            $this->line("Processed: [{$employee->code}] {$employee->full_name}");
            $employee->update([
                'year_paid_leave_count' => $hours,
                'started_import_paidleave' => $currentDate->format('Y-m-d')
            ]);

            $leaveManagement = ClientEmployeeLeaveManagement::where('client_employee_id', $employee->id)
                ->whereHas('leaveCategory', function ($query) use ($currentDate) {
                    $query->where('type', 'authorized_leave')
                        ->where('sub_type', 'year_leave')
                        ->where('start_date', '<=', $currentDate)
                        ->where('end_date', '>=', $currentDate);
                })
                ->first();

            if ($leaveManagement) {
                if ($employee['case_import_paidleave'] == 'tang_hang_thang') {
                    $leaveManagement->entitlement += $employee['hours_import_paidleave'];
                } elseif ($employee['case_import_paidleave'] == 'tang_hang_nam') {
                    $leaveManagement->entitlement = $employee['hours_import_paidleave'];
                }

                $leaveManagement->save();
            }
        }
    }
}
