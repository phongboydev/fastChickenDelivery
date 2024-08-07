<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use App\Models\LeaveCategory;
use App\Models\PaidLeaveChange;
use App\Support\Constant;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Console\Command;

class LeaveManagementCarryForwardEntitlement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave-management:carry-forward-entitlement {--id=* : The ID of the client}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Carry forward entitlement';

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
     * @return int
     */
    public function handle()
    {

        $year = date('Y') + 1;

        ClientEmployee::withoutEvents(function () use ($year) {
            ClientEmployee::chunk(100, function ($employees) use ($year) {
                foreach ($employees as $key => $employee) {
                    $leaveCategory = LeaveCategory::where([
                        "type" => 'authorized_leave',
                        'sub_type' => 'year_leave',
                        "year" => $year,
                        'client_id' => $employee->client_id,

                    ])->where("entitlement_next_year", ">", 0)->first();

                    $remainingEntitlement = $employee->year_paid_leave_count;
                    $yearPaidLeaveCount = 0;

                    if ($leaveCategory) {

                        $entitlementNextYear = $leaveCategory->entitlement_next_year;

                        if ($remainingEntitlement > 0) {

                            $lastYearPaidLeaveCount = min($remainingEntitlement, $entitlementNextYear);
                            $yearPaidLeaveExpiry = $lastYearPaidLeaveCount - $remainingEntitlement;

                            PaidLeaveChange::withoutEvents(function () use ($year, $yearPaidLeaveExpiry, $lastYearPaidLeaveCount, $leaveCategory, $employee) {
                                PaidLeaveChange::create([
                                    'id' => Str::uuid(),
                                    'client_id' => $leaveCategory->client_id,
                                    'client_employee_id' => $employee->id,
                                    'work_time_register_id' => NULL,
                                    'category' => 'year_leave',
                                    'year_leave_type' => 1,
                                    'changed_ammount' => $yearPaidLeaveExpiry,
                                    'changed_reason' => Constant::TYPE_SYSTEM,
                                    'changed_comment' => "Expired old year leave {$yearPaidLeaveExpiry} hours, carried forward {$lastYearPaidLeaveCount} hours can be used until {$leaveCategory->entitlement_next_year_effective_date} 23:59:59",
                                    'effective_at' => now(),
                                    'month' => 1,
                                    'year' => $year,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            });

                            $nextYearPaidLeaveCount = $employee->next_year_paid_leave_count;
                            if ($nextYearPaidLeaveCount > 0) {
                                $yearPaidLeaveCount = $nextYearPaidLeaveCount;
                            }

                            $employee->update([
                                'last_year_paid_leave_count' => $lastYearPaidLeaveCount,
                                'last_year_paid_leave_expiry' => $leaveCategory->entitlement_next_year_effective_date . " 23:59:59",
                                'year_paid_leave_count' => $yearPaidLeaveCount,
                                'year_paid_leave_expiry' => $year . "-12-31 23:59:59",
                                'next_year_paid_leave_count' => 0,
                                'next_year_paid_leave_expiry' => NULL,
                            ]);

                            $this->info("Processed: [{$employee->id} -- {$employee->code}] {$employee->full_name}" . " | Last Year: " . $lastYearPaidLeaveCount . " | This Year: {$yearPaidLeaveCount} | Expiry: {$yearPaidLeaveExpiry}");
                            continue;
                        }
                    } else {

                        PaidLeaveChange::withoutEvents(function () use ($year, $remainingEntitlement, $employee) {
                            $yearPaidLeaveExpiry = -1 * $remainingEntitlement;
                            PaidLeaveChange::create([
                                'id' => Str::uuid(),
                                'client_id' => $employee->client_id,
                                'client_employee_id' => $employee->id,
                                'work_time_register_id' => NULL,
                                'category' => 'year_leave',
                                'year_leave_type' => 1,
                                'changed_ammount' => $yearPaidLeaveExpiry,
                                'changed_reason' => Constant::TYPE_SYSTEM,
                                'changed_comment' => "Expired old year leave {$remainingEntitlement} hours. No entitlement for next year.",
                                'effective_at' => now(),
                                'month' => 1,
                                'year' => $year,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        });

                        $employee->update([
                            'last_year_paid_leave_count' => 0,
                            'last_year_paid_leave_expiry' => NULL,
                            'year_paid_leave_count' => $yearPaidLeaveCount,
                            'year_paid_leave_expiry' => $year . "-12-31 23:59:59",
                            'next_year_paid_leave_count' => 0,
                            'next_year_paid_leave_expiry' => NULL,
                        ]);
                    }

                    $this->info("Processed: [{$employee->id} -- {$employee->code}] {$employee->full_name}");
                }
            });
        });
        return 0;
    }
}
