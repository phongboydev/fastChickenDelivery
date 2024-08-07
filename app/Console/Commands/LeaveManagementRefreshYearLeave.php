<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClientEmployee;

class LeaveManagementRefreshYearLeave extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave-management:refresh-year-leave';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Year Leave';

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
        ClientEmployee::withoutEvents(function () {
            ClientEmployee::chunk(100, function ($employees) {
                foreach ($employees as $employee) {
                    $employee->update(['year_paid_leave_expiry' => now()->endOfYear()]);
                }
            });
        });
    }
}
