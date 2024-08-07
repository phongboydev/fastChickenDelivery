<?php

namespace App\Console\Commands;

use App\Models\WorkTimeRegisterPeriod;
use App\Models\PaidLeaveChange;
use App\Support\WorktimeRegisterHelper;
use Illuminate\Console\Command;
use Carbon\Carbon;

class LeaveManagementDecrease extends Command
{

    protected $signature = 'leave-management:decrease';
    protected $description = 'When registering "annual leave", do not deduct consecutive leave days, but deduct from the new leave day';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $today = Carbon::now();

        WorkTimeRegisterPeriod::where('da_tru', false)
            ->where('so_gio_tam_tinh', '>', 0)
            ->whereDate('date_time_register', '<=', $today->format('Y-m-d'))
            ->whereHas('worktimeRegister', function ($query) {
                $query->where('status', 'approved');
                $query->where('type', 'leave_request');
            })
            ->with('worktimeRegister')
            ->chunkById(100, function ($periods) {
                foreach ($periods as $period) {
                    $workTimeRegister = $period->worktimeRegister;

                    if ($workTimeRegister['status'] == 'approved') {
                        if ($workTimeRegister['category'] == 'year_leave') {
                            WorktimeRegisterHelper::processYearLeaveChange($workTimeRegister, true, true);
                        } else {
                            $realWorkHours = -1 * $period->so_gio_tam_tinh;

                            $month = Carbon::parse($period->date_time_register)->format('n');
                            $year = Carbon::parse($period->date_time_register)->format('Y');
                            $clientId = $workTimeRegister['clientEmployee']['client_id'];

                            $dataUpdate = ['da_tru' => 1];
                            $period->update($dataUpdate);

                            PaidLeaveChange::create([
                                'client_id' => $clientId,
                                'client_employee_id' => $workTimeRegister['client_employee_id'],
                                'work_time_register_id' => $workTimeRegister['id'],
                                'changed_ammount' => $realWorkHours,
                                'changed_reason' => 'system',
                                'effective_at' => $workTimeRegister['approved_date'],
                                'month' => $month,
                                'year' => $year
                            ]);
                        }
                    }
                }
            }, 'id');
    }
}
