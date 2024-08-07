<?php

namespace App\Console\Commands;

use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\ClientEmployee;
use App\Models\PaidLeaveChange;
use App\Models\PaidLeaveBalance;

class TidyPaidLeaveChange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:paidLeaveChange';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate paid leave change for all client employees';

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


        ClientEmployee::query()
            ->chunkById(100, function ($emloyees) {
                foreach ($emloyees as $emloyee) {

                    $this->line("Processed ... " . '[' . $emloyee->code . '] ' . $emloyee->full_name);

                    $this->generateTodayPaidLeaveChange($emloyee);
                }
            });
    }
    private function generateTodayPaidLeaveChange($employee)
    {
        $today = Carbon::now();
        $month = $today->format('n');
        $year  = $today->format('Y');

        $todayBalance = PaidLeaveChange::selectRaw('
            client_id, client_employee_id,
            SUM(IF(changed_ammount < 0, ABS(changed_ammount), 0)) AS used_balance,
            SUM(IF(changed_ammount > 0, ABS(changed_ammount), 0)) AS added_balance
        ')
            ->where('month', $month)->where('year', $year)->where('client_employee_id', $employee->id)->groupBy('client_id', 'client_employee_id')->first();

        $todayBeginBalance = 0;
        $todayEndBalance = $employee->year_paid_leave_count;

        if (!empty($todayBalance)) {
            $todayBeginBalance = !empty($todayBalance) && $todayBalance->begin_balance !== null ? $todayBalance->begin_balance : 0;
            $todayEndBalance = $todayBeginBalance - $todayBalance->used_balance + $todayBalance->added_balance;
        }

        PaidLeaveBalance::updateOrCreate(
            [
                'client_id' => $employee->client_id,
                'client_employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
            ],
            [
                'begin_balance' => $todayBeginBalance,
                'used_balance' => 0,
                'added_balance' => 0,
                'end_balance' => $todayEndBalance,
            ]
        );
    }
}
