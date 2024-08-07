<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use Carbon\Carbon;
use Illuminate\Console\Command;

class LeaveManagementRefreshDateIncreasePaidLeave extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave-management:refresh-date-increase-paid-leave';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Date Increase Paid Leave';

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
        $currentDate = Carbon::now()->format('Y-m-d');

        ClientEmployee::whereNotNull('case_import_paidleave')
            ->chunkById(100, function ($employees) use ($currentDate) {
                foreach ($employees as $employee) {
                    switch ($employee['case_import_paidleave']) {
                        case 'tang_hang_thang':
                            $startedImportPaidleave = Carbon::parse($employee['started_import_paidleave']);

                            if ($startedImportPaidleave->month == 4 && $startedImportPaidleave->year == 2024) {
                                $renewStartedImportPaidleave = $startedImportPaidleave->addMonth()->startOfMonth();
                                $employee->update(['started_import_paidleave' => $renewStartedImportPaidleave]);
                            } elseif ($startedImportPaidleave->gt($currentDate)) {
                                $renewStartedImportPaidleave = $startedImportPaidleave->startOfMonth();
                                $employee->update(['started_import_paidleave' => $renewStartedImportPaidleave]);
                            }
                            break;
                        case 'tang_hang_nam':
                            // Nếu là năm thì chuyển về ngày đầu tiên của năm
                            $startedImportPaidleave = Carbon::parse($employee['started_import_paidleave']);
                            $renewStartedImportPaidleave = $startedImportPaidleave->startOfYear();
                            $employee->update(['started_import_paidleave' => $renewStartedImportPaidleave]);
                            break;
                    }
                }
            }, 'id');
    }
}
