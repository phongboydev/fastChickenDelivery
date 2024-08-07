<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use Illuminate\Console\Command;
use Carbon\Carbon;


class LeaveManagementSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave-management:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize the logic on the monthly/year of annual hours';


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
        ClientEmployee::chunkById(config('app.paidleave_change_chunks', 600), function ($employees) {
            foreach ($employees as $employee) {
                $incrementDate = Carbon::parse($employee['started_import_paidleave']);
                $nextIncrementDate = $incrementDate->addMonth();
                $currentDate = Carbon::now();

                if ($employee['case_import_paidleave'] === 'tang_hang_thang') {
                    if ($currentDate->gt($nextIncrementDate)) {
                        // Đã tăng trong quá khứ
                        $this->info("Tăng hằng tháng: {$employee['full_name']} {$employee['start_import_paidleave']} {$employee['started_import_paidleave']}");

                        $this->error($incrementDate->startOfMonth()->format('Y-m-d'));
                        $this->newLine();

                        $employee->started_import_paidleave = $incrementDate->startOfMonth()->format('Y-m-d');
                        $employee->save();
                    } else {
                        // Có thể tăng trong tương lai
                        $this->info("Có thể tăng trong tương lai: {$employee['full_name']} {$employee['start_import_paidleave']} {$employee['started_import_paidleave']}");
                        $this->error($nextIncrementDate->startOfMonth()->format('Y-m-d'));
                    }
                } elseif ($employee['case_import_paidleave'] === 'tang_hang_nam') {
                    // $this->info("Tăng hằng năm: {$employee['full_name']} {$employee['start_import_paidleave']} {$employee['started_import_paidleave']}");
                    // $this->error($startedImportPaidLeave->startOfYear()->format('Y-m-d'));
                }
            }
        });
    }
}
