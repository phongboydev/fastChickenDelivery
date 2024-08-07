<?php

namespace App\Console\Commands;

use App\Models\WorkTimeRegisterPeriod;
use App\Models\PaidLeaveChange;
use App\Models\UnpaidLeaveChange;
use App\Support\Constant;
use Illuminate\Console\Command;
use Carbon\Carbon;

class LeaveChangeSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:leaveChangeSchedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Khi đăng ký "nghỉ phép" không trừ ngày phép liền, mà qua ngày nghỉ phép mới trừ';

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

        WorkTimeRegisterPeriod::where('da_tru', 0)
            ->where('so_gio_tam_tinh', '>', 0)
            ->whereDate('date_time_register', '<=', $today->format('Y-m-d'))
            ->whereHas('worktimeRegister', function ($query) {
                // only status is approved
                $query->where('status', 'approved');
            })
            ->with('worktimeRegister')
            ->chunkById(100, function ($periods) {
                foreach ($periods as $period) {
                    $workTimeRegister = $period->worktimeRegister;

                    if ($workTimeRegister['status'] == 'approved') {
                        $realWorkHours = -1 * $period->so_gio_tam_tinh;

                        $month = Carbon::parse($period->date_time_register)->format('n');
                        $year = Carbon::parse($period->date_time_register)->format('Y');
                        $clientId = $workTimeRegister['clientEmployee']['client_id'];

                        $period->update(['da_tru' => 1]);

                        $type = ($workTimeRegister['sub_type'] === Constant::AUTHORIZED_LEAVE) ? PaidLeaveChange::class : UnpaidLeaveChange::class;

                        $type::create([
                            'client_id' => $clientId,
                            'client_employee_id' => $workTimeRegister['client_employee_id'],
                            'work_time_register_id' => $workTimeRegister['id'],
                            'category' => $workTimeRegister['category'],
                            'changed_ammount' => $realWorkHours,
                            'changed_reason' => Constant::TYPE_SYSTEM,
                            'effective_at' => $workTimeRegister['approved_date'],
                            'month' => $month,
                            'year' => $year
                        ]);
                    }
                }
            }, 'id');
    }
}
