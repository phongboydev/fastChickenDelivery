<?php

namespace App\Console\Commands;

use App\Models\PaidLeaveBalance;
use App\Models\PaidLeaveChange;
use Illuminate\Console\Command;
use Carbon\Carbon;

class LeaveManagementSummarize extends Command
{
    protected $signature = 'leave-management:summarize';
    protected $description = 'Update the monthly use date used';

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

        $before = Carbon::now()->subMonth(1);

        $month = $today->format('n');
        $year  = $today->format('Y');
        $beforeMonth = $before->format('n');
        $beforeYear  = $before->format('Y');

        $beforePaidLeaveChanges = PaidLeaveChange::selectRaw('
            client_id, client_employee_id,
            SUM(IF(changed_ammount < 0, ABS(changed_ammount), 0)) AS used_balance,
            SUM(IF(changed_ammount > 0, ABS(changed_ammount), 0)) AS added_balance
        ')
            ->where('month', $beforeMonth)
            ->where('year', $beforeYear)
            ->where('category', 'year_leave')
            ->groupBy('client_id', 'client_employee_id')->get();

        $todayPaidLeaveChanges = PaidLeaveChange::selectRaw('
            client_id, client_employee_id,
            SUM(IF(changed_ammount < 0, ABS(changed_ammount), 0)) AS used_balance,
            SUM(IF(changed_ammount > 0, ABS(changed_ammount), 0)) AS added_balance
        ')
            ->where('month', $month)
            ->where('year', $year)
            ->where('category', 'year_leave')
            ->groupBy('client_id', 'client_employee_id')->get();

        logger('@beforePaidLeaveChanges', [$beforeMonth, $beforeYear, $beforePaidLeaveChanges]);
        logger('@todayPaidLeaveChanges', [$month, $year, $todayPaidLeaveChanges]);

        foreach ($beforePaidLeaveChanges as &$b) {

            $beforeBalance = PaidLeaveBalance::where('client_id', $b->client_id)
                ->where('client_employee_id', $b->client_employee_id)
                ->where('month', $beforeMonth)
                ->where('year', $beforeYear)->first();

            $beginBalance = !empty($beforeBalance) ? $beforeBalance->begin_balance : 0;
            $b->end_balance = $beginBalance - $b->used_balance + $b->added_balance;

            PaidLeaveBalance::where('client_id', $b->client_id)
                ->where('client_employee_id', $b->client_employee_id)
                ->where('month', $beforeMonth)
                ->where('year', $beforeYear)
                ->update([
                    'used_balance' => $b->used_balance,
                    'added_balance' => $b->added_balance,
                    'end_balance' => $b->end_balance,
                ]);
        }

        foreach ($todayPaidLeaveChanges as $t) {

            $beforeBalance = $beforePaidLeaveChanges->where('client_id', $t->client_id)->where('client_employee_id', $t->client_employee_id)->first();

            $beforeEndBalance = $beforeBalance ? $beforeBalance->end_balance : 0;

            PaidLeaveBalance::updateOrCreate(
                [
                    'client_id' => $t->client_id,
                    'client_employee_id' => $t->client_employee_id,
                    'month' => $month,
                    'year' => $year,
                ],
                [
                    'begin_balance' => $beforeEndBalance,
                    'used_balance' => 0,
                    'added_balance' => 0,
                    'end_balance' => $beforeEndBalance,
                ]
            );
        }
    }
}
